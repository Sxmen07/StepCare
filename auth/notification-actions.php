<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/db_connect.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Retrieve current authentication context (fallback to admin 1)
$currentRole   = $_GET['role'] ?? $_POST['role'] ?? 'admin';
$currentUserID = (int)($_GET['user_id'] ?? $_POST['user_id'] ?? 1);

$response = ['success' => false, 'message' => ''];

try {
    // -------------------------------------------------------------
    // 1. Fetch Notification Summary or Full Stream
    // -------------------------------------------------------------
    if ($action === 'fetch_summary' || $action === 'fetch_all') {
        $filterRole = $_GET['filter_role'] ?? 'all';
        $sortBy     = $_GET['sort_by'] ?? 'newest';

        $limitClause = ($action === 'fetch_summary') ? "LIMIT 10" : "";

        $whereRole = "";
        if ($filterRole !== 'all') {
            $escapedRole = $conn->real_escape_string($filterRole);
            $whereRole = "AND n.senderType = '{$escapedRole}'";
        }

        $orderSql = ($sortBy === 'oldest') ? "ASC" : "DESC";

        $sql = "SELECT n.*, 
                       CASE 
                         WHEN n.senderType = 'admin' THEN (SELECT CONCAT(firstName, ' ', lastName) FROM admins WHERE adminID = n.senderID)
                         WHEN n.senderType = 'nurse' THEN (SELECT CONCAT(firstName, ' ', lastName) FROM nurses WHERE nurseID = n.senderID)
                         ELSE 'System Alert'
                       END AS senderName,
                       CASE 
                         WHEN n.senderType = 'admin' THEN (SELECT adminPhotoURL FROM admins WHERE adminID = n.senderID)
                         WHEN n.senderType = 'nurse' THEN (SELECT nursePhotoURL FROM nurses WHERE nurseID = n.senderID)
                         ELSE NULL
                       END AS senderPhoto
                FROM notifications n
                WHERE (
                    FIND_IN_SET(?, n.recipientType) > 0 
                    OR n.recipientType LIKE CONCAT('%', ?, '%')
                    OR n.recipientType = 'all'
                    OR (n.senderType = ? AND n.senderID = ?)
                )
                $whereRole
                ORDER BY n.createdAt $orderSql $limitClause";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("SQL Prepare Error: " . $conn->error);
        }

        $stmt->bind_param("sssi", $currentRole, $currentRole, $currentRole, $currentUserID);
        $stmt->execute();
        $result = $stmt->get_result();

        $notifications = [];
        $unreadCount = 0;

        while ($row = $result->fetch_assoc()) {
            $row['can_manage'] = ($row['senderType'] === $currentRole && (int)$row['senderID'] === $currentUserID);
            $row['recipients_list'] = array_filter(array_map('trim', explode(',', $row['recipientType'])));
            
            // Count unread directly from the dataset
            if ((int)$row['isRead'] === 0) {
                $unreadCount++;
            }
            $notifications[] = $row;
        }

        $response['success'] = true;
        $response['unread_count'] = $unreadCount;
        $response['notifications'] = $notifications;
    }

    // -------------------------------------------------------------
    // 2. Fetch Single Notification Detail & Mark Read
    // -------------------------------------------------------------
    elseif ($action === 'get_detail') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) throw new Exception("Invalid Notification ID.");

        $upd = $conn->prepare("UPDATE notifications SET isRead = 1 WHERE notificationID = ?");
        $upd->bind_param("i", $id);
        $upd->execute();

        $sql = "SELECT n.*, 
                       CASE 
                         WHEN n.senderType = 'admin' THEN (SELECT CONCAT(firstName, ' ', lastName) FROM admins WHERE adminID = n.senderID)
                         WHEN n.senderType = 'nurse' THEN (SELECT CONCAT(firstName, ' ', lastName) FROM nurses WHERE nurseID = n.senderID)
                         ELSE 'System Alert'
                       END AS senderName,
                       CASE 
                         WHEN n.senderType = 'admin' THEN (SELECT adminPhotoURL FROM admins WHERE adminID = n.senderID)
                         WHEN n.senderType = 'nurse' THEN (SELECT nursePhotoURL FROM nurses WHERE nurseID = n.senderID)
                         ELSE NULL
                       END AS senderPhoto
                FROM notifications n
                WHERE n.notificationID = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();

        if ($data) {
            $data['can_manage'] = ($data['senderType'] === $currentRole && (int)$data['senderID'] === $currentUserID);
            $data['recipients_list'] = array_filter(array_map('trim', explode(',', $data['recipientType'])));
            $response['success'] = true;
            $response['data'] = $data;
        } else {
            throw new Exception("Notification record not found.");
        }
    }

    // -------------------------------------------------------------
    // 3. Dispatch Notification (Create)
    // -------------------------------------------------------------
    elseif ($action === 'send') {
        if ($currentRole === 'user') throw new Exception("Standard users cannot broadcast notifications.");

        $rawTargets = $_POST['recipientTypes'] ?? [];
        if (is_string($rawTargets)) {
            $rawTargets = explode(',', $rawTargets);
        }

        $allowed = ['user', 'nurse', 'admin'];
        $cleanTargets = array_values(array_intersect($rawTargets, $allowed));
        if (empty($cleanTargets)) {
            $cleanTargets = ['admin'];
        }

        $title   = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (empty($title) || empty($message)) {
            throw new Exception("Title and message content cannot be empty.");
        }

        $combinedRecipients = implode(',', $cleanTargets);

        $stmt = $conn->prepare("INSERT INTO notifications (recipientType, recipientID, senderType, senderID, title, message, isRead) VALUES (?, 0, ?, ?, ?, ?, 0)");
        $stmt->bind_param("ssiss", $combinedRecipients, $currentRole, $currentUserID, $title, $message);
        
        if (!$stmt->execute()) {
            throw new Exception("Database Insert Error: " . $stmt->error);
        }

        $response['success'] = true;
        $response['message'] = "Notification dispatched successfully.";
    }

    // -------------------------------------------------------------
    // 4. Update Notification (Edit)
    // -------------------------------------------------------------
    elseif ($action === 'update') {
        $id      = (int)($_POST['notificationID'] ?? 0);
        $title   = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');

        $rawTargets = $_POST['recipientTypes'] ?? [];
        if (is_string($rawTargets)) {
            $rawTargets = explode(',', $rawTargets);
        }
        $allowed = ['user', 'nurse', 'admin'];
        $cleanTargets = array_values(array_intersect($rawTargets, $allowed));
        if (empty($cleanTargets)) $cleanTargets = ['admin'];

        if ($id <= 0 || empty($title) || empty($message)) {
            throw new Exception("Invalid parameters for notification update.");
        }

        $check = $conn->prepare("SELECT senderType, senderID FROM notifications WHERE notificationID = ?");
        $check->bind_param("i", $id);
        $check->execute();
        $owner = $check->get_result()->fetch_assoc();

        if (!$owner || $owner['senderType'] !== $currentRole || (int)$owner['senderID'] !== $currentUserID) {
            throw new Exception("Forbidden: You can only edit notifications you authored.");
        }

        $combinedRecipients = implode(',', $cleanTargets);

        $upd = $conn->prepare("UPDATE notifications SET recipientType = ?, title = ?, message = ? WHERE notificationID = ?");
        $upd->bind_param("sssi", $combinedRecipients, $title, $message, $id);
        
        if (!$upd->execute()) {
            throw new Exception("Update execution error: " . $upd->error);
        }

        $response['success'] = true;
        $response['message'] = "Notification updated successfully.";
    }

    // -------------------------------------------------------------
    // 5. Delete Notification
    // -------------------------------------------------------------
    elseif ($action === 'delete') {
        $id = (int)($_POST['notificationID'] ?? 0);
        if ($id <= 0) throw new Exception("Invalid Notification ID.");

        $check = $conn->prepare("SELECT senderType, senderID FROM notifications WHERE notificationID = ?");
        $check->bind_param("i", $id);
        $check->execute();
        $owner = $check->get_result()->fetch_assoc();

        if (!$owner || $owner['senderType'] !== $currentRole || (int)$owner['senderID'] !== $currentUserID) {
            throw new Exception("Forbidden: You can only delete notifications you created.");
        }

        $del = $conn->prepare("DELETE FROM notifications WHERE notificationID = ?");
        $del->bind_param("i", $id);
        
        if (!$del->execute()) {
            throw new Exception("Delete execution error: " . $del->error);
        }

        $response['success'] = true;
        $response['message'] = "Notification deleted successfully.";
    }

    // -------------------------------------------------------------
    // 6. Mark All Visible Notifications as Read
    // -------------------------------------------------------------
    elseif ($action === 'mark_all_read') {
        $upd = $conn->prepare("UPDATE notifications SET isRead = 1 
                               WHERE (
                                   FIND_IN_SET(?, recipientType) > 0 
                                   OR recipientType LIKE CONCAT('%', ?, '%') 
                                   OR recipientType = 'all'
                                   OR (senderType = ? AND senderID = ?)
                               )");
        $upd->bind_param("sssi", $currentRole, $currentRole, $currentRole, $currentUserID);
        
        if (!$upd->execute()) {
            throw new Exception("Failed to mark all as read: " . $upd->error);
        }

        $response['success'] = true;
        $response['message'] = "All notifications marked as read.";
    } else {
        throw new Exception("Invalid action requested.");
    }
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;