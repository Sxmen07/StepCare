<?php
session_start();
header('Content-Type: application/json');

// 根据你的实际项目路径连接数据库
require_once '../../config/db.php'; 

// 确认当前是 admin（若 session 未登录可降级取 admin 默认账号）
$admin_id = $_SESSION['user_id'] ?? 1;

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_profile':
        getAdminProfile($pdo, $admin_id);
        break;

    case 'get_appointment_slots':
        getAppointmentSlots($pdo);
        break;

    case 'update_profile':
        updateAdminProfile($pdo, $admin_id);
        break;

    case 'upload_avatar':
        uploadAdminAvatar($pdo, $admin_id);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}

// 1. 获取 Admin 个人信息
function getAdminProfile($pdo, $admin_id) {
    $stmt = $pdo->prepare("SELECT id, name, email, avatar FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin) {
        echo json_encode(['status' => 'success', 'admin' => $admin]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Admin not found']);
    }
}

// 2. 获取未分配数量及各时段预约列表
function getAppointmentSlots($pdo) {
    // 统计未分配（nurse_id IS NULL 或者 status 为 pending）
    $unassigned_stmt = $pdo->query("SELECT COUNT(*) AS total FROM bookings WHERE nurse_id IS NULL OR status = 'pending'");
    $unassigned_count = $unassigned_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // 获取预约列表及具体 Time Slot
    $query = "SELECT b.id, b.booking_date AS date, b.time_slot, s.service_name,
                     CASE 
                        WHEN b.nurse_id IS NOT NULL AND b.nurse_id != 0 THEN 'assigned'
                        ELSE 'pending'
                     END AS status
              FROM bookings b
              LEFT JOIN services s ON b.service_id = s.id
              ORDER BY b.booking_date DESC, b.time_slot ASC
              LIMIT 30";
    
    $stmt = $pdo->query($query);
    $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'unassigned_count' => (int)$unassigned_count,
        'slots' => $slots
    ]);
}

// 3. 更新资料（只允许改 Name, Email, Password）
function updateAdminProfile($pdo, $admin_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $name = trim($data['name'] ?? '');
    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';

    if (empty($name) || empty($email)) {
        echo json_encode(['status' => 'error', 'message' => 'Name and Email are required']);
        return;
    }

    if (!empty($password)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?");
        $stmt->execute([$name, $email, $hashed, $admin_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
        $stmt->execute([$name, $email, $admin_id]);
    }

    echo json_encode(['status' => 'success']);
}

// 4. 头像上传
function uploadAdminAvatar($pdo, $admin_id) {
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'File upload error']);
        return;
    }

    $uploadDir = '../../images/avatars/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
    $filename = 'admin_' . $admin_id . '_' . time() . '.' . $extension;
    $targetPath = $uploadDir . $filename;
    $dbPath = 'images/avatars/' . $filename;

    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetPath)) {
        $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
        $stmt->execute([$dbPath, $admin_id]);
        echo json_encode(['status' => 'success', 'avatar_url' => $dbPath]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save avatar']);
    }
}
?>