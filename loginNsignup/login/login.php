<?php
session_start();
require '../../config/db_connect.php';

function getPortalByEmail(string $email): string
{
    $email = strtolower(trim($email));
    if (preg_match('/@admin\.stepcare\.com$/', $email)) return 'admin';
    if (preg_match('/@nurse\.stepcare\.com$/', $email)) return 'nurse';
    return 'user';
}

function getRedirectByPortal(string $portal): string
{
    switch ($portal) {
        case 'admin': return '/StepCare/admin/dashboard/admin-dashboard.html';
        case 'nurse': return '/StepCare/nurse/dashboard/nurse-dashboard.html';
        default:      return '/StepCare/user/aboutUs/aboutUs.php';
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    $portal = getPortalByEmail($email);

    if ($portal === 'admin') {
        $table = 'admins'; $idColumn = 'adminID'; $photoColumn = 'adminPhotoURL';
    } elseif ($portal === 'nurse') {
        $table = 'nurses'; $idColumn = 'nurseID'; $photoColumn = 'nursePhotoURL';
    } else {
        $table = 'users'; $idColumn = 'userID'; $photoColumn = 'userPhotoURL';
    }

    if ($table === 'users') {
        $stmt = $conn->prepare("SELECT $idColumn, firstName, $photoColumn, passwordHash, failedAttempt, lockedUntil, accountStatus FROM $table WHERE email = ?");
    } else {
        $stmt = $conn->prepare("SELECT $idColumn, firstName, $photoColumn, passwordHash, failedAttempt, lockedUntil, 'Active' AS accountStatus FROM $table WHERE email = ?");
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (isset($user['accountStatus']) && $user['accountStatus'] === 'Banned') {
            header("Location: login.html?error=banned");
            exit();
        }

        if ($user['lockedUntil'] !== null) {
            $lockTime = strtotime($user['lockedUntil']);
            $now = time();
            if ($now < $lockTime) {
                $remainingMinutes = ceil(($lockTime - $now) / 60);
                header("Location: login.html?error=locked&time=" . $remainingMinutes);
                exit();
            } else {
                $stmt_reset = $conn->prepare("UPDATE $table SET failedAttempt = 0, lockedUntil = NULL WHERE $idColumn = ?");
                $stmt_reset->bind_param("i", $user[$idColumn]);
                $stmt_reset->execute();
                $user['failedAttempt'] = 0;
            }
        }

        if (password_verify($password, $user['passwordHash'])) {
            $stmt_success = $conn->prepare("UPDATE $table SET failedAttempt = 0, lockedUntil = NULL WHERE $idColumn = ?");
            $stmt_success->bind_param("i", $user[$idColumn]);
            $stmt_success->execute();

            // Clear old session variables to prevent data crossover
            session_unset();

            // Set unified & role-specific session variables
            $_SESSION['role']         = $portal;
            $_SESSION['firstName']    = $user['firstName'];
            $_SESSION['userPhotoURL'] = $user[$photoColumn] ?? '';

            if ($portal === 'admin') {
                $_SESSION['adminID']  = $user['adminID'];
                $_SESSION['admin_id'] = $user['adminID']; // Fallback alias
            } elseif ($portal === 'nurse') {
                $_SESSION['nurseID']  = $user['nurseID'];
                $_SESSION['nurse_id'] = $user['nurseID']; // Fallback alias
            } else {
                $_SESSION['userID']   = $user['userID'];
                $_SESSION['user_id']  = $user['userID'];  // Fallback alias
            }

            header("Location: " . getRedirectByPortal($portal));
            exit();
        } else {
            $attempts = $user['failedAttempt'] + 1;
            if ($attempts >= 3) {
                $stmt_lock = $conn->prepare("UPDATE $table SET failedAttempt = ?, lockedUntil = DATE_ADD(NOW(), INTERVAL 3 MINUTE) WHERE $idColumn = ?");
                $stmt_lock->bind_param("ii", $attempts, $user[$idColumn]);
                $stmt_lock->execute();
                header("Location: login.html?error=locked&time=3");
                exit();
            } else {
                $stmt_fail = $conn->prepare("UPDATE $table SET failedAttempt = ? WHERE $idColumn = ?");
                $stmt_fail->bind_param("ii", $attempts, $user[$idColumn]);
                $stmt_fail->execute();
                header("Location: login.html?error=wrong_password&attempt=" . $attempts);
                exit();
            }
        }
    } else {
        header("Location: login.html?error=wrong_email");
        exit();
    }
    $stmt->close();
    $conn->close();
}
?>