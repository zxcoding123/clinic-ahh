<?php
session_start();
require_once 'config.php';

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'CSRF token validation failed.']);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// Get form data
$consultation_advice_id = $_POST['id'];


$patient_id = isset($_POST['patient_id']) ? intval($_POST['patient_id']) : 0;
$consultation_date = $_POST['consultation_date'] ?? '';
$consultation_time = $_POST['consultation_time'] ?? '';
$age = $_POST['patient_age'] ?? null;
$sex = $_POST['patient_sex'] ?? null;
$weight = $_POST['weight'] ?? null;
$height = $_POST['height'] ?? null;
$blood_pressure = $_POST['blood_pressure'] ?? null;
$temperature = $_POST['temperature'] ?? null;
$heart_rate = $_POST['heart_rate'] ?? null;
$respiratory_rate = $_POST['respiratory_rate'] ?? null;
$oxygen_saturation = $_POST['oxygen_saturation'] ?? null;
$complaints = $_POST['complaints'] ?? '';
$history = $_POST['history'] ?? '';
$physical_exam = $_POST['physical_exam'] ?? '';
$assessment = $_POST['assessment'] ?? '';
$plan = $_POST['plan'] ?? '';
$medications = $_POST['medications'] ?? '';
$consultation_type = $_POST['consultation_type'] ?? '';
$follow_up = $_POST['follow_up'] ?? '';
$physician_name = $_POST['physician_name'] ?? '';
$physician_license = $_POST['physician_license'] ?? '';
$signature_data = $_POST['signature_data'] ?? '';

// Validate required fields
if (
    empty($patient_id) || empty($consultation_date) || empty($consultation_time) ||
    empty($complaints) || empty($assessment) || empty($plan) || empty($physician_name)
) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    exit;
}

try {
    // Begin transaction
    $conn->begin_transaction();

    // Insert consultation record
    $sql = "INSERT INTO consultations_main (
        patient_id, consultation_date, consultation_time, age, sex, weight, height, blood_pressure, temperature, heart_rate, 
        respiratory_rate, oxygen_saturation, complaints, history, physical_exam, assessment, plan, medications, consultation_type, 
        follow_up, physician_name, physician_license, signature_data, created_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }

    $stmt->bind_param(
        'ississssssssssssssssssss',
        $patient_id,
        $consultation_date,
        $consultation_time,
        $age,
        $sex,
        $weight,
        $height,
        $blood_pressure,
        $temperature,
        $heart_rate,
        $respiratory_rate,
        $oxygen_saturation,
        $complaints,
        $history,
        $physical_exam,
        $assessment,
        $plan,
        $medications,
        $consultation_type,
        $follow_up,
        $physician_name,
        $physician_license,
        $signature_data,
        $_SESSION['user_id']
    );

    if (!$stmt->execute()) {
        throw new Exception("Failed to save consultation: " . $stmt->error);
    }

    $consultation_id = $stmt->insert_id;

    $updateStmt = $conn->prepare("UPDATE consultation_advice SET status = 'Completed' WHERE id = ?");
    $updateStmt->bind_param("i", $consultation_advice_id);
    if (!$updateStmt->execute()) {
        throw new Exception("Failed to update documents status");
    }
    $updateStmt->close();


    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Consultation record saved successfully!',
        'consultation_id' => $consultation_id
    ]);
} catch (Exception $e) {
    $conn->rollback();
    error_log("Consultation save error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error saving consultation: ' . $e->getMessage()]);
} finally {
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    $conn->close();
}
