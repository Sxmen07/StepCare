<?php
// English comments only
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db_connect.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'fetch_schedules':
        fetchSchedules($pdo);
        break;

    case 'fetch_available_nurses':
        fetchAvailableNurses($pdo);
        break;

    case 'assign_nurse':
        assignNurseToBooking($pdo);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action parameter']);
        break;
}

// 1. Fetch appointments for dispatching
function fetchSchedules($pdo) {
    try {
        $filter = $_GET['filter'] ?? 'all'; // 'all', 'unscheduled', 'assigned'

        $query = "
            SELECT 
                a.appointmentID,
                a.startDateTime,
                a.endDateTime,
                a.locationAddress,
                a.appointmentStatus,
                a.specialInstructions,
                a.createdAt,
                s.serviceID,
                s.serviceName,
                s.price,
                CONCAT(u.firstName, ' ', u.lastName) AS patientName,
                u.email AS patientEmail,
                u.phoneNumber AS patientPhone,
                a.nurseID,
                CONCAT(n.firstName, ' ', n.lastName) AS nurseName,
                n.phoneNumber AS nursePhone,
                n.specialization AS nurseSpecialization
            FROM appointments a
            JOIN services s ON a.serviceID = s.serviceID
            JOIN users u ON a.userID = u.userID
            LEFT JOIN nurses n ON a.nurseID = n.nurseID
        ";

        $conditions = ["a.appointmentStatus != 'cancelled'"];
        if ($filter === 'unscheduled') {
            $conditions[] = "(a.nurseID IS NULL OR a.nurseID = 0)";
        } elseif ($filter === 'assigned') {
            $conditions[] = "(a.nurseID IS NOT NULL AND a.nurseID > 0)";
        }

        $query .= " WHERE " . implode(' AND ', $conditions);
        $query .= " ORDER BY a.startDateTime ASC";

        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($rows as $row) {
            $isAssigned = (!empty($row['nurseID']) && (int)$row['nurseID'] > 0);
            $results[] = [
                'appointmentId' => (int)$row['appointmentID'],
                'bookingRef' => 'BK-' . str_pad($row['appointmentID'], 4, '0', STR_PAD_LEFT),
                'serviceName' => $row['serviceName'],
                'price' => number_format((float)$row['price'], 2),
                'patientName' => $row['patientName'],
                'patientEmail' => $row['patientEmail'],
                'patientPhone' => $row['patientPhone'] ?? 'N/A',
                'location' => $row['locationAddress'],
                'date' => date('M d, Y', strtotime($row['startDateTime'])),
                'timeRange' => date('H:i', strtotime($row['startDateTime'])) . ' - ' . date('H:i', strtotime($row['endDateTime'])),
                'status' => $row['appointmentStatus'],
                'isAssigned' => $isAssigned,
                'nurseId' => $isAssigned ? (int)$row['nurseID'] : null,
                'nurseName' => $isAssigned ? $row['nurseName'] : 'Unassigned',
                'nurseSpecialization' => $row['nurseSpecialization'] ?? 'General',
                'nursePhone' => $row['nursePhone'] ?? 'N/A'
            ];
        }

        echo json_encode(['status' => 'success', 'data' => $results]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 2. Fetch list of available nurses for assignment
function fetchAvailableNurses($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT nurseID, firstName, lastName, specialization, phoneNumber, averageRating 
            FROM nurses 
            ORDER BY firstName ASC
        ");
        $nurses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $list = [];
        foreach ($nurses as $n) {
            $list[] = [
                'nurseId' => (int)$n['nurseID'],
                'name' => trim($n['firstName'] . ' ' . $n['lastName']),
                'specialization' => $n['specialization'] ?: 'General Practice',
                'phone' => $n['phoneNumber'] ?? 'N/A',
                'rating' => number_format((float)$n['averageRating'], 1)
            ];
        }

        echo json_encode(['status' => 'success', 'data' => $list]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 3. Assign selected nurse to booking and notify nurse
function assignNurseToBooking($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        $appointmentId = (int)($input['appointmentId'] ?? 0);
        $nurseId = (int)($input['nurseId'] ?? 0);
        $backupNurseId = !empty($input['backupNurseId']) ? (int)$input['backupNurseId'] : null;

        if (!$appointmentId || !$nurseId) {
            echo json_encode(['status' => 'error', 'message' => 'Missing booking or nurse identifier']);
            return;
        }

        // 1. Fetch appointment details
        $stmt = $pdo->prepare("SELECT serviceID, userID, startDateTime FROM appointments WHERE appointmentID = ?");
        $stmt->execute([$appointmentId]);
        $appt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$appt) {
            echo json_encode(['status' => 'error', 'message' => 'Appointment not found']);
            return;
        }

        // 2. Update appointment
        $updateStmt = $pdo->prepare("
            UPDATE appointments 
            SET nurseID = ?, backupNurseID = ?, appointmentStatus = 'confirmed' 
            WHERE appointmentID = ?
        ");
        $updateStmt->execute([$nurseId, $backupNurseId, $appointmentId]);

        // 3. Record tracking timeline
        $trackStmt = $pdo->prepare("
            INSERT INTO trackServiceStatus (appointmentID, serviceID, userID, nurseID, status, message)
            VALUES (?, ?, ?, ?, 'assigned', 'Nurse assigned to your appointment by administration.')
        ");
        $trackStmt->execute([$appointmentId, $appt['serviceID'], $appt['userID'], $nurseId]);

        // 4. Send notification to assigned nurse
        $readableDate = date('M d, Y H:i', strtotime($appt['startDateTime']));
        $notifStmt = $pdo->prepare("
            INSERT INTO notifications (recipientType, recipientID, title, message, type, isRead)
            VALUES ('nurse', ?, 'New Appointment Dispatched', ?, 'booking_dispatch', 0)
        ");
        $notifStmt->execute([
            $nurseId,
            "You have been assigned to Booking #{$appointmentId} scheduled on {$readableDate}."
        ]);

        echo json_encode(['status' => 'success', 'message' => 'Nurse successfully assigned to booking']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>