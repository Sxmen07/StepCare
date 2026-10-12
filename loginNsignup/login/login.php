<?php
session_start();
require '../../config/db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    // Fetch user including photo URL
    $stmt = $conn->prepare(
        "SELECT userID, firstName, userPhotoURL, passwordHash, failedAttempt, lockedUntil
         FROM users WHERE email = ?"
    );
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // 1. Lock check
        if ($user['lockedUntil'] !== null) {
            $lockTime = strtotime($user['lockedUntil']);
            $now = time();

            if ($now < $lockTime) {
                $remainingMinutes = ceil(($lockTime - $now) / 60);
                header("Location: login.html?error=locked&time=" . $remainingMinutes);
                exit();
            } else {
                $stmt_reset = $conn->prepare(
                    "UPDATE users SET failedAttempt = 0, lockedUntil = NULL WHERE userID = ?"
                );
                $stmt_reset->bind_param("i", $user['userID']);
                $stmt_reset->execute();
                $user['failedAttempt'] = 0;
            }
        }

        // 2. Verify password
        if (password_verify($password, $user['passwordHash'])) {

            $stmt_success = $conn->prepare(
                "UPDATE users SET failedAttempt = 0, lockedUntil = NULL WHERE userID = ?"
            );
            $stmt_success->bind_param("i", $user['userID']);
            $stmt_success->execute();

            // Save session
            $_SESSION['userID']       = $user['userID'];
            $_SESSION['firstName']    = $user['firstName'];
            $_SESSION['userPhotoURL'] = $user['userPhotoURL'] ?? '';

            header("Location: ../../user/aboutUs/aboutUs.html");
            exit();

        } else {
            // Wrong password
            $attempts = $user['failedAttempt'] + 1;

            if ($attempts >= 3) {
                $stmt_lock = $conn->prepare(
                    "UPDATE users SET failedAttempt = ?, lockedUntil = DATE_ADD(NOW(), INTERVAL 3 MINUTE) WHERE userID = ?"
                );
                $stmt_lock->bind_param("ii", $attempts, $user['userID']);
                $stmt_lock->execute();

                header("Location: login.html?error=locked&time=3");
                exit();
            } else {
                $stmt_fail = $conn->prepare(
                    "UPDATE users SET failedAttempt = ? WHERE userID = ?"
                );
                $stmt_fail->bind_param("ii", $attempts, $user['userID']);
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