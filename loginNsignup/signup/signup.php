<?php
require __DIR__ . '/../../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: signup.html');
    exit;
}

/* ---------- Collect + sanitize ---------- */
$firstName             = trim($_POST['firstName'] ?? '');
$lastName              = trim($_POST['lastName'] ?? '');
$email                 = strtolower(trim($_POST['email'] ?? ''));
$phoneNumber           = trim($_POST['phoneNumber'] ?? '');
$IC                    = trim($_POST['IC'] ?? '');
$gender                = trim($_POST['gender'] ?? '');
$dateOfBirth           = trim($_POST['dateOfBirth'] ?? '');
$address               = trim($_POST['address'] ?? '');
$emergencyContactName  = trim($_POST['emergencyContactName'] ?? '');
$emergencyContactPhone = trim($_POST['emergencyContactPhone'] ?? '');
$password              = $_POST['password'] ?? '';
$confirmPassword       = $_POST['confirmPassword'] ?? '';

/* ---------- Required fields ---------- */
if ($firstName === '' || $lastName === '' || $email === '' ||
    $phoneNumber === '' || $emergencyContactName === '' ||
    $emergencyContactPhone === '' || $password === '') {
    header('Location: signup.html?error=empty');
    exit;
}

/* ---------- Email format ---------- */
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: signup.html?error=invalid');
    exit;
}

/* ---------- Block reserved domains ---------- */
if (str_ends_with($email, '@nurse.stepcare.com') ||
    str_ends_with($email, '@admin.stepcare.com')) {
    header('Location: signup.html?error=reserved');
    exit;
}

/* ---------- Password policy ---------- */
if (strlen($password) < 8) {
    header('Location: signup.html?error=short');
    exit;
}
if (!preg_match('/[a-zA-Z]/', $password) ||
    !preg_match('/\d/',       $password) ||
    !preg_match('/[^a-zA-Z0-9]/', $password)) {
    header('Location: signup.html?error=weak');
    exit;
}
if ($password !== $confirmPassword) {
    header('Location: signup.html?error=mismatch');
    exit;
}

/* ---------- Email already taken? ---------- */
$stmt = $conn->prepare('SELECT userID FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    header('Location: signup.html?error=exists');
    exit;
}
$stmt->close();

/* ---------- Hash password ---------- */
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

/* ---------- Insert ---------- */
$stmt = $conn->prepare(
    'INSERT INTO users
        (firstName, lastName, email, phoneNumber, IC, gender, dateOfBirth,
         address, emergencyContactName, emergencyContactPhone, passwordHash)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

/* Handle nullable fields (empty strings → NULL) */
$IC          = $IC          !== '' ? $IC          : null;
$gender      = $gender      !== '' ? $gender      : null;
$dateOfBirth = $dateOfBirth !== '' ? $dateOfBirth : null;
$address     = $address     !== '' ? $address     : null;

$stmt->bind_param(
    'sssssssssss',
    $firstName,
    $lastName,
    $email,
    $phoneNumber,
    $IC,
    $gender,
    $dateOfBirth,
    $address,
    $emergencyContactName,
    $emergencyContactPhone,
    $passwordHash
);

if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    header('Location: signup.html?error=server');
    exit;
}

$stmt->close();
$conn->close();

/* ---------- Redirect to login with success message ---------- */
header('Location: ../login/login.html?signup=success');
exit;