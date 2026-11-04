<?php
// English comments only
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db_connect.php';

// Strict session retrieval: check both session and query params
$sessionRole = $_SESSION['role'] ?? null;
$queryRole   = $_GET['role'] ?? null;
$adminId     = $_SESSION['adminID'] ?? $_SESSION['user_id'] ?? null;
$nurseId     = $_SESSION['nurseID'] ?? null;

// Determine role strictly from session or explicit request
$role = $sessionRole ?: $queryRole;

// Determine target authenticated ID strictly
$authenticatedId = null;
if ($role === 'nurse') {
    $authenticatedId = $nurseId;
} else {
    // Admin role
    $authenticatedId = $adminId;
}

// If no valid session exists, return unauthenticated status immediately (do not grab arbitrary records)
if (!$authenticatedId) {
    http_response_code(401);
    echo json_encode([
        'status' => 'unauthenticated',
        'message' => 'No active user session found. Please log in.'
    ]);
    exit;
}

try {
    if ($role === 'nurse') {
        $stmt = $pdo->prepare("SELECT nurseID AS id, firstName, lastName, email, nursePhotoURL AS photoURL FROM nurses WHERE nurseID = ? LIMIT 1");
        $stmt->execute([$authenticatedId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $profileLink = '/StepCare/nurse/profile/nurse-profile.html';
        $settingsLink = '/StepCare/nurse/settings/nurse-settings.html';
    } else {
        $stmt = $pdo->prepare("SELECT adminID AS id, firstName, lastName, email, adminPhotoURL AS photoURL FROM admins WHERE adminID = ? LIMIT 1");
        $stmt->execute([$authenticatedId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $profileLink = '/StepCare/admin/profile/admin-profile.html';
        $settingsLink = '/StepCare/admin/settings/admin-settings.html';
    }

    if ($user) {
        // Build clean full name
        $fullName = trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''));

        echo json_encode([
            'status' => 'success',
            'role' => $role,
            'user' => [
                'id' => $user['id'],
                'fullName' => $fullName ?: 'Authenticated User',
                'email' => $user['email'] ?? '',
                'photoURL' => $user['photoURL'] ?? null,
                'profileLink' => $profileLink,
                'settingsLink' => $settingsLink
            ]
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'User account not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>