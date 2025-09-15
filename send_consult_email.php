<?php
session_start();
require_once 'config.php';
require_once 'mailer.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Log request details
error_log("[" . date('Y-m-d H:i:s') . "] Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("[" . date('Y-m-d H:i:s') . "] Headers: " . print_r(getallheaders(), true));
error_log("[" . date('Y-m-d H:i:s') . "] POST Data: " . print_r($_POST, true));
error_log("[" . date('Y-m-d H:i:s') . "] Received CSRF Token: " . ($_POST['csrf_token'] ?? 'Not set'));
error_log("[" . date('Y-m-d H:i:s') . "] PHPSESSID Cookie: " . ($_COOKIE['PHPSESSID'] ?? 'Not set'));
error_log("[" . date('Y-m-d H:i:s') . "] Session User ID: " . ($_SESSION['user_id'] ?? 'Not set'));

header('Content-Type: application/json');

// Handle CORS preflight (OPTIONS) requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: http://localhost'); // Replace with your domain
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
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
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';
$csrf_token = isset($_POST['csrf_token']) ? trim($_POST['csrf_token']) : '';

if ( !$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$message) {
    error_log("Invalid input data: user_id=$user_id, admin_user_id=$admin_user_id, email=$email, message=" . (empty($message) ? 'empty' : 'set') . ", csrf_token=" . (empty($csrf_token) ? 'empty' : 'set'));
    echo json_encode(['success' => false, 'message' => 'Invalid or missing input data']);
    exit;
}

// // Validate CSRF token from database
// try {
//     $sql = "SELECT token FROM csrf_tokens WHERE user_id = ? AND token = ? AND created_at > NOW() - INTERVAL 1 HOUR";
//     $stmt = $conn->prepare($sql);
//     $stmt->bind_param('is', $admin_user_id, $csrf_token);
//     $stmt->execute();
//     $result = $stmt->get_result();
//     if ($result->num_rows === 0) {
//         error_log("CSRF token validation failed: Received=$csrf_token");
//         echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
//         $stmt->close();
//         exit;
//     }
//     $stmt->close();
// } catch (Exception $e) {
//     error_log("CSRF token validation error: " . $e->getMessage());
//     echo json_encode(['success' => false, 'message' => 'CSRF validation error']);
//     $conn->close();
//     exit;
// }

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
        error_log("Invalid user or email: user_id=$user_id, child_id=" . ($child_id ?? 'null') . ", email=$email");
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

// Check for existing consultation advice
try {
    $sql = "SELECT id FROM consultation_advice WHERE user_id = ? AND status IN ('Pending', 'Sent')";
    $params = [$user_id];
    $types = 'i';
    if ($child_id !== null) {
        $sql .= " AND child_id = ?";
        $params[] = $child_id;
        $types .= 'i';
    } else {
        $sql .= " AND child_id IS NULL";
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing_advice = $result->fetch_assoc();
    $stmt->close();

    $conn->begin_transaction();

    if ($existing_advice) {
        // Fix: Add = ? for consultation_message
        $sql = "UPDATE consultation_advice SET reason = ?, admin_user_id = ?, date_advised = NOW(), status = 'Pending', updated_at = NOW(), consultation_message = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sisi', $reason, $admin_user_id, $message, $existing_advice['id']);

        $stmt->execute();
        $stmt->close();
    } else {
        // Insert new record
        $sql = "INSERT INTO consultation_advice (user_id, child_id, admin_user_id, reason, status, date_advised, created_at, updated_at, consultation_message) 
                VALUES (?, ?, ?, ?, 'Pending', NOW(), NOW(), NOW(), ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iiiss', $user_id, $child_id, $admin_user_id, $reason, $message);
        $stmt->execute();
        $stmt->close();
    }
} catch (Exception $e) {
    $conn->rollback();
    error_log("Failed to process consultation advice: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to process consultation advice']);
    $conn->close();
    exit;
}

// Send email
$subject = 'Consultation Advice from WMSU Health Services';
$body = nl2br(htmlspecialchars($message));

if (send_email($email, $subject, $body)) {
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

    $conn->commit();

    error_log("Email sent successfully to $email, new CSRF token: $new_csrf_token");
    echo json_encode([
        'success' => true,
        'message' => 'Consultation advice sent successfully',
        'new_csrf_token' => $new_csrf_token
    ]);
} else {
    $conn->rollback();
    error_log("Failed to send email to $email");
    echo json_encode(['success' => false, 'message' => 'Failed to send email']);
}

$conn->close();
