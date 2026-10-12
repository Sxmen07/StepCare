<?php
session_start();

require __DIR__ . '/../../config/db_connect.php';       // gives $pdo + $conn
require __DIR__ . '/../../auth/mailer.php';              // gives sendMail()

/* ---------- Must be logged in ---------- */
if (empty($_SESSION['userID'])) {
    header('Location: ../../loginNsignup/login/login.html');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: services.php');
    exit;
}

$userID    = (int)$_SESSION['userID'];
$serviceID = (int)($_POST['serviceID'] ?? 0);
$startDate = trim($_POST['startDate'] ?? '');
$startTime = trim($_POST['startTime'] ?? '');
$address   = trim($_POST['locationAddress'] ?? '');
$notes     = trim($_POST['specialInstructions'] ?? '');

/* ---------- Validate ---------- */
if ($serviceID <= 0 || $startDate === '' || $startTime === '' || $address === '') {
    header("Location: serviceDetail.php?id=$serviceID&error=missing");
    exit;
}

if (strtotime("$startDate $startTime") < time()) {
    header("Location: serviceDetail.php?id=$serviceID&error=past");
    exit;
}

/* ---------- Fetch service + duration ---------- */
$stmt = $pdo->prepare(
    'SELECT serviceID, serviceName, price, price_unit, durationMin, durationMax
     FROM services WHERE serviceID = ?'
);
$stmt->execute([$serviceID]);
$service = $stmt->fetch();

if (!$service) {
    header('Location: services.php');
    exit;
}

/* ---------- Fetch user for email ---------- */
$stmt = $pdo->prepare(
    'SELECT firstName, lastName, email FROM users WHERE userID = ?'
);
$stmt->execute([$userID]);
$user = $stmt->fetch();

/* ---------- Compute start / end datetime ---------- */
$startDateTime = date('Y-m-d H:i:s', strtotime("$startDate $startTime"));
$durationMin   = (int)($service['durationMin'] ?: 60);   // default 60 mins
$endDateTime   = date('Y-m-d H:i:s', strtotime("$startDateTime +$durationMin minutes"));

/* ---------- Insert appointment ---------- */
$stmt = $pdo->prepare(
    'INSERT INTO appointments
        (serviceID, userID, startDateTime, endDateTime,
         locationAddress, specialInstructions, appointmentStatus)
     VALUES (?, ?, ?, ?, ?, ?, "confirmed")'
);
$stmt->execute([
    $serviceID, $userID, $startDateTime, $endDateTime,
    $address, $notes !== '' ? $notes : null
]);

$appointmentID = $pdo->lastInsertId();

/* ---------- Build confirmation email ---------- */
$prettyDate = date('D, d M Y', strtotime($startDateTime));
$prettyTime = date('g:i A',     strtotime($startDateTime));

$html = "
  <div style='font-family: Arial, sans-serif; max-width: 560px; margin: auto; color:#0f172a;'>
    <div style='background:#3C52C3; padding:24px; border-radius:12px 12px 0 0;'>
      <h1 style='color:white; margin:0; font-size:22px;'>
        <span style='color:#c7d2fe;'>Step</span><span style='color:#34d399;'>Care</span>
      </h1>
    </div>
    <div style='background:#fff; padding:24px; border:1px solid #e2e8f0; border-top:none;
                border-radius:0 0 12px 12px;'>
      <h2 style='color:#3C52C3; margin-top:0;'>Booking confirmed ✅</h2>
      <p>Hi " . htmlspecialchars($user['firstName']) . ",</p>
      <p>Your appointment has been booked successfully. Here are the details:</p>

      <table style='width:100%; border-collapse:collapse; margin:20px 0;'>
        <tr>
          <td style='padding:8px 0; color:#64748b; width:130px;'>Reference</td>
          <td style='padding:8px 0; font-weight:bold;'>#SC" . str_pad($appointmentID, 6, '0', STR_PAD_LEFT) . "</td>
        </tr>
        <tr>
          <td style='padding:8px 0; color:#64748b;'>Service</td>
          <td style='padding:8px 0;'>" . htmlspecialchars($service['serviceName']) . "</td>
        </tr>
        <tr>
          <td style='padding:8px 0; color:#64748b;'>Date</td>
          <td style='padding:8px 0;'>$prettyDate</td>
        </tr>
        <tr>
          <td style='padding:8px 0; color:#64748b;'>Time</td>
          <td style='padding:8px 0;'>$prettyTime (approx. {$durationMin} mins)</td>
        </tr>
        <tr>
          <td style='padding:8px 0; color:#64748b;'>Address</td>
          <td style='padding:8px 0;'>" . htmlspecialchars($address) . "</td>
        </tr>
        <tr>
          <td style='padding:8px 0; color:#64748b;'>Amount</td>
          <td style='padding:8px 0;'>$" . number_format((float)$service['price'], 2) . " / " . htmlspecialchars($service['price_unit']) . "</td>
        </tr>
      </table>

      <p style='color:#64748b; font-size:13px;'>
        A licensed nurse will be assigned to your appointment shortly.
        You can view or manage this booking from your dashboard.
      </p>
      <p style='color:#64748b; font-size:13px; margin-top:24px;'>
        Thank you for choosing StepCare.
      </p>
    </div>
  </div>
";

sendMail($user['email'], 'StepCare — Your booking is confirmed', $html);

/* ---------- Set flash for the detail page ---------- */
$_SESSION['booking_flash'] = [
    'title'   => 'Booking confirmed!',
    'message' => 'Reference #SC' . str_pad($appointmentID, 6, '0', STR_PAD_LEFT)
               . ' — ' . $prettyDate . ' at ' . $prettyTime
               . '. A confirmation email has been sent to you.'
];

header('Location: serviceDetail.php?id=' . $serviceID . '#book');
exit;