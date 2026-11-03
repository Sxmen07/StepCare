<?php
// nurse/appointmentHistory/appointmentHistory.php
session_start();
require __DIR__ . '/../../config/db_connect.php';

// 严格检查护士登录
if (empty($_SESSION['nurseID'])) {
    header('Location: /StepCare/loginNsignup/login/login.html');
    exit;
}

$nurseID = (int)$_SESSION['nurseID'];

// ==========================================
// 1. Fetch History & Stats
// ==========================================
$stmt = $pdo->prepare("
    SELECT 
        a.appointmentID, a.startDateTime, a.endDateTime, a.appointmentStatus,
        s.serviceName, s.category, s.price,
        u.firstName, u.lastName
    FROM appointments a
    JOIN services s ON a.serviceID = s.serviceID
    JOIN users u ON a.userID = u.userID
    WHERE a.nurseID = ? 
      AND a.appointmentStatus IN ('completed', 'cancelled', 'declined')
    ORDER BY a.startDateTime DESC
");
$stmt->execute([$nurseID]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Stats
$stats = [
    'completed' => 0,
    'cancelled' => 0,
    'earnings' => 0
];

foreach ($history as $appt) {
    if ($appt['appointmentStatus'] === 'completed') {
        $stats['completed']++;
        $stats['earnings'] += (float)$appt['price'];
    } else {
        $stats['cancelled']++;
    }
}

// Prepare JSON for JS
$historyJson = json_encode($history, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$statsJson = json_encode($stats);

include __DIR__ . '/appointmentHistory.html';
?>