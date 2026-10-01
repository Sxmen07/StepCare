<?php
session_start();
require '../../config/db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Get data from the HTML form
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // 1. Check if the user exists in the database
    // We use prepared statements to prevent SQL Injection (Hacking)
    $stmt = $conn->prepare("SELECT userID, firstName, passwordHash, failedAttempt, lockedUntil FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // 2. Check if the account is currently locked
        if ($user['lockedUntil'] !== null) {
            $lockTime = strtotime($user['lockedUntil']);
            $now = time();
            
            if ($now < $lockTime) {
                // Account is still locked
                $remainingSeconds = $lockTime - $now;
                $remainingMinutes = ceil($remainingSeconds / 60);
                header("Location: login.html?error=locked&time=" . $remainingMinutes);
                exit();
            } else {
                // Lock time has expired, reset the attempts
                $stmt_reset = $conn->prepare("UPDATE users SET failedAttempt = 0, lockedUntil = NULL WHERE userID = ?");
                $stmt_reset->bind_param("i", $user['userID']);
                $stmt_reset->execute();
                $user['failedAttempt'] = 0; // Update local variable
            }
        }

        // 3. Verify the Password
        // Note: Passwords MUST be hashed using password_hash() during signup!
        if (password_verify($password, $user['passwordHash'])) {
            
            // --- SUCCESSFUL LOGIN ---
            // Reset failed attempts
            $stmt_success = $conn->prepare("UPDATE users SET failedAttempt = 0, lockedUntil = NULL WHERE userID = ?");
            $stmt_success->bind_param("i", $user['userID']);
            $stmt_success->execute();

            // Set session variables
            $_SESSION['userID'] = $user['userID'];
            $_SESSION['firstName'] = $user['firstName'];

            // Redirect to user dashboard (adjust path as needed)
            header("Location: ../../user/dashboard.html");
            exit();

        } else {
            // --- WRONG PASSWORD ---
            $attempts = $user['failedAttempt'] + 1;
            
            if ($attempts >= 3) {
                // Lock for 3 minutes
                $stmt_lock = $conn->prepare("UPDATE users SET failedAttempt = ?, lockedUntil = DATE_ADD(NOW(), INTERVAL 3 MINUTE) WHERE userID = ?");
                $stmt_lock->bind_param("ii", $attempts, $user['userID']);
                $stmt_lock->execute();
                
                header("Location: login.html?error=locked&time=3");
                exit();
            } else {
                // Increment failed attempt
                $stmt_fail = $conn->prepare("UPDATE users SET failedAttempt = ? WHERE userID = ?");
                $stmt_fail->bind_param("ii", $attempts, $user['userID']);
                $stmt_fail->execute();
                
                header("Location: login.html?error=invalid");
                exit();
            }
        }
    } else {
        // --- EMAIL NOT FOUND ---
        // For security, we don't tell them "Email doesn't exist", we just say "Invalid email or password"
        header("Location: login.html?error=invalid");
        exit();
    }
    
    $stmt->close();
    $conn->close();
}
?>