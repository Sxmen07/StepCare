<?php
// admin/services/saveService.php
session_start();
header('Content-Type: application/json');
require __DIR__ . '/../../config/db_connect.php';

if (empty($_SESSION['adminID'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$serviceID      = (int)($_POST['serviceID'] ?? 0);
$serviceName    = trim($_POST['serviceName'] ?? '');
$category       = trim($_POST['category'] ?? '');
$description    = trim($_POST['description'] ?? '');
$price          = (float)($_POST['price'] ?? 0);
$price_unit     = trim($_POST['price_unit'] ?? 'visit');
$durationMin    = (int)($_POST['durationMin'] ?? 0);
$durationMax    = (int)($_POST['durationMax'] ?? 0);
$isTopRecommend = isset($_POST['isTopRecommendation']) ? 1 : 0;
$imageURL       = trim($_POST['serviceImageURL'] ?? '');

if ($serviceName === '' || $price <= 0) {
    echo json_encode(['success' => false, 'message' => 'Service name and valid price are required.']); exit;
}

try {
    if ($serviceID > 0) {
        // Update existing
        $stmt = $pdo->prepare("
            UPDATE services 
            SET serviceName = ?, category = ?, description = ?, price = ?, 
                price_unit = ?, durationMin = ?, durationMax = ?, 
                isTopRecommendation = ?, serviceImageURL = ?
            WHERE serviceID = ?
        ");
        $stmt->execute([
            $serviceName, $category, $description, $price, 
            $price_unit, $durationMin, $durationMax, 
            $isTopRecommend, $imageURL, $serviceID
        ]);
    } else {
        // Insert new
        $stmt = $pdo->prepare("
            INSERT INTO services (serviceName, category, description, price, price_unit, durationMin, durationMax, isTopRecommendation, serviceImageURL)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $serviceName, $category, $description, $price, 
            $price_unit, $durationMin, $durationMax, $isTopRecommend, $imageURL
        ]);
    }
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>