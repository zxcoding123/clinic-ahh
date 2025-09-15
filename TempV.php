<?php
session_start();
require_once 'config.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

// Check if patient_id or child_id is provided
$patient_id = isset($_GET['id']) ? intval($_GET['id']) : null;
$child_id = isset($_GET['id']) ? intval($_GET['id']) : null;



if (!$patient_id && !$child_id) {
    die("Error: No patient or child ID provided.");
}

// Initialize variables
$patient_data = [];
$emergency_contact = [];
$medical_info = [];
$child_data = [];

// Fetch data based on whether it's a patient or a child
if ($child_id) {
    // Fetch child data
    $sql_child = "
SELECT 
    c.*, 
    pt.*, 
    pr.id AS parent_record_id,
    pr.user_id AS parent_user_id,
    pr.id_path AS parent_id_path,
    u.user_type AS parent_user_type
FROM children c
LEFT JOIN parents pr 
    ON c.parent_id = pr.user_id
LEFT JOIN patients pt 
    ON pr.user_id = pt.user_id
LEFT JOIN users u
    ON pt.user_id = u.id
WHERE c.id = ?
";
    $stmt_child = mysqli_prepare($conn, $sql_child);
    mysqli_stmt_bind_param($stmt_child, "i", $child_id);
    mysqli_stmt_execute($stmt_child);
    $result_child = mysqli_stmt_get_result($stmt_child);
    $child_data = mysqli_fetch_assoc($result_child);
    mysqli_stmt_close($stmt_child);

    if ($child_data) {
        $patient_id = $child_data['id'];
        $photo_path = $child_data['photo_path'];
        $surname = $child_data['surname'];
        $firstname = $child_data['firstname'];
        $middlename = $child_data['middlename'];
        $suffix = $child_data['suffix'];
        $student_id = $child_data['student_id'];

        $age = $child_data['age'];
        $sex = $child_data['sex'];
        $birthday = $child_data['birthday'];
        $age = $child_data['age'];
        $sex = $child_data['sex'];
        $blood_type = $child_data['blood_type'];
        $religion = $child_data['religion'];
        $nationality = $child_data['nationality'];
        $civil_status = $child_data['civil_status'];

        $email = $child_data['email'];
        $contact_number = $child_data['contact_number'];
        $city_address = $child_data['city_address'];
        $provincial_address = $child_data['provincial_address'];
        $photo_path = $child_data['photo_path'];

        $grade_level = $child_data['grade_level'];
        $grading_quarter = $child_data['grading_quarter'];
        $track_strand = $child_data['track_strand'];
        $section = $child_data['section'];
        $semester = $child_data['semester'];
        $department = $child_data['department'];
        $course = $child_data['course'];
        $year_level = $child_data['year_level'];
        $position = $child_data['position'];

        $userType = $child_data['parent_user_type'];

        // Fetch emergency contact
        $sql_emergency = "
        SELECT surname, firstname, middlename, contact_number, relationship, city_address
        FROM emergency_contacts
        WHERE patient_id = (SELECT id FROM patients WHERE user_id = ?)";
        $stmt_emergency = mysqli_prepare($conn, $sql_emergency);
        mysqli_stmt_bind_param($stmt_emergency, "i", $patient_id);
        mysqli_stmt_execute($stmt_emergency);
        $result_emergency = mysqli_stmt_get_result($stmt_emergency);
        $emergency_contact = mysqli_fetch_assoc($result_emergency) ?: [];
        mysqli_stmt_close($stmt_emergency);


        $surname_emergency_contact = $emergency_contact['surname'];
        $firstname_emergency_contact = $emergency_contact['firstname'];
        $middlename_emergency_contact = $emergency_contact['middlename'];
        $contact_number_emergency_contact = $emergency_contact['contact_number'];
        $relationship_emergency_contact = $emergency_contact['relationship'];
        $city_address_emergency_contact = $emergency_contact['city_address'];

        // Fetch medical info
        $sql_medical = "
        SELECT illnesses, medications, vaccination_status, menstruation_age, menstrual_pattern, pregnancies,
               live_children, menstrual_symptoms, past_illnesses, hospital_admissions, family_history, other_conditions
        FROM medical_info
        WHERE patient_id = (SELECT id FROM patients WHERE user_id = ?)";
        $stmt_medical = mysqli_prepare($conn, $sql_medical);
        mysqli_stmt_bind_param($stmt_medical, "i", $patient_id);
        mysqli_stmt_execute($stmt_medical);
        $result_medical = mysqli_stmt_get_result($stmt_medical);
        $medical_info = mysqli_fetch_assoc($result_medical) ?: [];
        mysqli_stmt_close($stmt_medical);

        // Parse medical info
        $illnesses = !empty($medical_info['illnesses']) ? explode(',', $medical_info['illnesses']) : [];
        $vaccination_status = $medical_info['vaccination_status'];



        // Example data from DB (string)
        $medications_str = $medical_info['medications'] ?? '';

        // Parse into structured array
        $formatted_medications = [];

        if (!empty($medications_str)) {

            // Try to decode JSON
            $json_data = json_decode($medications_str, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($json_data)) {
                // JSON format detected
                foreach ($json_data as $med) {
                    $formatted_medications[] = [
                        'drug'      => trim($med['drug']),
                        'drug_other' => isset($med['drug_other']) ? trim($med['drug_other']) : '',
                        'dose'      => trim($med['dose']),
                        'unit'      => trim($med['unit']),
                        'frequency' => trim($med['frequency'])
                    ];
                }
            } else {
                // Legacy comma/colon format
                $med_list = explode(',', $medications_str);
                foreach ($med_list as $med) {
                    $parts = explode(':', $med);
                    if (count($parts) >= 4) {
                        $formatted_medications[] = [
                            'drug'      => trim($parts[0]),
                            'drug_other' => '',
                            'dose'      => trim($parts[1]),
                            'unit'      => trim($parts[2]),
                            'frequency' => trim($parts[3])
                        ];
                    }
                }
            }
        }

        $vaccination_status = $medical_info['vaccination_status'] ?? 'not';


        $menstruation_age = $medical_info['menstruation_age'] ?? '';
        $menstrual_pattern = $medical_info['menstrual_pattern'] ?? '';
        $pregnancies = $medical_info['pregnancies'] ?? 0;
        $live_children = $medical_info['live_children'] ?? 0;
        $menstrual_symptoms = !empty($medical_info['menstrual_symptoms']) ? explode(',', $medical_info['menstrual_symptoms']) : [];
        $past_illnesses = !empty($medical_info['past_illnesses']) ? explode(',', $medical_info['past_illnesses']) : [];

        $hospital_admissions = !empty($medical_info['hospital_admissions']) ? explode(',', $medical_info['hospital_admissions']) : [];



        $family_history = !empty($medical_info['family_history']) ? explode(',', $medical_info['family_history']) : [];
        $other_conditions = $medical_info['other_conditions'] ?? '';


        // Parse medical info
        $illnesses = !empty($medical_info['illnesses']) ? explode(',', $medical_info['illnesses']) : [];
        $medications = !empty($medical_info['medications']) ? json_decode($medical_info['medications'], true) : [];
        $vaccination_status = $medical_info['vaccination_status'] ?? 'not';



        $menstruation_age = $medical_info['menstruation_age'] ?? '';
        $menstrual_pattern = $medical_info['menstrual_pattern'] ?? '';
        $pregnancies = $medical_info['pregnancies'] ?? 0;
        $live_children = $medical_info['live_children'] ?? 0;
        $menstrual_symptoms = !empty($medical_info['menstrual_symptoms']) ? explode(',', $medical_info['menstrual_symptoms']) : [];
        $past_illnesses = !empty($medical_info['past_illnesses']) ? explode(',', $medical_info['past_illnesses']) : [];
        $hospital_admissions = !empty($medical_info['hospital_admissions']) ? explode(',', $medical_info['hospital_admissions']) : [];



        $family_history = !empty($medical_info['family_history']) ? explode(',', $medical_info['family_history']) : [];
        $other_conditions = $medical_info['other_conditions'] ?? '';
    } else {


   
        // Fetch patient data
        $sql_patient = "
        SELECT p.*,
               u.user_type
        FROM patients p
        LEFT JOIN users u ON p.user_id = u.id
        WHERE p.user_id = ?";
        $stmt_patient = mysqli_prepare($conn, $sql_patient);
        mysqli_stmt_bind_param($stmt_patient, "i", $patient_id);
        mysqli_stmt_execute($stmt_patient);
        $result_patient = mysqli_stmt_get_result($stmt_patient);
        $patient_data = mysqli_fetch_assoc($result_patient);
        mysqli_stmt_close($stmt_patient);

        if (!$patient_data) {
            die("Error: Patient not found.");
        }

        // Fetch emergency contact
        $sql_emergency = "
        SELECT surname, firstname, middlename, contact_number, relationship, city_address
        FROM emergency_contacts
        WHERE patient_id = (SELECT id FROM patients WHERE user_id = ?)";
        $stmt_emergency = mysqli_prepare($conn, $sql_emergency);
        mysqli_stmt_bind_param($stmt_emergency, "i", $patient_id);
        mysqli_stmt_execute($stmt_emergency);
        $result_emergency = mysqli_stmt_get_result($stmt_emergency);
        $emergency_contact = mysqli_fetch_assoc($result_emergency) ?: [];
        mysqli_stmt_close($stmt_emergency);


        $surname_emergency_contact = $emergency_contact['surname'];
        $firstname_emergency_contact = $emergency_contact['firstname'];
        $middlename_emergency_contact = $emergency_contact['middlename'];
        $contact_number_emergency_contact = $emergency_contact['contact_number'];
        $relationship_emergency_contact = $emergency_contact['relationship'];
        $city_address_emergency_contact = $emergency_contact['city_address'];

        // Fetch medical info

   
        
        $sql_medical = "
        SELECT illnesses, medications, vaccination_status, menstruation_age, menstrual_pattern, pregnancies,
               live_children, menstrual_symptoms, past_illnesses, hospital_admissions, family_history, other_conditions
        FROM medical_info
        WHERE patient_id = (SELECT id FROM patients WHERE user_id = ?)";
        $stmt_medical = mysqli_prepare($conn, $sql_medical);
        mysqli_stmt_bind_param($stmt_medical, "i", $patient_id);
        mysqli_stmt_execute($stmt_medical);
        $result_medical = mysqli_stmt_get_result($stmt_medical);
        $medical_info = mysqli_fetch_assoc($result_medical) ?: [];
        mysqli_stmt_close($stmt_medical);

        // Parse medical info
        $illnesses = !empty($medical_info['illnesses']) ? explode(',', $medical_info['illnesses']) : [];
        // Example data from DB (string)
        $medications_str = $medical_info['medications'] ?? '';


        // Parse into structured array
        $formatted_medications = [];
        if (!empty($medications_str)) {
            // If multiple medications, separate by comma
            $med_list = explode(',', $medications_str);
            foreach ($med_list as $med) {
                $parts = explode(':', $med);
                if (count($parts) >= 4) {
                    $formatted_medications[] = [
                        'drug' => trim($parts[0]),
                        'dose' => trim($parts[1]) . ' ' . trim($parts[2]) . ', ' . trim($parts[3])
                    ];
                }
            }
        }




        $vaccination_status = $medical_info['vaccination_status'] ?? 'not';


        $menstruation_age = $medical_info['menstruation_age'] ?? '';
        $menstrual_pattern = $medical_info['menstrual_pattern'] ?? '';
        $pregnancies = $medical_info['pregnancies'] ?? 0;
        $live_children = $medical_info['live_children'] ?? 0;
        $menstrual_symptoms = !empty($medical_info['menstrual_symptoms']) ? explode(',', $medical_info['menstrual_symptoms']) : [];
        $past_illnesses = !empty($medical_info['past_illnesses']) ? explode(',', $medical_info['past_illnesses']) : [];

        $hospital_admissions = !empty($medical_info['hospital_admissions']) ? explode(',', $medical_info['hospital_admissions']) : [];



        $family_history = !empty($medical_info['family_history']) ? explode(',', $medical_info['family_history']) : [];
        $other_conditions = $medical_info['other_conditions'] ?? '';


        // Parse medical info
        $illnesses = !empty($medical_info['illnesses']) ? explode(',', $medical_info['illnesses']) : [];
        $medications = !empty($medical_info['medications']) ? json_decode($medical_info['medications'], true) : [];
        $vaccination_status = $medical_info['vaccination_status'] ?? 'not';



        $menstruation_age = $medical_info['menstruation_age'] ?? '';
        $menstrual_pattern = $medical_info['menstrual_pattern'] ?? '';
        $pregnancies = $medical_info['pregnancies'] ?? 0;
        $live_children = $medical_info['live_children'] ?? 0;
        $menstrual_symptoms = !empty($medical_info['menstrual_symptoms']) ? explode(',', $medical_info['menstrual_symptoms']) : [];
        $past_illnesses = !empty($medical_info['past_illnesses']) ? explode(',', $medical_info['past_illnesses']) : [];
        $hospital_admissions = !empty($medical_info['hospital_admissions']) ? explode(',', $medical_info['hospital_admissions']) : [];




        $family_history = !empty($medical_info['family_history']) ? explode(',', $medical_info['family_history']) : [];
        $other_conditions = $medical_info['other_conditions'] ?? '';




        $photo_path = $patient_data['photo_path'];

        $surname = $patient_data['surname'];
        $firstname = $patient_data['firstname'];
        $middlename = $patient_data['middlename'];
        $suffix = $patient_data['suffix'];
        $student_id = $patient_data['student_id'];

        $age = $patient_data['age'];
        $sex = $patient_data['sex'];
        $birthday = $patient_data['birthday'];
        $age = $patient_data['age'];
        $sex = $patient_data['sex'];
        $blood_type = $patient_data['blood_type'];
        $religion = $patient_data['religion'];
        $nationality = $patient_data['nationality'];
        $civil_status = $patient_data['civil_status'];

        $email = $patient_data['email'];

        $contact_number = $patient_data['contact_number'];
        $city_address = $patient_data['city_address'];
        $provincial_address = $patient_data['provincial_address'];
        $photo_path = $patient_data['photo_path'];

        $grade_level = $patient_data['grade_level'];
        $grading_quarter = $patient_data['grading_quarter'];
        $track_strand = $patient_data['track_strand'];
        $section = $patient_data['section'];
        $semester = $patient_data['semester'];
        $department = $patient_data['department'];
        $course = $patient_data['course'];
        $year_level = $patient_data['year_level'];
        $position = $patient_data['position'];

        $userType = $patient_data['user_type'];
    }
}


// Define possible conditions for checkboxes
$comorbid_conditions = [
    'asthma' => 'Bronchial Asthma ("Hika")',
    'allergies' => 'Food Allergies',
    'rhinitis' => 'Allergic Rhinitis',
    'hyperthyroidism' => 'Hyperthyroidism',
    'hypothyroidism' => 'Hypothyroidism/Goiter',
    'anemia' => 'Anemia',
    'depression' => 'Major Depressive Disorder',
    'bipolar' => 'Bipolar Disorder',
    'anxiety' => 'Generalized Anxiety Disorder',
    'panic' => 'Panic Disorder',
    'stress' => 'Posttraumatic Stress Disorder',
    'Schizophrenia' => 'Schizophrenia',
    'Migraine' => 'Migraine (recurrent headaches)',
    'Epilepsy' => 'Epilepsy/Seizures',
    'GERD' => 'Gastroesophageal Reflux Disease (GERD)',
    'Irritable Bowel Syndrome' => 'Irritable Bowel Syndrome',
    'Hypertension' => 'Hypertension (elevated blood pressure)',
    'Diabetes mellitus' => 'Diabetes mellitus (elevated blood sugar)',
    'Dyslipidemia' => 'Dyslipidemia (elevated cholesterol levels)',
    'Arthritis' => 'Arthritis (joint pains)',
    'SLE' => 'Systemic Lupus Erythematosus (SLE)',
    'PCOS' => 'Polycystic Ovarian Syndrome (PCOS)',
    'Cancer' => 'Cancer',
    'Other' => 'Other'
];

$past_conditions = [
    'Varicella' => 'Varicella (Chicken Pox)',
    'Measles' => 'Measles',
    'Dengue' => 'Dengue',
    'Typhoid fever' => 'Typhoid fever',
    'Tuberculosis' => 'Tuberculosis',
    'Amoebiasis' => 'Amoebiasis',
    'Pneumonia' => 'Pneumonia',
    'Nephro/Urolithiasis' => 'Nephro/Urolithiasis (kidney stones)',
    'Appendicitis' => 'Appendicitis',
    'Injury' => 'Injury',
    'Burn' => 'Burn',
    'Cholecystitis' => 'Cholecystitis',
    'Stab/Laceration' => 'Stab/Laceration',
    'Fracture' => 'Fracture',
    'UTI' => 'Urinary Tract Infection (UTI)',
    'Other' => 'Other'
];

$family_conditions = [
    'Hypertension' => 'Hypertension (elevated blood pressure)',
    'Coronary Artery Disease' => 'Coronary Artery Disease',
    'Congestive Heart Failure' => 'Congestive Heart Failure',
    'Diabetes mellitus' => 'Diabetes mellitus (elevated blood sugar)',
    'Chronic Kidney Disease' => 'Chronic Kidney Disease (with/without regular Hemodialysis)',
    'Dyslipidemia' => 'Dyslipidemia (elevated cholesterol levels)',
    'Arthritis' => 'Arthritis (joint pains)',
    'Cancer' => 'Cancer',
    'Bronchial Asthma' => 'Bronchial Asthma ("Hika")',
    'COPD' => 'Chronic Obstructive Pulmonary Disease (COPD)',
    'Food Allergies' => 'Food Allergies',
    'Allergic Rhinitis' => 'Allergic Rhinitis',
    'Hyperthyroidism' => 'Hyperthyroidism',
    'Hypothyroidism' => 'Hypothyroidism/Goiter',
    'Major Depressive Disorder' => 'Major Depressive Disorder',
    'Bipolar Disorder' => 'Bipolar Disorder',
    'Generalized Anxiety Disorder' => 'Generalized Anxiety Disorder',
    'Panic Disorder' => 'Panic Disorder',
    'Posttraumatic Stress Disorder' => 'Posttraumatic Stress Disorder',
    'Schizophrenia' => 'Schizophrenia',
    'Epilepsy' => 'Epilepsy/Seizures',
    'Other' => 'Other'
];

$menstrual_symptoms_list = [
    'Dysmenorrhea' => 'Dysmenorrhea (cramps)',
    'Migraine' => 'Migraine',
    'Loss of consciousness' => 'Loss of consciousness',
    'Other' => 'Other'
];

// Helper function to check if a condition is present
function is_checked($condition, $list)
{
    return in_array($condition, $list) ? 'checked' : '';
}

// Helper function to format medications
function format_medications($meds)
{
    $result = array_fill(0, 5, ['drug' => '', 'dose' => '']);
    if (is_array($meds)) {
        foreach ($meds as $index => $med) {
            if ($index < 5) {
                $result[$index] = [
                    'drug' => $med['drug'] ?? ($med['drug_other'] ?? ''),
                    'dose' => ($med['dose'] ?? '') . ' ' . ($med['unit'] ?? '') . ' ' . ($med['frequency'] ?? '')
                ];
            }
        }
    }
    return $result;
}


$hospital_admissions_formatted = [];

// Make sure we actually have something
if (!empty($hospital_admissions)) {

    // If JSON format
    if (is_string($hospital_admissions) && str_starts_with(trim($hospital_admissions), '[')) {
        $decoded = json_decode($hospital_admissions, true);
        if (is_array($decoded)) {
            foreach ($decoded as $entry) {
                $hospital_admissions_formatted[] = [
                    'year'   => $entry['year'] ?? '',
                    'reason' => $entry['reason'] ?? ''
                ];
            }
        }
    }
    // If array of strings or single colon-separated string
    else {
        if (is_string($hospital_admissions)) {
            $hospital_admissions = explode(',', $hospital_admissions);
        }
        foreach ($hospital_admissions as $admission) {
            list($year, $reason) = explode(':', $admission, 2) + [null, null];
            $hospital_admissions_formatted[] = [
                'year'   => trim($year ?? ''),
                'reason' => trim($reason ?? '')
            ];
        }
    }
}

// If empty after parsing, add a blank "No" row
if (empty($hospital_admissions_formatted)) {
    $hospital_admissions_formatted[] = ['year' => '', 'reason' => ''];
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WMSU University Health Services - Patient Record</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .header-logo {
            height: 80px;
            max-width: 100px;
            object-fit: contain;
        }

        .header-center {
            text-align: center;
            border-bottom: 2px solid #8B0000;
            padding-bottom: 10px;
            flex-grow: 1;
            margin: 0 15px;
        }

        .header-center h1 {
            color: #8B0000;
            margin-bottom: 5px;
            font-size: 18px;
        }

        .header-center p {
            margin: 5px 0;
            font-size: 12px;
        }

        .back-button {
            background-color: #8B0000;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: bold;
            transition: background-color 0.3s;
        }

        .back-button:hover {
            background-color: #a11212;
        }

        .form-title {
            text-align: center;
            background-color: #8B0000;
            color: white;
            padding: 5px;
            margin: 10px 0;
            font-size: 14px;
            font-weight: bold;
            border: 1px solid #000;
        }

        .section-container {
            position: relative;
            margin-bottom: 30px;
            border: 1px solid #ddd;
            padding: 15px 15px 15px 100px;
            border-radius: 5px;
        }

        .section-title {
            position: absolute;
            left: 0;
            top: 0;
            width: 90px;
            height: 100%;
            background-color: #8B0000;
            color: white;
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
            border-top-left-radius: 5px;
            border-bottom-left-radius: 5px;
        }

        .section-content {
            border-left: 2px solid #8B0000;
            padding-left: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        table,
        th,
        td {
            border: 1px solid #ddd;
        }

        th,
        td {
            padding: 8px;
            text-align: left;
        }

        .photo-cell-container {
            float: left;
            width: 150px;
            margin-right: 20px;
        }

        .photo-cell {
            width: 150px;
            height: 150px;
            border: 1px dashed #999;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f9f9f9;
            margin-bottom: 15px;
        }

        .photo-cell img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }

        .personal-info-table {
            overflow: hidden;
        }

        .checkbox-group {
            margin-bottom: 10px;
        }

        .checkbox-line {
            margin-bottom: 5px;
        }

        .footer {
            text-align: center;
            font-size: 12px;
            margin-top: 20px;
            color: #666;
        }

        .page-break {
            page-break-after: always;
        }

        @media print {
            .header-logo {
                height: 70px;
            }

            .header-center h1 {
                font-size: 16px;
            }

            .header-center p {
                font-size: 11px;
            }

            .back-button {
                display: none;
            }
        }

        input,
        textarea,
        select {
            display: none;
        }

        .view-only-text {
            display: inline-block;
            min-height: 18px;
            padding: 2px 4px;
            border-bottom: 1px solid #999;
            min-width: 100px;
        }

        .checkbox-view {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 1px solid #333;
            margin-right: 5px;
            text-align: center;
            line-height: 14px;
            font-size: 12px;
        }

        .checked {
            background-color: #333;
            color: white;
        }
    </style>
</head>

<body>
    <!-- First Page -->
    <div class="header-container">
        <a href="history.php" class="back-button">Back</a>
        <img src="images/Western_Mindanao_State_University.png" alt="WMSU Logo" class="header-logo" style="margin-left: 15px;">
        <div class="header-center">
            <h1>WESTERN MINDANAO STATE UNIVERSITY</h1>
            <p>ZAMBOANGA CITY</p>
            <p>UNIVERSITY HEALTH SERVICES CENTER</p>
            <p>Tel. no. (062) 991-6736 | Email: healthservices@wmsu.edu.ph</p>
        </div>
        <img src="images/doh.png" alt="DOH Logo" class="header-logo">
    </div>

    <div class="form-title">PATIENT HEALTH PROFILE & CONSULTATIONS RECORD</div>
    <div style="text-align: center; font-style: italic; margin-bottom: 20px;">(Electronic or Paper-based Input)</div>

    <!-- Personal Information -->
    <div class="section-container">
        <div class="section-title">Personal Information</div>
        <div class="section-content">
            <div class="photo-cell-container">
                <div class="photo-cell">
                    <?php
                    $full_photo_path = $photo_path ?  $photo_path : '';
                    ?>

                    <?php if ($photo_path && file_exists($full_photo_path)): ?>
                        <img src="<?php echo htmlspecialchars($photo_path); ?>" alt="<?php echo $child_id ? 'Child Photo' : 'Patient Photo'; ?>">
                    <?php else: ?>
                        (Photo of Patient)
                    <?php endif; ?>
                </div>
            </div>
            <div class="personal-info-table">
                <table>
                    <tr>
                        <td>Name:</td>
                        <td><span class="view-only-text"><?php echo htmlspecialchars($firstname) ?></span></td>
                        <td><span class="view-only-text"><?php echo htmlspecialchars($middlename) ?></span></td>
                        <td><span class="view-only-text"><?php echo htmlspecialchars($surname) ?></span></td>
                    </tr>
                    <tr>
                        <td>Age:</td>
                        <td><span class="view-only-text"><?php echo htmlspecialchars($age); ?></span></td>
                        <td>Sex:</td>
                        <td><span class="view-only-text"><?php echo ucfirst(htmlspecialchars($sex)); ?></span></td>

                    </tr>
                    <?php



                    // Senior High School
                    if ($userType === 'Senior High School') {
                        echo '<td>Grade Level:</td>';
                        echo '<td><span class="view-only-text">' . htmlspecialchars($grade_level) . '</span></td>';

                        echo '<td>Track/Strand:</td>';
                        echo '<td><span class="view-only-text">' . htmlspecialchars($track_strand) . '</span></td>';
                        echo '</tr><tr>';
                        echo '<td> Section:</td>';
                        echo '<td colspan="3"><span class="view-only-text">' . htmlspecialchars($section) . '</span></td>';

                        // College or Incoming Freshmen
                    } elseif ($userType === 'College' || $userType === 'Incoming Freshman') {
                        echo '<td>Semester:</td>';
                        echo '<td><span class="view-only-text">' . htmlspecialchars($semester) . '</span></td>';
                        echo '<td>Department:</td>';
                        echo '<td><span class="view-only-text">' . htmlspecialchars($department) . '</span></td>';
                        echo '</tr><tr>';

                        echo '</tr><tr>';
                        echo '<td>Course:</td>';
                        echo '<td><span class="view-only-text">' . htmlspecialchars($course) . '</span></td>';
                        echo '<td>Year Level:</td>';
                        echo '<td><span class="view-only-text">' . htmlspecialchars($year_level) . '</span></td>';
                        echo '</tr><tr>';


                        // Parent
                    } elseif ($userType === 'Parent') {
                        echo '<td>Grade Level:</td>';
                        echo '<td><span class="view-only-text">' . htmlspecialchars($patient_data['grade_level'] ?? '-') . '</span></td>';
                        echo '</tr><tr>';
                        echo '<td>Grading Quarter:</td>';
                        echo '<td><span class="view-only-text">' . htmlspecialchars($patient_data['grading_quarter'] ?? '-') . '</span></td>';

                        // Default fallback
                    } else {
                        echo '<td colspan="2"><span class="text-muted">No educational details available</span></td>';
                    }
                    ?>
                    <tr>
                        <td>Birthday (MM-DD-YY):</td>
                        <td><span class="view-only-text"><?php echo htmlspecialchars($birthday); ?></span></td>
                        <td colspan="1">Religion:</td>
                        <td><span class="view-only-text"><?php echo htmlspecialchars($religion); ?></span></td>
                    </tr>
                    <tr>
                        <td>Nationality:</td>
                        <td><span class="view-only-text"><?php echo htmlspecialchars($nationality); ?></span></td>
                        <td colspan="1">Civil Status:</td>
                        <td><span class="view-only-text"><?php echo htmlspecialchars(ucfirst($civil_status)); ?></span></td>
                    </tr>
                    <tr>
                        <td>Email Address:</td>
                        <td colspan="3"><span class="view-only-text"><?php echo htmlspecialchars($email); ?></span></td>
                    </tr>
                    <tr>
                        <td>Contact #:</td>
                        <td colspan="3"><span class="view-only-text"><?php echo htmlspecialchars($contact_number); ?></span></td>
                    </tr>
                    <tr>
                        <td colspan="4">City Address: <span class="view-only-text"><?php echo htmlspecialchars($city_address); ?></span></td>
                    </tr>
                    <tr>
                        <td colspan="4">Provincial Address (if applicable): <span class="view-only-text"><?php echo htmlspecialchars($provincial_address); ?></span></td>
                    </tr>
                    <tr>
                        <td colspan="4">
                            <table>
                                <tr>
                                    <td>Emergency Contact Person within Zamboanga City</td>
                                    <td>Name: <span class="view-only-text"><?php echo htmlspecialchars(($firstname_emergency_contact) . ' ' . ($middlename_emergency_contact) . ' ' . ($surname_emergency_contact)); ?></span></td>
                                    <td>Contact #: <span class="view-only-text"><?php echo htmlspecialchars($contact_number_emergency_contact ?? ''); ?></span></td>
                                    <td>Relationship: <span class="view-only-text"><?php echo htmlspecialchars($relationship_emergency_contact ?? ''); ?></span></td>
                                </tr>
                                <tr>
                                    <td colspan="4">City Address: <span class="view-only-text"><?php echo htmlspecialchars($city_address_emergency_contact ?? ''); ?></span></td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </div>
            <div style="clear: both;"></div>
        </div>
    </div>

    <!-- Comorbid Illnesses -->
    <div class="section-container">
        <div class="section-title">Comorbid Illnesses</div>
        <div class="section-content">
            <p>Which of these conditions do you currently have?</p>
            <div class="checkbox-group">
                <?php
                $illnesses = !empty($medical_info['illnesses'])
                    ? array_map('strtolower', array_map('trim', explode(',', $medical_info['illnesses'])))
                    : [];

                foreach ($comorbid_conditions as $key => $label):
                    $additional_info = '';

                    // lowercase the key for matching
                    $lower_key = strtolower($key);

                    if ($lower_key === 'food allergies' && in_array($lower_key, $illnesses)) {
                        $additional_info = explode(':', strtolower($medical_info['illnesses']))[1] ?? '';
                    } elseif ($lower_key === 'cancer' && in_array($lower_key, $illnesses)) {
                        $additional_info = explode(':', strtolower($medical_info['illnesses']))[1] ?? '';
                    } elseif ($lower_key === 'other' && in_array($lower_key, $illnesses)) {
                        $additional_info = explode(':', strtolower($medical_info['illnesses']))[1] ?? '';
                    }
                ?>
                    <div class="checkbox-line">
                        <span class="checkbox-view <?php echo is_checked($lower_key, $illnesses); ?>">✓</span>
                        <?php echo $label; ?>
                        <?php if ($additional_info): ?>
                            (Specify: <span class="view-only-text"><?php echo htmlspecialchars($additional_info); ?></span>)
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

            </div>
        </div>
    </div>

    <!-- Maintenance Medications -->
    <div class="section-container">
        <div class="section-title">Maintenance Medications</div>
        <div class="section-content">
            <table>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 45%;">Generic Name of Drug</th>
                    <th style="width: 50%;">Dose and Frequency</th>
                </tr>
                <?php foreach ($formatted_medications as $index => $med): ?>
                    <tr>
                        <td><?php echo $index + 1; ?>.</td>
                        <td><span class="view-only-text"><?php echo ucfirst(htmlspecialchars($med['drug'])); ?></span></td>
                        <td><span class="view-only-text"><?php echo htmlspecialchars($med['dose']); ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <!-- COVID-19 Vaccination -->
    <div class="section-container">
        <div class="section-title">COVID-19 Vaccination</div>
        <div class="section-content">
            <div class="checkbox-group">
                <div class="checkbox-line">
                    <span class="checkbox-view <?php echo $vaccination_status === 'fully' ? 'checked' : ''; ?>">✓</span> Fully vaccinated (Primary series with or without booster shot/s)
                </div>
                <div class="checkbox-line">
                    <span class="checkbox-view <?php echo $vaccination_status === 'partially' ? 'checked' : ''; ?>">✓</span> Partially vaccinated (Incomplete primary series)
                </div>
                <div class="checkbox-line">
                    <span class="checkbox-view <?php echo $vaccination_status === 'not' ? 'checked' : ''; ?>">✓</span> Not vaccinated
                </div>
            </div>
        </div>
    </div>

    <?php
    if ($sex == 'female') {
    ?>
        <!-- Menstrual & Obstetric History -->
        <div class="section-container">
            <div class="section-title">Menstrual & Obstetric History</div>
            <div class="section-content">
                <div class="checkbox-group">
                    <div class="checkbox-line">
                        Age when menstruation began: <span class="view-only-text"><?php echo htmlspecialchars($menstruation_age); ?></span>
                        <span class="checkbox-view <?php echo $menstrual_pattern === 'regular' ? 'checked' : ''; ?>">✓</span> Regular (monthly)
                        <span class="checkbox-view <?php echo $menstrual_pattern === 'irregular' ? 'checked' : ''; ?>">✓</span> Irregular
                    </div>
                    <div class="checkbox-line">
                        Number of pregnancies: <span class="view-only-text"><?php echo htmlspecialchars($pregnancies); ?></span>
                        Number of live children: <span class="view-only-text"><?php echo htmlspecialchars($live_children); ?></span>
                    </div>
                    <p>Menstrual Symptoms:</p>
                    <?php foreach ($menstrual_symptoms_list as $key => $label): ?>
                        <?php
                        $additional_info = ($key === 'Other' && in_array('Other', $menstrual_symptoms)) ? ($menstrual_symptoms[array_search('Other', $menstrual_symptoms) + 1] ?? '') : '';
                        ?>
                        <div class="checkbox-line">
                            <span class="checkbox-view <?php echo is_checked($key, $menstrual_symptoms); ?>">✓</span>
                            <?php echo $label; ?>
                            <?php if ($additional_info): ?>
                                (Specify: <span class="view-only-text"><?php echo htmlspecialchars($additional_info); ?></span>)
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    <?php
    }
    ?>

    <!-- Past Medical & Surgical History -->
    <div class="section-container">
        <div class="section-title">Past Medical & Surgical History</div>
        <div class="section-content">
            <p>Which of these conditions have you had in the past?</p>
            <div class="checkbox-group">
                <?php
                $past_illnesses = !empty($medical_info['past_illnesses'])
                    ? array_map('strtolower', array_map('trim', explode(',', $medical_info['past_illnesses'])))
                    : [];

                foreach ($past_conditions as $key => $label):
                    $checked = in_array(strtolower($key), $past_illnesses) ? 'checked' : '';
                ?>
                    <div class="checkbox-line">
                        <span class="checkbox-view <?= $checked ? 'checked' : '' ?>">✓</span>
                        <?= htmlspecialchars($label) ?>
                    </div>
                <?php endforeach; ?>

            </div>
            <p>Have you ever been admitted to the hospital and/or underwent a surgery?</p>



            <table>
                <?php foreach ($hospital_admissions_formatted as $entry): ?>
                    <tr>
                        <td><span class="checkbox-view <?php echo empty($entry['year']) && empty($entry['reason']) ? 'checked' : ''; ?>">✓</span> No</td>
                        <td><span class="checkbox-view <?php echo !empty($entry['year']) || !empty($entry['reason']) ? 'checked' : ''; ?>">✓</span> Yes</td>
                        <td>Year: <?php echo htmlspecialchars($entry['year']); ?></td>
                        <td>Reason/s: <?php echo htmlspecialchars($entry['reason']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>

        </div>
    </div>

    <!-- Family Medical History -->
    <div class="section-container">
        <div class="section-title">Family Medical History</div>
        <div class="section-content">
            <p>Indicate the known health condition/s of your immediate family members.</p>
            <div class="checkbox-group">
                <?php foreach ($family_conditions as $key => $label): ?>
                    <?php
                    $additional_info = '';
                    if ($key === 'Cancer' && in_array('Cancer', $family_history)) {
                        $additional_info = explode(':', $medical_info['family_history'])[1] ?? '';
                    } elseif ($key === 'Food Allergies' && in_array('Food Allergies', $family_history)) {
                        $additional_info = explode(':', $medical_info['family_history'])[1] ?? '';
                    } elseif ($key === 'Other' && in_array('Other', $family_history)) {
                        $additional_info = explode(':', $medical_info['family_history'])[1] ?? '';
                    }
                    ?>
                    <div class="checkbox-line">
                        <span class="checkbox-view <?php echo is_checked($key, $family_history); ?>">✓</span>
                        <?php echo $label; ?>
                        <?php if ($additional_info): ?>
                            (Specify: <span class="view-only-text"><?php echo htmlspecialchars($additional_info); ?></span>)
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</body>

</html>