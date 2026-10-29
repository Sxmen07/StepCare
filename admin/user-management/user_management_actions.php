<?php
// English comments only
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db_connect.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'fetch_staff':
        fetchStaffList($pdo);
        break;

    case 'create_staff':
        createStaffAccount($pdo);
        break;

    case 'update_staff':
        updateStaffAccount($pdo);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action parameter']);
        break;
}

// 1. Fetch unified staff list
function fetchStaffList($pdo) {
    try {
        $staff = [];

        // Fetch admins
        $adminStmt = $pdo->query("SELECT adminID AS id, firstName, lastName, email, adminPhotoURL FROM admins ORDER BY adminID DESC");
        if ($adminStmt) {
            while ($row = $adminStmt->fetch(PDO::FETCH_ASSOC)) {
                $staff[] = [
                    'id' => 'ADM-' . str_pad($row['id'], 3, '0', STR_PAD_LEFT),
                    'raw_id' => (int)$row['id'],
                    'first_name' => $row['firstName'] ?? '',
                    'last_name' => $row['lastName'] ?? '',
                    'name' => trim(($row['firstName'] ?? '') . ' ' . ($row['lastName'] ?? '')),
                    'email' => $row['email'],
                    'role' => 'Admin',
                    'photo' => $row['adminPhotoURL'] ?? null
                ];
            }
        }

        // Fetch nurses
        $nurseStmt = $pdo->query("SELECT nurseID AS id, firstName, lastName, email, nursePhotoURL FROM nurses ORDER BY nurseID DESC");
        if ($nurseStmt) {
            while ($row = $nurseStmt->fetch(PDO::FETCH_ASSOC)) {
                $staff[] = [
                    'id' => 'NUR-' . str_pad($row['id'], 3, '0', STR_PAD_LEFT),
                    'raw_id' => (int)$row['id'],
                    'first_name' => $row['firstName'] ?? '',
                    'last_name' => $row['lastName'] ?? '',
                    'name' => trim(($row['firstName'] ?? '') . ' ' . ($row['lastName'] ?? '')),
                    'email' => $row['email'],
                    'role' => 'Nurse',
                    'photo' => $row['nursePhotoURL'] ?? null
                ];
            }
        }

        echo json_encode(['status' => 'success', 'data' => $staff]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 2. Create staff account
function createStaffAccount($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        $firstName   = trim($input['firstName'] ?? '');
        $lastName    = trim($input['lastName'] ?? '');
        $emailPrefix = trim($input['emailPrefix'] ?? '');
        $role        = trim($input['role'] ?? '');

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

        // Check duplicates
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
            $stmt = $pdo->prepare("INSERT INTO admins (firstName, lastName, email, passwordHash) VALUES (?, ?, ?, 'temp')");
            $stmt->execute([$firstName, $lastName, $fullEmail]);
            $newId = $pdo->lastInsertId();

            $cleanFirst = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $firstName));
            $defaultPassword = $cleanFirst . $newId;
            $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);

            $updateStmt = $pdo->prepare("UPDATE admins SET passwordHash = ? WHERE adminID = ?");
            $updateStmt->execute([$hashedPassword, $newId]);

            $formattedId = 'ADM-' . str_pad($newId, 3, '0', STR_PAD_LEFT);
        } else {
            $stmt = $pdo->prepare("INSERT INTO nurses (firstName, lastName, email, passwordHash) VALUES (?, ?, ?, 'temp')");
            $stmt->execute([$firstName, $lastName, $fullEmail]);
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
                'default_password' => $defaultPassword
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 3. Update staff details with cross-table migration support
function updateStaffAccount($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        $rawId       = (int)($input['rawId'] ?? 0);
        $originalRole= trim($input['originalRole'] ?? '');
        $newRole     = trim($input['role'] ?? '');
        $firstName   = trim($input['firstName'] ?? '');
        $lastName    = trim($input['lastName'] ?? '');
        $emailPrefix = trim($input['emailPrefix'] ?? '');

        if (!$rawId || empty($originalRole) || empty($newRole) || empty($firstName) || empty($lastName) || empty($emailPrefix)) {
            echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
            return;
        }

        $domainSuffix = ($newRole === 'Nurse') ? '@nurse.stepcare.com' : '@admin.stepcare.com';
        $fullEmail = strtolower($emailPrefix) . $domainSuffix;

        if (!filter_var($fullEmail, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email format.']);
            return;
        }

        // Email uniqueness validation (excluding current user)
        $adminCheck = $pdo->prepare("SELECT adminID FROM admins WHERE email = ? AND NOT (adminID = ? AND ? = 'Admin') LIMIT 1");
        $adminCheck->execute([$fullEmail, $rawId, $originalRole]);
        if ($adminCheck->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'This email is already in use by another admin.']);
            return;
        }

        $nurseCheck = $pdo->prepare("SELECT nurseID FROM nurses WHERE email = ? AND NOT (nurseID = ? AND ? = 'Nurse') LIMIT 1");
        $nurseCheck->execute([$fullEmail, $rawId, $originalRole]);
        if ($nurseCheck->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'This email is already in use by another nurse.']);
            return;
        }

        // Case A: Role unchanged
        if ($originalRole === $newRole) {
            if ($newRole === 'Admin') {
                $stmt = $pdo->prepare("UPDATE admins SET firstName = ?, lastName = ?, email = ? WHERE adminID = ?");
                $stmt->execute([$firstName, $lastName, $fullEmail, $rawId]);
            } else {
                $stmt = $pdo->prepare("UPDATE nurses SET firstName = ?, lastName = ?, email = ? WHERE nurseID = ?");
                $stmt->execute([$firstName, $lastName, $fullEmail, $rawId]);
            }
            echo json_encode(['status' => 'success', 'message' => 'Staff details updated successfully.']);
            return;
        }

        // Case B: Cross-table Role change (Admin -> Nurse or Nurse -> Admin)
        if ($originalRole === 'Admin' && $newRole === 'Nurse') {
            // Retrieve admin password hash and photo before moving
            $srcStmt = $pdo->prepare("SELECT passwordHash, adminPhotoURL FROM admins WHERE adminID = ? LIMIT 1");
            $srcStmt->execute([$rawId]);
            $src = $srcStmt->fetch(PDO::FETCH_ASSOC);

            if (!$src) {
                echo json_encode(['status' => 'error', 'message' => 'Original admin account not found.']);
                return;
            }

            // Insert into nurses table
            $insStmt = $pdo->prepare("INSERT INTO nurses (firstName, lastName, email, passwordHash, nursePhotoURL) VALUES (?, ?, ?, ?, ?)");
            $insStmt->execute([$firstName, $lastName, $fullEmail, $src['passwordHash'], $src['adminPhotoURL']]);

            // Remove from admins table
            $delStmt = $pdo->prepare("DELETE FROM admins WHERE adminID = ?");
            $delStmt->execute([$rawId]);

            echo json_encode(['status' => 'success', 'message' => 'Staff transferred to Nurse role successfully.']);
            return;
        }

        if ($originalRole === 'Nurse' && $newRole === 'Admin') {
            // Retrieve nurse password hash and photo before moving
            $srcStmt = $pdo->prepare("SELECT passwordHash, nursePhotoURL FROM nurses WHERE nurseID = ? LIMIT 1");
            $srcStmt->execute([$rawId]);
            $src = $srcStmt->fetch(PDO::FETCH_ASSOC);

            if (!$src) {
                echo json_encode(['status' => 'error', 'message' => 'Original nurse account not found.']);
                return;
            }

            // Insert into admins table
            $insStmt = $pdo->prepare("INSERT INTO admins (firstName, lastName, email, passwordHash, adminPhotoURL) VALUES (?, ?, ?, ?, ?)");
            $insStmt->execute([$firstName, $lastName, $fullEmail, $src['passwordHash'], $src['nursePhotoURL']]);

            // Remove from nurses table
            $delStmt = $pdo->prepare("DELETE FROM nurses WHERE nurseID = ?");
            $delStmt->execute([$rawId]);

            echo json_encode(['status' => 'success', 'message' => 'Staff transferred to Admin role successfully.']);
            return;
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>