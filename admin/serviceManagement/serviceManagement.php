<?php
// StepCare - Service Management Entry & Backend Controller
ini_set('display_errors', 0);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/../../config/db_connect.php';

// 1. Super Admin Access Check
$currentAdminId = (int)($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? $_SESSION['adminID'] ?? 1);

function checkSuperAdmin($pdo, $adminId) {
    if ($adminId === 1) return true; // Fallback for default super admin
    try {
        $stmt = $pdo->prepare("SELECT adminRole FROM admins WHERE adminID = ?");
        $stmt->execute([$adminId]);
        $role = strtolower(trim((string)$stmt->fetchColumn()));
        if ($role === 'super admin' || $role === 'superadmin') return true;
    } catch (Exception $e) {}
    return false;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$isAjax = !empty($action);

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    if (!checkSuperAdmin($pdo, $currentAdminId)) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Super Admin access required.']);
        exit;
    }
} else {
    if (!checkSuperAdmin($pdo, $currentAdminId)) {
        header('Location: /StepCare/admin/dashboard/admin-dashboard.html');
        exit;
    }
}

// 2. Fetch Services Logic
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

            // Time Slots (Inner Page with Capacity)
            $slotStmt = $pdo->prepare("SELECT timeSlotLabel, maxCapacity, currentBookings FROM serviceTimeSlots WHERE serviceID = ? ORDER BY slotID ASC");
            $slotStmt->execute([$serviceId]);
            $service['timeSlots'] = $slotStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode(['status' => 'success', 'data' => $services]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// 3. Handle Add or Edit Form Submission with Buffer Overlap Validation & Capacity Limits
if ($action === 'save_service') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $serviceID = $_POST['serviceID'] ?? '';
        $serviceName = $_POST['serviceName'] ?? '';
        $category = $_POST['category'] ?? '';
        $price = $_POST['price'] ?? 0;
        $durationMin = $_POST['durationMin'] ?? 45;
        $durationMax = (int)($_POST['durationMax'] ?? 60);
        $description = $_POST['description'] ?? ''; 
        $features = json_decode($_POST['features'] ?? '[]', true); 
        $timeSlotsRaw = json_decode($_POST['timeSlots'] ?? '[]', true); 

        // Validate time slot buffer overlaps (+1 hour front and back buffer check)
        $validatedSlots = [];
        $slotRanges = [];

        foreach ($timeSlotsRaw as $slotObj) {
            $slotTime = is_array($slotObj) ? ($slotObj['label'] ?? '') : trim($slotObj);
            $maxCap = is_array($slotObj) ? (int)($slotObj['capacity'] ?? 5) : 5;

            if (empty(trim($slotTime))) continue;

            $dateTime = DateTime::createFromFormat('H:i', $slotTime) ?: DateTime::createFromFormat('g:i A', $slotTime);
            if (!$dateTime) {
                $dateTime = new DateTime($slotTime);
            }
            if (!$dateTime) continue;

            $startMins = ((int)$dateTime->format('H') * 60) + (int)$dateTime->format('i');
            
            // Front buffer: -60 mins, Back buffer: +durationMax + 60 mins
            $bufferStart = max(0, $startMins - 60);
            $bufferEnd = $startMins + $durationMax + 60;

            foreach ($slotRanges as $existing) {
                if ($bufferStart < $existing['end'] && $bufferEnd > $existing['start']) {
                    echo json_encode([
                        'status' => 'error', 
                        'message' => "Time slot '{$slotTime}' violates buffer rules by overlapping another slot's 1-hour front/back buffer window."
                    ]);
                    exit;
                }
            }

            $slotRanges[] = ['start' => $bufferStart, 'end' => $bufferEnd];
            $validatedSlots[] = [
                'label' => $dateTime->format('g:i A'),
                'capacity' => $maxCap
            ];
        }

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

            // Refresh Time Slots with Capacity Limits
            $pdo->prepare("DELETE FROM serviceTimeSlots WHERE serviceID=?")->execute([$serviceID]);
            $slotStmt = $pdo->prepare("INSERT INTO serviceTimeSlots (serviceID, timeSlotLabel, maxCapacity) VALUES (?, ?, ?)");
            foreach ($validatedSlots as $s) {
                $slotStmt->execute([$serviceID, $s['label'], $s['capacity']]);
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

            // Insert Time Slots with Capacity Limits
            $slotStmt = $pdo->prepare("INSERT INTO serviceTimeSlots (serviceID, timeSlotLabel, maxCapacity) VALUES (?, ?, ?)");
            foreach ($validatedSlots as $s) {
                $slotStmt->execute([$newID, $s['label'], $s['capacity']]);
            }

            echo json_encode(['status' => 'success', 'message' => 'Service added successfully.']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

require_once __DIR__ . '/serviceManagement.html';
?>