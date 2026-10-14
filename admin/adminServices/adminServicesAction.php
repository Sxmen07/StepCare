<?php
session_start();
header('Content-Type: application/json');

require __DIR__ . '/../../config/db_connect.php';

if (empty($_SESSION['userID']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['ok' => false, 'message' => 'Not authorized']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (!is_array($payload) || ($payload['action'] ?? '') !== 'launch') {
    echo json_encode(['ok' => false, 'message' => 'Invalid request']);
    exit;
}

$changes = $payload['changes'] ?? [];
if (!is_array($changes) || empty($changes)) {
    echo json_encode(['ok' => false, 'message' => 'No changes provided']);
    exit;
}

$applied = 0;
$pdo->beginTransaction();

try {
    foreach ($changes as $key => $change) {
        $type = $change['type'] ?? '';
        $data = $change['data'] ?? [];

        /* ---------- CREATE ---------- */
        if ($type === 'create') {
            $stmt = $pdo->prepare(
                'INSERT INTO services
                    (serviceName, category, serviceImageURL, price, price_unit,
                     durationMin, durationMax, description, isTopRecommendation)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $data['serviceName'] ?? '',
                $data['category'] ?? null,
                $data['serviceImageURL'] ?? null,
                $data['price'] ?? 0,
                $data['price_unit'] ?? 'visit',
                $data['durationMin'] ?? null,
                $data['durationMax'] ?? null,
                $data['description'] ?? null,
                (int)($data['isTopRecommendation'] ?? 0),
            ]);
            $applied++;
            continue;
        }

        /* ---------- EXISTING (edit / pin / delete) ---------- */
        $serviceID = (int)$key;

        if ($type === 'delete') {
            $stmt = $pdo->prepare('DELETE FROM services WHERE serviceID = ?');
            $stmt->execute([$serviceID]);
            $applied++;
            continue;
        }

        if ($type === 'pin') {
            $pinned = (int)($data['isTopRecommendation'] ?? 0);
            $stmt = $pdo->prepare('UPDATE services SET isTopRecommendation = ? WHERE serviceID = ?');
            $stmt->execute([$pinned, $serviceID]);
            $applied++;
            continue;
        }

        if ($type === 'edit') {
            $stmt = $pdo->prepare(
                'UPDATE services SET
                    serviceName = ?,
                    category = ?,
                    serviceImageURL = ?,
                    price = ?,
                    price_unit = ?,
                    durationMin = ?,
                    durationMax = ?,
                    description = ?,
                    isTopRecommendation = ?
                 WHERE serviceID = ?'
            );
            $stmt->execute([
                $data['serviceName'] ?? '',
                $data['category'] ?? null,
                $data['serviceImageURL'] ?? null,
                $data['price'] ?? 0,
                $data['price_unit'] ?? 'visit',
                $data['durationMin'] ?? null,
                $data['durationMax'] ?? null,
                $data['description'] ?? null,
                (int)($data['isTopRecommendation'] ?? 0),
                $serviceID,
            ]);
            $applied++;
            continue;
        }
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['ok' => false, 'message' => 'DB error: ' . $e->getMessage()]);
    exit;
}

$services = $pdo->query("
    SELECT s.serviceID, s.serviceName, s.category, s.description, s.serviceImageURL,
           s.price, s.price_unit, s.average_rating, s.review_count,
           s.durationMin, s.durationMax, s.isTopRecommendation,
           GROUP_CONCAT(DISTINCT sb.badgeText SEPARATOR '|||') AS badges
    FROM services s
    LEFT JOIN serviceBadges sb ON sb.serviceID = s.serviceID
    GROUP BY s.serviceID
    ORDER BY s.isTopRecommendation DESC, s.serviceID DESC
")->fetchAll();

echo json_encode([
    'ok'       => true,
    'applied'  => $applied,
    'services' => $services,
]);