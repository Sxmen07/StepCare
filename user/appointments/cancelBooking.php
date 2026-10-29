<?php
session_start();
header('Content-Type: application/json');
require __DIR__ . '/../../config/db_connect.php';

if (empty($_SESSION['userID'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$userID        = (int)$_SESSION['userID'];
$appointmentID = (int)($_POST['appointmentID'] ?? 0);

if ($appointmentID <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid appointment ID']);
    exit;
}

/* 1. Fetch */
$stmt = $pdo->prepare("
    SELECT startDateTime, appointmentStatus
    FROM appointments
    WHERE appointmentID = ? AND userID = ?
");
$stmt->execute([$appointmentID, $userID]);
$appt = $stmt->fetch();

if (!$appt) {
    echo json_encode(['success' => false, 'message' => 'Appointment not found']);
    exit;
}

/* 2. Validate status */
if (in_array($appt['appointmentStatus'], ['cancelled', 'completed', 'declined'])) {
    echo json_encode(['success' => false, 'message' => 'This appointment cannot be cancelled.']);
    exit;
}

/* 3. 4-hour policy */
$hoursDiff = (strtotime($appt['startDateTime']) - time()) / 3600;
if ($hoursDiff < 4) {
    echo json_encode(['success' => false, 'message' => 'Cancellations must be made at least 4 hours in advance. Please contact support.']);
    exit;
}

/* 4. Process */
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        UPDATE appointments
        SET appointmentStatus = 'cancelled',
            cancellationReason = 'Cancelled by user'
        WHERE appointmentID = ?
    ");
    $stmt->execute([$appointmentID]);

    /* 4a. Timeline entry */
    $stmt = $pdo->prepare("
        INSERT INTO appointment_timeline
            (appointmentID, status, title, message, createdBy, createdByID)
        VALUES (?, 'cancelled', 'Appointment cancelled',
                'You cancelled this appointment. No fee has been charged.',
                'user', ?)
    ");
    $stmt->execute([$appointmentID, $userID]);

    /* 4b. Notification */
    $stmt = $pdo->prepare("
        INSERT INTO notifications
            (recipientType, recipientID, title, message, type, linkURL)
        VALUES ('user', ?, 'Booking Cancelled',
                'Your appointment has been successfully cancelled.',
                'appointment', '/StepCare/user/appointments/appointments.php')
    ");
    $stmt->execute([$userID]);

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}