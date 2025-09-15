<?php
// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "clinic";

// Create connection (MySQLi OOP)
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Example: check if studentId exists
if (isset($_POST['studentId'])) {
    $studentId = $conn->real_escape_string(trim($_POST['studentId']));

    $sql = "SELECT id FROM patients WHERE student_id = '$studentId' LIMIT 1";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        echo "Student ID already exists!";
    } else {
        echo "Student ID is available.";
    }
}
?>
