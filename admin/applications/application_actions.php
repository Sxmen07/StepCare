<?php
// English comments only
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db_connect.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'fetch_applications':
        fetchApplications($pdo);
        break;

    case 'update_status':
        updateApplicationStatus($pdo);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action parameter']);
        break;
}

// 1. Fetch leave applications with nurse details
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
            $results[] = [
                'requestId' => (int)$row['requestID'],
                'nurseId' => $formattedNurseId,
                'rawNurseId' => (int)$row['nurseID'],
                'nurseName' => trim(($row['firstName'] ?? '') . ' ' . ($row['lastName'] ?? 'Unknown Nurse')),
                'email' => $row['email'] ?? 'N/A',
                'photo' => $row['nursePhotoURL'] ?? null,
                'requestDate' => $row['requestDate'],
                'startTime' => date('H:i', strtotime($row['startTime'])),
                'endTime' => date('H:i', strtotime($row['endTime'])),
                'reason' => $row['reason'] ?: 'No explanation provided.',
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

// 2. Approve or Reject application and notify nurse
function updateApplicationStatus($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        $requestId = (int)($input['requestId'] ?? 0);
        $newStatus = trim($input['status'] ?? '');
        $adminId   = $_SESSION['adminID'] ?? 1; // Default to active admin ID 1 if test session

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

        // Update application status
        $updateStmt = $pdo->prepare("UPDATE nurseRequestOffDay SET status = ?, adminID = ? WHERE requestID = ?");
        $updateStmt->execute([$newStatus, $adminId, $requestId]);

        // Insert system notification for the applicant nurse
        $readableDate = date('M d, Y', strtotime($app['requestDate']));
        $notifTitle   = ($newStatus === 'approved') 
            ? "Off-Day Request Approved" 
            : "Off-Day Request Rejected";
        $notifMessage = ($newStatus === 'approved')
            ? "Your off-day request for {$readableDate} has been approved by admin."
            : "Your off-day request for {$readableDate} was declined. Please consult administration for details.";

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
?>