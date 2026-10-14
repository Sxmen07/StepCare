<?php
session_start();
require __DIR__ . '/../../config/db_connect.php';

/* ---------- Admin auth ---------- */
if (empty($_SESSION['userID']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../../loginNsignup/login/login.html');
    exit;
}

/* ---------- Fetch all services ---------- */
$services = $pdo->query("
    SELECT s.serviceID, s.serviceName, s.category, s.description, s.serviceImageURL,
           s.price, s.price_unit, s.average_rating, s.review_count,
           s.durationMin, s.durationMax, s.isTopRecommendation,
           (SELECT COUNT(*) FROM serviceFeatures WHERE serviceID = s.serviceID) AS featureCount,
           GROUP_CONCAT(DISTINCT sb.badgeText SEPARATOR '|||') AS badges
    FROM services s
    LEFT JOIN serviceBadges sb ON sb.serviceID = s.serviceID
    GROUP BY s.serviceID
    ORDER BY s.isTopRecommendation DESC, s.serviceID DESC
")->fetchAll();

/* ---------- Stats ---------- */
$totalServices = count($services);
$pinnedCount   = count(array_filter($services, fn($s) => (int)$s['isTopRecommendation'] === 1));
$avgRating     = $totalServices
    ? number_format(array_sum(array_column($services, 'average_rating')) / $totalServices, 2)
    : '0.00';

/* ---------- Categories (for edit dropdown) ---------- */
$categories = $pdo->query(
    "SELECT DISTINCT category FROM services
     WHERE category IS NOT NULL AND category <> ''
     ORDER BY category"
)->fetchAll(PDO::FETCH_COLUMN);

/* ---------- Services JSON for JS ---------- */
$servicesJson = json_encode($services, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

/* ---------- Hand off to the view ---------- */
include __DIR__ . '/adminServices.html';