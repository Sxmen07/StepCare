<?php
// nurse/appointmentDetails/updateNotes.php
session_start();
header('Content-Type: application/json');
require __DIR__ . '/../../../config/db_connect.php';

if (empty($_SESSION['nurseID'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$nurseID = (int)$_SESSION['nurseID'];
$appointmentID = (int)($_POST['appointmentID'] ?? 0);
$notes = trim($_POST['notes'] ?? '');

if ($appointmentID <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit;
}

try {
    $stmt = $pdo->prepare("UPDATE appointments SET nurseNotes = ? WHERE appointmentID = ? AND nurseID = ?");
    $stmt->execute([$notes, $appointmentID, $nurseID]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>