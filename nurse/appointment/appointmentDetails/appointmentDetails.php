<?php
// nurse/appointmentDetails/appointmentDetails.php
session_start();
require __DIR__ . '/../../../config/db_connect.php';

if (empty($_SESSION['nurseID'])) {
    header('Location: /StepCare/loginNsignup/login/login.html');
    exit;
}
$nurseID = (int)$_SESSION['nurseID'];
$appointmentID = (int)($_GET['id'] ?? 0);

if ($appointmentID <= 0) {
    header('Location: ../appointment.php');
    exit;
}

// 1. Fetch Full Details
$stmt = $pdo->prepare("
    SELECT a.*, 
           s.serviceName, s.category, 
           u.firstName, u.lastName, u.phoneNumber, u.gender, 
           u.emergencyContactName, u.emergencyContactPhone, u.address, u.email
    FROM appointments a
    JOIN services s ON a.serviceID = s.serviceID
    JOIN users u ON a.userID = u.userID
    WHERE a.appointmentID = ? AND a.nurseID = ?
");
$stmt->execute([$appointmentID, $nurseID]);
$appt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$appt) {
    die("Appointment not found or not assigned to you.");
}

// 2. Fetch Activity Timeline (ASC order so oldest is first)
$stmt = $pdo->prepare("
    SELECT status, title, message, createdBy, createdAt 
    FROM appointment_timeline 
    WHERE appointmentID = ? 
    ORDER BY createdAt ASC
");
$stmt->execute([$appointmentID]);
$timeline = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare JSON
$apptJson = json_encode($appt, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$timelineJson = json_encode($timeline, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

include __DIR__ . '/appointmentDetails.html';
?>