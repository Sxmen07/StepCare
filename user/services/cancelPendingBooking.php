<?php
session_start();
unset($_SESSION['pending_booking']);
header('Content-Type: application/json');
echo json_encode(['success' => true]);
exit;