<?php
session_start();
require __DIR__ . '/../../../../config/db_connect.php';
$userID = (int)($_SESSION['userID'] ?? 0);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE recipientType = 'user' AND recipientID = ? AND isRead = 0");
$stmt->execute([$userID]);
echo json_encode(['unread' => $stmt->fetchColumn()]);
?>