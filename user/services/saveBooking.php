<?php
session_start();
require __DIR__ . '/../../config/db_connect.php';

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if (empty($_SESSION['userID'])) {
    if ($isAjax) { echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']); exit; }
    header('Location: ../../loginNsignup/login/login.html');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) { echo json_encode(['success' => false, 'message' => 'Invalid request.']); exit; }
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

$errors = [];
if ($serviceID <= 0)     $errors[] = 'Invalid service.';
if ($startDate === '')   $errors[] = 'Please choose a date.';
if ($startTime === '')   $errors[] = 'Please choose a valid time slot.';
if ($patientName === '') $errors[] = 'Recipient name is required.';
if ($location === '')    $errors[] = 'Street / building is required.';
if ($area === '')        $errors[] = 'Area is required.';

if ($errors) {
    if ($isAjax) { echo json_encode(['success' => false, 'message' => implode(' ', $errors)]); exit; }
    $_SESSION['booking_flash'] = ['title' => 'Booking failed', 'message' => implode(' ', $errors)];
    header('Location: services.php');
    exit;
}

// Normalize startTime format to ensure matching with DB (HH:MM:SS)
$timeDt = DateTime::createFromFormat('H:i', $startTime) ?: DateTime::createFromFormat('H:i:s', $startTime);
if (!$timeDt) {
    if ($isAjax) { echo json_encode(['success' => false, 'message' => 'Invalid time slot format.']); exit; }
    $_SESSION['booking_flash'] = ['title' => 'Booking failed', 'message' => 'Invalid time slot format.'];
    header('Location: services.php');
    exit;
}
$normalizedStartTime = $timeDt->format('H:i:s');

// Validate if this time slot is actually configured and available for this service in the database
$slotCheckStmt = $pdo->prepare("
    SELECT slotID, maxCapacity FROM serviceTimeSlots 
    WHERE serviceID = ? AND TIME(STR_TO_DATE(timeSlotLabel, '%h:%i %p')) = ? AND isAvailable = 1
");
// Fallback check if timeSlotLabel is stored differently (e.g. 24h format vs 12h format)
$slotCheckStmt->execute([$serviceID, $normalizedStartTime]);
$slotRow = $slotCheckStmt->fetch(PDO::FETCH_ASSOC);

if (!$slotRow) {
    // Try alternative query matching string labels directly if needed
    $slotCheckStmtAlt = $pdo->prepare("
        SELECT slotID, maxCapacity FROM serviceTimeSlots 
        WHERE serviceID = ? AND isAvailable = 1
    ");
    $slotCheckStmtAlt->execute([$serviceID]);
    $availableSlots = $slotCheckStmtAlt->fetchAll(PDO::FETCH_ASSOC);
    
    $matched = false;
    $maxCapacity = 5; // default fallback
    foreach ($availableSlots as $as) {
        $dbDt = DateTime::createFromFormat('g:i A', $as['timeSlotLabel']) ?: DateTime::createFromFormat('H:i:s', $as['timeSlotLabel']);
        if ($dbDt && $dbDt->format('H:i:s') === $normalizedStartTime) {
            $matched = true;
            $maxCapacity = (int)($as['maxCapacity'] ?? 5);
            break;
        }
    }

    if (!$matched) {
        if ($isAjax) { echo json_encode(['success' => false, 'message' => 'Please choose a valid time slot.']); exit; }
        $_SESSION['booking_flash'] = ['title' => 'Booking failed', 'message' => 'Please choose a valid time slot.'];
        header('Location: services.php');
        exit;
    }
} else {
    $maxCapacity = (int)($slotRow['maxCapacity'] ?? 5);
}

$startDateTime = date('Y-m-d H:i:s', strtotime("$startDate $normalizedStartTime"));
if (strtotime($startDateTime) < time()) {
    if ($isAjax) { echo json_encode(['success' => false, 'message' => 'That time has already passed.']); exit; }
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

// Check slot capacity using the service's configured maxCapacity or total verified nurses
$totalNurses = (int)$pdo->query("SELECT COUNT(*) FROM nurses WHERE isVerified = 1")->fetchColumn();
if ($totalNurses <= 0) $totalNurses = 3;
$slotLimit = min($maxCapacity, $totalNurses);

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM appointments
     WHERE serviceID = ? AND DATE(startDateTime) = ? AND TIME(startDateTime) = ?
       AND appointmentStatus IN ('pending','confirmed','in_progress')"
);
$stmt->execute([$serviceID, $startDate, $normalizedStartTime]);
if ((int)$stmt->fetchColumn() >= $slotLimit) {
    if ($isAjax) { echo json_encode(['success' => false, 'message' => 'Sorry, that slot just filled up. Please pick another.']); exit; }
    $_SESSION['booking_flash'] = ['title' => 'Slot no longer available', 'message' => 'Sorry, that slot just filled up. Please pick another.'];
    header('Location: services.php');
    exit;
}

$address = $location;
if ($area !== '')     $address .= ', ' . $area;
if ($postcode !== '') $address .= ', ' . $postcode;
$address .= ' Penang';

$durationMin = (int)($service['durationMin'] ?: 60);
$endDateTime = date('Y-m-d H:i:s', strtotime("$startDateTime +$durationMin minutes"));

$prettyDate = date('D, d M Y', strtotime($startDateTime));
$prettyTime = date('g:i A',     strtotime($startDateTime));

$_SESSION['pending_booking'] = [
    'serviceID' => $serviceID,
    'patientName' => $patientName,
    'startDate' => $startDate,
    'startTime' => $normalizedStartTime,
    'startDateTime' => $startDateTime,
    'endDateTime' => $endDateTime,
    'address' => $address,
    'specialInstructions' => $notes !== '' ? $notes : null,
    'price' => $service['price'],
    'serviceName' => $service['serviceName'],
    'prettyDate' => $prettyDate,
    'prettyTime' => $prettyTime
];

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'prettyDate' => $prettyDate,
        'prettyTime' => $prettyTime,
        'serviceName' => $service['serviceName'],
        'price' => number_format((float)$service['price'], 2)
    ]);
    exit;
}

header('Location: services.php');
exit;