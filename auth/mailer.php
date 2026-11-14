<?php
// English comments only
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../lib/PHPMailer-7.1.1/src/PHPMailer.php';
require __DIR__ . '/../lib/PHPMailer-7.1.1/src/SMTP.php';
require __DIR__ . '/../lib/PHPMailer-7.1.1/src/Exception.php';

/**
 * Load environment variables from .env once, then cache them.
 * Real OS environment variables (getenv) override the .env file.
 *
 * @return array<string,string>
 */
function loadEnv(): array
{
    static $env = null;
    if ($env !== null) {
        return $env;
    }

    $env = [];
    $envPath = __DIR__ . '/../.env';   // StepCare/.env

    if (is_file($envPath)) {
        // INI_SCANNER_RAW does not strip quotes or treat ';' as comments,
        // so we handle surrounding quotes manually below.
        $parsed = parse_ini_file($envPath, false, INI_SCANNER_RAW);
        if ($parsed !== false) {
            foreach ($parsed as $key => $value) {
                if (is_string($value)) {
                    $len = strlen($value);
                    if ($len >= 2 &&
                        (($value[0] === '"' && $value[$len - 1] === '"') ||
                         ($value[0] === "'" && $value[$len - 1] === "'"))) {
                        $value = substr($value, 1, -1);
                    }
                }
                $env[$key] = $value;
            }
        } else {
            error_log('MAILER: failed to parse .env at ' . $envPath);
        }
    } else {
        error_log('MAILER: .env not found at ' . $envPath);
    }

    // OS env vars override .env (useful for real deployments)
    foreach (['GMAIL_USER', 'GMAIL_PASS'] as $key) {
        $osValue = getenv($key);
        if ($osValue !== false && $osValue !== '') {
            $env[$key] = $osValue;
        }
    }

    return $env;
}

/**
 * Send a single HTML email through Gmail SMTP.
 *
 * @param string $to       Recipient address
 * @param string $subject  Subject line
 * @param string $htmlBody HTML body
 * @param string $toName   Optional recipient display name
 * @return bool            true on success, false on any failure
 */
function sendMail(string $to, string $subject, string $htmlBody, string $toName = ''): bool
{
    $env       = loadEnv();
    $gmailUser = trim($env['GMAIL_USER'] ?? '');
    $gmailPass = trim($env['GMAIL_PASS'] ?? '');

    if ($gmailUser === '' || $gmailPass === '') {
        error_log('MAILER: GMAIL_USER or GMAIL_PASS is empty. Check ' . realpath(__DIR__ . '/../.env'));
        return false;
    }

    // Basic sanity check: Gmail App Passwords are 16 chars (spaces allowed)
    $strippedPass = str_replace(' ', '', $gmailPass);
    if (strlen($strippedPass) !== 16) {
        error_log('MAILER: GMAIL_PASS length is ' . strlen($strippedPass)
                . ' after removing spaces. Gmail App Passwords are 16 characters. '
                . 'Did you use your normal Gmail password instead of an App Password?');
        // We still try — Google will return 535 and we will see it in the log.
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->SMTPDebug   = 0;   // set to 2 for verbose SMTP log
        $mail->Debugoutput = function ($str, $level) {
            error_log("PHPMailer SMTP[$level]: $str");
        };

        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $gmailUser;
        $mail->Password   = $gmailPass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 20;

        // From MUST be the authenticated Gmail account (or a verified alias).
        // Using an unverified domain like no-reply@stepcare.com causes Gmail
        // to reject the message or silently drop it.
        $mail->setFrom($gmailUser, 'StepCare');
        $mail->addReplyTo($gmailUser, 'StepCare');

        if ($toName !== '') {
            $mail->addAddress($to, $toName);
        } else {
            $mail->addAddress($to);
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = trim(strip_tags($htmlBody));

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('PHPMailer Error: ' . $mail->ErrorInfo);
        return false;
    }
}