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
    $pdo->prepare("UPDATE familyCare SET isUsed = 1 WHERE userID = ? AND isUsed = 0")->execute([$userID]);

    do {
        $newCode = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $stmt = $pdo->prepare("SELECT 1 FROM familyCare WHERE accessCode = ?");
        $stmt->execute([$newCode]);
    } while ($stmt->fetch());

    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

    $stmt = $pdo->prepare("INSERT INTO familyCare (userID, accessCode, codeExpiredTime) VALUES (?, ?, ?)");
    $stmt->execute([$userID, $newCode, $expires]);

    echo json_encode(['ok' => true, 'code' => $newCode, 'expiresAt' => $expires]);
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

    $stmt = $pdo->prepare("SELECT familyCareID, userID, codeExpiredTime, isUsed FROM familyCare WHERE accessCode = ? LIMIT 1");
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

    $stmt = $pdo->prepare("SELECT 1 FROM familyConnections WHERE ownerID = ? AND viewerID = ? LIMIT 1");
    $stmt->execute([$invite['userID'], $userID]);
    if ($stmt->fetch()) {
        echo json_encode(['ok' => false, 'message' => 'You are already connected to this person']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO familyConnections (ownerID, viewerID) VALUES (?, ?)")->execute([$invite['userID'], $userID]);
        $pdo->prepare("UPDATE familyCare SET isUsed = 1 WHERE familyCareID = ?")->execute([$invite['familyCareID']]);
        $pdo->prepare(
            "INSERT INTO notifications (recipientType, recipientID, title, message, type, linkURL)
             VALUES ('user', ?, 'New family member connected', 'A family member has joined your care circle.', 'system', '../familyCare/familyCare.php')"
        )->execute([$invite['userID']]);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Family connection failed: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'message' => 'Connection failed. Please try again.']);
        exit;
    }

    echo json_encode(['ok' => true]);
    exit;
}

/* =========================================================
   DELETE CONNECTIONS (Bulk)
   ========================================================= */
if ($action === 'delete') {
    $connIDs = $payload['connectionIDs'] ?? [];
    if (empty($connIDs) || !is_array($connIDs)) {
        echo json_encode(['ok' => false, 'message' => 'No members selected']);
        exit;
    }

    // Sanitize array to integers
    $connIDs = array_map('intval', $connIDs);
    $inQuery = implode(',', array_fill(0, count($connIDs), '?'));

    // Verify ownership/viewership for security
    $stmt = $pdo->prepare(
        "SELECT connectionID FROM familyConnections 
         WHERE connectionID IN ($inQuery) AND (ownerID = ? OR viewerID = ?)"
    );
    $params = array_merge($connIDs, [$userID, $userID]);
    $stmt->execute($params);
    
    $validIDs = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($validIDs) > 0) {
        $delQuery = implode(',', array_fill(0, count($validIDs), '?'));
        $pdo->prepare("DELETE FROM familyConnections WHERE connectionID IN ($delQuery)")->execute($validIDs);
    }

    echo json_encode(['ok' => true]);
    exit;
}

/* =========================================================
   TOGGLE PIN
   ========================================================= */
if ($action === 'pin') {
    $connIDs = $payload['connectionIDs'] ?? [];
    if (empty($connIDs) || !is_array($connIDs)) {
        echo json_encode(['ok' => false, 'message' => 'No members selected']);
        exit;
    }

    $connIDs = array_map('intval', $connIDs);
    
    // We toggle: if 1 becomes 0, if 0 becomes 1
    $inQuery = implode(',', array_fill(0, count($connIDs), '?'));
    $stmt = $pdo->prepare(
        "UPDATE familyConnections 
         SET isPinned = NOT isPinned 
         WHERE connectionID IN ($inQuery) AND (ownerID = ? OR viewerID = ?)"
    );
    $params = array_merge($connIDs, [$userID, $userID]);
    $stmt->execute($params);

    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => false, 'message' => 'Unknown action']);