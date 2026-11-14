<?php
/* user/services/processPayment.php
   Attaches payment proof to an existing pending appointment.
   ALWAYS returns JSON. */

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function jsonOut(array $payload, int $status = 200): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
    }
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    echo ($json === false) ? '{"success":false,"message":"Server encoding error."}' : $json;
    exit;
}

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
    }
});

$uploadPath = null;

try {
    require __DIR__ . '/../../config/db_connect.php';

    if (empty($_SESSION['userID'])) {
        jsonOut(['success' => false, 'message' => 'Session expired. Please log in again.'], 401);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        jsonOut(['success' => false, 'message' => 'Invalid request method.'], 405);
    }

    $userID        = (int)$_SESSION['userID'];
    $appointmentID = (int)($_POST['appointmentID'] ?? 0);

    if ($appointmentID <= 0) {
        jsonOut(['success' => false, 'message' => 'Invalid appointment.'], 422);
    }

    /* -------- validate the upload -------- */
    if (!isset($_FILES['paymentProof']) || $_FILES['paymentProof']['error'] !== UPLOAD_ERR_OK) {
        jsonOut(['success' => false, 'message' => 'Please upload a valid payment proof image.'], 422);
    }

    $file = $_FILES['paymentProof'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
        jsonOut(['success' => false, 'message' => 'Only JPG and PNG images are allowed.'], 422);
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        jsonOut(['success' => false, 'message' => 'Maximum file size is 5MB.'], 422);
    }

    $pdo->beginTransaction();

    /* -------- verify ownership + state, and lock the rows -------- */
    $stmt = $pdo->prepare(
        "SELECT a.appointmentID, a.serviceID, a.appointmentStatus,
                p.paymentID, p.paymentStatus
         FROM appointments a
         JOIN payments p ON p.appointmentID = a.appointmentID
         WHERE a.appointmentID = ? AND a.userID = ?
         FOR UPDATE"
    );
    $stmt->execute([$appointmentID, $userID]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $pdo->rollBack();
        jsonOut(['success' => false, 'message' => 'Appointment not found.'], 404);
    }
    if ($row['appointmentStatus'] !== 'pending') {
        $pdo->rollBack();
        jsonOut(['success' => false, 'message' => 'This booking can no longer be paid.'], 409);
    }
    if (!in_array($row['paymentStatus'], ['pending', 'failed'], true)) {
        $pdo->rollBack();
        jsonOut(['success' => false, 'message' => 'Payment proof has already been submitted.'], 409);
    }

    $paymentID = (int)$row['paymentID'];
    $serviceID = (int)$row['serviceID'];

    /* -------- store the file -------- */
    $uploadDir = __DIR__ . '/../../uploads/payment_proofs/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Could not create the upload directory.');
    }

    $fileName   = 'pay_' . $appointmentID . '_' . time() . '.' . $ext;
    $uploadPath = $uploadDir . $fileName;
    $dbPath     = '../../uploads/payment_proofs/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new RuntimeException('Could not save the uploaded file.');
    }

    /* -------- update payment -------- */
    $pdo->prepare(
        "UPDATE payments
         SET paymentProofURL = ?, paymentMethod = 'Bank Transfer', paymentStatus = 'pending_verification'
         WHERE paymentID = ?"
    )->execute([$dbPath, $paymentID]);

    /* -------- timeline entry (non critical) -------- */
    try {
        $pdo->prepare(
            "INSERT INTO trackServiceStatus (appointmentID, serviceID, userID, status, message)
             VALUES (?, ?, ?, 'pending', 'Payment proof uploaded. Awaiting verification.')"
        )->execute([$appointmentID, $serviceID, $userID]);
    } catch (Throwable $ignore) {}

    $pdo->commit();

    jsonOut([
        'success'       => true,
        'appointmentID' => $appointmentID,
        'reference'     => '#SC' . str_pad((string)$appointmentID, 6, '0', STR_PAD_LEFT)
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO) {
        try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (Throwable $ignore) {}
    }
    if ($uploadPath && is_file($uploadPath)) { @unlink($uploadPath); }
    error_log('[processPayment] ' . $e->getMessage());
    jsonOut(['success' => false, 'message' => 'Could not upload your payment proof. Please try again.'], 500);
}