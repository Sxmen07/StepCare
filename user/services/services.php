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
$services = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

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
    $allReviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($allReviews as $r) {
        $reviewsByService[$r['serviceID']][] = $r;
    }
} catch (Exception $e) {
    // Fail silently if table not populated
}

/* ---------- Time Slots per Service from Database ---------- */
$serviceSlotsMap = [];
$slotStmt = $pdo->query("
    SELECT serviceID, timeSlotLabel, maxCapacity 
    FROM serviceTimeSlots 
    WHERE isAvailable = 1 
    ORDER BY slotID ASC
");
$rawSlots = $slotStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rawSlots as $slotRow) {
    $svcId = (int)$slotRow['serviceID'];
    $label = trim($slotRow['timeSlotLabel']);
    
    // Normalize time to 24-hour format HH:MM:SS
    $dt = DateTime::createFromFormat('g:i A', $label) ?: DateTime::createFromFormat('H:i', $label);
    if (!$dt) {
        try { $dt = new DateTime($label); } catch (Exception $e) { continue; }
    }
    
    $time24 = $dt->format('H:i:s');
    $hour = (int)$dt->format('H');
    $period = ($hour < 12) ? 'Morning' : 'Afternoon';

    if (!isset($serviceSlotsMap[$svcId])) {
        $serviceSlotsMap[$svcId] = [];
    }

    $serviceSlotsMap[$svcId][$time24] = [
        'label' => $dt->format('g:i A'),
        'period' => $period,
        'maxCapacity' => (int)($slotRow['maxCapacity'] ?? 5)
    ];
}

/* ---------- Dynamic Availability per Service for Next 30 Days ---------- */
$availability = [];
$cursor = new DateTime('today');

for ($i = 0; $i < 30; $i++) {
    $d = $cursor->format('Y-m-d');
    $availability[$d] = [];

    foreach ($services as $svc) {
        $svcId = (int)$svc['serviceID'];
        $availability[$d][$svcId] = [];
        $svcTimeSlots = $serviceSlotsMap[$svcId] ?? [];

        foreach ($svcTimeSlots as $time24 => $slotMeta) {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM appointments
                 WHERE serviceID = ?
                   AND DATE(startDateTime) = ?
                   AND TIME(startDateTime) = ?
                   AND appointmentStatus IN ('pending','confirmed','in_progress')"
            );
            $stmt->execute([$svcId, $d, $time24]);
            $bookedCount = (int)$stmt->fetchColumn();

            $maxCap = $slotMeta['maxCapacity'];
            $availability[$d][$svcId][$time24] = max(0, $maxCap - $bookedCount);
        }
    }
    $cursor->modify('+1 day');
}

/* ---------- Pre-fill patient name ---------- */
$defaultPatientName = '';
if (!empty($_SESSION['userID'])) {
    $stmt = $pdo->prepare('SELECT firstName, lastName FROM users WHERE userID = ?');
    $stmt->execute([$_SESSION['userID']]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($u) $defaultPatientName = trim($u['firstName'] . ' ' . $u['lastName']);
}

/* ---------- JSON Exports for JS ---------- */
$servicesJson     = json_encode($services, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$reviewsJson      = json_encode($reviewsByService, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$availabilityJson = json_encode($availability);
$serviceSlotsJson = json_encode($serviceSlotsMap);
$serverTimeNow    = date('H:i:s');

/* ---------- Flash & Success Message ---------- */
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