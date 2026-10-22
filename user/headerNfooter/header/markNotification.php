<?php
session_start();
require __DIR__ . '/../../../config/db_connect.php'; // Adjust path if necessary

header('Content-Type: application/json');

if (empty($_SESSION['userID'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$userID = (int)$_SESSION['userID'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle "Mark All as Read"
    if (isset($_POST['mark_all'])) {
        try {
            $stmt = $pdo->prepare("UPDATE notifications SET isRead = 1 WHERE recipientType = 'user' AND recipientID = ?");
            $stmt->execute([$userID]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // Handle individual toggle
    if (isset($_POST['id']) && isset($_POST['status'])) {
        $notifID = (int)$_POST['id'];
        $status  = (int)$_POST['status']; // 1 for read, 0 for unread

        try {
            // Ensure user owns this notification
            $stmt = $pdo->prepare("UPDATE notifications SET isRead = ? WHERE notificationID = ? AND recipientID = ? AND recipientType = 'user'");
            $stmt->execute([$status, $notifID, $userID]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);