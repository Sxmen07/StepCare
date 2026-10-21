<?php
require __DIR__ . '/../../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.html?mode=signup');
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

/* ---------- Check Terms & Conditions ---------- */
if (!isset($_POST['terms']) || $_POST['terms'] !== 'on') {
    header('Location: login.html?mode=signup&error=terms');
    exit;
}

/* ---------- Custom Validations ---------- */
$nameRegex = '/^[A-Za-z\s]+$/';
$phoneRegex = '/^\d+$/';

// First & Last Name check (no numbers)
if (!preg_match($nameRegex, $firstName) || !preg_match($nameRegex, $lastName)) {
    header('Location: login.html?mode=signup&error=invalid_name');
    exit;
}

// Email must end with @gmail.com
if (!str_ends_with($email, '@gmail.com')) {
    header('Location: login.html?mode=signup&error=gmail_only');
    exit;
}

// Phone number numeric check (no dashes)
if (!preg_match($phoneRegex, $phoneNumber)) {
    header('Location: login.html?mode=signup&error=invalid_phone');
    exit;
}

// IC validation (12 digits numeric if provided)
if ($IC !== '' && !preg_match('/^\d{12}$/', $IC)) {
    header('Location: login.html?mode=signup&error=invalid_ic');
    exit;
}

// Address check (cannot be all numbers)
if ($address !== '' && (ctype_digit($address) || !preg_match('/[A-Za-z]/', $address))) {
    header('Location: login.html?mode=signup&error=invalid_address');
    exit;
}

// Emergency Contact Name check (text only)
if (!preg_match($nameRegex, $emergencyContactName)) {
    header('Location: login.html?mode=signup&error=invalid_emergency_name');
    exit;
}

// Emergency Contact Phone check (numeric only)
if (!preg_match($phoneRegex, $emergencyContactPhone)) {
    header('Location: login.html?mode=signup&error=invalid_emergency_phone');
    exit;
}

/* ---------- Required fields ---------- */
if ($firstName === '' || $lastName === '' || $email === '' ||
    $phoneNumber === '' || $emergencyContactName === '' ||
    $emergencyContactPhone === '' || $password === '') {
    header('Location: login.html?mode=signup&error=empty');
    exit;
}

/* ---------- Email format ---------- */
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: login.html?mode=signup&error=invalid');
    exit;
}

/* ---------- Block reserved domains ---------- */
if (str_ends_with($email, '@nurse.stepcare.com') ||
    str_ends_with($email, '@admin.stepcare.com')) {
    header('Location: login.html?mode=signup&error=reserved');
    exit;
}

/* ---------- Password policy ---------- */
if (strlen($password) < 8) {
    header('Location: login.html?mode=signup&error=short');
    exit;
}
if (!preg_match('/[a-zA-Z]/', $password) ||
    !preg_match('/\d/',       $password) ||
    !preg_match('/[^a-zA-Z0-9]/', $password)) {
    header('Location: login.html?mode=signup&error=weak');
    exit;
}
if ($password !== $confirmPassword) {
    header('Location: login.html?mode=signup&error=mismatch');
    exit;
}

/* ---------- Email already taken? ---------- */
$stmt = $conn->prepare('SELECT userID FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    header('Location: login.html?mode=signup&error=exists');
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
    header('Location: login.html?mode=signup&error=server');
    exit;
}

$stmt->close();
$conn->close();

/* ---------- Redirect to login with success message ---------- */
header('Location: login.html?signup=success');
exit;
?>