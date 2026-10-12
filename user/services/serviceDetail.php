<?php
session_start();
require __DIR__ . '/../../config/db_connect.php';

/* ---------- Grab the service ---------- */
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: services.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT s.*,
           GROUP_CONCAT(DISTINCT sf.featureText ORDER BY sf.serviceFeatureID SEPARATOR '|||') AS features,
           GROUP_CONCAT(DISTINCT sb.badgeText   ORDER BY sb.serviceBadgeID   SEPARATOR '|||') AS badges
    FROM services s
    LEFT JOIN serviceFeatures sf ON sf.serviceID = s.serviceID
    LEFT JOIN serviceBadges   sb ON sb.serviceID = s.serviceID
    WHERE s.serviceID = ?
    GROUP BY s.serviceID
");
$stmt->execute([$id]);
$service = $stmt->fetch();

if (!$service) {
    header('Location: services.php');
    exit;
}

/* ---------- Flash message (from booking) ---------- */
$flash = $_SESSION['booking_flash'] ?? null;
unset($_SESSION['booking_flash']);

/* ---------- Hand off to the view ---------- */
include __DIR__ . '/serviceDetail.html';