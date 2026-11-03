<?php
// admin/services/serviceManagement.php
session_start();
require __DIR__ . '/../../config/db_connect.php';

// Optional: Check if admin is logged in
// if (empty($_SESSION['adminID'])) {
//     header('Location: /StepCare/loginNsignup/login/login.html');
//     exit;
// }

// Fetch all services
$stmt = $pdo->query("
    SELECT serviceID, serviceName, category, description, serviceImageURL, 
           price, price_unit, durationMin, durationMax, average_rating, review_count, isTopRecommendation
    FROM services 
    ORDER BY isTopRecommendation DESC, serviceName ASC
");
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch distinct categories for the pills
$categories = $pdo->query("
    SELECT DISTINCT category FROM services 
    WHERE category IS NOT NULL AND category <> '' 
    ORDER BY category
")->fetchAll(PDO::FETCH_COLUMN);

// Prepare JSON
$servicesJson = json_encode($services, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$categoriesJson = json_encode($categories);

include __DIR__ . '/serviceManagement.html';
?>