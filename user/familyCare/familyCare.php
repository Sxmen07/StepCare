<?php
session_start();
require __DIR__ . '/../../config/db_connect.php';

// Check login status
$isLoggedIn = !empty($_SESSION['userID']);

if ($isLoggedIn) {
    $userID = (int)$_SESSION['userID'];

    /* ---------- Self ---------- */
    $stmt = $pdo->prepare('SELECT userID, firstName, lastName, userPhotoURL FROM users WHERE userID = ?');
    $stmt->execute([$userID]);
    $self = $stmt->fetch();

    /* ---------- Connections (both directions) ---------- */
    $stmt = $pdo->prepare("
        SELECT c.connectionID, c.ownerID, c.viewerID, c.relationship,
               CASE WHEN c.ownerID = ? THEN c.viewerID ELSE c.ownerID END AS otherUserID,
               CASE WHEN c.ownerID = ? THEN 'viewer' ELSE 'owner' END AS iAm
        FROM familyConnections c
        WHERE c.ownerID = ? OR c.viewerID = ?
        ORDER BY c.createdAt DESC
    ");
    $stmt->execute([$userID, $userID, $userID, $userID]);
    $rows = $stmt->fetchAll();

    /* ---------- Fetch other users ---------- */
    $members = [];
    $memberIDs = array_unique(array_map(fn($r) => (int)$r['otherUserID'], $rows));

    if (!empty($memberIDs)) {
        $ph = implode(',', array_fill(0, count($memberIDs), '?'));
        $stmt = $pdo->prepare("SELECT userID, firstName, lastName, userPhotoURL FROM users WHERE userID IN ($ph)");
        $stmt->execute($memberIDs);
        $byId = [];
        foreach ($stmt->fetchAll() as $u) $byId[$u['userID']] = $u;

        foreach ($rows as $r) {
            $u = $byId[$r['otherUserID']] ?? null;
            if (!$u) continue;
            $members[] = [
                'connectionID' => (int)$r['connectionID'],
                'userID'       => (int)$u['userID'],
                'firstName'    => $u['firstName'],
                'lastName'     => $u['lastName'],
                'photoURL'     => $u['userPhotoURL'] ?: '../../images/default-user.png',
                'relationship' => $r['relationship'],
                'iAm'          => $r['iAm'],
            ];
        }
    }

    /* ---------- All appointments for self + members ---------- */
    $allIDs = array_merge([$userID], array_column($members, 'userID'));
    $appointments = [];

    if (!empty($allIDs)) {
        $ph = implode(',', array_fill(0, count($allIDs), '?'));
        $stmt = $pdo->prepare("
            SELECT a.appointmentID, a.userID, a.patientName,
                   a.startDateTime, a.endDateTime,
                   a.locationAddress, a.appointmentStatus,
                   s.serviceName, s.category, s.serviceImageURL,
                   s.price, s.price_unit
            FROM appointments a
            LEFT JOIN services s ON s.serviceID = a.serviceID
            WHERE a.userID IN ($ph)
            ORDER BY a.startDateTime DESC
        ");
        $stmt->execute($allIDs);
        $appointments = $stmt->fetchAll();
    }

    /* ---------- Active invite code ---------- */
    $stmt = $pdo->prepare("
        SELECT accessCode, codeExpiredTime
        FROM familyCare
        WHERE userID = ? AND isUsed = 0
          AND (codeExpiredTime IS NULL OR codeExpiredTime > NOW())
        ORDER BY createdAt DESC LIMIT 1
    ");
    $stmt->execute([$userID]);
    $activeCode = $stmt->fetch();

    /* ---------- Encode for JS ---------- */
    $selfJson         = json_encode($self, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $membersJson      = json_encode($members, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $appointmentsJson = json_encode($appointments, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $activeCodeJson   = json_encode($activeCode, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

} else {
    // Set empty defaults for guests so the HTML doesn't break
    $selfJson = 'null'; $membersJson = '[]'; $appointmentsJson = '[]'; $activeCodeJson = 'null';
}

$flash = $_SESSION['family_flash'] ?? null;
unset($_SESSION['family_flash']);

include __DIR__ . '/familyCare.html';
?>