<?php
// English comments only
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../config/db_connect.php';

$response = [
    'success' => false,
    'admin' => [
        'name' => 'Admin'
    ],
    'metrics' => [
        'total_revenue'        => '0.00',
        'active_appointments'  => 0,
        'total_users'          => 0,
        'unscheduled_bookings' => 0
    ],
    'recent_appointments'  => [],
    'pending_leaves'       => [],
    'unscheduled_list'     => [],
    'message'              => ''
];

try {
    // 1. Fetch latest active admin name
    $resAdmin = $conn->query("SELECT firstName, lastName FROM admins ORDER BY adminID DESC LIMIT 1");
    if ($resAdmin && $rowAdmin = $resAdmin->fetch_assoc()) {
        $response['admin']['name'] = trim(($rowAdmin['firstName'] ?? '') . ' ' . ($rowAdmin['lastName'] ?? ''));
    }

    // 2. Metrics Calculation
    // 2.1 Total paid revenue
    $resRev = $conn->query("SELECT IFNULL(SUM(totalAmount), 0) AS rev FROM payments WHERE paymentStatus = 'paid'");
    if ($resRev) $response['metrics']['total_revenue'] = number_format($resRev->fetch_assoc()['rev'], 2);

    // 2.2 Active appointments count
    $resAppt = $conn->query("SELECT COUNT(*) AS total FROM appointments WHERE appointmentStatus IN ('pending', 'confirmed', 'in_progress')");
    if ($resAppt) $response['metrics']['active_appointments'] = (int)$resAppt->fetch_assoc()['total'];

    // 2.3 Registered users count
    $resUsers = $conn->query("SELECT COUNT(*) AS total FROM users");
    if ($resUsers) $response['metrics']['total_users'] = (int)$resUsers->fetch_assoc()['total'];

    // 2.4 Unscheduled bookings (Pending and without an assigned nurse)
    $resUnscheduled = $conn->query("SELECT COUNT(*) AS total FROM appointments WHERE (nurseID IS NULL OR nurseID = 0) AND appointmentStatus != 'cancelled'");
    if ($resUnscheduled) $response['metrics']['unscheduled_bookings'] = (int)$resUnscheduled->fetch_assoc()['total'];

    // 3. Center Workspace: Recent 8 appointments
    $apptSql = "SELECT a.appointmentID, a.startDateTime, a.appointmentStatus, 
                       s.serviceName, s.price,
                       CONCAT(u.firstName, ' ', u.lastName) AS userName,
                       CONCAT(IFNULL(n.firstName, 'Unassigned'), ' ', IFNULL(n.lastName, '')) AS nurseName
                FROM appointments a
                JOIN services s ON a.serviceID = s.serviceID
                JOIN users u ON a.userID = u.userID
                LEFT JOIN nurses n ON a.nurseID = n.nurseID
                ORDER BY a.createdAt DESC LIMIT 8";
    $resRecent = $conn->query($apptSql);
    if ($resRecent) {
        while ($row = $resRecent->fetch_assoc()) {
            $response['recent_appointments'][] = $row;
        }
    }

    // 4. Action Queue: Pending Nurse Leave Applications
    $leaveSql = "SELECT r.requestID, r.requestDate, r.reason,
                        n.firstName, n.lastName
                 FROM nurseRequestOffDay r 
                 JOIN nurses n ON r.nurseID = n.nurseID 
                 WHERE r.status = 'pending' 
                 ORDER BY r.createdAt DESC LIMIT 4";
    $resLeaves = $conn->query($leaveSql);
    if ($resLeaves) {
        while ($row = $resLeaves->fetch_assoc()) {
            $response['pending_leaves'][] = $row;
        }
    }

    // 5. Action Queue: Unscheduled Bookings Queue
    $unscheduledSql = "SELECT a.appointmentID, a.createdAt, s.serviceName, 
                              CONCAT(u.firstName, ' ', u.lastName) AS userName
                       FROM appointments a
                       JOIN services s ON a.serviceID = s.serviceID
                       JOIN users u ON a.userID = u.userID
                       WHERE (a.nurseID IS NULL OR a.nurseID = 0) AND a.appointmentStatus != 'cancelled'
                       ORDER BY a.createdAt DESC LIMIT 4";
    $resUnscheduledList = $conn->query($unscheduledSql);
    if ($resUnscheduledList) {
        while ($row = $resUnscheduledList->fetch_assoc()) {
            $response['unscheduled_list'][] = $row;
        }
    }

    $response['success'] = true;
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;