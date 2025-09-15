<?php
require_once 'config.php';

// Check medical_documents table
echo "Checking medical_documents table for ID 12:\n";
$query = "SELECT id, file_path, original_file_name, document_type FROM medical_documents WHERE id = 12";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "Found in medical_documents:\n";
    echo "ID: " . $row['id'] . "\n";
    echo "File Path: " . $row['file_path'] . "\n";
    echo "Original File Name: " . $row['original_file_name'] . "\n";
    echo "Document Type: " . $row['document_type'] . "\n";
} else {
    echo "Not found in medical_documents\n";
}

// Check certificate_logs table
echo "\nChecking certificate_logs table for ID 12:\n";
$query = "SELECT id, file_path FROM certificate_logs WHERE id = 12";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "Found in certificate_logs:\n";
    echo "ID: " . $row['id'] . "\n";
    echo "File Path: " . $row['file_path'] . "\n";
} else {
    echo "Not found in certificate_logs\n";
}

// Check if the Uploads/medical_documents directory exists and what's in it
echo "\nChecking Uploads/medical_documents directory:\n";
$uploadDir = __DIR__ . '/Uploads/medical_documents';
if (is_dir($uploadDir)) {
    echo "Directory exists: $uploadDir\n";
    $files = glob($uploadDir . '/*');
    echo "Files in directory:\n";
    foreach ($files as $file) {
        echo "- " . basename($file) . "\n";
    }
} else {
    echo "Directory does not exist: $uploadDir\n";
}

$conn->close();
?> 