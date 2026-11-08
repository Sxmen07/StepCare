<?php
// English comments only
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/../../config/db_connect.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Check if current user is Super Admin
function isCurrentSuperAdmin($pdo) {
    $adminId = (int)($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 1);
    if ($adminId === 1) return true;

    try {
        $stmt = $pdo->prepare("SELECT adminRole FROM admins WHERE adminID = ?");
        $stmt->execute([$adminId]);
        $role = $stmt->fetchColumn();
        if ($role && strtolower(trim($role)) === 'super admin') return true;
    } catch (Exception $e) {}

    $role = strtolower($_SESSION['role'] ?? $_SESSION['user_role'] ?? '');
    $roleLevel = strtolower($_SESSION['role_level'] ?? $_SESSION['admin_level'] ?? '');
    return ($role === 'super admin' || $role === 'superadmin' || $roleLevel === 'super' || !empty($_SESSION['is_super_admin']));
}

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

// Helper: Send automated system notification
function sendSystemNotification($pdo, $recipientType, $recipientId, $title, $message, $type = 'system_alert') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (recipientType, recipientID, senderType, senderID, title, message, type, isRead, createdAt)
            VALUES (?, ?, 'system', 0, ?, ?, ?, 0, NOW())
        ");
        $stmt->execute([$recipientType, (int)$recipientId, $title, $message, $type]);
    } catch (Exception $e) {
        // Silently catch to prevent blocking main business execution
    }
}

// Smart detect category for historical legacy rows
function detectLeaveCategory($assignedName, $reason) {
    if (!empty($assignedName)) return $assignedName;
    $r = strtolower($reason ?? '');
    if (strpos($r, 'mc') !== false || strpos($r, 'medical') !== false || strpos($r, 'sick') !== false || strpos($r, 'doctor') !== false || strpos($r, 'fever') !== false || strpos($r, 'clinic') !== false) {
        return 'Medical Leave (MC)';
    }
    if (strpos($r, 'annual') !== false || strpos($r, 'vacation') !== false || strpos($r, 'holiday') !== false || strpos($r, 'trip') !== false) {
        return 'Annual Leave';
    }
    if (strpos($r, 'emergency') !== false || strpos($r, 'urgent') !== false || strpos($r, 'family') !== false) {
        return 'Emergency Leave';
    }
    if (strpos($r, 'hospital') !== false) {
        return 'Hospitalization Leave';
    }
    return 'Annual Leave';
}

// Helper: Calculate total, approved used, and remaining quota for a staff member
function getStaffLeaveBalance($pdo, $staffType, $staffId, $leaveTypeId) {
    $policyStmt = $pdo->prepare("SELECT daysPerYear FROM leave_types WHERE leaveTypeID = ? LIMIT 1");
    $policyStmt->execute([$leaveTypeId]);
    $totalDays = (int)$policyStmt->fetchColumn();
    if ($totalDays <= 0) $totalDays = 14;

    $currentYear = date('Y');
    if ($staffType === 'Admin') {
        $countStmt = $pdo->prepare("
            SELECT COUNT(*) FROM adminRequestOffDay 
            WHERE adminID = ? AND leaveTypeID = ? AND status = 'approved' AND YEAR(requestDate) = ?
        ");
    } else {
        $countStmt = $pdo->prepare("
            SELECT COUNT(*) FROM nurseRequestOffDay 
            WHERE nurseID = ? AND leaveTypeID = ? AND status = 'approved' AND YEAR(requestDate) = ?
        ");
    }
    $countStmt->execute([$staffId, $leaveTypeId, $currentYear]);
    $usedDays = (int)$countStmt->fetchColumn();

    return [
        'total' => $totalDays,
        'used' => $usedDays,
        'remaining' => max(0, $totalDays - $usedDays)
    ];
}

switch ($action) {
    case 'fetch_applications':
        header('Content-Type: application/json; charset=utf-8');
        fetchApplications($pdo);
        break;

    case 'fetch_my_leaves':
        header('Content-Type: application/json; charset=utf-8');
        fetchMyLeaves($pdo);
        break;

    case 'fetch_my_balance':
        header('Content-Type: application/json; charset=utf-8');
        fetchMyLeaveBalance($pdo);
        break;

    case 'submit_admin_leave':
        header('Content-Type: application/json; charset=utf-8');
        submitAdminLeave($pdo);
        break;

    case 'update_status':
        header('Content-Type: application/json; charset=utf-8');
        updateApplicationStatus($pdo);
        break;

    case 'fetch_leave_types':
        header('Content-Type: application/json; charset=utf-8');
        fetchLeaveTypes($pdo);
        break;

    case 'save_leave_type':
        header('Content-Type: application/json; charset=utf-8');
        saveLeaveType($pdo);
        break;

    case 'delete_leave_type':
        header('Content-Type: application/json; charset=utf-8');
        deleteLeaveType($pdo);
        break;

    case 'export_excel':
        exportApplicationsToExcel($pdo);
        break;

    default:
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'Invalid action parameter']);
        break;
}

// 1. Fetch Review Stream
function fetchApplications($pdo) {
    try {
        $statusFilter = strtolower($_GET['status'] ?? 'all');
        $targetAudience = strtolower($_GET['target_role'] ?? 'all');
        $isSuper = isCurrentSuperAdmin($pdo);

        $results = [];

        $hasNurseProof = false;
        try {
            $c = $pdo->query("SHOW COLUMNS FROM nurseRequestOffDay LIKE 'proofDocumentURL'");
            $hasNurseProof = ($c && $c->rowCount() > 0);
        } catch (Exception $e) {}

        // --- A. Nurse Requests ---
        if ($targetAudience === 'all' || $targetAudience === 'nurse') {
            $proofSelect = $hasNurseProof ? "r.proofDocumentURL," : "NULL AS proofDocumentURL,";
            $query = "
                SELECT 
                    r.requestID,
                    r.nurseID,
                    r.leaveTypeID,
                    r.requestDate,
                    r.startTime,
                    r.endTime,
                    r.status,
                    r.reason,
                    $proofSelect
                    r.rejectionReason,
                    r.createdAt,
                    n.firstName,
                    n.lastName,
                    n.email,
                    n.nursePhotoURL,
                    a.firstName AS adminFirstName,
                    a.lastName AS adminLastName,
                    lt.leaveName
                FROM nurseRequestOffDay r
                LEFT JOIN nurses n ON r.nurseID = n.nurseID
                LEFT JOIN admins a ON r.adminID = a.adminID
                LEFT JOIN leave_types lt ON r.leaveTypeID = lt.leaveTypeID
            ";

            $params = [];
            if ($statusFilter !== 'all') {
                $query .= " WHERE LOWER(r.status) = ?";
                $params[] = $statusFilter;
            }
            $query .= " ORDER BY r.createdAt DESC";

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $rawPhoto = $row['nursePhotoURL'] ?? '';
                $cleanPhoto = !empty($rawPhoto) ? basename($rawPhoto) : '';
                $avatarPath = !empty($cleanPhoto) ? "/StepCare/images/avatars/nurse/{$cleanPhoto}" : null;

                $docPath = cleanProofUrl($row['proofDocumentURL'] ?? '');
                $cleanDoc = $docPath ? basename($docPath) : '';
                $categoryName = detectLeaveCategory($row['leaveName'] ?? '', $row['reason'] ?? '');

                // Balance calculation if leaveTypeID bound
                $balanceInfo = null;
                if (!empty($row['leaveTypeID'])) {
                    $balanceInfo = getStaffLeaveBalance($pdo, 'Nurse', (int)$row['nurseID'], (int)$row['leaveTypeID']);
                }

                $results[] = [
                    'applicantType' => 'Nurse',
                    'requestId' => (int)$row['requestID'],
                    'staffId' => 'NUR-' . str_pad($row['nurseID'], 3, '0', STR_PAD_LEFT),
                    'rawStaffId' => (int)$row['nurseID'],
                    'staffName' => trim(($row['firstName'] ?? '') . ' ' . ($row['lastName'] ?? 'Unknown Nurse')),
                    'email' => $row['email'] ?? 'N/A',
                    'photo' => $avatarPath,
                    'leaveName' => $categoryName,
                    'balanceInfo' => $balanceInfo,
                    'requestDate' => $row['requestDate'],
                    'startTime' => date('H:i', strtotime($row['startTime'])),
                    'endTime' => date('H:i', strtotime($row['endTime'])),
                    'reason' => $row['reason'] ?: 'No explanation provided.',
                    'proofDocument' => $docPath,
                    'proofDocumentName' => $cleanDoc,
                    'rejectionReason' => $row['rejectionReason'] ?? '',
                    'status' => strtolower($row['status']),
                    'submittedAt' => date('M d, Y H:i', strtotime($row['createdAt'])),
                    'can_review' => true,
                    'reviewedBy' => $row['adminFirstName'] ? trim($row['adminFirstName'] . ' ' . ($row['adminLastName'] ?? '')) : null
                ];
            }
        }

        // --- B. Admin Requests ---
        if ($isSuper && ($targetAudience === 'all' || $targetAudience === 'admin')) {
            try {
                $adminQuery = "
                    SELECT 
                        r.requestID,
                        r.adminID,
                        r.leaveTypeID,
                        r.requestDate,
                        r.startTime,
                        r.endTime,
                        r.status,
                        r.reason,
                        r.proofDocumentURL,
                        r.rejectionReason,
                        r.createdAt,
                        a.firstName,
                        a.lastName,
                        a.email,
                        a.adminPhotoURL,
                        sa.firstName AS superFirstName,
                        sa.lastName AS superLastName,
                        lt.leaveName
                    FROM adminRequestOffDay r
                    LEFT JOIN admins a ON r.adminID = a.adminID
                    LEFT JOIN admins sa ON r.reviewedBySuperAdminID = sa.adminID
                    LEFT JOIN leave_types lt ON r.leaveTypeID = lt.leaveTypeID
                ";

                $aParams = [];
                if ($statusFilter !== 'all') {
                    $adminQuery .= " WHERE LOWER(r.status) = ?";
                    $aParams[] = $statusFilter;
                }
                $adminQuery .= " ORDER BY r.createdAt DESC";

                $aStmt = $pdo->prepare($adminQuery);
                $aStmt->execute($aParams);

                while ($row = $aStmt->fetch(PDO::FETCH_ASSOC)) {
                    $rawPhoto = $row['adminPhotoURL'] ?? '';
                    $cleanPhoto = !empty($rawPhoto) ? basename($rawPhoto) : '';
                    $avatarPath = !empty($cleanPhoto) ? "/StepCare/images/avatars/admin/{$cleanPhoto}" : null;

                    $docPath = cleanProofUrl($row['proofDocumentURL'] ?? '');
                    $cleanDoc = $docPath ? basename($docPath) : '';
                    $categoryName = detectLeaveCategory($row['leaveName'] ?? '', $row['reason'] ?? '');

                    $balanceInfo = null;
                    if (!empty($row['leaveTypeID'])) {
                        $balanceInfo = getStaffLeaveBalance($pdo, 'Admin', (int)$row['adminID'], (int)$row['leaveTypeID']);
                    }

                    $results[] = [
                        'applicantType' => 'Admin',
                        'requestId' => (int)$row['requestID'],
                        'staffId' => 'ADM-' . str_pad($row['adminID'], 3, '0', STR_PAD_LEFT),
                        'rawStaffId' => (int)$row['adminID'],
                        'staffName' => trim(($row['firstName'] ?? '') . ' ' . ($row['lastName'] ?? 'Unknown Admin')),
                        'email' => $row['email'] ?? 'N/A',
                        'photo' => $avatarPath,
                        'leaveName' => $categoryName,
                        'balanceInfo' => $balanceInfo,
                        'requestDate' => $row['requestDate'],
                        'startTime' => date('H:i', strtotime($row['startTime'])),
                        'endTime' => date('H:i', strtotime($row['endTime'])),
                        'reason' => $row['reason'] ?: 'No explanation provided.',
                        'proofDocument' => $docPath,
                        'proofDocumentName' => $cleanDoc,
                        'rejectionReason' => $row['rejectionReason'] ?? '',
                        'status' => strtolower($row['status']),
                        'submittedAt' => date('M d, Y H:i', strtotime($row['createdAt'])),
                        'can_review' => $isSuper,
                        'reviewedBy' => $row['superFirstName'] ? trim($row['superFirstName'] . ' ' . ($row['superLastName'] ?? '')) : null
                    ];
                }
            } catch (Exception $ex) {}
        }

        usort($results, function($a, $b) {
            return strtotime($b['submittedAt']) - strtotime($a['submittedAt']);
        });

        echo json_encode([
            'status' => 'success',
            'is_super_admin' => $isSuper,
            'data' => $results
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 2. Fetch logged-in Admin's own applications
function fetchMyLeaves($pdo) {
    try {
        $adminId = (int)($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 1);

        $stmt = $pdo->prepare("
            SELECT r.*, lt.leaveName, sa.firstName AS superFirstName, sa.lastName AS superLastName
            FROM adminRequestOffDay r
            LEFT JOIN admins sa ON r.reviewedBySuperAdminID = sa.adminID
            LEFT JOIN leave_types lt ON r.leaveTypeID = lt.leaveTypeID
            WHERE r.adminID = ?
            ORDER BY r.createdAt DESC
        ");
        $stmt->execute([$adminId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $myLeaves = [];
        foreach ($rows as $row) {
            $docPath = cleanProofUrl($row['proofDocumentURL'] ?? '');
            $cleanDoc = $docPath ? basename($docPath) : '';
            $categoryName = detectLeaveCategory($row['leaveName'] ?? '', $row['reason'] ?? '');

            $myLeaves[] = [
                'requestId' => (int)$row['requestID'],
                'leaveName' => $categoryName,
                'requestDate' => $row['requestDate'],
                'startTime' => date('H:i', strtotime($row['startTime'])),
                'endTime' => date('H:i', strtotime($row['endTime'])),
                'reason' => $row['reason'],
                'proofDocument' => $docPath,
                'proofDocumentName' => $cleanDoc,
                'status' => strtolower($row['status']),
                'rejectionReason' => $row['rejectionReason'] ?? '',
                'submittedAt' => date('M d, Y H:i', strtotime($row['createdAt'])),
                'reviewedBy' => $row['superFirstName'] ? trim($row['superFirstName'] . ' ' . ($row['superLastName'] ?? '')) : null
            ];
        }

        echo json_encode(['status' => 'success', 'data' => $myLeaves]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 3. Fetch logged-in Admin's current quota balance for all leave categories
function fetchMyLeaveBalance($pdo) {
    try {
        $adminId = (int)($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 1);
        $stmt = $pdo->query("SELECT * FROM leave_types WHERE targetRole IN ('all', 'admin') ORDER BY leaveTypeID ASC");
        $policies = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $balances = [];
        foreach ($policies as $p) {
            $bal = getStaffLeaveBalance($pdo, 'Admin', $adminId, (int)$p['leaveTypeID']);
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

// 4. Admin submits application with real-time quota verification
function submitAdminLeave($pdo) {
    try {
        $adminId     = (int)($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 1);
        $requestDate = trim($_POST['requestDate'] ?? '');
        $startTime   = trim($_POST['startTime'] ?? '');
        $endTime     = trim($_POST['endTime'] ?? '');
        $reason      = trim($_POST['reason'] ?? '');
        $leaveTypeID = !empty($_POST['leaveTypeID']) ? (int)$_POST['leaveTypeID'] : null;

        if (empty($requestDate) || empty($startTime) || empty($endTime) || empty($reason)) {
            echo json_encode(['status' => 'error', 'message' => 'Please fill in all required fields.']);
            return;
        }

        // Quota Limit Enforcement
        if ($leaveTypeID) {
            $bal = getStaffLeaveBalance($pdo, 'Admin', $adminId, $leaveTypeID);
            if ($bal['remaining'] <= 0) {
                echo json_encode([
                    'status' => 'error',
                    'message' => "Quota exceeded: You have exhausted all {$bal['total']} allocated days for this leave category this year."
                ]);
                return;
            }
        }

        $proofDocURL = null;
        if (isset($_FILES['proofDocument']) && $_FILES['proofDocument']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../uploads/proof_doc/admin/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileTmp  = $_FILES['proofDocument']['tmp_name'];
            $fileName = basename($_FILES['proofDocument']['name']);
            $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
            if (!in_array($fileExt, $allowedExtensions)) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid file format. Only JPG, PNG, WEBP, and PDF files are allowed.']);
                return;
            }

            $uniqueName  = 'PROOF_ADM_' . $adminId . '_' . time() . '.' . $fileExt;
            $targetPath  = $uploadDir . $uniqueName;

            if (move_uploaded_file($fileTmp, $targetPath)) {
                $proofDocURL = "/StepCare/uploads/proof_doc/admin/" . $uniqueName;
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to save uploaded document.']);
                return;
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO adminRequestOffDay (adminID, leaveTypeID, requestDate, startTime, endTime, reason, proofDocumentURL, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$adminId, $leaveTypeID, $requestDate, $startTime, $endTime, $reason, $proofDocURL]);

        // Trigger Notification: Broadcast to Admins that a new request arrived
        $adminNameStmt = $pdo->prepare("SELECT CONCAT(firstName, ' ', lastName) FROM admins WHERE adminID = ?");
        $adminNameStmt->execute([$adminId]);
        $applicantName = $adminNameStmt->fetchColumn() ?: "Administrator #{$adminId}";

        sendSystemNotification(
            $pdo,
            'admin',
            0, // Broadcast to all admins
            'New Leave Application Submitted',
            "{$applicantName} has submitted a new leave request for {$requestDate}.",
            'leave_application'
        );

        echo json_encode(['status' => 'success', 'message' => 'Application submitted successfully.']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 5. Fetch Leave Types
function fetchLeaveTypes($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM leave_types ORDER BY leaveTypeID ASC");
        $types = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        echo json_encode(['status' => 'success', 'data' => $types]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 6. Super Admin saves or updates a Leave Type
function saveLeaveType($pdo) {
    try {
        if (!isCurrentSuperAdmin($pdo)) {
            echo json_encode(['status' => 'error', 'message' => 'Only Super Admin can manage leave policies.']);
            return;
        }

        $input       = json_decode(file_get_contents('php://input'), true);
        $leaveTypeID = (int)($input['leaveTypeID'] ?? 0);
        $leaveName   = trim($input['leaveName'] ?? '');
        $targetRole  = trim($input['targetRole'] ?? 'all');
        $daysPerYear = (int)($input['daysPerYear'] ?? 14);

        if (empty($leaveName) || $daysPerYear <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Valid policy name and days quota are required.']);
            return;
        }

        if ($leaveTypeID > 0) {
            $stmt = $pdo->prepare("UPDATE leave_types SET leaveName = ?, targetRole = ?, daysPerYear = ? WHERE leaveTypeID = ?");
            $stmt->execute([$leaveName, $targetRole, $daysPerYear, $leaveTypeID]);
            $msg = 'Leave policy updated successfully.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO leave_types (leaveName, targetRole, daysPerYear, isCustom) VALUES (?, ?, ?, 1)");
            $stmt->execute([$leaveName, $targetRole, $daysPerYear]);
            $msg = 'New leave policy created successfully.';
        }

        echo json_encode(['status' => 'success', 'message' => $msg]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 7. Super Admin deletes a custom Leave Type
function deleteLeaveType($pdo) {
    try {
        if (!isCurrentSuperAdmin($pdo)) {
            echo json_encode(['status' => 'error', 'message' => 'Only Super Admin can delete leave policies.']);
            return;
        }

        $input       = json_decode(file_get_contents('php://input'), true);
        $leaveTypeID = (int)($input['leaveTypeID'] ?? 0);

        if ($leaveTypeID <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid leave type ID.']);
            return;
        }

        $stmt = $pdo->prepare("DELETE FROM leave_types WHERE leaveTypeID = ?");
        $stmt->execute([$leaveTypeID]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Policy deleted successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Policy not found or already deleted.']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 8. Approve or Reject application (Triggering quota deduction & Unicast Notification to applicant)
function updateApplicationStatus($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        $applicantType   = trim($input['applicantType'] ?? 'Nurse');
        $requestId       = (int)($input['requestId'] ?? 0);
        $newStatus       = trim($input['status'] ?? '');
        $rejectionReason = trim($input['rejectionReason'] ?? '');
        $adminId         = (int)($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 1);
        $isSuper         = isCurrentSuperAdmin($pdo);

        if (!$requestId || !in_array($newStatus, ['approved', 'rejected'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request parameters.']);
            return;
        }

        $reasonToStore = ($newStatus === 'rejected') ? ($rejectionReason ?: 'Operational scheduling conflict') : null;

        $targetRecipientId = 0;
        $targetRecipientType = strtolower($applicantType);
        $targetDate = '';

        if ($applicantType === 'Admin') {
            if (!$isSuper) {
                echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Only Super Admin can review Admin requests.']);
                return;
            }

            // Extract target admin ID and date for direct personal notification
            $infoStmt = $pdo->prepare("SELECT adminID, requestDate FROM adminRequestOffDay WHERE requestID = ?");
            $infoStmt->execute([$requestId]);
            $info = $infoStmt->fetch(PDO::FETCH_ASSOC);
            if ($info) {
                $targetRecipientId = (int)$info['adminID'];
                $targetDate = $info['requestDate'];
            }

            $updateStmt = $pdo->prepare("UPDATE adminRequestOffDay SET status = ?, rejectionReason = ?, reviewedBySuperAdminID = ? WHERE requestID = ?");
            $updateStmt->execute([$newStatus, $reasonToStore, $adminId, $requestId]);
        } else {
            // Extract target nurse ID and date for direct personal notification
            $infoStmt = $pdo->prepare("SELECT nurseID, requestDate FROM nurseRequestOffDay WHERE requestID = ?");
            $infoStmt->execute([$requestId]);
            $info = $infoStmt->fetch(PDO::FETCH_ASSOC);
            if ($info) {
                $targetRecipientId = (int)$info['nurseID'];
                $targetDate = $info['requestDate'];
            }

            $updateStmt = $pdo->prepare("UPDATE nurseRequestOffDay SET status = ?, rejectionReason = ?, adminID = ? WHERE requestID = ?");
            $updateStmt->execute([$newStatus, $reasonToStore, $adminId, $requestId]);
        }

        // Trigger Notification: Send ONLY to the applicant themselves
        if ($targetRecipientId > 0) {
            $statusText = ($newStatus === 'approved') ? 'Approved' : 'Declined';
            $notifTitle = "Off-Day Request {$statusText}";
            $notifMsg = "Your off-day request for {$targetDate} was {$statusText}.";
            if ($newStatus === 'rejected' && !empty($reasonToStore)) {
                $notifMsg .= " Reason: {$reasonToStore}";
            }

            sendSystemNotification(
                $pdo,
                $targetRecipientType,
                $targetRecipientId, // Specific ID so only they see it
                $notifTitle,
                $notifMsg,
                'leave_status'
            );
        }

        echo json_encode([
            'status' => 'success', 
            'message' => "Application marked as {$newStatus}."
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 9. Export Applications to Excel CSV
function exportApplicationsToExcel($pdo) {
    $statusFilter = $_GET['status'] ?? 'All';
    $filename = "StepCare_Applications_Export_" . date('Ymd_His') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($output, ['Applicant Type', 'Staff ID', 'Staff Name', 'Category', 'Date', 'Start Time', 'End Time', 'Reason', 'Status', 'Proof Document URL', 'Reviewed By']);

    try {
        $stmt = $pdo->query("
            SELECT 'Nurse' AS applicantType, r.nurseID AS staffID, CONCAT(n.firstName, ' ', n.lastName) AS staffName,
                   lt.leaveName, r.requestDate, r.startTime, r.endTime, r.reason, r.status, r.proofDocumentURL, CONCAT(a.firstName, ' ', a.lastName) AS reviewer
            FROM nurseRequestOffDay r
            LEFT JOIN nurses n ON r.nurseID = n.nurseID
            LEFT JOIN admins a ON r.adminID = a.adminID
            LEFT JOIN leave_types lt ON r.leaveTypeID = lt.leaveTypeID
            ORDER BY r.createdAt DESC
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cleanDoc = cleanProofUrl($row['proofDocumentURL'] ?? '');
            $categoryName = detectLeaveCategory($row['leaveName'] ?? '', $row['reason'] ?? '');

            fputcsv($output, [
                $row['applicantType'],
                'NUR-' . str_pad($row['staffID'], 3, '0', STR_PAD_LEFT),
                trim($row['staffName']),
                $categoryName,
                $row['requestDate'],
                $row['startTime'],
                $row['endTime'],
                $row['reason'],
                strtoupper($row['status']),
                $cleanDoc ?: 'None',
                $row['reviewer'] ?? 'Pending'
            ]);
        }
    } catch (Exception $e) {
        fputcsv($output, ['Export Error: ' . $e->getMessage()]);
    }

    fclose($output);
    exit;
}