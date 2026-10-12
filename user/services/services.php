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

/* ---------- Fetch distinct categories ---------- */
$categories = $pdo->query(
    "SELECT DISTINCT category FROM services
     WHERE category IS NOT NULL AND category <> ''
     ORDER BY category"
)->fetchAll(PDO::FETCH_COLUMN);

/* ---------- Pass services as JSON for the modal ---------- */
$servicesJson = json_encode($services, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

/* ---------- Flash message from booking ---------- */
$flash = $_SESSION['booking_flash'] ?? null;
unset($_SESSION['booking_flash']);

/* ---------- Hand off to the view ---------- */
include __DIR__ . '/services.html';