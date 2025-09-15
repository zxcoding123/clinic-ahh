<?php
require_once 'config.php';
$monthStr = $_GET['month'] ?? date('Y-m'); // e.g., "2025-09"
list($year, $month) = explode('-', $monthStr);

$sql = "
    SELECT appointment_date, appointment_time, COUNT(*) AS total
    FROM appointments
    WHERE appointment_type = 'dental'
      AND YEAR(appointment_date) = $year
      AND MONTH(appointment_date) = $month
              AND status = 'Pending'
    GROUP BY appointment_date, appointment_time
";

$res = $conn->query($sql);
$slots = [];

while ($row = $res->fetch_assoc()) {
    $date = $row['appointment_date'];
    $time = $row['appointment_time'];
    if (!isset($slots[$date])) $slots[$date] = [];
    $slots[$date][$time] = intval($row['total']);
}

header('Content-Type: application/json');
echo json_encode($slots);