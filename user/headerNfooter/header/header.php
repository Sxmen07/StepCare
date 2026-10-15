<?php
// user/headerNfooter/header/header.php
// Role: prepare variables, then hand off to the view.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ---- Logged-in state ---- */
$isLoggedIn = !empty($_SESSION['userID']);

/* ---- User data (with fallbacks) ---- */
$firstName = $_SESSION['firstName']    ?? '';
$userPhoto = $_SESSION['userPhotoURL'] ?? '';
if ($userPhoto === '') {
    $userPhoto = '../../images/default-user.png';
}

/* ---- Current page (for highlighting the nav link) ---- */
if (!isset($currentPage)) {
    $currentPage = basename($_SERVER['PHP_SELF'], '.php');
}

/* ---- Counts for badges (only if logged in and DB available) ---- */
$pendingAppointments = 0;
$unreadNotifications = 0;

if ($isLoggedIn && isset($pdo)) {
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM appointments
             WHERE userID = ? AND appointmentStatus = 'pending'"
        );
        $stmt->execute([$_SESSION['userID']]);
        $pendingAppointments = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM notifications
             WHERE userID = ? AND isRead = 0"
        );
        $stmt->execute([$_SESSION['userID']]);
        $unreadNotifications = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        // Fail silently — badges just won't show
        error_log('Header counts error: ' . $e->getMessage());
    }
}

/* ---- Hand off to the view ---- */
include __DIR__ . '/header.html';