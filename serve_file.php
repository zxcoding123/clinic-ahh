<?php
session_start();
require_once 'config.php';

// Set timezone
date_default_timezone_set('Asia/Manila');

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/php_errors.log'); // Adjust path if needed
// ini_set('display_errors', 1); // Uncomment for debugging

// // Check if user is logged in
// if (!isset($_SESSION['user_id'])) {
//     error_log('Unauthorized access attempt in serve_file.php');
//     http_response_code(403);
//     echo json_encode(['error' => 'Unauthorized access.']);
//     exit;
// }

// Validate request
if (!isset($_GET['id']) && !isset($_GET['path'])) {
    error_log('Missing document ID or path in serve_file.php');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request.']);
    exit;
}

$filePath = null;
$originalFileName = null;
$ext = null;
$baseDir = realpath(__DIR__ . '/Uploads/medical_documents'); // Base directory for security

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];

    // Try fetching from certificate_logs first
    $query = "SELECT file_path FROM certificate_logs WHERE id = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Prepare failed for certificate_logs: " . $conn->error);
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error.']);
        exit;
    }

    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        error_log("Execute failed for certificate_logs: " . $stmt->error);
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error.']);
        exit;
    }

    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $document = $result->fetch_assoc();
        $filePath = $document['file_path'];
        $originalFileName = basename($filePath);
        $ext = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
        error_log("Found document in certificate_logs: ID=$id, Path=$filePath");
    } else {
        // If not found in certificate_logs, try medical_documents
        $query = "SELECT file_path, original_file_name, document_type FROM medical_documents WHERE id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("Prepare failed for medical_documents: " . $conn->error);
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error.']);
            exit;
        }

        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) {
            error_log("Execute failed for medical_documents: " . $stmt->error);
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error.']);
            exit;
        }

        $result = $stmt->get_result();
        $document = $result->fetch_assoc();
        if (!$document) {
            error_log("Document not found for ID: $id");
            http_response_code(404);
            echo json_encode(['error' => 'Document not found.']);
            exit;
        }

        $filePath = $document['file_path'];
        $originalFileName = $document['original_file_name'] ?? 'document';
        $ext = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
        error_log("Found document in medical_documents: ID=$id, Path=$filePath");
    }
    $stmt->close();
} elseif (isset($_GET['path'])) {
    // Handle by direct path (e.g., fallback for legacy certificate_logs paths)
    $filePath = urldecode($_GET['path']);
    $filePath = preg_replace('/^\.\.\//', '', $filePath); // Clean '../'
    $originalFileName = basename($filePath);
    $ext = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
}

// Clean path (prevent ../ attacks)
$filePath = preg_replace('/^\.\.\//', '', $filePath);

// Normalize case: your DB stores 'uploads/' but directory is 'Uploads/'
$filePath = str_ireplace('uploads/', 'Uploads/', $filePath);

// Build absolute path relative to this script
$absoluteFilePath = realpath(__DIR__ . '/' . $filePath);

error_log("File path resolution: Original=$filePath, Resolved=$absoluteFilePath");

// If not found, try basename fallback
if (!$absoluteFilePath || !file_exists($absoluteFilePath)) {
    $originalPath = __DIR__ . '/Uploads/medical_documents/' . basename($filePath);
    error_log("Trying original path: $originalPath");
    if (file_exists($originalPath)) {
        $absoluteFilePath = realpath($originalPath);
        error_log("File found at original path: $absoluteFilePath");
    } else {
        error_log("File not found: $filePath (resolved: $absoluteFilePath, original: $originalPath)");
        http_response_code(404);
        echo json_encode(['error' => 'File not found on server. The file may have been deleted or moved.']);
        exit;
    }
}

// Security check - ensure file is within the allowed directory
$allowedDir = realpath(__DIR__ . '/Uploads/medical_documents');
// if (strpos($absoluteFilePath, $allowedDir) !== 0) {
//     error_log("Unauthorized file access attempt: $filePath (resolved: $absoluteFilePath)");
//     http_response_code(403);
//     echo json_encode(['error' => 'Unauthorized file access.']);
//     exit;
// }

// Check if file exists
if (!file_exists($absoluteFilePath)) {
    error_log("File not found: $filePath (resolved: $absoluteFilePath)");
    http_response_code(404);
    echo json_encode(['error' => 'File not found on server.']);
    exit;
}

// MIME types
$mimeTypes = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];
$mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';

// Serve file
header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . basename($originalFileName) . '"');
header('Content-Length: ' . filesize($absoluteFilePath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

readfile($absoluteFilePath);
exit;

$conn->close();
