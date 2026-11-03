<?php
// English comments only
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/../../config/db_connect.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'fetch_users':
        header('Content-Type: application/json; charset=utf-8');
        fetchUsersList($pdo);
        break;

    case 'toggle_ban':
        header('Content-Type: application/json; charset=utf-8');
        toggleUserBanByIC($pdo);
        break;

    case 'batch_ban':
        header('Content-Type: application/json; charset=utf-8');
        batchBanUsersByIC($pdo);
        break;

    case 'export_excel':
        exportUsersToExcel($pdo);
        break;

    default:
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'Invalid action parameter']);
        break;
}

// 1. Fetch user accounts including IC & banReason
function fetchUsersList($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT userID, firstName, lastName, email, IC, phoneNumber, gender, dateOfBirth, 
                   accountStatus, banReason, userPhotoURL, createdAt 
            FROM users 
            ORDER BY userID DESC
        ");

        $users = [];
        if ($stmt) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $rawPhoto = $row['userPhotoURL'] ?? '';
                $cleanPhoto = !empty($rawPhoto) ? basename($rawPhoto) : '';
                $avatarPath = !empty($cleanPhoto) ? "/StepCare/images/avatars/user/{$cleanPhoto}" : null;

                $users[] = [
                    'id' => 'USR-' . str_pad($row['userID'], 4, '0', STR_PAD_LEFT),
                    'raw_id' => (int)$row['userID'],
                    'first_name' => $row['firstName'] ?? '',
                    'last_name' => $row['lastName'] ?? '',
                    'name' => trim(($row['firstName'] ?? '') . ' ' . ($row['lastName'] ?? '')),
                    'email' => $row['email'],
                    'ic' => $row['IC'] ?? 'N/A',
                    'phone' => $row['phoneNumber'] ?? 'N/A',
                    'gender' => $row['gender'] ?? 'N/A',
                    'dob' => $row['dateOfBirth'] ?? 'N/A',
                    'status' => $row['accountStatus'] ?? 'Active',
                    'ban_reason' => $row['banReason'] ?? '',
                    'photo' => $avatarPath,
                    'created_at' => !empty($row['createdAt']) ? date('Y-m-d', strtotime($row['createdAt'])) : 'N/A'
                ];
            }
        }

        echo json_encode(['status' => 'success', 'data' => $users]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 2. Toggle Ban / Unban with Reason support
function toggleUserBanByIC($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $ic = trim($input['ic'] ?? '');
        $targetStatus = trim($input['status'] ?? '');
        $reason = trim($input['reason'] ?? '');

        if (empty($ic) || !in_array($targetStatus, ['Active', 'Banned'])) {
            echo json_encode(['status' => 'error', 'message' => 'Valid IC number and target status are required.']);
            return;
        }

        if ($targetStatus === 'Banned') {
            $stmt = $pdo->prepare("UPDATE users SET accountStatus = 'Banned', banReason = ? WHERE IC = ?");
            $stmt->execute([$reason ?: 'Violated community terms', $ic]);
        } else {
            // Clear ban reason upon reactivation
            $stmt = $pdo->prepare("UPDATE users SET accountStatus = 'Active', banReason = NULL WHERE IC = ?");
            $stmt->execute([$ic]);
        }

        $statusText = ($targetStatus === 'Banned') ? 'banned' : 'reactivated';
        echo json_encode([
            'status' => 'success',
            'message' => "All accounts linked to IC {$ic} have been {$statusText}."
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 3. Batch Ban multiple users with a unified Reason
function batchBanUsersByIC($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $ics = $input['ics'] ?? [];
        $reason = trim($input['reason'] ?? 'Batch administrative sanction');

        if (!is_array($ics) || empty($ics)) {
            echo json_encode(['status' => 'error', 'message' => 'No ICs provided for batch ban.']);
            return;
        }

        $cleanIcs = array_filter(array_map('trim', $ics), function($item) {
            return !empty($item) && $item !== 'N/A';
        });

        if (empty($cleanIcs)) {
            echo json_encode(['status' => 'error', 'message' => 'Valid IC numbers are required.']);
            return;
        }

        $placeholders = implode(',', array_fill(0, count($cleanIcs), '?'));
        $stmt = $pdo->prepare("UPDATE users SET accountStatus = 'Banned', banReason = ? WHERE IC IN ($placeholders)");
        $params = array_merge([$reason], array_values($cleanIcs));
        $stmt->execute($params);

        $count = $stmt->rowCount();
        echo json_encode([
            'status' => 'success',
            'message' => "Successfully banned all accounts for the selected {$count} identity record(s)."
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 4. Export users to Excel CSV (Includes Ban Reason column)
function exportUsersToExcel($pdo) {
    $filterStatus = $_GET['status'] ?? 'All';
    $filename = "StepCare_Users_Export_" . date('Ymd_His') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

    fputcsv($output, ['User ID', 'Full Name', 'IC / Passport', 'Email', 'Phone Number', 'Gender', 'Date of Birth', 'Account Status', 'Ban Reason', 'Registered Date']);

    try {
        if ($filterStatus === 'Active' || $filterStatus === 'Banned') {
            $stmt = $pdo->prepare("SELECT userID, firstName, lastName, IC, email, phoneNumber, gender, dateOfBirth, accountStatus, banReason, createdAt FROM users WHERE accountStatus = ? ORDER BY userID ASC");
            $stmt->execute([$filterStatus]);
        } else {
            $stmt = $pdo->query("SELECT userID, firstName, lastName, IC, email, phoneNumber, gender, dateOfBirth, accountStatus, banReason, createdAt FROM users ORDER BY userID ASC");
        }

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                'USR-' . str_pad($row['userID'], 4, '0', STR_PAD_LEFT),
                trim(($row['firstName'] ?? '') . ' ' . ($row['lastName'] ?? '')),
                $row['IC'] ?? 'N/A',
                $row['email'] ?? 'N/A',
                $row['phoneNumber'] ?? 'N/A',
                $row['gender'] ?? 'N/A',
                $row['dateOfBirth'] ?? 'N/A',
                $row['accountStatus'] ?? 'Active',
                $row['banReason'] ?? 'N/A',
                $row['createdAt'] ?? 'N/A'
            ]);
        }
    } catch (Exception $e) {
        fputcsv($output, ['Export Error: ' . $e->getMessage()]);
    }

    fclose($output);
    exit;
}
?>