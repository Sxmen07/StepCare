<?php
// English comments only
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db_connect.php';

// 1. Resolve active nurse ID safely from session or first available nurse record
$currentNurseId = $_SESSION['nurseID'] ?? 0;

if (!$currentNurseId) {
    try {
        $firstNurse = $pdo->query("SELECT nurseID FROM nurses ORDER BY nurseID ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $currentNurseId = $firstNurse ? (int)$firstNurse['nurseID'] : 1;
        $_SESSION['nurseID'] = $currentNurseId;
    } catch (Exception $e) {
        $currentNurseId = 1;
    }
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'fetch_dashboard_data':
        fetchDashboardData($pdo, $currentNurseId);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action parameter']);
        break;
}

function fetchDashboardData($pdo, $nurseId) {
    try {
        $today = date('Y-m-d');

        // 1. Fetch Nurse Identity
        $nurseStmt = $pdo->prepare("SELECT firstName, lastName FROM nurses WHERE nurseID = ? LIMIT 1");
        $nurseStmt->execute([$nurseId]);
        $nurseRow = $nurseStmt->fetch(PDO::FETCH_ASSOC);
        
        $nurseName = 'Nurse';
        if ($nurseRow) {
            $nurseName = trim(($nurseRow['firstName'] ?? '') . ' ' . ($nurseRow['lastName'] ?? ''));
            if (empty($nurseName)) $nurseName = 'Nurse';
        }

        // 2. Fetch Revenue & Basic Stats
        $totalRevenue = 0.00;
        $monthlyRevenue = 0.00;
        $completedCount = 0;

        try {
            // Count completed cares (FIXED: status -> appointmentStatus)
            $compStmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE nurseID = ? AND appointmentStatus = 'completed'");
            $compStmt->execute([$nurseId]);
            $completedCount = (int)$compStmt->fetchColumn();

            // Calculate payments (FIXED: status -> appointmentStatus, amount -> totalAmount)
            // Added 15% deduction (* 0.85) to match the history page logic
            $revStmt = $pdo->prepare("
                SELECT 
                    COALESCE(SUM(p.totalAmount * 0.85), 0) AS totalRev,
                    COALESCE(SUM(CASE WHEN DATE(p.paymentDate) >= DATE_FORMAT(NOW(), '%Y-%m-01') THEN p.totalAmount * 0.85 ELSE 0 END), 0) AS monthRev
                FROM payments p
                INNER JOIN appointments a ON p.appointmentID = a.appointmentID
                WHERE a.nurseID = ? AND a.appointmentStatus = 'completed'
            ");
            $revStmt->execute([$nurseId]);
            $revData = $revStmt->fetch(PDO::FETCH_ASSOC);
            if ($revData) {
                $totalRevenue = (float)$revData['totalRev'];
                $monthlyRevenue = (float)$revData['monthRev'];
            }
        } catch (Exception $ex) {
            $totalRevenue = 0.00;
            $monthlyRevenue = 0.00;
        }

        // 3. Fetch Today's Appointments (FIXED: appointmentDate -> DATE(startDateTime), status -> appointmentStatus)
        $todayApps = [];
        try {
            $appStmt = $pdo->prepare("
                SELECT 
                    a.appointmentID,
                    a.startDateTime,
                    a.appointmentStatus,
                    COALESCE(s.price, 0) AS price,
                    u.firstName AS userFirstName,
                    u.lastName AS userLastName,
                    s.serviceName
                FROM appointments a
                LEFT JOIN users u ON a.userID = u.userID
                LEFT JOIN services s ON a.serviceID = s.serviceID
                WHERE a.nurseID = ? AND DATE(a.startDateTime) = ?
                ORDER BY a.startDateTime ASC
            ");
            $appStmt->execute([$nurseId, $today]);

            while ($row = $appStmt->fetch(PDO::FETCH_ASSOC)) {
                $todayApps[] = [
                    'id' => 'APT-' . str_pad($row['appointmentID'], 4, '0', STR_PAD_LEFT),
                    'patient' => trim(($row['userFirstName'] ?? '') . ' ' . ($row['userLastName'] ?? 'Client')),
                    'service' => $row['serviceName'] ?? 'Nursing Care',
                    'time' => date('h:i A', strtotime($row['startDateTime'])),
                    'price' => 'RM ' . number_format((float)$row['price'], 2),
                    'status' => ucfirst(str_replace('_', ' ', strtolower($row['appointmentStatus'] ?? 'Pending')))
                ];
            }
        } catch (Exception $e) {
            $todayApps = [];
        }

        // 4. Fetch Notifications for Nurse
        $notifications = [];
        try {
            $notifStmt = $pdo->prepare("
                SELECT notificationID, title, message, type, isRead, createdAt
                FROM notifications
                WHERE recipientType = 'nurse' AND recipientID = ?
                ORDER BY createdAt DESC
                LIMIT 10
            ");
            $notifStmt->execute([$nurseId]);

            while ($row = $notifStmt->fetch(PDO::FETCH_ASSOC)) {
                $notifications[] = [
                    'id' => (int)$row['notificationID'],
                    'title' => $row['title'],
                    'message' => $row['message'],
                    'type' => $row['type'],
                    'isRead' => (bool)$row['isRead'],
                    'time' => date('M d, H:i', strtotime($row['createdAt']))
                ];
            }
        } catch (Exception $e) {
            $notifications = [];
        }

        echo json_encode([
            'status' => 'success',
            'data' => [
                'nurseName' => $nurseName,
                'todayDate' => date('l, F j, Y'),
                'revenueStats' => [
                    'totalRevenue' => number_format($totalRevenue, 2),
                    'monthlyRevenue' => number_format($monthlyRevenue, 2),
                    'todayCount' => count($todayApps),
                    'completedCount' => $completedCount
                ],
                'todayAppointments' => $todayApps,
                'notifications' => $notifications
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>