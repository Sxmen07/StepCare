<?php
session_start();
require __DIR__ . '/../../config/db_connect.php';

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if (empty($_SESSION['userID'])) {
    if ($isAjax) { echo json_encode(['success' => false, 'message' => 'Session expired.']); exit; }
    header('Location: ../../loginNsignup/login/login.html');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) { echo json_encode(['success' => false, 'message' => 'Invalid request.']); exit; }
    header('Location: services.php');
    exit;
}

$userID        = (int)$_SESSION['userID'];
$appointmentID = (int)($_POST['appointmentID'] ?? 0);

if ($appointmentID <= 0 || !isset($_FILES['paymentProof']) || $_FILES['paymentProof']['error'] !== UPLOAD_ERR_OK) {
    if ($isAjax) { echo json_encode(['success' => false, 'message' => 'Please upload a valid payment proof image.']); exit; }
    $_SESSION['booking_flash'] = ['title' => 'Upload Failed', 'message' => 'Please upload a valid payment proof image.'];
    header("Location: payment.php?id=$appointmentID");
    exit;
}

$stmt = $pdo->prepare("
    SELECT a.appointmentID, a.appointmentStatus, s.serviceName, 
           DATE_FORMAT(a.startDateTime, '%a, %d %b %Y') as prettyDate, 
           DATE_FORMAT(a.startDateTime, '%g:%i %p') as prettyTime 
    FROM appointments a 
    JOIN services s ON s.serviceID = a.serviceID
    JOIN payments p ON p.appointmentID = a.appointmentID
    WHERE a.appointmentID = ? AND a.userID = ? AND p.paymentStatus = 'pending'
");
$stmt->execute([$appointmentID, $userID]);
$appt = $stmt->fetch();

if (!$appt) {
    if ($isAjax) { echo json_encode(['success' => false, 'message' => 'Appointment not found or already paid.']); exit; }
    header('Location: services.php');
    exit;
}

$file = $_FILES['paymentProof'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png'];

if (!in_array($ext, $allowed)) {
    if ($isAjax) { echo json_encode(['success' => false, 'message' => 'Only JPG and PNG images are allowed.']); exit; }
    $_SESSION['booking_flash'] = ['title' => 'Invalid File', 'message' => 'Only JPG and PNG images are allowed.'];
    header("Location: payment.php?id=$appointmentID");
    exit;
}

if ($file['size'] > 5 * 1024 * 1024) {
    if ($isAjax) { echo json_encode(['success' => false, 'message' => 'Maximum file size is 5MB.']); exit; }
    $_SESSION['booking_flash'] = ['title' => 'File Too Large', 'message' => 'Maximum file size is 5MB.'];
    header("Location: payment.php?id=$appointmentID");
    exit;
}

$uploadDir = __DIR__ . '/../../uploads/payment_proofs/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$fileName = 'pay_' . $appointmentID . '_' . time() . '.' . $ext;
$uploadPath = $uploadDir . $fileName;
$dbPath = '../../uploads/payment_proofs/' . $fileName;

if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
    $stmt = $pdo->prepare("
        UPDATE payments 
        SET paymentProofURL = ?, paymentStatus = 'pending_verification', paymentMethod = 'Bank Transfer' 
        WHERE appointmentID = ?
    ");
    $stmt->execute([$dbPath, $appointmentID]);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO trackServiceStatus (appointmentID, serviceID, userID, status, message)
            SELECT appointmentID, serviceID, userID, 'pending', 'Payment proof uploaded. Awaiting verification.' 
            FROM appointments WHERE appointmentID = ?
        ");
        $stmt->execute([$appointmentID]);
    } catch (Exception $e) {}

    if ($isAjax) {
        echo json_encode(['success' => true]);
        exit;
    }

    $redirect = 'services.php?booked=1'
              . '&ref='     . urlencode('#SC' . str_pad($appointmentID, 6, '0', STR_PAD_LEFT))
              . '&date='    . urlencode($appt['prettyDate'])
              . '&time='    . urlencode($appt['prettyTime'])
              . '&service=' . urlencode($appt['serviceName']);
    header('Location: ' . $redirect);
    exit;
} else {
    if ($isAjax) { echo json_encode(['success' => false, 'message' => 'Could not save the uploaded file.']); exit; }
    $_SESSION['booking_flash'] = ['title' => 'Server Error', 'message' => 'Could not save the uploaded file.'];
    header("Location: payment.php?id=$appointmentID");
    exit;
}