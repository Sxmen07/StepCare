<?php
// StepCare - Self-Contained Nurse Sidebar Badges Endpoint
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

session_start();
require_once __DIR__ . '/../config/db_connect.php';

try {
    $nurseId = $_SESSION['nurseID'] ?? $_SESSION['user_id'] ?? null;

    // 1. Appointments: 该护士今日或待完成的派单预约
    $pendingAppointments = 0;
    if ($nurseId) {
        $stmtAppt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE nurseID = ? AND LOWER(status) IN ('confirmed', 'assigned', 'in_progress') AND appointmentDate = CURDATE()");
        $stmtAppt->execute([$nurseId]);
        $pendingAppointments = (int)$stmtAppt->fetchColumn();
    } else {
        // 如果没有特定 session，查询所有分配但未结案的预约
        $stmtAppt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE LOWER(status) IN ('confirmed', 'assigned') AND appointmentDate >= CURDATE()");
        $pendingAppointments = (int)$stmtAppt->fetchColumn();
    }

    // 2. Applications: 护士自己待审核中的请假或已有审批结果更新
    $pendingApplications = 0;
    if ($nurseId) {
        $stmtOff = $pdo->prepare("SELECT COUNT(*) FROM nurseRequestOffDay WHERE nurseID = ? AND LOWER(status) = 'pending'");
        $stmtOff->execute([$nurseId]);
        $pendingApplications = (int)$stmtOff->fetchColumn();
    } else {
        $stmtOff = $pdo->query("SELECT COUNT(*) FROM nurseRequestOffDay WHERE LOWER(status) = 'pending'");
        $pendingApplications = (int)$stmtOff->fetchColumn();
    }

    // 3. Care Log: 已完成护理但尚未提交/更新 Care Log 记录的单子
    $pendingLogs = 0;
    try {
        $stmtLog = $pdo->query("SELECT COUNT(*) FROM appointments WHERE LOWER(status) = 'completed' AND (careLog IS NULL OR careLog = '')");
        $pendingLogs = (int)$stmtLog->fetchColumn();
    } catch (Exception $ex) {
        $pendingLogs = 0;
    }

    echo json_encode([
        'status' => 'success',
        'counts' => [
            'appointments' => $pendingAppointments,
            'applications' => $pendingApplications,
            'care-log' => $pendingLogs
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'counts' => [
            'appointments' => 0,
            'applications' => 0,
            'care-log' => 0
        ]
    ]);
}