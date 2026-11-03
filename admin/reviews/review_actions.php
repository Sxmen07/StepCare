<?php
// English comments only
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db_connect.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_dropdown_options':
        getDropdownOptions($pdo);
        break;

    case 'get_analytics':
        getReviewAnalytics($pdo);
        break;

    case 'fetch_reviews':
        fetchReviewsList($pdo);
        break;

    case 'toggle_visibility':
        toggleReviewVisibility($pdo);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action parameter']);
        break;
}

// 1. Fetch available options for the selector dropdown (with nurse avatar & specialization)
function getDropdownOptions($pdo) {
    try {
        $type = $_GET['type'] ?? 'service';
        if ($type === 'nurse') {
            $stmt = $pdo->query("SELECT nurseID AS id, CONCAT(firstName, ' ', lastName) AS label, nursePhotoURL AS photoURL, specialization FROM nurses ORDER BY firstName ASC");
        } else {
            $stmt = $pdo->query("SELECT serviceID AS id, serviceName AS label, category FROM services ORDER BY serviceName ASC");
        }
        $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'data' => $options]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 2. Calculate quantifiable metrics for overall or a specific entity
function getReviewAnalytics($pdo) {
    try {
        $type = $_GET['type'] ?? 'service';
        $entityId = (int)($_GET['entityId'] ?? 0);

        $table = ($type === 'nurse') ? 'nurseReviews' : 'serviceComments';
        $foreignKey = ($type === 'nurse') ? 'nurseID' : 'serviceID';

        $whereClause = "";
        $params = [];
        if ($entityId > 0) {
            $whereClause = "WHERE {$foreignKey} = ?";
            $params[] = $entityId;
        }

        $summaryStmt = $pdo->prepare("
            SELECT 
                COUNT(*) AS totalCount,
                IFNULL(AVG(rating), 0) AS avgRating,
                SUM(CASE WHEN CAST(isHidden AS UNSIGNED) = 1 THEN 1 ELSE 0 END) AS hiddenCount,
                SUM(CASE WHEN rating <= 2 THEN 1 ELSE 0 END) AS lowRatingCount
            FROM {$table}
            {$whereClause}
        ");
        $summaryStmt->execute($params);
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

        $totalCount = (int)$summary['totalCount'];
        $avgRating = round((float)$summary['avgRating'], 2);
        $hiddenCount = (int)($summary['hiddenCount'] ?? 0);
        $lowRatingCount = (int)($summary['lowRatingCount'] ?? 0);

        $distQuery = "SELECT rating, COUNT(*) AS count FROM {$table} {$whereClause} GROUP BY rating";
        $distStmt = $pdo->prepare($distQuery);
        $distStmt->execute($params);
        $distRows = $distStmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $breakdown = [];
        for ($star = 5; $star >= 1; $star--) {
            $cnt = isset($distRows[$star]) ? (int)$distRows[$star] : 0;
            $pct = ($totalCount > 0) ? round(($cnt / $totalCount) * 100, 1) : 0;
            $breakdown[$star] = [
                'count' => $cnt,
                'percentage' => $pct
            ];
        }

        echo json_encode([
            'status' => 'success',
            'data' => [
                'type' => $type,
                'entityId' => $entityId,
                'totalReviews' => $totalCount,
                'averageRating' => number_format($avgRating, 1),
                'hiddenReviews' => $hiddenCount,
                'lowRatingCount' => $lowRatingCount,
                'starBreakdown' => $breakdown
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 3. Fetch reviews filtered by specific package/nurse and visibility status
function fetchReviewsList($pdo) {
    try {
        $type = $_GET['type'] ?? 'service';
        $entityId = (int)($_GET['entityId'] ?? 0);
        $filter = $_GET['filter'] ?? 'all';

        $conditions = [];
        $params = [];

        if ($type === 'nurse') {
            $query = "
                SELECT 
                    r.nurseReviewID AS reviewId,
                    r.rating,
                    r.comment,
                    CAST(r.isHidden AS UNSIGNED) AS isHidden,
                    r.createdAt,
                    CONCAT(u.firstName, ' ', u.lastName) AS patientName,
                    u.email AS patientEmail,
                    CONCAT(n.firstName, ' ', n.lastName) AS targetName,
                    n.specialization AS targetMeta,
                    n.nursePhotoURL AS targetPhoto
                FROM nurseReviews r
                JOIN users u ON r.userID = u.userID
                JOIN nurses n ON r.nurseID = n.nurseID
            ";
            if ($entityId > 0) {
                $conditions[] = "r.nurseID = ?";
                $params[] = $entityId;
            }
        } else {
            $query = "
                SELECT 
                    r.serviceCommentID AS reviewId,
                    r.rating,
                    r.comment,
                    CAST(r.isHidden AS UNSIGNED) AS isHidden,
                    r.createdAt,
                    CONCAT(u.firstName, ' ', u.lastName) AS patientName,
                    u.email AS patientEmail,
                    s.serviceName AS targetName,
                    s.category AS targetMeta,
                    NULL AS targetPhoto
                FROM serviceComments r
                JOIN users u ON r.userID = u.userID
                JOIN services s ON r.serviceID = s.serviceID
            ";
            if ($entityId > 0) {
                $conditions[] = "r.serviceID = ?";
                $params[] = $entityId;
            }
        }

        if ($filter === 'visible') {
            $conditions[] = "CAST(r.isHidden AS UNSIGNED) = 0";
        } elseif ($filter === 'hidden') {
            $conditions[] = "CAST(r.isHidden AS UNSIGNED) = 1";
        } elseif ($filter === 'low') {
            $conditions[] = "r.rating <= 2";
        }

        if (!empty($conditions)) {
            $query .= " WHERE " . implode(' AND ', $conditions);
        }

        $query .= " ORDER BY r.createdAt DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($rows as $row) {
            $results[] = [
                'reviewId' => (int)$row['reviewId'],
                'type' => $type,
                'rating' => (int)$row['rating'],
                'comment' => $row['comment'] ?: 'No text feedback provided.',
                'isHidden' => (int)$row['isHidden'] === 1,
                'createdAt' => date('M d, Y H:i', strtotime($row['createdAt'])),
                'patientName' => $row['patientName'],
                'patientEmail' => $row['patientEmail'],
                'targetName' => $row['targetName'],
                'targetMeta' => $row['targetMeta'] ?? 'General',
                'targetPhoto' => $row['targetPhoto'] ?? null
            ];
        }

        echo json_encode(['status' => 'success', 'data' => $results]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 4. Toggle review visibility (fixed restore & take down logic)
function toggleReviewVisibility($pdo) {
    try {
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true);

        $reviewId = isset($input['reviewId']) ? (int)$input['reviewId'] : 0;
        $type = trim($input['type'] ?? 'service');
        $newStatus = isset($input['isHidden']) ? (int)$input['isHidden'] : 0;

        if ($reviewId <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid review ID']);
            return;
        }

        $table = ($type === 'nurse') ? 'nurseReviews' : 'serviceComments';
        $idCol = ($type === 'nurse') ? 'nurseReviewID' : 'serviceCommentID';

        $stmt = $pdo->prepare("UPDATE {$table} SET isHidden = :status WHERE {$idCol} = :id");
        $stmt->bindValue(':status', $newStatus, PDO::PARAM_INT);
        $stmt->bindValue(':id', $reviewId, PDO::PARAM_INT);
        $stmt->execute();

        echo json_encode([
            'status' => 'success',
            'newStatus' => $newStatus,
            'message' => ($newStatus === 1) ? 'Review taken down.' : 'Review restored successfully.'
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>