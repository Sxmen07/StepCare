<?php
session_start();
header('Content-Type: application/json');
require __DIR__ . '/../../config/db_connect.php';

if (empty($_SESSION['userID'])) {
    echo json_encode(['ok' => false, 'message' => 'Please log in first']);
    exit;
}

$userID  = (int)$_SESSION['userID'];
$payload = json_decode(file_get_contents('php://input'), true);
$action  = $payload['action'] ?? '';
$code    = strtoupper(trim($payload['code'] ?? ''));

/* =========================================================
   CREATE CODE
   ========================================================= */
if ($action === 'create') {
    // Invalidate previous unused codes
    $pdo->prepare("UPDATE familyCare SET isUsed = 1 WHERE userID = ? AND isUsed = 0")
        ->execute([$userID]);

    // Generate a unique 6-character code
    do {
        $newCode = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $stmt = $pdo->prepare("SELECT 1 FROM familyCare WHERE accessCode = ?");
        $stmt->execute([$newCode]);
    } while ($stmt->fetch());

    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

    $stmt = $pdo->prepare(
        "INSERT INTO familyCare (userID, accessCode, codeExpiredTime)
         VALUES (?, ?, ?)"
    );
    $stmt->execute([$userID, $newCode, $expires]);

    echo json_encode([
        'ok'        => true,
        'code'      => $newCode,
        'expiresAt' => $expires,
    ]);
    exit;
}

/* =========================================================
   REDEEM CODE
   ========================================================= */
if ($action === 'redeem') {
    if ($code === '') {
        echo json_encode(['ok' => false, 'message' => 'Please enter a code']);
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT familyCareID, userID, codeExpiredTime, isUsed
         FROM familyCare WHERE accessCode = ? LIMIT 1"
    );
    $stmt->execute([$code]);
    $invite = $stmt->fetch();

    if (!$invite) {
        echo json_encode(['ok' => false, 'message' => 'Invalid code']);
        exit;
    }
    if ($invite['isUsed']) {
        echo json_encode(['ok' => false, 'message' => 'This code has already been used']);
        exit;
    }
    if ($invite['codeExpiredTime'] && strtotime($invite['codeExpiredTime']) < time()) {
        echo json_encode(['ok' => false, 'message' => 'This code has expired']);
        exit;
    }
    if ((int)$invite['userID'] === $userID) {
        echo json_encode(['ok' => false, 'message' => 'You cannot connect to yourself']);
        exit;
    }

    // Duplicate check
    $stmt = $pdo->prepare(
        "SELECT 1 FROM familyConnections WHERE ownerID = ? AND viewerID = ? LIMIT 1"
    );
    $stmt->execute([$invite['userID'], $userID]);
    if ($stmt->fetch()) {
        echo json_encode(['ok' => false, 'message' => 'You are already connected to this person']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO familyConnections (ownerID, viewerID) VALUES (?, ?)")
            ->execute([$invite['userID'], $userID]);

        $pdo->prepare("UPDATE familyCare SET isUsed = 1 WHERE familyCareID = ?")
            ->execute([$invite['familyCareID']]);

        // Notify the owner
        $pdo->prepare(
            "INSERT INTO notifications (userID, title, message, type, linkURL)
             VALUES (?, 'New family member connected',
                        'A family member has joined your care circle.',
                        'system',
                        '../familyCare/familyCare.php')"
        )->execute([$invite['userID']]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'message' => 'Connection failed. Please try again.']);
        exit;
    }

    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => false, 'message' => 'Unknown action']);