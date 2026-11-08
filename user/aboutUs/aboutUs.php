<?php
session_start();
require __DIR__ . '/../../config/db_connect.php';

/* ============================================================
   1. FETCH DYNAMIC STATS FOR SECTION 2 FROM DATABASE
   ============================================================ */
$stats = [
    'rating' => '0.0',
    'services' => 0,
    'nurses' => 0,
    'families' => 0
];

try {
    // Fetch overall average rating across services from database
    $stmt = $pdo->query("SELECT AVG(average_rating) FROM services WHERE average_rating > 0");
    $avg = $stmt->fetchColumn();
    if ($avg) {
        $stats['rating'] = number_format((float)$avg, 1);
    } else {
        // Fallback to average from comments if services table rating isn't populated yet
        $stmtCommentAvg = $pdo->query("SELECT AVG(rating) FROM serviceComments WHERE isHidden = 0");
        $commentAvg = $stmtCommentAvg->fetchColumn();
        if ($commentAvg) $stats['rating'] = number_format((float)$commentAvg, 1);
    }

    // Total completed appointments count from database
    $stmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE appointmentStatus = 'completed'");
    $completed = (int)$stmt->fetchColumn();
    $stats['services'] = $completed;

    // Total verified nurses count from database
    $stmt = $pdo->query("SELECT COUNT(*) FROM nurses WHERE isVerified = 1");
    $nurses = (int)$stmt->fetchColumn();
    $stats['nurses'] = $nurses;

    // Total unique families/users who booked or registered from database
    $stmt = $pdo->query("SELECT COUNT(DISTINCT userID) FROM appointments");
    $families = (int)$stmt->fetchColumn();
    if ($families === 0) {
        $stmtUsers = $pdo->query("SELECT COUNT(*) FROM users");
        $families = (int)$stmtUsers->fetchColumn();
    }
    $stats['families'] = $families;

} catch (Exception $e) {
    // Fail silently, keeping default 0 counts if query fails
}

/* ============================================================
   2. FETCH REAL SERVICE IMAGES FOR SECTION 1 (MARQUEE)
   ============================================================ */
$marqueeImages = [];
try {
    $stmt = $pdo->query("SELECT serviceImageURL FROM services WHERE serviceImageURL IS NOT NULL AND serviceImageURL != '' LIMIT 6");
    $marqueeImages = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

/* ============================================================
   3. FETCH REAL REVIEWS FOR SECTION 4 FROM DATABASE
   ============================================================ */
$reviews = [];
try {
    $stmt = $pdo->query("
        SELECT c.rating, c.comment, u.firstName, u.lastName 
        FROM serviceComments c
        JOIN users u ON u.userID = c.userID
        WHERE c.isHidden = 0 AND c.comment IS NOT NULL AND TRIM(c.comment) != ''
        ORDER BY c.createdAt DESC LIMIT 6
    ");
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$marqueeImagesJson = json_encode($marqueeImages, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$statsJson = json_encode($stats);

include __DIR__ . '/aboutUs.html';