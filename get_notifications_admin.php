<?php
require_once 'config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];
$limit = 10;

$query = "SELECT id, type, title, description, link, status, created_at 
          FROM notifications_admin
          WHERE user_id = ? 
          ORDER BY created_at DESC 
          LIMIT ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $userId, $limit);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

// Get unread count
$countQuery = "SELECT COUNT(*) as unread_count 
               FROM notifications_admin 
               WHERE user_id = ? AND status = 'unread'";
$countStmt = $conn->prepare($countQuery);
$countStmt->bind_param('i', $userId);
$countStmt->execute();
$countResult = $countStmt->get_result();
$unreadCount = $countResult->fetch_assoc()['unread_count'];

echo json_encode([
    'unread_count' => $unreadCount,
    'notifications' => $notifications
]);