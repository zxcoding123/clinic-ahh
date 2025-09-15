<?php
session_start();
require_once 'config.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

// Check if patient_id or child_id is provided
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : null;
$child_id = isset($_GET['child_id']) ? intval($_GET['child_id']) : null;

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
        SELECT c.id, c.parent_id, c.last_name, c.first_name, c.middle_name, c.type AS child_type, c.id_path,
               p.surname AS parent_surname, p.firstname AS parent_firstname, p.middlename AS parent_middlename,
               p.email AS parent_email, p.contact_number AS parent_contact_number, p.city_address AS parent_city_address
        FROM children c
        LEFT JOIN patients p ON c.parent_id = p.id
        WHERE c.id = ?";
    $stmt_child = mysqli_prepare($conn, $sql_child);
    mysqli_stmt_bind_param($stmt_child, "i", $child_id);
    mysqli_stmt_execute($stmt_child);
    $result_child = mysqli_stmt_get_result($stmt_child);
    $child_data = mysqli_fetch_assoc($result_child);
    mysqli_stmt_close($stmt_child);

    if (!$child_data) {
        die("Error: Child not found.");
    }

    // Fetch emergency contact for the parent
    $sql_emergency = "
        SELECT surname, firstname, middlename, contact_number, relationship, city_address
        FROM emergency_contacts
        WHERE patient_id = ?";
    $stmt_emergency = mysqli_prepare($conn, $sql_emergency);
    mysqli_stmt_bind_param($stmt_emergency, "i", $child_data['parent_id']);
    mysqli_stmt_execute($stmt_emergency);
    $result_emergency = mysqli_stmt_get_result($stmt_emergency);
    $emergency_contact = mysqli_fetch_assoc($result_emergency) ?: [];
    mysqli_stmt_close($stmt_emergency);

    // Fetch medical info for the parent
    $sql_medical = "
        SELECT illnesses, medications, vaccination_status, menstruation_age, menstrual_pattern, pregnancies,
               live_children, menstrual_symptoms, past_illnesses, hospital_admissions, family_history, other_conditions
        FROM medical_info
        WHERE patient_id = ?";
    $stmt_medical = mysqli_prepare($conn, $sql_medical);
    mysqli_stmt_bind_param($stmt_medical, "i", $child_data['parent_id']);
    mysqli_stmt_execute($stmt_medical);
    $result_medical = mysqli_stmt_get_result($stmt_medical);
    $medical_info = mysqli_fetch_assoc($result_medical) ?: [];
    mysqli_stmt_close($stmt_medical);
} else {
    // Fetch patient data
    $sql_patient = "
        SELECT p.surname, p.firstname, p.middlename, p.suffix, p.birthday, p.age, p.sex, p.blood_type,
               p.religion, p.nationality, p.civil_status, p.email, p.contact_number, p.city_address,
               p.provincial_address, p.photo_path, p.course, p.department, p.year_level,
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
}

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

// Define possible conditions for checkboxes
$comorbid_conditions = [
    'Bronchial Asthma' => 'Bronchial Asthma ("Hika")',
    'Food Allergies' => 'Food Allergies',
    'Allergic Rhinitis' => 'Allergic Rhinitis',
    'Hyperthyroidism' => 'Hyperthyroidism',
    'Hypothyroidism' => 'Hypothyroidism/Goiter',
    'Anemia' => 'Anemia',
    'Major Depressive Disorder' => 'Major Depressive Disorder',
    'Bipolar Disorder' => 'Bipolar Disorder',
    'Generalized Anxiety Disorder' => 'Generalized Anxiety Disorder',
    'Panic Disorder' => 'Panic Disorder',
    'Posttraumatic Stress Disorder' => 'Posttraumatic Stress Disorder',
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
    'Urinary Tract Infection' => 'Urinary Tract Infection',
    'Appendicitis' => 'Appendicitis',
    'Injury' => 'Injury',
    'Burn' => 'Burn',
    'Cholecystitis' => 'Cholecystitis',
    'Stab/Laceration' => 'Stab/Laceration',
    'Fracture' => 'Fracture',
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
function is_checked($condition, $list) {
    return in_array($condition, $list) ? 'checked' : '';
}

// Helper function to format medications
function format_medications($meds) {
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

$formatted_medications = format_medications($medications);

// Parse hospital admissions
$hospital_admissions_formatted = array_fill(0, 3, ['year' => '', 'reason' => '']);
if (!empty($hospital_admissions)) {
    foreach ($hospital_admissions as $index => $admission) {
        if ($index < 3) {
            list($year, $reason) = explode(':', $admission, 2) + [null, null];
            $hospital_admissions_formatted[$index] = ['year' => $year ?? '', 'reason' => $reason ?? ''];
        }
    }
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

            margin: 0 auto;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            background-color: #f8f8f8;
            border-bottom: 1px solid #ddd;
        }

        .back-button {
            padding: 8px 15px;
            text-decoration: none;
            color: #007bff;
            font-weight: bold;
            font-size: 1em;
        }

        .back-button:hover {
            text-decoration: underline;
        }

        .header-logo {
            max-width: 100px;
            max-height: 60px;
            object-fit: contain;
        }

        .header-center {
            text-align: center;
            flex-grow: 1;
            min-width: 0;
        }

        .header-center h1 {
            margin: 0;
            font-size: 1.5em;
            font-weight: bold;
            color: #333;
        }

        .header-center p {
            margin: 2px 0;
            font-size: 0.9em;
            color: #555;
        }

        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                text-align: center;
                padding: 15px;
            }

            .back-button {
                margin-bottom: 10px;
                align-self: flex-start;
            }

            .header-logo {
                max-width: 80px;
                max-height: 50px;
            }

            .header-center h1 {
                font-size: 1.2em;
            }

            .header-center p {
                font-size: 0.8em;
            }
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


        .section-content {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .pi-section {
            flex-direction: row;
        }

        .photo-cell-container {
            flex: 0 0 auto;
        }

        .photo-cell {
            width: 150px;
            height: 150px;
            border: 1px solid #ccc;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .photo-cell img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }

        .personal-info-table {
            flex: 1;
        }

        .personal-info-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .personal-info-table td {
            padding: 8px;
            border: 1px solid #ddd;
        }



        @media (max-width: 768px) {
            .section-content {
                flex-direction: column;
                align-items: center;
            }

            .photo-cell-container {
                order: -1;
                /* Move image to top */
                margin-bottom: 20px;
            }

            .photo-cell {
                width: 120px;
                height: 120px;
                margin: 0 auto;
            }

            .personal-info-table table {
                display: block;
            }

            .personal-info-table tr {
                display: flex;
                flex-direction: column;
                margin-bottom: 10px;
                border-bottom: 1px solid #ddd;
                padding-bottom: 10px;
            }

            .personal-info-table td {
                border: none;
                padding: 5px;
                display: block;
                text-align: center;
            }

            .personal-info-table td:first-child {
                font-weight: bold;
                color: #333;
            }

            .personal-info-table td:not(:first-child) {
                margin-left: 10px;
            }

            .personal-info-table table table {
                display: block;
            }

            .personal-info-table table table tr {
                display: flex;
                flex-direction: column;
            }

            .personal-info-table table table td {
                display: block;
                text-align: left;
            }
        }
    </style>
</head>
<body>
    <!-- First Page -->
    <div class="header-container">
        <a href="patient-profile.php" class="back-button">Back</a>
        <img src="images/23.png" alt="WMSU Logo" class="header-logo">
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
        <div class="section-content pi-section">
            <div class="photo-cell-container">
                <div class="photo-cell">
                    <?php 
                    $photo_path = '';
                    if ($child_id && !empty($child_data['id_path'])) {
                        $photo_path = $child_data['id_path'];
                    } elseif (!$child_id && !empty($patient_data['photo_path'])) {
                        $photo_path = $patient_data['photo_path'];
                    }
                    // Ensure photo_path is relative to the web root
                    $full_photo_path = $photo_path ? $_SERVER['DOCUMENT_ROOT'] . '/' . $photo_path : '';
                    ?>
                    <?php if ($photo_path && file_exists($full_photo_path)): ?>
                        <img src="/<?php echo htmlspecialchars($photo_path); ?>" alt="<?php echo $child_id ? 'Child Photo' : 'Patient Photo'; ?>">
                    <?php else: ?>
                        (Photo of Patient)
                    <?php endif; ?>
                </div>
            </div>
            <div class="personal-info-table">
                <table>
                    <tr>
                        <td>Name:</td>
                        <td><span class="view-only-text"><?php echo htmlspecialchars($child_id ? $child_data['last_name'] : $patient_data['surname']); ?></span></td>
                        <td><span class="view-only-text"><?php echo htmlspecialchars($child_id ? $child_data['first_name'] : $patient_data['firstname']); ?></span></td>
                        <td><span class="view-only-text"><?php echo htmlspecialchars($child_id ? ($child_data['middle_name'] ?? '') : ($patient_data['middlename'] ?? '')); ?></span></td>
                    </tr>
                    <tr>
                        <td>Age:</td>
                        <td><span class="view-only-text"><?php echo htmlspecialchars($child_id ? '' : $patient_data['age']); ?></span></td>
                        <td>Sex:</td>
                        <td><span class="view-only-text"><?php echo htmlspecialchars($child_id ? '' : $patient_data['sex']); ?></span></td>
                    </tr>
                    <tr>
                        <td>Course:</td>
                        <td><span class="view-only-text"><?php echo htmlspecialchars($child_id ? ($child_data['child_type'] ?? '') : ($patient_data['course'] ?? '')); ?></span></td>
                        <td>Year Level:</td>
                        <td><span class="view-only-text"><?php echo htmlspecialchars($child_id ? '' : ($patient_data['year_level'] ?? '')); ?></span></td>
                    </tr>
                    <tr>
                        <td>Birthday (MM-DD-YY):</td>
                        <td><span class="view-only-text"><?php echo htmlspecialchars($child_id ? '' : ($patient_data['birthday'] ? date('m-d-Y', strtotime($patient_data['birthday'])) : '')); ?></span></td>
                        <td colspan="2">Religion:</td>
                        <td><span class="view-only-text"><?php echo htmlspecialchars($child_id ? '' : ($patient_data['religion'] ?? '')); ?></span></td>
                    </tr>
                    <tr>
                        <td>Nationality:</td>
                        <td><span class="view-only-text"><?php echo htmlspecialchars($child_id ? '' : ($patient_data['nationality'] ?? '')); ?></span></td>
                        <td colspan="2">Civil Status:</td>
                        <td><span class="view-only-text"><?php echo htmlspecialchars($child_id ? '' : ($patient_data['civil_status'] ?? '')); ?></span></td>
                    </tr>
                    <tr>
                        <td>Email Address:</td>
                        <td colspan="3"><span class="view-only-text"><?php echo htmlspecialchars($child_id ? ($child_data['parent_email'] ?? '') : ($patient_data['email'] ?? '')); ?></span></td>
                    </tr>
                    <tr>
                        <td>Contact #:</td>
                        <td colspan="3"><span class="view-only-text"><?php echo htmlspecialchars($child_id ? ($child_data['parent_contact_number'] ?? '') : ($patient_data['contact_number'] ?? '')); ?></span></td>
                    </tr>
                    <tr>
                        <td colspan="4">City Address: <span class="view-only-text"><?php echo htmlspecialchars($child_id ? ($child_data['parent_city_address'] ?? '') : ($patient_data['city_address'] ?? '')); ?></span></td>
                    </tr>
                    <tr>
                        <td colspan="4">Provincial Address (if applicable): <span class="view-only-text"><?php echo htmlspecialchars($child_id ? '' : ($patient_data['provincial_address'] ?? '')); ?></span></td>
                    </tr>
                    <tr>
                        <td colspan="4">
                            <table>
                                <tr>
                                    <td>Emergency Contact Person within Zamboanga City</td>
                                    <td>Name: <span class="view-only-text"><?php echo htmlspecialchars(($emergency_contact['firstname'] ?? '') . ' ' . ($emergency_contact['middlename'] ?? '') . ' ' . ($emergency_contact['surname'] ?? '')); ?></span></td>
                                    <td>Contact #: <span class="view-only-text"><?php echo htmlspecialchars($emergency_contact['contact_number'] ?? ''); ?></span></td>
                                    <td>Relationship: <span class="view-only-text"><?php echo htmlspecialchars($emergency_contact['relationship'] ?? ''); ?></span></td>
                                </tr>
                                <tr>
                                    <td colspan="4">City Address: <span class="view-only-text"><?php echo htmlspecialchars($emergency_contact['city_address'] ?? ''); ?></span></td>
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
                <?php foreach ($comorbid_conditions as $key => $label): ?>
                    <?php
                    $additional_info = '';
                    if ($key === 'Food Allergies' && in_array('Food Allergies', $illnesses)) {
                        $additional_info = explode(':', $medical_info['illnesses'])[1] ?? '';
                    } elseif ($key === 'Cancer' && in_array('Cancer', $illnesses)) {
                        $additional_info = explode(':', $medical_info['illnesses'])[1] ?? '';
                    } elseif ($key === 'Other' && in_array('Other', $illnesses)) {
                        $additional_info = explode(':', $medical_info['illnesses'])[1] ?? '';
                    }
                    ?>
                    <div class="checkbox-line">
                        <span class="checkbox-view <?php echo is_checked($key, $illnesses); ?>">✓</span>
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
                <?php for ($i = 0; $i < 5; $i++): ?>
                    <tr>
                        <td><?php echo $i + 1; ?>.</td>
                        <td><span class="view-only-text"><?php echo htmlspecialchars($formatted_medications[$i]['drug']); ?></span></td>
                        <td><span class="view-only-text"><?php echo htmlspecialchars($formatted_medications[$i]['dose']); ?></span></td>
                    </tr>
                <?php endfor; ?>
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

    <!-- Past Medical & Surgical History -->
    <div class="section-container">
        <div class="section-title">Past Medical & Surgical History</div>
        <div class="section-content">
            <p>Which of these conditions have you had in the past?</p>
            <div class="checkbox-group">
                <?php foreach ($past_conditions as $key => $label): ?>
                    <?php
                    $additional_info = ($key === 'Other' && in_array('Other', $past_illnesses)) ? ($past_illnesses[array_search('Other', $past_illnesses) + 1] ?? '') : '';
                    ?>
                    <div class="checkbox-line">
                        <span class="checkbox-view <?php echo is_checked($key, $past_illnesses); ?>">✓</span>
                        <?php echo $label; ?>
                        <?php if ($additional_info): ?>
                            (Specify: <span class="view-only-text"><?php echo htmlspecialchars($additional_info); ?></span>)
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <p>Have you ever been admitted to the hospital and/or underwent a surgery?</p>
            <table>
                <?php for ($i = 0; $i < 3; $i++): ?>
                    <tr>
                        <td style="width: 10%;"><span class="checkbox-view <?php echo $i === 0 && empty($hospital_admissions) ? 'checked' : ''; ?>">✓</span> No</td>
                        <td style="width: 10%;"><span class="checkbox-view <?php echo $i === 0 && !empty($hospital_admissions) ? 'checked' : ''; ?>">✓</span> Yes</td>
                        <td style="width: 30%;">Year: <span class="view-only-text"><?php echo htmlspecialchars($hospital_admissions_formatted[$i]['year']); ?></span></td>
                        <td style="width: 50%;">Reason/s: <span class="view-only-text"><?php echo htmlspecialchars($hospital_admissions_formatted[$i]['reason']); ?></span></td>
                    </tr>
                <?php endfor; ?>
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