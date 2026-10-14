<?php
// admin/adminUploadImage.php

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

/* ---------- Auth ---------- */
if (empty($_SESSION['userID']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['ok' => false, 'message' => 'Not authorized']);
    exit;
}

/* ---------- Method ---------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'message' => 'POST required']);
    exit;
}

/* ---------- File ---------- */
if (empty($_FILES['image'])) {
    echo json_encode(['ok' => false, 'message' => 'No file in request']);
    exit;
}

$file = $_FILES['image'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    $errMap = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize (' . ini_get('upload_max_filesize') . ')',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE',
        UPLOAD_ERR_PARTIAL    => 'Upload was partial',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'No temp folder available',
        UPLOAD_ERR_CANT_WRITE => 'Cannot write to disk',
        UPLOAD_ERR_EXTENSION  => 'Upload stopped by extension',
    ];
    echo json_encode([
        'ok'      => false,
        'message' => $errMap[$file['error']] ?? 'Unknown upload error',
    ]);
    exit;
}

/* ---------- Size (max 4 MB) ---------- */
if ($file['size'] > 4 * 1024 * 1024) {
    echo json_encode(['ok' => false, 'message' => 'File too large (max 4 MB)']);
    exit;
}

/* ---------- Extension ---------- */
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['jpg','jpeg','png','webp','gif'];

if (!in_array($ext, $allowed, true)) {
    echo json_encode(['ok' => false, 'message' => 'Only JPG, PNG, WEBP, GIF allowed']);
    exit;
}
if ($ext === 'jpeg') $ext = 'jpg';

/* ---------- Target folder ----------
   This file lives at: StepCare/admin/adminUploadImage.php
   Target folder:      StepCare/images/services/
*/
$uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'services';

/* ---------- Folder MUST exist (we do NOT create it) ---------- */
if (!is_dir($uploadDir)) {
    echo json_encode([
        'ok'      => false,
        'message' => 'Upload folder missing: ' . $uploadDir
                   . ' — please create StepCare/images/services/ manually.',
    ]);
    exit;
}

if (!is_writable($uploadDir)) {
    echo json_encode([
        'ok'      => false,
        'message' => 'Upload folder is not writable',
        'debug'   => ['dir' => $uploadDir],
    ]);
    exit;
}

/* ---------- Save ---------- */
$filename = 'svc_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$target   = $uploadDir . DIRECTORY_SEPARATOR . $filename;

if (!move_uploaded_file($file['tmp_name'], $target)) {
    echo json_encode(['ok' => false, 'message' => 'Could not save the uploaded file']);
    exit;
}

/* ---------- Success ---------- */
echo json_encode([
    'ok'       => true,
    'path'     => '../../images/services/' . $filename,
    'filename' => $filename,
]);