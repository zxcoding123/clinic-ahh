<?php
include 'config.php';

// Fetch the latest (or only) academic year
$sql = "SELECT * FROM academic_years ORDER BY id DESC LIMIT 1";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo json_encode($result->fetch_assoc());
} else {
    echo json_encode(null);
}

$conn->close();
?>
