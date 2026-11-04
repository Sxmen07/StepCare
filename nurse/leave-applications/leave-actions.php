<?php
// English comments only
// Strict output buffering and error suppression for clean JSON response
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/../../config/db_connect.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Clean and normalize proof document URL
function cleanProofUrl($raw) {
    if (empty($raw)) return null;
    $raw = trim($raw);

    if (strpos($raw, '[') === 0) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && !empty($decoded[0])) {
            return stripslashes($decoded[0]);
        }
    }

    $raw = stripslashes($raw);
    if (strpos($raw, '/StepCare/') === 0 || strpos($raw, 'http') === 0) {
        return $raw;
    }

    return '/StepCare/' . ltrim($raw, '/');
}

// Fallback smart category detection for legacy data without leaveTypeID
function detectLeaveCategory($assignedName, $reason) {
    if (!empty($assignedName)) return $assignedName;
    $r = strtolower($reason ?? '');
    if (strpos($r, 'mc') !== false || strpos($r, 'medical') !== false || strpos($r, 'sick') !== false || strpos($r, 'doctor') !== false || strpos($r, 'fever') !== false || strpos($r, 'clinic') !== false) {
        return 'Medical Leave (MC)';
    }
    if (strpos($r, 'emergency') !== false || strpos($r, 'urgent') !== false || strpos($r, 'family') !== false) {
        return 'Emergency Leave';
    }
    if (strpos($r, 'unpaid') !== false) {
        return 'Unpaid Leave';
    }
    return 'Annual Leave';
}

// Helper: Calculate total, approved used, and remaining quota for current nurse
function getNurseLeaveBalance($pdo, $nurseId, $leaveTypeId) {
    $policyStmt = $pdo->prepare("SELECT daysPerYear FROM leave_types WHERE leaveTypeID = ? LIMIT 1");
    $policyStmt->execute([$leaveTypeId]);
    $totalDays = (int)$policyStmt->fetchColumn();
    if ($totalDays <= 0) $totalDays = 14;

    $currentYear = date('Y');
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) FROM nurseRequestOffDay 
        WHERE nurseID = ? AND leaveTypeID = ? AND LOWER(status) = 'approved' AND YEAR(requestDate) = ?
    ");
    $countStmt->execute([$nurseId, $leaveTypeId, $currentYear]);
    $usedDays = (int)$countStmt->fetchColumn();

    return [
        'total' => $totalDays,
        'used' => $usedDays,
        'remaining' => max(0, $totalDays - $usedDays)
    ];
}

switch ($action) {
    case 'fetch_my_leaves':
        header('Content-Type: application/json; charset=utf-8');
        fetchMyLeaves($pdo);
        break;

    case 'fetch_leave_types':
        header('Content-Type: application/json; charset=utf-8');
        fetchLeaveTypes($pdo);
        break;

    case 'fetch_my_balance':
        header('Content-Type: application/json; charset=utf-8');
        fetchMyLeaveBalance($pdo);
        break;

    case 'submit_leave':
        header('Content-Type: application/json; charset=utf-8');
        submitLeave($pdo);
        break;

    case 'cancel_leave':
        header('Content-Type: application/json; charset=utf-8');
        cancelLeave($pdo);
        break;

    default:
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'Invalid action parameter']);
        break;
}

// 1. Fetch available leave types applicable to nurses
function fetchLeaveTypes($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM leave_types WHERE targetRole IN ('all', 'nurse') ORDER BY leaveTypeID ASC");
        $types = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        echo json_encode(['status' => 'success', 'data' => $types]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 2. Fetch current nurse's live quota balances
function fetchMyLeaveBalance($pdo) {
    try {
        $nurseId = (int)($_SESSION['nurseID'] ?? $_SESSION['user_id'] ?? 1);
        $stmt = $pdo->query("SELECT * FROM leave_types WHERE targetRole IN ('all', 'nurse') ORDER BY leaveTypeID ASC");
        $policies = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $balances = [];
        foreach ($policies as $p) {
            $bal = getNurseLeaveBalance($pdo, $nurseId, (int)$p['leaveTypeID']);
            $balances[] = [
                'leaveTypeID' => (int)$p['leaveTypeID'],
                'leaveName' => $p['leaveName'],
                'total' => $bal['total'],
                'used' => $bal['used'],
                'remaining' => $bal['remaining']
            ];
        }

        echo json_encode(['status' => 'success', 'data' => $balances]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 3. Fetch applications list for current nurse
function fetchMyLeaves($pdo) {
    try {
        $nurseId = (int)($_SESSION['nurseID'] ?? $_SESSION['user_id'] ?? 1);
        $statusFilter = strtolower(trim($_GET['status'] ?? 'all'));

        // Check if leaveTypeID column exists in nurseRequestOffDay
        $hasLeaveTypeCol = false;
        try {
            $colCheck = $pdo->query("SHOW COLUMNS FROM nurseRequestOffDay LIKE 'leaveTypeID'");
            $hasLeaveTypeCol = ($colCheck && $colCheck->rowCount() > 0);
        } catch (Exception $e) {}

        if ($hasLeaveTypeCol) {
            $sql = "
                SELECT 
                    r.requestID,
                    r.nurseID,
                    r.leaveTypeID,
                    r.requestDate,
                    r.startTime,
                    r.endTime,
                    r.status,
                    r.reason,
                    r.proofDocumentURL,
                    r.rejectionReason,
                    r.createdAt,
                    a.firstName AS adminFirstName,
                    a.lastName AS adminLastName,
                    lt.leaveName
                FROM nurseRequestOffDay r
                LEFT JOIN admins a ON r.adminID = a.adminID
                LEFT JOIN leave_types lt ON r.leaveTypeID = lt.leaveTypeID
                WHERE r.nurseID = ?
            ";
        } else {
            $sql = "
                SELECT 
                    r.requestID,
                    r.nurseID,
                    NULL AS leaveTypeID,
                    r.requestDate,
                    r.startTime,
                    r.endTime,
                    r.status,
                    r.reason,
                    r.proofDocumentURL,
                    r.rejectionReason,
                    r.createdAt,
                    a.firstName AS adminFirstName,
                    a.lastName AS adminLastName,
                    NULL AS leaveName
                FROM nurseRequestOffDay r
                LEFT JOIN admins a ON r.adminID = a.adminID
                WHERE r.nurseID = ?
            ";
        }

        $params = [$nurseId];

        if ($statusFilter !== 'all' && !empty($statusFilter)) {
            $sql .= " AND LOWER(r.status) = ?";
            $params[] = $statusFilter;
        }

        $sql .= " ORDER BY r.createdAt DESC, r.requestID DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($rows as $row) {
            $sTime = !empty($row['startTime']) ? substr($row['startTime'], 0, 5) : '00:00';
            $eTime = !empty($row['endTime']) ? substr($row['endTime'], 0, 5) : '00:00';

            // Clean format: strictly display 'Full Day' whenever time is 00:00 - 00:00 or full shift
            $isFullDay = (
                ($sTime === '00:00' && ($eTime === '00:00' || $eTime === '23:59' || $eTime === '23:59:00')) ||
                ($sTime === $eTime) ||
                empty($row['startTime'])
            );
            $timeDisplay = $isFullDay ? 'Full Day' : ($sTime . ' - ' . $eTime);

            $docPath = cleanProofUrl($row['proofDocumentURL'] ?? '');
            $cleanDoc = $docPath ? basename($docPath) : '';
            $categoryName = detectLeaveCategory($row['leaveName'] ?? '', $row['reason'] ?? '');

            // Balance info if leaveTypeID exists
            $balanceInfo = null;
            if (!empty($row['leaveTypeID'])) {
                $balanceInfo = getNurseLeaveBalance($pdo, $nurseId, (int)$row['leaveTypeID']);
            }

            $adminName = trim(($row['adminFirstName'] ?? '') . ' ' . ($row['adminLastName'] ?? ''));

            $results[] = [
                'requestId' => (int)$row['requestID'],
                'leaveName' => $categoryName,
                'leaveTypeID' => !empty($row['leaveTypeID']) ? (int)$row['leaveTypeID'] : null,
                'balanceInfo' => $balanceInfo,
                'requestDate' => $row['requestDate'],
                'startTime' => $sTime,
                'endTime' => $eTime,
                'timeDisplay' => $timeDisplay,
                'isFullDay' => $isFullDay,
                'reason' => (string)($row['reason'] ?: 'No explanation provided.'),
                'proofDocument' => $docPath,
                'proofDocumentName' => $cleanDoc,
                'status' => strtolower((string)$row['status']),
                'rejectionReason' => (string)($row['rejectionReason'] ?? ''),
                'submittedAt' => !empty($row['createdAt']) ? date('M d, Y H:i', strtotime($row['createdAt'])) : date('M d, Y', strtotime($row['requestDate'])),
                'reviewedBy' => $adminName ?: null
            ];
        }

        echo json_encode([
            'status' => 'success',
            'data' => $results
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 4. Submit leave application with file upload to uploads/proof_doc/nurse/
function submitLeave($pdo) {
    try {
        $nurseId     = (int)($_SESSION['nurseID'] ?? $_SESSION['user_id'] ?? 1);
        $requestDate = trim($_POST['requestDate'] ?? '');
        $isFullDay   = isset($_POST['isFullDay']) && ($_POST['isFullDay'] === 'true' || $_POST['isFullDay'] === '1');
        $reason      = trim($_POST['reason'] ?? '');
        $leaveTypeID = !empty($_POST['leaveTypeID']) ? (int)$_POST['leaveTypeID'] : null;

        if (empty($requestDate) || empty($reason)) {
            echo json_encode(['status' => 'error', 'message' => 'Please provide target date and reason.']);
            return;
        }

        if ($isFullDay) {
            $startTime = '00:00:00';
            $endTime   = '23:59:00';
        } else {
            $startTime = trim($_POST['startTime'] ?? '08:00');
            $endTime   = trim($_POST['endTime'] ?? '18:00');
            if (strlen($startTime) === 5) $startTime .= ':00';
            if (strlen($endTime) === 5) $endTime .= ':00';
        }

        // Quota Limit Enforcement
        if ($leaveTypeID) {
            $bal = getNurseLeaveBalance($pdo, $nurseId, $leaveTypeID);
            if ($bal['remaining'] <= 0) {
                echo json_encode([
                    'status' => 'error',
                    'message' => "Quota exceeded: You have used all {$bal['total']} allocated days for this leave category."
                ]);
                return;
            }
        }

        // Upload to uploads/proof_doc/nurse/
        $proofDocURL = null;
        if (isset($_FILES['proofDocument']) && $_FILES['proofDocument']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../uploads/proof_doc/nurse/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileTmp  = $_FILES['proofDocument']['tmp_name'];
            $fileName = basename($_FILES['proofDocument']['name']);
            $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
            if (!in_array($fileExt, $allowedExtensions)) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid file format. Allowed: JPG, PNG, WEBP, and PDF.']);
                return;
            }

            $uniqueName = 'PROOF_NUR_' . $nurseId . '_' . time() . '.' . $fileExt;
            $targetPath = $uploadDir . $uniqueName;

            if (move_uploaded_file($fileTmp, $targetPath)) {
                $proofDocURL = "/StepCare/uploads/proof_doc/nurse/" . $uniqueName;
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to save uploaded document.']);
                return;
            }
        }

        // Check if leaveTypeID column exists
        $hasLeaveTypeCol = false;
        try {
            $colCheck = $pdo->query("SHOW COLUMNS FROM nurseRequestOffDay LIKE 'leaveTypeID'");
            $hasLeaveTypeCol = ($colCheck && $colCheck->rowCount() > 0);
        } catch (Exception $e) {}

        if ($hasLeaveTypeCol) {
            $stmt = $pdo->prepare("
                INSERT INTO nurseRequestOffDay (nurseID, leaveTypeID, requestDate, startTime, endTime, reason, proofDocumentURL, status, createdAt)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$nurseId, $leaveTypeID, $requestDate, $startTime, $endTime, $reason, $proofDocURL]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO nurseRequestOffDay (nurseID, requestDate, startTime, endTime, reason, proofDocumentURL, status, createdAt)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$nurseId, $requestDate, $startTime, $endTime, $reason, $proofDocURL]);
        }

        echo json_encode(['status' => 'success', 'message' => 'Application submitted successfully.']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 5. Cancel pending leave request
function cancelLeave($pdo) {
    try {
        $input   = json_decode(file_get_contents('php://input'), true);
        $reqId   = (int)($input['requestId'] ?? 0);
        $nurseId = (int)($_SESSION['nurseID'] ?? $_SESSION['user_id'] ?? 1);

        if (!$reqId) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request ID.']);
            return;
        }

        $stmt = $pdo->prepare("DELETE FROM nurseRequestOffDay WHERE requestID = ? AND nurseID = ? AND LOWER(status) = 'pending'");
        $stmt->execute([$reqId, $nurseId]);

        echo json_encode(['status' => 'success', 'message' => 'Request withdrawn.']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}