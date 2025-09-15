<?php
session_start();
require_once 'config.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check database connection
if (!$conn) {
    error_log("Database connection failed: " . mysqli_connect_error());
    die("Database connection failed. Please contact the administrator.");
}

// Set UTF-8 charset
mysqli_set_charset($conn, 'utf8mb4');

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
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
    c.last_name, c.first_name, c.middle_name, 
    pt.*, 
    pr.id AS parent_record_id,
    pr.user_id AS parent_user_id,
    pr.id_path AS parent_id_path,
    u.user_type AS parent_user_type
FROM children c
LEFT JOIN parents pr 
    ON c.parent_id = pr.user_id
LEFT JOIN patients pt ON pt.user_id = pr.user_id
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
        $surname = $child_data['last_name'];

        $firstname = $child_data['first_name'];
        $middlename = $child_data['middle_name'];
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
        WHERE patient_id = (SELECT id FROM patients WHERE id = ?)";
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
        WHERE patient_id = (SELECT id FROM patients WHERE id = ?)";
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
        WHERE p.id = ?";
        $stmt_patient = mysqli_prepare($conn, $sql_patient);
        mysqli_stmt_bind_param($stmt_patient, "i", $patient_id);
        mysqli_stmt_execute($stmt_patient);
        $result_patient = mysqli_stmt_get_result($stmt_patient);
        $patient_data = mysqli_fetch_assoc($result_patient);
        mysqli_stmt_close($stmt_patient);

        if (!$patient_data) {
            die("Error: Patient not found.");
        }

        // Fetch latest emergency contact
        $sql_emergency = "
    SELECT e.surname, e.firstname, e.middlename, e.contact_number, e.relationship, e.city_address
    FROM emergency_contacts e
    JOIN patients p ON e.patient_id = p.id
    WHERE p.id = ?
    ORDER BY e.id DESC
    LIMIT 1";
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
        WHERE patient_id = (SELECT id FROM patients WHERE id = ? LIMIT 1)   ORDER BY id DESC LIMIT 1";
        $stmt_medical = mysqli_prepare($conn, $sql_medical);
        mysqli_stmt_bind_param($stmt_medical, "i", $patient_id);
        mysqli_stmt_execute($stmt_medical);
        $result_medical = mysqli_stmt_get_result($stmt_medical);
        $medical_info = mysqli_fetch_assoc($result_medical) ?: [];
        mysqli_stmt_close($stmt_medical);

        // Turn illnesses string into map: condition => extra info
        $illnesses_map = [];
        if (!empty($medical_info['illnesses'])) {
            $items = explode(',', $medical_info['illnesses']);
            foreach ($items as $item) {
                $parts = explode(':', $item, 2);
                $condition = strtolower(trim($parts[0]));
                $extra = isset($parts[1]) ? trim($parts[1]) : '';
                $illnesses_map[$condition] = $extra;
            }
        }

        // Parse into associative array
        $family_history_map = [];
        if (!empty($medical_info['family_history'])) {
            $items = explode(',', $medical_info['family_history']);
            foreach ($items as $item) {
                $parts = explode(':', $item, 2);
                $condition = trim($parts[0]);
                $extra = isset($parts[1]) ? trim($parts[1]) : '';

                // Handle "Other" specially
                if (stripos($condition, 'Other') === 0) {
                    $family_history_map[$condition] = $extra;
                } else {
                    $family_history_map[$condition] = $extra;
                }
            }
        }



        // Turn illnesses string into map: condition => extra info
        $past_illnesses_map = [];
        if (!empty($medical_info['past_illnesses'])) {
            $items = explode(',', $medical_info['past_illnesses']);
            foreach ($items as $item) {
                $parts = explode(':', $item, 2);
                $condition = strtolower(trim($parts[0]));
                $extra = isset($parts[1]) ? trim($parts[1]) : '';
                $past_illnesses_map[$condition] = $extra;
            }
        }



        // helper: returns "checked" if in illnesses
        function checkbox_status($key, $map)
        {
            $key = strtolower($key);
            return !empty($map[$key]) || array_key_exists($key, $map) ? 'checked' : '';
        }


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





        $menstrual_symptoms = !empty($medical_info['menstrual_symptoms'])
            ? explode(',', $medical_info['menstrual_symptoms'])
            : [];





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

$hospital_admissions = !empty($medical_info['hospital_admissions'])
    ? explode(',', $medical_info['hospital_admissions'])
    : [];

// Parse admissions into structured array [ ['year' => ..., 'reason' => ...], ... ]
$admissions = [];
foreach ($hospital_admissions as $entry) {
    $parts = explode(':', $entry, 2); // limit 2 in case reason has colon
    $admissions[] = [
        'year' => $parts[0] ?? '',
        'reason' => $parts[1] ?? ''
    ];
}

// If no admissions found, default to empty rows
if (empty($admissions)) {
    $admissions = [
        ['year' => '', 'reason' => ''],
        ['year' => '', 'reason' => ''],
        ['year' => '', 'reason' => ''],
        ['year' => '', 'reason' => ''],
    ];
}





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

// Normalize hospital admissions into a consistent array
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

// If still empty, ensure at least one blank row
if (empty($hospital_admissions_formatted)) {
    $hospital_admissions_formatted[1] = ['year' => '', 'reason' => ''];
    $hospital_admissions_formatted[2] = ['year' => '', 'reason' => ''];
    $hospital_admissions_formatted[3] = ['year' => '', 'reason' => ''];
    $hospital_admissions_formatted[4] = ['year' => '', 'reason' => ''];
}




$collegeCourses = [
    'CLA' => [
        'BAComm' => 'BA in Communication',
        'BAPsych' => 'BA in Psychology',
        'BAEnglish' => 'BA in English',
        'BAHistory' => 'BA in History',
    ],
    'CSM' => [
        'BSBio' => 'BS in Biology',
        'BSChem' => 'BS in Chemistry',
        'BSMath' => 'BS in Mathematics',
    ],
    'COE' => [
        'BSCivEng' => 'BS in Civil Engineering',
        'BSElecEng' => 'BS in Electrical Engineering',
    ],
    'CTE' => [
        'BSEdEng' => 'BSEd in English',
        'BSEdMath' => 'BSEd in Mathematics',
    ],
    'COA' => [
        'BSArch' => 'BS in Architecture',
    ],
    'CON' => [
        'BSN' => 'BS in Nursing',
    ],
    'CA' => [
        'BSAgri' => 'BS in Agriculture',
        'BSAgriBus' => 'BS in Agribusiness',
        'BSFoodTech' => 'BS in Food Technology',
    ],
    'CFES' => [
        'BSForestry' => 'BS in Forestry',
        'BSEnvSci' => 'BS in Environmental Science',
    ],
    'CCJE' => [
        'BSCrim' => 'BS in Criminology',
    ],
    'CHE' => [
        'BSHomeEcon' => 'BS in Home Economics',
    ],
    'CCS' => [
        'BSCompSci' => 'BS in Computer Science',
        'BSInfoTech' => 'BS in Information Technology',
    ],
    'COM' => [
        'MD' => 'Doctor of Medicine',
    ],
    'CPADS' => [
        'BSPubAdmin' => 'BS in Public Administration',
    ],
    'CSSPE' => [
        'BSSportsSci' => 'BS in Sports Science',
    ],
    'CSWCD' => [
        'BSSocWork' => 'BS in Social Work',
    ],
    'CAIS' => [
        'BAIslamic' => 'BA in Islamic Studies',
    ],
];

$departmentName = $department;
$courseName = $course;

if (isset($collegeCourses[$department][$course])) {
    $departmentName = $department; // or you can map department codes to full names
    $courseName = $collegeCourses[$department][$course];
}


$departmentNames = [
    "CLA"    => "College of Liberal Arts",
    "CSM"    => "College of Science and Mathematics",
    "COE"    => "College of Engineering",
    "CTE"    => "College of Teacher Education",
    "COA"    => "College of Architecture",
    "CON"    => "College of Nursing",
    "CA"     => "College of Agriculture",
    "CFES"   => "College of Forestry and Environmental Science",
    "CCJE"   => "College of Criminal Justice Education",
    "CHE"    => "College of Home Economics",
    "CCS"    => "College of Computing Studies",
    "COM"    => "College of Medicine",
    "CPADS"  => "College of Public Administration and Development Studies",
    "CSSPE"  => "College of Sports Science, Physical Education",
    "CSWCD"  => "College of Social Work and Community Development",
    "CAIS"   => "College of Asian and Islamic Studies"
];


$departmentFullName = $departmentNames[$department] ?? $department;

$sql = "SELECT * FROM consultations 
        WHERE patient_id = $patient_id 
        ORDER BY consultation_date DESC, consultation_time DESC";

$result = mysqli_query($conn, $sql);

$consultations = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $consultations[] = $row;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Record Template</title>
    <style>
        body {
            font-family: 'Times New Roman', Times, serif, sans-serif;
            background-color: white;
            margin: 60px;
            padding: 20px;
            font-size: larger;
        }

        .img {
            width: 100%;
            height: auto;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .header img {
            height: 50px;
            vertical-align: middle;
        }

        .header h1 {
            margin: 5px 0;
            font-size: 20px;

        }

        .header p {
            margin: 0;
            font-size: 12px;

        }

        .main-table {
            width: 100%;
            border-collapse: collapse;
            background-color: #fff;
            border: 1px solid black;
        }

        .main-table th {
            background-color: white;
            color: white;
            padding: 10px;
            text-align: left;

        }

        .main-table td {

            padding: 8px;
        }

        .section-header {
            background-color: #DAA520;
            color: white;
            font-weight: bold;
            padding: 5px;
            text-align: center;
        }

        .sub-table {
            width: 100%;
            border-collapse: collapse;
        }

        .sub-table td {
            border: 1px solid #ddd;
            padding: 5px;
        }

        .checkbox-list {
            margin: 5px 0;
        }

        .footer {
            text-align: center;
            font-size: 10px;
            color: #8B4513;
            margin-top: 20px;
        }

        td {
            border: 1px solid black;
        }

        @media print {
            body {
                padding: 0;
                background: white;
            }

            .container {
                box-shadow: none;
                padding: 0;
            }

            .submit-btn {
                display: none;
            }

            table,
            th,
            td {
                border: 1px solid black !important;
                border-collapse: collapse !important;
            }

            .print-btn {
                visibility: hidden;
            }

            .back-btn {
                visibility: hidden;
            }
        }


        .print-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: #8B0000;
            /* dark red */
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.2);
            font-size: 16px;
        }

        .print-btn:hover {
            background-color: #a40000;
            /* lighter red on hover */
        }
    </style>
</head>

<body>

   

    <style>

    </style>

    <div id="printableArea">
        <a href="profile.php" class="back-btn">Go Back</a>
        <br> <br>
        <div class="header"
            style="display: flex; align-items: center; justify-content: center; gap: 20px; text-align: left;">
            <img src="images/wmsu_logo.png" alt="Logo 1" style="height: 60px;">
            <img src="images/clinic.png" alt="Logo 1" style="height: 60px;">
            <div style="flex: 1;">
                <h1 style="margin: 0; color:#8B0000">WESTERN MINDANAO STATE UNIVERSITY</h1>
                <p style="margin: 2px 0; font-weight: bold;">ZAMBOANGA CITY</p>
                <p style="margin: 2px 0; font-weight: bold;">UNIVERSITY HEALTH SERVICES CENTER</p>
                <p style="margin: 2px 0; font-weight: bold;">Tel. no (062) 991-6736 / Email: <a href="#">
                        healthservices@wmsu.edu.ph</a></p>

            </div>
            <img src="images/ISO.png" alt="Logo 2" style="height: 60px;">
        </div>
        <table class="main-table">
            <tr>
                <td colspan="4">
                    <div style="background-color: #8B0000; color: white; padding: 10px; text-align: center;">
                        <b>PATIENT HEALTH PROFILE & CONSULTATIONS RECORD</b>
                    </div>
                    <p style="text-align: center;">(Electronic or Paper-based Input)</p>
                </td>

            </tr>

            <tr>
                <td style="text-align: center; height:200px; width: 200px;" rowspan="2">
                    <?php
                    $full_photo_path = $photo_path ?  $photo_path : '';
                    ?>

                    <?php if ($photo_path && file_exists($full_photo_path)): ?>
                        <img src="<?php echo htmlspecialchars($photo_path); ?>" alt="<?php echo $child_id ? 'Child Photo' : 'Patient Photo'; ?>" class="img">
                    <?php else: ?>
                        (Photo of Patient)
                    <?php endif; ?>
                </td>
                <td colspan="3">

                    <div style="display: flex; justify-content: space-between; text-align: center; margin-top: 10px;">
                        <b>Name:</b>
                        <div style="flex: 1;"><i> <?php echo $surname ?></i></div>
                        <div style="flex: 1;"><i><?php echo $firstname ?></i></div>
                        <div style="flex: 1;"><i><?php echo $middlename ?></i></div>
                    </div>
                </td>
            </tr>

            <tr>
                <td colspan="3">
                    <div style="display: flex; justify-content: space-between; text-align: center; margin-top: 10px;">
                        <span style="color:white"><b>Name:</b></span>
                        <div style="flex: 1;"><i>(Surname)</i></div>
                        <div style="flex: 1;"><i>(First Name)</i></div>
                        <div style="flex: 1;"><i>(Middle Name)</i></div>
                    </div>

                </td>
            </tr>

            <tr>
                <td><b>Age: </b> <?php echo $age ?></td>
                <td><b>Sex: </b> <?php echo ucfirst($sex) ?></td>
                <td><b>Course: </b> <?php echo $courseName ?></td>
                <td><b>Year Level: </b> <?php echo $year_level ?></td>
            </tr>

            <tr>
                <td colspan="3">
                    <b>Birthday</b> <i>(MM-DD-YY)</i>:
                    <?php echo date("m-d-y", strtotime($birthday)); ?>

                </td>
                <td colspan="2">
                    <b>Religion: </b> <?php echo $religion ?>
                </td>
            </tr>

            <tr>
                <td colspan="3">
                    <b>Nationality:</b> <?php echo $nationality ?>
                </td>
                <td colspan="2">
                    <b>Civil Status:</b> <?php echo $civil_status ?>
                </td>
            </tr>

            <tr>
                <td colspan="3">
                    <b>Email Address:</b> <?php echo $email ?>
                </td>
                <td colspan="2">
                    <b>Contact Number:</b> <?php echo $contact_number ?>
                </td>
            </tr>

            <tr>
                <td colspan="4"><b>City Address: </b><br><br>
                    <?php echo $city_address ?></td>
            </tr>

            <tr>
                <td colspan="4"><b>Provincial Address (if applicable): </b><br><br>
                    <?php echo $provincial_address ?></td>
            </tr>

            <tr>
                <td style="text-align: center; height:200px; " rowspan="3">
                    <b>Emergency Contact Person</b>
                    <br>within<br> Zamboanga City
                </td>

                <td colspan="3">
                    <b>Name: <?php echo
                                $firstname_emergency_contact . " " .  $middlename_emergency_contact . " " .  $surname_emergency_contact ?></b>
                </td>
            </tr>

            <tr>
                <td colspan="2">
                    <b>Contact Number: </b>
                    </b> <?php echo $contact_number_emergency_contact ?>
                </td>
                <td colspan="2">
                    <b>Relationship: </b> <?php echo $relationship_emergency_contact ?>
                </td>
            </tr>

            <tr>
                <td colspan="3">
                    <b>City Address: </b><br><br>
                    <?php echo $city_address_emergency_contact ?>
                </td>
            </tr>

            <style>
                .checkbox-line {
                    display: flex;
                    align-items: center;
                    margin: 4px 0;
                    font-family: 'Times New Roman', Times, serif, sans-serif;
                }

                .checkbox-fake {
                    width: 16px;
                    height: 16px;
                    border: 2px solid #000;
                    display: inline-block;
                    margin-right: 8px;
                    position: relative;
                }

                /* default: empty box */
                .checkbox-fake::after {
                    content: "✔";
                    font-size: 14px;
                    position: absolute;
                    top: -3px;
                    left: 1px;
                    color: transparent;
                    /* hidden unless chosen */
                }

                /* chosen = black check */
                .checkbox-fake.checked::after {
                    color: black;
                }
            </style>

            <tr>
                <td style="text-align: center;"><b>Comorbid Illnesses</b></td>
                <td colspan="3">
                    Which of these conditions do you currently have?
                    <br> <br>
                    <div style="margin-left: 20px; margin-top: 5px;">
                        <div class="checkbox-line">
                            <span class="checkbox-fake <?php echo checkbox_status('asthma', $illnesses_map); ?>"></span>
                            Bronchial Asthma ("Hika")
                        </div>
                        <div class="checkbox-line">
                            <span class="checkbox-fake <?php echo checkbox_status('allergies', $illnesses_map); ?>"></span>
                            Food Allergies <span> &nbsp; (Specify food: <?php echo !empty($illnesses_map['allergies']) ? htmlspecialchars($illnesses_map['allergies']) : '__________________________________'; ?>)</span>
                        </div>
                        <div class="checkbox-line">
                            <span class="checkbox-fake <?php echo checkbox_status('hyperthyroidism', $illnesses_map); ?>"></span>
                            Hyperthyroidism
                        </div>
                        <div class="checkbox-line">
                            <span class="checkbox-fake <?php echo checkbox_status('hypothyroidism', $illnesses_map); ?>"></span>
                            Hypothyroidism/Goiter
                        </div>
                        <div class="checkbox-line">
                            <span class="checkbox-fake <?php echo checkbox_status('anemia', $illnesses_map); ?>"></span>
                            Anemia
                        </div>
                        <div class="checkbox-line">
                            <span class="checkbox-fake <?php echo checkbox_status('psychiatric', $illnesses_map); ?>"></span>
                            Psychiatric Illness
                        </div>
                        <div style="margin-left: 50px; margin-top: 10px;">
                            <div class="checkbox-line">
                                <span class="checkbox-fake <?php echo checkbox_status('depression', $illnesses_map); ?>"></span>
                                Major Depressive Disorder
                            </div>
                        </div>
                        <div style="margin-left: 50px;"">
                <div class=" checkbox-line">
                            <span class="checkbox-fake <?php echo checkbox_status('bipolar', $illnesses_map); ?>"></span>
                            Bipolar Disorder
                        </div>
                    </div>
                    <div style="margin-left: 50px;">
                        <div class="checkbox-line">
                            <span class="checkbox-fake <?php echo checkbox_status('anxiety', $illnesses_map); ?>"></span>
                            Generalized Anxiety Disorder
                        </div>
                    </div>

                    <div style="margin-left: 50px;">
                        <div class="checkbox-line">
                            <span class="checkbox-fake <?php echo checkbox_status('panic', $illnesses_map); ?>"></span>
                            Panic Disorder
                        </div>
                    </div>

                    <div style="margin-left: 50px;">
                        <div class="checkbox-line">
                            <span class="checkbox-fake <?php echo checkbox_status('ptsd', $illnesses_map); ?>"></span>
                            Postraumatic Stress Disorder
                        </div>
                    </div>

                    <div style="margin-left: 50px;">
                        <div class="checkbox-line">
                            <span class="checkbox-fake <?php echo checkbox_status('schizophrenia', $illnesses_map); ?>"></span>
                            Schizophrenia
                        </div>
                    </div>

                    <div style="margin-left: 50px;">
                        <div class="checkbox-line">
                            <span class="checkbox-fake <?php echo checkbox_status('other mental illness', $illnesses_map); ?>"></span>
                            Other: <?php echo !empty($illnesses_map['other mental illness']) ? htmlspecialchars($illnesses_map['other mental illness']) : '__________________________________'; ?>
                        </div>
                    </div>

                    <div class="checkbox-line" style="margin-top: 20px;">
                        <span class="checkbox-fake <?php echo checkbox_status('migraine', $illnesses_map); ?>"></span>
                        Migraine &nbsp; <i>(recurrent headaches)</i>
                    </div>
                    <div class="checkbox-line">
                        <span class="checkbox-fake <?php echo checkbox_status('epilepsy', $illnesses_map); ?>"></span>
                        Epilepsy/Seizures
                    </div>
                    <div class="checkbox-line">
                        <span class="checkbox-fake <?php echo checkbox_status('gerd', $illnesses_map); ?>"></span>
                        Gastroesophageal Reflux Disease (GERD)

                    </div>
                    <div class="checkbox-line">
                        <span class="checkbox-fake <?php echo checkbox_status('bowel_syndrome', $illnesses_map); ?>"></span>
                        Irritable Bowel Syndrome
                    </div>
                    <div class="checkbox-line">
                        <span class="checkbox-fake <?php echo checkbox_status('hypertension', $illnesses_map); ?>"></span>
                        Hypertension &nbsp; <i>(elevated blood pressure)</i>
                    </div>
                    <div class="checkbox-line">
                        <span class="checkbox-fake <?php echo checkbox_status('diabetes', $illnesses_map); ?>"></span>
                        Diabetus mellitus &nbsp; <i>(elevated blood sugar)</i>
                    </div>
                    <div class="checkbox-line">
                        <span class="checkbox-fake <?php echo checkbox_status('dyslipidemia', $illnesses_map); ?>"></span>
                        Dyslipidemia &nbsp; <i>(elevated cholesterol levels)</i>
                    </div>
                    <div class="checkbox-line">
                        <span class="checkbox-fake <?php echo checkbox_status('arthritis', $illnesses_map); ?>"></span>
                        Arthritis &nbsp; <i>(joint pains)</i>
                    </div>
                    <div class="checkbox-line">
                        <span class="checkbox-fake <?php echo checkbox_status('lupus', $illnesses_map); ?>"></span>
                        Systemic Lupus Erythematosus (SLE)
                    </div>
                    <div class="checkbox-line">
                        <span class="checkbox-fake <?php echo checkbox_status('pcos', $illnesses_map); ?>"></span>
                        Polycystic Ovary Syndrome (PCOS)
                    </div>
                    <div class="checkbox-line">
                        <span class="checkbox-fake <?php echo checkbox_status('cancer', $illnesses_map); ?>"></span>
                        Cancer &nbsp; (Specify: <?php echo !empty($illnesses_map['cancer']) ? htmlspecialchars($illnesses_map['cancer']) : '__________________________________'; ?>)
                    </div>

                    <div class="checkbox-line">
                        <span class="checkbox-fake <?php echo checkbox_status('other', $illnesses_map); ?>"></span>
                        Other: <?php echo !empty($illnesses_map['other']) ? htmlspecialchars($illnesses_map['other']) : '__________________________________'; ?>
                    </div>

    </div>
    </td>
    </tr>

    <?php
    // Example raw data from DB
    $raw_meds = $medical_info['medications'] ?? '';

    // Parse raw string "drug:dose:unit:frequency,drug2:..."
    $medications = [];
    if (!empty($raw_meds)) {
        $parts = explode(',', $raw_meds);
        foreach ($parts as $part) {
            $fields = explode(':', $part);
            $medications[] = [
                'drug' => $fields[0] ?? '',
                'dose' => $fields[1] ?? '',
                'unit' => $fields[2] ?? '',
                'frequency' => $fields[3] ?? ''
            ];
        }
    }

    // Use your formatter
    $formatted_meds = format_medications($medications);
    ?>


    <tr>
        <td rowspan="6" style="text-align: center;"><b>Maintenance Medications</b></td>
        <td colspan="2" style="text-align: center;"><b>Generic Name of Drug</b></td>
        <td colspan="2" style="text-align: center;"><b>Dose and Frequency</b></td>
    </tr>

    <?php foreach ($formatted_meds as $i => $med): ?>
        <tr>
            <td colspan="2"><?= ($i + 1) . '. ' . htmlspecialchars($med['drug']) ?></td>
            <td colspan="2"><?= ($i + 1) . '. ' . htmlspecialchars($med['dose']) ?></td>
        </tr>
    <?php endforeach; ?>



    <tr>
        <td colspan="1" style="text-align:center"><b>COVID-19 <br> Vaccination</b></td>
        <td colspan="3">
            <div class="container" style="margin: 30px;">
                <div class="checkbox-line">
                    <span class="checkbox-fake <?php echo $vaccination_status === 'fully' ? 'checked' : ''; ?>"></span>Fully vaccinated &nbsp; <i>(Primary series with or without
                        booster shot/s)</i>
                </div>

                <div class="checkbox-line">
                    <span class="checkbox-fake <?php echo $vaccination_status === 'partially' ? 'checked' : ''; ?>"></span>Partially vaccinated &nbsp; <i>(Incomplete primary
                        series)</i>
                </div>

                <div class="checkbox-line">
                    <span class="checkbox-fake <?php echo $vaccination_status === 'not' ? 'checked' : ''; ?>"></span>Not vaccinated
                </div>
            </div>
        </td>
    </tr>

    <?php if($sex == 'female') ?> 
    <tr>
        <td style="text-align: center;"><b>Menstrual & Obstetric History </b><br> <i>(for females only)</i></td>
        <td colspan="2">
            <br>
            Age when menstruation began: <?php echo $menstruation_age ?>
            <div class="container" style="margin-left: 25px; margin-top: 10px;">
                <div class="checkbox-line">
                    <span class="checkbox-fake <?php echo $menstrual_pattern === 'regular' ? 'checked' : ''; ?>"></span>Regular (monthly)
                </div>
                <div class="checkbox-line">
                    <span class="checkbox-fake <?php echo $menstrual_pattern === 'irregular' ? 'checked' : ''; ?>"></span>Irregular
                </div>
            </div>
            <br>
            Number of pregnancies: <?php echo $pregnancies ?>
            <br>
            Number of live children: <?php echo $live_children ?>
        </td>
        <td colspan="2">
            <br>
            Menstrual Symptoms:
            <div class="container" style="margin-left: 25px; margin-top: 10px;">
                <div class="checkbox-line">
                    <span class="checkbox-fake <?php if (in_array('dysmenorrhea', $menstrual_symptoms)) echo 'checked'; ?>"></span>Dysmenorrhea (cramps)
                </div>
                <div class="checkbox-line">
                    <span class="checkbox-fake <?php if (in_array('migraine', $menstrual_symptoms)) echo 'checked'; ?>"></span>Migraine
                </div>
                <div class="checkbox-line">
                    <span class="checkbox-fake <?php if (in_array('consciousness', $menstrual_symptoms)) echo 'checked'; ?>"></span>Loss of consciousness
                </div>
                <div class="checkbox-line">
                    <span class="checkbox-fake <?php if (in_array('other', $menstrual_symptoms)) echo 'checked'; ?>"></span>Other: ______________
                </div>
            </div>

        </td>
    </tr>
     <?php ?> 

    <tr>
        <td rowspan="4" style="text-align: center;"><b>Past Medical & Surgery History</b></td>
        <td colspan="3"><i>Which of these conditions have you had in the past?</i></td>
    </tr>
    <tr>
        <td colspan="2">
            <div class="checkbox-line">
                <span class="checkbox-fake <?php echo checkbox_status('varicella', $past_illnesses_map); ?>"></span>Varicella (Chicken Pox)
            </div>
            <div class="checkbox-line">
                <span class="checkbox-fake <?php echo checkbox_status('dengue', $past_illnesses_map); ?>"></span>Dengue
            </div>
            <div class="checkbox-line">
                <span class="checkbox-fake <?php echo checkbox_status('tuberculosis', $past_illnesses_map); ?>"></span>Tuberculosis
            </div>
            <div class="checkbox-line">
                <span class="checkbox-fake <?php echo checkbox_status('varicella', $past_illnesses_map); ?>"></span>Pneumonia
            </div>
            <div class="checkbox-line">
                <span class="checkbox-fake <?php echo checkbox_status('uti', $past_illnesses_map); ?>"></span>Urinary Tract Infection
            </div>
            <div class="checkbox-line">
                <span class="checkbox-fake <?php echo checkbox_status('appendicitis', $past_illnesses_map); ?>"></span>Appendicitis
            </div>
            <div class="checkbox-line">
                <span class="checkbox-fake <?php echo checkbox_status('cholecystitis', $past_illnesses_map); ?>"></span>Cholecystitis
            </div>
          
        </td>
        <td colspan="2">
            <div class="checkbox-line">
                <span class="checkbox-fake <?php echo checkbox_status('cholecystitis', $past_illnesses_map); ?>"></span>Measles
            </div>
            <div class="checkbox-line">
                <span class="checkbox-fake <?php echo checkbox_status('cholecystitis', $past_illnesses_map); ?>"></span>Typhoid Fever
            </div>
            <div class="checkbox-line">
                <span class="checkbox-fake <?php echo checkbox_status('cholecystitis', $past_illnesses_map); ?>"></span>Amoebiasis
            </div>
            <div class="checkbox-line">
                <span class="checkbox-fake <?php echo checkbox_status('cholecystitis', $past_illnesses_map); ?>"></span>Nephro/Urolithiasis <small><i> (kidney stones)</i></small>
            </div>
            <div class="checkbox-line">
                <span class="checkbox-fake <?php echo checkbox_status('injury', $past_illnesses_map); ?>"></span>Injury
            </div>
            <div class="container" style="margin-left: 20px">
                <div class="checkbox-line">
                    <span class="checkbox-fake <?php echo checkbox_status('burn', $past_illnesses_map); ?>"></span>Burn
                </div>
                <div class="checkbox-line">
                    <span class="checkbox-fake <?php echo checkbox_status('stab', $past_illnesses_map); ?>"></span>Stab
                </div>
                <div class="checkbox-line">
                    <span class="checkbox-fake <?php echo checkbox_status('fracture', $past_illnesses_map); ?>"></span>Fracture
                </div>
            </div>
            <div class="container">
                <div class="checkbox-line">
                    <span class="checkbox-fake <?php echo checkbox_status('appendicitis', $past_illnesses_map); ?>"></span>Appendicitis
                </div>
                <div class="checkbox-line">
                    <span class="checkbox-fake <?php echo checkbox_status('cholecystitis', $past_illnesses_map); ?>"></span>Cholecystitis
                </div>
                <div class="checkbox-line">
                    <span class="checkbox-fake <?php echo checkbox_status('other', $past_illnesses_map); ?>"></span>Other: <?php echo !empty($past_illnesses_map['other']) ? htmlspecialchars($past_illnesses_map['other']) : '__________________________________'; ?>
                </div>
            </div>
        </td>
    </tr>

    <tr>
        <td colspan="3"><i>Have you ever been admitted to the hospital and/or underwent a surgery?</i></td>

    </tr>



    <tr>
        <td>
            <div class="container" style="display: flex; justify-content: center; align-items: center;">
                <div class="checkbox-line">
                    <span class="checkbox-fake <?php echo empty($hospital_admissions) ? 'checked' : ''; ?>"></span>No
                </div>
            </div>
        </td>
        <td colspan="2">
            <div class="checkbox-line">
                <span class="checkbox-fake <?php echo !empty($hospital_admissions) ? 'checked' : ''; ?>"></span> Yes
            </div>
            <br>

            <?php foreach ($hospital_admissions_formatted as $adm): ?>
                Year:
                <?php echo !empty($adm['year']) ? htmlspecialchars($adm['year']) : '______________________'; ?>
                &nbsp; Reason/s:
                <?php echo !empty($adm['reason']) ? htmlspecialchars($adm['reason']) : '____________________________________________________________'; ?>
                <br><br>
            <?php endforeach; ?>
        </td>
    </tr>




    <tr>
        <td style="text-align: center;"> <b>Family <br> Medical <br> History</b></td>
        <td colspan="3">
            Indicate the known health condtion/s of your immediate family members.
            <br>
            <br>
            <div style="margin-left: 20px; margin-top: 5px;">
                <div style="margin-left: 20px; margin-top: 5px;">

                    <div class="checkbox-line">
                        <span class="checkbox-fake <?php echo checkbox_status('hypertension', $family_history_map); ?>"></span>
                        Hypertension &nbsp; <i>(elevated blood pressure)</i>
                    </div>

                    <div class="checkbox-line">
                        <span class="checkbox-fake <?php echo checkbox_status('coronary', $family_history_map); ?>"></span>
                        Coronary Artery Disease
                    </div>

                    <div class="checkbox-line">
                        <span class="checkbox-fake <?php echo checkbox_status('heart_failure', $family_history_map); ?>"></span>
                        Congestive Heart Failure
                    </div>


                    <div class="checkbox-line">
                        <span class="checkbox-fake <?php echo checkbox_status('diabetes', $family_history_map); ?>"></span>
                        Diabetus mellitus &nbsp; <i>(elevated blood sugar)</i>
                    </div>

                    <div class="checkbox-line">
                        <span class="checkbox-fake <?php echo checkbox_status('chronic_kidney', $family_history_map); ?>"></span>
                        Chronic Kidney Disease &nbsp; (with/withour regular Hemodialysis)
                    </div>

                    <div class="checkbox-line">
                        <span class="checkbox-fake <?php echo checkbox_status('dyslipidemia', $family_history_map); ?>"></span>
                        Dyslipidemia &nbsp; (elevated cholesterol levels)
                    </div>

                    <div class="checkbox-line">
                        <span class="checkbox-fake <?php echo checkbox_status('lupus', $family_history_map); ?>"></span>
                        Systemic Lupus Erythematosus (SLE)
                    </div>





                    <div class="checkbox-line">
                        <span class="checkbox-fake <?php echo checkbox_status('arthritis', $family_history_map); ?>"></span>
                        Arthritis &nbsp; <i>(joint pains)</i>
                    </div>

                    <div class="checkbox-line">


                        <span class="checkbox-fake <?php echo !empty($family_history_map['Cancer']) ? 'checked' : ''; ?>"></span>
                        Cancer: &nbsp; <?php echo !empty($family_history_map['Cancer']) ? htmlspecialchars($family_history_map['Cancer']) : '__________________________________'; ?>
                    </div>

                    <div class="checkbox-line">
                        <span class="checkbox-fake <?php echo checkbox_status('asthma', $family_history_map); ?>"></span>
                        Bronchial Asthma ("Hika")
                    </div>

                    <div class="checkbox-line">
                        <span class="checkbox-fake <?php echo checkbox_status('copd', $family_history_map); ?>"></span>
                        Chronic Obstructive Pulmonary Disease (COPD)
                    </div>

                    <div class="checkbox-line">
                        <span class="checkbox-fake <?php echo !empty($family_history_map['Family Allergies']) ? 'checked' : ''; ?>"></span>

                        Food Allergies
                        <span> &nbsp; (Specify food:
                            <?php echo !empty($family_history_map['Family Allergies'])
                                ? htmlspecialchars($family_history_map['Family Allergies'])
                                : '__________________________________'; ?>)</span>
                    </div>


                    <div class="checkbox-line">
                        <span class="checkbox-fake <?php echo checkbox_status('rhinitis', $family_history_map); ?>"></span>
                        Allergic Rhinitis
                    </div>

                    <div class="checkbox-line">
                        <span class="checkbox-fake <?php echo checkbox_status('hyperthyroidism', $family_history_map); ?>"></span>
                        Hyperthyroidism
                    </div>

                    <div class="checkbox-line">
                        <span class="checkbox-fake <?php echo checkbox_status('hypothyroidism', $family_history_map); ?>"></span>
                        Hypothyroidism/Goiter
                    </div>

                    <div class="checkbox-line">
                        <span class="checkbox-fake <?php echo checkbox_status('psychiatric', $family_history_map); ?>"></span>
                        Psychiatric Illness
                    </div>
                    <div style="margin-left: 50px; margin-top: 10px;">
                        <div class="checkbox-line">
                            <span class="checkbox-fake <?php echo checkbox_status('depression', $family_history_map); ?>"></span>
                            Major Depressive Disorder
                        </div>
                    </div>
                    <div style="margin-left: 50px;"">
                <div class=" checkbox-line">
                        <span class="checkbox-fake <?php echo checkbox_status('bipolar', $family_history_map); ?>"></span>
                        Bipolar Disorder
                    </div>
                </div>
                <div style="margin-left: 50px;">
                    <div class="checkbox-line">
                        <span class="checkbox-fake <?php echo checkbox_status('anxiety', $family_history_map); ?>"></span>
                        Generalized Anxiety Disorder
                    </div>
                </div>

                <div style="margin-left: 50px;">
                    <div class="checkbox-line">
                        <span class="checkbox-fake <?php echo checkbox_status('panic', $family_history_map); ?>"></span>
                        Panic Disorder
                    </div>
                </div>

                <div style="margin-left: 50px;">
                    <div class="checkbox-line">
                        <span class="checkbox-fake <?php echo checkbox_status('ptsd', $family_history_map); ?>"></span>
                        Postraumatic Stress Disorder
                    </div>
                </div>

                <div style="margin-left: 50px;">
                    <div class="checkbox-line">
                        <span class="checkbox-fake <?php echo checkbox_status('schizophrenia', $family_history_map); ?>"></span>
                        Schizophrenia
                    </div>
                </div>

                <div style="margin-left: 50px;">
                    <div class="checkbox-line">
                        <span class="checkbox-fake <?php echo !empty($family_history_map['Other Mental Illness (Family)']) ? 'checked' : ''; ?>"></span>
                        Other: &nbsp; <span>
                            <?php echo !empty($family_history_map['Other Mental Illness (Family)'])
                                ? htmlspecialchars($family_history_map['Other Mental Illness (Family)'])
                                : '__________________________________'; ?></span>
                    </div>
                </div>

                <div class="checkbox-line">
                    <span class="checkbox-fake <?php echo checkbox_status('schizophrenia', $family_history_map); ?>"></span>
                    Epilepsy/Seizures
                </div>

                <div class="checkbox-line">
                    <span class="checkbox-fake <?php echo !empty($family_history_map['Other (Family)']) ? 'checked' : ''; ?>"></span>
                    Other: <?php echo !empty($family_history_map['Other (Family)'])
                        ? htmlspecialchars($family_history_map['Other (Family)'])
                        : '__________________________________'; ?></span>
                </div>
            </div>
            </div>
        </td>
    </tr>
    </table>

 
    <?php if (count($consultations) >= 0): ?>
        <table class="main-table" style="margin-top: 25px; border-collapse: collapse; width: 100%;" border="1">
            <tr>
                <td colspan="7" style="background-color: #8B0000; color: white; padding: 10px; text-align: center;">
                    <b>CONSULTATIONS RECORD</b>
                </td>
            </tr>
            <tr style="text-align: center; font-weight: bold;">
                <td style="width: 10%; padding:10px;">Date <br> (mm-dd-yy) and Type</td>
                <td style="width: 20%;">Signs & Symptoms</td>
                <td style="width: 15%;">Vital Signs</td>
                <td style="width: 15%;">Test Results</td>
                <td style="width: 15%;">Diagnosis</td>
                <td style="width: 20%;">Management</td>
                <td style="width: 20%;">Nurse/Physician In-charge</td>
            </tr>


            <?php foreach ($consultations as $consultation): ?>
                <tr class="consultation-row">
                    <td style="text-align: center;">
                        <?php
                        $date = new DateTime($consultation['consultation_date']);
                        echo $date->format('F j, Y');

                        ?><br>
                        <?php
                        $time = new DateTime($consultation['consultation_time']);
                        echo "@ " . $time->format('h:i A');
                        echo "<br> - <br> ";
                        echo "<b>" . ucfirst($consultation['consultation_type']) . "</b>";
                        ?>

                    </td>
                    <td style="text-align: center;"><?php echo htmlspecialchars($consultation['complaints'] ?? 'N/A'); ?></td>


                    <td style="text-align: center;">
                        <p style="border-bottom: 1px solid black; width: 100%;">
                            <b>HR:</b> <?php echo htmlspecialchars($consultation['heart_rate'] ?? 'N/A'); ?> <br> <br>
                        </p>

                        <p style="border-bottom: 1px solid black; width: 100%;">
                            <b>RR:</b> <?php echo htmlspecialchars($consultation['respiratory_rate'] ?? 'N/A'); ?> <br> <br>
                        </p>

                        <p style="border-bottom: 1px solid black; width: 100%;">
                            <b>Temp:</b> <?php echo htmlspecialchars($consultation['temperature'] ?? 'N/A'); ?>°C <br> <br>
                        </p>

                        <p style="border-bottom: 1px solid black; width: 100%;">
                            <b>O2 Sat:</b> <?php echo htmlspecialchars($consultation['oxygen_saturation'] ?? 'N/A'); ?> <br> <br>
                        </p>

                        <p style="width: 100%;">
                            <b>BP:</b> <?php echo htmlspecialchars($consultation['blood_pressure'] ?? 'N/A'); ?> <br>
                        </p>
                    </td>

                    <td style="text-align: center;"><?php echo htmlspecialchars($consultation['test_results'] ?? 'N/A'); ?></td>
                    <td style="text-align: center;"><?php echo htmlspecialchars($consultation['diagnosis'] ?? 'N/A'); ?></td>
                    <td style="text-align: center;"><?php echo htmlspecialchars($consultation['treatment'] ?? 'N/A'); ?></td>
                    <td style="text-align: center;">
                        <?php if (!empty($consultation['staff_signature'])): ?>
                            <img src="<?= $consultation['staff_signature'] ?>" alt="Signature" style="max-width:150px; height:auto;">
                        <?php else: ?>
                            <span>No signature</span>
                        <?php endif; ?>

                        <br> <br>
                        <?php echo $consultation['staff_name'] ?>
                    </td>

                </tr>
            <?php endforeach; ?>
        <?php else: ?>

            <tr style="text-align: center; font-weight: bold;">
                <td style="width: 10%; padding:10px;">Date <br> (mm-dd-yy)</td>
                <td style="width: 20%;">Signs & Symptoms</td>
                <td style="width: 15%;">Vital Signs</td>
                <td style="width: 15%;">Test Results</td>
                <td style="width: 15%;">Diagnosis</td>
                <td style="width: 20%;">Management</td>
                <td style="width: 20%;">Nurse/Physician In-charge</td>
            </tr>

            <tr>
                <td></td>
                <td></td>
                <td>
                    <p style="border-bottom: 1px solid black; width: 100%;">
                        HR: <br> <br>
                    </p>

                    <p style="border-bottom: 1px solid black; width: 100%;">
                        RR: <br> <br>
                    </p>

                    <p style="border-bottom: 1px solid black; width: 100%;">
                        Temp: <br> <br>
                    </p>

                    <p style="border-bottom: 1px solid black; width: 100%;">
                        O2 Sat: <br> <br>
                    </p>

                    <p style="width: 100%;">
                        BP: <br>
                    </p>
                </td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>


        <?php endif; ?>
        </table>
        </div>

    

    <br> <br>
   
    </div>

</body>

</html>