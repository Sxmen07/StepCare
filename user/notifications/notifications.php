<?php
session_start();
require __DIR__ . '/../../config/db_connect.php';

if (empty($_SESSION['userID'])) {
    header('Location: ../../loginNsignup/login/login.html');
    exit;
}

$userID = (int)$_SESSION['userID'];
$currentPage = 'notifications'; // For header highlighting

// Fetch all notifications
$stmt = $pdo->prepare("
    SELECT notificationID, title, message, linkURL, isRead, createdAt 
    FROM notifications 
    WHERE recipientType = 'user' AND recipientID = ? 
    ORDER BY createdAt DESC
");
$stmt->execute([$userID]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count unread for the filter badges
$unreadCount = 0;
foreach ($notifications as $n) {
    if (!$n['isRead']) $unreadCount++;
}

// Load the view
include __DIR__ . '/notifications.html';
?>