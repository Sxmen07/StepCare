<?php
// English comments only
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db_connect.php';

// Resolve current nurseID robustly
$currentNurseId = $_SESSION['nurseID'] ?? 0;
if (!$currentNurseId) {
    try {
        // If session not set, try to get the nurse from the latest request or first nurse
        $latest = $pdo->query("SELECT nurseID FROM nurseRequestOffDay ORDER BY requestID DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($latest && !empty($latest['nurseID'])) {
            $currentNurseId = (int)$latest['nurseID'];
        } else {
            $firstNurse = $pdo->query("SELECT nurseID FROM nurses ORDER BY nurseID ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $currentNurseId = $firstNurse ? (int)$firstNurse['nurseID'] : 1;
        }
        $_SESSION['nurseID'] = $currentNurseId;
    } catch (Exception $e) {
        $currentNurseId = 1;
    }
}

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

// 1. Fetch current nurse's leave requests (Fully Case-Insensitive)
function fetchMyLeaveApplications($pdo, $nurseId) {
    try {
        $statusFilter = strtolower(trim($_GET['status'] ?? 'all'));

        $query = "
            SELECT 
                r.requestID,
                r.nurseID,
                r.requestDate,
                r.startTime,
                r.endTime,
                LOWER(r.status) AS status,
                r.reason,
                r.rejectionReason,
                r.proofDocumentURL,
                r.createdAt,
                a.firstName AS adminFirstName,
                a.lastName AS adminLastName
            FROM nurseRequestOffDay r
            LEFT JOIN admins a ON r.adminID = a.adminID
            WHERE (r.nurseID = ? OR ? = 0)
        ";

        $params = [$nurseId, $nurseId];

        if ($statusFilter !== 'all' && !empty($statusFilter)) {
            $query .= " AND LOWER(r.status) = ?";
            $params[] = $statusFilter;
        }

        $query .= " ORDER BY r.requestID DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($rows as $row) {
            $startTime = !empty($row['startTime']) ? date('H:i', strtotime($row['startTime'])) : '00:00';
            $endTime   = !empty($row['endTime']) ? date('H:i', strtotime($row['endTime'])) : '23:59';
            
            $isFullDay = ($startTime === '00:00' && ($endTime === '23:59' || $endTime === '23:59:00' || $endTime === '23:59:59'));
            $timeDisplay = $isFullDay ? 'Full Day' : "{$startTime} - {$endTime}";

            // Decode proof documents
            $proofFiles = [];
            if (!empty($row['proofDocumentURL'])) {
                $decoded = json_decode($row['proofDocumentURL'], true);
                if (is_array($decoded)) {
                    $proofFiles = $decoded;
                } else {
                    $proofFiles = explode(',', $row['proofDocumentURL']);
                }
            }

            $results[] = [
                'requestId' => (int)$row['requestID'],
                'requestDate' => $row['requestDate'],
                'isFullDay' => $isFullDay,
                'timeDisplay' => $timeDisplay,
                'startTime' => $startTime,
                'endTime' => $endTime,
                'reason' => $row['reason'] ?: 'No explanation specified',
                'rejectionReason' => $row['rejectionReason'] ?? '',
                'proofFiles' => array_values(array_filter($proofFiles)),
                'status' => $row['status'],
                'submittedAt' => !empty($row['createdAt']) ? date('M d, Y H:i', strtotime($row['createdAt'])) : 'Recent',
                'reviewedBy' => $row['adminFirstName'] ? trim($row['adminFirstName'] . ' ' . ($row['adminLastName'] ?? '')) : null
            ];
        }

        echo json_encode(['status' => 'success', 'data' => $results]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 2. Submit new off-day with Multiple Proof Files
function submitLeaveApplication($pdo, $nurseId) {
    try {
        $requestDate = trim($_POST['requestDate'] ?? '');
        $isFullDay   = isset($_POST['isFullDay']) && $_POST['isFullDay'] === 'true';
        $reason      = trim($_POST['reason'] ?? '');

        if (empty($requestDate) || empty($reason)) {
            echo json_encode(['status' => 'error', 'message' => 'Please provide the target date and reason.']);
            return;
        }

        if ($isFullDay) {
            $startTime = '00:00:00';
            $endTime   = '23:59:00';
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

        // Upload multiple files
        $uploadedFilePaths = [];
        $uploadDir = __DIR__ . '/proof_doc/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }

        if (isset($_FILES['proofDocuments'])) {
            $files = $_FILES['proofDocuments'];
            $fileCount = is_array($files['name']) ? count($files['name']) : 0;
            $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

            for ($i = 0; $i < $fileCount; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                    if (in_array($ext, $allowedExtensions)) {
                        $dateSubmitted = date('Y-m-d_His');
                        $newFileName = "{$nurseId}_{$dateSubmitted}_" . ($i + 1) . ".{$ext}";
                        $destination = $uploadDir . $newFileName;

                        if (move_uploaded_file($files['tmp_name'][$i], $destination)) {
                            $uploadedFilePaths[] = "/StepCare/nurse/leave-applications/proof_doc/{$newFileName}";
                        }
                    }
                }
            }
        }

        $proofJson = !empty($uploadedFilePaths) ? json_encode($uploadedFilePaths) : null;

        // Insert record (explicitly as 'pending')
        $stmt = $pdo->prepare("
            INSERT INTO nurseRequestOffDay (nurseID, requestDate, startTime, endTime, reason, proofDocumentURL, status, createdAt)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$nurseId, $requestDate, $startTime, $endTime, $reason, $proofJson]);

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

        // Check and delete
        $stmtSelect = $pdo->prepare("SELECT proofDocumentURL FROM nurseRequestOffDay WHERE requestID = ? AND LOWER(status) = 'pending'");
        $stmtSelect->execute([$requestId]);
        $row = $stmtSelect->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(['status' => 'error', 'message' => 'Unable to cancel: Request is already reviewed or invalid.']);
            return;
        }

        $stmt = $pdo->prepare("DELETE FROM nurseRequestOffDay WHERE requestID = ? AND LOWER(status) = 'pending'");
        $stmt->execute([$requestId]);

        if (!empty($row['proofDocumentURL'])) {
            $files = json_decode($row['proofDocumentURL'], true) ?: explode(',', $row['proofDocumentURL']);
            foreach ($files as $f) {
                $localFile = __DIR__ . '/proof_doc/' . basename($f);
                if (file_exists($localFile)) {
                    @unlink($localFile);
                }
            }
        }

        echo json_encode(['status' => 'success', 'message' => 'Application withdrawn successfully.']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>