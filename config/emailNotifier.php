<?php
// StepCare/config/emailNotifier.php

require_once __DIR__ . '/../lib/PHPMailer-7.1.1/src/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer-7.1.1/src/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer-7.1.1/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load .env variables
function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value, '"\'');
    }
}
loadEnv(__DIR__ . '/../.env');

/**
 * Main function to trigger a notification (Database + Email)
 */
function createNotification($pdo, $recipientType, $recipientID, $title, $message, $linkURL = '') {
    // 1. Insert into Database
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (recipientType, recipientID, title, message, linkURL, isRead, createdAt) 
            VALUES (?, ?, ?, ?, ?, 0, NOW())
        ");
        $stmt->execute([$recipientType, $recipientID, $title, $message, $linkURL]);
        $notificationID = $pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("Failed to create DB notification: " . $e->getMessage());
        return false;
    }

    // 2. Send Email if recipient is a user
    if ($recipientType === 'user') {
        sendUserEmailNotification($pdo, $recipientID, $title, $message, $linkURL);
    }

    return $notificationID;
}

/**
 * Sends the email via Gmail SMTP
 */
function sendUserEmailNotification($pdo, $userID, $title, $message, $linkURL = '') {
    $stmt = $pdo->prepare("SELECT firstName, lastName, email FROM users WHERE userID = ?");
    $stmt->execute([$userID]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || empty($user['email'])) return false;

    $toEmail = $user['email'];
    $toName  = trim($user['firstName'] . ' ' . $user['lastName']);

    $baseUrl = 'http://localhost/StepCare';
    $fullLink = $baseUrl . ($linkURL ?: '/user/notifications/notifications.php');

    $htmlBody = '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 16px; background-color: #f8fafc;">
        <h2 style="color: #3C52C3; margin-top: 0; font-size: 22px;">StepCare Notification</h2>
        <p style="font-size: 15px; color: #1e293b; font-weight: bold;">Hello ' . htmlspecialchars($toName) . ',</p>
        <p style="font-size: 14px; color: #475569; line-height: 1.6;">' . htmlspecialchars($message) . '</p>
        
        <div style="margin: 28px 0;">
            <a href="' . htmlspecialchars($fullLink) . '" style="background-color: #3C52C3; color: white; padding: 12px 24px; text-decoration: none; border-radius: 10px; font-weight: bold; display: inline-block; font-size: 14px;">View in StepCare</a>
        </div>
        
        <p style="font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 14px; margin-top: 24px;">
            This is an automated notification sent to your registered email account. Please do not reply directly.
        </p>
    </div>';

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['GMAIL_USER'] ?? '';
        $mail->Password   = $_ENV['GMAIL_PASS'] ?? '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom($_ENV['GMAIL_USER'] ?? 'no-reply@stepcare.com', 'StepCare Notifications');
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = $title;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Gmail delivery failed: {$mail->ErrorInfo}");
        return false;
    }
}
?>