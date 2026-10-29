<?php
session_start();

require __DIR__ . '/../../config/db_connect.php';

// 判断是否为 AJAX 请求
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if (empty($_SESSION['userID'])) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
        exit;
    }
    header('Location: ../../loginNsignup/login/login.html');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit;
    }
    header('Location: services.php');
    exit;
}

$userID      = (int)$_SESSION['userID'];
$serviceID   = (int)($_POST['serviceID'] ?? 0);
$startDate   = trim($_POST['startDate'] ?? '');
$startTime   = trim($_POST['startTime'] ?? '');
$patientName = trim($_POST['patientName'] ?? '');
$location    = trim($_POST['location'] ?? '');
$area        = trim($_POST['area'] ?? '');
$postcode    = trim($_POST['postcode'] ?? '');
$notes       = trim($_POST['specialInstructions'] ?? '');

$allowedSlots = ['08:00:00','10:00:00','11:00:00','14:00:00','16:00:00'];

$errors = [];
if ($serviceID <= 0)                             $errors[] = 'Invalid service.';
if ($startDate === '')                           $errors[] = 'Please choose a date.';
if (!in_array($startTime, $allowedSlots, true))  $errors[] = 'Please choose a valid time slot.';
if ($patientName === '')                         $errors[] = 'Recipient name is required.';
if ($location === '')                            $errors[] = 'Street / building is required.';
if ($area === '')                                $errors[] = 'Area is required.';

if ($errors) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
        exit;
    }
    $_SESSION['booking_flash'] = ['title' => 'Booking failed', 'message' => implode(' ', $errors)];
    header('Location: services.php');
    exit;
}

$startDateTime = date('Y-m-d H:i:s', strtotime("$startDate $startTime"));
if (strtotime($startDateTime) < time()) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'That time has already passed.']);
        exit;
    }
    $_SESSION['booking_flash'] = ['title' => 'Booking failed', 'message' => 'That time has already passed.'];
    header('Location: services.php');
    exit;
}

$stmt = $pdo->prepare('SELECT serviceID, serviceName, price, price_unit, durationMin FROM services WHERE serviceID = ?');
$stmt->execute([$serviceID]);
$service = $stmt->fetch();
if (!$service) { 
    if ($isAjax) { echo json_encode(['success' => false, 'message' => 'Service not found.']); exit; }
    header('Location: services.php'); exit; 
}

$totalNurses = (int)$pdo->query("SELECT COUNT(*) FROM nurses WHERE isVerified = 1")->fetchColumn();
if ($totalNurses <= 0) $totalNurses = 3;

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM appointments
     WHERE DATE(startDateTime) = ? AND TIME(startDateTime) = ?
       AND appointmentStatus IN ('pending','confirmed','in_progress')"
);
$stmt->execute([$startDate, $startTime]);
if ((int)$stmt->fetchColumn() >= $totalNurses) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Sorry, that slot just filled up. Please pick another.']);
        exit;
    }
    $_SESSION['booking_flash'] = ['title' => 'Slot no longer available', 'message' => 'Sorry, that slot just filled up. Please pick another.'];
    header('Location: services.php');
    exit;
}

// Cleanup abandoned unpaid bookings older than 15 minutes
$pdo->prepare("
    UPDATE appointments a
    JOIN payments p ON a.appointmentID = p.appointmentID
    SET a.appointmentStatus = 'cancelled', p.paymentStatus = 'failed'
    WHERE a.userID = ? AND a.appointmentStatus = 'pending' AND p.paymentStatus = 'pending'
      AND a.createdAt < NOW() - INTERVAL 15 MINUTE
")->execute([$userID]);

$address = $location;
if ($area !== '')     $address .= ', ' . $area;
if ($postcode !== '') $address .= ', ' . $postcode;
$address .= ' Penang';

$durationMin = (int)($service['durationMin'] ?: 60);
$endDateTime = date('Y-m-d H:i:s', strtotime("$startDateTime +$durationMin minutes"));

$prettyDate = date('D, d M Y', strtotime($startDateTime));
$prettyTime = date('g:i A',     strtotime($startDateTime));

$stmt = $pdo->prepare('SELECT firstName, email FROM users WHERE userID = ?');
$stmt->execute([$userID]);
$user = $stmt->fetch();

/* ---------- Insert as PENDING ---------- */
$stmt = $pdo->prepare(
    'INSERT INTO appointments
        (serviceID, userID, patientName, startDateTime, endDateTime,
         locationAddress, specialInstructions, appointmentStatus)
     VALUES (?, ?, ?, ?, ?, ?, ?, "pending")'
);
$stmt->execute([
    $serviceID, $userID, $patientName,
    $startDateTime, $endDateTime, $address,
    $notes !== '' ? $notes : null,
]);
$appointmentID = $pdo->lastInsertId();

/* ---------- Create PENDING payment record ---------- */
$stmt = $pdo->prepare("
    INSERT INTO payments (appointmentID, totalAmount, paymentStatus)
    VALUES (?, ?, 'pending')
");
$stmt->execute([$appointmentID, $service['price']]);

/* ---------- Return Response ---------- */
if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'appointmentID' => $appointmentID,
        'prettyDate' => $prettyDate,
        'prettyTime' => $prettyTime,
        'serviceName' => $service['serviceName'],
        'price' => number_format((float)$service['price'], 2)
    ]);
    exit;
}

header('Location: payment.php?id=' . $appointmentID);
exit;