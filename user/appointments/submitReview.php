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
$rating        = (int)($_POST['rating'] ?? 0);
$comment       = trim($_POST['comment'] ?? '');

if ($appointmentID <= 0 || $rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}
if (mb_strlen($comment) > 1000) {
    $comment = mb_substr($comment, 0, 1000);
}

/* 1. Verify appointment belongs to user and is completed */
$stmt = $pdo->prepare("
    SELECT appointmentID, serviceID, nurseID, appointmentStatus
    FROM appointments
    WHERE appointmentID = ? AND userID = ?
");
$stmt->execute([$appointmentID, $userID]);
$appt = $stmt->fetch();

if (!$appt) {
    echo json_encode(['success' => false, 'message' => 'Appointment not found']);
    exit;
}
if ($appt['appointmentStatus'] !== 'completed') {
    echo json_encode(['success' => false, 'message' => 'You can only review completed appointments']);
    exit;
}

/* 2. Prevent duplicate reviews */
$dup = $pdo->prepare("SELECT 1 FROM serviceComments WHERE appointmentID = ? AND userID = ?");
$dup->execute([$appointmentID, $userID]);
if ($dup->fetch()) {
    echo json_encode(['success' => false, 'message' => 'You have already reviewed this appointment']);
    exit;
}

try {
    $pdo->beginTransaction();

    /* 3a. Insert service comment */
    $stmt = $pdo->prepare("
        INSERT INTO serviceComments (serviceID, userID, appointmentID, rating, comment)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $appt['serviceID'],
        $userID,
        $appointmentID,
        $rating,
        $comment !== '' ? $comment : null,
    ]);

    /* 3b. Insert nurse review + recompute nurse average */
    if (!empty($appt['nurseID'])) {
        $stmt = $pdo->prepare("
            INSERT INTO nurseReviews (nurseID, appointmentID, userID, rating, comment)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $appt['nurseID'],
            $appointmentID,
            $userID,
            $rating,
            $comment !== '' ? $comment : null,
        ]);

        $stmt = $pdo->prepare("
            UPDATE nurses
            SET averageRating = (
                SELECT COALESCE(AVG(rating), 0)
                FROM nurseReviews
                WHERE nurseID = ? AND isHidden = 0
            )
            WHERE nurseID = ?
        ");
        $stmt->execute([$appt['nurseID'], $appt['nurseID']]);
    }

    /* 3c. Recompute service average + review count */
    $stmt = $pdo->prepare("
        UPDATE services
        SET average_rating = (
                SELECT COALESCE(AVG(rating), 0)
                FROM serviceComments
                WHERE serviceID = ? AND isHidden = 0
            ),
            review_count = (
                SELECT COUNT(*)
                FROM serviceComments
                WHERE serviceID = ? AND isHidden = 0
            )
        WHERE serviceID = ?
    ");
    $stmt->execute([$appt['serviceID'], $appt['serviceID'], $appt['serviceID']]);

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}