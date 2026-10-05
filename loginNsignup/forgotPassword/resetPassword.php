<?php
require __DIR__ . '/../../config/db_connect.php';

$rawToken = $_GET['token'] ?? '';

/* No token at all → send them back to the request page */
if ($rawToken === '') {
    header('Location: forgotPassword.html?error=missing_token');
    exit;
}

$hash = hash('sha256', $rawToken);

$stmt = $pdo->prepare(
    'SELECT resetID, userID, expiresAt, usedAt
     FROM passwordResets
     WHERE tokenHash = ?
     LIMIT 1'
);
$stmt->execute([$hash]);
$row = $stmt->fetch();

/* Decide the outcome and forward to the HTML */
if (!$row) {
    header('Location: resetPassword.html?error=invalid');
    exit;
}
if ($row['usedAt']) {
    header('Location: resetPassword.html?error=used');
    exit;
}
if (strtotime($row['expiresAt']) < time()) {
    header('Location: resetPassword.html?error=expired');
    exit;
}

/* Valid → pass the token along to the HTML form */
header('Location: resetPassword.html?token=' . urlencode($rawToken));
exit;