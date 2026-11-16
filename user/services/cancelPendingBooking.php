<?php
/* user/services/cancelPendingBooking.php
   Cancels the pending appointment created by saveBooking.php so the slot is freed. */

session_start();

header('Content-Type: application/json; charset=utf-8');

function jsonOut(array $payload, int $status = 200): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

try {
    require __DIR__ . '/../../config/db_connect.php';

    if (empty($_SESSION['userID'])) {
        jsonOut(['success' => false, 'message' => 'Session expired.'], 401);
    }

    $userID        = (int)$_SESSION['userID'];
    $appointmentID = (int)($_POST['appointmentID'] ?? $_GET['appointmentID'] ?? 0);

    unset($_SESSION['pending_booking'], $_SESSION['active_appointment_id']);

    if ($appointmentID <= 0) {
        jsonOut(['success' => true]);        // nothing to cancel
    }

    $pdo->beginTransaction();

    $upd = $pdo->prepare(
        "UPDATE appointments
         SET appointmentStatus = 'cancelled'
         WHERE appointmentID = ? AND userID = ? AND appointmentStatus = 'pending'"
    );
    $upd->execute([$appointmentID, $userID]);

    if ($upd->rowCount() > 0) {
        $pdo->prepare(
            "UPDATE payments SET paymentStatus = 'failed'
             WHERE appointmentID = ? AND paymentStatus IN ('pending','pending_verification')"
        )->execute([$appointmentID]);
    }

    $pdo->commit();

    jsonOut(['success' => true]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO) {
        try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (Throwable $ignore) {}
    }
    error_log('[cancelPendingBooking] ' . $e->getMessage());
    jsonOut(['success' => false, 'message' => 'Could not cancel the booking.'], 500);
}