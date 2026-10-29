<?php
session_start();
require __DIR__ . '/../../config/db_connect.php';

if (empty($_SESSION['userID'])) {
    header('Location: ../../loginNsignup/login/login.html');
    exit;
}

$userID = (int)$_SESSION['userID'];

$stmt = $pdo->prepare("
    SELECT
        a.appointmentID,
        a.serviceID,
        a.nurseID,
        a.patientName,
        a.startDateTime,
        a.endDateTime,
        a.locationAddress,
        a.specialInstructions,
        a.appointmentStatus,
        s.serviceName,
        s.category,
        s.serviceImageURL,
        s.price,
        s.price_unit,
        n.firstName   AS nurseFirstName,
        n.lastName    AS nurseLastName
    FROM appointments a
    LEFT JOIN services s ON s.serviceID = a.serviceID
    LEFT JOIN nurses   n ON n.nurseID   = a.nurseID
    WHERE a.userID = ?
    ORDER BY a.appointmentID DESC
");
$stmt->execute([$userID]);
$appointments = $stmt->fetchAll();

/* Counts for the chips (small inline badges) */
$counts = ['all' => count($appointments),
           'pending' => 0, 'confirmed' => 0,
           'completed' => 0, 'cancelled' => 0];

foreach ($appointments as $a) {
    $st = $a['appointmentStatus'];
    if ($st === 'pending')                                       $counts['pending']++;
    elseif (in_array($st, ['confirmed','in_progress'], true))    $counts['confirmed']++;
    elseif ($st === 'completed')                                 $counts['completed']++;
    elseif (in_array($st, ['cancelled','declined'], true))       $counts['cancelled']++;
}

/* Which completed appointments has this user already reviewed? */
$reviewedAppts = []; // appointmentID => rating
$rStmt = $pdo->prepare("
    SELECT appointmentID, rating
    FROM serviceComments
    WHERE userID = ? AND appointmentID IS NOT NULL
");
$rStmt->execute([$userID]);
foreach ($rStmt->fetchAll() as $r) {
    $reviewedAppts[(int)$r['appointmentID']] = (int)$r['rating'];
}

/* Small helper for status badges */
function statusBadge(string $status): array {
    return match ($status) {
        'pending'     => ['Pending Nurse', 'amber'],
        'confirmed'   => ['Confirmed',     'blue'],
        'in_progress' => ['In Progress',   'indigo'],
        'completed'   => ['Completed',     'emerald'],
        'cancelled'   => ['Cancelled',     'red'],
        'declined'    => ['Declined',      'red'],
        default       => [ucfirst($status), 'slate'],
    };
}

$flash = $_SESSION['booking_flash'] ?? null;
unset($_SESSION['booking_flash']);

include __DIR__ . '/appointments.html';