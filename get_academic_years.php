<?php
include 'config.php';

$sql = "SELECT * FROM academic_years ORDER BY id DESC LIMIT 10";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<ul class='list-group'>";
    while ($row = $result->fetch_assoc()) {
        echo "<li class='list-group-item'>
                <strong>{$row['start_year']} - {$row['end_year']}</strong> | 
                Semester: {$row['semester']} | 
                Quarter: {$row['grading_quarter']}
              </li>";
    }
    echo "</ul>";
} else {
    echo "<p class='text-muted'>No academic years found.</p>";
}
$conn->close();

