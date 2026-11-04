<?php
// nurse/appointmentDetails/updateActivity.php
session_start();
header('Content-Type: application/json');
require __DIR__ . '/../../config/db_connect.php';

if (empty($_SESSION['nurseID'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$nurseID = (int)$_SESSION['nurseID'];
$payload = json_decode(file_get_contents('php://input'), true);

$appointmentID = (int)($payload['appointmentID'] ?? 0);
$status        = trim($payload['status'] ?? '');
$title         = trim($payload['title'] ?? '');
$message       = trim($payload['message'] ?? '');

if ($appointmentID <= 0 || $status === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid payload']); exit;
}

try {
    $pdo->beginTransaction();

    // 1. Fetch appointment info to know WHO to notify and WHAT service it is
    $stmt = $pdo->prepare("
        SELECT a.userID, s.serviceName 
        FROM appointments a 
        JOIN services s ON a.serviceID = s.serviceID 
        WHERE a.appointmentID = ?
    ");
    $stmt->execute([$appointmentID]);
    $apptInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$apptInfo) {
        throw new Exception('Appointment not found');
    }

    $targetUserID = (int)$apptInfo['userID'];
    $serviceName  = $apptInfo['serviceName'];

    // 2. Insert into timeline (keeps the specific 'on_the_way', 'arrived' etc.)
    $stmt = $pdo->prepare("
        INSERT INTO appointment_timeline (appointmentID, status, title, message, createdBy, createdByID)
        VALUES (?, ?, ?, ?, 'nurse', ?)
    ");
    $stmt->execute([$appointmentID, $status, $title, $message, $nurseID]);

    // 3. Update main appointment status
    $mainStatus = 'in_progress';
    if ($status === 'completed') {
        $mainStatus = 'completed';
    } elseif ($status === 'confirmed' || $status === 'pending') {
        $mainStatus = $status;
    }

    $stmt = $pdo->prepare("UPDATE appointments SET appointmentStatus = ? WHERE appointmentID = ? AND nurseID = ?");
    $stmt->execute([$mainStatus, $appointmentID, $nurseID]);

    // 4. Create a dynamic notification for the user
    $notifTitle = $title;
    $notifMessage = "Your appointment for {$serviceName} has been updated: {$message}";

    // Customize the message based on the specific action for a better user experience
    switch ($status) {
        case 'on_the_way':
            $notifTitle = 'Nurse is on the way';
            $notifMessage = "Your nurse is currently en route to your location for your {$serviceName} appointment.";
            break;
        case 'arrived':
            $notifTitle = 'Nurse has arrived';
            $notifMessage = "Your nurse has arrived at your location for your {$serviceName} appointment.";
            break;
        case 'in_progress':
            $notifTitle = 'Service started';
            $notifMessage = "Your {$serviceName} service has officially started.";
            break;
        case 'completed':
            $notifTitle = 'Service completed';
            $notifMessage = "Your {$serviceName} service has been successfully completed. Thank you for choosing StepCare!";
            break;
    }

    // Generate the link to the user's appointment details page
    $linkURL = '/StepCare/user/appointments/viewDetails/viewDetails.php?id=' . $appointmentID;

    // Insert the notification
    $stmt = $pdo->prepare("
        INSERT INTO notifications (recipientType, recipientID, senderType, senderID, title, message, type, linkURL)
        VALUES ('user', ?, 'nurse', ?, ?, ?, 'appointment', ?)
    ");
    $stmt->execute([
        $targetUserID, 
        $nurseID, 
        $notifTitle, 
        $notifMessage, 
        $linkURL
    ]);

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>