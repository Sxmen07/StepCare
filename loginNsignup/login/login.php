<?php
session_start();
require '../../config/db_connect.php';

function getPortalByEmail(string $email): string
{
    $email = strtolower(trim($email));

    if (preg_match('/@admin\.stepcare\.com$/', $email)) {
        return 'admin';
    }

    if (preg_match('/@nurse\.stepcare\.com$/', $email)) {
        return 'nurse';
    }

    return 'user';
}

function getRedirectByPortal(string $portal): string
{
    switch ($portal) {
        case 'admin':
            return '/StepCare/admin/dashboard/dashboard.php';
        case 'nurse':
            return '/StepCare/nurse/dashboard/dashboard.php';
        default:
            return '/StepCare/user/aboutUs/aboutUs.php';
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    // 1. Determine the portal and set the correct table/columns
    $portal = getPortalByEmail($email);

    if ($portal === 'admin') {
        $table     = 'admins';
        $idColumn  = 'adminID';
        $photoColumn = 'adminPhotoURL';
    } elseif ($portal === 'nurse') {
        $table     = 'nurses';
        $idColumn  = 'nurseID';
        $photoColumn = 'nursePhotoURL';
    } else {
        $table     = 'users';
        $idColumn  = 'userID';
        $photoColumn = 'userPhotoURL';
    }

    // 2. Fetch user from the correct table
    $stmt = $conn->prepare(
        "SELECT $idColumn, firstName, $photoColumn, passwordHash, failedAttempt, lockedUntil
         FROM $table WHERE email = ?"
    );
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // 3. Lock check
        if ($user['lockedUntil'] !== null) {
            $lockTime = strtotime($user['lockedUntil']);
            $now = time();

            if ($now < $lockTime) {
                $remainingMinutes = ceil(($lockTime - $now) / 60);
                header("Location: login.html?error=locked&time=" . $remainingMinutes);
                exit();
            } else {
                $stmt_reset = $conn->prepare(
                    "UPDATE $table SET failedAttempt = 0, lockedUntil = NULL WHERE $idColumn = ?"
                );
                $stmt_reset->bind_param("i", $user[$idColumn]);
                $stmt_reset->execute();
                $user['failedAttempt'] = 0;
            }
        }

        // 4. Verify password
        if (password_verify($password, $user['passwordHash'])) {

            $stmt_success = $conn->prepare(
                "UPDATE $table SET failedAttempt = 0, lockedUntil = NULL WHERE $idColumn = ?"
            );
            $stmt_success->bind_param("i", $user[$idColumn]);
            $stmt_success->execute();

            // Save session (we map everything to userID so your header/footer still works)
            $_SESSION['userID']       = $user[$idColumn];
            $_SESSION['firstName']    = $user['firstName'];
            $_SESSION['userPhotoURL'] = $user[$photoColumn] ?? '';
            $_SESSION['role']         = $portal;

            // Redirect to the correct dashboard
            header("Location: " . getRedirectByPortal($portal));
            exit();

        } else {
            // 5. Wrong password
            $attempts = $user['failedAttempt'] + 1;

            if ($attempts >= 3) {
                $stmt_lock = $conn->prepare(
                    "UPDATE $table SET failedAttempt = ?, lockedUntil = DATE_ADD(NOW(), INTERVAL 3 MINUTE) WHERE $idColumn = ?"
                );
                $stmt_lock->bind_param("ii", $attempts, $user[$idColumn]);
                $stmt_lock->execute();

                header("Location: login.html?error=locked&time=3");
                exit();
            } else {
                $stmt_fail = $conn->prepare(
                    "UPDATE $table SET failedAttempt = ? WHERE $idColumn = ?"
                );
                $stmt_fail->bind_param("ii", $attempts, $user[$idColumn]);
                $stmt_fail->execute();

                header("Location: login.html?error=invalid");
                exit();
            }
        }
    } else {
        header("Location: login.html?error=invalid");
        exit();
    }

    $stmt->close();
    $conn->close();
}
?>