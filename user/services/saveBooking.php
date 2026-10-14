<?php
session_start();

require __DIR__ . '/../../config/db_connect.php';
require __DIR__ . '/../../auth/mailer.php';

if (empty($_SESSION['userID'])) {
    header('Location: ../../loginNsignup/login/login.html');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
    $_SESSION['booking_flash'] = ['title' => 'Booking failed', 'message' => implode(' ', $errors)];
    header('Location: services.php');
    exit;
}

$startDateTime = date('Y-m-d H:i:s', strtotime("$startDate $startTime"));
if (strtotime($startDateTime) < time()) {
    $_SESSION['booking_flash'] = ['title' => 'Booking failed', 'message' => 'That time has already passed.'];
    header('Location: services.php');
    exit;
}

$stmt = $pdo->prepare('SELECT serviceID, serviceName, price, price_unit, durationMin FROM services WHERE serviceID = ?');
$stmt->execute([$serviceID]);
$service = $stmt->fetch();
if (!$service) { header('Location: services.php'); exit; }

$totalNurses = (int)$pdo->query("SELECT COUNT(*) FROM nurses WHERE isVerified = 1")->fetchColumn();
if ($totalNurses <= 0) $totalNurses = 3;

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM appointments
     WHERE DATE(startDateTime) = ? AND TIME(startDateTime) = ?
       AND appointmentStatus IN ('pending','confirmed','in_progress')"
);
$stmt->execute([$startDate, $startTime]);
if ((int)$stmt->fetchColumn() >= $totalNurses) {
    $_SESSION['booking_flash'] = [
        'title'   => 'Slot no longer available',
        'message' => 'Sorry, that slot just filled up. Please pick another.',
    ];
    header('Location: services.php');
    exit;
}

$address = $location;
if ($area !== '')     $address .= ', ' . $area;
if ($postcode !== '') $address .= ', ' . $postcode;
$address .= ' Penang';

$durationMin = (int)($service['durationMin'] ?: 60);
$endDateTime = date('Y-m-d H:i:s', strtotime("$startDateTime +$durationMin minutes"));

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

$prettyDate = date('D, d M Y', strtotime($startDateTime));
$prettyTime = date('g:i A',     strtotime($startDateTime));

/* ---------- Send confirmation email ---------- */
$html = "
  <div style='font-family: Arial, sans-serif; max-width: 560px; margin: auto; color:#0f172a;'>
    <div style='background:#3C52C3; padding:24px; border-radius:12px 12px 0 0;'>
      <h1 style='color:white; margin:0; font-size:22px;'>
        <span style='color:#c7d2fe;'>Step</span><span style='color:#34d399;'>Care</span>
      </h1>
    </div>
    <div style='background:#fff; padding:24px; border:1px solid #e2e8f0; border-top:none;
                border-radius:0 0 12px 12px;'>
      <h2 style='color:#3C52C3; margin-top:0;'>Booking received</h2>
      <p>Hi " . htmlspecialchars($user['firstName']) . ",</p>
      <p>We've received your booking. A nurse will be assigned shortly — you'll get
         another email once that's confirmed.</p>

      <table style='width:100%; border-collapse:collapse; margin:20px 0;'>
        <tr><td style='padding:8px 0; color:#64748b; width:130px;'>Reference</td>
            <td style='padding:8px 0; font-weight:bold;'>#SC" . str_pad($appointmentID, 6, '0', STR_PAD_LEFT) . "</td></tr>
        <tr><td style='padding:8px 0; color:#64748b;'>Service</td>
            <td style='padding:8px 0;'>" . htmlspecialchars($service['serviceName']) . "</td></tr>
        <tr><td style='padding:8px 0; color:#64748b;'>Recipient</td>
            <td style='padding:8px 0;'>" . htmlspecialchars($patientName) . "</td></tr>
        <tr><td style='padding:8px 0; color:#64748b;'>Date</td>
            <td style='padding:8px 0;'>$prettyDate</td></tr>
        <tr><td style='padding:8px 0; color:#64748b;'>Time</td>
            <td style='padding:8px 0;'>$prettyTime (approx. {$durationMin} mins)</td></tr>
        <tr><td style='padding:8px 0; color:#64748b;'>Address</td>
            <td style='padding:8px 0;'>" . htmlspecialchars($address) . "</td></tr>
        <tr><td style='padding:8px 0; color:#64748b;'>Amount</td>
            <td style='padding:8px 0;'>RM" . number_format((float)$service['price'], 2) . " / " . htmlspecialchars($service['price_unit']) . "</td></tr>
        <tr><td style='padding:8px 0; color:#64748b;'>Status</td>
            <td style='padding:8px 0;'><span style='background:#fef3c7; color:#92400e;
                padding:3px 10px; border-radius:9999px; font-size:12px; font-weight:700;'>
                PENDING NURSE</span></td></tr>
      </table>

      <p style='color:#64748b; font-size:13px;'>
        You can track this appointment from your dashboard at any time.
      </p>
      <p style='color:#64748b; font-size:13px; margin-top:24px;'>
        Thank you for choosing StepCare.
      </p>
    </div>
  </div>
";

sendMail($user['email'], 'StepCare — Booking received', $html);

/* ---------- Redirect back to services with popup params ---------- */
$redirect = 'services.php?booked=1'
          . '&ref='     . urlencode('#SC' . str_pad($appointmentID, 6, '0', STR_PAD_LEFT))
          . '&date='    . urlencode($prettyDate)
          . '&time='    . urlencode($prettyTime)
          . '&service=' . urlencode($service['serviceName']);

header('Location: ' . $redirect);
exit;