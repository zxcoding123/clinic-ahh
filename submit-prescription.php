<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $consultation_id = isset($_SESSION['consultation_id']) ? (int)$_SESSION['consultation_id'] : 0;
    $patient_name = mysqli_real_escape_string($conn, $_POST['prescription-patient-name']);
    $age = (int)$_POST['prescription-age'];
    $sex = mysqli_real_escape_string($conn, $_POST['prescription-sex']);
    $diagnosis = mysqli_real_escape_string($conn, $_POST['prescription-diagnosis']);
    $medications = json_encode($_POST['medications']);
    $prescribing_physician = mysqli_real_escape_string($conn, $_POST['prescribing_physician']);
    $physician_signature = mysqli_real_escape_string($conn, $_POST['physician_signature']);
    $prescription_date = date('Y-m-d');

    // Save signature to file
    $signature_path = '../Uploads/signatures/prescription_' . time() . '.png';
    $signature_data = str_replace('data:image/png;base64,', '', $physician_signature);
    file_put_contents($signature_path, base64_decode($signature_data));

    // Insert into prescriptions table
    $sql = "INSERT INTO prescriptions (consultation_id, patient_name, age, sex, diagnosis, medications, prescribing_physician, physician_signature, prescription_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_stmt_init($conn);
    if (mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_bind_param($stmt, "isissssss", $consultation_id, $patient_name, $age, $sex, $diagnosis, $medications, $prescribing_physician, $signature_path, $prescription_date);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Clear consultation_id from session
        unset($_SESSION['consultation_id']);

        header("Location: medical-appointments.php?success=prescription_submitted");
        exit();
    } else {
        die("Error preparing statement: " . mysqli_error($conn));
    }
}
?>