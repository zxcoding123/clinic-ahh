<?php
session_start();
include 'config.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pr-Site: no-cache");
header("Expires: 0");

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

error_log("=== STARTING FORM PROCESSING ===");
error_log("Session ID: " . session_id());
error_log("User ID in session: " . ($_SESSION['user_id'] ?? 'NOT SET'));

error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));

// Define admin user types
define('ADMIN_TYPES', ['Super Admin', 'Medical Admin', 'Dental Admin']);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("Unauthorized access to form.php: No user_id in session, redirecting to /login");
    header("Location: login.php");
    exit();
}

// Check if user is an admin and redirect to adminhome.php
$userId = (int)$_SESSION['user_id'];
$query = "SELECT user_type FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    error_log("Admin check query prepare failed: " . $conn->error);
    $_SESSION['error'] = "Database error. Please try again.";
    header("Location: login.php");
    exit();
}
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if ($user && in_array(($user['user_type']), ADMIN_TYPES, true)) {
    error_log("Admin user_id: $userId, user_type: {$user['user_type']} attempted to access form.php, redirecting to /adminhome.php");
    header("Location: adminhome.php");
    exit();
}

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch user data
$query = "SELECT last_name, first_name, middle_name, email, user_type, profile_submitted, documents_uploaded FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    error_log("User fetch query prepare failed: " . $conn->error);
    $_SESSION['error'] = "Database error. Please try again.";
    header("Location: login.php");
    exit();
}
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Fetch user data
$query = "SELECT last_name, first_name, middle_name, email, user_type, profile_submitted, documents_uploaded FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    error_log("User fetch query prepare failed: " . $conn->error);
    $_SESSION['error'] = "Database error. Please try again.";
    header("Location: login.php");
    exit();
}
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();




// Handle AJAX request for email fetch
if (isset($_GET['action']) && $_GET['action'] === 'get_email') {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'User not logged in']);
        exit();
    }

    $query = "SELECT email FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Email fetch query prepare failed: " . $conn->error);
        echo json_encode(['error' => 'Database error']);
        exit();
    }
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        echo json_encode(['email' => strtolower($user['email'])]);
    } else {
        echo json_encode(['error' => 'User not found']);
    }
    $stmt->close();
    exit();
}

// Assign user data to variables with proper capitalization
$userSurname = isset($user['last_name']) ? ucfirst(strtolower(trim($user['last_name']))) : '';
$userFirstname = isset($user['first_name']) ? ucfirst(strtolower(trim($user['first_name']))) : '';
$userMiddlename = isset($user['middle_name']) ? ucfirst(strtolower(trim($user['middle_name']))) : '';
$userEmail = isset($user['email']) ? strtolower(trim($user['email'])) : '';
$userType = isset($user['user_type']) ? $user['user_type'] : '';

// Map user_type to form-compatible values
$formUserType = match ($userType) {
    'Highschool' => 'high_school',
    'Senior High School' => 'senior_high',
    'College' => 'college',
    'Employee' => 'employee',
    'Incoming Freshman' => 'incoming_freshman',
    default => ''
};

// Function to sanitize input data
function sanitizeInput($data)
{
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Function to capitalize first letter
function capitalizeFirst($str)
{
    return ucfirst(strtolower(trim($str)));
}

// Function to handle file upload
function uploadFile($file, $uploadDir = 'Uploads/patient_photos/')
{
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new Exception('Invalid file parameters');
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return null;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new Exception('File too large');
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
        'image/gif' => 'gif'
    ];
    $maxSize = 40 * 1024 * 1024; // 40MB

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!array_key_exists($mimeType, $allowedTypes)) {
        throw new Exception('Invalid file type. Allowed: JPG, PNG, GIF');
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

    if (!is_uploaded_file($file['tmp_name']) || !move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('Failed to process uploaded file');
    }

    return $targetPath;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json'); // Ensure JSON response for AJAX
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        error_log("Invalid CSRF token for user_id: $userId");
        echo json_encode(['error' => 'Invalid form submission']);
        exit();
    }

    // Server-side validation
    $requiredFields = [
        'surname' => 'Surname',
        'firstname' => 'First name',
        'birthday' => 'Birthday',
        'sex' => 'Gender',
        'religion' => 'Religion',
        'nationality' => 'Nationality',
        'civilStatus' => 'Civil Status',
        'email' => 'Email address',
        'contactNumber' => 'Contact number',
        'studentId' => 'Student ID',
        'cityAddress' => 'City address',
        'emergencySurname' => 'Emergency contact surname',
        'emergencyFirstname' => 'Emergency contact first name',
        'emergencyContactNumber' => 'Emergency contact number',
        'emergencyRelationship' => 'Emergency contact relationship',
        'emergencyCityAddress' => 'Emergency contact city address',
        'vaccination' => 'Vaccination status'
    ];

    // Add user-type specific fields
    if ($formUserType === 'high_school') {
        $requiredFields['Grades'] = 'Grade';
        $requiredFields['gradeLevel'] = 'Grading quarter';
    } elseif ($formUserType === 'senior_high') {
        $requiredFields['Grades'] = 'Grade';
        $requiredFields['Track/Strand'] = 'Track/Strand';
        $requiredFields['section'] = 'Section';
        $requiredFields['Sem'] = 'Semester';
    } elseif ($formUserType === 'college') {
        $requiredFields['department'] = 'Department';
        $requiredFields['course'] = 'Course';
        $requiredFields['Sem'] = 'Semester';
        $requiredFields['yearLevel'] = 'Year Level';
    }
    // No additional required fields for 'employee' or 'incoming_freshman'

    $errors = [];
    foreach ($requiredFields as $field => $label) {
        if (empty(trim($_POST[$field] ?? ''))) {
            $errors[] = "$label is required";
        }
    }

    // Validate "Other" fields
    if (($_POST['religion'] ?? '') === 'OTHER' && empty(trim($_POST['other_religion'] ?? ''))) {
        $errors[] = 'Please specify the religion';
    }
    if ($formUserType === 'senior_high' && ($_POST['Track/Strand'] ?? '') === 'OTHER' && empty(trim($_POST['other_track_strand'] ?? ''))) {
        $errors[] = 'Please specify the track/strand';
    }
    if (($_POST['emergencyRelationship'] ?? '') === 'Other' && empty(trim($_POST['other_relationship'] ?? ''))) {
        $errors[] = 'Please specify the emergency contact relationship';
    }

    // Validate email
    if (!filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }

    // Validate phone numbers
    $phoneRegex = '/^09[0-9]{9}$/';
    if (!preg_match($phoneRegex, $_POST['contactNumber'] ?? '')) {
        $errors[] = 'Contact number must be 11 digits starting with 09';
    }
    if (!preg_match($phoneRegex, $_POST['emergencyContactNumber'] ?? '')) {
        $errors[] = 'Emergency contact number must be 11 digits starting with 09';
    }

    // Validate birthday
    $birthday = DateTime::createFromFormat('Y-m-d', $_POST['birthday'] ?? '');
    if (!$birthday || $birthday > new DateTime()) {
        $errors[] = 'Please enter a valid past birthday';
    }

    // Validate age
    if (empty($_POST['age']) || (int)$_POST['age'] < 11) {
        $errors[] = 'Age must be at least 11 years old';
    }

    // Validate yearLevel for college
    if ($formUserType === 'college' && !empty($_POST['yearLevel'])) {
        $yearLevel = (int)$_POST['yearLevel'];
        if ($yearLevel < 1 || $yearLevel > 5) {
            $errors[] = 'Year Level must be between 1 and 5';
        }
    }

    // Validate vaccination status
    if (!in_array($_POST['vaccination'] ?? '', ['fully', 'partially', 'not'])) {
        $errors[] = 'Please select a valid vaccination status';
    }

    // Validate hospital admissions
    if (isset($_POST['hospital_admission']) && $_POST['hospital_admission'] === 'Yes') {
        if (!isset($_POST['hospital_admissions']) || !is_array($_POST['hospital_admissions'])) {
            $errors[] = 'Please provide hospital admission details';
        } else {
            foreach ($_POST['hospital_admissions'] as $index => $admission) {
                if (empty(trim($admission['year'] ?? '')) || empty(trim($admission['reason'] ?? ''))) {
                    $errors[] = 'Please fill in all fields for hospital admission #' . ($index + 1);
                }
            }
        }
    }

    if (!empty($errors)) {
        error_log("Validation errors for user_id: $userId: " . implode('; ', $errors));
        echo json_encode(['error' => implode('; ', $errors)]);
        exit();
    }

    try {
        $conn->begin_transaction();

        // Handle religion
        $religion = $_POST['religion'] ?? '';
        if ($religion === 'OTHER' && !empty($_POST['other_religion'])) {
            $finalReligion = 'OTHER: ' . sanitizeInput($_POST['other_religion']);
        } else {
            $finalReligion = sanitizeInput($religion);
        }

        // Handle track/strand for senior_high only
        $finalTrackStrand = null;
        if ($formUserType === 'senior_high') {
            $trackStrand = $_POST['Track/Strand'] ?? '';
            if ($trackStrand === 'OTHER' && !empty($_POST['other_track_strand'])) {
                $finalTrackStrand = 'OTHER: ' . sanitizeInput($_POST['other_track_strand']);
            } else {
                $finalTrackStrand = sanitizeInput($trackStrand);
            }
        }

        $photoPath = null;
        if (!empty($_FILES['studentPhoto']['tmp_name'])) {
            $photoPath = uploadFile($_FILES['studentPhoto']);
        }

        $patientData = [
            'user_id' => $userId,
            'studentId' => $_POST['studentId'],
            'surname' => capitalizeFirst(sanitizeInput($_POST['surname'])),
            'firstname' => capitalizeFirst(sanitizeInput($_POST['firstname'])),
            'middlename' => !empty($_POST['middlename']) ? capitalizeFirst(sanitizeInput($_POST['middlename'])) : null,
            'suffix' => !empty($_POST['suffix']) ? sanitizeInput($_POST['suffix']) : null,
            'birthday' => sanitizeInput($_POST['birthday']),
            'age' => (int)$_POST['age'],
            'sex' => sanitizeInput($_POST['sex']),
            'blood_type' => !empty($_POST['bloodType']) ? sanitizeInput($_POST['bloodType']) : null,
            'religion' => $finalReligion,
            'nationality' => sanitizeInput($_POST['nationality']),
            'civil_status' => sanitizeInput($_POST['civilStatus']),
            'email' => strtolower(filter_var($_POST['email'], FILTER_SANITIZE_EMAIL)),
            'contact_number' => preg_replace('/[^0-9]/', '', $_POST['contactNumber']),
            'city_address' => capitalizeFirst(sanitizeInput($_POST['cityAddress'])),
            'provincial_address' => !empty($_POST['provincialAddress']) ? capitalizeFirst(sanitizeInput($_POST['provincialAddress'])) : null,
            'photo_path' => $photoPath,
            'grade_level' => null,
            'grading_quarter' => null,
            'track_strand' => null,
            'section' => null,
            'semester' => null,
            'department' => null,
            'course' => null,
            'year_level' => null,
            'position' => null,
            'employee_id' => null,
            'id_path' => null,
            'cor_path' => null
        ];

        if ($formUserType === 'high_school') {
            $patientData['grade_level'] = 'Grade ' . sanitizeInput($_POST['Grades']);
            $patientData['grading_quarter'] = sanitizeInput($_POST['gradeLevel']);
        } elseif ($formUserType === 'senior_high') {
            $patientData['grade_level'] = 'Grade ' . sanitizeInput($_POST['Grades']);
            $patientData['track_strand'] = $finalTrackStrand;
            $patientData['section'] = sanitizeInput($_POST['section']);
            $patientData['semester'] = sanitizeInput($_POST['Sem']);
        } elseif ($formUserType === 'college') {
            $patientData['department'] = sanitizeInput($_POST['department']);
            $patientData['course'] = sanitizeInput($_POST['course']);
            $patientData['semester'] = sanitizeInput($_POST['Sem']);
            $patientData['year_level'] = (int)$_POST['yearLevel'];
        }
        // No additional fields for 'employee' or 'incoming_freshman'

        // Notice the 30th ? added to VALUES and extra 's' in type string
        $stmt = $conn->prepare("INSERT INTO patients (

        user_id, student_id, surname, 
        firstname, middlename, suffix, 
        birthday, age,   sex, 
        blood_type, religion, nationality, 
        civil_status, email,  contact_number, 
        city_address, provincial_address,  photo_path,
        grade_level, grading_quarter, track_strand,
         section, semester,  department, 
         course, year_level, position,
          employee_id, id_path, cor_path
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param(
            "issssssis" . "ssssssssssssssss" . "issss",
            $patientData['user_id'],
            $patientData['studentId'],
            $patientData['surname'],
            $patientData['firstname'],
            $patientData['middlename'],
            $patientData['suffix'],
            $patientData['birthday'],
            $patientData['age'],
            $patientData['sex'],
            $patientData['blood_type'],
            $patientData['religion'],
            $patientData['nationality'],
            $patientData['civil_status'],
            $patientData['email'],
            $patientData['contact_number'],
            $patientData['city_address'],
            $patientData['provincial_address'],
            $patientData['photo_path'],
            $patientData['grade_level'],
            $patientData['grading_quarter'],
            $patientData['track_strand'],
            $patientData['section'],
            $patientData['semester'],
            $patientData['department'],
            $patientData['course'],
            $patientData['year_level'],
            $patientData['position'],
            $patientData['employee_id'],
            $patientData['id_path'],
            $patientData['cor_path']
        );

        if (!$stmt->execute()) {
            error_log("SQL Error: " . $stmt->error);
            throw new Exception("Patient insert failed: " . $stmt->error);
        }
        $patientId = $conn->insert_id;
        $stmt->close();

        // Handle emergency relationship
        $relationship = $_POST['emergencyRelationship'] ?? '';
        if ($relationship === 'Other' && !empty($_POST['other_relationship'])) {
            $finalRelationship = 'Other: ' . sanitizeInput($_POST['other_relationship']);
        } else {
            $finalRelationship = sanitizeInput($relationship);
        }

        $emergencyData = [
            'patient_id' => $patientId,
            'surname' => capitalizeFirst(sanitizeInput($_POST['emergencySurname'])),
            'firstname' => capitalizeFirst(sanitizeInput($_POST['emergencyFirstname'])),
            'middlename' => !empty($_POST['emergencyMiddlename']) ? capitalizeFirst(sanitizeInput($_POST['emergencyMiddlename'])) : null,
            'contact_number' => preg_replace('/[^0-9]/', '', $_POST['emergencyContactNumber']),
            'relationship' => $finalRelationship,
            'city_address' => capitalizeFirst(sanitizeInput($_POST['emergencyCityAddress']))
        ];

        $emergencyStmt = $conn->prepare("INSERT INTO emergency_contacts (
            patient_id, surname, firstname, middlename, contact_number, relationship, city_address
        ) VALUES (?, ?, ?, ?, ?, ?, ?)");

        $emergencyStmt->bind_param(
            "issssss",
            $emergencyData['patient_id'],
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
        $emergencyStmt->close();

        $illnesses = isset($_POST['illness']) ? (array)$_POST['illness'] : [];

        // Psychiatric illnesses (multiple checkboxes)
        if (in_array('psychiatric', $illnesses) && isset($_POST['psychiatric'])) {
            $psychiatricIllnesses = array_map('sanitizeInput', (array)$_POST['psychiatric']);
            $illnesses = array_diff($illnesses, ['psychiatric']);
            $illnesses = array_merge($illnesses, $psychiatricIllnesses);
        }

        // Other mental illness (free text)
        if (in_array('psychiatric', $illnesses) && !empty($_POST['other_mental_illness'])) {
            $psychiatric_illnesses_details = 'Other Mental Illness: ' . sanitizeInput($_POST['other_mental_illness']);
            $illnesses = array_diff($illnesses, ['psychiatric_illnesses_details']);
            $illnesses[] = $psychiatric_illnesses_details;
        }

        // Cancer with details
        if (in_array('cancer_specify', $illnesses) && !empty($_POST['cancer_details'])) {
            $cancer_details = 'Cancer: ' . sanitizeInput($_POST['cancer_details']);
            $illnesses = array_diff($illnesses, ['cancer_specify']);
            $illnesses[] = $cancer_details;
        }

        // Allergies with details
        if (in_array('allergies', $illnesses) && !empty($_POST['allergiesSpecify'])) {
            $allergySpec = 'Allergies: ' . sanitizeInput($_POST['allergiesSpecify']);
            $illnesses = array_diff($illnesses, ['allergies']);
            $illnesses[] = $allergySpec;
        }

        // Other illnesses (free text)
        if (in_array('other', $illnesses) && !empty($_POST['otherIllness'])) {
            $otherIllness = 'Other: ' . sanitizeInput($_POST['otherIllness']);
            $illnesses = array_diff($illnesses, ['other']);
            $illnesses[] = $otherIllness;
        }

        $family_history = isset($_POST['family_history']) ? (array)$_POST['family_history'] : [];

        // Psychiatric illnesses (checkbox group)
        if (in_array('psychiatric', $family_history) && isset($_POST['psychiatric'])) {
            $psychiatricIllnesses = array_map('sanitizeInput', (array)$_POST['psychiatric']);
            $family_history = array_diff($family_history, ['family_history_psychiatric']);
            $family_history = array_merge($family_history, $psychiatricIllnesses);
        }




        // Other mental illness (free text)
        if (in_array('psychiatric', $family_history) && !empty($_POST['family_other_mental_illness'])) {
            $psychiatric_illnesses_details_family = 'Other Mental Illness (Family):' . sanitizeInput($_POST['family_other_mental_illness']);
            $family_history = array_diff($family_history, ['family_other_mental_illness']);
            $family_history[] = $psychiatric_illnesses_details_family;
            echo "yes";
        }

        // Cancer with details
        if (in_array('cancer_specify_family', $family_history) && !empty($_POST['family_cancer_details'])) {
            $cancer_details = 'Cancer:' . sanitizeInput($_POST['family_cancer_details']);
            $family_history = array_diff($family_history, ['cancer_specify_family']);
            $family_history[] = $cancer_details;
        }

        // Food allergies with details
        if (in_array('familyFoodAllergies', $family_history) && !empty($_POST['allergiesSpecifyFamily'])) {
            $allergySpecFamily = 'Family Allergies:' . sanitizeInput($_POST['allergiesSpecifyFamily']);
            $family_history = array_diff($family_history, ['familyFoodAllergies']);
            $family_history[] = $allergySpecFamily;
        }

        // Other illness (free text)
        if (in_array('other', $family_history) && !empty($_POST['otherIllness'])) {
            $otherIllness = 'Other (Family):' . sanitizeInput($_POST['otherIllness']);
            $family_history = array_diff($family_history, ['other']);
            $family_history[] = $otherIllness;
        }
        //

    // Get base illnesses
$past_illnesses = isset($_POST['past_illness']) ? array_map('sanitizeInput', (array)$_POST['past_illness']) : [];

// Handle psychiatric illness details
if (in_array('psychiatric', $past_illnesses) && !empty($_POST['psychiatric'])) {
    $psychiatric = array_map('sanitizeInput', (array)$_POST['psychiatric']);
    // Replace "psychiatric" with detailed values
    $past_illnesses = array_diff($past_illnesses, ['psychiatric']);
    $past_illnesses = array_merge($past_illnesses, $psychiatric);
}

// Handle "Other" illness
$other_illness = isset($_POST['past_illness_other']) ? trim($_POST['past_illness_other']) : "";
if (!empty($other_illness)) {
    $past_illnesses[] = "Other: " . sanitizeInput($other_illness);
}

// Ready for DB
$illnesses_str = implode(", ", $past_illnesses);



        $medicationsStr = null;
        if (isset($_POST['medications']) && is_array($_POST['medications'])) {
            $medications = [];
            foreach ($_POST['medications'] as $med) {
                $drug = $med['drug'] === 'other' && !empty($med['drug_other']) ? sanitizeInput($med['drug_other']) : sanitizeInput($med['drug']);
                if (!empty($drug) && !empty($med['dose']) && !empty($med['unit']) && !empty($med['frequency'])) {
                    $medStr = sprintf(
                        "%s:%s:%s:%s",
                        $drug,
                        sanitizeInput($med['dose']),
                        sanitizeInput($med['unit']),
                        sanitizeInput($med['frequency'])
                    );
                    $medications[] = $medStr;
                }
            }
            $medicationsStr = !empty($medications) ? implode(',', $medications) : null;
        }

        $hospitalAdmissionsStr = null;
        if (isset($_POST['hospital_admission']) && $_POST['hospital_admission'] === 'Yes' && isset($_POST['hospital_admissions'])) {
            $admissions = [];
            foreach ($_POST['hospital_admissions'] as $admission) {
                if (!empty($admission['year']) && !empty($admission['reason'])) {
                    $admStr = sprintf(
                        "%s:%s",
                        sanitizeInput($admission['year']),
                        sanitizeInput($admission['reason'])
                    );
                    $admissions[] = $admStr;
                }
            }
            $hospitalAdmissionsStr = !empty($admissions) ? implode(',', $admissions) : null;
        }
        $menstrualSymptoms = [];

        if (!empty($_POST['symptoms'])) {
            $menstrualSymptoms = array_map('sanitizeInput', (array)$_POST['symptoms']);
        }

        // Check if "otherSymptoms" has text
        if (!empty($_POST['otherSymptoms'])) {
            $menstrualSymptoms[] = 'Other: ' . sanitizeInput($_POST['otherSymptoms']);
        }

        // Build menstrual symptoms array
        $menstrualSymptoms = isset($_POST['symptoms'])
            ? array_map('sanitizeInput', (array)$_POST['symptoms'])
            : [];

        // If "other" is checked, append the text input
        if (in_array('other', $menstrualSymptoms) && !empty($_POST['otherSymptoms'])) {
            $menstrualSymptoms[] = 'other: ' . sanitizeInput($_POST['otherSymptoms']);
        }



        $medicalData = [
            'patient_id' => $patientId,
            'illnesses' => !empty($illnesses) ? implode(",", array_map('sanitizeInput', $illnesses)) : null,
            'medications' => $medicationsStr,
            'vaccination_status' => !empty($_POST['vaccination']) ? sanitizeInput($_POST['vaccination']) : null,
            'menstruation_age' => isset($_POST['menstruationAge']) ? (int)$_POST['menstruationAge'] : null,
            'menstrual_pattern' => !empty($_POST['menstrualPattern']) ? sanitizeInput($_POST['menstrualPattern']) : null,
            'pregnancies' => isset($_POST['pregnancies']) ? (int)$_POST['pregnancies'] : null,
            'live_children' => isset($_POST['liveChildren']) ? (int)$_POST['liveChildren'] : null,
            'menstrual_symptoms' => !empty($menstrualSymptoms) ? implode(",", $menstrualSymptoms) : null,
        'past_illnesses' => !empty($illnesses_str) ? $illnesses_str : null,
            'hospital_admissions' => $hospitalAdmissionsStr,
            'family_history' => !empty($family_history) ? implode(",", array_map('sanitizeInput', $family_history)) : null,
            'other_conditions' => !empty($_POST['other_family_history']) ? sanitizeInput($_POST['other_family_history']) : null
        ];

        $medicalStmt = $conn->prepare("INSERT INTO medical_info (
            patient_id, illnesses, medications, vaccination_status, menstruation_age, 
            menstrual_pattern, pregnancies, live_children, menstrual_symptoms, 
            past_illnesses, hospital_admissions, family_history, other_conditions
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $medicalStmt->bind_param(
            "isssissssssss",
            $medicalData['patient_id'],
            $medicalData['illnesses'],
            $medicalData['medications'],
            $medicalData['vaccination_status'],
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
        $medicalStmt->close();

        $updateStmt = $conn->prepare("UPDATE users SET profile_submitted = 1 WHERE id = ?");
        $updateStmt->bind_param("i", $userId);
        if (!$updateStmt->execute()) {
            throw new Exception("User update failed: " . $updateStmt->error);
        }
        $updateStmt->close();

        // 1. Get all IDs for the target user types
        $adminQuery = $conn->prepare("
    SELECT id 
    FROM users 
    WHERE user_type IN ('Super Admin', 'Medical Admin', 'Dental Admin')
");
        $adminQuery->execute();
        $adminResult = $adminQuery->get_result();

        // 2. Build notification data
        $notificationTitle = "New Health Profile Submission!";
        $notificationDescription = "{$patientData['firstname']} {$patientData['surname']} ({$userType}) has submitted their health profile";
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
        error_log("Health profile submitted successfully for user_id: $userId, redirecting to /uploaddocs");
        echo json_encode(['success' => 'Health profile submitted successfully. Please upload your documents.']);
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Form submission error: UserID=$userId, Error=" . $e->getMessage());
        echo json_encode(['error' => 'An error occurred: ' . $e->getMessage()]);
        exit();
    }
}
file_put_contents('debug_post.txt', print_r($_POST, true));
file_put_contents('debug_files.txt', print_r($_FILES, true));

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PATIENT HEALTH PROFILE</title>
    <link rel="stylesheet" href="css/profiles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="apple-touch-icon" sizes="180x180" href="images/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="images/favicon-16x16.png">
    <link rel="manifest" href="images/site.webmanifest">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                <div class="modal-body">
                    <?php echo isset($_SESSION['error']) ? htmlspecialchars($_SESSION['error']) : ''; ?>
                </div>
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

    <!-- Account Activation Success Modal -->
    <div class="modal fade" id="signupSuccessModal" tabindex="-1" aria-labelledby="signupSuccessLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="signupSuccessLabel">Account Activated!</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Your account has been successfully activated.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">Continue</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Verification Failed Modal -->
    <div class="modal fade" id="verificationFailedModal" tabindex="-1" aria-labelledby="verificationFailedLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #990000; color: white;">
                    <h5 class="modal-title" id="verificationFailedLabel">Verification Failed</h5>
                </div>
                <div class="modal-body">
                    <?php echo isset($_SESSION['STATUS_MESSAGE']) ? htmlspecialchars($_SESSION['STATUS_MESSAGE']) : 'Something went wrong.'; ?>
                </div>
                <div class="modal-footer">
                    <a href="signup.php" class="btn btn-danger">Back to Sign Up</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Verification Failed Modal -->
    <div class="modal fade" id="verificationFailedModal" tabindex="-1" aria-labelledby="verificationFailedLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success">
                    <h5 class="modal-title" id="verificationFailedLabel">Email already verified!</h5>
                </div>
                <div class="modal-body">
                    <?php echo isset($_SESSION['STATUS_MESSAGE']) ? htmlspecialchars($_SESSION['STATUS_MESSAGE']) : 'Something went wrong.'; ?>
                </div>
                <div class="modal-footer">
                    <a href="signup.php" class="btn btn-danger">Back to Sign Up</a>
                </div>
            </div>
        </div>
    </div>






    <div class="mainContainer">
        <form class="health-profile-form" id="healthProfileForm" action="form.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="user_type" value="<?= htmlspecialchars($formUserType) ?>">
            <div class="form-header">
                <h1>PATIENT HEALTH PROFILE</h1>
            </div>

            <div id="upperSections">
                <fieldset class="form-section">
                    <legend>Personal Information</legend>
                    <div class="photo-upload-section">
                        <label for="studentPhoto">Photo of Student</label>
                        <input type="file" id="studentPhoto" name="studentPhoto"
                            accept="image/*" hidden onchange="displayImage(this, 'previewImage')">
                        <div class="upload-box"
                            onclick="document.getElementById('studentPhoto').click()">
                            <img id="previewImage" src="#" alt="Preview" style="display: none; width: 100%; height: 100%; object-fit: cover; position: absolute; top: 0; left: 0;">
                            <span id="uploadText">+</span>
                        </div>
                    </div>

                    <div class="personal-info-grid">
                        <div class="form-group full-width">
                            <label>Name:</label>
                            <div class="name-inputs">
                                <div class="input-wrapper">
                                    <input type="text" placeholder="Surname" name="surname" value="<?= htmlspecialchars($userSurname) ?>" required>
                                    <div class="invalid-feedback">Please enter the surname</div>
                                </div>
                                <div class="input-wrapper">
                                    <input type="text" placeholder="First name" name="firstname" value="<?= htmlspecialchars($userFirstname) ?>" required>
                                    <div class="invalid-feedback">Please enter the first name</div>
                                </div>
                                <div class="input-wrapper">
                                    <input type="text" placeholder="Middle name" name="middlename" value="<?= htmlspecialchars($userMiddlename) ?>">
                                    <div class="invalid-feedback">Please enter a valid middle name</div>
                                </div>
                                <div class="input-wrapper">
                                    <input type="text" placeholder="Suffix" name="suffix">
                                    <div class="invalid-feedback">Please enter a valid suffix</div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="input-wrapper">
                                <label for="birthday">Birthday:</label>
                                <input type="date" id="birthday" name="birthday" required onchange="calculateAge()">
                                <div class="invalid-feedback">Please select a valid birthday</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="input-wrapper">
                                <label for="age">Age:</label>
                                <input type="number" id="age" name="age" class="age-input" readonly>
                                <div class="invalid-feedback">Age will be calculated automatically</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="input-wrapper">
                                <label for="sex">Sex:</label>
                                <select id="sex" name="sex" required onchange="toggleMenstrualSection()">
                                    <option value="">Select</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                                <div class="invalid-feedback">Please select a gender</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="input-wrapper">
                                <label for="bloodType">Blood Type:</label>
                                <select id="bloodType" name="bloodType">
                                    <option value="">Select</option>
                                    <option value="A+">A+</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B-">B-</option>
                                    <option value="AB+">AB+</option>
                                    <option value="AB-">AB-</option>
                                    <option value="O+">O+</option>
                                    <option value="O-">O-</option>
                                    <option value="unknown">Unknown</option>
                                </select>
                                <div class="invalid-feedback">Please select a blood type</div>
                            </div>
                        </div>

                        <?php if ($formUserType === 'high_school'): ?>
                            <div class="form-group">
                                <div class="input-wrapper">
                                    <label for="Grades">Grade:</label>
                                    <select id="Grades" name="Grades" required>
                                        <option value="">Select</option>
                                        <option value="7">Grade 7</option>
                                        <option value="8">Grade 8</option>
                                        <option value="9">Grade 9</option>
                                        <option value="10">Grade 10</option>
                                    </select>
                                    <div class="invalid-feedback">Please select a grade</div>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="input-wrapper">
                                    <label for="gradeLevel">Grading/Quarter:</label>
                                    <select id="gradeLevel" name="gradeLevel" required>
                                        <option value="">Select</option>
                                        <option value="1">1</option>
                                        <option value="2">2</option>
                                        <option value="3">3</option>
                                        <option value="4">4</option>
                                    </select>
                                    <div class="invalid-feedback">Please select a grading quarter</div>
                                </div>
                            </div>
                        <?php elseif ($formUserType === 'senior_high'): ?>
                            <div class="form-group">
                                <div class="input-wrapper">
                                    <label for="Grades">Grade:</label>
                                    <select id="Grades" name="Grades" required>
                                        <option value="">Select</option>
                                        <option value="11">Grade 11</option>
                                        <option value="12">Grade 12</option>
                                    </select>
                                    <div class="invalid-feedback">Please select a grade</div>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="input-wrapper">
                                    <label for="Track/Strand">Track/Strand:</label>
                                    <select id="Track/Strand" name="Track/Strand" required onchange="toggleOtherTrackStrandInput()">
                                        <option value="">Select Track/Strand</option>
                                        <optgroup label="Academic Track">
                                            <option value="GAS">General Academic Strand (GAS)</option>
                                            <option value="HUMSS">Humanities and Social Sciences Strand (HUMSS)</option>
                                            <option value="STEM">Science, Technology, Engineering, and Mathematics Strand (STEM)</option>
                                            <option value="ABM">Accountancy, Business and Management Strand (ABM)</option>
                                        </optgroup>
                                        <optgroup label="TVL Track">
                                            <option value="TVL-ICT">Information and Communication Technology (ICT)</option>
                                            <option value="TVL-HE">Home Economics (HE)</option>
                                            <option value="TVL-IA">Industrial Arts (IA)</option>
                                            <option value="TVL-AFA">Agri-Fishery Arts (AFA)</option>
                                        </optgroup>
                                        <option value="SPORTS">Sports Track</option>
                                        <option value="ARTS-DESIGN">Arts and Design Track</option>
                                        <option value="OTHER">Others (Please specify)</option>
                                    </select>
                                    <div id="otherTrackStrandWrapper" style="display: none; margin-top: 10px;">
                                        <input type="text" id="otherTrackStrand" name="other_track_strand" placeholder="Please specify Track/Strand" class="form-control">
                                    </div>
                                    <div class="invalid-feedback">Please select or specify your Track/Strand</div>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="input-wrapper">
                                    <label for="section">Section:</label>
                                    <input type="text" id="section" name="section" required>
                                    <div class="invalid-feedback">Please enter the section</div>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="input-wrapper">
                                    <label for="Sem">Semester:</label>
                                    <select id="Sem" name="Sem" required>
                                        <option value="">Select</option>
                                        <option value="Midterm">First Semester</option>
                                        <option value="Finals">Second Semester</option>
                                    </select>
                                    <div class="invalid-feedback">Please select a semester</div>
                                </div>
                            </div>
                        <?php elseif ($formUserType === 'college'): ?>
                            <div class="form-group">
                                <div class="input-wrapper">
                                    <label for="department">College:</label>
                                    <select id="department" name="department" required onchange="updateCourseDropdown()">
                                        <option value="">Select College</option>
                                        <option value="CLA">College of Liberal Arts (CLA)</option>
                                        <option value="CSM">College of Science and Mathematics (CSM)</option>
                                        <option value="COE">College of Engineering (COE)</option>
                                        <option value="CTE">College of Teacher Education (CTE)</option>
                                        <option value="COA">College of Architecture (COA)</option>
                                        <option value="CON">College of Nursing (CON)</option>
                                        <option value="CA">College of Agriculture (CA)</option>
                                        <option value="CFES">College of Forestry and Environmental Studies (CFES)</option>
                                        <option value="CCJE">College of Criminal Justice Education (CCJE)</option>
                                        <option value="CHE">College of Home Economics (CHE)</option>
                                        <option value="CCS">College of Computing Studies (CCS)</option>
                                        <option value="COM">College of Medicine (COM)</option>
                                        <option value="CPADS">College of Public Administration and Development Studies (CPADS)</option>
                                        <option value="CSSPE">College of Sports Science and Physical Education (CSSPE)</option>
                                        <option value="CSWCD">College of Social Work and Community Development (CSWCD)</option>
                                        <option value="CAIS">College of Asian and Islamic Studies (CAIS)</option>
                                    </select>
                                    <div class="invalid-feedback">Please select a college</div>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="input-wrapper">
                                    <label for="course">Course:</label>
                                    <select id="course" name="course" required>
                                        <option value="">Select College First</option>
                                    </select>
                                    <div class="invalid-feedback">Please select a course</div>
                                </div>
                            </div>
                            <div class "form-group">
                                <div class="input-wrapper">
                                    <label for="Sem">Semester:</label>
                                    <select id="Sem" name="Sem" required>
                                        <option value="">Select</option>
                                        <option value="Midterm">First Semester</option>
                                        <option value="Finals">Second Semester</option>
                                    </select>
                                    <div class="invalid-feedback">Please select a semester</div>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="input-wrapper">
                                    <label for="yearLevel">Year Level:</label>
                                    <input type="number" id="yearLevel" name="yearLevel" min="1" max="5" required>
                                    <div class="invalid-feedback">Please enter the year level</div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="form-group">
                            <div class="input-wrapper">
                                <label for="religion">Religion:</label>
                                <select id="religion" name="religion" required onchange="toggleOtherReligionInput()">
                                    <option value="">Select Religion</option>
                                    <option value="Roman Catholic">Roman Catholic</option>
                                    <option value="Islam">Islam</option>
                                    <option value="Iglesia ni Cristo">Iglesia ni Cristo</option>
                                    <option value="Protestant">Protestant</option>
                                    <option value="Born Again Christian">Born Again Christian</option>
                                    <option value="Seventh-day Adventist">Seventh-day Adventist</option>
                                    <option value="Jehovah's Witness">Jehovah's Witness</option>
                                    <option value="Buddhist">Buddhist</option>
                                    <option value="OTHER">Others (Please specify)</option>
                                </select>
                                <div id="otherReligionWrapper" style="display: none; margin-top: 10px;">
                                    <input type="text" id="otherReligion" name="other_religion" placeholder="Please specify religion" class="form-control">
                                </div>
                                <div class="invalid-feedback">Please select or specify your religion</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="input-wrapper">
                                <label for="nationality">Nationality:</label>
                                <input type="text" id="nationality" name="nationality" value="Filipino" required>
                                <div class="invalid-feedback">Please enter the nationality</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="input-wrapper">
                                <label for="civilStatus">Civil Status:</label>
                                <select id="civilStatus" name="civilStatus" required>
                                    <option value="">Select</option>
                                    <option value="single">Single</option>
                                    <option value="married">Married</option>
                                    <option value="widowed">Widowed</option>
                                    <option value="divorced">Divorced</option>
                                </select>
                                <div class="invalid-feedback">Please select a civil status</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="input-wrapper">
                                <label for="email">Email Address:</label>
                                <input type="email" id="email" name="email"
                                    value="<?= htmlspecialchars($userEmail) ?>"
                                    oninput="formatEmailInput(this)"
                                    onkeydown="return preventCapitalization(event)"
                                    required>
                                <div class="invalid-feedback">Please enter a valid email address</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="input-wrapper">
                                <label for="contactNumber">Contact Number:</label>
                                <input type="tel" id="contactNumber" name="contactNumber" pattern="09[0-9]{9}" maxlength="11" oninput="validatePhoneNumber(this)" required>
                                <small class="form-text text-muted">Format: 09XXXXXXXXX (11 digits)</small>
                                <div class="invalid-feedback">Please enter a valid 11-digit contact number starting with 09</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="input-wrapper">
                                <label for="studentId">Student ID:</label>
                                <input type="text" id="studentId" name="studentId" required value="N/A">
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>

                        <div class="form-group full-width">
                            <div class="input-wrapper">
                                <label for="cityAddress">City Address:</label>
                                <input type="text" id="cityAddress" name="cityAddress" required>
                                <div class="invalid-feedback">Please enter the city address</div>
                            </div>
                        </div>

                        <div class="form-group full-width">
                            <div class="input-wrapper">
                                <label for="provincialAddress">Provincial Address (if applicable):</label>
                                <input type="text" id="provincialAddress" name="provincialAddress">
                                <div class="invalid-feedback">Please enter a valid provincial address</div>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend>Emergency Contact Person (within Zamboanga City)</legend>
                    <div class="emergency-contact-grid">
                        <div class="form-group full-width">
                            <label>Name:</label>
                            <div class="name-inputs">
                                <div class="input-wrapper">
                                    <input type="text" placeholder="Surname" name="emergencySurname" required>
                                    <div class="invalid-feedback">Please enter the emergency contact surname</div>
                                </div>
                                <div class="input-wrapper">
                                    <input type="text" placeholder="First name" name="emergencyFirstname" required>
                                    <div class="invalid-feedback">Please enter the emergency contact first name</div>
                                </div>
                                <div class="input-wrapper">
                                    <input type="text" placeholder="Middle name" name="emergencyMiddlename">
                                    <div class="invalid-feedback">Please enter a valid emergency contact middle name</div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="input-wrapper">
                                <label for="emergencyContactNumber">Contact Number:</label>
                                <input type="tel" id="emergencyContactNumber" name="emergencyContactNumber" pattern="09[0-9]{9}" maxlength="11" oninput="validatePhoneNumber(this)" required>
                                <small class="form-text text-muted">Format: 09XXXXXXXXX (11 digits)</small>
                                <div class="invalid-feedback">Please enter a valid 11-digit emergency contact number starting with 09</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="input-wrapper">
                                <label for="emergencyRelationship">Relationship:</label>
                                <select id="emergencyRelationship" name="emergencyRelationship" required onchange="toggleOtherRelationshipInput()">
                                    <option value="">Select Relationship</option>
                                    <option value="Parent">Parent</option>
                                    <option value="Sibling">Sibling</option>
                                    <option value="Spouse">Spouse</option>
                                    <option value="Child">Child</option>
                                    <option value="Guardian">Guardian</option>
                                    <option value="Friend">Friend</option>
                                    <option value="Other">Other</option>
                                </select>
                                <div id="otherRelationshipWrapper" style="display: none; margin-top: 10px;">
                                    <input type="text" id="otherRelationship" name="other_relationship" placeholder="Please specify relationship" class="form-control">
                                </div>
                                <div class="invalid-feedback">Please select or specify a relationship</div>
                            </div>
                        </div>

                        <div class="form-group full-width">
                            <div class="input-wrapper">
                                <label for="emergencyCityAddress">City Address:</label>
                                <input type="text" id="emergencyCityAddress" name="emergencyCityAddress" required>
                                <div class="invalid-feedback">Please enter the emergency contact city address</div>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <div class="form-navigation">
                    <button type="button" class="next-btn btn btn-primary" onclick="nextStep()">Next</button>
                </div>
            </div>

            <div class="form-step" id="step2" style="display: none;">
                <fieldset class="form-section">
                    <legend>Comorbid Illnesses</legend>
                    <p class="form-question">Which of these conditions do you currently have?</p>
                    <div class="checkbox-grid">
                        <div class="checkbox-column">
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="asthma">
                                Bronchial Asthma ("Hika")
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="allergies">
                                Food Allergies
                                <input type="text" placeholder="Specify food" name="allergiesSpecify" class="inline-input">
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="rhinitis">
                                Allergic Rhinitis
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="hyperthyroidism">
                                Hyperthyroidism
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="hypothyroidism">
                                Hypothyroidism/Goiter
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="anemia">
                                Anemia
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="migraine">
                                Migraine (recurrent headaches)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="epilepsy">
                                Epilepsy/Seizures
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="gerd">
                                Gastroesophageal Reflux Disease (GERD)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="bowel_syndrome">
                                Irritable Bowel Syndrome
                            </label>
                        </div>

                        <div class="checkbox-column">
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="psychiatric">
                                Psychiatric Illness:
                                <div class="nested-checkboxes">
                                    <label class="checkbox-label nested">
                                        <input type="checkbox" name="illness[]" value="depression">
                                        Major Depressive Disorder
                                    </label>
                                    <label class="checkbox-label nested">
                                        <input type="checkbox" name="illness[]" value="bipolar">
                                        Bipolar Disorder
                                    </label>
                                    <label class="checkbox-label nested">
                                        <input type="checkbox" name="illness[]" value="anxiety">
                                        Generalized Anxiety Disorder
                                    </label>
                                    <label class="checkbox-label nested">
                                        <input type="checkbox" name="illness[]" value="panic">
                                        Panic Disorder
                                    </label>
                                    <label class="checkbox-label nested">
                                        <input type="checkbox" name="illness[]" value="ptsd">
                                        Posttraumatic Stress Disorder
                                    </label>
                                    <label class="checkbox-label nested">
                                        <input type="checkbox" name="illness[]" value="schizophrenia">
                                        Schizophrenia
                                    </label>

                                    Other:
                                    <input type="text" placeholder="Specify other mental illness" name="other_mental_illness" class="inline-input">
                                </div>
                            </label>

                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="lupus">
                                Systemic Lupus Erythematosus (SLE)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="hypertension">
                                Hypertension (elevated blood pressure)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="diabetes">
                                Diabetes mellitus (elevated blood sugar)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="dyslipidemia">
                                Dyslipidemia (elevated cholesterol levels)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="arthritis">
                                Arthritis (joint pains)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="sle">
                                Systemic Lupus Erythematosus (SLE)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="pcos">
                                Polycystic Ovarian Syndrome (PCOS)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="cancer_specify" onchange="toggleExtra(this, 'cancer-input')">
                                Cancer
                            </label>

                            <div id="cancer-input" class="extra-input">
                                <input type="text" name="cancer_details" placeholder="Please specify..." />
                            </div>

                            <script>
                                function toggleExtra(checkbox, inputId) {
                                    let extra = document.getElementById(inputId);
                                    if (checkbox.checked) {
                                        extra.style.display = "block";
                                    } else {
                                        extra.style.display = "none";
                                    }
                                }
                            </script>
                            <br>
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="other">
                                Other:
                                <input type="text" placeholder="Specify if there is" name="otherIllness" class="inline-input">
                            </label>
                        </div>
                    </div>

                </fieldset>

                <fieldset class="form-section">
                    <legend>Maintenance Medications</legend>
                    <table class="medications-table" id="medicationsTable">
                        <thead>
                            <tr>
                                <th>Generic Name of Drug</th>
                                <th>Dose and Frequency</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <select class="table-input drug-select" name="medications[0][drug]" onchange="handleDrugSelect(this)">
                                        <option value="">Select a drug</option>
                                        <option value="paracetamol">Paracetamol</option>
                                        <option value="ibuprofen">Ibuprofen</option>
                                        <option value="amoxicillin">Amoxicillin</option>
                                        <option value="metformin">Metformin</option>
                                        <option value="atorvastatin">Atorvastatin</option>
                                        <option value="losartan">Losartan</option>
                                        <option value="omeprazole">Omeprazole</option>
                                        <option value="simvastatin">Simvastatin</option>
                                        <option value="aspirin">Aspirin</option>
                                        <option value="levothyroxine">Levothyroxine</option>
                                        <option value="other">Other</option>
                                    </select>
                                    <input type="text" class="table-input other-input" name="medications[0][drug_other]" placeholder="Enter drug name" style="display: none;">
                                </td>
                                <td>
                                    <div class="dose-options">
                                        <input type="number" class="table-input" name="medications[0][dose]" placeholder="Dose" style="width: 80px;">
                                        <select class="table-input" name="medications[0][unit]">
                                            <option value="mg">mg</option>
                                            <option value="g">g</option>
                                            <option value="ml">ml</option>
                                            <option value="units">units</option>
                                        </select>
                                        <select class="table-input" name="medications[0][frequency]">
                                            <option value="">Select Frequency</option>
                                            <option value="once daily">Once daily</option>
                                            <option value="twice daily">Twice daily</option>
                                            <option value="three times daily">Three times daily</option>
                                            <option value="four times daily">Four times daily</option>
                                            <option value="as needed">As needed</option>
                                        </select>
                                    </div>
                                </td>
                                <td>
                                    <button type="button" class="remove-btn" onclick="removeMedicationRow(this)">×</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <button type="button" class="add-btn btn btn-secondary" onclick="addMedicationRow()">+ Add Medication</button>
                </fieldset>

                <fieldset class="form-section">
                    <legend>COVID Vaccination</legend>
                    <div class="radio-group" id="vaccinationGroup">
                        <label class="radio-label">
                            <input type="radio" name="vaccination" value="fully" required>
                            Fully vaccinated (Primary series with or without booster shot/s)
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="vaccination" value="partially">
                            Partially vaccinated (Incomplete primary series)
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="vaccination" value="not">
                            Not vaccinated
                        </label>
                        <div class="invalid-feedback" style="display: none;">Please select a vaccination status</div>
                    </div>
                </fieldset>

                <fieldset class="form-section menstrual-section" id="menstrualSection" style="display: none;">
                    <legend>Menstrual & Obstetric History</legend>
                    <p class="form-subtitle">(for females only)</p>
                    <div class="menstrual-grid">
                        <div class="form-group">
                            <label>Age when menstruation began:</label>
                            <input type="number" name="menstruationAge" class="short-input">
                        </div>
                        <div class="form-group">
                            <label>Menstrual Pattern:</label>
                            <div class="radio-group">
                                <label class="radio-label">
                                    <input type="radio" name="menstrualPattern" value="regular">
                                    Regular (monthly)
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="menstrualPattern" value="irregular">
                                    Irregular
                                </label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Number of pregnancies:</label>
                            <input type="number" name="pregnancies" class="short-input">
                        </div>
                        <div class="form-group">
                            <label>Number of live children:</label>
                            <input type="number" name="liveChildren" class="short-input">
                        </div>
                        <div class="form-group">
                            <label>Menstrual Symptoms:</label>
                            <div class="checkbox-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="symptoms[]" value="dysmenorrhea">
                                    Dysmenorrhea (cramps)
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="symptoms[]" value="migraine">
                                    Migraine
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="symptoms[]" value="consciousness">
                                    Loss of consciousness
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="symptoms[]" value="other">
                                    Other:
                                    <input type="text" class="inline-input" name="otherSymptoms">
                                </label>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <div class="form-navigation">
                    <button type="button" class="prev-btn btn btn-secondary" onclick="prevStep()">Previous</button>
                    <button type="button" class="next-btn btn btn-primary" onclick="nextStep2()">Next</button>
                </div>
            </div>

            <div class="form-step" id="step3" style="display: none;">
                <fieldset class="form-section">
                    <legend>Past Medical & Surgical History</legend>
                    <label class="form-label">Which of these conditions have you had in the past?</label>
                    <div class="checkbox-grid">
                        <div class="checkbox-column">
                            <label class="checkbox-label">
                                <input type="checkbox" name="past_illness[]" value="varicella">
                                Varicella (Chicken Pox)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="past_illness[]" value="dengue">
                                Dengue
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="past_illness[]" value="tuberculosis">
                                Tuberculosis
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="past_illness[]" value="pneumonia">
                                Pneumonia
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="past_illness[]" value="uti">
                                Urinary Tract Infection
                            </label>
                        </div>
                        <div class="checkbox-column">
                            <label class="checkbox-label">
                                <input type="checkbox" name="past_illness[]" value="appendicitis">
                                Appendicitis
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="past_illness[]" value="cholecystitis">
                                Cholecystitis
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="past_illness[]" value="measles">
                                Measles
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="past_illness[]" value="typhoid fever">
                                Typhoid Fever
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="past_illness[]" value="amoebiasis">
                                Amoebiasis
                            </label>
                        </div>
                        <div class="checkbox-column">
                            <label class="checkbox-label">
                                <input type="checkbox" name="past_illness[]" value="kidney stones">
                                Kidney Stones
                            </label>

                            <div class="checkbox-column">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="past_illness[]" value="injury">
                                    Injury
                                    <div class="nested-checkboxes">
                                        <label class="checkbox-label nested">
                                            <input type="checkbox" name="past_illness[]" value="burn">
                                            Burn
                                        </label>
                                        <label class="checkbox-label nested">
                                            <input type="checkbox" name="past_illness[]" value="stab">
                                            Stab/Laceration
                                        </label>
                                        <label class="checkbox-label nested">
                                            <input type="checkbox" name="past_illness[]" value="fracture">
                                            Fracture
                                        </label>
                                    </div>
                                </label>



                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="otherPastIllnessCheckbox" name="past_illness[]" value="other" onclick="toggleOtherPastIllness()">
                            Other (Specify)
                        </label>
                        <input type="text" class="form-control" id="otherPastIllnessInput"
                            name="past_illness_other"
                            placeholder="Specify other illnesses"
                            style="display: none; width: 300px; margin-top: 5px;"
                            disabled>
                    </div>


                </fieldset>

                <fieldset class="form-section">
                    <legend>Hospital Admission / Surgery</legend>
                    <label class="form-label">Have you ever been admitted to the hospital and/or undergone surgery?</label>
                    <div class="radio-group">
                        <label class="radio-label">
                            <input type="radio" name="hospital_admission" value="No" checked onclick="toggleSurgeryFields(false)">
                            No
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="hospital_admission" value="Yes" onclick="toggleSurgeryFields(true)">
                            Yes
                        </label>
                    </div>

                    <div id="surgeryDetails" style="display: none; margin-top: 15px;">
                        <table class="medications-table" id="surgeryTable">
                            <thead>
                                <tr>
                                    <th>Year</th>
                                    <th>Reason</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <input type="number" class="table-input" name="hospital_admissions[0][year]" min="1900" max="2025" placeholder="e.g., 2015">
                                    </td>
                                    <td>
                                        <input type="text" class="table-input" name="hospital_admissions[0][reason]" placeholder="e.g., Appendectomy">
                                    </td>
                                    <td>
                                        <button type="button" class="remove-btn" onclick="removeSurgeryRow(this)">×</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <button type="button" class="add-btn btn btn-secondary" onclick="addSurgeryRow()">+ Add Admission/Surgery</button>
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend>Family Medical History</legend>
                    <label class="form-label">Indicate the known health conditions of your immediate family members:</label>
                    <div class="checkbox-grid">
                        <div class="checkbox-column">
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="asthma">
                                Bronchial Asthma ("Hika")
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="familyFoodAllergies">
                                Food Allergies
                                <input type="text" placeholder="Specify food" name="allergiesSpecifyFamily" class="inline-input">
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="rhinitis">
                                Allergic Rhinitis
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="hyperthyroidism">
                                Hyperthyroidism
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="hypothyroidism">
                                Hypothyroidism/Goiter
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="anemia">
                                Anemia
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="migraine">
                                Migraine (recurrent headaches)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="diabetes">
                                Diabetes mellitus (elevated blood sugar)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="stroke">
                                Stroke (Cerebrovascular Disease)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="heart_failure">
                                Congestive Heart Failure
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="coronary">
                                Coronary Artery Disease / Heart Disease
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="copd">
                                Chronic Obstructive Pulmonary Disease (COPD)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="chronic_kidney">
                                Chronic Kidney Disease (with/without regular Hemodialysis)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="epilepsy">
                                Epilepsy/Seizures
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="gerd">
                                Gastroesophageal Reflux Disease (GERD)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="bowel_syndrome">
                                Irritable Bowel Syndrome
                            </label>

                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="liver_disease">
                                Liver Disease (Hepatitis, Cirrhosis)
                            </label>
                        </div>

                        <div class="checkbox-column">
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="psychiatric">
                                Psychiatric Illness:
                                <div class="nested-checkboxes">
                                    <label class="checkbox-label nested">
                                        <input type="checkbox" name="family_history[]" value="depression">
                                        Major Depressive Disorder
                                    </label>
                                    <label class="checkbox-label nested">
                                        <input type="checkbox" name="family_history[]" value="bipolar">
                                        Bipolar Disorder
                                    </label>
                                    <label class="checkbox-label nested">
                                        <input type="checkbox" name="family_history[]" value="anxiety">
                                        Generalized Anxiety Disorder
                                    </label>
                                    <label class="checkbox-label nested">
                                        <input type="checkbox" name="family_history[]" value="panic">
                                        Panic Disorder
                                    </label>
                                    <label class="checkbox-label nested">
                                        <input type="checkbox" name="family_history[]" value="ptsd">
                                        Posttraumatic Stress Disorder
                                    </label>
                                    <label class="checkbox-label nested">
                                        <input type="checkbox" name="family_history[]" value="schizophrenia">
                                        Schizophrenia
                                    </label>

                                    Other:
                                    <input type="text" placeholder="Specify other mental illness" name="family_other_mental_illness" class="inline-input">
                                </div>
                            </label>

                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="lupus">
                                Systemic Lupus Erythematosus (SLE)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="hypertension">
                                Hypertension (elevated blood pressure)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="dyslipidemia">
                                Dyslipidemia (elevated cholesterol levels)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="arthritis">
                                Arthritis (joint pains)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="pcos">
                                Polycystic Ovarian Syndrome (PCOS)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="cancer_specify_family" onchange="toggleExtraFamilyIllness(this, 'cancer-input-family')">
                                Cancer
                            </label>

                            <div id="cancer-input-family" class="extra-input">
                                <input type="text" name="family_cancer_details" placeholder="Please specify..." />
                            </div>

                            <br>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="other">
                                Other:
                                <input type="text" placeholder="Specify if there is" name="other_family_history" class="inline-input">
                            </label>
                        </div>
                    </div>
                </fieldset>


                <script>
                    function toggleExtraFamilyIllness(checkbox, inputId) {
                        let extra = document.getElementById(inputId);
                        if (checkbox.checked) {
                            extra.style.display = "block";
                        } else {
                            extra.style.display = "none";
                        }
                    }
                </script>

                <div class="form-navigation">
                    <button type="button" class="prev-btn btn btn-secondary" onclick="prevStep2()">Previous</button>
                    <button type="submit" class="submit-btn btn btn-success">Submit Form</button>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        if (!window.bootstrap) {
            document.write('<script src="/js/bootstrap.bundle.min.js"><\/script>');
        }
    </script>
    <script src="js/form copy.js"></script>
    <script>
        function toggleOtherPastIllness() {
            const checkbox = document.getElementById("otherPastIllnessCheckbox");
            const input = document.getElementById("otherPastIllnessInput");

            if (checkbox.checked) {
                input.style.display = "block";
                input.disabled = false; // ✅ will be included in POST
            } else {
                input.style.display = "none";
                input.disabled = true; // ✅ prevent empty field submission
                input.value = ""; // ✅ clear value when unchecked
            }
        }

        const studentIdInput = document.getElementById('studentId');

        studentIdInput.addEventListener('focus', function() {
            if (this.value === 'N/A') {
                this.value = '';
            }
        });

        studentIdInput.addEventListener('blur', function() {
            if (this.value.trim() === '') {
                this.value = 'N/A'; // Optional: put N/A back if left empty
            }
        });
    </script>
    <?php
    // Store status values before unsetting
    $errorMsg = $_SESSION['error'] ?? null;
    $successMsg = $_SESSION['success'] ?? null;
    $status = $_SESSION['STATUS'] ?? null;

    // Clear session variables so they don't persist
    unset($_SESSION['error'], $_SESSION['success'], $_SESSION['STATUS']);
    ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!empty($errorMsg)): ?>
                try {
                    new bootstrap.Modal(document.getElementById('errorModal')).show();
                } catch (e) {
                    console.error('Failed to show errorModal:', e);
                    alert('Error: <?php echo addslashes(htmlspecialchars($errorMsg)); ?>');
                }
            <?php endif; ?>

            <?php if (!empty($successMsg)): ?>
                try {
                    new bootstrap.Modal(document.getElementById('successModal')).show();
                } catch (e) {
                    console.error('Failed to show successModal:', e);
                    alert('Success: <?php echo addslashes(htmlspecialchars($successMsg)); ?>');
                }
            <?php endif; ?>

            <?php if ($status === "SIGN_UP_SUCCESS"): ?>
                new bootstrap.Modal(document.getElementById('signupSuccessModal')).show();
            <?php elseif ($status === "VERIFICATION_FAILED"): ?>
                const verificationFailedModal = new bootstrap.Modal(
                    document.getElementById('verificationFailedModal'), {
                        backdrop: 'static',
                        keyboard: false
                    }
                );
                verificationFailedModal.show();
                document.getElementById('gotoLoginBtn')?.addEventListener('click', () => {
                    window.location.href = "signup.php?clear=1";
                });
            <?php elseif ($status === "ALREADY_VERIFIED"): ?>
                new bootstrap.Modal(document.getElementById('alreadyVerifiedModal')).show();
            <?php endif; ?>
        });
    </script>




</body>

</html>