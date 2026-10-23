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
    $userPhoto = '/StepCare/images/default-user.png';
}

/* ---- Current page (for highlighting the nav link) ---- */
if (!isset($currentPage)) {
    $currentPage = basename($_SERVER['PHP_SELF'], '.php');
}

/* ---- Counts for badges (only if logged in and DB available) ---- */
$pendingAppointments = 0;
$unreadNotifications = 0;
$recentNotifications = []; // NEW: Holds the actual list of notifications

if ($isLoggedIn && isset($pdo)) {

    /* --- Pending appointments --- */
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
               FROM appointments
              WHERE userID = ?
                AND appointmentStatus = 'pending'"
        );
        $stmt->execute([$_SESSION['userID']]);
        $pendingAppointments = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        error_log('Header pendingAppointments error: ' . $e->getMessage());
    }

    /* --- Unread notifications count --- */
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
               FROM notifications
              WHERE recipientType = 'user'
                AND recipientID   = ?
                AND isRead        = 0"
        );
        $stmt->execute([$_SESSION['userID']]);
        $unreadNotifications = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        error_log('Header unreadNotifications error: ' . $e->getMessage());
    }

    /* --- NEW: Fetch the latest 5 notifications for the dropdown --- */
    try {
        $stmt = $pdo->prepare(
            "SELECT notificationID, title, message, linkURL, isRead, createdAt
               FROM notifications
              WHERE recipientType = 'user'
                AND recipientID   = ?
              ORDER BY createdAt DESC
              LIMIT 5"
        );
        $stmt->execute([$_SESSION['userID']]);
        $recentNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Header recentNotifications error: ' . $e->getMessage());
    }
}

/* ---- Hand off to the view ---- */
include __DIR__ . '/header.html';
?>