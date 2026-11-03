<?php
// StepCare - Service Management Entry & Backend Controller
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Handle AJAX Request to fetch service data from database
if (isset($_GET['action']) && $_GET['action'] === 'fetch_services') {
    header('Content-Type: application/json; charset=utf-8');
    require_once __DIR__ . '/../../config/db_connect.php';

    try {
        // 1. Query all services sorted by top recommendation and ID
        $stmt = $pdo->query("SELECT * FROM services ORDER BY isTopRecommendation DESC, serviceID ASC");
        $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Fetch associated features and badges for each service
        foreach ($services as &$service) {
            $serviceId = (int)$service['serviceID'];

            // Features list
            $featStmt = $pdo->prepare("SELECT featureText FROM serviceFeatures WHERE serviceID = ? ORDER BY serviceFeatureID ASC");
            $featStmt->execute([$serviceId]);
            $service['features'] = $featStmt->fetchAll(PDO::FETCH_COLUMN);

            // Badges list
            $badgeStmt = $pdo->prepare("SELECT badgeText FROM serviceBadges WHERE serviceID = ? ORDER BY serviceBadgeID ASC");
            $badgeStmt->execute([$serviceId]);
            $service['badges'] = $badgeStmt->fetchAll(PDO::FETCH_COLUMN);
        }

        echo json_encode([
            'status' => 'success',
            'data'   => $services
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'status'  => 'error',
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// Render the HTML view template
require_once __DIR__ . '/serviceManagement.html';