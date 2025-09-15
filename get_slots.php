<?php
require_once 'config.php';

$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;
$date = $_GET['date'] ?? null;

if ($date) {
    // Handle single-date request
    $sql = "SELECT 
                DATE_FORMAT(STR_TO_DATE(appointment_time, '%H:%i:%s'), '%l:%i %p') AS slot,
                COUNT(*) AS total
            FROM appointments
            WHERE appointment_date = ?
              AND appointment_type = 'dental'
              AND status = 'Pending'
            GROUP BY slot";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $date);
} elseif ($start_date && $end_date) {
    // Handle date-range request
    $sql = "SELECT 
                appointment_date,
                DATE_FORMAT(STR_TO_DATE(appointment_time, '%H:%i:%s'), '%l:%i %p') AS slot,
                COUNT(*) AS total
            FROM appointments
            WHERE appointment_date BETWEEN ? AND ?
              AND appointment_type = 'dental'
                  AND status = 'Pending'
            GROUP BY appointment_date, slot";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing date or date range parameters']);
    exit;
}

$stmt->execute();
$res = $stmt->get_result();

$data = [];
if ($date) {
    // Single-date response: { "8:00 AM": count, ... }
    while ($row = $res->fetch_assoc()) {
        $data[$row['slot']] = (int)$row['total'];
    }
} else {
    // Date-range response: { "YYYY-MM-DD": { "8:00 AM": count, ... }, ... }
    while ($row = $res->fetch_assoc()) {
        $date = $row['appointment_date'];
        if (!isset($data[$date])) {
            $data[$date] = [];
        }
        $data[$date][$row['slot']] = (int)$row['total'];
    }
}

header('Content-Type: application/json');
echo json_encode($data);
$stmt->close();
$conn->close();
?>