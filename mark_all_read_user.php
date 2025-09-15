<?php
require_once 'config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];

$query = "UPDATE user_notifications SET status = 'read' WHERE user_id = ? AND status = 'unread'";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $userId);
$success = $stmt->execute();

echo json_encode(['success' => $success]);