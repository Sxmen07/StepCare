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

if ($appointmentID <= 0 || empty($_FILES['paymentProof'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request or file missing']);
    exit;
}

// Verify appointment belongs to user and check start date (must be at least 2 days away)
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

$hoursUntilStart = (strtotime($appt['startDateTime']) - time()) / 3600;
if ($hoursUntilStart < 48) {
    echo json_encode(['success' => false, 'message' => 'Re-uploading payment proof must be done at least 2 days (48 hours) before the appointment start time.']);
    exit;
}

$file = $_FILES['paymentProof'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File upload error']);
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png', 'pdf', 'webp'];
if (!in_array($ext, $allowed, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file format. Allowed: JPG, PNG, WEBP, PDF']);
    exit;
}

$uploadDir = __DIR__ . '/../../uploads/payments/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$fileName = 'PAY_' . $appointmentID . '_' . time() . '.' . $ext;
$targetPath = $uploadDir . $fileName;
$dbPath = '../../uploads/payments/' . $fileName;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Update payment record
    $stmt = $pdo->prepare("
        UPDATE payments
        SET paymentProofURL = ?,
            paymentStatus = 'pending_verification',
            rejectionReason = NULL
        WHERE appointmentID = ?
    ");
    $stmt->execute([$dbPath, $appointmentID]);

    // Record timeline event
    $stmt = $pdo->prepare("
        INSERT INTO appointment_timeline (appointmentID, status, title, message, createdBy, createdByID)
        VALUES (?, 'pending_verification', 'Payment proof re-uploaded', 'Customer re-uploaded new payment proof for verification.', 'user', ?)
    ");
    $stmt->execute([$appointmentID, $userID]);

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}