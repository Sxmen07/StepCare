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

switch ($action) {
    case 'fetch_applications':
        header('Content-Type: application/json; charset=utf-8');
        fetchApplications($pdo);
        break;

    case 'fetch_my_leaves':
        header('Content-Type: application/json; charset=utf-8');
        fetchMyLeaves($pdo);
        break;

    case 'submit_admin_leave':
        header('Content-Type: application/json; charset=utf-8');
        submitAdminLeave($pdo);
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

// 1. Fetch Review Stream (Nurse requests, plus Admin requests if Super Admin)
function fetchApplications($pdo) {
    try {
        $statusFilter = strtolower($_GET['status'] ?? 'all');
        $targetAudience = strtolower($_GET['target_role'] ?? 'all'); // 'nurse', 'admin', or 'all'
        $isSuper = isCurrentSuperAdmin($pdo);

        $results = [];

        // --- A. Fetch Nurse Requests ---
        if ($targetAudience === 'all' || $targetAudience === 'nurse') {
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
                    n.email,
                    n.nursePhotoURL,
                    a.firstName AS adminFirstName,
                    a.lastName AS adminLastName
                FROM nurseRequestOffDay r
                LEFT JOIN nurses n ON r.nurseID = n.nurseID
                LEFT JOIN admins a ON r.adminID = a.adminID
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

                $results[] = [
                    'applicantType' => 'Nurse',
                    'requestId' => (int)$row['requestID'],
                    'staffId' => 'NUR-' . str_pad($row['nurseID'], 3, '0', STR_PAD_LEFT),
                    'rawStaffId' => (int)$row['nurseID'],
                    'staffName' => trim(($row['firstName'] ?? '') . ' ' . ($row['lastName'] ?? 'Unknown Nurse')),
                    'email' => $row['email'] ?? 'N/A',
                    'photo' => $avatarPath,
                    'requestDate' => $row['requestDate'],
                    'startTime' => date('H:i', strtotime($row['startTime'])),
                    'endTime' => date('H:i', strtotime($row['endTime'])),
                    'reason' => $row['reason'] ?: 'No explanation provided.',
                    'rejectionReason' => $row['rejectionReason'] ?? '',
                    'status' => strtolower($row['status']),
                    'submittedAt' => date('M d, Y H:i', strtotime($row['createdAt'])),
                    'can_review' => true, // Admins & Super Admins can review nurse leaves
                    'reviewedBy' => $row['adminFirstName'] ? trim($row['adminFirstName'] . ' ' . ($row['adminLastName'] ?? '')) : null
                ];
            }
        }

        // --- B. Fetch Admin Requests (Visible to Super Admin or read-only context) ---
        if ($isSuper && ($targetAudience === 'all' || $targetAudience === 'admin')) {
            try {
                $adminQuery = "
                    SELECT 
                        r.requestID,
                        r.adminID,
                        r.requestDate,
                        r.startTime,
                        r.endTime,
                        r.status,
                        r.reason,
                        r.rejectionReason,
                        r.createdAt,
                        a.firstName,
                        a.lastName,
                        a.email,
                        a.adminPhotoURL,
                        sa.firstName AS superFirstName,
                        sa.lastName AS superLastName
                    FROM adminRequestOffDay r
                    LEFT JOIN admins a ON r.adminID = a.adminID
                    LEFT JOIN admins sa ON r.reviewedBySuperAdminID = sa.adminID
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

                    $results[] = [
                        'applicantType' => 'Admin',
                        'requestId' => (int)$row['requestID'],
                        'staffId' => 'ADM-' . str_pad($row['adminID'], 3, '0', STR_PAD_LEFT),
                        'rawStaffId' => (int)$row['adminID'],
                        'staffName' => trim(($row['firstName'] ?? '') . ' ' . ($row['lastName'] ?? 'Unknown Admin')),
                        'email' => $row['email'] ?? 'N/A',
                        'photo' => $avatarPath,
                        'requestDate' => $row['requestDate'],
                        'startTime' => date('H:i', strtotime($row['startTime'])),
                        'endTime' => date('H:i', strtotime($row['endTime'])),
                        'reason' => $row['reason'] ?: 'No explanation provided.',
                        'rejectionReason' => $row['rejectionReason'] ?? '',
                        'status' => strtolower($row['status']),
                        'submittedAt' => date('M d, Y H:i', strtotime($row['createdAt'])),
                        'can_review' => $isSuper, // Only Super Admin can approve/reject admin leaves
                        'reviewedBy' => $row['superFirstName'] ? trim($row['superFirstName'] . ' ' . ($row['superLastName'] ?? '')) : null
                    ];
                }
            } catch (Exception $ex) {
                // Ignore if table not yet migrated
            }
        }

        // Sort descending by submission
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

// 2. Fetch logged-in Admin's own leave history
function fetchMyLeaves($pdo) {
    try {
        $adminId = (int)($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 1);

        $stmt = $pdo->prepare("
            SELECT r.*, sa.firstName AS superFirstName, sa.lastName AS superLastName
            FROM adminRequestOffDay r
            LEFT JOIN admins sa ON r.reviewedBySuperAdminID = sa.adminID
            WHERE r.adminID = ?
            ORDER BY r.createdAt DESC
        ");
        $stmt->execute([$adminId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $myLeaves = [];
        foreach ($rows as $row) {
            $myLeaves[] = [
                'requestId' => (int)$row['requestID'],
                'requestDate' => $row['requestDate'],
                'startTime' => date('H:i', strtotime($row['startTime'])),
                'endTime' => date('H:i', strtotime($row['endTime'])),
                'reason' => $row['reason'],
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

// 3. Admin submits their own leave application
function submitAdminLeave($pdo) {
    try {
        $adminId = (int)($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 1);
        $input   = json_decode(file_get_contents('php://input'), true);

        $requestDate = trim($input['requestDate'] ?? '');
        $startTime   = trim($input['startTime'] ?? '');
        $endTime     = trim($input['endTime'] ?? '');
        $reason      = trim($input['reason'] ?? '');

        if (empty($requestDate) || empty($startTime) || empty($endTime) || empty($reason)) {
            echo json_encode(['status' => 'error', 'message' => 'Please fill in all required leave information.']);
            return;
        }

        $stmt = $pdo->prepare("
            INSERT INTO adminRequestOffDay (adminID, requestDate, startTime, endTime, reason, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$adminId, $requestDate, $startTime, $endTime, $reason]);

        echo json_encode(['status' => 'success', 'message' => 'Your leave application has been submitted to Super Admin for approval.']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 4. Approve or Reject application
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

        if ($applicantType === 'Admin') {
            if (!$isSuper) {
                echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Only Super Administrators can review Admin leave requests.']);
                return;
            }

            $updateStmt = $pdo->prepare("UPDATE adminRequestOffDay SET status = ?, rejectionReason = ?, reviewedBySuperAdminID = ? WHERE requestID = ?");
            $updateStmt->execute([$newStatus, $reasonToStore, $adminId, $requestId]);
        } else {
            // Nurse leave request
            $updateStmt = $pdo->prepare("UPDATE nurseRequestOffDay SET status = ?, rejectionReason = ?, adminID = ? WHERE requestID = ?");
            $updateStmt->execute([$newStatus, $reasonToStore, $adminId, $requestId]);
        }

        echo json_encode([
            'status' => 'success', 
            'message' => "Application marked as {$newStatus}."
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 5. Export Applications to Excel CSV
function exportApplicationsToExcel($pdo) {
    $statusFilter = $_GET['status'] ?? 'All';
    $filename = "StepCare_Applications_Export_" . date('Ymd_His') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($output, ['Applicant Type', 'Staff ID', 'Staff Name', 'Date', 'Start Time', 'End Time', 'Reason', 'Status', 'Rejection Reason', 'Reviewed By']);

    try {
        $stmt = $pdo->query("
            SELECT 'Nurse' AS applicantType, r.nurseID AS staffID, CONCAT(n.firstName, ' ', n.lastName) AS staffName,
                   r.requestDate, r.startTime, r.endTime, r.reason, r.status, r.rejectionReason, CONCAT(a.firstName, ' ', a.lastName) AS reviewer
            FROM nurseRequestOffDay r
            LEFT JOIN nurses n ON r.nurseID = n.nurseID
            LEFT JOIN admins a ON r.adminID = a.adminID
            ORDER BY r.createdAt DESC
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['applicantType'],
                'NUR-' . str_pad($row['staffID'], 3, '0', STR_PAD_LEFT),
                trim($row['staffName']),
                $row['requestDate'],
                $row['startTime'],
                $row['endTime'],
                $row['reason'],
                strtoupper($row['status']),
                $row['rejectionReason'] ?? 'N/A',
                $row['reviewer'] ?? 'Pending'
            ]);
        }
    } catch (Exception $e) {
        fputcsv($output, ['Export Error: ' . $e->getMessage()]);
    }

    fclose($output);
    exit;
}
?>