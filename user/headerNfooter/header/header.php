<?php
// user/headerNfooter/header.php
// - Starts session
// - Prepares variables for the template
// - Includes header.html

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ---------- Logged-in state ---------- */
$isLoggedIn = !empty($_SESSION['userID']);

/* ---------- User data (with fallbacks) ---------- */
$firstName = $_SESSION['firstName'] ?? '';
$userPhoto = $_SESSION['userPhotoURL'] ?? '';

if ($userPhoto === '' || $userPhoto === null) {
    $userPhoto = '../../images/default-user.png';
}

/* ---------- Which nav link is active? ---------- */
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

/* ---------- Hand off to the template ---------- */
include __DIR__ . '/header.html';