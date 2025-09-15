<?php
session_start();

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Sanitize input function
function sanitizeInput($data)
{
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check authentication and document upload status
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// if (!isset($_SESSION['documents_uploaded'])) {
//      header("Location: uploaddocs");
//     exit();
// }

include 'config.php';

// Database connection
$userId = $_SESSION['user_id'];

// Check if profile is already submitted
$query = "SELECT profile_submitted, user_type FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user && $user['user_type'] !== 'Parent') {
    header("Location: form.php");
    exit();
}

// if ($user && $user['profile_submitted'] == 1) {
//     header("Location: homepage.php");
//     exit();
// }

// Fetch parent, children, and user data
$parent = null;
$children = [];
$userData = null;
if ($user['user_type'] === 'Parent') {
    // Fetch user data (parent's name and email)
    $userQuery = $conn->prepare("SELECT last_name, first_name, middle_name, email FROM users WHERE id = ?");
    $userQuery->bind_param("i", $userId);
    $userQuery->execute();
    $userData = $userQuery->get_result()->fetch_assoc();
    $userQuery->close();

    // Fetch parent record
    $parentQuery = $conn->prepare("SELECT id FROM parents WHERE user_id = ?");
    $parentQuery->bind_param("i", $userId);
    $parentQuery->execute();
    $parent = $parentQuery->get_result()->fetch_assoc();
    $parentQuery->close();

    if ($parent) {

        // Fetch patient ID for the parent
        $patientQuery = $conn->prepare("SELECT user_id FROM patients WHERE user_id = ?");
        $patientQuery->bind_param("i", $userId);
        $patientQuery->execute();
        $patient = $patientQuery->get_result()->fetch_assoc();
        $patientQuery->close();

        if ($patient) {
            // Fetch children
            $childrenQuery = $conn->prepare("SELECT id, last_name, first_name, middle_name, type FROM children WHERE parent_id = ?");
            $childrenQuery->bind_param("i", $patient['user_id']);
            $childrenQuery->execute();
            $children = $childrenQuery->get_result()->fetch_all(MYSQLI_ASSOC);
            $childrenQuery->close();
        }
    }
}

/**
 * Secure file upload with validation
 */
function uploadFile($file, $uploadDir = 'uploads/student_photos/')
{
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new Exception('Invalid file parameters');
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new Exception('File too large');
        case UPLOAD_ERR_PARTIAL:
            throw new Exception('File upload incomplete');
        case UPLOAD_ERR_NO_FILE:
            return null; // Allow optional photo
        default:
            throw new Exception('Unknown upload error');
    }

    if (!file_exists($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        throw new Exception('Failed to create upload directory');
    }

    if (!is_writable($uploadDir)) {
        throw new Exception('Upload directory not writable');
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];
    $maxSize = 40 * 1024 * 1024; // 40MB

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!array_key_exists($mimeType, $allowedTypes)) {
        throw new Exception('Invalid file type. Allowed: JPG, PNG, GIF, WEBP');
    }

    if ($file['size'] > $maxSize) {
        throw new Exception('File exceeds 40MB limit');
    }

    if (!getimagesize($file['tmp_name'])) {
        throw new Exception('File is not a valid image');
    }

    $extension = $allowedTypes[$mimeType];
    $fileName = sprintf('%s.%s', bin2hex(random_bytes(16)), $extension);
    $targetPath = $uploadDir . $fileName;

    if (
        !is_uploaded_file($file['tmp_name']) ||
        !move_uploaded_file($file['tmp_name'], $targetPath)
    ) {
        throw new Exception('Failed to process uploaded file');
    }

    return $targetPath;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = 'Invalid form submission';
        header("Location: Elemform.php");
        exit();
    }



    // Validate required fields for each child
    $firstEmergencyData = null;
    $errors = [];

    foreach ($children as $index => $child) {
        // Student fields validation
        $required = [
            "surname$index" => "Surname",
            "firstname$index" => "First name",
            "birthday$index" => "Birthday",
            "sex$index" => "Gender",
            "gradeLevel$index" => "Grade level",
            "gradingQuarter$index" => "Grading quarter",
            "religion$index" => "Religion",
            "nationality$index" => "Nationality",
            "email$index" => "Email address",
            "contactNumber$index" => "Contact number",
            "cityAddress$index" => "City address"
        ];

        foreach ($required as $field => $name) {
            if (empty(trim($_POST[$field] ?? ''))) {
                $errors[] = "$name is required for student " . ($index + 1);
            }
        }

        if (!filter_var($_POST["email$index"] ?? '', FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format for student " . ($index + 1);
        }

        $phoneRegex = '/^09[0-9]{9}$/';
        if (!preg_match($phoneRegex, $_POST["contactNumber$index"] ?? '')) {
            $errors[] = "Contact number must be 11 digits starting with 09 for student " . ($index + 1);
        }

        $religion = sanitizeInput($_POST["religion$index"] ?? '');
        if ($religion === 'OTHER' && empty(trim($_POST["other_religion$index"] ?? ''))) {
            $errors[] = "Please specify the religion for student " . ($index + 1);
        }

        // Validate vaccination status
        if (
            empty($_POST["vaccination$index"] ?? '') ||
            !in_array($_POST["vaccination$index"], ['fully', 'partially', 'not'])
        ) {
            $errors[] = "COVID vaccination status is required for student " . ($index + 1);
        }

        // Emergency contact validation
        $isEmergencyCopied = $index > 0 && isset($_POST["sameEmergencyContact$index"]) && $_POST["sameEmergencyContact$index"] === 'on';
        if (!$isEmergencyCopied || $index === 0) {
            $emergencyRequired = [
                "emergencySurname$index" => "Emergency contact surname",
                "emergencyFirstname$index" => "Emergency contact first name",
                "emergencyContactNumber$index" => "Emergency contact number",
                "emergencyRelationship$index" => "Emergency contact relationship",
                "emergencyCityAddress$index" => "Emergency contact city address"
            ];

            foreach ($emergencyRequired as $field => $name) {
                if (empty(trim($_POST[$field] ?? ''))) {
                    $errors[] = "$name is required for student " . ($index + 1);
                }
            }

            if (!preg_match($phoneRegex, $_POST["emergencyContactNumber$index"] ?? '')) {
                $errors[] = "Emergency contact number must be 11 digits starting with 09 for student " . ($index + 1);
            }

            if ($_POST["emergencyRelationship$index"] === 'Other' && empty(trim($_POST["other_relationship$index"] ?? ''))) {
                $errors[] = "Please specify the other relationship for student " . ($index + 1);
            }

            // Store first student's emergency data
            if ($index === 0) {
                $firstEmergencyData = [
                    'surname' => sanitizeInput($_POST["emergencySurname$index"] ?? ''),
                    'firstname' => sanitizeInput($_POST["emergencyFirstname$index"] ?? ''),
                    'middlename' => sanitizeInput($_POST["emergencyMiddlename$index"] ?? ''),
                    'contact_number' => preg_replace('/[^0-9]/', '', $_POST["emergencyContactNumber$index"] ?? ''),
                    'relationship' => sanitizeInput($_POST["emergencyRelationship$index"] ?? ''),
                    'city_address' => sanitizeInput($_POST["emergencyCityAddress$index"] ?? ''),
                    'other_relationship' => sanitizeInput($_POST["other_relationship$index"] ?? '')
                ];
            }
        } else {
            if (!$firstEmergencyData) {
                $errors[] = "First student's emergency contact data is not available";
            }
        }
    }

    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        header("Location: elemform.php");
        exit();
    }

    try {
        $conn->begin_transaction();

        foreach ($children as $index => $child) {
            // Process file upload
            $photoPath = null;
            if (!empty($_FILES["studentPhoto$index"]['tmp_name'])) {
                $photoPath = uploadFile($_FILES["studentPhoto$index"]);
            }

            // Student data
            $data = [
                'studentId' => sanitizeInput($_POST["studentId$index"]),
                'surname' => sanitizeInput($_POST["surname$index"]),
                'firstname' => sanitizeInput($_POST["firstname$index"]),
                'middlename' => sanitizeInput($_POST["middlename$index"] ?? ''),
                'suffix' => sanitizeInput($_POST["suffix$index"] ?? ''),
                'birthday' => sanitizeInput($_POST["birthday$index"]),
                'age' => filter_var($_POST["age$index"], FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1, 'max_range' => 100]
                ]) ?: null,
                'sex' => in_array(strtolower($_POST["sex$index"]), ['male', 'female']) ?
                    strtolower($_POST["sex$index"]) : null,
                'blood_type' => sanitizeInput($_POST["bloodType$index"] ?? 'unknown'),
                'grade_level' => sanitizeInput($_POST["gradeLevel$index"]),
                'grading_quarter' => sanitizeInput($_POST["gradingQuarter$index"] ?? ''),
                'religion' => sanitizeInput($_POST["religion$index"] ?? ''),
                'nationality' => sanitizeInput($_POST["nationality$index"] ?? 'Filipino'),
                'email' => filter_var($_POST["email$index"], FILTER_SANITIZE_EMAIL),
                'contact_number' => preg_replace('/[^0-9]/', '', $_POST["contactNumber$index"] ?? ''),
                'city_address' => sanitizeInput($_POST["cityAddress$index"] ?? ''),
                'provincial_address' => sanitizeInput($_POST["provincialAddress$index"] ?? ''),
                'photo_path' => $photoPath
            ];

            // Handle religion
            if ($data['religion'] === 'OTHER' && !empty($_POST["other_religion$index"])) {
                $data['religion'] = 'OTHER: ' . sanitizeInput($_POST["other_religion$index"]);
            }

            // Insert into patients table
            $stmt = $conn->prepare("INSERT INTO patients (
                user_id, student_id, surname, firstname, middlename, suffix, birthday, age, 
                sex, blood_type, grade_level, grading_quarter, religion, nationality, 
                email, contact_number, city_address, provincial_address, photo_path
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt->bind_param(
                "issssssisssssssssss",
                $userId,
                $data['studentId'],
                $data['surname'],
                $data['firstname'],
                $data['middlename'],
                $data['suffix'],
                $data['birthday'],
                $data['age'],
                $data['sex'],
                $data['blood_type'],
                $data['grade_level'],
                $data['grading_quarter'],
                $data['religion'],
                $data['nationality'],
                $data['email'],
                $data['contact_number'],
                $data['city_address'],
                $data['provincial_address'],
                $data['photo_path']
            );

            if (!$stmt->execute()) {
                throw new Exception("Patient insert failed: " . $stmt->error);
            }
            $patientId = $conn->insert_id;

            // ✅ Update ONLY this child with the new patient_id
            $updateChild = $conn->prepare("UPDATE children SET patient_id = ? WHERE id = ?");
            $updateChild->bind_param("ii", $patientId, $child['id']);
            if (!$updateChild->execute()) {
                throw new Exception("Failed to update child ID {$child['id']} with patient_id $patientId: " . $updateChild->error);
            }
            $updateChild->close();

            // Emergency contact data
            $emergencyData = [
                'surname' => sanitizeInput($_POST["emergencySurname$index"] ?? ''),
                'firstname' => sanitizeInput($_POST["emergencyFirstname$index"] ?? ''),
                'middlename' => sanitizeInput($_POST["emergencyMiddlename$index"] ?? ''),
                'contact_number' => preg_replace('/[^0-9]/', '', $_POST["emergencyContactNumber$index"] ?? ''),
                'relationship' => sanitizeInput($_POST["emergencyRelationship$index"] ?? ''),
                'city_address' => sanitizeInput($_POST["emergencyCityAddress$index"] ?? '')
            ];

            // Use first student's emergency data if sameEmergencyContact is checked
            if ($index > 0 && isset($_POST["sameEmergencyContact$index"]) && $_POST["sameEmergencyContact$index"] === 'on') {
                $emergencyData = $firstEmergencyData;
            } else {
                // Handle "Other" relationship
                if ($emergencyData['relationship'] === 'Other' && !empty($_POST["other_relationship$index"])) {
                    $emergencyData['relationship'] = 'OTHER: ' . sanitizeInput($_POST["other_relationship$index"]);
                }
            }

            // Insert emergency contact
            $emergencyStmt = $conn->prepare("INSERT INTO emergency_contacts (
                patient_id, surname, firstname, middlename, contact_number, relationship, city_address
            ) VALUES (?, ?, ?, ?, ?, ?, ?)");

            $emergencyStmt->bind_param(
                "issssss",
                $patientId,
                $emergencyData['surname'],
                $emergencyData['firstname'],
                $emergencyData['middlename'],
                $emergencyData['contact_number'],
                $emergencyData['relationship'],
                $emergencyData['city_address']
            );

            if (!$emergencyStmt->execute()) {
                throw new Exception("Emergency contact insert failed: " . $emergencyStmt->error);
            }

            // Medical info
            $medicalData = [
                'illnesses' => isset($_POST["illness$index"]) ?
                    implode(",", array_map('sanitizeInput', (array)$_POST["illness$index"])) : null,

                'medications' => isset($_POST["medications$index"]) ?
                    implode(", ", array_map(function ($med) {
                        $drug = sanitizeInput($med['drug'] ?? '');
                        if ($drug === 'other') {
                            $drug = sanitizeInput($med['drug_other'] ?? 'Other');
                        }
                        $dose = sanitizeInput($med['dose'] ?? '');
                        $unit = sanitizeInput($med['unit'] ?? '');
                        $freq = sanitizeInput($med['frequency'] ?? '');

                        // Format: "Drug (DoseUnit, Frequency)"
                        return trim("$drug: $dose $unit $freq");
                    }, (array)$_POST["medications$index"]))
                    : null,

                'vaccination' => sanitizeInput($_POST["vaccination$index"] ?? ''),
                'menstruation_age' => filter_var($_POST["menstruationAge$index"] ?? 0, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 0, 'max_range' => 30]
                ]) ?: null,
                'menstrual_pattern' => sanitizeInput($_POST["menstrualPattern$index"] ?? ''),
                'pregnancies' => filter_var($_POST["pregnancies$index"] ?? 0, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 0, 'max_range' => 20]
                ]) ?: null,
                'live_children' => filter_var($_POST["liveChildren$index"] ?? 0, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 0, 'max_range' => 20]
                ]) ?: null,
                'menstrual_symptoms' => isset($_POST["menstrual_symptoms$index"]) ?
                    implode(",", array_map('sanitizeInput', (array)$_POST["menstrual_symptoms$index"])) : null,
                'past_illnesses' => isset($_POST["past_illness$index"]) ?
                    implode(",", array_map('sanitizeInput', (array)$_POST["past_illness$index"])) : null,

                'hospital_admissions' => isset($_POST["hospital_admissions$index"])
                    ? implode(", ", array_map(function ($item) {
                        $year   = sanitizeInput($item['year'] ?? '');
                        $reason = sanitizeInput($item['reason'] ?? '');
                        return trim("$year: $reason");
                    }, (array)$_POST["hospital_admissions$index"]))
                    : null,


                'family_history' => isset($_POST["family_history$index"]) ?
                    implode(",", array_map('sanitizeInput', (array)$_POST["family_history$index"])) : null,

                'other_conditions' => null // Not used in form, reserved for future use
            ];

            // Initialize an array to store all illness information
            $illnessesData = [];

            // 1. Get the base checkbox values
            if (isset($_POST["illness$index"])) {
                $illnessesData = (array)$_POST["illness$index"];
            }

            // 2. Process text inputs and replace checkbox values with detailed text
            $textInputs = [
                'allergies' => ["foodAllergy$index", "Allergies"],
                'cancer_specify' => ["cancer_details$index", "Cancer"],
                'other' => ["otherIllness$index", "Other"],
                'other_mental_illness' => ["other_mental_illness$index", "Other Mental Illness"]
            ];

            foreach ($textInputs as $checkboxValue => [$inputName, $label]) {
                $key = array_search($checkboxValue, $illnessesData);
                if ($key !== false && !empty($_POST[$inputName])) {
                    $illnessesData[$key] = $label . ": " . sanitizeInput($_POST[$inputName]);
                }
            }

            // 3. Process mental illness other text
            if (!empty($_POST["other_mental_illness$index"]) || !empty($_POST["illness$index"])) {
                $psychiatricIllnesses = array_intersect((array)$_POST["illness$index"], [
                    'depression',
                    'bipolar',
                    'anxiety',
                    'panic',
                    'ptsd',
                    'schizophrenia'
                ]);

                $psychiatricOutput = [];

                // If they checked any psychiatric sub-illness, prefix with "psychiatric"
                if (!empty($psychiatricIllnesses)) {
                    $psychiatricOutput[] = "psychiatric";
                    $psychiatricOutput = array_merge($psychiatricOutput, $psychiatricIllnesses);
                }

                // Add the other text if provided
                if (!empty($_POST["other_mental_illness$index"])) {
                    $psychiatricOutput[] = "Other Mental Illness: " . sanitizeInput($_POST["other_mental_illness$index"]);
                }

                // Store as one string
                if (!empty($psychiatricOutput)) {
                    $illnessesData[] = implode(",", $psychiatricOutput);
                }
            }


            // 4. Convert array to comma-separated string for storage
            $illnessesString = !empty($illnessesData) ? implode(", ", $illnessesData) : null;

            // Initialize array
            $pastIllnessesData = [];

            // 1. Base checkbox values
            if (isset($_POST["past_illness$index"])) {
                $pastIllnessesData = (array)$_POST["past_illness$index"];
            }

            // 2. Handle text input for "Other"
            if (!empty($_POST["past_illness_other$index"])) {
                $pastIllnessesData[] = "Other: " . sanitizeInput($_POST["past_illness_other$index"]);
            }

            // 3. Save flattened string
            $medicalData['past_illnesses'] = implode(", ", $pastIllnessesData);


            // Initialize array
            $familyHistoryData = [];

            // 1. Base checkbox values
            if (isset($_POST["family_history$index"])) {
                $familyHistoryData = (array)$_POST["family_history$index"];
            }

            // 2. Handle text inputs
            $textInputs = [
                'family_allergy_specify'   => "Family Allergies",
                'family_cancer_details'    => "Cancer",
                'family_other_mental_illness' => "Other Mental Illness (Family)",
                'other_family_history'     => "Other (Family)"
            ];

            foreach ($textInputs as $inputName => $label) {
                if (!empty($_POST[$inputName])) {
                    $familyHistoryData[] = $label . ": " . sanitizeInput($_POST[$inputName]);
                }
            }

            // 3. Save flattened string
            $medicalData['family_history'] = implode(", ", $familyHistoryData);


            if (in_array('other', (array)($_POST["menstrual_symptoms$index"] ?? [])) && !empty($_POST["otherSymptoms$index"])) {
                $medicalData['menstrual_symptoms'] .= ',' . sanitizeInput($_POST["otherSymptoms$index"]);
            }

            // Insert medical info
            $medicalStmt = $conn->prepare("INSERT INTO medical_info (
                patient_id, illnesses, medications, vaccination_status, menstruation_age, 
                menstrual_pattern, pregnancies, live_children, menstrual_symptoms, 
                past_illnesses, hospital_admissions, family_history, other_conditions
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $medicalStmt->bind_param(
                "isssisiisssss",
                $patientId,
                $illnessesString,
                $medicalData['medications'],
                $medicalData['vaccination'],
                $medicalData['menstruation_age'],
                $medicalData['menstrual_pattern'],
                $medicalData['pregnancies'],
                $medicalData['live_children'],
                $medicalData['menstrual_symptoms'],
                $medicalData['past_illnesses'],
                $medicalData['hospital_admissions'],
                $medicalData['family_history'],
                $medicalData['other_conditions']
            );

            if (!$medicalStmt->execute()) {
                throw new Exception("Medical info insert failed: " . $medicalStmt->error);
            }
        }

        // Update user status
        $updateStmt = $conn->prepare("UPDATE users SET profile_submitted = 1 WHERE id = ?");
        $updateStmt->bind_param("i", $userId);
        if (!$updateStmt->execute()) {
            throw new Exception("User update failed: " . $updateStmt->error);
        }

        // 1. Get all IDs for the target user types
        $adminQuery = $conn->prepare("
    SELECT id 
    FROM users 
    WHERE user_type IN ('Super Admin', 'Medical Admin', 'Dental Admin')
");
        $adminQuery->execute();
        $adminResult = $adminQuery->get_result();

        $userType = "Elementary Student";
        // 2. Build notification data
        $notificationTitle = "New Health Profile Submission!";

        $notificationDescription = "{$data['firstname']} {$data['surname']} ({$userType}) has submitted their health profile";
        $notificationLink = "patient-profile.php";
        $notificationType = "health_profile_submission";

        // 3. Prepare insert statement
        $notificationStmt = $conn->prepare("
    INSERT INTO notifications_admin (
        user_id, type, title, description, link, status, created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, 'unread', NOW(), NOW())
");

        // 4. Loop through target users and send notifications
        while ($adminRow = $adminResult->fetch_assoc()) {
            $targetUserId = $adminRow['id'];

            $notificationStmt->bind_param(
                "issss",
                $targetUserId,
                $notificationType,
                $notificationTitle,
                $notificationDescription,
                $notificationLink
            );

            if (!$notificationStmt->execute()) {
                error_log("Failed to create admin notification for user {$targetUserId}: " . $notificationStmt->error);
            }
        }

        // Close statements
        $notificationStmt->close();
        $adminQuery->close();


        $conn->commit();
       $_SESSION['STATUS'] = "SUBMISSION_PROFILE_SUCCESFUL";
        header("Location: homepage.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Database error: " . $e->getMessage());
        $_SESSION['error'] = "System error: " . $e->getMessage();
        echo $e->getMessage();
        header("Location: elemform.php");
        exit();
    }
}



?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elementary Student Health Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/profiles.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="apple-touch-icon" sizes="180x180" href="images/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="images/favicon-16x16.png">
    <link rel="manifest" href="images/site.webmanifest">
</head>
<style>
    body {
        font-family: 'Poppins'
    }

    .form-section {
        border: 1px solid #ccc;
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 5px;
    }

    .form-section legend {
        font-size: 1.2em;
        font-weight: bold;
        padding: 0 10px;
    }

    .medications-table {
        width: 100%;
        margin-top: 10px;
    }

    .medication-row {
        display: grid;
        grid-template-columns: 2fr 2fr 0.5fr;
        gap: 10px;
        margin-bottom: 15px;
        align-items: center;
    }

    .drug-select,
    .other-input,
    .dose-options input,
    .dose-options select {
        padding: 5px;
        width: 100%;
        box-sizing: border-box;
        font-size: 0.9em;
    }

    .other-input {
        display: none;
    }

    .dose-options {
        display: flex;
        gap: 5px;
    }

    .dose-options input {
        width: 80px;
    }

    .remove-btn {
        padding: 5px 10px;
        background-color: #dc3545;
        color: white;
        border: none;
        border-radius: 3px;
        cursor: pointer;
    }

    .remove-btn:hover {
        background-color: #c82333;
    }

    .add-btn {
        padding: 8px 15px;
        background-color: #6c757d;
        color: white;
        border: none;
        border-radius: 3px;
        cursor: pointer;
    }

    .add-btn:hover {
        background-color: #5a6268;
    }

    @media (max-width: 768px) {
        .medication-row {
            grid-template-columns: 1fr;
            gap: 5px;
        }

        .dose-options {
            flex-direction: column;
            gap: 10px;
        }

        .dose-options input {
            width: 100%;
        }

        .remove-btn,
        .add-btn {
            width: 100%;
            margin-top: 10px;
        }

        .form-section {
            padding: 10px;
        }
    }
</style>

<body>
    <!-- Error Modal -->
    <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #990000; color: white;">
                    <h5 class="modal-title" id="errorModalLabel">Error</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #990000; color: white;">
                    <h5 class="modal-title" id="successModalLabel">Success</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php echo isset($_SESSION['success']) ? htmlspecialchars($_SESSION['success']) : ''; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Missing Fields Modal -->
    <div class="modal fade" id="missingFieldsModal" tabindex="-1" aria-labelledby="missingFieldsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #990000; color: white;">
                    <h5 class="modal-title" id="missingFieldsModalLabel">Missing Required Fields</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Please fill out the following required fields:</p>
                    <ul id="missingFieldsList"></ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="mainContainer">
        <?php if (empty($children)): ?>
            <div class="alert alert-warning" role="alert">
                No students found. Please register students in the system.
            </div>
        <?php else: ?>
            <form class="health-profile-form" id="healthProfileForm" action="elemform.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="form-header">
                    <h1>PATIENT HEALTH PROFILE</h1>
                </div>

                <!-- Student Tabs -->
                <div class="tab-container mb-3" id="studentTabs">
                    <?php foreach ($children as $index => $child): ?>
                        <div class="student-tab <?= $index === 0 ? 'active' : '' ?>"
                            data-target="studentForm<?= $index ?>">
                            <?= htmlspecialchars($child['last_name'] . ', ' . $child['first_name'] . ($child['middle_name'] ? ' ' . $child['middle_name'] : '')) ?> (<?= htmlspecialchars($child['type']) ?>)
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Student Forms -->
                <?php foreach ($children as $index => $child): ?>
                    <div class="student-form <?= $index === 0 ? 'active' : '' ?>" id="studentForm<?= $index ?>">
                        <!-- Step 1: Personal Information -->
                        <div class="form-step" id="step1-<?= $index ?>">
                            <fieldset class="form-section">
                                <legend>Personal Information</legend>
                                <div class="photo-upload-section">
                                    <label for="studentPhoto<?= $index ?>">Photo of Student</label>
                                    <input type="file" id="studentPhoto<?= $index ?>" name="studentPhoto<?= $index ?>"
                                        accept="image/*" hidden onchange="displayImage(this, 'previewImage<?= $index ?>')">
                                    <div class="upload-box" onclick="document.getElementById('studentPhoto<?= $index ?>').click()">
                                        <img id="previewImage<?= $index ?>" src="#" alt="Upload Icon" style="display: none;">
                                        <span id="uploadText<?= $index ?>">+</span>
                                    </div>
                                </div>

                                <div class="personal-info-grid">
                                    <div class="form-group full-width">
                                        <label>Name:</label>
                                        <div class="name-inputs">
                                            <div class="input-wrapper">
                                                <input type="text" placeholder="Surname" name="surname<?= $index ?>"
                                                    class="form-control capitalize"
                                                    value="<?= htmlspecialchars($_POST["surname$index"] ?? $child['last_name']) ?>" required>
                                                <div class="invalid-feedback">Please enter the surname</div>
                                            </div>
                                            <div class="input-wrapper">
                                                <input type="text" placeholder="First name" name="firstname<?= $index ?>"
                                                    class="form-control capitalize"
                                                    value="<?= htmlspecialchars($_POST["firstname$index"] ?? $child['first_name']) ?>" required>
                                                <div class="invalid-feedback">Please enter the first name</div>
                                            </div>
                                            <div class="input-wrapper">
                                                <input type="text" placeholder="Middle name" name="middlename<?= $index ?>"
                                                    class="form-control capitalize"
                                                    value="<?= htmlspecialchars($_POST["middlename$index"] ?? $child['middle_name'] ?? '') ?>">
                                            </div>
                                            <div class="input-wrapper">
                                                <input type="text" placeholder="Suffix" name="suffix<?= $index ?>"
                                                    value="<?= htmlspecialchars($_POST["suffix$index"] ?? '') ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="birthday<?= $index ?>">Birthday:</label>
                                        <input type="date" id="birthday<?= $index ?>" name="birthday<?= $index ?>"
                                            value="<?= htmlspecialchars($_POST["birthday$index"] ?? '') ?>" required
                                            onchange="calculateAge(<?= $index ?>)">
                                        <div class="invalid-feedback" id="birthdayError<?= $index ?>">Please select a valid birthday</div>
                                    </div>

                                    <div class="form-group">
                                        <label for="age<?= $index ?>">Age:</label>
                                        <input type="number" id="age<?= $index ?>" name="age<?= $index ?>"
                                            value="<?= htmlspecialchars($_POST["age$index"] ?? '') ?>" class="age-input" readonly>
                                    </div>

                                    <div class="form-group">
                                        <label for="sex<?= $index ?>">Sex:</label>
                                        <select id="sex<?= $index ?>" name="sex<?= $index ?>" required
                                            onchange="toggleMenstrualSection(<?= $index ?>)">
                                            <option value="">Select</option>
                                            <option value="male" <?= ($_POST["sex$index"] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                                            <option value="female" <?= ($_POST["sex$index"] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                                        </select>
                                        <div class="invalid-feedback">Please select a gender</div>
                                    </div>

                                    <div class="form-group">
                                        <label for="bloodType<?= $index ?>">Blood Type:</label>
                                        <select id="bloodType<?= $index ?>" name="bloodType<?= $index ?>">
                                            <option value="unknown" <?= ($_POST["bloodType$index"] ?? '') === 'unknown' ? 'selected' : '' ?>>Unknown</option>
                                            <option value="A+" <?= ($_POST["bloodType$index"] ?? '') === 'A+' ? 'selected' : '' ?>>A+</option>
                                            <option value="A-" <?= ($_POST["bloodType$index"] ?? '') === 'A-' ? 'selected' : '' ?>>A-</option>
                                            <option value="B+" <?= ($_POST["bloodType$index"] ?? '') === 'B+' ? 'selected' : '' ?>>B+</option>
                                            <option value="B-" <?= ($_POST["bloodType$index"] ?? '') === 'B-' ? 'selected' : '' ?>>B-</option>
                                            <option value="AB+" <?= ($_POST["bloodType$index"] ?? '') === 'AB+' ? 'selected' : '' ?>>AB+</option>
                                            <option value="AB-" <?= ($_POST["bloodType$index"] ?? '') === 'AB-' ? 'selected' : '' ?>>AB-</option>
                                            <option value="O+" <?= ($_POST["bloodType$index"] ?? '') === 'O+' ? 'selected' : '' ?>>O+</option>
                                            <option value="O-" <?= ($_POST["bloodType$index"] ?? '') === 'O-' ? 'selected' : '' ?>>O-</option>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="gradeLevel<?= $index ?>">Grade:</label>
                                        <select id="gradeLevel<?= $index ?>" name="gradeLevel<?= $index ?>" required>
                                            <option value="">Select</option>
                                            <?php if ($child['type'] === 'Kindergarten'): ?>
                                                <option value="kinder1" <?= ($_POST["gradeLevel$index"] ?? '') === 'kinder1' ? 'selected' : '' ?>>Kindergarten 1</option>
                                                <option value="kinder2" <?= ($_POST["gradeLevel$index"] ?? '') === 'kinder2' ? 'selected' : '' ?>>Kindergarten 2</option>
                                            <?php elseif ($child['type'] === 'Elementary'): ?>
                                                <option value="1" <?= ($_POST["gradeLevel$index"] ?? '') === '1' ? 'selected' : '' ?>>Grade 1</option>
                                                <option value="2" <?= ($_POST["gradeLevel$index"] ?? '') === '2' ? 'selected' : '' ?>>Grade 2</option>
                                                <option value="3" <?= ($_POST["gradeLevel$index"] ?? '') === '3' ? 'selected' : '' ?>>Grade 3</option>
                                                <option value="4" <?= ($_POST["gradeLevel$index"] ?? '') === '4' ? 'selected' : '' ?>>Grade 4</option>
                                                <option value="5" <?= ($_POST["gradeLevel$index"] ?? '') === '5' ? 'selected' : '' ?>>Grade 5</option>
                                                <option value="6" <?= ($_POST["gradeLevel$index"] ?? '') === '6' ? 'selected' : '' ?>>Grade 6</option>
                                            <?php endif; ?>
                                        </select>
                                        <div class="invalid-feedback">Please select a grade level</div>
                                    </div>

                                    <input type="hidden" name="studentType<?= $index ?>"
                                        value="<?= htmlspecialchars($_POST["studentType$index"] ?? $child['type']) ?>">

                                    <div class="form-group">
                                        <label for="gradingQuarter<?= $index ?>">Grading/Quarter:</label>
                                        <select id="gradingQuarter<?= $index ?>" name="gradingQuarter<?= $index ?>" required>
                                            <option value="">Select</option>
                                            <option value="1" <?= ($_POST["gradingQuarter$index"] ?? '') === '1' ? 'selected' : '' ?>>1</option>
                                            <option value="2" <?= ($_POST["gradingQuarter$index"] ?? '') === '2' ? 'selected' : '' ?>>2</option>
                                            <option value="3" <?= ($_POST["gradingQuarter$index"] ?? '') === '3' ? 'selected' : '' ?>>3</option>
                                            <option value="4" <?= ($_POST["gradingQuarter$index"] ?? '') === '4' ? 'selected' : '' ?>>4</option>
                                        </select>
                                        <div class="invalid-feedback">Please select a grading quarter</div>
                                    </div>

                                    <div class="form-group">
                                        <label for="religion<?= $index ?>">Religion:</label>
                                        <select id="religion<?= $index ?>" name="religion<?= $index ?>" required
                                            onchange="toggleOtherReligionInput(<?= $index ?>)">
                                            <option value="">Select Religion</option>
                                            <option value="Roman Catholic" <?= ($_POST["religion$index"] ?? '') === 'Roman Catholic' ? 'selected' : '' ?>>Roman Catholic</option>
                                            <option value="Islam" <?= ($_POST["religion$index"] ?? '') === 'Islam' ? 'selected' : '' ?>>Islam</option>
                                            <option value="Iglesia ni Cristo" <?= ($_POST["religion$index"] ?? '') === 'Iglesia ni Cristo' ? 'selected' : '' ?>>Iglesia ni Cristo</option>
                                            <option value="Protestant" <?= ($_POST["religion$index"] ?? '') === 'Protestant' ? 'selected' : '' ?>>Protestant</option>
                                            <option value="Born Again Christian" <?= ($_POST["religion$index"] ?? '') === 'Born Again Christian' ? 'selected' : '' ?>>Born Again Christian</option>
                                            <option value="Seventh-day Adventist" <?= ($_POST["religion$index"] ?? '') === 'Seventh-day Adventist' ? 'selected' : '' ?>>Seventh-day Adventist</option>
                                            <option value="Jehovah's Witness" <?= ($_POST["religion$index"] ?? '') === "Jehovah's Witness" ? 'selected' : '' ?>>Jehovah's Witness</option>
                                            <option value="Buddhist" <?= ($_POST["religion$index"] ?? '') === 'Buddhist' ? 'selected' : '' ?>>Buddhist</option>
                                            <option value="OTHER" <?= ($_POST["religion$index"] ?? '') === 'OTHER' ? 'selected' : '' ?>>Others (Please specify)</option>
                                        </select>
                                        <div id="otherReligionWrapper<?= $index ?>" style="display: <?= ($_POST["religion$index"] ?? '') === 'OTHER' ? 'block' : 'none' ?>; margin-top: 10px;">
                                            <input type="text" id="otherReligion<?= $index ?>"
                                                name="other_religion<?= $index ?>"
                                                placeholder="Please specify religion"
                                                class="form-control"
                                                value="<?= htmlspecialchars($_POST["other_religion$index"] ?? '') ?>">
                                        </div>
                                        <div class="invalid-feedback">Please select or specify your religion</div>
                                    </div>

                                    <div class="form-group">
                                        <label for="nationality<?= $index ?>">Nationality:</label>
                                        <input type="text" id="nationality<?= $index ?>" name="nationality<?= $index ?>"
                                            value="<?= htmlspecialchars($_POST["nationality$index"] ?? 'Filipino') ?>" required>
                                        <div class="invalid-feedback">Please enter the nationality</div>
                                    </div>

                                    <div class="form-group">
                                        <label for="email<?= $index ?>">Email Address:</label>
                                        <input type="email" id="email<?= $index ?>" name="email<?= $index ?>"
                                            value="<?= htmlspecialchars($_POST["email$index"] ?? $userData['email'] ?? '') ?>" required>
                                        <div class="invalid-feedback">Please enter a valid email address</div>
                                    </div>

                                    <div class="form-group">
                                        <label for="contactNumber<?= $index ?>">Contact Number:</label>
                                        <input type="tel" id="contactNumber<?= $index ?>"
                                            name="contactNumber<?= $index ?>"
                                            pattern="09[0-9]{9}"
                                            maxlength="11"
                                            oninput="validatePhoneNumber(this)"
                                            value="<?= htmlspecialchars($_POST["contactNumber$index"] ?? '') ?>" required>
                                        <small class="form-text text-muted">Format: 09XXXXXXXXX (11 digits)</small>
                                        <div class="invalid-feedback">Please enter a valid 11-digit contact number starting with 09</div>
                                    </div>

                                    <div class="form-group">
                                        <div class="input-wrapper">
                                            <label for="studentId">Student ID:</label>
                                            <input type="text" id="studentId<?= $index ?>" name="studentId<?= $index ?>" required value="N/A">
                                            <div class="invalid-feedback">Please enter your Student ID</div>
                                        </div>
                                    </div>

                                    <div class="form-group full-width">
                                        <label for="cityAddress<?= $index ?>">City Address:</label>
                                        <input type="text" id="cityAddress<?= $index ?>" name="cityAddress<?= $index ?>"
                                            value="<?= htmlspecialchars($_POST["cityAddress$index"] ?? '') ?>" required>
                                        <div class="invalid-feedback">Please enter the city address</div>
                                    </div>

                                    <div class="form-group full-width">
                                        <label for="provincialAddress<?= $index ?>">Provincial Address (if applicable):</label>
                                        <input type="text" id="provincialAddress<?= $index ?>" name="provincialAddress<?= $index ?>"
                                            value="<?= htmlspecialchars($_POST["provincialAddress$index"] ?? '') ?>">
                                    </div>
                                </div>
                            </fieldset>

                            <!-- Emergency Contact Person -->
                            <fieldset class="form-section">
                                <legend>Emergency Contact Person (within Zamboanga City)</legend>
                                <div class="emergency-contact-grid">
                                    <?php if ($index > 0): ?>
                                        <div class="form-group full-width">
                                            <label class="checkbox-label">
                                                <input type="checkbox" id="sameEmergencyContact<?= $index ?>"
                                                    name="sameEmergencyContact<?= $index ?>"
                                                    checked
                                                    onchange="toggleEmergencyContactCopy(<?= $index ?>)">
                                                Same as first student
                                            </label>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Name Fields -->
                                    <div class="form-group full-width">
                                        <label>Name:</label>
                                        <div class="name-inputs">
                                            <div class="input-wrapper">
                                                <input type="text" id="emergencySurname<?= $index ?>"
                                                    name="emergencySurname<?= $index ?>"
                                                    class="form-control capitalize"
                                                    placeholder="Surname"
                                                    value="<?= htmlspecialchars($_POST["emergencySurname$index"] ?? $userData['last_name'] ?? '') ?>" required>
                                                <div class="invalid-feedback">Please enter the emergency contact surname</div>
                                            </div>
                                            <div class="input-wrapper">
                                                <input type="text" id="emergencyFirstname<?= $index ?>"
                                                    name="emergencyFirstname<?= $index ?>"
                                                    class="form-control capitalize"
                                                    placeholder="First name"
                                                    value="<?= htmlspecialchars($_POST["emergencyFirstname$index"] ?? $userData['first_name'] ?? '') ?>" required>
                                                <div class="invalid-feedback">Please enter the emergency contact first name</div>
                                            </div>
                                            <div class="input-wrapper">
                                                <input type="text" id="emergencyMiddlename<?= $index ?>"
                                                    name="emergencyMiddlename<?= $index ?>"
                                                    class="form-control capitalize"
                                                    placeholder="Middle name"
                                                    value="<?= htmlspecialchars($_POST["emergencyMiddlename$index"] ?? $userData['middle_name'] ?? '') ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Contact Number -->
                                    <div class="form-group">
                                        <label for="emergencyContactNumber<?= $index ?>">Contact Number:</label>
                                        <input type="tel" id="emergencyContactNumber<?= $index ?>"
                                            name="emergencyContactNumber<?= $index ?>"
                                            pattern="09[0-9]{9}"
                                            maxlength="11"
                                            oninput="validatePhoneNumber(this)"
                                            value="<?= htmlspecialchars($_POST["emergencyContactNumber$index"] ?? '') ?>" required>
                                        <small class="form-text text-muted">Format: 09XXXXXXXXX (11 digits)</small>
                                        <div class="invalid-feedback">Please enter a valid 11-digit emergency contact number starting with 09</div>
                                    </div>

                                    <!-- Relationship -->
                                    <div class="form-group">
                                        <label for="emergencyRelationship<?= $index ?>">Relationship:</label>
                                        <select id="emergencyRelationship<?= $index ?>"
                                            name="emergencyRelationship<?= $index ?>"
                                            required
                                            onchange="toggleOtherRelationshipInput(<?= $index ?>)">
                                            <option value="">Select Relationship</option>
                                            <option value="Parent" <?= ($_POST["emergencyRelationship$index"] ?? 'Parent') === 'Parent' ? 'selected' : '' ?>>Parent</option>
                                            <option value="Sibling" <?= ($_POST["emergencyRelationship$index"] ?? '') === 'Sibling' ? 'selected' : '' ?>>Sibling</option>
                                            <option value="Spouse" <?= ($_POST["emergencyRelationship$index"] ?? '') === 'Spouse' ? 'selected' : '' ?>>Spouse</option>
                                            <option value="Child" <?= ($_POST["emergencyRelationship$index"] ?? '') === 'Child' ? 'selected' : '' ?>>Child</option>
                                            <option value="Guardian" <?= ($_POST["emergencyRelationship$index"] ?? '') === 'Guardian' ? 'selected' : '' ?>>Guardian</option>
                                            <option value="Friend" <?= ($_POST["emergencyRelationship$index"] ?? '') === 'Friend' ? 'selected' : '' ?>>Friend</option>
                                            <option value="Other" <?= ($_POST["emergencyRelationship$index"] ?? '') === 'Other' ? 'selected' : '' ?>>Others (Please specify)</option>
                                        </select>
                                        <div id="otherRelationshipWrapper<?= $index ?>" style="display: <?= ($_POST["emergencyRelationship$index"] ?? '') === 'Other' ? 'block' : 'none' ?>; margin-top: 10px;">
                                            <input type="text" id="otherRelationship<?= $index ?>"
                                                name="other_relationship<?= $index ?>"
                                                placeholder="Please specify relationship"
                                                class="form-control"
                                                value="<?= htmlspecialchars($_POST["other_relationship$index"] ?? '') ?>">
                                        </div>
                                        <div class="invalid-feedback">Please select or specify a relationship</div>
                                    </div>

                                    <!-- Address -->
                                    <div class="form-group full-width">
                                        <label for="emergencyCityAddress<?= $index ?>">City Address:</label>
                                        <input type="text" id="emergencyCityAddress<?= $index ?>"
                                            name="emergencyCityAddress<?= $index ?>"
                                            value="<?= htmlspecialchars($_POST["emergencyCityAddress$index"] ?? '') ?>" required>
                                        <div class="invalid-feedback">Please enter the emergency contact city address</div>
                                    </div>
                                </div>
                            </fieldset>

                            <div class="form-navigation">
                                <button type="button" class="btn btn-secondary"
                                    onclick="prevStudent(<?= $index ?>)"
                                    <?= $index === 0 ? 'disabled' : '' ?>>Previous Student</button>
                                <button type="button" class="btn btn-primary"
                                    onclick="nextStep(<?= $index ?>)">Next</button>
                            </div>
                        </div>

                        <!-- Step 2: Current Medical Information -->
                        <div class="form-step" id="step2-<?= $index ?>" style="display: none;">
                            <fieldset class="form-section">
                                <legend>Comorbid Illnesses</legend>
                                <p class="form-question">Which of these conditions does the student currently have?</p>
                                <div class="checkbox-grid">
                                    <div class="checkbox-column">
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="illness<?= $index ?>[]" value="asthma"
                                                <?= in_array('asthma', (array)($_POST["illness$index"] ?? [])) ? 'checked' : '' ?>>
                                            Bronchial Asthma ("Hika")
                                        </label>

                                        <label class="checkbox-label">
                                            <input type="checkbox" name="illness<?= $index ?>[]" value="allergies"
                                                id="allergiesCheckbox<?= $index ?>"
                                                <?= in_array('allergies', (array)($_POST["illness$index"] ?? [])) ? 'checked' : '' ?>
                                                onchange="toggleIllnessInput('allergies', <?= $index ?>)">
                                            Food Allergies
                                        </label>
                                        <input type="text" placeholder="Specify food"
                                            name="foodAllergy<?= $index ?>"
                                            id="allergiesInput<?= $index ?>"
                                            class="inline-input"
                                            value="<?= htmlspecialchars($_POST["foodAllergy$index"] ?? '') ?>"
                                            style="display: <?= in_array('allergies', (array)($_POST["illness$index"] ?? [])) ? 'inline-block' : 'none' ?>;">

                                        <label class="checkbox-label">
                                            <input type="checkbox" name="illness<?= $index ?>[]" value="rhinitis"
                                                <?= in_array('rhinitis', (array)($_POST["illness$index"] ?? [])) ? 'checked' : '' ?>>
                                            Allergic Rhinitis
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="illness<?= $index ?>[]" value="hyperthyroidism"
                                                <?= in_array('hyperthyroidism', (array)($_POST["illness$index"] ?? [])) ? 'checked' : '' ?>>
                                            Hyperthyroidism
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="illness<?= $index ?>[]" value="hypothyroidism"
                                                <?= in_array('hypothyroidism', (array)($_POST["illness$index"] ?? [])) ? 'checked' : '' ?>>
                                            Hypothyroidism/Goiter
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="illness<?= $index ?>[]" value="anemia"
                                                <?= in_array('anemia', (array)($_POST["illness$index"] ?? [])) ? 'checked' : '' ?>>
                                            Anemia
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="illness<?= $index ?>[]" value="migraine"
                                                <?= in_array('migraine', (array)($_POST["illness$index"] ?? [])) ? 'checked' : '' ?>>
                                            Migraine (recurrent headaches)
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="illness<?= $index ?>[]" value="epilepsy"
                                                <?= in_array('epilepsy', (array)($_POST["illness$index"] ?? [])) ? 'checked' : '' ?>>
                                            Epilepsy/Seizures
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="illness<?= $index ?>[]" value="gerd"
                                                <?= in_array('gerd', (array)($_POST["illness$index"] ?? [])) ? 'checked' : '' ?>>
                                            Gastroesophageal Reflux Disease (GERD)
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="illness<?= $index ?>[]" value="bowel_syndrome"
                                                <?= in_array('bowel_syndrome', (array)($_POST["illness$index"] ?? [])) ? 'checked' : '' ?>>
                                            Irritable Bowel Syndrome
                                        </label>
                                    </div>

                                    <div class="checkbox-column">
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="illness<?= $index ?>[]" value="psychiatric"
                                                <?= in_array('psychiatric', (array)($_POST["illness$index"] ?? [])) ? 'checked' : '' ?>>
                                            Psychiatric Illness:
                                        </label>
                                        <div class="nested-checkboxes">
                                            <label class="checkbox-label nested">
                                                <input type="checkbox" name="illness<?= $index ?>[]" value="depression"
                                                    <?= in_array('depression', (array)($_POST["illness$index"] ?? [])) ? 'checked' : '' ?>>
                                                Major Depressive Disorder
                                            </label>
                                            <label class="checkbox-label nested">
                                                <input type="checkbox" name="illness<?= $index ?>[]" value="bipolar"
                                                    <?= in_array('bipolar', (array)($_POST["illness$index"] ?? [])) ? 'checked' : '' ?>>
                                                Bipolar Disorder
                                            </label>
                                            <label class="checkbox-label nested">
                                                <input type="checkbox" name="illness<?= $index ?>[]" value="anxiety"
                                                    <?= in_array('anxiety', (array)($_POST["illness$index"] ?? [])) ? 'checked' : '' ?>>
                                                Generalized Anxiety Disorder
                                            </label>
                                            <label class="checkbox-label nested">
                                                <input type="checkbox" name="illness<?= $index ?>[]" value="panic"
                                                    <?= in_array('panic', (array)($_POST["illness$index"] ?? [])) ? 'checked' : '' ?>>
                                                Panic Disorder
                                            </label>
                                            <label class="checkbox-label nested">
                                                <input type="checkbox" name="illness<?= $index ?>[]" value="ptsd"
                                                    <?= in_array('ptsd', (array)($_POST["illness$index"] ?? [])) ? 'checked' : '' ?>>
                                                Posttraumatic Stress Disorder
                                            </label>
                                            <label class="checkbox-label nested">
                                                <input type="checkbox" name="illness<?= $index ?>[]" value="schizophrenia"
                                                    <?= in_array('schizophrenia', (array)($_POST["illness$index"] ?? [])) ? 'checked' : '' ?>>
                                                Schizophrenia
                                            </label>
                                            Other:
                                            <input type="text" placeholder="Specify other mental illness"
                                                name="other_mental_illness<?= $index ?>"
                                                value="<?= htmlspecialchars($_POST["other_mental_illness$index"] ?? '') ?>"
                                                class="inline-input">
                                        </div>

                                        <label class="checkbox-label">
                                            <input type="checkbox" name="illness<?= $index ?>[]" value="lupus"
                                                <?= in_array('lupus', (array)($_POST["illness$index"] ?? [])) ? 'checked' : '' ?>>
                                            Systemic Lupus Erythematosus (SLE)
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="illness<?= $index ?>[]" value="hypertension"
                                                <?= in_array('hypertension', (array)($_POST["illness$index"] ?? [])) ? 'checked' : '' ?>>
                                            Hypertension (elevated blood pressure)
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="illness<?= $index ?>[]" value="diabetes"
                                                <?= in_array('diabetes', (array)($_POST["illness$index"] ?? [])) ? 'checked' : '' ?>>
                                            Diabetes mellitus (elevated blood sugar)
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="illness<?= $index ?>[]" value="dyslipidemia"
                                                <?= in_array('dyslipidemia', (array)($_POST["illness$index"] ?? [])) ? 'checked' : '' ?>>
                                            Dyslipidemia (elevated cholesterol levels)
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="illness<?= $index ?>[]" value="arthritis"
                                                <?= in_array('arthritis', (array)($_POST["illness$index"] ?? [])) ? 'checked' : '' ?>>
                                            Arthritis (joint pains)
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="illness<?= $index ?>[]" value="pcos"
                                                <?= in_array('pcos', (array)($_POST["illness$index"] ?? [])) ? 'checked' : '' ?>>
                                            Polycystic Ovarian Syndrome (PCOS)
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="illness<?= $index ?>[]" value="cancer_specify"
                                                id="cancerCheckbox<?= $index ?>"
                                                <?= in_array('cancer_specify', (array)($_POST["illness$index"] ?? [])) ? 'checked' : '' ?>
                                                onchange="toggleIllnessInput('cancer', <?= $index ?>)">
                                            Cancer
                                        </label>
                                        <input type="text" placeholder="Please specify..."
                                            name="cancer_details<?= $index ?>"
                                            id="cancerInput<?= $index ?>"
                                            class="inline-input"
                                            value="<?= htmlspecialchars($_POST["cancer_details$index"] ?? '') ?>"
                                            style="display: <?= in_array('cancer_specify', (array)($_POST["illness$index"] ?? [])) ? 'inline-block' : 'none' ?>;">

                                        <label class="checkbox-label">
                                            <input type="checkbox" name="illness<?= $index ?>[]" value="other"
                                                id="otherCheckbox<?= $index ?>"
                                                <?= in_array('other', (array)($_POST["illness$index"] ?? [])) ? 'checked' : '' ?>
                                                onchange="toggleIllnessInput('other', <?= $index ?>)">
                                            Other:
                                        </label>
                                        <input type="text" placeholder="Specify"
                                            name="otherIllness<?= $index ?>"
                                            id="otherInput<?= $index ?>"
                                            class="inline-input"
                                            value="<?= htmlspecialchars($_POST["otherIllness$index"] ?? '') ?>"
                                            style="display: <?= in_array('other', (array)($_POST["illness$index"] ?? [])) ? 'inline-block' : 'none' ?>;">
                                    </div>
                                </div>
                            </fieldset>


                            <fieldset class="form-section">
                                <legend>Maintenance Medications</legend>
                                <table id="medicationsTable<?= $index ?>" class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Drug</th>
                                            <th>Dose/Frequency</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $medications = $_POST["medications$index"] ?? [[]];
                                        foreach ($medications as $medIndex => $med):
                                        ?>
                                            <tr>
                                                <td>
                                                    <select class="table-input drug-select" name="medications<?= $index ?>[<?= $medIndex ?>][drug]"
                                                        onchange="handleDrugSelect(this)">
                                                        <option value="">Select a drug</option>
                                                        <option value="paracetamol" <?= ($med['drug'] ?? '') === 'paracetamol' ? 'selected' : '' ?>>Paracetamol</option>
                                                        <option value="ibuprofen" <?= ($med['drug'] ?? '') === 'ibuprofen' ? 'selected' : '' ?>>Ibuprofen</option>
                                                        <option value="amoxicillin" <?= ($med['drug'] ?? '') === 'amoxicillin' ? 'selected' : '' ?>>Amoxicillin</option>
                                                        <option value="fluticasone" <?= ($med['drug'] ?? '') === 'fluticasone' ? 'selected' : '' ?>>Fluticasone</option>
                                                        <option value="budesonide" <?= ($med['drug'] ?? '') === 'budesonide' ? 'selected' : '' ?>>Budesonide</option>
                                                        <option value="montelukast" <?= ($med['drug'] ?? '') === 'montelukast' ? 'selected' : '' ?>>Montelukast</option>
                                                        <option value="cetirizine" <?= ($med['drug'] ?? '') === 'cetirizine' ? 'selected' : '' ?>>Cetirizine</option>
                                                        <option value="methylphenidate" <?= ($med['drug'] ?? '') === 'methylphenidate' ? 'selected' : '' ?>>Methylphenidate</option>
                                                        <option value="lisdexamfetamine" <?= ($med['drug'] ?? '') === 'lisdexamfetamine' ? 'selected' : '' ?>>Lisdexamfetamine</option>
                                                        <option value="guanfacine" <?= ($med['drug'] ?? '') === 'guanfacine' ? 'selected' : '' ?>>Guanfacine</option>
                                                        <option value="insulin" <?= ($med['drug'] ?? '') === 'insulin' ? 'selected' : '' ?>>Insulin</option>
                                                        <option value="levetiracetam" <?= ($med['drug'] ?? '') === 'levetiracetam' ? 'selected' : '' ?>>Levetiracetam</option>
                                                        <option value="valproic_acid" <?= ($med['drug'] ?? '') === 'valproic_acid' ? 'selected' : '' ?>>Valproic Acid</option>
                                                        <option value="other" <?= ($med['drug'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                                                    </select>
                                                    <input type="text" class="table-input other-input"
                                                        name="medications<?= $index ?>[<?= $medIndex ?>][drug_other]"
                                                        placeholder="Enter drug name"
                                                        style="display: <?= ($med['drug'] ?? '') === 'other' ? 'inline-block' : 'none' ?>;"
                                                        value="<?= htmlspecialchars($med['drug_other'] ?? '') ?>">
                                                </td>
                                                <td>
                                                    <div class="dose-options">
                                                        <input type="number" class="table-input"
                                                            name="medications<?= $index ?>[<?= $medIndex ?>][dose]"
                                                            placeholder="Dose" style="width: 80px;"
                                                            value="<?= htmlspecialchars($med['dose'] ?? '') ?>">
                                                        <select class="table-input" name="medications<?= $index ?>[<?= $medIndex ?>][unit]">
                                                            <option value="mg" <?= ($med['unit'] ?? '') === 'mg' ? 'selected' : '' ?>>mg</option>
                                                            <option value="g" <?= ($med['unit'] ?? '') === 'g' ? 'selected' : '' ?>>g</option>
                                                            <option value="ml" <?= ($med['unit'] ?? '') === 'ml' ? 'selected' : '' ?>>ml</option>
                                                            <option value="units" <?= ($med['unit'] ?? '') === 'units' ? 'selected' : '' ?>>units</option>
                                                        </select>
                                                        <select class="table-input" name="medications<?= $index ?>[<?= $medIndex ?>][frequency]">
                                                            <option value="">Frequency</option>
                                                            <option value="once daily" <?= ($med['frequency'] ?? '') === 'once daily' ? 'selected' : '' ?>>Once daily</option>
                                                            <option value="twice daily" <?= ($med['frequency'] ?? '') === 'twice daily' ? 'selected' : '' ?>>Twice daily</option>
                                                            <option value="three times daily" <?= ($med['frequency'] ?? '') === 'three times daily' ? 'selected' : '' ?>>Three times daily</option>
                                                            <option value="four times daily" <?= ($med['frequency'] ?? '') === 'four times daily' ? 'selected' : '' ?>>Four times daily</option>
                                                            <option value="as needed" <?= ($med['frequency'] ?? '') === 'as needed' ? 'selected' : '' ?>>As needed</option>
                                                        </select>
                                                    </div>
                                                </td>
                                                <td>
                                                    <button type="button" class="remove-btn"
                                                        onclick="removeMedicationRow(this, <?= $index ?>)">×</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <button type="button" class="add-btn" onclick="addMedicationRow(<?= $index ?>)">Add Medication</button>
                            </fieldset>

                            <fieldset class="form-section">
                                <legend>COVID Vaccination</legend>
                                <div class="radio-group" id="vaccinationGroup<?= $index ?>">
                                    <label class="radio-label">
                                        <input type="radio" name="vaccination<?= $index ?>" value="fully" required
                                            <?= ($_POST["vaccination$index"] ?? '') === 'fully' ? 'checked' : '' ?>>
                                        Fully vaccinated (Primary series with or without booster shot/s)
                                    </label>
                                    <label class="radio-label">
                                        <input type="radio" name="vaccination<?= $index ?>" value="partially"
                                            <?= ($_POST["vaccination$index"] ?? '') === 'partially' ? 'checked' : '' ?>>
                                        Partially vaccinated (Incomplete primary series)
                                    </label>
                                    <label class="radio-label">
                                        <input type="radio" name="vaccination<?= $index ?>" value="not"
                                            <?= ($_POST["vaccination$index"] ?? '') === 'not' ? 'checked' : '' ?>>
                                        Not vaccinated
                                    </label>
                                    <div class="invalid-feedback" style="display: none;">Please select a vaccination status</div>
                                </div>
                            </fieldset>

                            <fieldset class="form-section menstrual-section" id="menstrualSection<?= $index ?>"
                                style="display: <?= ($_POST["sex$index"] ?? '') === 'female' ? 'block' : 'none' ?>;">
                                <legend>Menstrual History</legend>
                                <p class="form-subtitle">(for females only)</p>
                                <div class="menstrual-grid">
                                    <div class="form-group">
                                        <label>Age when menstruation began:</label>
                                        <input type="number" name="menstruationAge<?= $index ?>" class="short-input"
                                            value="<?= htmlspecialchars($_POST["menstruationAge$index"] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Menstrual Pattern:</label>
                                        <div class="radio-group">
                                            <label class="radio-label">
                                                <input type="radio" name="menstrualPattern<?= $index ?>" value="regular"
                                                    <?= ($_POST["menstrualPattern$index"] ?? '') === 'regular' ? 'checked' : '' ?>>
                                                Regular (monthly)
                                            </label>
                                            <label class="radio-label">
                                                <input type="radio" name="menstrualPattern<?= $index ?>" value="irregular"
                                                    <?= ($_POST["menstrualPattern$index"] ?? '') === 'irregular' ? 'checked' : '' ?>>
                                                Irregular
                                            </label>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Menstrual Symptoms:</label>
                                        <div class="checkbox-group">
                                            <label class="checkbox-label">
                                                <input type="checkbox" name="menstrual_symptoms<?= $index ?>[]" value="dysmenorrhea"
                                                    <?= in_array('dysmenorrhea', (array)($_POST["menstrual_symptoms$index"] ?? [])) ? 'checked' : '' ?>>
                                                Dysmenorrhea (cramps)
                                            </label>
                                            <label class="checkbox-label">
                                                <input type="checkbox" name="menstrual_symptoms<?= $index ?>[]" value="migraine"
                                                    <?= in_array('migraine', (array)($_POST["menstrual_symptoms$index"] ?? [])) ? 'checked' : '' ?>>
                                                Migraine
                                            </label>
                                            <label class="checkbox-label">
                                                <input type="checkbox" name="menstrual_symptoms<?= $index ?>[]" value="consciousness"
                                                    <?= in_array('consciousness', (array)($_POST["menstrual_symptoms$index"] ?? [])) ? 'checked' : '' ?>>
                                                Loss of consciousness
                                            </label>
                                            <label class="checkbox-label">
                                                <input type="checkbox" name="menstrual_symptoms<?= $index ?>[]" value="other"
                                                    <?= in_array('other', (array)($_POST["menstrual_symptoms$index"] ?? [])) ? 'checked' : '' ?>>
                                                Other:
                                                <input type="text" class="inline-input" name="otherSymptoms<?= $index ?>"
                                                    value="<?= htmlspecialchars($_POST["otherSymptoms$index"] ?? '') ?>">
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </fieldset>

                            <div class="form-navigation">
                                <button type="button" class="btn btn-secondary" onclick="prevStep(<?= $index ?>)">Previous</button>
                                <button type="button" class="btn btn-primary" onclick="nextStep2(<?= $index ?>)">Next</button>
                            </div>
                        </div>

                        <!-- Step 3: Past Medical History -->
                        <div class="form-step" id="step3-<?= $index ?>" style="display: none;">
                            <fieldset class="form-section">
                                <legend>Past Medical & Surgical History</legend>
                                <label class="form-label">Which of these conditions has the student had in the past?</label>
                                <div class="checkbox-grid">
                                    <div class="checkbox-column">
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="past_illness<?= $index ?>[]" value="varicella"
                                                <?= in_array('varicella', (array)($_POST["past_illness$index"] ?? [])) ? 'checked' : '' ?>>
                                            Varicella (Chicken Pox)
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="past_illness<?= $index ?>[]" value="dengue"
                                                <?= in_array('dengue', (array)($_POST["past_illness$index"] ?? [])) ? 'checked' : '' ?>>
                                            Dengue
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="past_illness<?= $index ?>[]" value="tuberculosis"
                                                <?= in_array('tuberculosis', (array)($_POST["past_illness$index"] ?? [])) ? 'checked' : '' ?>>
                                            Tuberculosis
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="past_illness<?= $index ?>[]" value="pneumonia"
                                                <?= in_array('pneumonia', (array)($_POST["past_illness$index"] ?? [])) ? 'checked' : '' ?>>
                                            Pneumonia
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="past_illness<?= $index ?>[]" value="UTI"
                                                <?= in_array('uti', (array)($_POST["past_illness$index"] ?? [])) ? 'checked' : '' ?>>
                                            Urinary Tract Infection
                                        </label>
                                    </div>

                                    <div class="checkbox-column">
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="past_illness<?= $index ?>[]" value="appendicitis"
                                                <?= in_array('appendicitis', (array)($_POST["past_illness$index"] ?? [])) ? 'checked' : '' ?>>
                                            Appendicitis
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="past_illness<?= $index ?>[]" value="Cholecystitis"
                                                <?= in_array('cholecystitis', (array)($_POST["past_illness$index"] ?? [])) ? 'checked' : '' ?>>
                                            Cholecystitis
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="past_illness<?= $index ?>[]" value="measles"
                                                <?= in_array('measles', (array)($_POST["past_illness$index"] ?? [])) ? 'checked' : '' ?>>
                                            Measles
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="past_illness<?= $index ?>[]" value="typhoid fever"
                                                <?= in_array('typhoid fever', (array)($_POST["past_illness$index"] ?? [])) ? 'checked' : '' ?>>
                                            Typhoid Fever
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="past_illness<?= $index ?>[]" value="amoebiasis"
                                                <?= in_array('amoebiasis', (array)($_POST["past_illness$index"] ?? [])) ? 'checked' : '' ?>>
                                            Amoebiasis
                                        </label>
                                    </div>

                                    <div class="checkbox-column">
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="past_illness<?= $index ?>[]" value="kidney stones"
                                                <?= in_array('Kidney Stones', (array)($_POST["past_illness$index"] ?? [])) ? 'checked' : '' ?>>
                                            Kidney Stones
                                        </label>

                                        <label class="checkbox-label">
                                            <input type="checkbox" name="past_illness<?= $index ?>[]" value="injury"
                                                <?= in_array('injury', (array)($_POST["past_illness$index"] ?? [])) ? 'checked' : '' ?>>
                                            Injury
                                        </label>

                                        <div class="nested-checkboxes" style="margin-left: 20px;">
                                            <label class="checkbox-label nested">
                                                <input type="checkbox" name="past_illness<?= $index ?>[]" value="burn"
                                                    <?= in_array('burn', (array)($_POST["past_illness$index"] ?? [])) ? 'checked' : '' ?>>
                                                Burn
                                            </label>
                                            <label class="checkbox-label nested">
                                                <input type="checkbox" name="past_illness<?= $index ?>[]" value="stab"
                                                    <?= in_array('stab', (array)($_POST["past_illness$index"] ?? [])) ? 'checked' : '' ?>>
                                                Stab/Laceration
                                            </label>
                                            <label class="checkbox-label nested">
                                                <input type="checkbox" name="past_illness<?= $index ?>[]" value="fracture"
                                                    <?= in_array('fracture', (array)($_POST["past_illness$index"] ?? [])) ? 'checked' : '' ?>>
                                                Fracture
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="otherPastIllnessCheckbox<?= $index ?>"
                                            <?= !empty($_POST["past_illness_other$index"]) ? 'checked' : '' ?>
                                            onclick="toggleOtherPastIllness(<?= $index ?>)">
                                        Other (Specify)
                                    </label>
                                    <input type="text" class="form-control"
                                        id="otherPastIllnessInput<?= $index ?>"
                                        name="past_illness_other<?= $index ?>"
                                        placeholder="Specify other illnesses"
                                        style="display: <?= !empty($_POST["past_illness_other$index"]) ? 'block' : 'none' ?>; width: 300px; margin-top: 5px;"
                                        value="<?= htmlspecialchars($_POST["past_illness_other$index"] ?? '') ?>">
                                </div>
                            </fieldset>

                            <script>
                                function toggleOtherPastIllness(index) {
                                    const input = document.getElementById("otherPastIllnessInput" + index);
                                    const checkbox = document.getElementById("otherPastIllnessCheckbox" + index);
                                    input.style.display = checkbox.checked ? "block" : "none";
                                }
                            </script>


                            <fieldset class="form-section">
                                <legend>Hospital Admission / Surgery</legend>
                                <label class="form-label">Has the student ever been admitted to the hospital and/or undergone surgery?</label>
                                <div class="radio-group">
                                    <label class="radio-label">
                                        <input type="radio" name="hospital_admission<?= $index ?>" value="No"
                                            onclick="toggleSurgeryFields(<?= $index ?>, false)"
                                            <?= ($_POST["hospital_admission$index"] ?? 'No') === 'No' ? 'checked' : '' ?>>
                                        No
                                    </label>
                                    <label class="radio-label">
                                        <input type="radio" name="hospital_admission<?= $index ?>" value="Yes"
                                            onclick="toggleSurgeryFields(<?= $index ?>, true)"
                                            <?= ($_POST["hospital_admission$index"] ?? '') === 'Yes' ? 'checked' : '' ?>>
                                        Yes
                                    </label>
                                </div>

                                <div id="surgeryDetails<?= $index ?>" style="display: <?= ($_POST["hospital_admission$index"] ?? '') === 'Yes' ? 'block' : 'none' ?>; margin-top: 15px;">
                                    <table class="medications-table" id="surgeryTable<?= $index ?>">
                                        <thead>
                                            <tr>
                                                <th>Year</th>
                                                <th>Reason</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $admissions = $_POST["hospital_admissions$index"] ?? [];
                                            foreach ($admissions as $admIndex => $admission):
                                            ?>
                                                <tr>
                                                    <td>
                                                        <input type="number" class="table-input"
                                                            name="hospital_admissions<?= $index ?>[<?= $admIndex ?>][year]"
                                                            min="1900" max="2025" placeholder="e.g., 2015"
                                                            value="<?= htmlspecialchars($admission['year'] ?? '') ?>">
                                                    </td>
                                                    <td>
                                                        <input type="text" class="table-input"
                                                            name="hospital_admissions<?= $index ?>[<?= $admIndex ?>][reason]"
                                                            placeholder="e.g., Appendectomy"
                                                            value="<?= htmlspecialchars($admission['reason'] ?? '') ?>">
                                                    </td>
                                                    <td>
                                                        <button type="button" class="remove-btn"
                                                            onclick="removeSurgeryRow(this, <?= $index ?>)">×</button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <button type="button" class="add-btn" onclick="addSurgeryRow(<?= $index ?>)">+ Add Admission/Surgery</button>
                                </div>
                            </fieldset>

                            <fieldset class="form-section">
                                <legend>Family Medical History</legend>
                                <label class="form-label">Indicate the known health conditions of your immediate family members:</label>

                                <div class="checkbox-grid">
                                    <!-- Column 1 (keep existing + add missing) -->
                                    <div class="checkbox-column">
                                        <!-- EXISTING -->


                                        <label class="checkbox-label">
                                            <input type="checkbox" name="family_history<?= $index ?>[]" value="hypertension">
                                            Hypertension (Elevated Blood Pressure)
                                        </label>

                                        <label class="checkbox-label">
                                            <input type="checkbox" name="family_history<?= $index ?>[]" value="congestive">
                                            Congestive Heart Failure
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="family_history<?= $index ?>[]" value="diabetes">
                                            Diabetes Mellitus (Elevated Blood Sugar)
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="family_history<?= $index ?>[]" value="chronic_kidney">
                                            Chronic Kidney Disease (With/Without Hemodialysis)
                                        </label>

                                        <!-- ADDED FROM form.php -->
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="family_history<?= $index ?>[]" value="copd">
                                            Chronic Obstructive Pulmonary Disease (COPD)
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="family_history<?= $index ?>[]" value="gerd">
                                            Gastroesophageal Reflux Disease (GERD)
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="family_history<?= $index ?>[]" value="bowel_syndrome">
                                            Irritable Bowel Syndrome
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="family_history<?= $index ?>[]" value="epilepsy">
                                            Epilepsy / Seizures
                                        </label>
                                    </div>

                                    <!-- Column 2 (keep existing + add missing) -->
                                    <div class="checkbox-column">


                                        <!-- Your original “specify cancer type” controls -->
                                        <label class="checkbox-label">
                                            <input type="checkbox" id="cancerCheckbox_<?= $index ?>" onclick="toggleFamilyInput(<?= $index ?>, 'cancer')">
                                            Cancer - Specify Type
                                        </label>
                                        <input type="text"
                                            class="form-control d-none"
                                            id="cancerInput_<?= $index ?>"
                                            name="family_cancer_details<?= $index ?>"
                                            placeholder="Specify cancer type"
                                            style="margin-top: 5px;">

                                        <!-- EXISTING -->
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="family_history<?= $index ?>[]" value="stroke">
                                            Stroke (Cerebrovascular Disease)
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="family_history<?= $index ?>[]" value="asthma">
                                            Asthma
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="family_history<?= $index ?>[]" value="tuberculosis">
                                            Tuberculosis
                                        </label>

                                        <!-- ADDED FROM form.php -->
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="family_history<?= $index ?>[]" value="rhinitis">
                                            Allergic Rhinitis
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="family_history<?= $index ?>[]" value="hyperthyroidism">
                                            Hyperthyroidism
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="family_history<?= $index ?>[]" value="hypothyroidism">
                                            Hypothyroidism / Goiter
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="family_history<?= $index ?>[]" value="anemia">
                                            Anemia
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="family_history<?= $index ?>[]" value="migraine">
                                            Migraine (recurrent headaches)
                                        </label>
                                    </div>

                                    <!-- Column 3 (keep existing + add missing) -->
                                    <div class="checkbox-column">
                                        <!-- EXISTING -->
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="family_history<?= $index ?>[]" value="psychiatric">
                                            Psychiatric Illness:
                                        </label>


                                        <!-- ADDED FROM form.php: nested psychiatric options -->
                                        <div class="nested-checkboxes" style="margin-left:1.5rem;">
                                            <label class="checkbox-label nested">
                                                <input type="checkbox" name="family_history<?= $index ?>[]" value="depression">
                                                Major Depressive Disorder
                                            </label>
                                            <label class="checkbox-label nested">
                                                <input type="checkbox" name="family_history<?= $index ?>[]" value="bipolar">
                                                Bipolar Disorder
                                            </label>
                                            <label class="checkbox-label nested">
                                                <input type="checkbox" name="family_history<?= $index ?>[]" value="anxiety">
                                                Generalized Anxiety Disorder
                                            </label>
                                            <label class="checkbox-label nested">
                                                <input type="checkbox" name="family_history<?= $index ?>[]" value="panic">
                                                Panic Disorder
                                            </label>
                                            <label class="checkbox-label nested">
                                                <input type="checkbox" name="family_history<?= $index ?>[]" value="ptsd">
                                                Posttraumatic Stress Disorder
                                            </label>
                                            <label class="checkbox-label nested">
                                                <input type="checkbox" name="family_history<?= $index ?>[]" value="schizophrenia">
                                                Schizophrenia
                                            </label>
                                            <div style="margin-top:6px;">
                                                Other:
                                                <input type="text" placeholder="Specify other mental illness"
                                                    name="family_other_mental_illness<?= $index ?>[]" class="inline-input">
                                            </div>
                                        </div>

                                        <!-- EXISTING -->
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="family_history<?= $index ?>[]" value="liver_disease">
                                            Liver Disease (Hepatitis, Cirrhosis)
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="family_history<?= $index ?>[]" value="arthritis">
                                            Arthritis/Gout
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="family_history<?= $index ?>[]" value="blood_disorder">
                                            Blood Disorder (Anemia, Hemophilia, etc.)
                                        </label>



                                        <!-- EXISTING allergy specify input
                                         
                                           const checkbox = document.getElementById(`${type}Checkbox_${index}`);
                                           const input = document.getElementById(`${type}Input_${index}`);
            otherFamilyInput_
                                        -->
                                        <label class="checkbox-label">
                                            <input type="checkbox" id="allergyCheckbox_<?= $index ?>" onclick="toggleFamilyInput(<?= $index ?>, 'allergy')">
                                            Allergies - Specify
                                        </label>
                                        <input type="text"
                                            class="form-control d-none"
                                            id="allergyInput_<?= $index ?>"
                                            name="family_allergy_specify<?= $index ?>"
                                            placeholder="Specify allergies"
                                            style="margin-top: 5px;">

                                        <!-- ADDED FROM form.php: dyslipidemia, PCOS, lupus/SLE -->
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="family_history<?= $index ?>[]" value="dyslipidemia">
                                            Dyslipidemia (Elevated cholesterol levels)
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="family_history<?= $index ?>[]" value="pcos">
                                            Polycystic Ovarian Syndrome (PCOS)
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="family_history<?= $index ?>[]" value="lupus">
                                            Systemic Lupus Erythematosus (SLE)
                                        </label>

                                        <!-- EXISTING -->
                                        <label class="checkbox-label">
                                            <input type="checkbox" id="otherFamilyCheckbox_<?= $index ?>" onclick="toggleFamilyInput(<?= $index ?>, 'otherFamily')">
                                            Other (Specify)
                                        </label>
                                        <input type="text"
                                            class="form-control d-none"
                                            id="otherFamilyInput_<?= $index ?>"
                                            name="other_family_history<?= $index ?>"
                                            placeholder="Specify other conditions"
                                            style="margin-top: 5px;">
                                    </div>
                                </div>
                            </fieldset>



                            <div class="form-navigation">
                                <button type="button" class="btn btn-secondary"
                                    onclick="prevStep2(<?= $index ?>)">Previous</button>
                                <?php if ($index === count($children) - 1): ?>
                                    <button type="submit" class="btn btn-success">Submit All</button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-primary"
                                        onclick="nextStudent(<?= $index ?>)">Next Student</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </form>
        <?php endif; ?>
    </div>

    <!-- JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleFamilyInput(index, type) {
            const checkbox = document.getElementById(`${type}Checkbox_${index}`);
            const input = document.getElementById(`${type}Input_${index}`);

            if (checkbox.checked) {
                input.classList.remove('d-none');
            } else {
                input.classList.add('d-none');
                input.value = ''; // Clear the input when unchecked
            }
        }

        function toggleIllnessInput(type, index) {
            const checkbox = document.getElementById(`${type}Checkbox${index}`);
            const input = document.getElementById(`${type}Input${index}`);
            input.style.display = checkbox.checked ? 'inline-block' : 'none';

            // Optional: clear text if unchecked
            if (!checkbox.checked) input.value = '';
        }

        function displayImage(input, previewId) {
            console.log('Displaying image for preview:', previewId);
            const file = input.files[0];
            const preview = document.getElementById(previewId);
            const uploadText = document.getElementById(`uploadText${previewId.replace('previewImage', '')}`);

            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    uploadText.style.display = 'none';
                    console.log('Image preview updated');
                };
                reader.readAsDataURL(file);
            } else {
                console.log('No file selected for image upload');
            }
        }

        function calculateAge(index) {
            console.log('Calculating age for student:', index);
            const birthdayInput = document.getElementById(`birthday${index}`);
            const ageInput = document.getElementById(`age${index}`);
            const birthdayError = document.getElementById(`birthdayError${index}`);

            const birthday = new Date(birthdayInput.value);
            const today = new Date();

            if (isNaN(birthday.getTime())) {
                birthdayInput.classList.add('is-invalid');
                birthdayError.textContent = 'Please select a valid birthday';
                ageInput.value = '';
                console.log('Invalid birthday');
                return;
            }

            let age = today.getFullYear() - birthday.getFullYear();
            const monthDiff = today.getMonth() - birthday.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthday.getDate())) {
                age--;
            }

            if (age < 0 || age > 100) {
                birthdayInput.classList.add('is-invalid');
                birthdayError.textContent = 'Invalid age calculated';
                ageInput.value = '';
                console.log('Age out of valid range');
            } else {
                birthdayInput.classList.remove('is-invalid');
                ageInput.value = age;
                console.log('Age calculated:', age);
            }
        }

        function toggleMenstrualSection(index) {
            console.log('Toggling menstrual section for student:', index);
            const sexSelect = document.getElementById(`sex${index}`);
            const menstrualSection = document.getElementById(`menstrualSection${index}`);
            menstrualSection.style.display = sexSelect.value === 'female' ? 'block' : 'none';
            console.log('Menstrual section display:', menstrualSection.style.display);
        }

        function toggleOtherReligionInput(index) {
            console.log('Toggling other religion input for student:', index);
            const religionSelect = document.getElementById(`religion${index}`);
            const otherReligionWrapper = document.getElementById(`otherReligionWrapper${index}`);
            const otherReligionInput = document.getElementById(`otherReligion${index}`);

            if (religionSelect.value === 'OTHER') {
                otherReligionWrapper.style.display = 'block';
                otherReligionInput.required = true;
                console.log('Showing other religion input');
            } else {
                otherReligionWrapper.style.display = 'none';
                otherReligionInput.required = false;
                otherReligionInput.value = '';
                console.log('Hiding other religion input');
            }
        }

        function toggleOtherRelationshipInput(index) {
            console.log('Toggling other relationship input for student:', index);
            const relationshipSelect = document.getElementById(`emergencyRelationship${index}`);
            const otherRelationshipWrapper = document.getElementById(`otherRelationshipWrapper${index}`);
            const otherRelationshipInput = document.getElementById(`otherRelationship${index}`);

            if (relationshipSelect.value === 'Other') {
                otherRelationshipWrapper.style.display = 'block';
                otherRelationshipInput.required = true;
                console.log('Showing other relationship input');
            } else {
                otherRelationshipWrapper.style.display = 'none';
                otherRelationshipInput.required = false;
                otherRelationshipInput.value = '';
                console.log('Hiding other relationship input');
            }
        }

        function toggleEmergencyContactCopy(index) {
            console.log('Toggling emergency contact copy for student:', index);
            const checkbox = document.getElementById(`sameEmergencyContact${index}`);
            const emergencyFields = [
                `emergencySurname${index}`,
                `emergencyFirstname${index}`,
                `emergencyMiddlename${index}`,
                `emergencyContactNumber${index}`,
                `emergencyRelationship${index}`,
                `emergencyCityAddress${index}`,
                `otherRelationship${index}`
            ];

            emergencyFields.forEach(field => {
                const element = document.getElementById(field);
                if (element) {
                    element.disabled = checkbox.checked;
                    if (checkbox.checked) {
                        element.value = document.getElementById(field.replace(index, '0')).value;
                        console.log(`Copied value for ${field}`);
                    }
                }
            });

            const otherRelationshipWrapper = document.getElementById(`otherRelationshipWrapper${index}`);
            if (otherRelationshipWrapper) {
                otherRelationshipWrapper.style.display = checkbox.checked ?
                    document.getElementById(`otherRelationshipWrapper0`).style.display :
                    document.getElementById(`emergencyRelationship${index}`).value === 'Other' ? 'block' : 'none';
                console.log('Other relationship wrapper display:', otherRelationshipWrapper.style.display);
            }
        }

        function validatePhoneNumber(input) {
            console.log('Validating phone number:', input.value);
            input.value = input.value.replace(/[^0-9]/g, '');
            if (input.value.length > 11) {
                input.value = input.value.slice(0, 11);
            }
            if (input.value && !input.value.startsWith('09')) {
                input.classList.add('is-invalid');
                console.log('Invalid phone number: does not start with 09');
            } else if (input.value.length === 11) {
                input.classList.remove('is-invalid');
                console.log('Valid phone number');
            }
        }

        function addMedicationRow(index) {
            console.log('Adding medication row for student:', index);
            const table = document.getElementById(`medicationsTable${index}`).getElementsByTagName('tbody')[0];
            const rowCount = table.rows.length;
            const row = table.insertRow();

            row.innerHTML = `
        <td>
            <select class="table-input drug-select" name="medications${index}[${rowCount}][drug]" onchange="handleDrugSelect(this)">
                <option value="">Select a drug</option>
                <option value="paracetamol">Paracetamol</option>
                <option value="ibuprofen">Ibuprofen</option>
                <option value="amoxicillin">Amoxicillin</option>
                <option value="fluticasone">Fluticasone</option>
                <option value="budesonide">Budesonide</option>
                <option value="montelukast">Montelukast</option>
                <option value="cetirizine">Cetirizine</option>
                <option value="methylphenidate">Methylphenidate</option>
                <option value="lisdexamfetamine">Lisdexamfetamine</option>
                <option value="guanfacine">Guanfacine</option>
                <option value="insulin">Insulin</option>
                <option value="levetiracetam">Levetiracetam</option>
                <option value="valproic_acid">Valproic Acid</option>
                <option value="other">Other</option>
            </select>
            <input type="text" class="table-input other-input" name="medications${index}[${rowCount}][drug_other]" 
                   placeholder="Enter drug name" style="display: none;">
        </td>
        <td>
            <div class="dose-options">
                <input type="number" class="table-input" name="medications${index}[${rowCount}][dose]" 
                       placeholder="Dose" style="width: 80px;">
                <select class="table-input" name="medications${index}[${rowCount}][unit]">
                    <option value="mg">mg</option>
                    <option value="g">g</option>
                    <option value="ml">ml</option>
                    <option value="units">units</option>
                </select>
                <select class="table-input" name="medications${index}[${rowCount}][frequency]">
                    <option value="">Choose Frequency</option>
                    <option value="once daily">Once daily</option>
                    <option value="twice daily">Twice daily</option>
                    <option value="three times daily">Three times daily</option>
                    <option value="four times daily">Four times daily</option>
                    <option value="as needed">As needed</option>
                </select>
            </div>
        </td>
        <td>
            <button type="button" class="remove-btn" onclick="removeMedicationRow(this, ${index})">×</button>
        </td>
    `;
            console.log('Medication row added');
        }

        function removeMedicationRow(button, index) {
            console.log('Removing medication row for student:', index);
            const row = button.closest('tr');
            row.parentNode.removeChild(row);
            console.log('Medication row removed');
        }

        function handleDrugSelect(select) {
            console.log('Handling drug select:', select.value);
            const row = select.closest('tr');
            const otherInput = row.querySelector('.other-input');
            otherInput.style.display = select.value === 'other' ? 'inline-block' : 'none';
            if (select.value !== 'other') {
                otherInput.value = '';
                console.log('Cleared other drug input');
            }
            console.log('Other input display:', otherInput.style.display);
        }

        function addSurgeryRow(index) {
            console.log('Adding surgery row for student:', index);
            const table = document.getElementById(`surgeryTable${index}`).getElementsByTagName('tbody')[0];
            const rowCount = table.rows.length;
            const row = table.insertRow();

            row.innerHTML = `
        <td>
            <input type="number" class="table-input" name="hospital_admissions${index}[${rowCount}][year]" 
                   min="1900" max="2025" placeholder="e.g., 2015">
        </td>
        <td>
            <input type="text" class="table-input" name="hospital_admissions${index}[${rowCount}][reason]" 
                   placeholder="e.g., Appendectomy">
        </td>
        <td>
            <button type="button" class="remove-btn" onclick="removeSurgeryRow(this, ${index})">×</button>
        </td>
    `;
            console.log('Surgery row added');
        }

        function removeSurgeryRow(button, index) {
            console.log('Removing surgery row for student:', index);
            const row = button.closest('tr');
            row.parentNode.removeChild(row);
            console.log('Surgery row removed');
        }

        function toggleSurgeryFields(index, show) {
            console.log('Toggling surgery fields for student:', index, 'Show:', show);
            const surgeryDetails = document.getElementById(`surgeryDetails${index}`);
            surgeryDetails.style.display = show ? 'block' : 'none';
            console.log('Surgery details display:', surgeryDetails.style.display);
        }

        function toggleOtherPastIllness(index) {
            console.log('Toggling other past illness for student:', index);
            const checkbox = document.getElementById(`otherPastIllnessCheckbox${index}`);
            const input = document.getElementById(`otherPastIllnessInput${index}`);
            input.style.display = checkbox.checked ? 'block' : 'none';
            input.required = checkbox.checked;
            if (!checkbox.checked) {
                input.value = '';
                console.log('Cleared other past illness input');
            }
            console.log('Other past illness input display:', input.style.display);
        }

        function toggleOtherFamilyHistory(index) {
            console.log('Toggling other family history for student:', index);
            const checkbox = document.querySelector(`input[name="family_history${index}[]"][value="other"]`);
            const input = document.getElementById(`otherFamilyHistoryInput${index}`);
            input.style.display = checkbox.checked ? 'block' : 'none';
            input.required = checkbox.checked;
            if (!checkbox.checked) {
                input.value = '';
                console.log('Cleared other family history input');
            }
            console.log('Other family history input display:', input.style.display);
        }

        function switchTab(targetId) {
            console.log('Switching to tab:', targetId);
            const tabs = document.querySelectorAll('.student-tab');
            const forms = document.querySelectorAll('.student-form');

            tabs.forEach(tab => tab.classList.remove('active'));
            forms.forEach(form => {
                form.classList.remove('active');
                form.style.display = 'none';
            });

            const targetTab = document.querySelector(`.student-tab[data-target="${targetId}"]`);
            const targetForm = document.getElementById(targetId);

            if (targetTab && targetForm) {
                targetTab.classList.add('active');
                targetForm.classList.add('active');
                targetForm.style.display = 'block';

                const steps = targetForm.querySelectorAll('.form-step');
                steps.forEach((step, index) => {
                    step.style.display = index === 0 ? 'block' : 'none';
                });
                console.log('Switched to tab and form:', targetId);
            } else {
                console.error('Target tab or form not found:', targetId);
            }
        }

        function showMissingFieldsModal(errors) {
            const modalElement = document.getElementById('missingFieldsModal');
            if (modalElement) {
                const errorList = document.getElementById('missingFieldsList');
                if (errorList) {
                    errorList.innerHTML = errors.map(error => `<li>${error}</li>`).join('');
                }
                const modal = new bootstrap.Modal(modalElement);
                modal.show();
                console.log('missingFieldsModal shown with errors:', errors);
            } else {
                console.error('missingFieldsModal element not found');
            }
        }

        function nextStep(index) {
            console.log('Next step for student:', index);
            const step1 = document.getElementById(`step1-${index}`);
            const step2 = document.getElementById(`step2-${index}`);
            const validationResult = validateStep1(index);

            if (validationResult.isValid) {
                step1.style.display = 'none';
                step2.style.display = 'block';
                console.log('Moved to step 2');
            } else {
                showMissingFieldsModal(validationResult.errors);
            }
        }

        function prevStep(index) {
            console.log('Previous step for student:', index);
            const step1 = document.getElementById(`step1-${index}`);
            const step2 = document.getElementById(`step2-${index}`);
            step2.style.display = 'none';
            step1.style.display = 'block';
            console.log('Moved to step 1');
        }

        function nextStep2(index) {
            console.log('Next step 2 for student:', index);
            const step2 = document.getElementById(`step2-${index}`);
            const step3 = document.getElementById(`step3-${index}`);
            const validationResult = validateStep2(index);

            if (validationResult.isValid) {
                step2.style.display = 'none';
                step3.style.display = 'block';
                console.log('Moved to step 3');
            } else {
                showMissingFieldsModal(validationResult.errors);
            }
        }

        function prevStep2(index) {
            console.log('Previous step 2 for student:', index);
            const step2 = document.getElementById(`step2-${index}`);
            const step3 = document.getElementById(`step3-${index}`);
            step3.style.display = 'none';
            step2.style.display = 'block';
            console.log('Moved to step 2');
        }

        function nextStudent(index) {
            console.log('Next student:', index);
            const nextIndex = index + 1;
            const nextFormId = `studentForm${nextIndex}`;

            // Validate current step based on which step is visible
            const step1 = document.getElementById(`step1-${index}`);
            const step2 = document.getElementById(`step2-${index}`);
            const step3 = document.getElementById(`step3-${index}`);

            let validationResult;
            if (step1.style.display !== 'none') {
                validationResult = validateStep1(index);
            } else if (step2.style.display !== 'none') {
                validationResult = validateStep2(index);
            } else {
                validationResult = validateStep3(index);
            }

            if (validationResult.isValid && document.getElementById(nextFormId)) {
                switchTab(nextFormId);
                console.log('Switched to next student:', nextIndex);
            } else if (!validationResult.isValid) {
                showMissingFieldsModal(validationResult.errors);
            } else {
                console.log('No next student form found');
            }
        }

        function prevStudent(index) {
            console.log('Previous student:', index);
            const prevIndex = index - 1;
            const prevFormId = `studentForm${prevIndex}`;
            if (document.getElementById(prevFormId)) {
                switchTab(prevFormId);
                console.log('Switched to previous student:', prevIndex);
            } else {
                console.log('No previous student form found');
            }
        }

        function validateStep1(index) {
            console.log('Validating step 1 for student:', index);
            let isValid = true;
            const errors = [];
            const requiredFields = [{
                    name: `surname${index}`,
                    label: 'Surname'
                },
                {
                    name: `firstname${index}`,
                    label: 'First Name'
                },
                {
                    name: `birthday${index}`,
                    label: 'Birthday'
                },
                {
                    name: `sex${index}`,
                    label: 'Sex'
                },
                {
                    name: `gradeLevel${index}`,
                    label: 'Grade Level'
                },
                {
                    name: `gradingQuarter${index}`,
                    label: 'Grading Quarter'
                },
                {
                    name: `religion${index}`,
                    label: 'Religion'
                },
                {
                    name: `nationality${index}`,
                    label: 'Nationality'
                },
                {
                    name: `email${index}`,
                    label: 'Email'
                },
                {
                    name: `contactNumber${index}`,
                    label: 'Contact Number'
                },
                {
                    name: `studentId${index}`,
                    label: 'Student ID'
                },
                {
                    name: `cityAddress${index}`,
                    label: 'City Address'
                },
                {
                    name: `emergencySurname${index}`,
                    label: 'Emergency Contact Surname'
                },
                {
                    name: `emergencyFirstname${index}`,
                    label: 'Emergency Contact First Name'
                },
                {
                    name: `emergencyContactNumber${index}`,
                    label: 'Emergency Contact Number'
                },
                {
                    name: `emergencyRelationship${index}`,
                    label: 'Emergency Relationship'
                },
                {
                    name: `emergencyCityAddress${index}`,
                    label: 'Emergency City Address'
                }
            ];

            requiredFields.forEach(field => {
                const input = document.querySelector(`[name="${field.name}"]`);
                if (input && !input.value.trim()) {
                    input.classList.add('is-invalid');
                    isValid = false;
                    errors.push(`Student ${index + 1}: ${field.label} is required`);
                    console.log(`Validation failed for field: ${field.name}`);
                } else if (input) {
                    input.classList.remove('is-invalid');
                }
            });

            const email = document.querySelector(`[name="email${index}"]`);
            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
                email.classList.add('is-invalid');
                isValid = false;
                errors.push(`Student ${index + 1}: Invalid email format`);
                console.log('Invalid email format');
            }

            const contact = document.querySelector(`[name="contactNumber${index}"]`);
            if (contact && !/^09[0-9]{9}$/.test(contact.value)) {
                contact.classList.add('is-invalid');
                isValid = false;
                errors.push(`Student ${index + 1}: Contact number must be 11 digits starting with 09`);
                console.log('Invalid contact number');
            }

            const emergencyContact = document.querySelector(`[name="emergencyContactNumber${index}"]`);
            if (emergencyContact && !/^09[0-9]{9}$/.test(emergencyContact.value)) {
                emergencyContact.classList.add('is-invalid');
                isValid = false;
                errors.push(`Student ${index + 1}: Emergency contact number must be 11 digits starting with 09`);
                console.log('Invalid emergency contact number');
            }

            const religion = document.querySelector(`[name="religion${index}"]`);
            const otherReligion = document.querySelector(`[name="other_religion${index}"]`);
            if (religion.value === 'OTHER' && (!otherReligion || !otherReligion.value.trim())) {
                if (otherReligion) otherReligion.classList.add('is-invalid');
                isValid = false;
                errors.push(`Student ${index + 1}: Please specify other religion`);
                console.log('Other religion not specified');
            } else if (otherReligion) {
                otherReligion.classList.remove('is-invalid');
            }

            const relationship = document.querySelector(`[name="emergencyRelationship${index}"]`);
            const otherRelationship = document.querySelector(`[name="other_relationship${index}"]`);
            if (relationship.value === 'Other' && (!otherRelationship || !otherRelationship.value.trim())) {
                if (otherRelationship) otherRelationship.classList.add('is-invalid');
                isValid = false;
                errors.push(`Student ${index + 1}: Please specify other relationship`);
                console.log('Other relationship not specified');
            } else if (otherRelationship) {
                otherRelationship.classList.remove('is-invalid');
            }

            console.log('Step 1 validation result:', isValid, 'Errors:', errors);
            return {
                isValid,
                errors
            };
        }

        function validateStep2(index) {
            console.log('Validating step 2 for student:', index);
            let isValid = true;
            const errors = [];

            const vaccinationGroup = document.getElementById(`vaccinationGroup${index}`);
            const vaccinationInputs = vaccinationGroup.querySelectorAll('input[type="radio"]');
            const vaccinationChecked = Array.from(vaccinationInputs).some(input => input.checked);

            if (!vaccinationChecked) {
                const errorMessage = vaccinationGroup.querySelector('.invalid-feedback');
                errorMessage.style.display = 'block';
                isValid = false;
                errors.push(`Student ${index + 1}: Vaccination status is required`);
                console.log('Vaccination status not selected');
            } else {
                const errorMessage = vaccinationGroup.querySelector('.invalid-feedback');
                errorMessage.style.display = 'none';
            }

            console.log('Step 2 validation result:', isValid, 'Errors:', errors);
            return {
                isValid,
                errors
            };
        }

        function validateStep3(index) {
            console.log('Validating step 3 for student:', index);
            return {
                isValid: true,
                errors: []
            };
        }

        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing tabs, forms, and modals');
            const tabs = document.querySelectorAll('.student-tab');
            const forms = document.querySelectorAll('.student-form');

            if (tabs.length === 0 || forms.length === 0) {
                console.error('No tabs or forms found');
                return;
            }

            tabs.forEach(tab => tab.classList.remove('active'));
            forms.forEach(form => {
                form.classList.remove('active');
                form.style.display = 'none';
            });

            if (tabs[0] && forms[0]) {
                tabs[0].classList.add('active');
                forms[0].classList.add('active');
                forms[0].style.display = 'block';

                const steps = forms[0].querySelectorAll('.form-step');
                steps.forEach((step, index) => {
                    step.style.display = index === 0 ? 'block' : 'none';
                });
                console.log('Initialized first tab and form: studentForm0');
            }

            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    switchTab(this.dataset.target);
                });
            });

            const inputs = document.querySelectorAll('input, select');
            inputs.forEach(input => {
                input.addEventListener('input', () => {
                    if (input.value.trim()) {
                        input.classList.remove('is-invalid');
                        console.log('Removed is-invalid for input:', input.name);
                    }
                });
            });

            const form = document.getElementById('healthProfileForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    console.log('Form submission triggered');
                    let isValid = true;
                    const allErrors = [];
                    const forms = document.querySelectorAll('.student-form');

                    forms.forEach((form, index) => {
                        const step1Result = validateStep1(index);
                        const step2Result = validateStep2(index);
                        const step3Result = validateStep3(index);

                        if (!step1Result.isValid) {
                            isValid = false;
                            allErrors.push(...step1Result.errors);
                        }
                        if (!step2Result.isValid) {
                            isValid = false;
                            allErrors.push(...step2Result.errors);
                        }
                        if (!step3Result.isValid) {
                            isValid = false;
                            allErrors.push(...step3Result.errors);
                        }
                    });

                    if (!isValid) {
                        e.preventDefault();
                        showMissingFieldsModal(allErrors);
                    } else {
                        console.log('Form validated successfully, submitting');
                    }
                });
            }

            const errorModalElement = document.getElementById('errorModal');
            const successModalElement = document.getElementById('successModal');

            <?php if (isset($_SESSION['errors']) && !empty($_SESSION['errors'])): ?>
                console.log('Showing errorModal due to session errors');
                if (errorModalElement) {
                    const errorContainer = errorModalElement.querySelector('.modal-body');
                    if (errorContainer) {
                        errorContainer.innerHTML = `
                    <ul>
                        <?php foreach ($_SESSION['errors'] as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                `;
                    }
                    try {
                        const errorModal = new bootstrap.Modal(errorModalElement);
                        errorModal.show();
                        console.log('errorModal initialized and shown with session errors');
                    } catch (e) {
                        console.error('Failed to show errorModal:', e);
                    }
                } else {
                    console.error('errorModal element not found');
                }
                <?php unset($_SESSION['errors']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                console.log('Showing successModal');
                if (successModalElement) {
                    try {
                        const successModal = new bootstrap.Modal(successModalElement);
                        successModal.show();
                        console.log('successModal initialized and shown');
                    } catch (e) {
                        console.error('Failed to show successModal:', e);
                    }
                } else {
                    console.error('successModal element not found');
                }
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
        });
    </script>