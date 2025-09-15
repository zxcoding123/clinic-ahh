<?php
require 'config.php';

function sendResponse($success, $message)
{
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method.');
}

$user_id = $_POST['user_id'];
$child_id = $_POST['child_id'] ?? null;
$admin_id = $_POST['admin_id'];

if (!isset($_FILES['file_name']) || $_FILES['file_name']['error'] !== UPLOAD_ERR_OK) {
    sendResponse(false, 'PDF upload failed.');
}

$upload_dir = 'Uploads/medical_documents/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$file_name = uniqid('cert_') . '.pdf';
$target_path = $upload_dir . $file_name;

if (!move_uploaded_file($_FILES['file_name']['tmp_name'], $target_path)) {
    sendResponse(false, 'Failed to save uploaded PDF.');
}

// Fetch user email
$stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$email = $result->fetch_assoc()['email'] ?? '';
$stmt->close();

// Log the certificate
$stmt = $conn->prepare("
    INSERT INTO certificate_logs 
    (user_id, child_id, admin_id, file_path, recipient_email, sent_at) 
    VALUES (?, ?, ?, ?, ?, NOW())
");
$child_id_nullable = $child_id > 0 ? $child_id : null;
$stmt->bind_param("iiiss", $user_id, $child_id_nullable, $admin_id, $target_path, $email);
$stmt->execute();
$stmt->close();

sendResponse(true, 'Certificate uploaded and logged.');
