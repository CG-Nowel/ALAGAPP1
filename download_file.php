<?php
require 'db_connect.php';

// Auth check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo "Unauthorized";
    exit;
}

// Input: ?file_id=NN
$file_id = isset($_GET['file_id']) ? intval($_GET['file_id']) : 0;
if ($file_id <= 0) {
    http_response_code(400);
    echo "Invalid request";
    exit;
}

// Fetch file metadata
$sql = "SELECT pf.*, p.parent_id FROM patient_files pf JOIN patients p ON pf.patient_id = p.id WHERE pf.id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $file_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$file = mysqli_fetch_assoc($res);

if (!$file) {
    http_response_code(404);
    echo "File not found";
    exit;
}

// Authorization: parent who owns the child, admins, and doctors with a relationship to the patient
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'] ?? null;

$allowed = false;
if ($user_type === 'ADMIN') {
    $allowed = true;
}
if ($user_type === 'DOCTOR' || $user_type === 'DOCTOR_OWNER') {
    // Only allow doctors who have an appointment with this patient
    $doc_check = mysqli_prepare($conn, "SELECT 1 FROM appointments WHERE doctor_id = ? AND patient_id = ? LIMIT 1");
    mysqli_stmt_bind_param($doc_check, "ii", $user_id, $file['patient_id']);
    mysqli_stmt_execute($doc_check);
    $doc_result = mysqli_stmt_get_result($doc_check);
    if (mysqli_num_rows($doc_result) > 0) {
        $allowed = true;
    }
    mysqli_stmt_close($doc_check);
}
if (intval($file['parent_id']) === intval($user_id)) {
    $allowed = true;
}

if (!$allowed) {
    http_response_code(403);
    echo "Access denied";
    exit;
}

// Build path to stored file - basename() prevents path traversal
$upload_base = __DIR__ . '/uploads/patient_files';
$stored = $upload_base . '/' . basename($file['stored_filename']);

if (!is_file($stored)) {
    http_response_code(404);
    echo "File missing on server";
    exit;
}

// Send file with safe headers
$mime = $file['mime_type'] ?: 'application/octet-stream';
$original = $file['original_filename'];

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($original) . '"');
header('Content-Length: ' . (int)$file['file_size']);
header('Cache-Control: private, max-age=0');
readfile($stored);
exit;
?>
