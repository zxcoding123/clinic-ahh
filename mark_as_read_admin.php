<?php
require_once 'config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$notificationId = $data['id'] ?? null;
$userId = $_SESSION['user_id'];

if (!$notificationId) {
    echo json_encode(['success' => false, 'error' => 'Invalid notification ID']);
    exit;
}

// Verify the notification belongs to the user
$verifyQuery = "SELECT id FROM notifications_admin WHERE id = ? AND user_id = ?";
$verifyStmt = $conn->prepare($verifyQuery);
$verifyStmt->bind_param('ii', $notificationId, $userId);
$verifyStmt->execute();
$verifyStmt->store_result();

if ($verifyStmt->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Notification not found']);
    exit;
}

// Mark as read
$updateQuery = "UPDATE notifications_admin SET status = 'read' WHERE id = ?";
$updateStmt = $conn->prepare($updateQuery);
$updateStmt->bind_param('i', $notificationId);
$success = $updateStmt->execute();

echo json_encode(['success' => $success]);