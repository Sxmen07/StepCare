<?php
// English comments only
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db_connect.php';

// Detect role explicitly from session or request query
$role = $_SESSION['role'] ?? $_GET['role'] ?? null;
$adminId = $_SESSION['adminID'] ?? null;
$nurseId = $_SESSION['nurseID'] ?? null;

// Determine role based on session presence if not explicitly declared
if (!$role) {
    if ($adminId) {
        $role = 'admin';
    } elseif ($nurseId) {
        $role = 'nurse';
    } else {
        // Fallback default based on client hint or primary admin
        $role = 'admin';
    }
}

try {
    if ($role === 'nurse') {
        // Fetch from nurses table
        $targetId = $nurseId ?? 1;
        $stmt = $pdo->prepare("SELECT nurseID AS id, firstName, lastName, email, nursePhotoURL AS photoURL FROM nurses WHERE nurseID = ? LIMIT 1");
        $stmt->execute([$targetId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $stmt = $pdo->query("SELECT nurseID AS id, firstName, lastName, email, nursePhotoURL AS photoURL FROM nurses ORDER BY nurseID ASC LIMIT 1");
            $user = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        }

        $profileLink = '/StepCare/nurse/profile/nurse-profile.html';
        $settingsLink = '/StepCare/nurse/settings/nurse-settings.html';
    } else {
        // Fetch from admins table
        $targetId = $adminId ?? 1;
        $stmt = $pdo->prepare("SELECT adminID AS id, firstName, lastName, email, adminPhotoURL AS photoURL FROM admins WHERE adminID = ? LIMIT 1");
        $stmt->execute([$targetId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $stmt = $pdo->query("SELECT adminID AS id, firstName, lastName, email, adminPhotoURL AS photoURL FROM admins ORDER BY adminID ASC LIMIT 1");
            $user = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        }

        $profileLink = '/StepCare/admin/profile/admin-profile.html';
        $settingsLink = '/StepCare/admin/settings/admin-settings.html';
    }

    if ($user) {
        echo json_encode([
            'status' => 'success',
            'role' => $role,
            'user' => [
                'id' => $user['id'],
                'fullName' => trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? '')),
                'email' => $user['email'] ?? '',
                'photoURL' => $user['photoURL'] ?? null,
                'profileLink' => $profileLink,
                'settingsLink' => $settingsLink
            ]
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No active user found']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>