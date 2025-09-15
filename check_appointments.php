<?php
session_start();
$conn = new mysqli("localhost", "root", "", "clinic");

$user_id = $_SESSION['user_id'];
$today = date("Y-m-d");
$dayOfWeek = date('N', strtotime($today));

// If today is Sunday (7), start next day as Monday
if ($dayOfWeek == 7) {
    $week_start = date("Y-m-d", strtotime($today . ' +1 day')); 
    $week_end   = date("Y-m-d", strtotime($week_start . ' +6 days'));
} else {
    $week_start = date("Y-m-d", strtotime($today . ' -' . ($dayOfWeek - 1) . ' days'));
    $week_end   = date("Y-m-d", strtotime($week_start . ' +6 days'));
}

$sql = "SELECT COUNT(*) AS total 
        FROM appointments 
        WHERE user_id = '$user_id' 
        AND appointment_date BETWEEN '$week_start' AND '$week_end'";

$result = $conn->query($sql);
$row = $result->fetch_assoc();
$appointments_count = $row['total'] ?? 0;

$response = [
    "count" => $appointments_count,
    "remaining" => max(0, 3 - $appointments_count)
];

header('Content-Type: application/json');
echo json_encode($response);
