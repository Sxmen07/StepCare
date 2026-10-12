<?php
session_start();
require __DIR__ . '/../../config/db_connect.php';

/* ---------- Fetch all services (with comma-separated features/badges) ---------- */
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

/* ---------- Fetch distinct categories for the filter chips ---------- */
$categories = $pdo->query(
    "SELECT DISTINCT category FROM services WHERE category IS NOT NULL AND category <> '' ORDER BY category"
)->fetchAll(PDO::FETCH_COLUMN);

/* ---------- Hand off to the view ---------- */
include __DIR__ . '/services.html';