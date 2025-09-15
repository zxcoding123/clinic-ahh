<?php
session_start();
require_once 'config.php';
require_once 'mailer.php';

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Enhanced logging
error_log("[" . date('Y-m-d H:i:s') . "] Script started: cancel_consultation.php");
error_log("[" . date('Y-m-d H:i:s') . "] Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("[" . date('Y-m-d H:i:s') . "] POST Data: " . json_encode($_POST));
error_log("[" . date('Y-m-d H:i:s') . "] Session Data: " . print_r($_SESSION, true));

header('Content-Type: application/json');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: https://wmsuhealthservices.site');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
    http_response_code(204);
    exit;
}

// Set response headers for main request
header('Access-Control-Allow-Origin: https://wmsuhealthservices.site');
header('Access-Control-Allow-Credentials: true');

// Check for POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("[" . date('Y-m-d H:i:s') . "] Invalid request method");
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Validate inputs
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$csrf_token = isset($_POST['csrf_token']) ? trim($_POST['csrf_token']) : '';

if ($id <= 0 || !preg_match('/^[a-f0-9]{64}$/i', $csrf_token)) {
    error_log("[" . date('Y-m-d H:i:s') . "] Invalid input: id=$id, csrf_token=" . (empty($csrf_token) ? 'empty' : 'invalid format'));
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid or missing input data']);
    exit;
}

// Validate session
if (!isset($_SESSION['user_id'])) {
    error_log("[" . date('Y-m-d H:i:s') . "] Session invalid - user not authenticated");
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

$admin_user_id = (int)$_SESSION['user_id'];

// Check database connection
if (!$conn) {
    error_log("[" . date('Y-m-d H:i:s') . "] Database connection failed");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit;
}

try {
    // Validate CSRF token
    $conn->query("DELETE FROM csrf_tokens WHERE created_at <= NOW() - INTERVAL 1 HOUR");

    $sql = "SELECT token FROM csrf_tokens WHERE user_id = ? AND token = ? AND created_at > NOW() - INTERVAL 1 HOUR";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare CSRF validation query: " . $conn->error);
    }
    $stmt->bind_param('is', $admin_user_id, $csrf_token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        error_log("[" . date('Y-m-d H:i:s') . "] CSRF token validation failed");
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        $stmt->close();
        exit;
    }
    $stmt->close();

    $conn->begin_transaction();

    // Delete consultation advice
    $sql = "DELETE FROM consultation_advice WHERE id = ? AND status IN ('Pending', 'Sent') AND admin_user_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare delete query: " . $conn->error);
    }
    $stmt->bind_param('ii', $id, $admin_user_id);
    $stmt->execute();
    $affected_rows = $stmt->affected_rows;
    $stmt->close();

    if ($affected_rows === 0) {
        $conn->rollback();
        error_log("[" . date('Y-m-d H:i:s') . "] Consultation advice not found or already cancelled");
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Consultation advice not found or already cancelled']);
        $conn->close();
        exit;
    }

    // Generate and store new CSRF token
    $new_csrf_token = bin2hex(random_bytes(32));
    
    $sql = "INSERT INTO csrf_tokens (user_id, token, created_at) VALUES (?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE token = ?, created_at = NOW()";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare CSRF token insert query: " . $conn->error);
    }
    $stmt->bind_param('iss', $admin_user_id, $new_csrf_token, $new_csrf_token);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    error_log("[" . date('Y-m-d H:i:s') . "] Consultation advice deleted successfully");
    echo json_encode([
        'success' => true, 
        'message' => 'Consultation advice deleted successfully', 
        'new_csrf_token' => $new_csrf_token
    ]);
} catch (Exception $e) {
    $conn->rollback();
    error_log("[" . date('Y-m-d H:i:s') . "] Error deleting consultation advice: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred while deleting the consultation advice']);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
?>