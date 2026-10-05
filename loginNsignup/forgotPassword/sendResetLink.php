<?php
require __DIR__ . '/../../config/db_connect.php';
require __DIR__ . '/../../auth/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forgotPassword.html');
    exit;
}

$email = strtolower(trim($_POST['email'] ?? ''));

/* ---- Always show the same response (prevents email enumeration) ---- */
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: forgotPassword.html?sent=1');
    exit;
}

/* ---- Look up user (only users table for now) ---- */
$stmt = $pdo->prepare('SELECT userID, firstName, email FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$u = $stmt->fetch();

if ($u) {
    /* ---- Generate token ---- */
    $rawToken  = bin2hex(random_bytes(32));                 // 64 hex chars
    $tokenHash = hash('sha256', $rawToken);                 // stored in DB
    $expiresAt = date('Y-m-d H:i:s', time() + 10 * 60);     // 10 minutes

    /* ---- Invalidate any existing unused tokens for this user ---- */
    $pdo->prepare(
        'UPDATE passwordResets SET usedAt = NOW()
         WHERE userID = ? AND usedAt IS NULL'
    )->execute([$u['userID']]);

    /* ---- Insert new token ---- */
    $pdo->prepare(
        'INSERT INTO passwordResets (userID, tokenHash, expiresAt)
         VALUES (?, ?, ?)'
    )->execute([$u['userID'], $tokenHash, $expiresAt]);

    /* ---- Build the reset link ---- */
    $link = "http://localhost/StepCare/loginNsignup/forgotPassword/resetPassword.php?token=$rawToken";

    $html = "
      <div style='font-family: Arial, sans-serif; max-width: 520px; margin: auto;'>
        <h2 style='color:#3C52C3;'>Reset your StepCare password</h2>
        <p>Hi " . htmlspecialchars($u['firstName']) . ",</p>
        <p>We received a request to reset your password. Click the button below within <strong>10 minutes</strong>:</p>
        <p style='text-align:center; margin: 30px 0;'>
          <a href='$link'
             style='background:#3C52C3; color:white; padding:14px 28px;
                    border-radius:8px; text-decoration:none; font-weight:bold;'>
            Set new password
          </a>
        </p>
        <p style='color:#888; font-size:13px;'>
          If the button doesn't work, paste this into your browser:<br>
          <span style='word-break:break-all;'>$link</span>
        </p>
        <p style='color:#888; font-size:13px;'>
          If you didn't request this, you can safely ignore this email.
        </p>
      </div>
    ";

    $sent = sendMail($u['email'], 'StepCare — Reset your password', $html);

    if (!$sent) {
        error_log('Email failed to send to ' . $u['email']);
        header('Location: forgotPassword.html?error=mail_failed');
        exit;
    }
}

/* ---- Always the same message ---- */
header('Location: forgotPassword.html?sent=1');
exit;