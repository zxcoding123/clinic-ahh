<?php
session_start();

include 'config.php';

// Prevent caching
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

// Check if patient already exists
$existingPatientQuery = "SELECT * FROM patients WHERE user_id = ?";
$stmt = $conn->prepare($existingPatientQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$existingPatient = $result->fetch_assoc();

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



if ($user && in_array(trim($user['user_type']), ADMIN_TYPES, true)) {
    error_log("ADMIN REDIRECT TRIGGERED - User Type: {$user['user_type']}");
    header("Location: /adminhome.php");
    exit();
} else {
    error_log("User is not admin - proceeding with form");
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
    header("Location: /login");
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
        error_log("User data fetched: " . print_r($user, true));
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
    error_log("Attempting file upload: " . $file['name']);

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
    error_log("File uploaded successfully to: " . $targetPath);
    return $targetPath;
}



// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json'); // Ensure JSON response for AJAX
    error_log("CSRF token check - Session token: {$_SESSION['csrf_token']}, POST token: {$_POST['csrf_token']}");
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
        echo json_encode(['error' => implode('; ', $errors)]);
        error_log("Validation errors for user_id: $userId: " . implode('; ', $errors));
        echo json_encode(['error' => implode('; ', $errors)]);
        exit();
    }

    try {
        error_log("Starting database transaction");

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
            'student_id' => $_POST['studentId'],
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


        // Check if patient already exists
        $existingPatientQueryNew = "SELECT id, user_id FROM patients WHERE user_id = ?";
        $stmt = $conn->prepare($existingPatientQueryNew);
        $stmt->bind_param("i", $userId);

        $stmt->execute();
        $result = $stmt->get_result();
        $existingPatient = $result->fetch_assoc();
        $stmt->close();



        if ($existingPatient) {
            error_log("Existing patient found - ID: {$existingPatient['id']}");
            $patientId = $existingPatient['id'];

            // 1. Archive existing medical info to history
            $archiveMedicalQuery = "
    INSERT INTO medical_info_history (
        patient_id, illnesses, medications, vaccination_status, 
        menstruation_age, menstrual_pattern, pregnancies, live_children, 
        menstrual_symptoms, past_illnesses, hospital_admissions, 
        family_history, other_conditions
    )
    SELECT patient_id, illnesses, medications, vaccination_status, 
           menstruation_age, menstrual_pattern, pregnancies, live_children, 
           menstrual_symptoms, past_illnesses, hospital_admissions, 
           family_history, other_conditions
    FROM medical_info
    WHERE patient_id = ?
";

            $stmt = $conn->prepare($archiveMedicalQuery);
            $stmt->bind_param("i", $patientId);
            if (!$stmt->execute()) {
                throw new Exception("Failed to archive medical info: " . $stmt->error);
            }
            $stmt->close();


            // 1. Archive existing patient record to history
            $archivePatientQuery = "
    INSERT INTO patients_history (
        user_id, student_id, surname, firstname, middlename, suffix, 
        birthday, age, sex, blood_type, religion, nationality, 
        civil_status, email, contact_number, city_address, 
        provincial_address, photo_path, grade_level, grading_quarter, 
        track_strand, section, semester, department, course, 
        year_level, position, employee_id, id_path, cor_path
    )
    SELECT 
        user_id, student_id, surname, firstname, middlename, suffix, 
        birthday, age, sex, blood_type, religion, nationality, 
        civil_status, email, contact_number, city_address, 
        provincial_address, photo_path, grade_level, grading_quarter, 
        track_strand, section, semester, department, course, 
        year_level, position, employee_id, id_path, cor_path
    FROM patients
    WHERE user_id = ?
";

            $stmt = $conn->prepare($archivePatientQuery);
            $stmt->bind_param("i", $patientId);
            if (!$stmt->execute()) {
                throw new Exception("Failed to archive patient: " . $stmt->error);
            }
            $stmt->close();
        } else {
            error_log("No existing patient record found");
        }
        // Change from INSERT to UPDATE for patients table
        $sql = "UPDATE patients SET
    student_id = ?,
    surname = ?,
    firstname = ?,
    middlename = ?,
    suffix = ?,
    birthday = ?,
    age = ?,
    sex = ?,
    blood_type = ?,
    religion = ?,
    nationality = ?,
    civil_status = ?,
    email = ?,
    contact_number = ?,
    city_address = ?,
    provincial_address = ?,
    photo_path = ?,
    grade_level = ?,
    grading_quarter = ?,
    track_strand = ?,
    section = ?,
    semester = ?,
    department = ?,
    course = ?,
    year_level = ?,
    position = ?,
    employee_id = ?,
    id_path = ?,
    cor_path = ?
WHERE user_id = ?";

        $stmt = $conn->prepare($sql);

        // Reorder parameters - user_id moves to the end for WHERE clause
        $stmt->bind_param(
            "ssssssissssssssssssssssssssssi", // Notice the last 'i' for user_id
            $patientData['student_id'],
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
            $patientData['cor_path'],
            $patientData['user_id']  // Moved to the end for WHERE clause
        );

        if (!$stmt->execute()) {
            throw new Exception("Patient update failed: " . $stmt->error);
        }
        $stmt->close();

        // Get patient_id from database since we're not inserting new record
        $patientId = $patientData['user_id']; // Or query it if needed

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

        // Check if emergency contact exists first
        $checkStmt = $conn->prepare("SELECT id FROM emergency_contacts WHERE patient_id = ?");
        $checkStmt->bind_param("i", $patientId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        $checkStmt->close();

        if ($result->num_rows > 0) {
            // Update existing emergency contact
            $emergencyStmt = $conn->prepare("UPDATE emergency_contacts SET
        surname = ?,
        firstname = ?,
        middlename = ?,
        contact_number = ?,
        relationship = ?,
        city_address = ?
        WHERE patient_id = ?");

            $emergencyStmt->bind_param(
                "ssssssi",
                $emergencyData['surname'],
                $emergencyData['firstname'],
                $emergencyData['middlename'],
                $emergencyData['contact_number'],
                $emergencyData['relationship'],
                $emergencyData['city_address'],
                $patientId
            );
        } else {
            // Insert new emergency contact if none exists
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
        }

        if (!$emergencyStmt->execute()) {
            throw new Exception("Emergency contact operation failed: " . $emergencyStmt->error);
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

        $past_illnesses = isset($_POST['past_illness']) ? (array)$_POST['past_illness'] : [];

        // Handle illnesses, medications, family history, etc.
        $past_illness = isset($_POST['past_illness']) ? (array)$_POST['past_illness'] : [];
        if (in_array('psychiatric', $past_illnesses) && isset($_POST['psychiatric'])) {
            $past_illness = array_map('sanitizeInput', (array)$_POST['psychiatric']);
            $illnesses = array_diff($past_illnesses, ['psychiatric']);
            $illnesses = array_merge($past_illnesses, $past_illness);
        }

        // Normal illnesses
        $past_illnesses = isset($_POST['past_illness']) ? array_map('htmlspecialchars', (array)$_POST['past_illness']) : [];

        // Separate "Other" illness
        $other_illness = isset($_POST['past_illness_other']) ? htmlspecialchars(trim($_POST['past_illness_other'])) : "";

        if (!empty($other_illness)) {
            $past_illnesses[] = "Other: " . $other_illness;
        }

        $past_illnesses = isset($_POST['past_illness']) ? (array)$_POST['past_illness'] : [];



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
            'past_illnesses' => isset($_POST['past_illness']) ? implode(",", array_map('sanitizeInput', (array)$_POST['past_illness'])) : null,
            'hospital_admissions' => $hospitalAdmissionsStr,
            'family_history' => !empty($familyHistory) ? implode(",", array_map('sanitizeInput', $familyHistory)) : null,
            'other_conditions' => !empty($_POST['other_family_history']) ? sanitizeInput($_POST['other_family_history']) : null
        ];

        // Check if medical info exists first
        $checkStmt = $conn->prepare("SELECT patient_id FROM medical_info WHERE patient_id = ?");
        $checkStmt->bind_param("i", $patientId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        $checkStmt->close();

        if ($result->num_rows > 0) {
            // Update existing medical info
            $medicalStmt = $conn->prepare("UPDATE medical_info SET
        illnesses = ?,
        medications = ?,
        vaccination_status = ?,
        menstruation_age = ?,
        menstrual_pattern = ?,
        pregnancies = ?,
        live_children = ?,
        menstrual_symptoms = ?,
        past_illnesses = ?,
        hospital_admissions = ?,
        family_history = ?,
        other_conditions = ?
        WHERE patient_id = ?");

            $medicalStmt->bind_param(
                "ssssiissssssi",
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
                $medicalData['other_conditions'],
                $medicalData['patient_id']
            );
        } else {
            // Insert new medical info if none exists
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
        }

        if (!$medicalStmt->execute()) {
            throw new Exception("Medical info operation failed: " . $medicalStmt->error);
        }
        $medicalStmt->close();

        $updateStmt = $conn->prepare("UPDATE users SET profile_submitted = 1, profile_update_required = 0 WHERE id = ?");
        $updateStmt->bind_param("i", $userId);
        if (!$updateStmt->execute()) {
            throw new Exception("User update failed: " . $updateStmt->error);
        }
        $updateStmt->close();
        error_log("Transaction completed successfully");

        // 1. Get all IDs for the target user types
        $adminQuery = $conn->prepare("
    SELECT id 
    FROM users 
    WHERE user_type IN ('Super Admin', 'Medical Admin', 'Dental Admin')
");
        $adminQuery->execute();
        $adminResult = $adminQuery->get_result();

        // 2. Build notification data
        $notificationTitle = "Health Profile Updated!";
        $notificationDescription = "{$patientData['firstname']} {$patientData['surname']} - ({$userType}) has updated their health profile.";
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

           // Insert patient data into patients_history
    $insertHistoryQuery = "
        INSERT INTO patients_history (
            user_id, student_id, surname, firstname, middlename, suffix,
            birthday, age, sex, blood_type, religion, nationality, civil_status,
            email, contact_number, city_address, provincial_address, photo_path,
            grade_level, grading_quarter, track_strand, section, semester,
            department, course, year_level, position, employee_id, id_path,
            cor_path, created_at, updated_at, archived_at
        )
        SELECT 
            user_id, student_id, surname, firstname, middlename, suffix,
            birthday, age, sex, blood_type, religion, nationality, civil_status,
            email, contact_number, city_address, provincial_address, photo_path,
            grade_level, grading_quarter, track_strand, section, semester,
            department, course, year_level, position, employee_id, id_path,
            cor_path, created_at, updated_at, NOW()
        FROM patients 
        WHERE id = ?
    ";

    $stmt = $conn->prepare($insertHistoryQuery);
    $stmt->bind_param("i", $patientData['id']);
    $stmt->execute();
    $stmt->close();



        $conn->commit();
          $_SESSION['STATUS'] = "UPDATE_PROFILE_SUCCESFUL";
        error_log("Health profile updated successfully for user_id: $userId, redirecting to /uploaddocs");
        echo json_encode(['success' => 'Health profile updated successfully. Please upload your documents.']);
        exit();
    } catch (Exception $e) {
        error_log("TRANSACTION FAILED: " . $e->getMessage());
        error_log("Patient data being inserted: " . print_r($patientData, true));

        $conn->rollback();
        error_log("Form submission error: UserID=$userId, Error=" . $e->getMessage());
        echo json_encode(['error' => 'An error occurred: ' . $e->getMessage()]);
        exit();
    }
}

error_log("=== END OF FORM PROCESSING ===");
error_log("Current URL: " . $_SERVER['REQUEST_URI']);
error_log("Headers sent: " . headers_sent());

$patientData = null;
$emergencyContactData = null;
$medicalInfoData = null;

$patientQuery = "SELECT * FROM patients WHERE user_id = ?";
$stmt = $conn->prepare($patientQuery);
if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $patientData = $result->fetch_assoc();
    $stmt->close();

 if ($patientData) {

      if ($patientData) {
        // Fetch emergency contact data
        $emergencyQuery = "SELECT * FROM emergency_contacts WHERE patient_id = ?";
        $stmt = $conn->prepare($emergencyQuery);
        $stmt->bind_param("i", $patientData['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $emergencyContactData = $result->fetch_assoc();
        $stmt->close();

        // Fetch medical info data
        $medicalQuery = "SELECT * FROM medical_info WHERE patient_id = ?";
        $stmt = $conn->prepare($medicalQuery);
        $stmt->bind_param("i", $patientData['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $medicalInfoData = $result->fetch_assoc();
        $stmt->close();
    }

 
}

}


// Parse medications data
$medications = [];
if (!empty($medicalInfoData['medications'])) {
    // Assuming medications are separated by newlines or commas
    $medEntries = preg_split('/\r\n|\n|,/', $medicalInfoData['medications']);
    foreach ($medEntries as $entry) {
        $parts = explode(':', trim($entry));
        if (count($parts) >= 4) {
            $medications[] = [
                'drug' => $parts[0],
                'dose' => $parts[1],
                'unit' => $parts[2],
                'frequency' => $parts[3]
            ];
        }
    }
}

// Function to safely output data to form fields
function formValue($fieldName, $default = '')
{
    global $patientData, $emergencyContactData, $medicalInfoData;

    if (isset($_POST[$fieldName])) {
        return htmlspecialchars($_POST[$fieldName]);
    }

    if ($patientData && isset($patientData[$fieldName])) {
        return htmlspecialchars($patientData[$fieldName]);
    }

    if ($emergencyContactData && isset($emergencyContactData[$fieldName])) {
        return htmlspecialchars($emergencyContactData[$fieldName]);
    }

    if ($medicalInfoData && isset($medicalInfoData[$fieldName])) {
        return htmlspecialchars($medicalInfoData[$fieldName]);
    }

    return $default;
}
function isChecked($fieldName, $value, $default = false)
{
    global $patientData, $emergencyContactData, $medicalInfoData;

    if (isset($_POST[$fieldName])) {
        return $_POST[$fieldName] == $value ? 'checked' : '';
    }

    if ($medicalInfoData && isset($medicalInfoData[$fieldName])) {
        $values = explode(',', $medicalInfoData[$fieldName]);

        foreach ($values as $val) {
            $val = trim($val);
            // Check for exact match or prefix (for "Allergies: Tester" matching "allergies")
            if ($val === $value || stripos($val, $value . ':') === 0) {
                return 'checked';
            }
        }
    }

    // Fallback to other sources
    $dataSources = [$patientData, $emergencyContactData];
    foreach ($dataSources as $source) {
        if ($source && isset($source[$fieldName])) {
            return $source[$fieldName] == $value ? 'checked' : '';
        }
    }

    return $default ? 'checked' : '';
}


$religion = formValue('religion');
$isOther = str_starts_with($religion, 'OTHER:');
$otherValue = $isOther ? trim(substr($religion, 6)) : '';

$illnessesRaw = $medicalInfoData['illnesses'] ?? ''; // e.g. "asthma,...,Allergies: Tester,Other: Niggas"
$illnesses = explode(',', $illnessesRaw);

$allergiesSpecify = '';
$otherSpecify = '';
$checkedIllnesses = [];

foreach ($illnesses as $item) {
    $item = trim($item);

    if (stripos($item, 'Allergies:') === 0) {
        $allergiesSpecify = trim(substr($item, strlen('Allergies:')));
        $checkedIllnesses[] = 'allergies';
    } elseif (stripos($item, 'Other:') === 0) {
        $otherSpecify = trim(substr($item, strlen('Other:')));
        $checkedIllnesses[] = 'other';
    } elseif (stripos($item, 'Cancer:') === 0) {
        $otherSpecify = trim(substr($item, strlen('Cancer:')));
        $checkedIllnesses[] = 'cancer';
    } else {
        $checkedIllnesses[] = strtolower($item);
    }
}

$pastIllnessesRaw = $medicalInfoData['past_illnesses'] ?? '';
$familyHistoryRaw = $medicalInfoData['family_history'] ?? '';

$pastIllnesses = explode(',', $pastIllnessesRaw);
$familyHistory = explode(',', $familyHistoryRaw);

$otherPastIllness = '';
$otherFamilyCondition = '';
$familyAllergy = '';
$cancerType = '';

// Extract "Other", "Allergies", "Cancer" labels if present
foreach ($pastIllnesses as $item) {
    $item = trim($item);
    if (stripos($item, 'Other:') === 0) {
        $otherPastIllness = trim(substr($item, strlen('Other:')));
    }
}

foreach ($familyHistory as $item) {
    $item = trim($item);

    if (stripos($item, 'Other (Family):') === 0) {
        $otherFamilyCondition = trim(substr($item, strlen('Other (Family):')));
    } elseif (stripos($item, 'Family Allergies:') === 0) {
        $familyAllergy = trim(substr($item, strlen('Family Allergies:')));
    } elseif (stripos($item, 'Cancer:') === 0) {
        $cancerType = trim(substr($item, strlen('Cancer:')));
    } elseif (stripos($item, 'Other Mental Illness (Family):') === 0) {
        $mentalIllnessFamily = trim(substr($item, strlen('Other Mental Illness (Family):')));
    }
}



$hasHospitalAdmissions = !empty($medicalInfoData['hospital_admissions']);



$menstrualSymptoms = !empty($medicalInfoData['menstrual_symptoms'])
    ? explode(",", $medicalInfoData['menstrual_symptoms'])
    : [];

// Extract "other" text if it exists
$otherSymptomValue = null;
foreach ($menstrualSymptoms as $i => $symptom) {
    $symptom = trim($symptom); // clean spaces

    if (stripos($symptom, 'other:') === 0) {
        $otherSymptomValue = trim(substr($symptom, 6)); // get text after "other:"
        $menstrualSymptoms[$i] = 'other'; // replace with plain "other"
    } else {
        $menstrualSymptoms[$i] = $symptom; // normalize value
    }
}

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
        <form class="health-profile-form" id="healthProfileForm" action="update_form.php" method="POST" enctype="multipart/form-data">
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
                                <input type="date" id="birthday" name="birthday" required onchange="calculateAge()" value="<?= formValue('birthday') ?>">
                                <div class="invalid-feedback">Please select a valid birthday</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="input-wrapper">
                                <label for="age">Age:</label>
                                <input type="number" id="age" name="age" class="age-input" readonly value="<?= formValue(fieldName: 'age') ?>">
                                <div class="invalid-feedback">Age will be calculated automatically</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="input-wrapper">
                                <label for="sex">Sex:</label>
                                <select id="sex" name="sex" required onchange="toggleMenstrualSection()">
                                    <option value="">Select</option>
                                    <option value="male" <?= formValue('sex') == 'male' ? 'selected' : '' ?>>Male</option>
                                    <option value="female" <?= formValue('sex') == 'female' ? 'selected' : '' ?>>Female</option>
                                </select>
                                <div class="invalid-feedback">Please select a gender</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="input-wrapper">
                                <label for="bloodType">Blood Type:</label>
                                <select id="bloodType" name="bloodType">
                                    <option value="">Select</option>
                                    <option value="A+" <?= formValue('blood_type') == 'A+' ? 'selected' : '' ?>>A+</option>
                                    <option value="A-" <?= formValue('blood_type') == 'A-' ? 'selected' : '' ?>>A-</option>
                                    <option value="B+" <?= formValue('blood_type') == 'B+' ? 'selected' : '' ?>>B+</option>
                                    <option value="B-" <?= formValue('blood_type') == 'B-' ? 'selected' : '' ?>>B-</option>
                                    <option value="AB+" <?= formValue('blood_type') == 'AB+' ? 'selected' : '' ?>>AB+</option>
                                    <option value="AB-" <?= formValue('blood_type') == 'AB-' ? 'selected' : '' ?>>AB-</option>
                                    <option value="O+" <?= formValue('blood_type') == 'O+' ? 'selected' : '' ?>>O+</option>
                                    <option value="O-" <?= formValue('blood_type') == 'O-' ? 'selected' : '' ?>>O-</option>
                                    <option value="unknown" <?= formValue('blood_type') == 'unknown' ? 'selected' : '' ?>>Unknown</option>

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

                                        <option value="7" <?= formValue('grade_level') == 'Grade 7' ? 'selected' : '' ?>>Grade 7</option>
                                        <option value="8" <?= formValue('grade_level') == 'Grade 8' ? 'selected' : '' ?>>Grade 8</option>
                                        <option value="9" <?= formValue('grade_level') == 'Grade 9' ? 'selected' : '' ?>>Grade 9</option>
                                        <option value="10" <?= formValue('grade_level') == 'Grade 10' ? 'selected' : '' ?>>Grade 10</option>
                                    </select>
                                    <div class="invalid-feedback">Please select a grade</div>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="input-wrapper">
                                    <label for="gradeLevel">Grading/Quarter:</label>
                                    <select id="gradeLevel" name="gradeLevel" required>
                                        <option value="">Select</option>
                                        <option value="1" <?= formValue('grading_quarter') == '1' ? 'selected' : '' ?>>1</option>
                                        <option value="2" <?= formValue('grading_quarter') == '2' ? 'selected' : '' ?>>2</option>
                                        <option value="3" <?= formValue('grading_quarter') == '3' ? 'selected' : '' ?>>3</option>
                                        <option value="4" <?= formValue('grading_quarter') == '4' ? 'selected' : '' ?>>4</option>
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
                                        <option value="11" <?= formValue('grade_level') == 'Grade 11' ? 'selected' : '' ?>>Grade 11</option>
                                        <option value="12" <?= formValue('grade_level') == 'Grade 12' ? 'selected' : '' ?>>Grade 12</option>
                                    </select>
                                    <div class="invalid-feedback">Please select a grade</div>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="input-wrapper">
                                    <label for="Track/Strand">Track/Strand:</label>
                                    <select id="Track/Strand" name="Track/Strand" required onchange="toggleOtherTrackStrandInput()">
                                        <option value="" <?= formValue('track_strand') == '' ? 'selected' : '' ?>>Select Track/Strand</option>

                                        <optgroup label="Academic Track">
                                            <option value="GAS" <?= formValue('track_strand') == 'GAS' ? 'selected' : '' ?>>General Academic Strand (GAS)</option>
                                            <option value="HUMSS" <?= formValue('track_strand') == 'HUMSS' ? 'selected' : '' ?>>Humanities and Social Sciences Strand (HUMSS)</option>
                                            <option value="STEM" <?= formValue('track_strand') == 'STEM' ? 'selected' : '' ?>>Science, Technology, Engineering, and Mathematics Strand (STEM)</option>
                                            <option value="ABM" <?= formValue('track_strand') == 'ABM' ? 'selected' : '' ?>>Accountancy, Business and Management Strand (ABM)</option>
                                        </optgroup>

                                        <optgroup label="TVL Track">
                                            <option value="TVL-ICT" <?= formValue('track_strand') == 'TVL-ICT' ? 'selected' : '' ?>>Information and Communication Technology (ICT)</option>
                                            <option value="TVL-HE" <?= formValue('track_strand') == 'TVL-HE' ? 'selected' : '' ?>>Home Economics (HE)</option>
                                            <option value="TVL-IA" <?= formValue('track_strand') == 'TVL-IA' ? 'selected' : '' ?>>Industrial Arts (IA)</option>
                                            <option value="TVL-AFA" <?= formValue('track_strand') == 'TVL-AFA' ? 'selected' : '' ?>>Agri-Fishery Arts (AFA)</option>
                                        </optgroup>

                                        <option value="SPORTS" <?= formValue('track_strand') == 'SPORTS' ? 'selected' : '' ?>>Sports Track</option>
                                        <option value="ARTS-DESIGN" <?= formValue('track_strand') == 'ARTS-DESIGN' ? 'selected' : '' ?>>Arts and Design Track</option>
                                        <option value="OTHER" <?= formValue('track_strand') == 'OTHER' ? 'selected' : '' ?>>Others (Please specify)</option>
                                    </select>

                                    <div id="otherTrackStrandWrapper" style="display: none; margin-top: 10px;">
                                        <input type="text" id="otherTrackStrand" name="other_track_strand" placeholder="Please specify Track/Strand" class="form-control"
                                            value="<?= formValue('track_strand') ?>">
                                    </div>
                                    <div class="invalid-feedback">Please select or specify your Track/Strand</div>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="input-wrapper">
                                    <label for="section">Section:</label>
                                    <input type="text" id="section" name="section" value="<?= formValue('section') ?>" required>
                                    <div class="invalid-feedback">Please enter the section</div>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="input-wrapper">
                                    <label for="Sem">Semester:</label>
                                    <select id="Sem" name="Sem" required>
                                        <option value="">Select</option>
                                        <option value="Midterm" <?= formValue('semester') == 'Midterm' ? 'selected' : '' ?>>First Semester</option>
                                        <option value="Finals" <?= formValue('semester') == 'Finals' ? 'selected' : '' ?>>Second Semester</option>
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
                                        <option value="CLA" <?= formValue('department') == 'CLA' ? 'selected' : '' ?>>College of Liberal Arts (CLA)</option>
                                        <option value="CSM" <?= formValue('department') == 'CSM' ? 'selected' : '' ?>>College of Science and Mathematics (CSM)</option>
                                        <option value="COE" <?= formValue('department') == 'COE' ? 'selected' : '' ?>>College of Engineering (COE)</option>
                                        <option value="CTE" <?= formValue('department') == 'CTE' ? 'selected' : '' ?>>College of Teacher Education (CTE)</option>
                                        <option value="COA" <?= formValue('department') == 'COA' ? 'selected' : '' ?>>College of Architecture (COA)</option>
                                        <option value="CON" <?= formValue('department') == 'CON' ? 'selected' : '' ?>>College of Nursing (CON)</option>
                                        <option value="CA" <?= formValue('department') == 'CA' ? 'selected' : '' ?>>College of Agriculture (CA)</option>
                                        <option value="CFES" <?= formValue('department') == 'CFES' ? 'selected' : '' ?>>College of Forestry and Environmental Studies (CFES)</option>
                                        <option value="CCJE" <?= formValue('department') == 'CCJE' ? 'selected' : '' ?>>College of Criminal Justice Education (CCJE)</option>
                                        <option value="CHE" <?= formValue('department') == 'CHE' ? 'selected' : '' ?>>College of Home Economics (CHE)</option>
                                        <option value="CCS" <?= formValue('department') == 'CCS' ? 'selected' : '' ?>>College of Computing Studies (CCS)</option>
                                        <option value="COM" <?= formValue('department') == 'COM' ? 'selected' : '' ?>>College of Medicine (COM)</option>
                                        <option value="CPADS" <?= formValue('department') == 'CPADS' ? 'selected' : '' ?>>College of Public Administration and Development Studies (CPADS)</option>
                                        <option value="CSSPE" <?= formValue('department') == 'CSSPE' ? 'selected' : '' ?>>College of Sports Science and Physical Education (CSSPE)</option>
                                        <option value="CSWCD" <?= formValue('department') == 'CSWCD' ? 'selected' : '' ?>>College of Social Work and Community Development (CSWCD)</option>
                                        <option value="CAIS" <?= formValue('department') == 'CAIS' ? 'selected' : '' ?>>College of Asian and Islamic Studies (CAIS)</option>
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
                            <div class="form-group">
                                <div class="input-wrapper">
                                    <label for="Sem">Semester:</label>
                                    <select id="Sem" name="Sem" required>
                                        <option value="">Select</option>
                                        <option value="Midterm" <?= formValue('semester') == 'Midterm' ? 'selected' : '' ?>>First Semester</option>
                                        <option value="Finals" <?= formValue('semester') == 'Finals' ? 'selected' : '' ?>>Second Semester</option>
                                    </select>
                                    <div class="invalid-feedback">Please select a semester</div>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="input-wrapper">
                                    <label for="yearLevel">Year Level:</label>
                                    <input type="number" id="yearLevel" name="yearLevel" min="1" max="5" value="<?= formValue('year_level') ?>" required>
                                    <div class="invalid-feedback">Please enter the year level</div>
                                </div>
                            </div>
                        <?php endif; ?>


                        <div class="form-group">
                            <div class="input-wrapper">
                                <label for="religion">Religion:</label>
                                <select id="religion" name="religion" required onchange="toggleOtherReligionInput()">
                                    <option value="">Select Religion</option>
                                    <option value="Roman Catholic" <?= $religion == 'Roman Catholic' ? 'selected' : '' ?>>Roman Catholic</option>
                                    <option value="Islam" <?= $religion == 'Islam' ? 'selected' : '' ?>>Islam</option>
                                    <option value="Iglesia ni Cristo" <?= $religion == 'Iglesia ni Cristo' ? 'selected' : '' ?>>Iglesia ni Cristo</option>
                                    <option value="Protestant" <?= $religion == 'Protestant' ? 'selected' : '' ?>>Protestant</option>
                                    <option value="Born Again Christian" <?= $religion == 'Born Again Christian' ? 'selected' : '' ?>>Born Again Christian</option>
                                    <option value="Seventh-day Adventist" <?= $religion == 'Seventh-day Adventist' ? 'selected' : '' ?>>Seventh-day Adventist</option>
                                    <option value="Jehovah's Witness" <?= $religion == "Jehovah's Witness" ? 'selected' : '' ?>>Jehovah's Witness</option>
                                    <option value="Buddhist" <?= $religion == 'Buddhist' ? 'selected' : '' ?>>Buddhist</option>
                                    <option value="OTHER" <?= $isOther ? 'selected' : '' ?>>Others (Please specify)</option>
                                </select>

                                <div id="otherReligionWrapper" style="<?= $isOther ? '' : 'display: none;' ?> margin-top: 10px;">
                                    <input type="text"
                                        id="otherReligion"
                                        name="other_religion"
                                        placeholder="Please specify religion"
                                        class="form-control"
                                        value="<?= htmlspecialchars($otherValue) ?>">
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
                                    <option value="single" <?= formValue('civil_status') == 'single' ? 'selected' : '' ?>>Single</option>
                                    <option value="married" <?= formValue('civil_status') == 'married' ? 'selected' : '' ?>>Married</option>
                                    <option value="widowed" <?= formValue('civil_status') == 'widowed' ? 'selected' : '' ?>>Widowed</option>
                                    <option value="divorced" <?= formValue('civil_status') == 'divorced' ? 'selected' : '' ?>>Divorced</option>
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
                                <input type="tel" id="contactNumber" name="contactNumber" pattern="09[0-9]{9}"
                                    value="<?= formValue('contact_number') ?>"
                                    maxlength="11" oninput="validatePhoneNumber(this)" required>
                                <small class="form-text text-muted">Format: 09XXXXXXXXX (11 digits)</small>
                                <div class="invalid-feedback">Please enter a valid 11-digit contact number starting with 09</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="input-wrapper">
                                <label for="studentId">Student ID: </label>
                                <input type="text" id="studentId" name="studentId" required value="<?= formValue('student_id') ?>">
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>







                        <div class="form-group full-width">
                            <div class="input-wrapper">
                                <label for="cityAddress">City Address:</label>
                                <input type="text" id="cityAddress" name="cityAddress" value="<?= formValue('city_address') ?>" required>
                                <div class="invalid-feedback">Please enter the city address</div>
                            </div>
                        </div>

                        <div class="form-group full-width">
                            <div class="input-wrapper">
                                <label for="provincialAddress">Provincial Address (if applicable):</label>
                                <input type="text" id="provincialAddress" name="provincialAddress" value="<?= formValue('provincial_address') ?>">
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
                                    <input type="text" placeholder="Surname" name="emergencySurname" required value="<?php echo $emergencyContactData['surname'] ?>">
                                    <div class="invalid-feedback">Please enter the emergency contact surname</div>
                                </div>
                                <div class="input-wrapper">
                                    <input type="text" placeholder="First name" name="emergencyFirstname" required value="<?php echo $emergencyContactData['firstname'] ?>">
                                    <div class="invalid-feedback">Please enter the emergency contact first name</div>
                                </div>
                                <div class="input-wrapper">
                                    <input type="text" placeholder="Middle name" name="emergencyMiddlename" value="<?php echo $emergencyContactData['middlename'] ?>">
                                    <div class="invalid-feedback">Please enter a valid emergency contact middle name</div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="input-wrapper">
                                <label for="emergencyContactNumber">Contact Number:</label>
                                <input type="tel" id="emergencyContactNumber" name="emergencyContactNumber"
                                    value="<?php echo $emergencyContactData['contact_number'] ?>"
                                    pattern="09[0-9]{9}" maxlength="11" oninput="validatePhoneNumber(this)" required>
                                <small class="form-text text-muted">Format: 09XXXXXXXXX (11 digits)</small>
                                <div class="invalid-feedback">Please enter a valid 11-digit emergency contact number starting with 09</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="input-wrapper">
                                <label for="emergencyRelationship">Relationship:</label>

                                <?php
                                $relationship = $emergencyContactData['relationship'] ?? '';
                                $isOther = str_starts_with($relationship, 'Other:');
                                $otherValue = $isOther ? trim(substr($relationship, 6)) : ''; // remove "Other:" prefix
                                ?>

                                <select id="emergencyRelationship" name="emergencyRelationship" required onchange="toggleOtherRelationshipInput()">
                                    <option value="">Select Relationship</option>
                                    <option value="Parent" <?= $relationship == 'Parent' ? 'selected' : '' ?>>Parent</option>
                                    <option value="Sibling" <?= $relationship == 'Sibling' ? 'selected' : '' ?>>Sibling</option>
                                    <option value="Spouse" <?= $relationship == 'Spouse' ? 'selected' : '' ?>>Spouse</option>
                                    <option value="Child" <?= $relationship == 'Child' ? 'selected' : '' ?>>Child</option>
                                    <option value="Guardian" <?= $relationship == 'Guardian' ? 'selected' : '' ?>>Guardian</option>
                                    <option value="Friend" <?= $relationship == 'Friend' ? 'selected' : '' ?>>Friend</option>
                                    <option value="Other" <?= $isOther ? 'selected' : '' ?>>Other</option>
                                </select>

                                <div id="otherRelationshipWrapper" style="<?= $isOther ? '' : 'display:none;' ?>; margin-top: 10px;">
                                    <input type="text" id="otherRelationship" name="other_relationship"
                                        placeholder="Please specify relationship"
                                        class="form-control"
                                        value="<?= htmlspecialchars($otherValue) ?>">
                                </div>

                                <div class="invalid-feedback">Please select or specify a relationship</div>

                            </div>
                        </div>

                        <div class="form-group full-width">
                            <div class="input-wrapper">
                                <label for="emergencyCityAddress">City Address:</label>
                                <input type="text" id="emergencyCityAddress" name="emergencyCityAddress" value="<?php echo $emergencyContactData['city_address'] ?>" required>
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
                                <input type="checkbox" name="illness[]" value="asthma" <?= isChecked('illnesses', 'asthma') ?>>
                                Bronchial Asthma ("Hika")
                            </label>
                            <?php
                            $isAllergyChecked = in_array('allergies', $checkedIllnesses);
                            $showSpecify = $isAllergyChecked || !empty($allergiesSpecify);
                            ?>
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="allergies" id="allergiesCheckbox"
                                    <?= $isAllergyChecked ? 'checked' : '' ?>>
                                Food Allergies
                                <div id="allergiesSpecifyContainer" style="<?= $showSpecify ? '' : 'display: none;' ?>">
                                    <input
                                        type="text"
                                        name="allergiesSpecify"
                                        placeholder="Specify food"
                                        value="<?= htmlspecialchars($allergiesSpecify) ?>"
                                        class="inline-input">
                                </div>
                            </label>

                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="rhinitis" <?= isChecked('illnesses', 'rhinitis') ?>>
                                Allergic Rhinitis
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="hyperthyroidism" <?= isChecked('illnesses', 'hyperthyroidism') ?>>
                                Hyperthyroidism
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="hypothyroidism" <?= isChecked('illnesses', 'hypothyroidism') ?>>
                                Hypothyroidism/Goiter
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="anemia" <?= isChecked('illnesses', 'anemia') ?>>
                                Anemia
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="migraine" <?= isChecked('illnesses', 'migraine') ?>>
                                Migraine (recurrent headaches)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="epilepsy" <?= isChecked('illnesses', 'epilepsy') ?>>
                                Epilepsy/Seizures
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="gerd" <?= isChecked('illnesses', 'gerd') ?>>
                                Gastroesophageal Reflux Disease (GERD)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="bowel_syndrome" <?= isChecked('illnesses', 'bowel_syndrome') ?>>
                                Irritable Bowel Syndrome
                            </label>
                        </div>

                        <div class="checkbox-column">
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="psychiatric"
                                    <?= isChecked('illnesses', 'depression') ?>
                                    <?= isChecked('illnesses', 'bipolar') ?>
                                    <?= isChecked('illnesses', 'anxiety') ?>
                                    <?= isChecked('illnesses', 'panic') ?>
                                    <?= isChecked('illnesses', 'stress') ?>
                                    <?= isChecked('illnesses', 'schizophrenia') ?>>
                                Psychiatric Illness:
                                <div class="nested-checkboxes">
                                    <label class="checkbox-label nested">
                                        <input type="checkbox" name="psychiatric[]" value="depression" <?= isChecked('illnesses', 'depression') ?>>
                                        Major Depressive Disorder
                                    </label>
                                    <label class="checkbox-label nested">
                                        <input type="checkbox" name="psychiatric[]" value="bipolar" <?= isChecked('illnesses', 'bipolar') ?>>
                                        Bipolar Disorder
                                    </label>
                                    <label class="checkbox-label nested">
                                        <input type="checkbox" name="psychiatric[]" value="anxiety" <?= isChecked('illnesses', 'anxiety') ?>>
                                        Generalized Anxiety Disorder
                                    </label>
                                    <label class="checkbox-label nested">
                                        <input type="checkbox" name="psychiatric[]" value="panic" <?= isChecked('illnesses', 'panic') ?>>
                                        Panic Disorder
                                    </label>
                                    <label class="checkbox-label nested">
                                        <input type="checkbox" name="psychiatric[]" value="stress" <?= isChecked('illnesses', 'ptsd') ?>>
                                        Posttraumatic Stress Disorder
                                    </label>
                                    <label class="checkbox-label nested">
                                        <input type="checkbox" name="psychiatric[]" value="schizophrenia" <?= isChecked('illnesses', 'schizophrenia') ?>>
                                        Schizophrenia
                                    </label>
                                </div>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="hypertension" <?= isChecked('illnesses', 'hypertension') ?>>
                                Hypertension (elevated blood pressure)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="diabetes" <?= isChecked('illnesses', 'diabetes') ?>>
                                Diabetes mellitus (elevated blood sugar)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="dyslipidemia" <?= isChecked('illnesses', 'dyslipidemia') ?>>
                                Dyslipidemia (elevated cholesterol levels)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="arthritis" <?= isChecked('illnesses', 'arthritis') ?>>
                                Arthritis (joint pains)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="lupus" <?= isChecked('illnesses', 'lupus') ?>>
                                Systemic Lupus Erythematosus (SLE)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="pcos" <?= isChecked('illnesses', 'pcos') ?>>
                                Polycystic Ovarian Syndrome (PCOS)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="cancer" <?= isChecked('illnesses', 'cancer') ?>>
                                Cancer
                            </label>

                            <div id="cancer-input" class="extra-input">
                                <input type="text" name="cancer_details" placeholder="Please specify..." value="<?= htmlspecialchars($otherSpecify) ?>" />
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
                            <label class="checkbox-label">
                                <input type="checkbox" name="illness[]" value="other" id="otherCheckbox"
                                    <?= in_array('other', $checkedIllnesses) ? 'checked' : '' ?>>
                                Other:
                                <div id="otherSpecifyContainer" style="<?= in_array('other', $checkedIllnesses) ? '' : 'display: none;' ?>">
                                    <input
                                        type="text"
                                        name="otherSpecify"
                                        placeholder="Specify other condition"
                                        value="<?= htmlspecialchars($otherSpecify) ?>"
                                        class="inline-input">
                                </div>
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
                            <?php if (empty($medications)): ?>
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
                                        <input type="text" class="table-input other-input" name="medications[0][drug_other]"
                                            placeholder="Enter drug name"
                                            value="">
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
                            <?php else: ?>
                                <?php foreach ($medications as $index => $med): ?>
                                    <tr>
                                        <td>
                                            <select class="table-input drug-select" name="medications[<?= $index ?>][drug]" onchange="handleDrugSelect(this)">
                                                <option value="">Select a drug</option>
                                                <option value="paracetamol" <?= $med['drug'] === 'paracetamol' ? 'selected' : '' ?>>Paracetamol</option>
                                                <option value="ibuprofen" <?= $med['drug'] === 'ibuprofen' ? 'selected' : '' ?>>Ibuprofen</option>
                                                <option value="amoxicillin" <?= $med['drug'] === 'amoxicillin' ? 'selected' : '' ?>>Amoxicillin</option>
                                                <option value="metformin" <?= $med['drug'] === 'metformin' ? 'selected' : '' ?>>Metformin</option>
                                                <option value="atorvastatin" <?= $med['drug'] === 'atorvastatin' ? 'selected' : '' ?>>Atorvastatin</option>
                                                <option value="losartan" <?= $med['drug'] === 'losartan' ? 'selected' : '' ?>>Losartan</option>
                                                <option value="omeprazole" <?= $med['drug'] === 'omeprazole' ? 'selected' : '' ?>>Omeprazole</option>
                                                <option value="simvastatin" <?= $med['drug'] === 'simvastatin' ? 'selected' : '' ?>>Simvastatin</option>
                                                <option value="aspirin" <?= $med['drug'] === 'aspirin' ? 'selected' : '' ?>>Aspirin</option>
                                                <option value="levothyroxine" <?= $med['drug'] === 'levothyroxine' ? 'selected' : '' ?>>Levothyroxine</option>
                                                <option value="other" <?= !in_array($med['drug'], ['paracetamol', 'ibuprofen', 'amoxicillin', 'metformin', 'atorvastatin', 'losartan', 'omeprazole', 'simvastatin', 'aspirin', 'levothyroxine']) ? 'selected' : '' ?>>Other</option>
                                            </select>
                                            <input type="text" class="table-input other-input" name="medications[<?= $index ?>][drug_other]"
                                                placeholder="Enter drug name"
                                                value="<?= !in_array($med['drug'], ['paracetamol', 'ibuprofen', 'amoxicillin', 'metformin', 'atorvastatin', 'losartan', 'omeprazole', 'simvastatin', 'aspirin', 'levothyroxine']) ? htmlspecialchars($med['drug']) : '' ?>"
                                                style="<?= in_array($med['drug'], ['paracetamol', 'ibuprofen', 'amoxicillin', 'metformin', 'atorvastatin', 'losartan', 'omeprazole', 'simvastatin', 'aspirin', 'levothyroxine']) ? 'display: none;' : '' ?>">
                                        </td>
                                        <td>
                                            <div class="dose-options">
                                                <input type="number" class="table-input" name="medications[<?= $index ?>][dose]"
                                                    placeholder="Dose" style="width: 80px;"
                                                    value="<?= htmlspecialchars($med['dose']) ?>">
                                                <select class="table-input" name="medications[<?= $index ?>][unit]">
                                                    <option value="mg" <?= $med['unit'] === 'mg' ? 'selected' : '' ?>>mg</option>
                                                    <option value="g" <?= $med['unit'] === 'g' ? 'selected' : '' ?>>g</option>
                                                    <option value="ml" <?= $med['unit'] === 'ml' ? 'selected' : '' ?>>ml</option>
                                                    <option value="units" <?= $med['unit'] === 'units' ? 'selected' : '' ?>>units</option>
                                                </select>
                                                <select class="table-input" name="medications[<?= $index ?>][frequency]">
                                                    <option value="">Select Frequency</option>
                                                    <option value="once daily" <?= $med['frequency'] === 'once daily' ? 'selected' : '' ?>>Once daily</option>
                                                    <option value="twice daily" <?= $med['frequency'] === 'twice daily' ? 'selected' : '' ?>>Twice daily</option>
                                                    <option value="three times daily" <?= $med['frequency'] === 'three times daily' ? 'selected' : '' ?>>Three times daily</option>
                                                    <option value="four times daily" <?= $med['frequency'] === 'four times daily' ? 'selected' : '' ?>>Four times daily</option>
                                                    <option value="as needed" <?= $med['frequency'] === 'as needed' ? 'selected' : '' ?>>As needed</option>
                                                </select>
                                            </div>
                                        </td>
                                        <td>
                                            <button type="button" class="remove-btn" onclick="removeMedicationRow(this)">×</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <button type="button" class="add-btn btn btn-secondary" onclick="addMedicationRow()">+ Add Medication</button>
                </fieldset>

                <script>
                    // Function to handle drug selection changes
                    function handleDrugSelect(select) {
                        const row = select.closest('tr');
                        const otherInput = row.querySelector('.other-input');
                        if (select.value === 'other') {
                            otherInput.style.display = '';
                        } else {
                            otherInput.style.display = 'none';
                            otherInput.value = '';
                        }
                    }

                    // Initialize all drug selects when page loads
                    document.addEventListener('DOMContentLoaded', function() {
                        document.querySelectorAll('.drug-select').forEach(select => {
                            // Trigger the change handler for each select
                            handleDrugSelect(select);

                            // If this is an "other" drug, make sure the input has the value
                            if (select.value === 'other') {
                                const row = select.closest('tr');
                                const otherInput = row.querySelector('.other-input');
                                if (otherInput.value === '') {
                                    // If the input is empty but "other" is selected, maybe set a default?
                                    // otherInput.value = 'Custom Drug';
                                }
                            }
                        });
                    });
                </script>

                <fieldset class="form-section">
                    <legend>COVID Vaccination</legend>
                    <div class="radio-group" id="vaccinationGroup">
                        <label class="radio-label">
                            <input type="radio" name="vaccination" value="fully" required <?= isChecked('vaccination_status', 'fully') ?>>
                            Fully vaccinated (Primary series with or without booster shot/s)
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="vaccination" value="partially" <?= isChecked('vaccination_status', 'partially') ?>>
                            Partially vaccinated (Incomplete primary series)
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="vaccination" value="not" <?= isChecked('vaccination_status', 'not') ?>>
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
                            <input type="number" name="menstruationAge" class="short-input" value="<?= formValue(fieldName: 'menstruation_age') ?>">
                        </div>
                        <div class="form-group">
                            <label>Menstrual Pattern:</label>
                            <div class="radio-group">
                                <label class="radio-label">
                                    <input type="radio" name="menstrualPattern" value="regular" <?= isChecked('menstrual_pattern', 'regular') ?>>
                                    Regular (monthly)
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="menstrualPattern" value="irregular" <?= isChecked('menstrual_pattern', 'irregular') ?>>
                                    Irregular
                                </label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Number of pregnancies:</label>
                            <input type="number" name="pregnancies" class="short-input" value="<?= formValue(fieldName: 'pregnancies') ?>">
                        </div>
                        <div class="form-group">
                            <label>Number of live children:</label>
                            <input type="number" name="liveChildren" class="short-input" value="<?= formValue(fieldName: 'live_children') ?>">
                        </div>
                        <div class="form-group">
                            <label>Menstrual Symptoms:</label>
                            <div class="checkbox-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="symptoms[]" value="dysmenorrhea" <?= isChecked('menstrual_symptoms', 'dysmenorrhea') ?>>
                                    Dysmenorrhea (cramps)
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="symptoms[]" value="migraine" <?= isChecked('menstrual_symptoms', 'migraine') ?>>
                                    Migraine
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="symptoms[]" value="consciousness" <?= isChecked('menstrual_symptoms', 'consciousness') ?>>
                                    Loss of consciousness
                                </label>

                                <label class="checkbox-label">
                                    <input type="checkbox" name="symptoms[]" value="other"
                                        <?= isChecked('menstrual_symptoms', 'other') ?>>
                                    Other:
                                    <input type="text" class="inline-input" name="otherSymptoms"
                                        value="<?= htmlspecialchars($otherSymptomValue ?? '') ?>">
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
                                <input type="checkbox" name="past_illness[]" value="varicella" <?= isChecked('past_illnesses', 'varicella') ?>>
                                Varicella (Chicken Pox)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="past_illness[]" value="dengue" <?= isChecked('past_illnesses', 'dengue') ?>>
                                Dengue
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="past_illness[]" value="tuberculosis" <?= isChecked('past_illnesses', 'tuberculosis') ?>>
                                Tuberculosis
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="past_illness[]" value="pneumonia" <?= isChecked('past_illnesses', 'pneumonia') ?>>
                                Pneumonia
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="past_illness[]" value="uti" <?= isChecked('past_illnesses', 'uti') ?>>
                                Urinary Tract Infection
                            </label>
                        </div>
                        <div class="checkbox-column">
                            <label class="checkbox-label">
                                <input type="checkbox" name="past_illness[]" value="appendicitis" <?= isChecked('past_illnesses', 'appendicitis') ?>>
                                Appendicitis
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="past_illness[]" value="cholecystitis" <?= isChecked('past_illnesses', 'cholecystitis') ?>>
                                Cholecystitis
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="past_illness[]" value="measles" <?= isChecked('past_illnesses', 'measles') ?>>
                                Measles
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="past_illness[]" value="typhoid fever" <?= isChecked('past_illnesses', 'typhoid fever') ?>>
                                Typhoid Fever
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="past_illness[]" value="amoebiasis" <?= isChecked('past_illnesses', 'amoebiasis') ?>>
                                Amoebiasis
                            </label>
                        </div>
                        <div class="checkbox-column">
                            <label class="checkbox-label">
                                <input type="checkbox" name="past_illness[]" value="kidney stones" <?= isChecked('past_illnesses', 'kidney stones') ?>>
                                Kidney Stones
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="past_illness[]" value="injury" <?= isChecked('past_illnesses', 'injury') ?>>
                                Injury
                            </label>
                            <div class="nested-checkboxes">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="past_illness[]" value="burn" <?= isChecked('past_illnesses', 'burn') ?>>
                                    Burn
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="past_illness[]" value="stab" <?= isChecked('past_illnesses', 'stab') ?>>
                                    Stab/Laceration
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="past_illness[]" value="fracture" <?= isChecked('past_illnesses', 'fracture') ?>>
                                    Fracture
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="otherPastIllnessCheckbox" onclick="toggleOtherPastIllness()" <?= $otherPastIllness ? 'checked' : '' ?>>
                            Other (Specify)
                        </label>
                        <input type="text" class="form-control" id="otherPastIllnessInput" name="past_illness_other"
                            placeholder="Specify other illnesses"
                            style="<?= $otherPastIllness ? '' : 'display:none;' ?> width: 300px; margin-top: 5px;"
                            value="<?= htmlspecialchars($otherPastIllness) ?>">
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend>Hospital Admission / Surgery</legend>
                    <label class="form-label">Have you ever been admitted to the hospital and/or undergone surgery?</label>
                    <div class="radio-group">
                        <label class="radio-label">
                            <input type="radio" name="hospital_admission" value="No"
                                onclick="toggleSurgeryFields(false)"
                                <?= !$hasHospitalAdmissions ? 'checked' : '' ?>>
                            No
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="hospital_admission" value="Yes"
                                onclick="toggleSurgeryFields(true)"
                                <?= $hasHospitalAdmissions ? 'checked' : '' ?>>
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
                                <?php
                                $hospitalAdmissions = [];
                                if (!empty($medicalInfoData['hospital_admissions'])) {
                                    // Example format: "2022:niggas,2021:appendectomy"
                                    $entries = explode(',', $medicalInfoData['hospital_admissions']);
                                    foreach ($entries as $i => $entry) {
                                        $parts = explode(':', $entry, 2);
                                        $year = htmlspecialchars(trim($parts[0] ?? ''));
                                        $reason = htmlspecialchars(trim($parts[1] ?? ''));
                                        echo <<<HTML
        <tr>
            <td>
                <input type="number" class="table-input" name="hospital_admissions[{$i}][year]" value="{$year}" min="1900" max="2025" placeholder="e.g., 2015">
            </td>
            <td>
                <input type="text" class="table-input" name="hospital_admissions[{$i}][reason]" value="{$reason}" placeholder="e.g., Appendectomy">
            </td>
            <td>
                <button type="button" class="remove-btn" onclick="removeSurgeryRow(this)">×</button>
            </td>
        </tr>
HTML;
                                    }
                                } else {
                                    // Show one blank row if no existing data
                                    echo <<<HTML
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
HTML;
                                }
                                ?>
                            </tbody>

                        </table>
                        <button type="button" class="add-btn btn btn-secondary" onclick="addSurgeryRow()">+ Add Admission/Surgery</button>
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend>Family Medical History</legend>
                    <label class="form-label">Indicate the known health conditions of your immediate family members:</label>
                    <div class="checkbox-grid">

                        <!-- Column 1 -->
                        <div class="checkbox-column">
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="hypertension" <?= isChecked('family_history', 'hypertension') ?>>
                                Hypertension (Elevated Blood Pressure)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="coronary" <?= isChecked('family_history', 'coronary') ?>>
                                Coronary Artery Disease / Heart Disease
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="heart_failure" <?= isChecked('family_history', 'heart_failure') ?>>
                                Congestive Heart Failure
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="diabetes" <?= isChecked('family_history', 'diabetes') ?>>
                                Diabetes Mellitus (Elevated Blood Sugar)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="chronic_kidney" <?= isChecked('family_history', 'chronic_kidney') ?>>
                                Chronic Kidney Disease (With/Without Hemodialysis)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="copd" <?= isChecked('family_history', 'copd') ?>>
                                Chronic Obstructive Pulmonary Disease (COPD)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="asthma" <?= isChecked('family_history', 'asthma') ?>>
                                Bronchial Asthma ("Hika")
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="rhinitis" <?= isChecked('family_history', 'rhinitis') ?>>
                                Allergic Rhinitis
                            </label>
                        </div>

                        <!-- Column 2 -->
                        <div class="checkbox-column">
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="cancer" <?= isChecked('family_history', 'cancer') ?>>
                                Cancer (Any Type)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" id="cancerCheckbox" onclick="toggleCancerInput()" <?= $cancerType ? 'checked' : '' ?>>
                                Cancer - Specify Type
                            </label>
                            <input type="text" class="form-control" id="cancerInput" name="cancer_specify_family"
                                placeholder="Specify cancer type"
                                style="<?= $cancerType ? '' : 'display:none;' ?> margin-top: 5px;"
                                value="<?= htmlspecialchars($cancerType) ?>">

                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="stroke" <?= isChecked('family_history', 'stroke') ?>>
                                Stroke (Cerebrovascular Disease)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="tuberculosis" <?= isChecked('family_history', 'tuberculosis') ?>>
                                Tuberculosis
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="epilepsy" <?= isChecked('family_history', 'epilepsy') ?>>
                                Epilepsy/Seizures
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="gerd" <?= isChecked('family_history', 'gerd') ?>>
                                Gastroesophageal Reflux Disease (GERD)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="bowel_syndrome" <?= isChecked('family_history', 'bowel_syndrome') ?>>
                                Irritable Bowel Syndrome (IBS)
                            </label>
                        </div>

                        <!-- Column 3 -->
                        <div class="checkbox-column">
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="psychiatric" <?= isChecked('family_history', 'psychiatric') ?>>
                                Psychiatric Illness:
                                <div class="nested-checkboxes">
                                    <label class="checkbox-label nested">
                                        <input type="checkbox" name="family_history[]" value="depression" <?= isChecked('family_history', 'depression') ?>>
                                        Major Depressive Disorder
                                    </label>
                                    <label class="checkbox-label nested">
                                        <input type="checkbox" name="family_history[]" value="bipolar" <?= isChecked('family_history', 'bipolar') ?>>
                                        Bipolar Disorder
                                    </label>
                                    <label class="checkbox-label nested">
                                        <input type="checkbox" name="family_history[]" value="anxiety" <?= isChecked('family_history', 'anxiety') ?>>
                                        Generalized Anxiety Disorder
                                    </label>
                                    <label class="checkbox-label nested">
                                        <input type="checkbox" name="family_history[]" value="panic" <?= isChecked('family_history', 'panic') ?>>
                                        Panic Disorder
                                    </label>
                                    <label class="checkbox-label nested">
                                        <input type="checkbox" name="family_history[]" value="ptsd" <?= isChecked('family_history', 'ptsd') ?>>
                                        Posttraumatic Stress Disorder
                                    </label>
                                    <label class="checkbox-label nested">
                                        <input type="checkbox" name="family_history[]" value="schizophrenia" <?= isChecked('family_history', 'schizophrenia') ?>>
                                        Schizophrenia
                                    </label>
                                    Other:
                                    <input type="text"
                                        style="<?= $mentalIllnessFamily ? '' : 'display:none;' ?>"
                                        placeholder="Specify other mental illness"
                                        name="family_other_mental_illness"
                                        class="inline-input"
                                        <?php if (!empty($mentalIllnessFamily)): ?>
                                        value="<?= htmlspecialchars($mentalIllnessFamily) ?>"
                                        <?php endif; ?>>

                                </div>
                            </label>

                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="liver_disease" <?= isChecked('family_history', 'liver_disease') ?>>
                                Liver Disease (Hepatitis, Cirrhosis)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="arthritis" <?= isChecked('family_history', 'arthritis') ?>>
                                Arthritis / Gout
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="anemia" <?= isChecked('family_history', 'anemia') ?>>
                                Blood Disorder (Anemia, Hemophilia, etc.)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" id="allergyCheckbox" onclick="toggleAllergyInput()" <?= $familyAllergy ? 'checked' : '' ?>>
                                Allergies - Specify
                            </label>
                            <input type="text" class="form-control" id="allergyInput" name="family_allergy_specify"
                                placeholder="Specify allergies"
                                style="<?= $familyAllergy ? '' : 'display:none;' ?> margin-top: 5px;"
                                value="<?= htmlspecialchars($familyAllergy) ?>">

                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="hyperthyroidism" <?= isChecked('family_history', 'hyperthyroidism') ?>>
                                Hyperthyroidism
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="hypothyroidism" <?= isChecked('family_history', 'hypothyroidism') ?>>
                                Hypothyroidism / Goiter
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="dyslipidemia" <?= isChecked('family_history', 'dyslipidemia') ?>>
                                Dyslipidemia (Elevated Cholesterol Levels)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="lupus" <?= isChecked('family_history', 'lupus') ?>>
                                Systemic Lupus Erythematosus (SLE)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="pcos" <?= isChecked('family_history', 'pcos') ?>>
                                Polycystic Ovarian Syndrome (PCOS)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" id="otherFamilyCheckbox" onclick="toggleOtherFamilyInput()" <?= $otherFamilyCondition ? 'checked' : '' ?>>
                                Other (Specify)
                            </label>
                            <input type="text" class="form-control" id="otherFamilyInput" name="other_family_history"
                                placeholder="Specify other conditions"
                                style="<?= $otherFamilyCondition ? '' : 'display:none;' ?> margin-top: 5px;"
                                value="<?= htmlspecialchars($otherFamilyCondition) ?>">
                        </div>
                    </div>
                </fieldset>


                <div class="form-navigation">
                    <button type="button" class="prev-btn btn btn-secondary" onclick="prevStep2()">Previous</button>
                    <button type="submit" class="submit-btn btn btn-success">Submit Form</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Bootstrap Modal -->
    <div class="modal fade" id="changePicModal" tabindex="-1" aria-labelledby="changePicModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="changePicModalLabel">Profile Picture Update</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Please update your profile picture to a clear and recent one.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Okay</button>
                </div>
            </div>
        </div>
    </div>



    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script src="js/form_update.js"></script>

    <script>
        window.addEventListener('DOMContentLoaded', function() {
            const selected = document.querySelector('input[name="hospital_admission"]:checked');
            toggleSurgeryFields(selected?.value === 'Yes');
        });
    </script>

    <script>
        if (!window.bootstrap) {
            document.write('<script src="/js/bootstrap.bundle.min.js"><\/script>');
        }
    </script>

    <script>
        const selectedCourse = "<?= formValue('course') ?>";
        window.addEventListener('DOMContentLoaded', () => {
            updateCourseDropdown();
        });
    </script>

    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var changePicModal = new bootstrap.Modal(document.getElementById('changePicModal'));
            changePicModal.show();
        });
    </script>

    <script>
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

    <script>
        function toggleOtherPastIllness() {
            const checkbox = document.getElementById('otherPastIllnessCheckbox');
            const input = document.getElementById('otherPastIllnessInput');
            input.style.display = checkbox.checked ? 'block' : 'none';
        }

        function toggleCancerInput() {
            const checkbox = document.getElementById('cancerCheckbox');
            const input = document.getElementById('cancerInput');
            input.style.display = checkbox.checked ? 'block' : 'none';
        }

        function toggleAllergyInput() {
            const checkbox = document.getElementById('allergyCheckbox');
            const input = document.getElementById('allergyInput');
            input.style.display = checkbox.checked ? 'block' : 'none';
        }

        function toggleOtherFamilyInput() {
            const checkbox = document.getElementById('otherFamilyCheckbox');
            const input = document.getElementById('otherFamilyInput');
            input.style.display = checkbox.checked ? 'block' : 'none';
        }

        document.addEventListener('DOMContentLoaded', () => {
            const allergyCheckbox = document.getElementById('allergiesCheckbox');
            const specifyContainer = document.getElementById('allergiesSpecifyContainer');

            // Function to toggle visibility
            function toggleSpecify() {
                if (allergyCheckbox.checked) {
                    specifyContainer.style.display = 'block';
                } else {
                    specifyContainer.style.display = 'none';
                }
            }

            // Initial check on page load
            toggleSpecify();

            // Add event listener
            allergyCheckbox.addEventListener('change', toggleSpecify);
        });


        document.addEventListener('DOMContentLoaded', () => {
            const otherCheckbox = document.getElementById('otherCheckbox');
            const otherSpecifyContainer = document.getElementById('otherSpecifyContainer');

            function toggleOther() {
                otherCheckbox.checked ?
                    otherSpecifyContainer.style.display = 'block' :
                    otherSpecifyContainer.style.display = 'none';
            }

            toggleOther(); // initial check (for edit pages)

            otherCheckbox.addEventListener('change', toggleOther);
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