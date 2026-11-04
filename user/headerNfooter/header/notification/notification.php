<?php
// StepCare/headerNfooter/header/notification/notification.php
session_start();
require __DIR__ . '/../../../../config/db_connect.php'; // 4 levels up to root config

if (empty($_SESSION['userID'])) {
    header('Location: /StepCare/loginNsignup/login/login.html');
    exit;
}

$userID = (int)$_SESSION['userID'];

// Fetch ALL notifications for the user
$stmt = $pdo->prepare("
    SELECT notificationID, title, message, linkURL, isRead, createdAt 
    FROM notifications 
    WHERE recipientType = 'user' AND recipientID = ? 
    ORDER BY createdAt DESC
");
$stmt->execute([$userID]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$notificationsJson = json_encode($notifications, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

include __DIR__ . '/notification.html';
?>