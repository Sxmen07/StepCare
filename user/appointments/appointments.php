<?php
session_start();
require __DIR__ . '/../../config/db_connect.php';

if (empty($_SESSION['userID'])) {
    header('Location: ../../loginNsignup/login/login.html');
    exit;
}

$userID = (int)$_SESSION['userID'];

/* ================= BACKGROUND CHECK: AUTO-CANCELLATIONS ================= */
// 1. Auto-cancel if payment failed & less than 2 days (48 hours) left before startDateTime
// 2. Auto-cancel if appointment is pending/confirmed, nurseID is null, and startDateTime day has arrived or passed without assignment
$nowSql = date('Y-m-d H:i:s');
$twoDaysLaterSql = date('Y-m-d H:i:s', strtotime('+2 days'));

// Check payment failures needing 2-day auto-cancel
$failPayStmt = $pdo->prepare("
    SELECT a.appointmentID 
    FROM appointments a
    JOIN payments p ON p.appointmentID = a.appointmentID
    WHERE a.userID = ? 
      AND a.appointmentStatus NOT IN ('cancelled', 'completed', 'declined')
      AND p.paymentStatus = 'failed'
      AND a.startDateTime <= ?
");
$failPayStmt->execute([$userID, $twoDaysLaterSql]);
$toCancelPayment = $failPayStmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($toCancelPayment as $aid) {
    $cancelPStmt = $pdo->prepare("UPDATE appointments SET appointmentStatus = 'cancelled', cancellationReason = 'Auto-cancelled: Payment proof not re-uploaded at least 2 days prior to appointment.' WHERE appointmentID = ?");
    $cancelPStmt->execute([$aid]);
    $pdo->prepare("INSERT INTO appointment_timeline (appointmentID, status, title, message, createdBy) VALUES (?, 'cancelled', 'Auto-cancelled', 'Payment proof was not re-uploaded at least 2 days prior to appointment.', 'system')")->execute([$aid]);
}

// Check unassigned nurse auto-cancel (if startDateTime <= current time and nurseID is still null)
$unassignedStmt = $pdo->prepare("
    SELECT appointmentID 
    FROM appointments 
    WHERE userID = ? 
      AND appointmentStatus IN ('pending', 'confirmed')
      AND nurseID IS NULL
      AND startDateTime <= ?
");
$unassignedStmt->execute([$userID, $nowSql]);
$toCancelUnassigned = $unassignedStmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($toCancelUnassigned as $aid) {
    $cancelUStmt = $pdo->prepare("UPDATE appointments SET appointmentStatus = 'cancelled', cancellationReason = 'Auto-cancelled: No nurse was assigned before the appointment day.' WHERE appointmentID = ?");
    $cancelUStmt->execute([$aid]);
    $pdo->prepare("INSERT INTO appointment_timeline (appointmentID, status, title, message, createdBy) VALUES (?, 'cancelled', 'Auto-cancelled', 'No nurse was assigned before the appointment day.', 'system')")->execute([$aid]);
}

/* ================= FETCH APPOINTMENTS ================= */
$stmt = $pdo->prepare("
    SELECT
        a.appointmentID, a.serviceID, a.nurseID,
        a.patientName, a.startDateTime, a.endDateTime,
        a.locationAddress, a.specialInstructions, a.appointmentStatus,
        s.serviceName, s.category, s.serviceImageURL, s.price, s.price_unit,
        n.firstName AS nurseFirstName,
        n.lastName  AS nurseLastName,
        p.paymentStatus, p.paymentProofURL, p.rejectionReason
    FROM appointments a
    LEFT JOIN services s ON s.serviceID = a.serviceID
    LEFT JOIN nurses   n ON n.nurseID   = a.nurseID
    LEFT JOIN payments p ON p.appointmentID = a.appointmentID
    WHERE a.userID = ?
    ORDER BY a.appointmentID DESC
");
$stmt->execute([$userID]);
$appointments = $stmt->fetchAll();

/* Fetch latest timeline status entry for each appointment */
$latestStatuses = [];
if (!empty($appointments)) {
    $apptIDs = array_column($appointments, 'appointmentID');
    $placeholders = implode(',', array_fill(0, count($apptIDs), '?'));
    $tStmt = $pdo->prepare("
        SELECT appointmentID, title, message, createdAt, status
        FROM appointment_timeline
        WHERE appointmentID IN ($placeholders)
        ORDER BY createdAt DESC, timelineID DESC
    ");
    $tStmt->execute($apptIDs);
    foreach ($tStmt->fetchAll() as $row) {
        $aid = (int)$row['appointmentID'];
        if (!isset($latestStatuses[$aid])) {
            $latestStatuses[$aid] = $row;
        }
    }
}

$counts = ['all' => count($appointments),
           'pending' => 0, 'confirmed' => 0,
           'completed' => 0, 'cancelled' => 0];

foreach ($appointments as $a) {
    $st = $a['appointmentStatus'];
    if ($st === 'pending')                                    $counts['pending']++;
    elseif (in_array($st, ['confirmed','in_progress'], true)) $counts['confirmed']++;
    elseif ($st === 'completed')                              $counts['completed']++;
    elseif (in_array($st, ['cancelled','declined'], true))    $counts['cancelled']++;
}

/* Fetch existing reviews submitted by this user for completed appointments */
$reviewedAppts = [];
$rStmt = $pdo->prepare("
    SELECT sc.appointmentID, sc.rating, sc.comment, nr.rating AS nurseRating, nr.comment AS nurseComment
    FROM serviceComments sc
    LEFT JOIN nurseReviews nr ON nr.appointmentID = sc.appointmentID AND nr.userID = sc.userID
    WHERE sc.userID = ? AND sc.appointmentID IS NOT NULL
");
$rStmt->execute([$userID]);
foreach ($rStmt->fetchAll() as $r) {
    $reviewedAppts[(int)$r['appointmentID']] = [
        'rating' => (int)$r['rating'],
        'comment' => $r['comment'],
        'nurseRating' => (int)($r['nurseRating'] ?? $r['rating']),
        'nurseComment' => $r['nurseComment'] ?? $r['comment']
    ];
}

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