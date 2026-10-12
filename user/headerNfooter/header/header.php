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
// A page can override this by setting $currentPage before including this file.
if (!isset($currentPage)) {
    $currentPage = basename($_SERVER['PHP_SELF'], '.php');
}

/* ---- Hand off to the view ---- */
include __DIR__ . '/header.html';