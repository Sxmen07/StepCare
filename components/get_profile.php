<?php
// components/get_profile.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db_connect.php';

// Retrieve role and ID from query parameters
$role = isset($_GET['role']) ? strtolower(trim($_GET['role'])) : 'admin';
$id   = isset($_GET['id'])   ? intval($_GET['id']) : 1;

try {
    if ($role === 'admin') {
        $stmt = $pdo->prepare("SELECT adminID AS id, firstName, lastName, email, adminPhotoURL AS photoURL, createdAt FROM admins WHERE adminID = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        $roleTitle = 'Administrator';
    } elseif ($role === 'nurse') {
        $stmt = $pdo->prepare("SELECT nurseID AS id, firstName, lastName, email, nursePhotoURL AS photoURL, phoneNumber, specialization, bio, averageRating, isVerified, createdAt FROM nurses WHERE nurseID = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        $roleTitle = 'Registered Nurse';
    } elseif ($role === 'user') {
        $stmt = $pdo->prepare("SELECT userID AS id, firstName, lastName, email, userPhotoURL AS photoURL, IC, phoneNumber, address, emergencyContactName, emergencyContactPhone, createdAt FROM users WHERE userID = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        $roleTitle = 'Family / Patient';
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid role specified']);
        exit;
    }

    if ($data) {
        echo json_encode([
            'success'   => true,
            'role'      => $role,
            'roleTitle' => $roleTitle,
            'profile'   => $data
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}