<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../config/db_connect.php';

$response = [
    'success' => false,
    'admin' => [
        'name' => 'Admin'
    ],
    'metrics' => [
        'total_revenue'       => '0.00',
        'active_appointments' => 0,
        'total_users'         => 0,
        'pending_nurses'      => 0
    ],
    'recent_appointments' => [],
    'pending_leaves'      => [],
    'pending_nurse_list'  => [],
    'message'             => ''
];

try {
    // 1. 获取默认 Admin 名字 (优先取最新插入的 admin)
    $resAdmin = $conn->query("SELECT firstName, lastName FROM admins ORDER BY adminID DESC LIMIT 1");
    if ($resAdmin && $rowAdmin = $resAdmin->fetch_assoc()) {
        $response['admin']['name'] = trim($rowAdmin['firstName'] . ' ' . $rowAdmin['lastName']);
    }

    // 2. 指标统计
    // 2.1 总收入 (已支付)
    $resRev = $conn->query("SELECT IFNULL(SUM(totalAmount), 0) AS rev FROM payments WHERE paymentStatus = 'paid'");
    if ($resRev) $response['metrics']['total_revenue'] = number_format($resRev->fetch_assoc()['rev'], 2);

    // 2.2 活跃预约数 (待处理、已确认、进行中)
    $resAppt = $conn->query("SELECT COUNT(*) AS total FROM appointments WHERE appointmentStatus IN ('pending', 'confirmed', 'in_progress')");
    if ($resAppt) $response['metrics']['active_appointments'] = (int)$resAppt->fetch_assoc()['total'];

    // 2.3 注册普通用户总数
    $resUsers = $conn->query("SELECT COUNT(*) AS total FROM users");
    if ($resUsers) $response['metrics']['total_users'] = (int)$resUsers->fetch_assoc()['total'];

    // 2.4 待核准护士总数
    $resNurses = $conn->query("SELECT COUNT(*) AS total FROM nurses WHERE isVerified = 0");
    if ($resNurses) $response['metrics']['pending_nurses'] = (int)$resNurses->fetch_assoc()['total'];

    // 3. 中间大卡片：近期预约记录 (最多 8 笔)
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

    // 4. 右侧 Sticky 卡片：待处理护士请假申请
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

    // 5. 右侧 Sticky 卡片：待核准护士列表
    $nurseVerifSql = "SELECT nurseID, firstName, lastName, specialization, createdAt 
                      FROM nurses 
                      WHERE isVerified = 0 
                      ORDER BY createdAt DESC LIMIT 4";
    $resVerif = $conn->query($nurseVerifSql);
    if ($resVerif) {
        while ($row = $resVerif->fetch_assoc()) {
            $response['pending_nurse_list'][] = $row;
        }
    }

    $response['success'] = true;
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;