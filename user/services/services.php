<?php
session_start();
require __DIR__ . '/../../config/db_connect.php';

// --- AUTO-CANCEL ABANDONED UNPAID BOOKINGS ---
if (!empty($_SESSION['userID'])) {
    $cleanup = $pdo->prepare("
        UPDATE appointments a
        JOIN payments p ON a.appointmentID = p.appointmentID
        SET a.appointmentStatus = 'cancelled', p.paymentStatus = 'failed'
        WHERE a.userID = ? AND a.appointmentStatus = 'pending' AND p.paymentStatus = 'pending'
          AND a.createdAt < NOW() - INTERVAL 15 MINUTE
    ");
    $cleanup->execute([$_SESSION['userID']]);
}

/* ---------- Fetch all services ---------- */
$sql = "
    SELECT
        s.serviceID, s.serviceName, s.category, s.description, s.serviceImageURL,
        s.price, s.price_unit, s.average_rating, s.review_count,
        s.durationMin, s.durationMax, s.isTopRecommendation,
        GROUP_CONCAT(DISTINCT sf.featureText ORDER BY sf.serviceFeatureID SEPARATOR '|||') AS features,
        GROUP_CONCAT(DISTINCT sb.badgeText   ORDER BY sb.serviceBadgeID   SEPARATOR '|||') AS badges
    FROM services s
    LEFT JOIN serviceFeatures sf ON sf.serviceID = s.serviceID
    LEFT JOIN serviceBadges   sb ON sb.serviceID = s.serviceID
    GROUP BY s.serviceID
    ORDER BY s.isTopRecommendation DESC, s.average_rating DESC
";
$services = $pdo->query($sql)->fetchAll();

/* ---------- Categories ---------- */
$categories = $pdo->query(
    "SELECT DISTINCT category FROM services
     WHERE category IS NOT NULL AND category <> ''
     ORDER BY category"
)->fetchAll(PDO::FETCH_COLUMN);

/* ---------- Fetch Reviews (grouped by service) ---------- */
$reviewsByService = [];
try {
    $stmt = $pdo->query("
        SELECT c.serviceID, c.rating, c.comment, c.createdAt, 
               u.firstName, u.lastName 
        FROM serviceComments c
        JOIN users u ON u.userID = c.userID
        WHERE c.isHidden = 0
        ORDER BY c.createdAt DESC
    ");
    $allReviews = $stmt->fetchAll();
    
    foreach ($allReviews as $r) {
        $reviewsByService[$r['serviceID']][] = $r;
    }
} catch (Exception $e) {
    // Table might not exist yet, fail silently
}

/* ---------- Time slots (fixed) ---------- */
$timeSlots = [
    '08:00:00' => ['label' => '8:00 AM',  'period' => 'Morning'],
    '10:00:00' => ['label' => '10:00 AM', 'period' => 'Morning'],
    '11:00:00' => ['label' => '11:00 AM', 'period' => 'Morning'],
    '14:00:00' => ['label' => '2:00 PM',  'period' => 'Afternoon'],
    '16:00:00' => ['label' => '4:00 PM',  'period' => 'Afternoon'],
];

/* ---------- Nurses available ---------- */
$totalNurses = (int)$pdo->query("SELECT COUNT(*) FROM nurses WHERE isVerified = 1")->fetchColumn();
if ($totalNurses <= 0) $totalNurses = 3;

/* ---------- Availability for next 30 days ---------- */
$availability = [];
$cursor = new DateTime('today');
for ($i = 0; $i < 30; $i++) {
    $d = $cursor->format('Y-m-d');
    $availability[$d] = [];
    foreach ($timeSlots as $time => $meta) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM appointments
             WHERE DATE(startDateTime) = ?
               AND TIME(startDateTime) = ?
               AND appointmentStatus IN ('pending','confirmed','in_progress')"
        );
        $stmt->execute([$d, $time]);
        $booked = (int)$stmt->fetchColumn();
        $availability[$d][$time] = max(0, $totalNurses - $booked);
    }
    $cursor->modify('+1 day');
}

/* ---------- Pre-fill patient name ---------- */
$defaultPatientName = '';
if (!empty($_SESSION['userID'])) {
    $stmt = $pdo->prepare('SELECT firstName, lastName FROM users WHERE userID = ?');
    $stmt->execute([$_SESSION['userID']]);
    $u = $stmt->fetch();
    if ($u) $defaultPatientName = trim($u['firstName'] . ' ' . $u['lastName']);
}

/* ---------- Encode for JS ---------- */
$servicesJson     = json_encode($services, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$reviewsJson      = json_encode($reviewsByService, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$availabilityJson = json_encode($availability);
$timeSlotsJson    = json_encode($timeSlots);
$serverTimeNow    = date('H:i:s');

/* ---------- Flash ---------- */
$flash = $_SESSION['booking_flash'] ?? null;
unset($_SESSION['booking_flash']);

$bookingSuccess = null;
if (!empty($_GET['booked'])) {
    $bookingSuccess = [
        'ref'     => $_GET['ref']     ?? '',
        'date'    => $_GET['date']    ?? '',
        'time'    => $_GET['time']    ?? '',
        'service' => $_GET['service'] ?? '',
    ];
}

include __DIR__ . '/services.html';