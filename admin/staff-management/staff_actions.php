<?php
// English comments only
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/../../config/db_connect.php';

$action = $_GET['action'] ?? '';

// Check if current logged-in admin is a super admin
function isCurrentSuperAdmin($pdo) {
    $adminId = (int)($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 1);

    if ($adminId === 1) {
        return true;
    }

    try {
        $stmt = $pdo->prepare("SELECT adminRole FROM admins WHERE adminID = ?");
        $stmt->execute([$adminId]);
        $role = $stmt->fetchColumn();

        if ($role && strtolower(trim($role)) === 'super admin') {
            return true;
        }
    } catch (Exception $e) {}

    $role = strtolower($_SESSION['role'] ?? $_SESSION['user_role'] ?? '');
    $roleLevel = strtolower($_SESSION['role_level'] ?? $_SESSION['admin_level'] ?? '');
    if ($role === 'super admin' || $role === 'superadmin' || $roleLevel === 'super' || !empty($_SESSION['is_super_admin'])) {
        return true;
    }

    return false;
}

switch ($action) {
    case 'fetch_staff':
        header('Content-Type: application/json; charset=utf-8');
        fetchStaffList($pdo);
        break;

    case 'create_staff':
        header('Content-Type: application/json; charset=utf-8');
        createStaffAccount($pdo);
        break;

    case 'update_staff':
        header('Content-Type: application/json; charset=utf-8');
        updateStaffAccount($pdo);
        break;

    case 'delete_staff':
        header('Content-Type: application/json; charset=utf-8');
        deleteStaffAccount($pdo);
        break;

    case 'export_excel':
        exportStaffToExcel($pdo);
        break;

    default:
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'Invalid action parameter']);
        break;
}

// 1. Fetch unified staff list
function fetchStaffList($pdo) {
    try {
        $staff = [];

        $hasAdminRoleCol = false;
        try {
            $colCheck = $pdo->query("SHOW COLUMNS FROM admins LIKE 'adminRole'");
            $hasAdminRoleCol = ($colCheck && $colCheck->rowCount() > 0);
        } catch (Exception $e) {
            $hasAdminRoleCol = false;
        }

        $adminSql = $hasAdminRoleCol
            ? "SELECT adminID AS id, firstName, lastName, email, adminPhotoURL, adminRole, createdByAdminID FROM admins ORDER BY adminID DESC"
            : "SELECT adminID AS id, firstName, lastName, email, adminPhotoURL, createdByAdminID FROM admins ORDER BY adminID DESC";

        $adminStmt = $pdo->query($adminSql);
        if ($adminStmt) {
            while ($row = $adminStmt->fetch(PDO::FETCH_ASSOC)) {
                $rawPhoto = $row['adminPhotoURL'] ?? '';
                $cleanPhoto = !empty($rawPhoto) ? basename($rawPhoto) : '';
                $avatarPath = !empty($cleanPhoto) ? "/StepCare/images/avatars/admin/{$cleanPhoto}" : null;

                $displayRole = 'Admin';
                $roleVal = strtolower(trim($row['adminRole'] ?? ''));
                if ((int)$row['id'] === 1 || $roleVal === 'super admin' || $roleVal === 'superadmin') {
                    $displayRole = 'Super Admin';
                }

                $creatorId = !empty($row['createdByAdminID']) ? (int)$row['createdByAdminID'] : 1;

                $staff[] = [
                    'id' => 'ADM-' . str_pad($row['id'], 3, '0', STR_PAD_LEFT),
                    'raw_id' => (int)$row['id'],
                    'first_name' => $row['firstName'] ?? '',
                    'last_name' => $row['lastName'] ?? '',
                    'name' => trim(($row['firstName'] ?? '') . ' ' . ($row['lastName'] ?? '')),
                    'email' => $row['email'],
                    'role' => $displayRole,
                    'specialization' => '-',
                    'created_by' => 'ADM-' . str_pad($creatorId, 3, '0', STR_PAD_LEFT),
                    'created_by_raw' => $creatorId,
                    'is_super_admin_account' => ($displayRole === 'Super Admin'),
                    'photo' => $avatarPath
                ];
            }
        }

        $nurseStmt = $pdo->query("SELECT nurseID AS id, firstName, lastName, email, specialization, nursePhotoURL, createdByAdminID FROM nurses ORDER BY nurseID DESC");
        if ($nurseStmt) {
            while ($row = $nurseStmt->fetch(PDO::FETCH_ASSOC)) {
                $rawPhoto = $row['nursePhotoURL'] ?? '';
                $cleanPhoto = !empty($rawPhoto) ? basename($rawPhoto) : '';
                $avatarPath = !empty($cleanPhoto) ? "/StepCare/images/avatars/nurse/{$cleanPhoto}" : null;

                $creatorId = !empty($row['createdByAdminID']) ? (int)$row['createdByAdminID'] : 1;

                $staff[] = [
                    'id' => 'NUR-' . str_pad($row['id'], 3, '0', STR_PAD_LEFT),
                    'raw_id' => (int)$row['id'],
                    'first_name' => $row['firstName'] ?? '',
                    'last_name' => $row['lastName'] ?? '',
                    'name' => trim(($row['firstName'] ?? '') . ' ' . ($row['lastName'] ?? '')),
                    'email' => $row['email'],
                    'role' => 'Nurse',
                    'specialization' => !empty($row['specialization']) ? $row['specialization'] : 'General Nursing',
                    'created_by' => 'ADM-' . str_pad($creatorId, 3, '0', STR_PAD_LEFT),
                    'created_by_raw' => $creatorId,
                    'is_super_admin_account' => false,
                    'photo' => $avatarPath
                ];
            }
        }

        echo json_encode([
            'status' => 'success',
            'is_super_admin' => isCurrentSuperAdmin($pdo),
            'data' => $staff
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 2. Delete staff account (Super Admin Only)
function deleteStaffAccount($pdo) {
    try {
        if (!isCurrentSuperAdmin($pdo)) {
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Only Super Administrators can delete staff accounts.']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $rawId = (int)($input['rawId'] ?? 0);
        $role  = trim($input['role'] ?? '');

        if ($rawId <= 0 || empty($role)) {
            echo json_encode(['status' => 'error', 'message' => 'Valid staff ID and role are required.']);
            return;
        }

        if (($role === 'Admin' || $role === 'Super Admin') && $rawId === 1) {
            echo json_encode(['status' => 'error', 'message' => 'System Protection: The root Super Administrator account (ID: 1) cannot be deleted.']);
            return;
        }

        if ($role === 'Nurse') {
            $stmt = $pdo->prepare("DELETE FROM nurses WHERE nurseID = ?");
            $stmt->execute([$rawId]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM admins WHERE adminID = ?");
            $stmt->execute([$rawId]);
        }

        if ($stmt->rowCount() > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Staff account has been permanently removed.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Account not found or already deleted.']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 3. Export tailored Excel / CSV report
function exportStaffToExcel($pdo) {
    $role = $_GET['role'] ?? 'All';
    $filename = "StepCare_Staff_Export_" . date('Ymd_His') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    try {
        if ($role === 'Nurse') {
            fputcsv($output, ['Specialization', 'Staff ID', 'Full Name', 'Date of Birth', 'Gender', 'Email', 'Phone Number', 'Created By']);
            $stmt = $pdo->query("SELECT nurseID, firstName, lastName, email, phoneNumber, gender, dateOfBirth, specialization, createdByAdminID FROM nurses ORDER BY nurseID ASC");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $creatorId = !empty($row['createdByAdminID']) ? 'ADM-' . str_pad($row['createdByAdminID'], 3, '0', STR_PAD_LEFT) : 'ADM-001';
                fputcsv($output, [
                    $row['specialization'] ?? 'General Nursing',
                    'NUR-' . str_pad($row['nurseID'], 3, '0', STR_PAD_LEFT),
                    trim(($row['firstName'] ?? '') . ' ' . ($row['lastName'] ?? '')),
                    $row['dateOfBirth'] ?? 'N/A',
                    $row['gender'] ?? 'N/A',
                    $row['email'] ?? 'N/A',
                    $row['phoneNumber'] ?? 'N/A',
                    $creatorId
                ]);
            }
        } elseif ($role === 'Admin' || $role === 'Super Admin') {
            fputcsv($output, ['Staff ID', 'Full Name', 'Email', 'Role', 'Created By']);
            $stmt = $pdo->query("SELECT adminID, firstName, lastName, email, createdByAdminID FROM admins ORDER BY adminID ASC");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $roleLabel = ((int)$row['adminID'] === 1) ? 'Super Admin' : 'Admin';
                $creatorId = !empty($row['createdByAdminID']) ? 'ADM-' . str_pad($row['createdByAdminID'], 3, '0', STR_PAD_LEFT) : 'ADM-001';
                fputcsv($output, [
                    'ADM-' . str_pad($row['adminID'], 3, '0', STR_PAD_LEFT),
                    trim(($row['firstName'] ?? '') . ' ' . ($row['lastName'] ?? '')),
                    $row['email'] ?? 'N/A',
                    $roleLabel,
                    $creatorId
                ]);
            }
        } else {
            fputcsv($output, ['--- ADMINISTRATORS ---']);
            fputcsv($output, ['Staff ID', 'Full Name', 'Email', 'Role', 'Created By']);
            $stmtAdmin = $pdo->query("SELECT adminID, firstName, lastName, email, createdByAdminID FROM admins ORDER BY adminID ASC");
            while ($row = $stmtAdmin->fetch(PDO::FETCH_ASSOC)) {
                $roleLabel = ((int)$row['adminID'] === 1) ? 'Super Admin' : 'Admin';
                $creatorId = !empty($row['createdByAdminID']) ? 'ADM-' . str_pad($row['createdByAdminID'], 3, '0', STR_PAD_LEFT) : 'ADM-001';
                fputcsv($output, [
                    'ADM-' . str_pad($row['adminID'], 3, '0', STR_PAD_LEFT),
                    trim(($row['firstName'] ?? '') . ' ' . ($row['lastName'] ?? '')),
                    $row['email'] ?? 'N/A',
                    $roleLabel,
                    $creatorId
                ]);
            }

            fputcsv($output, []);
            fputcsv($output, ['--- NURSES ---']);
            fputcsv($output, ['Specialization', 'Staff ID', 'Full Name', 'Date of Birth', 'Gender', 'Email', 'Phone Number', 'Created By']);
            $stmtNurse = $pdo->query("SELECT nurseID, firstName, lastName, email, phoneNumber, gender, dateOfBirth, specialization, createdByAdminID FROM nurses ORDER BY nurseID ASC");
            while ($row = $stmtNurse->fetch(PDO::FETCH_ASSOC)) {
                $creatorId = !empty($row['createdByAdminID']) ? 'ADM-' . str_pad($row['createdByAdminID'], 3, '0', STR_PAD_LEFT) : 'ADM-001';
                fputcsv($output, [
                    $row['specialization'] ?? 'General Nursing',
                    'NUR-' . str_pad($row['nurseID'], 3, '0', STR_PAD_LEFT),
                    trim(($row['firstName'] ?? '') . ' ' . ($row['lastName'] ?? '')),
                    $row['dateOfBirth'] ?? 'N/A',
                    $row['gender'] ?? 'N/A',
                    $row['email'] ?? 'N/A',
                    $row['phoneNumber'] ?? 'N/A',
                    $creatorId
                ]);
            }
        }
    } catch (Exception $e) {
        fputcsv($output, ['Export Error: ' . $e->getMessage()]);
    }

    fclose($output);
    exit;
}

// 4. Create staff account (with custom specialization input for Nurse)
function createStaffAccount($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        $firstName      = trim($input['firstName'] ?? '');
        $lastName       = trim($input['lastName'] ?? '');
        $emailPrefix    = trim($input['emailPrefix'] ?? '');
        $role           = trim($input['role'] ?? '');
        $specialization = trim($input['specialization'] ?? 'General Nursing');

        $currentAdminID = (int)($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 1);

        if (empty($firstName) || empty($lastName) || empty($emailPrefix) || empty($role)) {
            echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
            return;
        }

        if (!in_array($role, ['Admin', 'Nurse'])) {
            echo json_encode(['status' => 'error', 'message' => 'Selected role is invalid.']);
            return;
        }

        $domainSuffix = ($role === 'Nurse') ? '@nurse.stepcare.com' : '@admin.stepcare.com';
        $fullEmail = strtolower($emailPrefix) . $domainSuffix;

        if (!filter_var($fullEmail, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email prefix entered.']);
            return;
        }

        $checkAdmin = $pdo->prepare("SELECT adminID FROM admins WHERE email = ? LIMIT 1");
        $checkAdmin->execute([$fullEmail]);
        if ($checkAdmin->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'This email already belongs to an existing admin.']);
            return;
        }

        $checkNurse = $pdo->prepare("SELECT nurseID FROM nurses WHERE email = ? LIMIT 1");
        $checkNurse->execute([$fullEmail]);
        if ($checkNurse->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'This email already belongs to an existing nurse.']);
            return;
        }

        if ($role === 'Admin') {
            $stmt = $pdo->prepare("INSERT INTO admins (firstName, lastName, email, passwordHash, createdByAdminID) VALUES (?, ?, ?, 'temp', ?)");
            $stmt->execute([$firstName, $lastName, $fullEmail, $currentAdminID]);
            $newId = $pdo->lastInsertId();

            $cleanFirst = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $firstName));
            $defaultPassword = $cleanFirst . $newId;
            $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);

            $updateStmt = $pdo->prepare("UPDATE admins SET passwordHash = ? WHERE adminID = ?");
            $updateStmt->execute([$hashedPassword, $newId]);

            $formattedId = 'ADM-' . str_pad($newId, 3, '0', STR_PAD_LEFT);
        } else {
            $stmt = $pdo->prepare("INSERT INTO nurses (firstName, lastName, email, passwordHash, specialization, createdByAdminID) VALUES (?, ?, ?, 'temp', ?, ?)");
            $stmt->execute([$firstName, $lastName, $fullEmail, $specialization, $currentAdminID]);
            $newId = $pdo->lastInsertId();

            $cleanFirst = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $firstName));
            $defaultPassword = $cleanFirst . $newId;
            $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);

            $updateStmt = $pdo->prepare("UPDATE nurses SET passwordHash = ? WHERE nurseID = ?");
            $updateStmt->execute([$hashedPassword, $newId]);

            $formattedId = 'NUR-' . str_pad($newId, 3, '0', STR_PAD_LEFT);
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Staff account created successfully.',
            'created_user' => [
                'id' => $formattedId,
                'name' => "{$firstName} {$lastName}",
                'email' => $fullEmail,
                'role' => $role,
                'specialization' => ($role === 'Nurse') ? $specialization : null,
                'created_by' => 'ADM-' . str_pad($currentAdminID, 3, '0', STR_PAD_LEFT),
                'default_password' => $defaultPassword
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 5. Update staff details with custom specialization input
function updateStaffAccount($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        $rawId          = (int)($input['rawId'] ?? 0);
        $originalRole   = trim($input['originalRole'] ?? '');
        $newRole        = trim($input['role'] ?? '');
        $firstName      = trim($input['firstName'] ?? '');
        $lastName       = trim($input['lastName'] ?? '');
        $emailPrefix    = trim($input['emailPrefix'] ?? '');
        $specialization = trim($input['specialization'] ?? 'General Nursing');

        $dbOriginalRole = ($originalRole === 'Super Admin') ? 'Admin' : $originalRole;

        if (!$rawId || empty($dbOriginalRole) || empty($newRole) || empty($firstName) || empty($lastName) || empty($emailPrefix)) {
            echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
            return;
        }

        $domainSuffix = ($newRole === 'Nurse') ? '@nurse.stepcare.com' : '@admin.stepcare.com';
        $fullEmail = strtolower($emailPrefix) . $domainSuffix;

        if (!filter_var($fullEmail, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email format.']);
            return;
        }

        $adminCheck = $pdo->prepare("SELECT adminID FROM admins WHERE email = ? AND NOT (adminID = ? AND ? = 'Admin') LIMIT 1");
        $adminCheck->execute([$fullEmail, $rawId, $dbOriginalRole]);
        if ($adminCheck->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'This email is already in use by another admin.']);
            return;
        }

        $nurseCheck = $pdo->prepare("SELECT nurseID FROM nurses WHERE email = ? AND NOT (nurseID = ? AND ? = 'Nurse') LIMIT 1");
        $nurseCheck->execute([$fullEmail, $rawId, $dbOriginalRole]);
        if ($nurseCheck->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'This email is already in use by another nurse.']);
            return;
        }

        if ($dbOriginalRole === $newRole) {
            if ($newRole === 'Admin') {
                $stmt = $pdo->prepare("UPDATE admins SET firstName = ?, lastName = ?, email = ? WHERE adminID = ?");
                $stmt->execute([$firstName, $lastName, $fullEmail, $rawId]);
            } else {
                $stmt = $pdo->prepare("UPDATE nurses SET firstName = ?, lastName = ?, email = ?, specialization = ? WHERE nurseID = ?");
                $stmt->execute([$firstName, $lastName, $fullEmail, $specialization, $rawId]);
            }
            echo json_encode(['status' => 'success', 'message' => 'Staff details updated successfully.']);
            return;
        }

        if ($dbOriginalRole === 'Admin' && $newRole === 'Nurse') {
            if ($rawId === 1) {
                echo json_encode(['status' => 'error', 'message' => 'System Protection: Root Super Admin cannot be transferred to Nurse.']);
                return;
            }

            $srcStmt = $pdo->prepare("SELECT passwordHash, adminPhotoURL, createdByAdminID FROM admins WHERE adminID = ? LIMIT 1");
            $srcStmt->execute([$rawId]);
            $src = $srcStmt->fetch(PDO::FETCH_ASSOC);

            if (!$src) {
                echo json_encode(['status' => 'error', 'message' => 'Original admin account not found.']);
                return;
            }

            $insStmt = $pdo->prepare("INSERT INTO nurses (firstName, lastName, email, passwordHash, specialization, nursePhotoURL, createdByAdminID) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $insStmt->execute([$firstName, $lastName, $fullEmail, $src['passwordHash'], $specialization, $src['adminPhotoURL'], $src['createdByAdminID'] ?? 1]);

            $delStmt = $pdo->prepare("DELETE FROM admins WHERE adminID = ?");
            $delStmt->execute([$rawId]);

            echo json_encode(['status' => 'success', 'message' => 'Staff transferred to Nurse role successfully.']);
            return;
        }

        if ($dbOriginalRole === 'Nurse' && $newRole === 'Admin') {
            $srcStmt = $pdo->prepare("SELECT passwordHash, nursePhotoURL, createdByAdminID FROM nurses WHERE nurseID = ? LIMIT 1");
            $srcStmt->execute([$rawId]);
            $src = $srcStmt->fetch(PDO::FETCH_ASSOC);

            if (!$src) {
                echo json_encode(['status' => 'error', 'message' => 'Original nurse account not found.']);
                return;
            }

            $insStmt = $pdo->prepare("INSERT INTO admins (firstName, lastName, email, passwordHash, adminPhotoURL, createdByAdminID) VALUES (?, ?, ?, ?, ?, ?)");
            $insStmt->execute([$firstName, $lastName, $fullEmail, $src['passwordHash'], $src['nursePhotoURL'], $src['createdByAdminID'] ?? 1]);

            $delStmt = $pdo->prepare("DELETE FROM nurses WHERE nurseID = ?");
            $delStmt->execute([$rawId]);

            echo json_encode(['status' => 'success', 'message' => 'Staff transferred to Admin role successfully.']);
            return;
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}