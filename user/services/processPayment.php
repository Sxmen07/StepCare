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

$userID = (int)$_SESSION['userID'];

if (empty($_SESSION['pending_booking'])) {
    if ($isAjax) { echo json_encode(['success' => false, 'message' => 'No pending booking found. Please fill the form again.']); exit; }
    header('Location: services.php');
    exit;
}

if (!isset($_FILES['paymentProof']) || $_FILES['paymentProof']['error'] !== UPLOAD_ERR_OK) {
    if ($isAjax) { echo json_encode(['success' => false, 'message' => 'Please upload a valid payment proof image.']); exit; }
    header('Location: services.php');
    exit;
}

$file = $_FILES['paymentProof'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png'];

if (!in_array($ext, $allowed)) {
    if ($isAjax) { echo json_encode(['success' => false, 'message' => 'Only JPG and PNG images are allowed.']); exit; }
    header('Location: services.php');
    exit;
}

if ($file['size'] > 5 * 1024 * 1024) {
    if ($isAjax) { echo json_encode(['success' => false, 'message' => 'Maximum file size is 5MB.']); exit; }
    header('Location: services.php');
    exit;
}

$booking = $_SESSION['pending_booking'];

// 再次检查库存（防止用户停留在支付页太久被别人抢掉）
$totalNurses = (int)$pdo->query("SELECT COUNT(*) FROM nurses WHERE isVerified = 1")->fetchColumn();
if ($totalNurses <= 0) $totalNurses = 3;

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM appointments
     WHERE DATE(startDateTime) = ? AND TIME(startDateTime) = ?
       AND appointmentStatus IN ('pending','confirmed','in_progress')"
);
$stmt->execute([$booking['startDate'], $booking['startTime']]);
if ((int)$stmt->fetchColumn() >= $totalNurses) {
    unset($_SESSION['pending_booking']);
    if ($isAjax) { echo json_encode(['success' => false, 'message' => 'Sorry, that slot was just taken. Please book again.']); exit; }
    header('Location: services.php');
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. 生成真实的 appointment
    $stmt = $pdo->prepare(
        'INSERT INTO appointments
            (serviceID, userID, patientName, startDateTime, endDateTime,
             locationAddress, specialInstructions, appointmentStatus)
         VALUES (?, ?, ?, ?, ?, ?, ?, "pending")'
    );
    $stmt->execute([
        $booking['serviceID'], $userID, $booking['patientName'],
        $booking['startDateTime'], $booking['endDateTime'], $booking['address'],
        $booking['specialInstructions']
    ]);
    $appointmentID = $pdo->lastInsertId();

    // 2. 生成 payments 记录
    $stmt = $pdo->prepare("
        INSERT INTO payments (appointmentID, totalAmount, paymentStatus)
        VALUES (?, ?, 'pending_verification')
    ");
    $stmt->execute([$appointmentID, $booking['price']]);
    $paymentID = $pdo->lastInsertId();

    // 3. 保存图片
    $uploadDir = __DIR__ . '/../../uploads/payment_proofs/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $fileName = 'pay_' . $appointmentID . '_' . time() . '.' . $ext;
    $uploadPath = $uploadDir . $fileName;
    $dbPath = '../../uploads/payment_proofs/' . $fileName;

    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        // 4. 更新图片路径
        $stmt = $pdo->prepare("
            UPDATE payments 
            SET paymentProofURL = ?, paymentMethod = 'Bank Transfer' 
            WHERE paymentID = ?
        ");
        $stmt->execute([$dbPath, $paymentID]);

        // 5. 记录 Timeline
        try {
            $stmt = $pdo->prepare("
                INSERT INTO trackServiceStatus (appointmentID, serviceID, userID, status, message)
                VALUES (?, ?, ?, 'pending', 'Payment proof uploaded. Awaiting verification.')
            ");
            $stmt->execute([$appointmentID, $booking['serviceID'], $userID]);
        } catch (Exception $e) {}

        $pdo->commit();
        
        // 清除暂存数据
        unset($_SESSION['pending_booking']);

        if ($isAjax) {
            echo json_encode(['success' => true, 'appointmentID' => $appointmentID]);
            exit;
        }
        header('Location: services.php');
        exit;
    } else {
        throw new Exception('Could not save the uploaded file.');
    }
} catch (Exception $e) {
    $pdo->rollBack();
    error_log($e->getMessage());
    if ($isAjax) { echo json_encode(['success' => false, 'message' => 'Database or file error.']); exit; }
    header('Location: services.php');
    exit;
}