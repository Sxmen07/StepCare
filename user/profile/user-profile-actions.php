<?php
// Prevent PHP notices from corrupting JSON output
error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=UTF-8');

// 1. Locate db_connect.php dynamically across root directories
$conn = null;
$possiblePaths = [
    __DIR__ . '/../../config/db_connect.php',
    __DIR__ . '/../../../config/db_connect.php',
    $_SERVER['DOCUMENT_ROOT'] . '/StepCare/config/db_connect.php'
];

foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

session_start();

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$requestedID = (int)($_SESSION['userID'] ?? $_SESSION['user_id'] ?? $_GET['user_id'] ?? $_POST['user_id'] ?? 0);

$response = ['success' => false, 'message' => ''];

try {
    if (!$conn || $conn->connect_error) {
        throw new Exception("Database connection could not be established.");
    }

    // -------------------------------------------------------------
    // Action 1: Fetch Profile and Appointments
    // -------------------------------------------------------------
    if ($action === 'fetch_profile') {
        $userData = null;

        // Fetch specified user or fallback to the first existing user in table
        if ($requestedID > 0) {
            $stmt = $conn->prepare("SELECT * FROM users WHERE userID = ?");
            $stmt->bind_param("i", $requestedID);
            $stmt->execute();
            $userData = $stmt->get_result()->fetch_assoc();
        }

        if (!$userData) {
            $userQuery = $conn->query("SELECT * FROM users ORDER BY userID ASC LIMIT 1");
            if ($userQuery && $userQuery->num_rows > 0) {
                $userData = $userQuery->fetch_assoc();
            }
        }

        if (!$userData) {
            throw new Exception("No user accounts found in users table.");
        }

        $activeUserID = (int)$userData['userID'];

        if (empty($userData['userPhotoURL'])) {
            $userData['userPhotoURL'] = '/StepCare/images/default.jpg';
        }

        // Fetch upcoming appointments count (case-insensitive column check)
        $upcomingCount = 0;
        $checkAppts = $conn->query("SHOW TABLES LIKE 'appointments'");
        if ($checkAppts && $checkAppts->num_rows > 0) {
            $colRes = $conn->query("SHOW COLUMNS FROM appointments");
            $cols = [];
            while ($c = $colRes->fetch_assoc()) $cols[] = $c['Field'];

            $statusCol = in_array('appointmentStatus', $cols) ? 'appointmentStatus' : 'status';
            $cStmt = $conn->prepare("SELECT COUNT(*) AS total FROM appointments WHERE userID = ? AND {$statusCol} IN ('booked', 'pending', 'in_progress')");
            if ($cStmt) {
                $cStmt->bind_param("i", $activeUserID);
                $cStmt->execute();
                $upcomingCount = (int)$cStmt->get_result()->fetch_assoc()['total'];
            }
        }

        // Fetch family membership count
        $familyMemberCount = 0;
        $hasFamily = false;
        $checkFam = $conn->query("SHOW TABLES LIKE 'family_members'");
        if ($checkFam && $checkFam->num_rows > 0) {
            $fStmt = $conn->prepare("SELECT COUNT(*) AS total FROM family_members WHERE userID = ?");
            $fStmt->bind_param("i", $activeUserID);
            $fStmt->execute();
            $familyMemberCount = (int)$fStmt->get_result()->fetch_assoc()['total'];
            $hasFamily = ($familyMemberCount > 0);
        }

        // Fetch appointments list
        $appointmentsList = [];
        if ($checkAppts && $checkAppts->num_rows > 0) {
            $dateCol = in_array('appointmentDate', $cols) ? 'appointmentDate' : (in_array('date', $cols) ? 'date' : $cols[0]);
            $statusCol = in_array('appointmentStatus', $cols) ? 'appointmentStatus' : (in_array('status', $cols) ? 'status' : $cols[1]);
            $priceCol = in_array('price', $cols) ? 'price' : (in_array('totalPrice', $cols) ? 'totalPrice' : 0);

            $apptsSql = "SELECT a.*, 
                                a.{$dateCol} AS computedDate,
                                a.{$statusCol} AS computedStatus,
                                " . ($priceCol ? "a.{$priceCol}" : "0.00") . " AS computedPrice,
                                COALESCE(s.serviceName, 'Care Service') AS serviceName, 
                                CONCAT(n.firstName, ' ', n.lastName) AS nurseName
                         FROM appointments a
                         LEFT JOIN services s ON a.serviceID = s.serviceID
                         LEFT JOIN nurses n ON a.nurseID = n.nurseID
                         WHERE a.userID = ?
                         ORDER BY a.{$dateCol} DESC";

            $aStmt = $conn->prepare($apptsSql);
            if ($aStmt) {
                $aStmt->bind_param("i", $activeUserID);
                $aStmt->execute();
                $res = $aStmt->get_result();
                while ($r = $res->fetch_assoc()) {
                    $appointmentsList[] = [
                        'appointmentID'     => $r['appointmentID'],
                        'appointmentDate'   => $r['computedDate'],
                        'appointmentStatus' => $r['computedStatus'],
                        'price'             => $r['computedPrice'],
                        'serviceName'       => $r['serviceName'],
                        'nurseName'         => $r['nurseName']
                    ];
                }
            }
        }

        $response['success'] = true;
        $response['user'] = $userData;
        $response['stats'] = [
            'upcoming_appointments' => $upcomingCount,
            'has_family'            => $hasFamily,
            'family_count'          => $familyMemberCount
        ];
        $response['appointments'] = $appointmentsList;
    }

    // -------------------------------------------------------------
    // Action 2: Upload Avatar Image
    // -------------------------------------------------------------
    elseif ($action === 'upload_avatar') {
        $targetID = (int)($_POST['userID'] ?? $requestedID);
        if ($targetID <= 0) {
            $uQ = $conn->query("SELECT userID FROM users ORDER BY userID ASC LIMIT 1");
            $targetID = (int)$uQ->fetch_assoc()['userID'];
        }

        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("No valid image uploaded.");
        }

        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
            throw new Exception("Invalid image format.");
        }

        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/StepCare/images/profiles/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = 'avatar_user_' . $targetID . '_' . time() . '.' . $ext;
        $destPath = $uploadDir . $fileName;

        if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $destPath)) {
            throw new Exception("Failed to save image file.");
        }

        $photoURL = '/StepCare/images/profiles/' . $fileName;
        $upd = $conn->prepare("UPDATE users SET userPhotoURL = ? WHERE userID = ?");
        $upd->bind_param("si", $photoURL, $targetID);
        $upd->execute();

        $response['success'] = true;
        $response['photoURL'] = $photoURL;
        $response['message'] = "Avatar updated successfully.";
    }

    // -------------------------------------------------------------
    // Action 3: Update Profile Info
    // -------------------------------------------------------------
    elseif ($action === 'update_profile') {
        $targetID = (int)($_POST['userID'] ?? $requestedID);
        if ($targetID <= 0) {
            $uQ = $conn->query("SELECT userID FROM users ORDER BY userID ASC LIMIT 1");
            $targetID = (int)$uQ->fetch_assoc()['userID'];
        }

        $firstName             = trim($_POST['firstName'] ?? '');
        $lastName              = trim($_POST['lastName'] ?? '');
        $email                 = trim($_POST['email'] ?? '');
        $phoneNumber           = trim($_POST['phoneNumber'] ?? '');
        $IC                    = trim($_POST['IC'] ?? '');
        $gender                = trim($_POST['gender'] ?? '');
        $dateOfBirth           = trim($_POST['dateOfBirth'] ?? '');
        $address               = trim($_POST['address'] ?? '');
        $emergencyContactName  = trim($_POST['emergencyContactName'] ?? '');
        $emergencyContactPhone = trim($_POST['emergencyContactPhone'] ?? '');

        $sql = "UPDATE users SET 
                    firstName = ?, 
                    lastName = ?, 
                    email = ?, 
                    phoneNumber = ?, 
                    IC = ?, 
                    gender = ?, 
                    dateOfBirth = ?, 
                    address = ?, 
                    emergencyContactName = ?, 
                    emergencyContactPhone = ?
                WHERE userID = ?";

        $upd = $conn->prepare($sql);
        $upd->bind_param(
            "ssssssssssi",
            $firstName,
            $lastName,
            $email,
            $phoneNumber,
            $IC,
            $gender,
            $dateOfBirth,
            $address,
            $emergencyContactName,
            $emergencyContactPhone,
            $targetID
        );

        if (!$upd->execute()) {
            throw new Exception("Failed to update profile: " . $upd->error);
        }

        $response['success'] = true;
        $response['message'] = "Profile information updated successfully.";
    }
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;