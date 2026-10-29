<?php
session_start();
require __DIR__ . '/../../config/db_connect.php';

if (empty($_SESSION['userID'])) {
    header('Location: ../../loginNsignup/login/login.html');
    exit;
}

$userID = (int)$_SESSION['userID'];
$appointmentID = (int)($_GET['id'] ?? 0);

if ($appointmentID > 0) {
    // Verify ownership
    $stmt = $pdo->prepare("SELECT appointmentID FROM appointments WHERE appointmentID = ? AND userID = ? AND appointmentStatus = 'pending'");
    $stmt->execute([$appointmentID, $userID]);
    
    if ($stmt->fetch()) {
        $pdo->prepare("UPDATE appointments SET appointmentStatus = 'cancelled' WHERE appointmentID = ?")->execute([$appointmentID]);
        $pdo->prepare("UPDATE payments SET paymentStatus = 'failed' WHERE appointmentID = ?")->execute([$appointmentID]);
    }
}

header('Location: services.php');
exit;