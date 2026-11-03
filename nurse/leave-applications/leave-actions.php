<?php
// English comments only
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db_connect.php';

// Session nurseID fallback for testing / development
$currentNurseId = $_SESSION['nurseID'] ?? 1;

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'fetch_my_leaves':
        fetchMyLeaveApplications($pdo, $currentNurseId);
        break;

    case 'submit_leave':
        submitLeaveApplication($pdo, $currentNurseId);
        break;

    case 'cancel_leave':
        cancelLeaveApplication($pdo, $currentNurseId);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action parameter']);
        break;
}

// 1. Fetch current nurse's leave requests
function fetchMyLeaveApplications($pdo, $nurseId) {
    try {
        $statusFilter = $_GET['status'] ?? 'All';

        $query = "
            SELECT 
                r.requestID,
                r.requestDate,
                r.startTime,
                r.endTime,
                r.status,
                r.reason,
                r.rejectionReason,
                r.proofDocumentURL,
                r.createdAt,
                a.firstName AS adminFirstName,
                a.lastName AS adminLastName
            FROM nurseRequestOffDay r
            LEFT JOIN admins a ON r.adminID = a.adminID
            WHERE r.nurseID = ?
        ";

        $params = [$nurseId];
        if ($statusFilter !== 'All') {
            $query .= " AND r.status = ?";
            $params[] = strtolower($statusFilter);
        }

        $query .= " ORDER BY r.createdAt DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($rows as $row) {
            $isFullDay = ($row['startTime'] === '00:00:00' && ($row['endTime'] === '23:59:59' || $row['endTime'] === '23:59:00'));
            $timeDisplay = $isFullDay ? 'Full Day' : date('H:i', strtotime($row['startTime'])) . ' - ' . date('H:i', strtotime($row['endTime']));

            $results[] = [
                'requestId' => (int)$row['requestID'],
                'requestDate' => $row['requestDate'],
                'isFullDay' => $isFullDay,
                'timeDisplay' => $timeDisplay,
                'startTime' => date('H:i', strtotime($row['startTime'])),
                'endTime' => date('H:i', strtotime($row['endTime'])),
                'reason' => $row['reason'] ?: 'No explanation specified',
                'rejectionReason' => $row['rejectionReason'] ?? '',
                'proofDocumentURL' => $row['proofDocumentURL'] ?? null,
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

// 2. Submit new off-day / leave request with proof document upload
function submitLeaveApplication($pdo, $nurseId) {
    try {
        $requestDate = trim($_POST['requestDate'] ?? '');
        $isFullDay   = isset($_POST['isFullDay']) && $_POST['isFullDay'] === 'true';
        $reason      = trim($_POST['reason'] ?? '');

        if (empty($requestDate) || empty($reason)) {
            echo json_encode(['status' => 'error', 'message' => 'Please provide the target date and reason.']);
            return;
        }

        // Prevent selecting past dates
        if (strtotime($requestDate) < strtotime(date('Y-m-d'))) {
            echo json_encode(['status' => 'error', 'message' => 'Cannot request leave on past dates.']);
            return;
        }

        if ($isFullDay) {
            $startTime = '00:00:00';
            $endTime   = '23:59:59';
        } else {
            $startTime = trim($_POST['startTime'] ?? '');
            $endTime   = trim($_POST['endTime'] ?? '');

            if (empty($startTime) || empty($endTime)) {
                echo json_encode(['status' => 'error', 'message' => 'Please select the specific leave hours.']);
                return;
            }

            if (strtotime($startTime) >= strtotime($endTime)) {
                echo json_encode(['status' => 'error', 'message' => 'Start time must precede end time.']);
                return;
            }
        }

        // Check for active or approved duplicate on the same date
        $checkStmt = $pdo->prepare("
            SELECT requestID FROM nurseRequestOffDay 
            WHERE nurseID = ? AND requestDate = ? AND status IN ('pending', 'approved') 
            LIMIT 1
        ");
        $checkStmt->execute([$nurseId, $requestDate]);
        if ($checkStmt->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'You already have an active or approved request for this date.']);
            return;
        }

        // Handle Proof Document File Upload
        $proofPath = null;
        if (isset($_FILES['proofDocument']) && $_FILES['proofDocument']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['proofDocument'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
            if (!in_array($ext, $allowedExtensions)) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid file format. Allowed: PDF, JPG, PNG, DOC, DOCX.']);
                return;
            }

            // Create target folder if not exists
            $uploadDir = __DIR__ . '/proof_doc/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Naming convention: [ID]_[DATESUBMITTED].[ext]
            $dateSubmitted = date('Y-m-d_His');
            $newFileName = "{$nurseId}_{$dateSubmitted}.{$ext}";
            $destination = $uploadDir . $newFileName;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $proofPath = "/StepCare/nurse/leave-applications/proof_doc/{$newFileName}";
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to save uploaded document.']);
                return;
            }
        }

        // Insert leave record
        $stmt = $pdo->prepare("
            INSERT INTO nurseRequestOffDay (nurseID, requestDate, startTime, endTime, reason, proofDocumentURL, status, createdAt)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$nurseId, $requestDate, $startTime, $endTime, $reason, $proofPath]);

        echo json_encode(['status' => 'success', 'message' => 'Leave application submitted successfully.']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 3. Cancel pending application
function cancelLeaveApplication($pdo, $nurseId) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $requestId = (int)($input['requestId'] ?? 0);

        if (!$requestId) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request target.']);
            return;
        }

        // Get proof file to delete on withdraw
        $stmtSelect = $pdo->prepare("SELECT proofDocumentURL FROM nurseRequestOffDay WHERE requestID = ? AND nurseID = ? AND status = 'pending'");
        $stmtSelect->execute([$requestId, $nurseId]);
        $row = $stmtSelect->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(['status' => 'error', 'message' => 'Unable to cancel: Request is already reviewed or invalid.']);
            return;
        }

        // Delete record
        $stmt = $pdo->prepare("DELETE FROM nurseRequestOffDay WHERE requestID = ? AND nurseID = ? AND status = 'pending'");
        $stmt->execute([$requestId, $nurseId]);

        // Cleanup local proof file
        if (!empty($row['proofDocumentURL'])) {
            $localFile = __DIR__ . '/proof_doc/' . basename($row['proofDocumentURL']);
            if (file_exists($localFile)) {
                @unlink($localFile);
            }
        }

        echo json_encode(['status' => 'success', 'message' => 'Application withdrawn successfully.']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>