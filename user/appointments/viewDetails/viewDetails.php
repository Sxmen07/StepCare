<?php
// user/appointments/viewDetails/viewDetails.php
session_start();
require __DIR__ . '/../../../config/db_connect.php';

if (empty($_SESSION['userID'])) {
    header('Location: ../../../loginNsignup/login/login.html');
    exit;
}

$userID        = (int)$_SESSION['userID'];
$appointmentID = (int)($_GET['id'] ?? 0);

if ($appointmentID <= 0) {
    header('Location: ../appointments.php');
    exit;
}

/* ---- Load full record ---- */
$stmt = $pdo->prepare("
    SELECT
        a.appointmentID, a.patientName, a.startDateTime, a.endDateTime,
        a.locationAddress, a.specialInstructions, a.appointmentStatus,
        a.dayCount, a.createdAt,
        s.serviceName, s.category, s.serviceImageURL, s.price, s.price_unit,
        s.durationMin, s.durationMax,
        u.firstName AS userFirstName, u.lastName AS userLastName,
        u.email     AS userEmail,      u.phoneNumber AS userPhone,
        n.nurseID,
        n.firstName      AS nurseFirstName,
        n.lastName       AS nurseLastName,
        n.phoneNumber    AS nursePhone,
        n.specialization AS nurseSpecialization,
        n.nursePhotoURL  AS nursePhoto,
        n.averageRating  AS nurseRating
    FROM appointments a
    LEFT JOIN services s ON s.serviceID = a.serviceID
    LEFT JOIN users    u ON u.userID    = a.userID
    LEFT JOIN nurses   n ON n.nurseID   = a.nurseID
    WHERE a.appointmentID = ? AND a.userID = ?
");
$stmt->execute([$appointmentID, $userID]);
$appt = $stmt->fetch();

if (!$appt) {
    header('Location: ../appointments.php');
    exit;
}

/* ---- Load timeline ---- */
$tStmt = $pdo->prepare("
    SELECT timelineID, status, title, message, createdBy, createdByID, createdAt
    FROM appointment_timeline
    WHERE appointmentID = ?
    ORDER BY createdAt ASC, timelineID ASC
");
$tStmt->execute([$appointmentID]);
$timeline = $tStmt->fetchAll();

if (empty($timeline)) {
    $timeline[] = [
        'status'    => $appt['appointmentStatus'],
        'title'     => 'Booking received',
        'message'   => 'Your appointment has been recorded.',
        'createdBy' => 'system',
        'createdAt' => $appt['createdAt'] ?? $appt['startDateTime'],
    ];
}

/* ---- Helpers ---- */
function tlColor(string $s): array {
    return match ($s) {
        'pending'                => ['amber-500',   'bg-amber-100 text-amber-800 ring-amber-200'],
        'confirmed', 'assigned'  => ['blue-500',    'bg-blue-100 text-blue-800 ring-blue-200'],
        'on_the_way'             => ['violet-500',  'bg-violet-100 text-violet-800 ring-violet-200'],
        'arrived'                => ['indigo-500',  'bg-indigo-100 text-indigo-800 ring-indigo-200'],
        'in_progress'            => ['indigo-600',  'bg-indigo-100 text-indigo-800 ring-indigo-200'],
        'completed'              => ['emerald-500', 'bg-emerald-100 text-emerald-800 ring-emerald-200'],
        'reviewed'               => ['amber-500',   'bg-amber-100 text-amber-800 ring-amber-200'],
        'cancelled', 'declined'  => ['rose-500',    'bg-rose-100 text-rose-700 ring-rose-200'],
        default                  => ['slate-400',   'bg-slate-100 text-slate-700 ring-slate-200'],
    };
}

function statusLabel(string $s): string {
    return match ($s) {
        'pending'     => 'Pending Nurse',
        'confirmed'   => 'Confirmed',
        'assigned'    => 'Nurse Assigned',
        'on_the_way'  => 'On the Way',
        'arrived'     => 'Nurse Arrived',
        'in_progress' => 'In Progress',
        'completed'   => 'Completed',
        'reviewed'    => 'Reviewed',
        'cancelled'   => 'Cancelled',
        'declined'    => 'Declined',
        default       => ucfirst(str_replace('_', ' ', $s)),
    };
}

/* Services store paths as ../../images/... relative to /user/appointments/.
   From viewDetails we are one level deeper → upgrade to ../../../ */
function fixImg(?string $p): string {
    if (!$p) return '';
    return preg_replace('#^\.\./\.\./#', '../../../', $p);
}

$ref        = '#SC' . str_pad((string)$appt['appointmentID'], 6, '0', STR_PAD_LEFT);
$serviceImg = fixImg($appt['serviceImageURL']) ?: '../../../images/services/placeholder.jpg';
$nurseImg   = fixImg($appt['nursePhoto'])
           ?: 'https://ui-avatars.com/api/?name=' .
              urlencode(($appt['nurseFirstName'] ?? 'N') . ' ' . ($appt['nurseLastName'] ?? '')) .
              '&background=3C52C3&color=fff&size=200';

$dt = strtotime($appt['startDateTime']);

include __DIR__ . '/viewDetails.html';