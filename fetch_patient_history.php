<?php
require_once 'config.php';

$patientId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT id, surname, firstname, middlename, archived_at 
                        FROM patients_history 
                        WHERE user_id = ? 
                        ORDER BY archived_at DESC");
$stmt->bind_param("i", $patientId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<p class='text-muted'>No history records found for this patient.</p>";
} else {
    echo "<ul class='list-group'>";
    while ($row = $result->fetch_assoc()) {
        $archivedDate = $row['archived_at'] ? date('F j, Y h:i A', strtotime($row['archived_at'])) : '—';
        echo "<li class='list-group-item'>
                <div class='d-flex justify-content-between align-items-center'>
                    <div>
                        <strong>{$row['firstname']} {$row['middlename']} {$row['surname']}</strong><br>
                        Archived: {$archivedDate}
                    </div>
                    <a href='patient_history.php?id={$row['id']}' class='btn btn-sm btn-outline-primary'>View Details</a>
                </div>
              </li>";
    }
    echo "</ul>";
}

$stmt->close();
