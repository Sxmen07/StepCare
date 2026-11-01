<?php
// nurse/appointmentDetails/updateActivity.php
session_start();
header('Content-Type: application/json');
require __DIR__ . '/../../../config/db_connect.php';

if (empty($_SESSION['nurseID'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$nurseID = (int)$_SESSION['nurseID'];
$payload = json_decode(file_get_contents('php://input'), true);

$appointmentID = (int)($payload['appointmentID'] ?? 0);
$status        = trim($payload['status'] ?? '');
$title         = trim($payload['title'] ?? '');
$message       = trim($payload['message'] ?? '');

if ($appointmentID <= 0 || $status === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid payload']); exit;
}

try {
    $pdo->beginTransaction();

    // 1. Insert into timeline (keeps the specific 'on_the_way', 'arrived' etc.)
    $stmt = $pdo->prepare("
        INSERT INTO appointment_timeline (appointmentID, status, title, message, createdBy, createdByID)
        VALUES (?, ?, ?, ?, 'nurse', ?)
    ");
    $stmt->execute([$appointmentID, $status, $title, $message, $nurseID]);

    // 2. Update main appointment status
    $mainStatus = 'in_progress';
    if ($status === 'completed') {
        $mainStatus = 'completed';
    } elseif ($status === 'confirmed' || $status === 'pending') {
        $mainStatus = $status;
    }

    $stmt = $pdo->prepare("UPDATE appointments SET appointmentStatus = ? WHERE appointmentID = ? AND nurseID = ?");
    $stmt->execute([$mainStatus, $appointmentID, $nurseID]);

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>