<?php
// English comments only
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db_connect.php';

// Target the authentic adminID from session, fallback to row 1
$adminId = $_SESSION['adminID'] ?? $_SESSION['userID'] ?? 1;
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_data':
        fetchAdminData($pdo, $adminId);
        break;

    case 'update_profile':
        updateAdminData($pdo, $adminId);
        break;

    case 'upload_avatar':
        uploadAdminAvatar($pdo, $adminId);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}

// 1. Fetch exact admin record directly from `admins` table
function fetchAdminData($pdo, $adminId) {
    try {
        $stmt = $pdo->prepare("SELECT adminID, firstName, lastName, email, adminPhotoURL FROM admins WHERE adminID = ? LIMIT 1");
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fallback to first admin record if specific ID not found in session
        if (!$admin) {
            $stmt = $pdo->query("SELECT adminID, firstName, lastName, email, adminPhotoURL FROM admins ORDER BY adminID ASC LIMIT 1");
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$admin) {
            echo json_encode(['status' => 'error', 'message' => 'No record in admins table.']);
            return;
        }

        // Store active adminID in session
        $_SESSION['adminID'] = $admin['adminID'];

        // 2. Count unassigned appointments safely
        $unassigned = 0;
        try {
            $countStmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE nurseID IS NULL OR nurseID = 0 OR appointmentStatus = 'pending'");
            $unassigned = $countStmt ? (int)$countStmt->fetchColumn() : 0;
        } catch (Exception $ex) {}

        // 3. Fetch appointment slots safely
        $slots = [];
        try {
            $stmt = $pdo->query("SELECT * FROM appointments ORDER BY 1 DESC LIMIT 20");
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            foreach ($rows as $row) {
                $date = $row['appointmentDate'] ?? $row['date'] ?? $row['bookingDate'] ?? '-';
                $timeSlot = $row['timeSlot'] ?? $row['time_slot'] ?? $row['time'] ?? '09:00 - 11:00';
                $nurseId = $row['nurseID'] ?? $row['nurse_id'] ?? null;
                $statusVal = strtolower((string)($row['appointmentStatus'] ?? $row['status'] ?? ''));

                $isAssigned = (!empty($nurseId) && (int)$nurseId > 0) || in_array($statusVal, ['assigned', 'completed']);

                $slots[] = [
                    'appointmentID' => $row['appointmentID'] ?? $row['id'] ?? 0,
                    'date' => $date,
                    'timeSlot' => $timeSlot,
                    'serviceName' => $row['serviceName'] ?? 'General Care',
                    'status' => $isAssigned ? 'assigned' : 'pending'
                ];
            }
        } catch (Exception $ex) {}

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

// 2. Update directly into `admins` table
function updateAdminData($pdo, $adminId) {
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

        $targetId = $_SESSION['adminID'] ?? $adminId ?? 1;

        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE admins SET firstName = ?, lastName = ?, email = ?, passwordHash = ? WHERE adminID = ?");
            $stmt->execute([$firstName, $lastName, $email, $hashed, $targetId]);
        } else {
            $stmt = $pdo->prepare("UPDATE admins SET firstName = ?, lastName = ?, email = ? WHERE adminID = ?");
            $stmt->execute([$firstName, $lastName, $email, $targetId]);
        }

        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 3. Upload avatar and write to adminPhotoURL in `admins` table
function uploadAdminAvatar($pdo, $adminId) {
    try {
        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['status' => 'error', 'message' => 'Upload error or no file sent.']);
            return;
        }

        $uploadDir = __DIR__ . '/../../images/avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $targetId = $_SESSION['adminID'] ?? $adminId ?? 1;
        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        $filename = 'admin_' . $targetId . '_' . time() . '.' . $ext;
        $dest = $uploadDir . $filename;
        $dbPath = 'images/avatars/' . $filename;

        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $dest)) {
            $stmt = $pdo->prepare("UPDATE admins SET adminPhotoURL = ? WHERE adminID = ?");
            $stmt->execute([$dbPath, $targetId]);
            echo json_encode(['status' => 'success', 'avatar_url' => $dbPath]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to move uploaded file.']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>