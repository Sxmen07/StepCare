<?php
require __DIR__ . '/../../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forgotPassword.html');
    exit;
}

$rawToken = $_POST['token'] ?? '';
$password = $_POST['password'] ?? '';
$confirm  = $_POST['confirmPassword'] ?? '';

if ($rawToken === '' || strlen($password) < 8 || $password !== $confirm) {
    header('Location: forgotPassword.html?error=bad_input');
    exit;
}

$hash = hash('sha256', $rawToken);

$pdo->beginTransaction();
try {
    /* ---- Lock and re-verify the token ---- */
    $stmt = $pdo->prepare(
        'SELECT resetID, userID, expiresAt, usedAt
         FROM passwordResets
         WHERE tokenHash = ?
         LIMIT 1
         FOR UPDATE'
    );
    $stmt->execute([$hash]);
    $row = $stmt->fetch();

    if (!$row || $row['usedAt'] || strtotime($row['expiresAt']) < time()) {
        $pdo->rollBack();
        header('Location: forgotPassword.html?error=expired');
        exit;
    }

    /* ---- Update password ---- */
    $newHash = password_hash($password, PASSWORD_DEFAULT);

    $pdo->prepare(
        'UPDATE users
         SET passwordHash = ?,
             failedAttempt = 0,
             lockedUntil   = NULL
         WHERE userID = ?'
    )->execute([$newHash, $row['userID']]);

    /* ---- Mark token as used ---- */
    $pdo->prepare('UPDATE passwordResets SET usedAt = NOW() WHERE resetID = ?')
        ->execute([$row['resetID']]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Reset error: ' . $e->getMessage());
    header('Location: forgotPassword.html?error=server');
    exit;
}

header('Location: ../login/login.html?reset=success');
exit;