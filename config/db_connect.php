<?php
// config/db_connect.php

date_default_timezone_set('Asia/Kuala_Lumpur');

$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "stepcare";

/* ---------- mysqli (used by login.php / signup.php) ---------- */
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

/* ---------- PDO (used by the password-reset flow) ---------- */
try {
    $pdo = new PDO(
        "mysql:host=$servername;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('PDO connection failed: ' . $e->getMessage());
}