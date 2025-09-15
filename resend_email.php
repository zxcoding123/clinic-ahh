<?php
require_once 'config.php';
require_once 'mailer.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Start output buffering to prevent accidental output
ob_start();

// Log request details
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Headers: " . print_r(getallheaders(), true));
error_log("POST Data: " . print_r($_POST, true));
error_log("Received CSRF Token: " . ($_POST['csrf_token'] ?? 'Not set'));
error_log("PHPSESSID Cookie: " . ($_COOKIE['PHPSESSID'] ?? 'Not set'));

// Clear any accidental output
ob_clean();

header('Content-Type: application/json');

// Handle CORS preflight (OPTIONS) requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *'); 
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit;
}

// Check POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Validate inputs
$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$admin_user_id = isset($_POST['admin_user_id']) ? (int)$_POST['admin_user_id'] : 0;
$child_id = isset($_POST['child_id']) && !empty($_POST['child_id']) ? (int)$_POST['child_id'] : null;
$email = isset($_POST['email']) ? filter_var($_POST['email'], FILTER_SANITIZE_EMAIL) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';
$csrf_token = isset($_POST['csrf_token']) ? trim($_POST['csrf_token']) : '';

if (!$user_id || !$admin_user_id || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$message || !$csrf_token) {
    error_log("Invalid input data");
    echo json_encode(['success' => false, 'message' => 'Invalid or missing input data']);
    exit;
}

// Validate CSRF token
try {
    $sql = "SELECT token FROM csrf_tokens WHERE user_id = ? AND created_at > NOW() - INTERVAL 1 HOUR";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $admin_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $valid_token = false;

    while ($row = $result->fetch_assoc()) {
        if (hash_equals($row['token'], $csrf_token)) {
            $valid_token = true;
            break;
        }
    }
    $stmt->close();

    if (!$valid_token) {
        error_log("CSRF token validation failed");
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    // Delete used CSRF token
    $sql = "DELETE FROM csrf_tokens WHERE user_id = ? AND token = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('is', $admin_user_id, $csrf_token);
    $stmt->execute();
    $stmt->close();
} catch (Exception $e) {
    error_log("CSRF token validation error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'CSRF validation error']);
    $conn->close();
    exit;
}

// Verify user and email
try {
    if ($child_id) {
        $sql = "SELECT u.email 
                FROM users u
                JOIN patients p ON u.id = p.user_id
                JOIN children c ON c.parent_id = p.id
                WHERE u.id = ? AND c.id = ? AND u.email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iis', $user_id, $child_id, $email);
    } else {
        $sql = "SELECT email FROM users WHERE id = ? AND email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('is', $user_id, $email);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        error_log("Invalid user or email");
        echo json_encode(['success' => false, 'message' => 'Invalid user or email']);
        $stmt->close();
        $conn->close();
        exit;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
    $conn->close();
    exit;
}

// Send email
$subject = 'Consultation Advice from WMSU Health Services';
$body = nl2br(htmlspecialchars($message));

if (send_email($email, $subject, $body)) {
    // UPDATE existing consultation advice record
    try {
        $sql = "UPDATE consultation_advice 
                SET reason = ?, status = 'Pending', date_advised = NOW(), updated_at = NOW() 
                WHERE user_id = ? AND admin_user_id = ? 
                AND (child_id = ? OR (child_id IS NULL AND ? IS NULL))";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('siiii', $message, $user_id, $admin_user_id, $child_id, $child_id);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            error_log("No existing record found to update");
            echo json_encode(['success' => false, 'message' => 'No existing consultation record found to update']);
            $stmt->close();
            $conn->close();
            exit;
        }
        
        $stmt->close();
    } catch (Exception $e) {
        error_log("Failed to update consultation advice: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to update consultation advice']);
        $conn->close();
        exit;
    }

    // Generate new CSRF token
    $new_csrf_token = bin2hex(random_bytes(32));
    try {
        $sql = "INSERT INTO csrf_tokens (user_id, token, created_at) VALUES (?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE token = ?, created_at = NOW()";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iss', $admin_user_id, $new_csrf_token, $new_csrf_token);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        error_log("Failed to store new CSRF token: " . $e->getMessage());
    }

    error_log("Email sent successfully to $email");
    echo json_encode([
        'success' => true,
        'message' => 'Consultation advice updated successfully',
        'new_csrf_token' => $new_csrf_token
    ]);
} else {
    error_log("Failed to send email to $email");
    echo json_encode(['success' => false, 'message' => 'Failed to send email']);
}

$conn->close();
?>