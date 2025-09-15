<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Log incoming request data
error_log("record_consultation.php: Incoming POST data: " . print_r($_POST, true));
error_log("record_consultation.php: Session ID: " . session_id());
error_log("record_consultation.php: Session CSRF Token: " . ($_SESSION['csrf_token'] ?? 'Not set'));
error_log("record_consultation.php: PHPSESSID Cookie: " . ($_COOKIE['PHPSESSID'] ?? 'Not set'));

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
    error_log("CSRF token missing: POST csrf_token = " . ($_POST['csrf_token'] ?? 'none') . ", Session csrf_token = " . ($_SESSION['csrf_token'] ?? 'none'));
    echo json_encode(['success' => false, 'message' => 'CSRF token missing']);
    exit();
}

if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    error_log("CSRF token validation failed: Expected " . $_SESSION['csrf_token'] . ", Got " . ($_POST['csrf_token'] ?? 'none'));
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

// Verify session ID (relaxed for debugging; re-enable after testing)
// if (!isset($_POST['session_id']) || $_POST['session_id'] !== session_id()) {
//     error_log("Session ID validation failed: Expected " . session_id() . ", Got " . ($_POST['session_id'] ?? 'none'));
//     echo json_encode(['success' => false, 'message' => 'Invalid session']);
//     exit();
// }

// Verify input data
$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : null;
$email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
$reason = filter_var($_POST['message'] ?? '', FILTER_SANITIZE_STRING);
$admin_user_id = isset($_POST['admin_user_id']) ? (int)$_POST['admin_user_id'] : null;

if (!$user_id || !$email || !$reason || !$admin_user_id) {
    error_log("Missing required fields: user_id=$user_id, email=$email, reason=$reason, admin_user_id=$admin_user_id");
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    // Insert into consultation_records
    $sql = "INSERT INTO consultation_records (user_id, email, reason, advised_at, admin_user_id) VALUES (?, ?, ?, NOW(), ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('issi', $user_id, $email, $reason, $admin_user_id);
    $stmt->execute();
    $consult_id = $conn->insert_id;
    $stmt->close();

    // Delete related medical documents
    $sql = "DELETE FROM medical_documents WHERE user_id = ? AND document_type IN (
        'chest_xray_results',
        'complete_blood_count_results',
        'blood_typing_results',
        'urinalysis_results',
        'drug_test_results',
        'hepatitis_b_surface_antigen_test'
    )";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();

    // Send email (using PHP's mail function as a placeholder)
    $subject = "Medical Consultation Required";
    $message = "Dear Patient,\n\n" . $reason . "\n\nPlease schedule an appointment at your earliest convenience.\n\nBest regards,\nWMSU Health Services";
    $headers = "From: no-reply@wmsuclinic.com\r\n";
    if (!mail($email, $subject, $message, $headers)) {
        throw new Exception('Failed to send email');
    }

    // Generate new CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $new_csrf_token = $_SESSION['csrf_token'];

    // Update CSRF token in database
    $sql = "INSERT INTO csrf_tokens (user_id, token, created_at) VALUES (?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE token = ?, created_at = NOW()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iss', $admin_user_id, $new_csrf_token, $new_csrf_token);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    echo json_encode(['success' => true, 'new_csrf_token' => $new_csrf_token, 'consult_id' => $consult_id]);
} catch (Exception $e) {
    $conn->rollback();
    error_log("Consultation recording error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>