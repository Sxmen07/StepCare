<?php
session_start();
require __DIR__ . '/../../config/db_connect.php';

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
if ($totalNurses <= 0) $totalNurses = 3;   // fallback for demo

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
               AND appointmentStatus IN ('confirmed','in_progress')"
        );
        $stmt->execute([$d, $time]);
        $booked = (int)$stmt->fetchColumn();
        $availability[$d][$time] = max(0, $totalNurses - $booked);
    }
    $cursor->modify('+1 day');
}

/* ---------- Pre-fill patient name from the logged-in user ---------- */
$defaultPatientName = '';
if (!empty($_SESSION['userID'])) {
    $stmt = $pdo->prepare('SELECT firstName, lastName FROM users WHERE userID = ?');
    $stmt->execute([$_SESSION['userID']]);
    $u = $stmt->fetch();
    if ($u) $defaultPatientName = trim($u['firstName'] . ' ' . $u['lastName']);
}

/* ---------- Encode for JS ---------- */
$servicesJson     = json_encode($services, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$availabilityJson = json_encode($availability);
$timeSlotsJson    = json_encode($timeSlots);

/* ---------- Flash ---------- */
$flash = $_SESSION['booking_flash'] ?? null;
unset($_SESSION['booking_flash']);

include __DIR__ . '/services.html';