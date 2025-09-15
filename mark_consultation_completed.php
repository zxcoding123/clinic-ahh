<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    error_log("CSRF token validation failed: Expected " . ($_SESSION['csrf_token'] ?? 'none') . ", Got " . ($_POST['csrf_token'] ?? 'none'));
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

if (!isset($_POST['session_id']) || $_POST['session_id'] !== session_id()) {
    error_log("Session ID validation failed: Expected " . session_id() . ", Got " . ($_POST['session_id'] ?? 'none'));
    echo json_encode(['success' => false, 'message' => 'Invalid session']);
    exit();
}

$consult_id = isset($_POST['consult_id']) ? (int)$_POST['consult_id'] : null;

if (!$consult_id) {
    echo json_encode(['success' => false, 'message' => 'Missing consultation ID']);
    exit();
}

try {
    $sql = "UPDATE consultation_records SET status = 'completed' WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $consult_id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("Error marking consultation as completed: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>