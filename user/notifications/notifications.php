<?php
// user/notifications/notifications.php
session_start();
require __DIR__ . '/../../config/db_connect.php';

if (empty($_SESSION['userID'])) {
    header('Location: ../../loginNsignup/login/login.html');
    exit;
}

$userID = (int)$_SESSION['userID'];
$currentPage = 'notifications';

// Fetch all notifications for this user
$stmt = $pdo->prepare("
    SELECT notificationID, title, message, linkURL, isRead, createdAt 
    FROM notifications 
    WHERE recipientType = 'user' AND recipientID = ? 
    ORDER BY createdAt DESC
");
$stmt->execute([$userID]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count unread notifications
$unreadCount = 0;
foreach ($notifications as $n) {
    if (!(int)$n['isRead']) $unreadCount++;
}

include __DIR__ . '/notifications.html';
?>