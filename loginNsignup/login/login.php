<?php
// English comments only
session_start();
require '../../config/db_connect.php';

function getRedirectByPortal(string $portal): string
{
    switch ($portal) {
        case 'admin': return '/StepCare/admin/dashboard/dashboard.html';
        case 'nurse': return '/StepCare/nurse/dashboard/nurse-dashboard.html';
        default:      return '/StepCare/user/aboutUs/aboutUs.php';
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        header("Location: login.html?error=empty_fields");
        exit();
    }

    $portal = '';
    $table = '';
    $idColumn = '';
    $photoColumn = '';
    $user = null;

    // Dynamically check across admins, nurses, and users tables
    $queries = [
        ['portal' => 'admin', 'table' => 'admins', 'idCol' => 'adminID', 'photoCol' => 'adminPhotoURL'],
        ['portal' => 'nurse', 'table' => 'nurses', 'idCol' => 'nurseID', 'photoCol' => 'nursePhotoURL'],
        ['portal' => 'user',  'table' => 'users',  'idCol' => 'userID',  'photoCol' => 'userPhotoURL']
    ];

    foreach ($queries as $q) {
        $stmt = $conn->prepare("SELECT {$q['idCol']} AS id, firstName, {$q['photoCol']} AS photoURL, passwordHash, failedAttempt, lockedUntil FROM {$q['table']} WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                $portal = $q['portal'];
                $table = $q['table'];
                $idColumn = $q['idCol'];
                $photoColumn = $q['photoCol'];
                $stmt->close();
                break;
            }
            $stmt->close();
        }
    }

    // If user account exists in one of the tables
    if ($user) {
        // Check if account is locked
        if (!empty($user['lockedUntil'])) {
            $lockTime = strtotime($user['lockedUntil']);
            $now = time();
            if ($now < $lockTime) {
                $remainingMinutes = ceil(($lockTime - $now) / 60);
                header("Location: login.html?error=locked&time=" . $remainingMinutes);
                exit();
            } else {
                // Lock expired, reset failed attempts
                $stmt_reset = $conn->prepare("UPDATE $table SET failedAttempt = 0, lockedUntil = NULL WHERE $idColumn = ?");
                $stmt_reset->bind_param("i", $user['id']);
                $stmt_reset->execute();
                $stmt_reset->close();
                $user['failedAttempt'] = 0;
            }
        }

        // Verify password
        if (password_verify($password, $user['passwordHash'])) {
            // Reset failed attempts on successful login
            $stmt_success = $conn->prepare("UPDATE $table SET failedAttempt = 0, lockedUntil = NULL WHERE $idColumn = ?");
            $stmt_success->bind_param("i", $user['id']);
            $stmt_success->execute();
            $stmt_success->close();

            // Set session variables
            $_SESSION['firstName']    = $user['firstName'];
            $_SESSION['userPhotoURL'] = $user['photoURL'] ?? '';
            $_SESSION['role']         = $portal;

            if ($portal === 'admin') {
                $_SESSION['adminID'] = $user['id'];
            } elseif ($portal === 'nurse') {
                $_SESSION['nurseID'] = $user['id'];
            } else {
                $_SESSION['userID'] = $user['id'];
            }

            header("Location: " . getRedirectByPortal($portal));
            exit();
        } else {
            // Incorrect password handling
            $attempts = (int)($user['failedAttempt'] ?? 0) + 1;
            if ($attempts >= 3) {
                $stmt_lock = $conn->prepare("UPDATE $table SET failedAttempt = ?, lockedUntil = DATE_ADD(NOW(), INTERVAL 3 MINUTE) WHERE $idColumn = ?");
                $stmt_lock->bind_param("ii", $attempts, $user['id']);
                $stmt_lock->execute();
                $stmt_lock->close();
                header("Location: login.html?error=locked&time=3");
                exit();
            } else {
                $stmt_fail = $conn->prepare("UPDATE $table SET failedAttempt = ? WHERE $idColumn = ?");
                $stmt_fail->bind_param("ii", $attempts, $user['id']);
                $stmt_fail->execute();
                $stmt_fail->close();
                header("Location: login.html?error=wrong_password&attempt=" . $attempts);
                exit();
            }
        }
    } else {
        // Email not found in admins, nurses, or users
        header("Location: login.html?error=wrong_email");
        exit();
    }

    $conn->close();
}
?>