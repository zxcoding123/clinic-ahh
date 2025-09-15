<?php
require_once 'config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];

$query = "SELECT COUNT(*) as unread_count 
          FROM notifications_admin
          WHERE user_id = ? AND status = 'unread'";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();

echo json_encode($result->fetch_assoc());