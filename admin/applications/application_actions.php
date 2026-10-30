<?php
// English comments only
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/../../config/db_connect.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'fetch_applications':
        header('Content-Type: application/json; charset=utf-8');
        fetchApplications($pdo);
        break;

    case 'update_status':
        header('Content-Type: application/json; charset=utf-8');
        updateApplicationStatus($pdo);
        break;

    case 'export_excel':
        exportApplicationsToExcel($pdo);
        break;

    default:
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'Invalid action parameter']);
        break;
}

// 1. Fetch leave applications with nurse details & rejection reasons
function fetchApplications($pdo) {
    try {
        $statusFilter = $_GET['status'] ?? 'All';

        $query = "
            SELECT 
                r.requestID,
                r.nurseID,
                r.requestDate,
                r.startTime,
                r.endTime,
                r.status,
                r.reason,
                r.rejectionReason,
                r.createdAt,
                r.adminID,
                n.firstName,
                n.lastName,
                n.email,
                n.nursePhotoURL,
                a.firstName AS adminFirstName,
                a.lastName AS adminLastName
            FROM nurseRequestOffDay r
            LEFT JOIN nurses n ON r.nurseID = n.nurseID
            LEFT JOIN admins a ON r.adminID = a.adminID
        ";

        $params = [];
        if ($statusFilter !== 'All') {
            $query .= " WHERE r.status = ?";
            $params[] = strtolower($statusFilter);
        }

        $query .= " ORDER BY r.createdAt DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($rows as $row) {
            $formattedNurseId = 'NUR-' . str_pad($row['nurseID'], 3, '0', STR_PAD_LEFT);
            $rawPhoto = $row['nursePhotoURL'] ?? '';
            $cleanPhoto = !empty($rawPhoto) ? basename($rawPhoto) : '';
            $avatarPath = !empty($cleanPhoto) ? "/StepCare/images/avatars/nurse/{$cleanPhoto}" : null;

            $results[] = [
                'requestId' => (int)$row['requestID'],
                'nurseId' => $formattedNurseId,
                'rawNurseId' => (int)$row['nurseID'],
                'nurseName' => trim(($row['firstName'] ?? '') . ' ' . ($row['lastName'] ?? 'Unknown Nurse')),
                'email' => $row['email'] ?? 'N/A',
                'photo' => $avatarPath,
                'requestDate' => $row['requestDate'],
                'startTime' => date('H:i', strtotime($row['startTime'])),
                'endTime' => date('H:i', strtotime($row['endTime'])),
                'reason' => $row['reason'] ?: 'No explanation provided.',
                'rejectionReason' => $row['rejectionReason'] ?? '',
                'status' => strtolower($row['status']),
                'submittedAt' => date('M d, Y H:i', strtotime($row['createdAt'])),
                'reviewedBy' => $row['adminFirstName'] ? trim($row['adminFirstName'] . ' ' . ($row['adminLastName'] ?? '')) : null
            ];
        }

        echo json_encode(['status' => 'success', 'data' => $results]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 2. Approve or Reject application with Audit Comment support
function updateApplicationStatus($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        $requestId       = (int)($input['requestId'] ?? 0);
        $newStatus       = trim($input['status'] ?? '');
        $rejectionReason = trim($input['rejectionReason'] ?? '');
        $adminId         = $_SESSION['adminID'] ?? 1;

        if (!$requestId || !in_array($newStatus, ['approved', 'rejected'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request payload']);
            return;
        }

        // Fetch application before updating
        $stmt = $pdo->prepare("SELECT nurseID, requestDate FROM nurseRequestOffDay WHERE requestID = ? LIMIT 1");
        $stmt->execute([$requestId]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$app) {
            echo json_encode(['status' => 'error', 'message' => 'Application record not found']);
            return;
        }

        // Update application status & rejection reason
        $reasonToStore = ($newStatus === 'rejected') ? ($rejectionReason ?: 'Scheduling conflicts / Operational requirements') : null;
        $updateStmt = $pdo->prepare("UPDATE nurseRequestOffDay SET status = ?, rejectionReason = ?, adminID = ? WHERE requestID = ?");
        $updateStmt->execute([$newStatus, $reasonToStore, $adminId, $requestId]);

        // Insert notification for the nurse
        $readableDate = date('M d, Y', strtotime($app['requestDate']));
        $notifTitle   = ($newStatus === 'approved') ? "Off-Day Request Approved" : "Off-Day Request Declined";
        $notifMessage = ($newStatus === 'approved')
            ? "Your off-day request for {$readableDate} has been approved by administration."
            : "Your off-day request for {$readableDate} was declined. Reason: " . $reasonToStore;

        $notifStmt = $pdo->prepare("
            INSERT INTO notifications (recipientType, recipientID, title, message, type, isRead)
            VALUES ('nurse', ?, ?, ?, 'leave_status', 0)
        ");
        $notifStmt->execute([$app['nurseID'], $notifTitle, $notifMessage]);

        echo json_encode([
            'status' => 'success', 
            'message' => "Application has been successfully marked as {$newStatus}."
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 3. Export Leave Applications to Excel (CSV with UTF-8 BOM)
function exportApplicationsToExcel($pdo) {
    $statusFilter = $_GET['status'] ?? 'All';
    $filename = "StepCare_Applications_Export_" . date('Ymd_His') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel

    fputcsv($output, ['Application ID', 'Nurse ID', 'Nurse Name', 'Target Date', 'Start Time', 'End Time', 'Nurse Reason', 'Status', 'Rejection Reason', 'Reviewed By', 'Submission Date']);

    try {
        $query = "
            SELECT 
                r.requestID,
                r.nurseID,
                r.requestDate,
                r.startTime,
                r.endTime,
                r.status,
                r.reason,
                r.rejectionReason,
                r.createdAt,
                n.firstName,
                n.lastName,
                a.firstName AS adminFirstName,
                a.lastName AS adminLastName
            FROM nurseRequestOffDay r
            LEFT JOIN nurses n ON r.nurseID = n.nurseID
            LEFT JOIN admins a ON r.adminID = a.adminID
        ";

        $params = [];
        if ($statusFilter !== 'All') {
            $query .= " WHERE r.status = ?";
            $params[] = strtolower($statusFilter);
        }
        $query .= " ORDER BY r.createdAt DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                'APP-' . str_pad($row['requestID'], 4, '0', STR_PAD_LEFT),
                'NUR-' . str_pad($row['nurseID'], 3, '0', STR_PAD_LEFT),
                trim(($row['firstName'] ?? '') . ' ' . ($row['lastName'] ?? '')),
                $row['requestDate'] ?? '',
                date('H:i', strtotime($row['startTime'])),
                date('H:i', strtotime($row['endTime'])),
                $row['reason'] ?? '',
                strtoupper($row['status'] ?? ''),
                $row['rejectionReason'] ?? 'N/A',
                $row['adminFirstName'] ? trim($row['adminFirstName'] . ' ' . ($row['adminLastName'] ?? '')) : 'Pending',
                $row['createdAt'] ?? ''
            ]);
        }
    } catch (Exception $e) {
        fputcsv($output, ['Export Error: ' . $e->getMessage()]);
    }

    fclose($output);
    exit;
}
?>