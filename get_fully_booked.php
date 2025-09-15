<?php

require_once 'config.php';

$dateStart = $_GET['start']; // e.g. "2025-09-01"
$dateEnd   = $_GET['end'];   // e.g. "2025-09-30"

// allowed slots per day (Mon–Fri: 9 slots, Sat: 4 slots)
$allSlots = [
    "08:00:00","09:00:00","10:00:00","11:00:00",
    "12:00:00","13:00:00","14:00:00","15:00:00","16:00:00"
];

$sql = "SELECT appointment_date, appointment_time, COUNT(*) as total
        FROM appointments
        WHERE appointment_date BETWEEN ? AND ?
          AND appointment_type = 'dental'
        GROUP BY appointment_date, appointment_time";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $dateStart, $dateEnd);
$stmt->execute();
$res = $stmt->get_result();

$counts = [];
while ($row = $res->fetch_assoc()) {
    $counts[$row['appointment_date']][$row['appointment_time']] = (int)$row['total'];
}

$fullyBooked = [];
foreach ($counts as $date => $slots) {
    $dayOfWeek = date('w', strtotime($date)); // 0=Sun ... 6=Sat
    $daySlots = ($dayOfWeek == 6) ? array_slice($allSlots, 0, 4) : $allSlots;

    $allFull = true;
    foreach ($daySlots as $slot) {
        if (($slots[$slot] ?? 0) < 6) {
            $allFull = false;
            break;
        }
    }

    if ($allFull) {
        $fullyBooked[] = $date;
    }
}

header('Content-Type: application/json');
echo json_encode($fullyBooked);
