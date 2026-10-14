<?php
session_start();

// 1. Admin Authentication Check (FIXED)
// Check for 'userID' and ensure the role is 'admin'
if (empty($_SESSION['userID']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../../loginNsignup/login/login.html');
    exit;
}

// 2. Fetch data for the dashboard
$adminName = htmlspecialchars($_SESSION['firstName'] ?? 'Admin');
$totalUsers = 1248;
$activeServices = 12;
$careLogsToday = 34;
$avgRating = "4.85";

// 3. Pass data to JavaScript
$dashboardData = [
    'totalUsers' => $totalUsers,
    'activeServices' => $activeServices,
    'careLogsToday' => $careLogsToday,
    'avgRating' => $avgRating
];
$dashboardJson = json_encode($dashboardData);

// 4. Load the View
include __DIR__ . '/dashboard.html';
?>