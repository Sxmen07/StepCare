<?php
// Strict error suppression in output to ensure clean JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db_connect.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'fetch_my_leaves':
        fetchMyLeaves($pdo);
        break;

    case 'submit_leave':
        submitLeave($pdo);
        break;

    case 'cancel_leave':
        cancelLeave($pdo);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}

function fetchMyLeaves($pdo) {
    try {
        $filter = strtolower(trim($_GET['status'] ?? 'all'));

        // Query without ANY restrictive WHERE or session nurseID filters
        $sql = "
            SELECT 
                r.requestID,
                r.nurseID,
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
        ";

        $params = [];
        if ($filter !== 'all' && !empty($filter)) {
            $sql .= " WHERE LOWER(r.status) = ?";
            $params[] = $filter;
        }

        $sql .= " ORDER BY r.requestID DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $list = [];
        foreach ($rows as $row) {
            $sTime = !empty($row['startTime']) ? substr($row['startTime'], 0, 5) : '00:00';
            $eTime = !empty($row['endTime']) ? substr($row['endTime'], 0, 5) : '23:59';

            $isFullDay = ($sTime === '00:00' && ($eTime === '23:59' || $eTime === '23:59:00'));
            $timeDisplay = $isFullDay ? 'Full Day' : ($sTime . ' - ' . $eTime);

            // Handle Proof Files safely
            $proofFiles = [];
            if (!empty($row['proofDocumentURL'])) {
                $rawDoc = $row['proofDocumentURL'];
                $decoded = json_decode($rawDoc, true);
                if (is_array($decoded)) {
                    $proofFiles = $decoded;
                } else {
                    $proofFiles = [$rawDoc];
                }
            }

            $adminName = trim(($row['adminFirstName'] ?? '') . ' ' . ($row['adminLastName'] ?? ''));

            $list[] = [
                'requestId' => (int)$row['requestID'],
                'nurseId' => (int)$row['nurseID'],
                'requestDate' => (string)($row['requestDate'] ?? ''),
                'timeDisplay' => $timeDisplay,
                'reason' => (string)($row['reason'] ?: 'No explanation provided'),
                'rejectionReason' => (string)($row['rejectionReason'] ?? ''),
                'proofFiles' => array_values(array_filter($proofFiles)),
                'status' => strtolower((string)($row['status'] ?: 'pending')),
                'reviewedBy' => $adminName ?: null
            ];
        }

        echo json_encode([
            'status' => 'success',
            'data' => $list
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}

function submitLeave($pdo) {
    try {
        $nurseId     = $_SESSION['nurseID'] ?? 1;
        $requestDate = trim($_POST['requestDate'] ?? '');
        $isFullDay   = isset($_POST['isFullDay']) && $_POST['isFullDay'] === 'true';
        $reason      = trim($_POST['reason'] ?? '');

        if (empty($requestDate) || empty($reason)) {
            echo json_encode(['status' => 'error', 'message' => 'Date and reason are required.']);
            return;
        }

        if ($isFullDay) {
            $startTime = '00:00:00';
            $endTime   = '23:59:00';
        } else {
            $startTime = trim($_POST['startTime'] ?? '08:00');
            $endTime   = trim($_POST['endTime'] ?? '18:00');
        }

        $uploaded = [];
        $uploadDir = __DIR__ . '/proof_doc/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }

        if (isset($_FILES['proofDocuments'])) {
            $files = $_FILES['proofDocuments'];
            $count = is_array($files['name']) ? count($files['name']) : 0;
            for ($i = 0; $i < $count; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                    $filename = "{$nurseId}_" . date('Y-m-d_His') . "_{$i}.{$ext}";
                    if (move_uploaded_file($files['tmp_name'][$i], $uploadDir . $filename)) {
                        $uploaded[] = "/StepCare/nurse/leave-applications/proof_doc/{$filename}";
                    }
                }
            }
        }

        $proofJson = !empty($uploaded) ? json_encode($uploaded) : null;

        $stmt = $pdo->prepare("
            INSERT INTO nurseRequestOffDay (nurseID, requestDate, startTime, endTime, reason, proofDocumentURL, status, createdAt)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$nurseId, $requestDate, $startTime, $endTime, $reason, $proofJson]);

        echo json_encode(['status' => 'success', 'message' => 'Application submitted successfully.']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

function cancelLeave($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $reqId = (int)($input['requestId'] ?? 0);

        if (!$reqId) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
            return;
        }

        $stmt = $pdo->prepare("DELETE FROM nurseRequestOffDay WHERE requestID = ?");
        $stmt->execute([$reqId]);

        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}