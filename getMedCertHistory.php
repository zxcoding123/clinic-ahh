<?php
require_once 'config.php';

// Check if user_id is provided
if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid user ID']);
    exit();
}

$user_id = intval($_GET['user_id']);

// Fetch medical certificate history
$sql = "
    SELECT 
        id,
        submitted_at,
        reason,
        file_path,
        original_file_name
    FROM medical_documents
    WHERE user_id = ? 
    AND document_type = 'medical_certificate'
    AND status = 'completed'
    ORDER BY submitted_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$history = [];
while ($row = $result->fetch_assoc()) {
    $history[] = [
        'id' => $row['id'],
        'submitted_at' => $row['submitted_at'] ? date('Y-m-d', strtotime($row['submitted_at'])) : 'N/A',
        'reason' => $row['reason'] ?: 'Not Specified',
        'ext' => pathinfo($row['original_file_name'], PATHINFO_EXTENSION)
    ];
}

$stmt->close();
$conn->close();

// Return JSON response
header('Content-Type: application/json');
echo json_encode($history);
?>