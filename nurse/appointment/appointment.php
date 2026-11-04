<?php
// nurse/appointment.php
session_start();
require __DIR__ . '/../../config/db_connect.php';

if (empty($_SESSION['nurseID'])) {
    header('Location: /StepCare/loginNsignup/login/login.html');
    exit;
}

$nurseID = (int)$_SESSION['nurseID'];

// ==========================================
// 1. Fetch Dashboard Stats
// ==========================================
$stats = ['today' => 0, 'upcoming' => 0, 'completed' => 0];

// Today's Appointments
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM appointments 
    WHERE nurseID = ? 
      AND DATE(startDateTime) = CURDATE() 
      AND appointmentStatus NOT IN ('cancelled', 'declined')
");
$stmt->execute([$nurseID]);
$stats['today'] = (int)$stmt->fetchColumn();

// Upcoming Appointments
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM appointments 
    WHERE nurseID = ? 
      AND startDateTime > NOW() 
      AND appointmentStatus IN ('pending', 'confirmed')
");
$stmt->execute([$nurseID]);
$stats['upcoming'] = (int)$stmt->fetchColumn();

// Completed Appointments
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM appointments 
    WHERE nurseID = ? 
      AND appointmentStatus = 'completed'
");
$stmt->execute([$nurseID]);
$stats['completed'] = (int)$stmt->fetchColumn();


// ==========================================
// 2. Fetch Current/Immediate Service
// ==========================================
$stmt = $pdo->prepare("
    SELECT a.appointmentID, a.startDateTime, a.endDateTime, a.locationAddress, 
           a.specialInstructions, a.appointmentStatus,
           s.serviceName, s.category,
           u.firstName, u.lastName, u.phoneNumber, u.gender,
           u.emergencyContactName, u.emergencyContactPhone
    FROM appointments a
    JOIN services s ON a.serviceID = s.serviceID
    JOIN users u ON a.userID = u.userID
    WHERE a.nurseID = ? 
      AND a.appointmentStatus IN ('confirmed', 'in_progress')
      AND DATE(a.startDateTime) >= CURDATE()
    ORDER BY a.startDateTime ASC 
    LIMIT 1
");
$stmt->execute([$nurseID]);
$currentService = $stmt->fetch(PDO::FETCH_ASSOC);

$currentID = $currentService ? $currentService['appointmentID'] : 0;

// ==========================================
// 3. Fetch Upcoming Schedules
// ==========================================
$stmt = $pdo->prepare("
    SELECT a.appointmentID, a.startDateTime, a.endDateTime, a.locationAddress, a.appointmentStatus,
           s.serviceName, s.category,
           u.firstName, u.lastName
    FROM appointments a
    JOIN services s ON a.serviceID = s.serviceID
    JOIN users u ON a.userID = u.userID
    WHERE a.nurseID = ? 
      AND a.appointmentStatus IN ('pending', 'confirmed')
      AND a.appointmentID != ?
    ORDER BY a.startDateTime ASC
");
$stmt->execute([$nurseID, $currentID]);
$upcomingSchedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare JSON for JS
$statsJson = json_encode($stats);
$currentJson = json_encode($currentService ?: null, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$upcomingJson = json_encode($upcomingSchedules, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

include __DIR__ . '/appointment.html';
?>