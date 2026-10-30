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

// 1. Fetch available options for the selector dropdown
function getDropdownOptions($pdo) {
    try {
        $type = $_GET['type'] ?? 'service';
        if ($type === 'nurse') {
            $stmt = $pdo->query("SELECT nurseID AS id, CONCAT(firstName, ' ', lastName) AS label FROM nurses ORDER BY firstName ASC");
        } else {
            $stmt = $pdo->query("SELECT serviceID AS id, serviceName AS label FROM services ORDER BY serviceName ASC");
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
        $type = $_GET['type'] ?? 'service'; // 'service' or 'nurse'
        $entityId = (int)($_GET['entityId'] ?? 0); // 0 means all

        $table = ($type === 'nurse') ? 'nurseReviews' : 'serviceComments';
        $foreignKey = ($type === 'nurse') ? 'nurseID' : 'serviceID';

        $whereClause = "";
        $params = [];
        if ($entityId > 0) {
            $whereClause = "WHERE {$foreignKey} = ?";
            $params[] = $entityId;
        }

        // Summary calculations
        $summaryStmt = $pdo->prepare("
            SELECT 
                COUNT(*) AS totalCount,
                IFNULL(AVG(rating), 0) AS avgRating,
                SUM(CASE WHEN isHidden = 1 THEN 1 ELSE 0 END) AS hiddenCount,
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

        // Star distribution breakdown
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
                    r.isHidden,
                    r.createdAt,
                    CONCAT(u.firstName, ' ', u.lastName) AS patientName,
                    u.email AS patientEmail,
                    CONCAT(n.firstName, ' ', n.lastName) AS targetName,
                    n.specialization AS targetMeta
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
                    r.isHidden,
                    r.createdAt,
                    CONCAT(u.firstName, ' ', u.lastName) AS patientName,
                    u.email AS patientEmail,
                    s.serviceName AS targetName,
                    s.category AS targetMeta
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
            $conditions[] = "r.isHidden = 0";
        } elseif ($filter === 'hidden') {
            $conditions[] = "r.isHidden = 1";
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
                'targetMeta' => $row['targetMeta'] ?? 'General'
            ];
        }

        echo json_encode(['status' => 'success', 'data' => $results]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 4. Toggle review visibility (moderation take down or restore)
function toggleReviewVisibility($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        $reviewId = (int)($input['reviewId'] ?? 0);
        $type = trim($input['type'] ?? 'service');
        $newStatus = (int)($input['isHidden'] ?? 0);

        if (!$reviewId) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid review identifier']);
            return;
        }

        $table = ($type === 'nurse') ? 'nurseReviews' : 'serviceComments';
        $idCol = ($type === 'nurse') ? 'nurseReviewID' : 'serviceCommentID';

        $stmt = $pdo->prepare("UPDATE {$table} SET isHidden = ? WHERE {$idCol} = ?");
        $stmt->execute([$newStatus, $reviewId]);

        echo json_encode([
            'status' => 'success',
            'message' => ($newStatus === 1) ? 'Review taken down successfully.' : 'Review restored.'
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>