<?php
session_start();
require_once 'config.php';

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['Super Admin', 'Medical Admin'])) {
    header("Location: /login.php");
    exit();
}

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: medical-appointments.php");
    exit();
}

// Retrieve and sanitize form data
$patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
$child_id = filter_input(INPUT_POST, 'child_id', FILTER_VALIDATE_INT) ?: null;
$appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
$staff_id = filter_input(INPUT_POST, 'staff_id', FILTER_VALIDATE_INT);
$consultation_type = filter_input(INPUT_POST, 'consultation_type', FILTER_SANITIZE_STRING);
$name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
$consultation_date = filter_input(INPUT_POST, 'consultation_date', FILTER_SANITIZE_STRING);
$consultation_time = filter_input(INPUT_POST, 'consultation_time', FILTER_SANITIZE_STRING);
$grade_course_section = filter_input(INPUT_POST, 'grade_course_section', FILTER_SANITIZE_STRING);
$age = filter_input(INPUT_POST, 'age', FILTER_VALIDATE_INT);
$sex = filter_input(INPUT_POST, 'sex', FILTER_SANITIZE_STRING);
$weight = filter_input(INPUT_POST, 'weight', FILTER_SANITIZE_STRING);
$birthday = filter_input(INPUT_POST, 'birthday', FILTER_SANITIZE_STRING);
$blood_pressure = filter_input(INPUT_POST, 'blood_pressure', FILTER_SANITIZE_STRING);
$temperature = filter_input(INPUT_POST, 'temperature', FILTER_SANITIZE_STRING);
$heart_rate = filter_input(INPUT_POST, 'heart_rate', FILTER_SANITIZE_STRING);
$oxygen_saturation = filter_input(INPUT_POST, 'oxygen_saturation', FILTER_SANITIZE_STRING);
$complaints = filter_input(INPUT_POST, 'complaints', FILTER_SANITIZE_STRING);
$diagnosis = filter_input(INPUT_POST, 'diagnosis', FILTER_SANITIZE_STRING);
$treatment = filter_input(INPUT_POST, 'treatment', FILTER_SANITIZE_STRING);
$staff_signature = filter_input(INPUT_POST, 'staff_signature', FILTER_SANITIZE_STRING);

// Validate required fields
if (!$patient_id || !$appointment_id || !$staff_id || !$name || !$consultation_date || !$consultation_time || !$age || !$sex || !$weight || !$birthday || !$blood_pressure || !$temperature || !$heart_rate || !$oxygen_saturation || !$complaints || !$diagnosis || !$treatment || !$staff_signature) {
    $_SESSION['error'] = "All required fields must be filled.";
    header("Location: consultationForm.php?appointment_id=$appointment_id");
    exit();
}

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Insert consultation data
    $sql = "INSERT INTO consultations (
        patient_id, child_id, staff_id, name, consultation_date, consultation_time, 
        grade_course_section, age, sex, weight, birthday, blood_pressure, temperature, 
        heart_rate, oxygen_saturation, complaints, diagnosis, treatment, staff_signature, consultation_type
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        throw new Exception("Failed to prepare consultation insert statement.");
    }
    
    mysqli_stmt_bind_param(
        $stmt, "iiisssssisssssssssss",
        $patient_id, $child_id, $staff_id, $name, $consultation_date, $consultation_time,
        $grade_course_section, $age, $sex, $weight, $birthday, $blood_pressure, $temperature,
        $heart_rate, $oxygen_saturation, $complaints, $diagnosis, $treatment, $staff_signature, $consultation_type
    );
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to save consultation.");
    }
    
    mysqli_stmt_close($stmt);

    // Update appointment status to Completed
    $sql = "UPDATE appointments SET status = 'Completed' WHERE id = ?";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        throw new Exception("Failed to prepare appointment update statement.");
    }
    
    mysqli_stmt_bind_param($stmt, "i", $appointment_id);
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to update appointment status.");
    }
    
    mysqli_stmt_close($stmt);

    // Commit transaction
    mysqli_commit($conn);
    
    $_SESSION['success'] = "Consultation saved successfully.";
    header("Location: medical-appointments.php");
    exit();
} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($conn);
    $_SESSION['error'] = "Error saving consultation: " . $e->getMessage();
    header("Location: consultationForm.php?appointment_id=$appointment_id");
    exit();
}
?>