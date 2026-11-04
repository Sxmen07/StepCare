<?php
// English comments only
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

session_start();
require_once __DIR__ . '/../config/db_connect.php';

$currentAdminId = (int)($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 1);

function checkSuperAdmin($pdo, $adminId) {
    if ($adminId === 1) return true;
    try {
        $stmt = $pdo->prepare("SELECT adminRole FROM admins WHERE adminID = ?");
        $stmt->execute([$adminId]);
        $role = strtolower(trim((string)$stmt->fetchColumn()));
        if ($role === 'super admin' || $role === 'superadmin') return true;
    } catch (Exception $e) {}

    $sessRole = strtolower($_SESSION['role'] ?? $_SESSION['user_role'] ?? $_SESSION['adminRole'] ?? '');
    return ($sessRole === 'super admin' || $sessRole === 'superadmin' || !empty($_SESSION['is_super_admin']));
}

$isSuperAdmin = checkSuperAdmin($pdo, $currentAdminId);

$counts = [
    'verifications' => 0,
    'schedule'      => 0,
    'applications'  => 0,
    'reviews'       => 0,
    'notifications' => 0
];

try {
    // 1. Verifications: Pending client verification/payment
    $stmtVer = $pdo->query("SELECT COUNT(*) FROM appointments WHERE LOWER(status) IN ('pending', 'pending_verification', 'awaiting_payment')");
    if ($stmtVer) $counts['verifications'] = (int)$stmtVer->fetchColumn();
} catch (Exception $e) {}

try {
    // 2. Schedule: Verified appointments without an assigned nurse
    $stmtSch = $pdo->query("SELECT COUNT(*) FROM appointments WHERE LOWER(status) IN ('confirmed', 'verified', 'approved') AND (nurseID IS NULL OR nurseID = 0)");
    if ($stmtSch) $counts['schedule'] = (int)$stmtSch->fetchColumn();
} catch (Exception $e) {}

try {
    // 3. Applications: Genuine pending off-day requests from staff
    $stmtApp = $pdo->query("SELECT COUNT(*) FROM nurseRequestOffDay WHERE LOWER(status) = 'pending'");
    if ($stmtApp) $counts['applications'] = (int)$stmtApp->fetchColumn();
} catch (Exception $e) {}

try {
    // 4. Notifications: Only count unread messages targeted to this admin (exclude self-broadcasts)
    $stmtNotif = $pdo->prepare("
        SELECT COUNT(*) FROM notifications 
        WHERE isRead = 0 
          AND (recipientType IN ('admin', 'all') OR recipientType IS NULL)
          AND (senderID != ? OR senderID IS NULL)
    ");
    $stmtNotif->execute([$currentAdminId]);
    $counts['notifications'] = (int)$stmtNotif->fetchColumn();
} catch (Exception $e) {
    $counts['notifications'] = 0;
}

try {
    // 5. Reviews: Recent low ratings
    $stmtRev = $pdo->query("SELECT COUNT(*) FROM reviews WHERE rating <= 2 AND createdAt >= DATE_SUB(NOW(), INTERVAL 3 DAY)");
    if ($stmtRev) $counts['reviews'] = (int)$stmtRev->fetchColumn();
} catch (Exception $e) {}

echo json_encode([
    'status'         => 'success',
    'is_super_admin' => $isSuperAdmin,
    'counts'         => $counts
]);