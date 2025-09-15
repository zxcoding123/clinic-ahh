<?php
session_start();
require_once 'config.php';

// Check if user is logged in and has appropriate permissions
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get the raw POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid data received']);
    exit;
}

// Get appointment_id and patient_id from URL
$appointment_id = isset($data['appointment_id']) ? intval($data['appointment_id']) : 0;
$patient_id = isset($data['patient_id']) ? intval($data['patient_id']) : 0;

if ($appointment_id <= 0 || $patient_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid appointment or patient ID']);
    exit;
}

// Extract data for dental_consultations table
$own_brush = isset($data['toothbrush']) && $data['toothbrush'] === 'yes' ? 1 : 0;

// Prepare data for insertion
$pt_upper_right = !empty($pt_upper_right_quadrant) ? implode(';', $pt_upper_right_quadrant) : NULL;
$pt_upper_left = !empty($pt_upper_left_quadrant) ? implode(';', $pt_upper_left_quadrant) : NULL;
$pt_lower_left = !empty($pt_lower_left_quadrant) ? implode(';', $pt_lower_left_quadrant) : NULL;
$pt_lower_right = !empty($pt_lower_right_quadrant) ? implode(';', $pt_lower_right_quadrant) : NULL;

$tt_upper_right = !empty($tt_upper_right_quadrant) ? implode(';', $tt_upper_right_quadrant) : NULL;
$tt_upper_left = !empty($tt_upper_left_quadrant) ? implode(';', $tt_upper_left_quadrant) : NULL;
$tt_lower_left = !empty($tt_lower_left_quadrant) ? implode(';', $tt_lower_left_quadrant) : NULL;
$tt_lower_right = !empty($tt_lower_right_quadrant) ? implode(';', $tt_lower_right_quadrant) : NULL;

// Find consultation id from appointment_id and patient_id
$consultationSql = "SELECT id FROM consultations 
                    WHERE appointment_id = $appointment_id 
                      AND patient_id = $patient_id 
                    LIMIT 1";
$consultationResult = mysqli_query($conn, $consultationSql);

if ($consultationResult && mysqli_num_rows($consultationResult) > 0) {
    $row = mysqli_fetch_assoc($consultationResult);
    $consultation_id = $row['id'];
} else {
    echo json_encode(['success' => false, 'message' => 'No matching consultation found.']);
    exit;
}

// Extract JSON fields safely
$permanent_teeth_json   = isset($data['permanent_teeth']) ? json_encode($data['permanent_teeth']) : '{}';
$temporary_teeth_json   = isset($data['temporary_teeth']) ? json_encode($data['temporary_teeth']) : '{}';
$dental_treatment_json  = isset($data['dental_treatment_record']) ? json_encode($data['dental_treatment_record']) : '{}';
$remarks                = isset($data['remarks']) ? $data['remarks'] : '';
$examined_by            = isset($data['examined_by']) ? $data['examined_by'] : '';
$exam_date              = isset($data['exam_date']) ? $data['exam_date'] : date('Y-m-d'); // default today


// Check if dental consultation already exists
$checkDentalSql = "SELECT id FROM dental_consultations WHERE consultation_id = $consultation_id LIMIT 1";
$checkDentalResult = mysqli_query($conn, $checkDentalSql);
$dentalExists = mysqli_num_rows($checkDentalResult) > 0;

// Insert or update dental consultation
if ($dentalExists) {
    $updateSql = "UPDATE dental_consultations SET 
        own_brush = '$own_brush',
        pt_upper_right_quadrant = " . ($pt_upper_right ? "'" . mysqli_real_escape_string($conn, $pt_upper_right) . "'" : "NULL") . ",
        pt_upper_left_quadrant = " . ($pt_upper_left ? "'" . mysqli_real_escape_string($conn, $pt_upper_left) . "'" : "NULL") . ",
        pt_lower_left_quadrant = " . ($pt_lower_left ? "'" . mysqli_real_escape_string($conn, $pt_lower_left) . "'" : "NULL") . ",
        pt_lower_right_quadrant = " . ($pt_lower_right ? "'" . mysqli_real_escape_string($conn, $pt_lower_right) . "'" : "NULL") . ",
        tt_upper_right_quadrant = " . ($tt_upper_right ? "'" . mysqli_real_escape_string($conn, $tt_upper_right) . "'" : "NULL") . ",
        tt_upper_left_quadrant = " . ($tt_upper_left ? "'" . mysqli_real_escape_string($conn, $tt_upper_left) . "'" : "NULL") . ",
        tt_lower_left_quadrant = " . ($tt_lower_left ? "'" . mysqli_real_escape_string($conn, $tt_lower_left) . "'" : "NULL") . ",
        tt_lower_right_quadrant = " . ($tt_lower_right ? "'" . mysqli_real_escape_string($conn, $tt_lower_right) . "'" : "NULL") . ",
        permanent_teeth = '" . mysqli_real_escape_string($conn, $permanent_teeth_json) . "',
        temporary_teeth = '" . mysqli_real_escape_string($conn, $temporary_teeth_json) . "',
        dental_treatment_record = '" . mysqli_real_escape_string($conn, $dental_treatment_json) . "',
        remarks = '" . mysqli_real_escape_string($conn, $remarks) . "',
        examined_by = '" . mysqli_real_escape_string($conn, $examined_by) . "',
        exam_date = '$exam_date',
        updated_at = NOW()
        WHERE consultation_id = $consultation_id";

    if (!mysqli_query($conn, $updateSql)) {
        error_log("Failed to update dental consultation: " . mysqli_error($conn));
        echo json_encode(['success' => false, 'message' => 'Failed to update dental consultation record: ' . mysqli_error($conn)]);
        exit;
    } else {
        echo json_encode(['success' => true, 'message' => 'Dental consultation updated successfully']);
    }
} else {
    $insertSql = "INSERT INTO dental_consultations (
        consultation_id, user_id, own_brush, 
        pt_upper_right_quadrant, pt_upper_left_quadrant, pt_lower_left_quadrant, pt_lower_right_quadrant,
        tt_upper_right_quadrant, tt_upper_left_quadrant, tt_lower_left_quadrant, tt_lower_right_quadrant,
        permanent_teeth, temporary_teeth, dental_treatment_record, remarks, examined_by, exam_date, created_at, updated_at
    ) VALUES (
        $consultation_id, {$_SESSION['user_id']}, $own_brush,
        " . ($pt_upper_right ? "'" . mysqli_real_escape_string($conn, $pt_upper_right) . "'" : "NULL") . ",
        " . ($pt_upper_left ? "'" . mysqli_real_escape_string($conn, $pt_upper_left) . "'" : "NULL") . ",
        " . ($pt_lower_left ? "'" . mysqli_real_escape_string($conn, $pt_lower_left) . "'" : "NULL") . ",
        " . ($pt_lower_right ? "'" . mysqli_real_escape_string($conn, $pt_lower_right) . "'" : "NULL") . ",
        " . ($tt_upper_right ? "'" . mysqli_real_escape_string($conn, $tt_upper_right) . "'" : "NULL") . ",
        " . ($tt_upper_left ? "'" . mysqli_real_escape_string($conn, $tt_upper_left) . "'" : "NULL") . ",
        " . ($tt_lower_left ? "'" . mysqli_real_escape_string($conn, $tt_lower_left) . "'" : "NULL") . ",
        " . ($tt_lower_right ? "'" . mysqli_real_escape_string($conn, $tt_lower_right) . "'" : "NULL") . ",
        '" . mysqli_real_escape_string($conn, $permanent_teeth_json) . "',
        '" . mysqli_real_escape_string($conn, $temporary_teeth_json) . "',
        '" . mysqli_real_escape_string($conn, $dental_treatment_json) . "',
        '" . mysqli_real_escape_string($conn, $remarks) . "',
        '" . mysqli_real_escape_string($conn, $examined_by) . "',
        '$exam_date',
        NOW(),
        NOW()
    )";

    if (!mysqli_query($conn, $insertSql)) {
        error_log("Failed to insert dental consultation: " . mysqli_error($conn));
        echo json_encode(['success' => false, 'message' => 'Failed to create dental consultation record: ' . mysqli_error($conn)]);
        exit;
    } else {
        echo json_encode(['success' => true, 'message' => 'Dental consultation created successfully']);

        // Update appointment and consultation status to Completed
        $updateAppointmentSql = "UPDATE appointments SET status = 'Completed' WHERE id = $appointment_id";
        mysqli_query($conn, $updateAppointmentSql);

        $updateConsultationSql = "UPDATE consultations SET status = 'Completed' WHERE appointment_id = $appointment_id";
        mysqli_query($conn, $updateConsultationSql);
    }
}
