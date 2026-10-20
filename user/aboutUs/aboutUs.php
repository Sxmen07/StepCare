<?php
session_start();
require __DIR__ . '/../../config/db_connect.php';

/* ============================================================
   1. FETCH STATS FOR SECTION 2
   ============================================================ */
$stats = [
    'rating' => '4.9', // Default fallbacks
    'services' => '500',
    'nurses' => '50',
    'families' => '2000'
];

try {
    // Average rating across all services
    $stmt = $pdo->query("SELECT AVG(average_rating) FROM services");
    $avg = $stmt->fetchColumn();
    if ($avg) $stats['rating'] = number_format((float)$avg, 1);

    // Total completed services
    $stmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE appointmentStatus = 'completed'");
    $completed = (int)$stmt->fetchColumn();
    if ($completed > 0) $stats['services'] = $completed + 500; // Add baseline for demo

    // Total verified nurses
    $stmt = $pdo->query("SELECT COUNT(*) FROM nurses WHERE isVerified = 1");
    $nurses = (int)$stmt->fetchColumn();
    if ($nurses > 0) $stats['nurses'] = $nurses + 50; // Add baseline for demo
} catch (Exception $e) {
    // Silently use fallbacks
}

/* ============================================================
   2. FETCH IMAGES FOR SECTION 1 (MARQUEE)
   ============================================================ */
$marqueeImages = [];
try {
    $stmt = $pdo->query("SELECT serviceImageURL FROM services LIMIT 5");
    $marqueeImages = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

if (empty($marqueeImages)) {
    // Fallback images if DB is empty
    $marqueeImages = [
        '../../images/services/elderly.png',
        '../../images/services/woundcare.png',
        '../../images/services/stroke.png',
        '../../images/services/maternal.png'
    ];
}

/* ============================================================
   3. FETCH REVIEWS FOR SECTION 4
   ============================================================ */
$reviews = [];
try {
    $stmt = $pdo->query("
        SELECT c.rating, c.comment, u.firstName, u.lastName 
        FROM serviceComments c
        JOIN users u ON u.userID = c.userID
        WHERE c.isHidden = 0 AND c.comment IS NOT NULL
        ORDER BY c.createdAt DESC LIMIT 6
    ");
    $reviews = $stmt->fetchAll();
} catch (Exception $e) {}

// Fallback reviews if DB is empty
if (empty($reviews)) {
    $reviews = [
        ['rating' => 5, 'comment' => 'The nurse arrived right on time and was incredibly professional. My mother felt so comfortable.', 'firstName' => 'Sarah', 'lastName' => 'J.'],
        ['rating' => 5, 'comment' => 'I booked a wound care service for my father. The process was seamless and the care was top-notch.', 'firstName' => 'David', 'lastName' => 'C.'],
        ['rating' => 4, 'comment' => 'Excellent service. The app makes it so easy to track appointments and communicate.', 'firstName' => 'Mei', 'lastName' => 'L.'],
        ['rating' => 5, 'comment' => 'Compassionate and highly skilled nurses. StepCare has been a lifesaver for our family.', 'firstName' => 'Ahmad', 'lastName' => 'R.']
    ];
}

$marqueeImagesJson = json_encode($marqueeImages, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$statsJson = json_encode($stats);

include __DIR__ . '/aboutUs.html';