<?php
// English comments only
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db_connect.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'fetch_verifications':
        fetchVerifications($pdo);
        break;

    case 'verify_payment':
        verifyPayment($pdo);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action parameter']);
        break;
}

// 1. Fetch payments waiting for verification or filtered by status
function fetchVerifications($pdo) {
    try {
        $statusFilter = strtolower(trim($_GET['status'] ?? 'all'));

        $query = "
            SELECT 
                p.paymentID,
                p.appointmentID,
                p.paymentDate,
                p.totalAmount,
                p.paymentMethod,
                p.transactionID,
                p.paymentStatus,
                p.paymentProofURL,
                p.createdAt AS paymentCreatedAt,
                a.startDateTime,
                a.endDateTime,
                a.locationAddress,
                a.appointmentStatus,
                u.firstName AS userFirstName,
                u.lastName AS userLastName,
                u.email AS userEmail,
                u.phoneNumber AS userPhone,
                s.serviceName,
                n.firstName AS nurseFirstName,
                n.lastName AS nurseLastName
            FROM payments p
            INNER JOIN appointments a ON p.appointmentID = a.appointmentID
            LEFT JOIN users u ON a.userID = u.userID
            LEFT JOIN services s ON a.serviceID = s.serviceID
            LEFT JOIN nurses n ON a.nurseID = n.nurseID
        ";

        $params = [];
        if ($statusFilter !== 'all' && !empty($statusFilter)) {
            $query .= " WHERE LOWER(p.paymentStatus) = ?";
            $params[] = $statusFilter;
        }

        $query .= " ORDER BY p.paymentID DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($rows as $row) {
            $clientName = trim(($row['userFirstName'] ?? '') . ' ' . ($row['userLastName'] ?? ''));
            $nurseName  = trim(($row['nurseFirstName'] ?? '') . ' ' . ($row['nurseLastName'] ?? ''));

            $results[] = [
                'paymentId'       => (int)$row['paymentID'],
                'appointmentId'   => (int)$row['appointmentID'],
                'formattedApptId' => 'APT-' . str_pad($row['appointmentID'], 4, '0', STR_PAD_LEFT),
                'clientName'      => $clientName ?: 'Unknown User',
                'clientEmail'     => $row['userEmail'] ?? 'N/A',
                'clientPhone'     => $row['userPhone'] ?? 'N/A',
                'serviceName'     => $row['serviceName'] ?? 'General Care Service',
                'nurseName'       => $nurseName ?: 'Not Assigned',
                'totalAmount'     => number_format((float)$row['totalAmount'], 2),
                'paymentMethod'   => $row['paymentMethod'] ?: 'Manual Transfer',
                'transactionId'   => $row['transactionID'] ?: 'N/A',
                'paymentStatus'   => strtolower($row['paymentStatus']),
                'appointmentStatus' => strtolower($row['appointmentStatus'] ?? 'pending'),
                'paymentProofURL' => $row['paymentProofURL'] ?? null,
                'scheduleTime'    => !empty($row['startDateTime']) ? date('M d, Y h:i A', strtotime($row['startDateTime'])) : 'TBD',
                'submittedAt'     => !empty($row['paymentCreatedAt']) ? date('M d, Y H:i', strtotime($row['paymentCreatedAt'])) : 'Recent'
            ];
        }

        echo json_encode(['status' => 'success', 'data' => $results]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 2. Approve or Reject a payment verification request
function verifyPayment($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        $paymentId = (int)($input['paymentId'] ?? 0);
        $decision  = strtolower(trim($input['decision'] ?? '')); // 'approve' or 'reject'
        $adminNote = trim($input['note'] ?? '');
        $adminId   = $_SESSION['adminID'] ?? 1;

        if (!$paymentId || !in_array($decision, ['approve', 'reject'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid parameters provided.']);
            return;
        }

        // Fetch payment and linked appointment
        $stmt = $pdo->prepare("SELECT appointmentID, totalAmount, paymentStatus FROM payments WHERE paymentID = ? LIMIT 1");
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payment) {
            echo json_encode(['status' => 'error', 'message' => 'Payment record not found.']);
            return;
        }

        $appointmentId = (int)$payment['appointmentID'];
        $amountFormatted = number_format((float)$payment['totalAmount'], 2);

        $pdo->beginTransaction();

        if ($decision === 'approve') {
            // 1. Update payments table to 'paid'
            $upPay = $pdo->prepare("UPDATE payments SET paymentStatus = 'paid', paymentDate = NOW() WHERE paymentID = ?");
            $upPay->execute([$paymentId]);

            // 2. Update appointments table to 'confirmed'
            $upApp = $pdo->prepare("UPDATE appointments SET appointmentStatus = 'confirmed' WHERE appointmentID = ?");
            $upApp->execute([$appointmentId]);

            // 3. Insert into appointment_timeline
            $tlStmt = $pdo->prepare("
                INSERT INTO appointment_timeline (appointmentID, status, title, message, createdBy, createdByID, createdAt)
                VALUES (?, 'confirmed', 'Payment Verified', ?, 'admin', ?, NOW())
            ");
            $msg = "Payment of RM {$amountFormatted} verified by Administrator. Booking confirmed.";
            if (!empty($adminNote)) {
                $msg .= " Note: " . $adminNote;
            }
            $tlStmt->execute([$appointmentId, $msg, $adminId]);

        } else {
            // Reject branch
            // 1. Update payments table to 'failed'
            $upPay = $pdo->prepare("UPDATE payments SET paymentStatus = 'failed' WHERE paymentID = ?");
            $upPay->execute([$paymentId]);

            // 2. Insert into appointment_timeline
            $tlStmt = $pdo->prepare("
                INSERT INTO appointment_timeline (appointmentID, status, title, message, createdBy, createdByID, createdAt)
                VALUES (?, 'declined', 'Payment Verification Failed', ?, 'admin', ?, NOW())
            ");
            $reasonMsg = !empty($adminNote) ? $adminNote : "Uploaded proof document was invalid or transaction unverified.";
            $msg = "Payment of RM {$amountFormatted} was declined. Reason: " . $reasonMsg;
            $tlStmt->execute([$appointmentId, $msg, $adminId]);
        }

        $pdo->commit();

        echo json_encode([
            'status'  => 'success',
            'message' => $decision === 'approve' ? 'Payment approved and appointment confirmed.' : 'Payment marked as rejected.'
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>