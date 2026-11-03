<?php
// StepCare - Service Management Entry & Backend Controller
ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once __DIR__ . '/../../config/db_connect.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'fetch_services') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $stmt = $pdo->query("SELECT * FROM services ORDER BY isTopRecommendation DESC, serviceID ASC");
        $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($services as &$service) {
            $serviceId = (int)$service['serviceID'];

            // Features (What's Included)
            $featStmt = $pdo->prepare("SELECT featureText FROM serviceFeatures WHERE serviceID = ? ORDER BY serviceFeatureID ASC");
            $featStmt->execute([$serviceId]);
            $service['features'] = $featStmt->fetchAll(PDO::FETCH_COLUMN);

            // Badges
            $badgeStmt = $pdo->prepare("SELECT badgeText FROM serviceBadges WHERE serviceID = ? ORDER BY serviceBadgeID ASC");
            $badgeStmt->execute([$serviceId]);
            $service['badges'] = $badgeStmt->fetchAll(PDO::FETCH_COLUMN);

            // Time Slots (Inner Page)
            $slotStmt = $pdo->prepare("SELECT timeSlotLabel FROM serviceTimeSlots WHERE serviceID = ? ORDER BY slotID ASC");
            $slotStmt->execute([$serviceId]);
            $service['timeSlots'] = $slotStmt->fetchAll(PDO::FETCH_COLUMN);
        }

        echo json_encode(['status' => 'success', 'data' => $services]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// Handle Add or Edit Form Submission
if ($action === 'save_service') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $serviceID = $_POST['serviceID'] ?? '';
        $serviceName = $_POST['serviceName'] ?? '';
        $category = $_POST['category'] ?? '';
        $price = $_POST['price'] ?? 0;
        $durationMin = $_POST['durationMin'] ?? 45;
        $durationMax = $_POST['durationMax'] ?? 60;
        $description = $_POST['description'] ?? ''; // Inner page content
        $features = json_decode($_POST['features'] ?? '[]', true); // What's included
        $timeSlots = json_decode($_POST['timeSlots'] ?? '[]', true); // Inner page time slots

        // Handle Image Upload if provided
        $serviceImageURL = $_POST['existingImage'] ?? '../../images/services/elderly.png';
        if (isset($_FILES['serviceImage']) && $_FILES['serviceImage']['error'] === UPLOAD_ERR_OK) {
            $fileTmp = $_FILES['serviceImage']['tmp_name'];
            $fileName = time() . '_' . basename($_FILES['serviceImage']['name']);
            $uploadDir = __DIR__ . '/../../images/services/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $destination = $uploadDir . $fileName;
            if (move_uploaded_file($fileTmp, $destination)) {
                $serviceImageURL = '../../images/services/' . $fileName;
            }
        }

        if (!empty($serviceID)) {
            // UPDATE existing service
            $stmt = $pdo->prepare("UPDATE services SET serviceName=?, category=?, description=?, price=?, durationMin=?, durationMax=?, serviceImageURL=? WHERE serviceID=?");
            $stmt->execute([$serviceName, $category, $description, $price, $durationMin, $durationMax, $serviceImageURL, $serviceID]);

            // Refresh Features
            $pdo->prepare("DELETE FROM serviceFeatures WHERE serviceID=?")->execute([$serviceID]);
            $featStmt = $pdo->prepare("INSERT INTO serviceFeatures (serviceID, featureText) VALUES (?, ?)");
            foreach ($features as $f) {
                if (!empty(trim($f))) {
                    $featStmt->execute([$serviceID, trim($f)]);
                }
            }

            // Refresh Time Slots
            $pdo->prepare("DELETE FROM serviceTimeSlots WHERE serviceID=?")->execute([$serviceID]);
            $slotStmt = $pdo->prepare("INSERT INTO serviceTimeSlots (serviceID, timeSlotLabel) VALUES (?, ?)");
            foreach ($timeSlots as $s) {
                if (!empty(trim($s))) {
                    $slotStmt->execute([$serviceID, trim($s)]);
                }
            }

            echo json_encode(['status' => 'success', 'message' => 'Service updated successfully.']);
        } else {
            // INSERT new service
            $stmt = $pdo->prepare("INSERT INTO services (serviceName, category, description, serviceImageURL, price, price_unit, durationMin, durationMax) VALUES (?, ?, ?, ?, ?, 'visit', ?, ?)");
            $stmt->execute([$serviceName, $category, $description, $serviceImageURL, $price, $durationMin, $durationMax]);
            $newID = $pdo->lastInsertId();

            // Insert Features
            $featStmt = $pdo->prepare("INSERT INTO serviceFeatures (serviceID, featureText) VALUES (?, ?)");
            foreach ($features as $f) {
                if (!empty(trim($f))) {
                    $featStmt->execute([$newID, trim($f)]);
                }
            }

            // Insert Time Slots
            $slotStmt = $pdo->prepare("INSERT INTO serviceTimeSlots (serviceID, timeSlotLabel) VALUES (?, ?)");
            foreach ($timeSlots as $s) {
                if (!empty(trim($s))) {
                    $slotStmt->execute([$newID, trim($s)]);
                }
            }

            echo json_encode(['status' => 'success', 'message' => 'Service added successfully.']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

require_once __DIR__ . '/serviceManagement.html';