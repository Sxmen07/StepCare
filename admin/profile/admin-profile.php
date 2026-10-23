<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db_connect.php';

$adminId = $_SESSION['userID'] ?? $_SESSION['user_id'] ?? $_SESSION['adminID'] ?? null;
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_data':
        fetchAdminProfileData($pdo, $adminId);
        break;

    case 'update_profile':
        updateAdminProfileData($pdo, $adminId);
        break;

    case 'upload_avatar':
        uploadAdminAvatarImage($pdo, $adminId);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action specified.']);
        break;
}

// Dynamically inspect column existence in a table
function getExistingColumn($pdo, $table, $candidates) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
        $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($candidates as $cand) {
            foreach ($cols as $actual) {
                if (strcasecmp($cand, $actual) === 0) {
                    return $actual;
                }
            }
        }
    } catch (Exception $e) {}
    return null;
}

// Retrieve admin details and schedule records safely
function fetchAdminProfileData($pdo, $adminId) {
    try {
        // Resolve avatar column
        $photoCol = getExistingColumn($pdo, 'users', ['userPhotoURL', 'avatar', 'photo', 'image']);
        $photoSelect = $photoCol ? ", `{$photoCol}` AS userPhotoURL" : ", NULL AS userPhotoURL";

        $admin = null;

        if ($adminId) {
            $stmt = $pdo->prepare("SELECT userID, firstName, lastName, email {$photoSelect} FROM users WHERE userID = ? LIMIT 1");
            $stmt->execute([$adminId]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$admin) {
            $stmt = $pdo->query("SELECT userID, firstName, lastName, email {$photoSelect} FROM users ORDER BY userID ASC LIMIT 1");
            $admin = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            if ($admin) {
                $_SESSION['userID'] = $admin['userID'];
            }
        }

        if (!$admin) {
            echo json_encode(['status' => 'error', 'message' => 'No user record found.']);
            return;
        }

        // Dynamically locate date and status columns in appointments
        $dateCol = getExistingColumn($pdo, 'appointments', ['appointmentDate', 'appointment_date', 'date', 'bookingDate', 'booking_date', 'createdAt']);
        $timeCol = getExistingColumn($pdo, 'appointments', ['timeSlot', 'time_slot', 'appointmentTime', 'time']);
        $nurseCol = getExistingColumn($pdo, 'appointments', ['nurseID', 'nurse_id']);
        $statusCol = getExistingColumn($pdo, 'appointments', ['appointmentStatus', 'status']);
        $idCol = getExistingColumn($pdo, 'appointments', ['appointmentID', 'appointment_id', 'id']);

        // Calculate count of unassigned appointments safely
        $unassigned = 0;
        if ($nurseCol) {
            $countStmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE `{$nurseCol}` IS NULL OR `{$nurseCol}` = 0");
            $unassigned = $countStmt ? (int)$countStmt->fetchColumn() : 0;
        }

        // Construct query using dynamically detected fields
        $slots = [];
        if ($idCol && $dateCol) {
            $timeSelect = $timeCol ? "COALESCE(`{$timeCol}`, '09:00 - 11:00')" : "'09:00 - 11:00'";
            $statusSelect = $nurseCol ? "CASE WHEN `{$nurseCol}` IS NOT NULL AND `{$nurseCol}` > 0 THEN 'assigned' ELSE 'pending' END" : "'pending'";

            $query = "
                SELECT `{$idCol}` AS appointmentID,
                       `{$dateCol}` AS date,
                       {$timeSelect} AS timeSlot,
                       'General Care' AS serviceName,
                       {$statusSelect} AS status
                FROM appointments
                ORDER BY `{$dateCol}` DESC
                LIMIT 20
            ";
            $slotStmt = $pdo->query($query);
            $slots = $slotStmt ? $slotStmt->fetchAll(PDO::FETCH_ASSOC) : [];
        }

        echo json_encode([
            'status' => 'success',
            'admin' => $admin,
            'unassigned_count' => $unassigned,
            'slots' => $slots
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// Update profile without hardcoding column assumptions
function updateAdminProfileData($pdo, $adminId) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $firstName = trim($input['firstName'] ?? '');
        $lastName = trim($input['lastName'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if (empty($firstName) || empty($email)) {
            echo json_encode(['status' => 'error', 'message' => 'First name and email are required.']);
            return;
        }

        if (!$adminId) {
            $stmt = $pdo->query("SELECT userID FROM users ORDER BY userID ASC LIMIT 1");
            $adminId = $stmt ? $stmt->fetchColumn() : null;
        }

        if (!$adminId) {
            echo json_encode(['status' => 'error', 'message' => 'Target user account not found.']);
            return;
        }

        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET firstName = ?, lastName = ?, email = ?, password = ? WHERE userID = ?");
            $stmt->execute([$firstName, $lastName, $email, $hashed, $adminId]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET firstName = ?, lastName = ?, email = ? WHERE userID = ?");
            $stmt->execute([$firstName, $lastName, $email, $adminId]);
        }

        $_SESSION['userID'] = $adminId;
        $_SESSION['firstName'] = $firstName;
        $_SESSION['lastName'] = $lastName;

        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// Upload avatar and save path
function uploadAdminAvatarImage($pdo, $adminId) {
    try {
        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['status' => 'error', 'message' => 'File payload invalid.']);
            return;
        }

        $uploadDir = __DIR__ . '/../../images/avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        if (!$adminId) {
            $stmt = $pdo->query("SELECT userID FROM users ORDER BY userID ASC LIMIT 1");
            $adminId = $stmt ? $stmt->fetchColumn() : 1;
        }

        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        $filename = 'admin_' . $adminId . '_' . time() . '.' . $ext;
        $dest = $uploadDir . $filename;
        $dbPath = 'images/avatars/' . $filename;

        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $dest)) {
            $photoCol = getExistingColumn($pdo, 'users', ['userPhotoURL', 'avatar', 'photo', 'image']) ?? 'userPhotoURL';
            $stmt = $pdo->prepare("UPDATE users SET `{$photoCol}` = ? WHERE userID = ?");
            $stmt->execute([$dbPath, $adminId]);
            echo json_encode(['status' => 'success', 'avatar_url' => $dbPath]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to write file.']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>