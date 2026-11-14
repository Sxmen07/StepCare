<?php
/* user/services/saveBooking.php
   Creates appointment + pending payment atomically.
   ALWAYS returns JSON – never HTML. */

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

/* ---------------- helpers ---------------- */
function jsonOut(array $payload, int $status = 200): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
    }
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    echo ($json === false) ? '{"success":false,"message":"Server encoding error."}' : $json;
    exit;
}

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['success' => false, 'message' => 'Server error. Please try again.'], JSON_INVALID_UTF8_SUBSTITUTE);
    }
});

try {
    require __DIR__ . '/../../config/db_connect.php';

    if (empty($_SESSION['userID'])) {
        jsonOut(['success' => false, 'message' => 'Session expired. Please log in again.'], 401);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        jsonOut(['success' => false, 'message' => 'Invalid request method.'], 405);
    }

    $userID      = (int)$_SESSION['userID'];
    $serviceID   = (int)($_POST['serviceID'] ?? 0);
    $startDate   = trim($_POST['startDate'] ?? '');
    $startTime   = trim($_POST['startTime'] ?? '');
    $patientName = trim($_POST['patientName'] ?? '');
    $location    = trim($_POST['location'] ?? '');
    $area        = trim($_POST['area'] ?? '');
    $postcode    = trim($_POST['postcode'] ?? '');
    $notes       = trim($_POST['specialInstructions'] ?? '');

    $errors = [];
    if ($serviceID <= 0)     $errors[] = 'Invalid service.';
    if ($startDate === '')   $errors[] = 'Please choose a date.';
    if ($startTime === '')   $errors[] = 'Please choose a valid time slot.';
    if ($patientName === '') $errors[] = 'Recipient name is required.';
    if ($location === '')    $errors[] = 'Street / building is required.';
    if ($area === '')        $errors[] = 'Area is required.';
    if ($errors) {
        jsonOut(['success' => false, 'message' => implode(' ', $errors)], 422);
    }

    $timeDt = DateTime::createFromFormat('H:i:s', $startTime) ?: DateTime::createFromFormat('H:i', $startTime);
    if (!$timeDt) {
        jsonOut(['success' => false, 'message' => 'Invalid time slot format.'], 422);
    }
    $normalizedStartTime = $timeDt->format('H:i:s');

    $slotStmt = $pdo->prepare("SELECT slotID, timeSlotLabel, maxCapacity FROM serviceTimeSlots WHERE serviceID = ? AND isAvailable = 1");
    $slotStmt->execute([$serviceID]);

    $matchedSlotID = null;
    $maxCapacity   = 5;

    foreach ($slotStmt->fetchAll(PDO::FETCH_ASSOC) as $slot) {
        $label = trim($slot['timeSlotLabel']);
        $dt = DateTime::createFromFormat('g:i A', $label) ?: DateTime::createFromFormat('H:i:s', $label) ?: DateTime::createFromFormat('H:i', $label);
        if (!$dt) {
            try { $dt = new DateTime($label); } catch (Throwable $ex) { continue; }
        }
        if ($dt->format('H:i:s') === $normalizedStartTime) {
            $matchedSlotID = (int)$slot['slotID'];
            $maxCapacity   = (int)($slot['maxCapacity'] ?: 5);
            break;
        }
    }
    if ($matchedSlotID === null) {
        jsonOut(['success' => false, 'message' => 'Please choose a valid time slot.'], 422);
    }

    $startDateTime = date('Y-m-d H:i:s', strtotime("$startDate $normalizedStartTime"));
    if (strtotime($startDateTime) < time()) {
        jsonOut(['success' => false, 'message' => 'That time has already passed.'], 422);
    }

    $stmt = $pdo->prepare('SELECT serviceID, serviceName, price, price_unit, durationMin FROM services WHERE serviceID = ?');
    $stmt->execute([$serviceID]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$service) {
        jsonOut(['success' => false, 'message' => 'Service not found.'], 404);
    }

    $totalNurses = (int)$pdo->query("SELECT COUNT(*) FROM nurses WHERE isVerified = 1")->fetchColumn();
    if ($totalNurses <= 0) $totalNurses = 3;
    $slotLimit = max(1, min($maxCapacity, $totalNurses));

    $address = $location;
    if ($area !== '')     $address .= ', ' . $area;
    if ($postcode !== '') $address .= ', ' . $postcode;
    $address .= ' Penang';

    $durationMin = (int)($service['durationMin'] ?: 60);
    $endDateTime = date('Y-m-d H:i:s', strtotime("$startDateTime +$durationMin minutes"));

    /* ================= ATOMIC RESERVATION ================= */
    $pdo->beginTransaction();

    // 1. Lock this slot row
    $pdo->prepare("SELECT slotID FROM serviceTimeSlots WHERE slotID = ? FOR UPDATE")->execute([$matchedSlotID]);

    // 2. CLEANUP: Delete this user's previous abandoned pending booking for THIS exact slot.
    // (This happens if they clicked "Continue to Payment" earlier, then clicked "Back" to fix a typo)
    $delOld = $pdo->prepare(
        "DELETE a FROM appointments a
         JOIN payments p ON p.appointmentID = a.appointmentID
         WHERE a.userID = ? AND a.serviceID = ? AND DATE(a.startDateTime) = ? AND TIME(a.startDateTime) = ?
           AND a.appointmentStatus = 'pending' AND p.paymentStatus = 'pending'"
    );
    $delOld->execute([$userID, $serviceID, $startDate, $normalizedStartTime]);

    // 3. Count current bookings
    $cntStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM appointments
         WHERE serviceID = ? AND DATE(startDateTime) = ? AND TIME(startDateTime) = ?
           AND appointmentStatus IN ('pending','confirmed','in_progress')"
    );
    $cntStmt->execute([$serviceID, $startDate, $normalizedStartTime]);
    $bookedCount = (int)$cntStmt->fetchColumn();

    if ($bookedCount >= $slotLimit) {
        $pdo->rollBack();
        jsonOut(['success' => false, 'message' => 'Sorry, that slot is currently held by another user. Please pick another time.'], 409);
    }

    // 4. Create appointment
    $insAppt = $pdo->prepare(
        'INSERT INTO appointments
            (serviceID, userID, patientName, startDateTime, endDateTime, locationAddress, specialInstructions, appointmentStatus)
         VALUES (?, ?, ?, ?, ?, ?, ?, "pending")'
    );
    $insAppt->execute([$serviceID, $userID, $patientName, $startDateTime, $endDateTime, $address, $notes !== '' ? $notes : null]);
    $appointmentID = (int)$pdo->lastInsertId();

    // 5. Create pending payment
    $insPay = $pdo->prepare("INSERT INTO payments (appointmentID, totalAmount, paymentStatus) VALUES (?, ?, 'pending')");
    $insPay->execute([$appointmentID, $service['price']]);

    $pdo->commit();

    jsonOut([
        'success'       => true,
        'appointmentID' => $appointmentID,
        'reference'     => '#SC' . str_pad((string)$appointmentID, 6, '0', STR_PAD_LEFT),
        'prettyDate'    => date('D, d M Y', strtotime($startDateTime)),
        'prettyTime'    => date('g:i A', strtotime($startDateTime)),
        'serviceName'   => $service['serviceName'],
        'price'         => number_format((float)$service['price'], 2)
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO) {
        try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (Throwable $ignore) {}
    }
    error_log('[saveBooking] ' . $e->getMessage());
    jsonOut(['success' => false, 'message' => 'We could not complete your booking. Please try again.'], 500);
}