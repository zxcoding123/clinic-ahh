<?php
session_start();

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Sanitize input function
function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

include 'config.php';

$userId = $_SESSION['user_id'];

// Fetch parent, students, and related data
$parent = null;
$students = [];
$userEmail = '';

$parentQuery = $conn->prepare("SELECT * FROM parents WHERE user_id = ?");
$parentQuery->bind_param("i", $userId);
$parentQuery->execute();
$parent = $parentQuery->get_result()->fetch_assoc();

if ($parent) {
    $studentsQuery = $conn->prepare("
        SELECT s.*, 
               ec.surname AS ec_surname, ec.firstname AS ec_firstname, ec.middlename AS ec_middlename,
               ec.contact_number AS ec_contact_number, ec.relationship AS ec_relationship,
               ec.city_address AS ec_city_address,
               smi.illnesses, smi.medications, smi.vaccination_status, smi.menstruation_age,
               smi.menstrual_pattern, smi.pregnancies, smi.live_children, smi.menstrual_symptoms,
               smi.past_illnesses, smi.hospital_admissions, smi.family_history,
               smi.other_illness, smi.other_past_illness, smi.other_family_history, smi.other_symptoms
        FROM students s
        LEFT JOIN emergency_contacts ec ON s.id = ec.student_id
        LEFT JOIN student_medical_info smi ON s.id = smi.student_id
        WHERE s.parent_id = ?
    ");
    $studentsQuery->bind_param("i", $parent['id']);
    $studentsQuery->execute();
    $students = $studentsQuery->get_result()->fetch_all(MYSQLI_ASSOC);
}

$emailQuery = $conn->prepare("SELECT email FROM users WHERE id = ?");
$emailQuery->bind_param("i", $userId);
$emailQuery->execute();
$userEmail = $emailQuery->get_result()->fetch_assoc()['email'] ?? '';

// Handle form update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = 'Invalid form submission';
        header("Location: /StudentProfileUpdate");
        exit();
    }

    try {
        $conn->begin_transaction();

        foreach ($students as $index => $student) {
            $studentId = $student['id'];

            // Validate required fields
            $required = [
                "surname$index" => "Surname",
                "firstname$index" => "First name",
                "birthday$index" => "Birthday",
                "sex$index" => "Gender",
                "gradeLevel$index" => "Grade level",
                "email$index" => "Email"
            ];

            foreach ($required as $field => $name) {
                if (empty(trim($_POST[$field] ?? ''))) {
                    throw new Exception("$name is required for student " . ($index + 1));
                }
            }

            if (!filter_var($_POST["email$index"], FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email format for student " . ($index + 1));
            }

            // Emergency contact validation
            $emergencyRequired = [
                "emergencySurname$index" => "Emergency contact surname",
                "emergencyFirstname$index" => "Emergency contact first name",
                "emergencyContactNumber$index" => "Emergency contact number",
                "emergencyRelationship$index" => "Emergency contact relationship",
                "emergencyCityAddress$index" => "Emergency contact city address"
            ];

            foreach ($emergencyRequired as $field => $name) {
                if (empty(trim($_POST[$field] ?? ''))) {
                    throw new Exception("$name is required for student " . ($index + 1));
                }
            }

            // Process file upload
            $photoPath = $student['photo_path'];
            if (!empty($_FILES["studentPhoto$index"]['tmp_name'])) {
                $photoPath = uploadFile($_FILES["studentPhoto$index"]);
            }

            // Student data
            $religion = sanitizeInput($_POST["religion$index"] ?? '');
            if ($religion === 'OTHER' && !empty($_POST["other_religion$index"])) {
                $religion = 'OTHER: ' . sanitizeInput($_POST["other_religion$index"]);
            }

            $data = [
                'surname' => sanitizeInput($_POST["surname$index"]),
                'firstname' => sanitizeInput($_POST["firstname$index"]),
                'middlename' => sanitizeInput($_POST["middlename$index"] ?? ''),
                'suffix' => sanitizeInput($_POST["suffix$index"] ?? ''),
                'birthday' => sanitizeInput($_POST["birthday$index"]),
                'age' => filter_var($_POST["age$index"], FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1, 'max_range' => 100]
                ]),
                'sex' => in_array(strtolower($_POST["sex$index"]), ['male', 'female']) ? 
                         strtolower($_POST["sex$index"]) : null,
                'blood_type' => sanitizeInput($_POST["bloodType$index"] ?? ''),
                'grade_level' => sanitizeInput($_POST["gradeLevel$index"]),
                'grading_quarter' => sanitizeInput($_POST["gradingQuarter$index"] ?? ''),
                'religion' => $religion,
                'nationality' => sanitizeInput($_POST["nationality$index"] ?? 'Filipino'),
                'email' => filter_var($_POST["email$index"], FILTER_SANITIZE_EMAIL),
                'contact_number' => preg_replace('/[^0-9]/', '', $_POST["contactNumber$index"] ?? ''),
                'city_address' => sanitizeInput($_POST["cityAddress$index"] ?? ''),
                'provincial_address' => sanitizeInput($_POST["provincialAddress$index"] ?? ''),
                'photo_path' => $photoPath
            ];

            // Update student
            $stmt = $conn->prepare("UPDATE students SET 
                surname = ?, firstname = ?, middlename = ?, suffix = ?, birthday = ?, age = ?, 
                sex = ?, blood_type = ?, grade_level = ?, grading_quarter = ?, religion = ?, 
                nationality = ?, email = ?, contact_number = ?, city_address = ?, 
                provincial_address = ?, photo_path = ?
                WHERE id = ?");
            $stmt->bind_param(
                "sssssisssssssssssi",
                $data['surname'], $data['firstname'], $data['middlename'], $data['suffix'],
                $data['birthday'], $data['age'], $data['sex'], $data['blood_type'],
                $data['grade_level'], $data['grading_quarter'], $data['religion'],
                $data['nationality'], $data['email'], $data['contact_number'],
                $data['city_address'], $data['provincial_address'], $data['photo_path'],
                $studentId
            );
            if (!$stmt->execute()) {
                throw new Exception("Student update failed: " . $stmt->error);
            }

            // Emergency contact data
            $emergencyData = [
                'surname' => sanitizeInput($_POST["emergencySurname$index"] ?? ''),
                'firstname' => sanitizeInput($_POST["emergencyFirstname$index"] ?? ''),
                'middlename' => sanitizeInput($_POST["emergencyMiddlename$index"] ?? ''),
                'contact_number' => preg_replace('/[^0-9]/', '', $_POST["emergencyContactNumber$index"] ?? ''),
                'relationship' => sanitizeInput($_POST["emergencyRelationship$index"] ?? ''),
                'city_address' => sanitizeInput($_POST["emergencyCityAddress$index"] ?? '')
            ];

            if ($emergencyData['relationship'] === 'Other' && !empty($_POST["other_relationship$index"])) {
                $emergencyData['relationship'] = 'OTHER: ' . sanitizeInput($_POST["other_relationship$index"]);
            }

            // Update emergency contact
            $emergencyStmt = $conn->prepare("UPDATE emergency_contacts SET 
                surname = ?, firstname = ?, middlename = ?, contact_number = ?, 
                relationship = ?, city_address = ?
                WHERE student_id = ?");
            $emergencyStmt->bind_param(
                "ssssssi",
                $emergencyData['surname'], $emergencyData['firstname'], $emergencyData['middlename'],
                $emergencyData['contact_number'], $emergencyData['relationship'],
                $emergencyData['city_address'], $studentId
            );
            if (!$emergencyStmt->execute()) {
                throw new Exception("Emergency contact update failed: " . $emergencyStmt->error);
            }

            // Medical info
            $medicalData = [
                'illnesses' => isset($_POST["illness$index"]) ? 
                    implode(",", array_map('sanitizeInput', (array)$_POST["illness$index"])) : null,
                'medications' => isset($_POST["medications$index"]) ? 
                    json_encode(array_map(function($v) { 
                        return array_map('sanitizeInput', (array)$v);
                    }, (array)$_POST["medications$index"])) : null,
                'vaccination' => sanitizeInput($_POST["vaccination$index"] ?? ''),
                'menstruation_age' => filter_var($_POST["menstruationAge$index"] ?? 0, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 0, 'max_range' => 30]
                ]),
                'menstrual_pattern' => sanitizeInput($_POST["menstrualPattern$index"] ?? ''),
                'pregnancies' => filter_var($_POST["pregnancies$index"] ?? 0, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 0, 'max_range' => 20]
                ]),
                'live_children' => filter_var($_POST["liveChildren$index"] ?? 0, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 0, 'max_range' => 20]
                ]),
                'menstrual_symptoms' => isset($_POST["menstrual_symptoms$index"]) ? 
                    implode(",", array_map('sanitizeInput', (array)$_POST["menstrual_symptoms$index"])) : null,
                'past_illnesses' => isset($_POST["past_illness$index"]) ? 
                    implode(",", array_map('sanitizeInput', (array)$_POST["past_illness$index"])) : null,
                'hospital_admissions' => isset($_POST["hospital_admissions$index"]) ? 
                    json_encode(array_map(function($item) {
                        return [
                            'year' => sanitizeInput($item['year'] ?? ''),
                            'reason' => sanitizeInput($item['reason'] ?? '')
                        ];
                    }, (array)$_POST["hospital_admissions$index"])) : null,
                'family_history' => isset($_POST["family_history$index"]) ? 
                    implode(",", array_map('sanitizeInput', (array)$_POST["family_history$index"])) : null,
                'other_illness' => sanitizeInput($_POST["otherIllness$index"] ?? ''),
                'other_past_illness' => sanitizeInput($_POST["other_past_illness$index"] ?? ''),
                'other_family_history' => sanitizeInput($_POST["other_family_history$index"] ?? ''),
                'other_symptoms' => sanitizeInput($_POST["otherSymptoms$index"] ?? '')
            ];

            $medicalStmt = $conn->prepare("UPDATE student_medical_info SET 
                illnesses = ?, medications = ?, vaccination_status = ?, menstruation_age = ?, 
                menstrual_pattern = ?, pregnancies = ?, live_children = ?, menstrual_symptoms = ?, 
                past_illnesses = ?, hospital_admissions = ?, family_history = ?,
                other_illness = ?, other_past_illness = ?, other_family_history = ?, other_symptoms = ?
                WHERE student_id = ?");
            $medicalStmt->bind_param(
                "sssisiiisssssssi",
                $medicalData['illnesses'], $medicalData['medications'], $medicalData['vaccination'],
                $medicalData['menstruation_age'], $medicalData['menstrual_pattern'],
                $medicalData['pregnancies'], $medicalData['live_children'],
                $medicalData['menstrual_symptoms'], $medicalData['past_illnesses'],
                $medicalData['hospital_admissions'], $medicalData['family_history'],
                $medicalData['other_illness'], $medicalData['other_past_illness'],
                $medicalData['other_family_history'], $medicalData['other_symptoms'],
                $studentId
            );
            if (!$medicalStmt->execute()) {
                throw new Exception("Medical info update failed: " . $medicalStmt->error);
            }
        }

        $conn->commit();
        $_SESSION['success'] = "Profile updated successfully!";
        header("Location: /StudentProfileUpdate");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Update failed: " . $e->getMessage();
        header("Location: /StudentProfileUpdate");
        exit();
    }
}

// File upload function (same as original)
function uploadFile($file, $uploadDir = '../Uploads/student_photos/') {
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
            throw new Exception('No file uploaded');
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
    $maxSize = 2 * 1024 * 1024;

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!array_key_exists($mimeType, $allowedTypes)) {
        throw new Exception('Invalid file type. Allowed: JPG, PNG, GIF');
    }

    if ($file['size'] > $maxSize) {
        throw new Exception('File exceeds 2MB limit');
    }

    if (!getimagesize($file['tmp_name'])) {
        throw new Exception('File is not a valid image');
    }

    $extension = $allowedTypes[$mimeType];
    $fileName = sprintf('%s.%s', bin2hex(random_bytes(16)), $extension);
    $targetPath = $uploadDir . $fileName;

    if (!is_uploaded_file($file['tmp_name']) || 
        !move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('Failed to process uploaded file');
    }

    return $targetPath;
}

// Display messages
function displayMessage($type) {
    if (isset($_SESSION[$type])) {
        echo '<div class="alert alert-'.($type === 'success' ? 'success' : 'danger').'">' . 
             htmlspecialchars($_SESSION[$type]) . '</div>';
        unset($_SESSION[$type]);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Student Health Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/profiles.css">
    <style>
        @media print {
            .no-print { display: none; }
            .print-container { 
                width: 100%;
                margin: 0;
                padding: 20px;
            }
            .student-form { display: block !important; }
            .form-navigation { display: none; }
            .tab-container { display: none; }
            fieldset { 
                border: 1px solid #000;
                padding: 10px;
                margin-bottom: 20px;
            }
            legend { 
                font-size: 1.2em;
                font-weight: bold;
            }
            .form-group label { 
                font-weight: bold;
                margin-right: 10px;
            }
            .form-group { margin-bottom: 10px; }
            input, select { 
                border: none !important;
                background: transparent !important;
            }
        }
    </style>
</head>
<body>
    <!-- Validation Modal -->
    <div class="modal fade validation-modal" id="validationModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Incomplete Form Submission</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Please correct the following errors to continue:</p>
                    <ul id="errorList" class="error-list"></ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Okay</button>
                </div>
            </div>
        </div>
    </div>

    <div class="mainContainer print-container">
        <?php 
        displayMessage('success');
        displayMessage('error');
        ?>
        <form class="health-profile-form" id="healthProfileForm" action="/StudentProfileUpdate" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="update" value="1">
            <div class="form-header">
                <h1>PATIENT HEALTH PROFILE</h1>
                <button type="button" class="btn btn-primary no-print" onclick="window.print()">Print Records</button>
            </div>

            <!-- Student Tabs -->
            <div class="tab-container mb-3 no-print" id="studentTabs">
                <?php foreach ($students as $index => $student): ?>
                    <div class="student-tab <?= $index === 0 ? 'active' : '' ?>" 
                         data-target="studentForm<?= $index ?>">
                        <?= htmlspecialchars($student['firstname'] . ' ' . $student['surname']) ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Student Forms -->
            <?php foreach ($students as $index => $student): ?>
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
                                    <img id="previewImage<?= $index ?>" 
                                         src="<?= $student['photo_path'] ? htmlspecialchars($student['photo_path']) : '#' ?>" 
                                         alt="Student Photo" style="display: <?= $student['photo_path'] ? 'block' : 'none' ?>;">
                                    <span id="uploadText<?= $index ?>" style="display: <?= $student['photo_path'] ? 'none' : 'block' ?>;">+</span>
                                </div>
                            </div>

                            <div class="personal-info-grid">
                                <div class="form-group full-width">
                                    <label>Name:</label>
                                    <div class="name-inputs">
                                        <div class="input-wrapper">
                                            <input type="text" placeholder="Surname" name="surname<?= $index ?>" 
                                                   value="<?= htmlspecialchars($student['surname']) ?>" required>
                                            <div class="invalid-feedback">Please enter the surname</div>
                                        </div>
                                        <div class="input-wrapper">
                                            <input type="text" placeholder="First name" name="firstname<?= $index ?>" 
                                                   value="<?= htmlspecialchars($student['firstname']) ?>" required>
                                            <div class="invalid-feedback">Please enter the first name</div>
                                        </div>
                                        <div class="input-wrapper">
                                            <input type="text" placeholder="Middle name" name="middlename<?= $index ?>" 
                                                   value="<?= htmlspecialchars($student['middlename']) ?>">
                                        </div>
                                        <div class="input-wrapper">
                                            <input type="text" placeholder="Suffix" name="suffix<?= $index ?>" 
                                                   value="<?= htmlspecialchars($student['suffix']) ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="birthday<?= $index ?>">Birthday:</label>
                                    <input type="date" id="birthday<?= $index ?>" name="birthday<?= $index ?>" 
                                           value="<?= htmlspecialchars($student['birthday']) ?>" required 
                                           onchange="calculateAge(<?= $index ?>)">
                                    <div class="invalid-feedback" id="birthdayError<?= $index ?>">Please select a valid birthday</div>
                                </div>

                                <div class="form-group">
                                    <label for="age<?= $index ?>">Age:</label>
                                    <input type="number" id="age<?= $index ?>" name="age<?= $index ?>" 
                                           value="<?= htmlspecialchars($student['age']) ?>" class="age-input" readonly>
                                </div>

                                <div class="form-group">
                                    <label for="sex<?= $index ?>">Sex:</label>
                                    <select id="sex<?= $index ?>" name="sex<?= $index ?>" required 
                                            onchange="toggleMenstrualSection(<?= $index ?>)">
                                        <option value="">Select</option>
                                        <option value="male" <?= $student['sex'] === 'male' ? 'selected' : '' ?>>Male</option>
                                        <option value="female" <?= $student['sex'] === 'female' ? 'selected' : '' ?>>Female</option>
                                    </select>
                                    <div class="invalid-feedback">Please select a gender</div>
                                </div>

                                <div class="form-group">
                                    <label for="bloodType<?= $index ?>">Blood Type:</label>
                                    <select id="bloodType<?= $index ?>" name="bloodType<?= $index ?>">
                                        <option value="">Select</option>
                                        <option value="A+" <?= $student['blood_type'] === 'A+' ? 'selected' : '' ?>>A+</option>
                                        <option value="A-" <?= $student['blood_type'] === 'A-' ? 'selected' : '' ?>>A-</option>
                                        <option value="B+" <?= $student['blood_type'] === 'B+' ? 'selected' : '' ?>>B+</option>
                                        <option value="B-" <?= $student['blood_type'] === 'B-' ? 'selected' : '' ?>>B-</option>
                                        <option value="AB+" <?= $student['blood_type'] === 'AB+' ? 'selected' : '' ?>>AB+</option>
                                        <option value="AB-" <?= $student['blood_type'] === 'AB-' ? 'selected' : '' ?>>AB-</option>
                                        <option value="O+" <?= $student['blood_type'] === 'O+' ? 'selected' : '' ?>>O+</option>
                                        <option value="O-" <?= $student['blood_type'] === 'O-' ? 'selected' : '' ?>>O-</option>
                                        <option value="unknown" <?= $student['blood_type'] === 'unknown' ? 'selected' : '' ?>>Unknown</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="gradeLevel<?= $index ?>">Grade:</label>
                                    <select id="gradeLevel<?= $index ?>" name="gradeLevel<?= $index ?>" required>
                                        <option value="">Select</option>
                                        <option value="kinder1" <?= $student['grade_level'] === 'kinder1' ? 'selected' : '' ?>>Kindergarten 1</option>
                                        <option value="kinder2" <?= $student['grade_level'] === 'kinder2' ? 'selected' : '' ?>>Kindergarten 2</option>
                                        <option value="1" <?= $student['grade_level'] === '1' ? 'selected' : '' ?>>Grade 1</option>
                                        <option value="2" <?= $student['grade_level'] === '2' ? 'selected' : '' ?>>Grade 2</option>
                                        <option value="3" <?= $student['grade_level'] === '3' ? 'selected' : '' ?>>Grade 3</option>
                                        <option value="4" <?= $student['grade_level'] === '4' ? 'selected' : '' ?>>Grade 4</option>
                                        <option value="5" <?= $student['grade_level'] === '5' ? 'selected' : '' ?>>Grade 5</option>
                                        <option value="6" <?= $student['grade_level'] === '6' ? 'selected' : '' ?>>Grade 6</option>
                                    </select>
                                    <div class="invalid-feedback">Please select a grade level</div>
                                </div>

                                <div class="form-group">
                                    <label for="gradingQuarter<?= $index ?>">Grading/Quarter:</label>
                                    <select id="gradingQuarter<?= $index ?>" name="gradingQuarter<?= $index ?>" required>
                                        <option value="">Select</option>
                                        <option value="1" <?= $student['grading_quarter'] === '1' ? 'selected' : '' ?>>1</option>
                                        <option value="2" <?= $student['grading_quarter'] === '2' ? 'selected' : '' ?>>2</option>
                                        <option value="3" <?= $student['grading_quarter'] === '3' ? 'selected' : '' ?>>3</option>
                                        <option value="4" <?= $student['grading_quarter'] === '4' ? 'selected' : '' ?>>4</option>
                                    </select>
                                    <div class="invalid-feedback">Please select a grading quarter</div>
                                </div>

                                <div class="form-group">
                                    <label for="religion<?= $index ?>">Religion:</label>
                                    <?php
                                    $religion = $student['religion'];
                                    $otherReligion = '';
                                    if (strpos($religion, 'OTHER:') === 0) {
                                        $otherReligion = substr($religion, 6);
                                        $religion = 'OTHER';
                                    }
                                    ?>
                                    <select id="religion<?= $index ?>" name="religion<?= $index ?>" required 
                                            onchange="toggleOtherReligionInput(<?= $index ?>)">
                                        <option value="">Select Religion</option>
                                        <option value="Roman Catholic" <?= $religion === 'Roman Catholic' ? 'selected' : '' ?>>Roman Catholic</option>
                                        <option value="Islam" <?= $religion === 'Islam' ? 'selected' : '' ?>>Islam</option>
                                        <option value="Iglesia ni Cristo" <?= $religion === 'Iglesia ni Cristo' ? 'selected' : '' ?>>Iglesia ni Cristo</option>
                                        <option value="Protestant" <?= $religion === 'Protestant' ? 'selected' : '' ?>>Protestant</option>
                                        <option value="Born Again Christian" <?= $religion === 'Born Again Christian' ? 'selected' : '' ?>>Born Again Christian</option>
                                        <option value="Seventh-day Adventist" <?= $religion === 'Seventh-day Adventist' ? 'selected' : '' ?>>Seventh-day Adventist</option>
                                        <option value="Jehovah's Witness" <?= $religion === "Jehovah's Witness" ? 'selected' : '' ?>>Jehovah's Witness</option>
                                        <option value="Buddhist" <?= $religion === 'Buddhist' ? 'selected' : '' ?>>Buddhist</option>
                                        <option value="OTHER" <?= $religion === 'OTHER' ? 'selected' : '' ?>>Others (Please specify)</option>
                                    </select>
                                    <div id="otherReligionWrapper<?= $index ?>" style="display: <?= $religion === 'OTHER' ? 'block' : 'none' ?>; margin-top: 10px;">
                                        <input type="text" id="otherReligion<?= $index ?>" 
                                               name="other_religion<?= $index ?>" 
                                               placeholder="Please specify religion" 
                                               class="form-control"
                                               value="<?= htmlspecialchars($otherReligion) ?>">
                                    </div>
                                    <div class="invalid-feedback">Please select or specify your religion</div>
                                </div>

                                <div class="form-group">
                                    <label for="nationality<?= $index ?>">Nationality:</label>
                                    <input type="text" id="nationality<?= $index ?>" name="nationality<?= $index ?>" 
                                           value="<?= htmlspecialchars($student['nationality']) ?>" required>
                                    <div class="invalid-feedback">Please enter the nationality</div>
                                </div>

                                <div class="form-group">
                                    <label for="email<?= $index ?>">Email Address:</label>
                                    <input type="email" id="email<?= $index ?>" name="email<?= $index ?>" 
                                           value="<?= htmlspecialchars($student['email']) ?>" required>
                                    <div class="invalid-feedback">Please enter a valid email address</div>
                                </div>

                                <div class="form-group">
                                    <label for="contactNumber<?= $index ?>">Contact Number:</label>
                                    <input type="tel" id="contactNumber<?= $index ?>" 
                                           name="contactNumber<?= $index ?>" 
                                           pattern="09[0-9]{9}"
                                           maxlength="11"
                                           oninput="validatePhoneNumber(this)"
                                           value="<?= htmlspecialchars($student['contact_number']) ?>" required>
                                    <small class="form-text text-muted">Format: 09XXXXXXXXX (11 digits)</small>
                                    <div class="invalid-feedback">Please enter a valid 11-digit contact number starting with 09</div>
                                </div>

                                <div class="form-group full-width">
                                    <label for="cityAddress<?= $index ?>">City Address:</label>
                                    <input type="text" id="cityAddress<?= $index ?>" name="cityAddress<?= $index ?>" 
                                           value="<?= htmlspecialchars($student['city_address']) ?>" required>
                                    <div class="invalid-feedback">Please enter the city address</div>
                                </div>

                                <div class="form-group full-width">
                                    <label for="provincialAddress<?= $index ?>">Provincial Address (if applicable):</label>
                                    <input type="text" id="provincialAddress<?= $index ?>" name="provincialAddress<?= $index ?>" 
                                           value="<?= htmlspecialchars($student['provincial_address']) ?>">
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
                                               onchange="toggleEmergencyContactCopy(<?= $index ?>)">
                                        Same as first student
                                    </label>
                                </div>
                                <?php endif; ?>

                                <div class="form-group full-width">
                                    <label>Name:</label>
                                    <div class="name-inputs">
                                        <div class="input-wrapper">
                                            <input type="text" id="visibleEmergencySurname<?= $index ?>" 
                                                   placeholder="Surname" 
                                                   <?= $index > 0 ? 'disabled' : '' ?>
                                                   value="<?= htmlspecialchars($student['ec_surname']) ?>"
                                                   onchange="if(<?= $index ?> === 0) copyEmergencyContactToAll()">
                                            <input type="hidden" id="hiddenEmergencySurname<?= $index ?>" 
                                                   name="emergencySurname<?= $index ?>" 
                                                   value="<?= htmlspecialchars($student['ec_surname']) ?>">
                                            <div class="invalid-feedback">Please enter the emergency contact surname</div>
                                        </div>
                                        <div class="input-wrapper">
                                            <input type="text" id="visibleEmergencyFirstname<?= $index ?>" 
                                                   placeholder="First name" 
                                                   <?= $index > 0 ? 'disabled' : '' ?>
                                                   value="<?= htmlspecialchars($student['ec_firstname']) ?>"
                                                   onchange="if(<?= $index ?> === 0) copyEmergencyContactToAll()">
                                            <input type="hidden" id="hiddenEmergencyFirstname<?= $index ?>" 
                                                   name="emergencyFirstname<?= $index ?>" 
                                                   value="<?= htmlspecialchars($student['ec_firstname']) ?>">
                                            <div class="invalid-feedback">Please enter the emergency contact first name</div>
                                        </div>
                                        <div class="input-wrapper">
                                            <input type="text" id="visibleEmergencyMiddlename<?= $index ?>" 
                                                   placeholder="Middle name" 
                                                   <?= $index > 0 ? 'disabled' : '' ?>
                                                   value="<?= htmlspecialchars($student['ec_middlename']) ?>"
                                                   onchange="if(<?= $index ?> === 0) copyEmergencyContactToAll()">
                                            <input type="hidden" id="hiddenEmergencyMiddlename<?= $index ?>" 
                                                   name="emergencyMiddlename<?= $index ?>" 
                                                   value="<?= htmlspecialchars($student['ec_middlename']) ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="visibleEmergencyContactNumber<?= $index ?>">Contact Number:</label>
                                    <input type="tel" id="visibleEmergencyContactNumber<?= $index ?>" 
                                           <?= $index > 0 ? 'disabled' : '' ?>
                                           pattern="09[0-9]{9}"
                                           maxlength="11"
                                           value="<?= htmlspecialchars($student['ec_contact_number']) ?>"
                                           onchange="if(<?= $index ?> === 0) copyEmergencyContactToAll()"
                                           oninput="validatePhoneNumber(this)">
                                    <input type="hidden" id="hiddenEmergencyContactNumber<?= $index ?>" 
                                           name="emergencyContactNumber<?= $index ?>" 
                                           value="<?= htmlspecialchars($student['ec_contact_number']) ?>">
                                    <small class="form-text text-muted">Format: 09XXXXXXXXX (11 digits)</small>
                                    <div class="invalid-feedback">Please enter a valid 11-digit emergency contact number starting with 09</div>
                                </div>

                                <div class="form-group">
                                    <?php
                                    $ecRelationship = $student['ec_relationship'];
                                    $otherRelationship = '';
                                    if (strpos($ecRelationship, 'OTHER:') === 0) {
                                        $otherRelationship = substr($ecRelationship, 6);
                                        $ecRelationship = 'Other';
                                    }
                                    ?>
                                    <label for="visibleEmergencyRelationship<?= $index ?>">Relationship:</label>
                                    <select id="visibleEmergencyRelationship<?= $index ?>" 
                                            <?= $index > 0 ? 'disabled' : '' ?>
                                            onchange="if(<?= $index ?> === 0) { copyEmergencyContactToAll(); toggleOtherRelationshipInput(<?= $index ?>); } else { toggleOtherRelationshipInput(<?= $index ?>); }">
                                        <option value="">Select Relationship</option>
                                        <option value="Parent" <?= $ecRelationship === 'Parent' ? 'selected' : '' ?>>Parent</option>
                                        <option value="Sibling" <?= $ecRelationship === 'Sibling' ? 'selected' : '' ?>>Sibling</option>
                                        <option value="Spouse" <?= $ecRelationship === 'Spouse' ? 'selected' : '' ?>>Spouse</option>
                                        <option value="Child" <?= $ecRelationship === 'Child' ? 'selected' : '' ?>>Child</option>
                                        <option value="Guardian" <?= $ecRelationship === 'Guardian' ? 'selected' : '' ?>>Guardian</option>
                                        <option value="Friend" <?= $ecRelationship === 'Friend' ? 'selected' : '' ?>>Friend</option>
                                        <option value="Other" <?= $ecRelationship === 'Other' ? 'selected' : '' ?>>Others (Please specify)</option>
                                    </select>
                                    <input type="hidden" id="hiddenEmergencyRelationship<?= $index ?>" 
                                           name="emergencyRelationship<?= $index ?>" 
                                           value="<?= htmlspecialchars($ecRelationship) ?>">
                                    <div id="otherRelationshipWrapper<?= $index ?>" style="display: <?= $ecRelationship === 'Other' ? 'block' : 'none' ?>; margin-top: 10px;">
                                        <input type="text" id="otherRelationship<?= $index ?>" 
                                               name="other_relationship<?= $index ?>" 
                                               placeholder="Please specify relationship" 
                                               class="form-control"
                                               value="<?= htmlspecialchars($otherRelationship) ?>"
                                               <?= $index > 0 ? 'disabled' : '' ?>>
                                    </div>
                                    <div class="invalid-feedback">Please select or specify a relationship</div>
                                </div>

                                <div class="form-group full-width">
                                    <label for="visibleEmergencyCityAddress<?= $index ?>">City Address:</label>
                                    <input type="text" id="visibleEmergencyCityAddress<?= $index ?>" 
                                           <?= $index > 0 ? 'disabled' : '' ?>
                                           value="<?= htmlspecialchars($student['ec_city_address']) ?>"
                                           onchange="if(<?= $index ?> === 0) copyEmergencyContactToAll()">
                                    <input type="hidden" id="hiddenEmergencyCityAddress<?= $index ?>" 
                                           name="emergencyCityAddress<?= $index ?>" 
                                           value="<?= htmlspecialchars($student['ec_city_address']) ?>">
                                    <div class="invalid-feedback">Please enter the emergency contact city address</div>
                                </div>
                            </div>
                        </fieldset>

                        <div class="form-navigation no-print">
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
                                    <?php
                                    $illnesses = $student['illnesses'] ? explode(',', $student['illnesses']) : [];
                                    ?>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="illness<?= $index ?>[]" value="asthma" 
                                               <?= in_array('asthma', $illnesses) ? 'checked' : '' ?>>
                                        Bronchial Asthma ("Hika")
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="illness<?= $index ?>[]" value="allergies" 
                                               <?= in_array('allergies', $illnesses) ? 'checked' : '' ?>>
                                        Food Allergies
                                        <input type="text" placeholder="Specify food" name="allergiesSpecify<?= $index ?>" 
                                               class="inline-input" value="<?= htmlspecialchars($student['other_illness']) ?>">
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="illness<?= $index ?>[]" value="rhinitis" 
                                               <?= in_array('rhinitis', $illnesses) ? 'checked' : '' ?>>
                                        Allergic Rhinitis
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="illness<?= $index ?>[]" value="hyperthyroidism" 
                                               <?= in_array('hyperthyroidism', $illnesses) ? 'checked' : '' ?>>
                                        Hyperthyroidism
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="illness<?= $index ?>[]" value="hypothyroidism" 
                                               <?= in_array('hypothyroidism', $illnesses) ? 'checked' : '' ?>>
                                        Hypothyroidism/Goiter
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="illness<?= $index ?>[]" value="anemia" 
                                               <?= in_array('anemia', $illnesses) ? 'checked' : '' ?>>
                                        Anemia
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="illness<?= $index ?>[]" value="other" 
                                               <?= in_array('other', $illnesses) ? 'checked' : '' ?>>
                                        Other:
                                        <input type="text" placeholder="Specify" name="otherIllness<?= $index ?>" 
                                               class="inline-input" value="<?= htmlspecialchars($student['other_illness']) ?>">
                                    </label>
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
                                    $medications = json_decode($student['medications'] ?? '[]', true);
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
                                           <?= $student['vaccination_status'] === 'fully' ? 'checked' : '' ?>>
                                    Fully vaccinated (Primary series with or without booster shot/s)
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="vaccination<?= $index ?>" value="partially" 
                                           <?= $student['vaccination_status'] === 'partially' ? 'checked' : '' ?>>
                                    Partially vaccinated (Incomplete primary series)
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="vaccination<?= $index ?>" value="not" 
                                           <?= $student['vaccination_status'] === 'not' ? 'checked' : '' ?>>
                                    Not vaccinated
                                </label>
                                <div class="invalid-feedback" style="display: none;">Please select a vaccination status</div>
                            </div>
                        </fieldset>

                        <fieldset class="form-section menstrual-section" id="menstrualSection<?= $index ?>" 
                                  style="display: <?= $student['sex'] === 'female' ? 'block' : 'none' ?>;">
                            <legend>Menstrual History</legend>
                            <p class="form-subtitle">(for females only)</p>
                            <div class="menstrual-grid">
                                <div class="form-group">
                                    <label>Age when menstruation began:</label>
                                    <input type="number" name="menstruationAge<?= $index ?>" class="short-input" 
                                           value="<?= htmlspecialchars($student['menstruation_age']) ?>">
                                </div>
                                <div class="form-group">
                                    <label>Menstrual Pattern:</label>
                                    <div class="radio-group">
                                        <label class="radio-label">
                                            <input type="radio" name="menstrualPattern<?= $index ?>" value="regular" 
                                                   <?= $student['menstrual_pattern'] === 'regular' ? 'checked' : '' ?>>
                                            Regular (monthly)
                                        </label>
                                        <label class="radio-label">
                                            <input type="radio" name="menstrualPattern<?= $index ?>" value="irregular" 
                                                   <?= $student['menstrual_pattern'] === 'irregular' ? 'checked' : '' ?>>
                                            Irregular
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Menstrual Symptoms:</label>
                                    <?php
                                    $menstrualSymptoms = $student['menstrual_symptoms'] ? explode(',', $student['menstrual_symptoms']) : [];
                                    ?>
                                    <div class="checkbox-group">
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="menstrual_symptoms<?= $index ?>[]" value="dysmenorrhea" 
                                                   <?= in_array('dysmenorrhea', $menstrualSymptoms) ? 'checked' : '' ?>>
                                            Dysmenorrhea (cramps)
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="menstrual_symptoms<?= $index ?>[]" value="migraine" 
                                                   <?= in_array('migraine', $menstrualSymptoms) ? 'checked' : '' ?>>
                                            Migraine
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="menstrual_symptoms<?= $index ?>[]" value="consciousness" 
                                                   <?= in_array('consciousness', $menstrualSymptoms) ? 'checked' : '' ?>>
                                            Loss of consciousness
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="menstrual_symptoms<?= $index ?>[]" value="other" 
                                                   <?= in_array('other', $menstrualSymptoms) ? 'checked' : '' ?>>
                                            Other:
                                            <input type="text" class="inline-input" name="otherSymptoms<?= $index ?>" 
                                                   value="<?= htmlspecialchars($student['other_symptoms']) ?>">
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </fieldset>

                        <div class="form-navigation no-print">
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
                                    <?php
                                    $pastIllnesses = $student['past_illnesses'] ? explode(',', $student['past_illnesses']) : [];
                                    ?>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="past_illness<?= $index ?>[]" value="Varicella" 
                                               <?= in_array('Varicella', $pastIllnesses) ? 'checked' : '' ?>>
                                        Varicella (Chicken Pox)
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="past_illness<?= $index ?>[]" value="Dengue" 
                                               <?= in_array('Dengue', $pastIllnesses) ? 'checked' : '' ?>>
                                        Dengue
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="past_illness<?= $index ?>[]" value="Tuberculosis" 
                                               <?= in_array('Tuberculosis', $pastIllnesses) ? 'checked' : '' ?>>
                                        Tuberculosis
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="past_illness<?= $index ?>[]" value="Pneumonia" 
                                               <?= in_array('Pneumonia', $pastIllnesses) ? 'checked' : '' ?>>
                                        Pneumonia
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="past_illness<?= $index ?>[]" value="UTI" 
                                               <?= in_array('UTI', $pastIllnesses) ? 'checked' : '' ?>>
                                        Urinary Tract Infection
                                    </label>
                                </div>
                                <div class="checkbox-column">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="past_illness<?= $index ?>[]" value="Appendicitis" 
                                               <?= in_array('Appendicitis', $pastIllnesses) ? 'checked' : '' ?>>
                                        Appendicitis
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="past_illness<?= $index ?>[]" value="Measles" 
                                               <?= in_array('Measles', $pastIllnesses) ? 'checked' : '' ?>>
                                        Measles
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="past_illness<?= $index ?>[]" value="Amoebiasis" 
                                               <?= in_array('Amoebiasis', $pastIllnesses) ? 'checked' : '' ?>>
                                        Amoebiasis
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="past_illness<?= $index ?>[]" value="Injury" 
                                               <?= in_array('Injury', $pastIllnesses) ? 'checked' : '' ?>>
                                        Injury
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="past_illness<?= $index ?>[]" value="Fracture" 
                                               <?= in_array('Fracture', $pastIllnesses) ? 'checked' : '' ?>>
                                        Fracture
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="otherPastIllnessCheckbox<?= $index ?>" 
                                               onclick="toggleOtherPastIllness(<?= $index ?>)"
                                               <?= in_array('other', $pastIllnesses) ? 'checked' : '' ?>>
                                        Other (Specify)
                                    </label>
                                    <input type="text" class="form-control" id="otherPastIllnessInput<?= $index ?>" 
                                           name="other_past_illness<?= $index ?>" placeholder="Specify other illnesses" 
                                           style="display: <?= in_array('other', $pastIllnesses) ? 'block' : 'none' ?>; width: 300px; margin-top: 5px;"
                                           value="<?= htmlspecialchars($student['other_past_illness']) ?>">
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="form-section">
                            <legend>Hospital Admission / Surgery</legend>
                            <label class="form-label">Has the student ever been admitted to the hospital and/or undergone surgery?</label>
                            <?php
                            $hospitalAdmissions = json_decode($student['hospital_admissions'] ?? '[]', true);
                            $hasAdmissions = !empty($hospitalAdmissions) && $hospitalAdmissions[0]['year'] !== '';
                            ?>
                            <div class="radio-group">
                                <label class="radio-label">
                                    <input type="radio" name="hospital_admission<?= $index ?>" value="No" 
                                           onclick="toggleSurgeryFields(<?= $index ?>, false)"
                                           <?= !$hasAdmissions ? 'checked' : '' ?>>
                                    No
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="hospital_admission<?= $index ?>" value="Yes" 
                                           onclick="toggleSurgeryFields(<?= $index ?>, true)"
                                           <?= $hasAdmissions ? 'checked' : '' ?>>
                                    Yes
                                </label>
                            </div>

                            <div id="surgeryDetails<?= $index ?>" style="display: <?= $hasAdmissions ? 'block' : 'none' ?>; margin-top: 15px;">
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
                                        foreach ($hospitalAdmissions as $admIndex => $admission):
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
                            <label class="form-label">Indicate the known health conditions of the student's immediate family members:</label>
                            <div class="checkbox-grid">
                                <div class="checkbox-column">
                                    <?php
                                    $familyHistory = $student['family_history'] ? explode(',', $student['family_history']) : [];
                                    ?>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="family_history<?= $index ?>[]" value="Hypertension" 
                                               <?= in_array('Hypertension', $familyHistory) ? 'checked' : '' ?>>
                                        Hypertension (Elevated Blood Pressure)
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="family_history<?= $index ?>[]" value="Diabetes Mellitus" 
                                               <?= in_array('Diabetes Mellitus', $familyHistory) ? 'checked' : '' ?>>
                                        Diabetes Mellitus (Elevated Blood Sugar)
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="family_history<?= $index ?>[]" value="Asthma" 
                                               <?= in_array('Asthma', $familyHistory) ? 'checked' : '' ?>>
                                        Asthma
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="family_history<?= $index ?>[]" value="Tuberculosis" 
                                               <?= in_array('Tuberculosis', $familyHistory) ? 'checked' : '' ?>>
                                        Tuberculosis
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="family_history<?= $index ?>[]" value="Cancer" 
                                               <?= in_array('Cancer', $familyHistory) ? 'checked' : '' ?>>
                                        Cancer (Any Type)
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="family_history<?= $index ?>[]" value="Mental Illness" 
                                               <?= in_array('Mental Illness', $familyHistory) ? 'checked' : '' ?>>
                                        Mental Illness (Depression, Schizophrenia, etc.)
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="family_history<?= $index ?>[]" value="Blood Disorder" 
                                               <?= in_array('Blood Disorder', $familyHistory) ? 'checked' : '' ?>>
                                        Blood Disorder (Anemia, Hemophilia, etc.)
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="otherFamilyCheckbox<?= $index ?>" 
                                               onclick="toggleOtherFamilyInput(<?= $index ?>)"
                                               <?= in_array('other', $familyHistory) ? 'checked' : '' ?>>
                                        Other (Specify)
                                    </label>
                                    <input type="text" class="form-control" id="otherFamilyInput<?= $index ?>" 
                                           name="other_family_history<?= $index ?>" placeholder="Specify other conditions" 
                                           style="display: <?= in_array('other', $familyHistory) ? 'block' : 'none' ?>; margin-top: 5px;"
                                           value="<?= htmlspecialchars($student['other_family_history']) ?>">
                                </div>
                            </div>
                        </fieldset>

                        <div class="form-navigation no-print">
                            <button type="button" class="btn btn-secondary" onclick="prevStep2(<?= $index ?>)">Previous</button>
                            <?php if ($index === count($students) - 1): ?>
                                <button type="submit" class="btn btn-success" id="submitBtn">
                                    <span id="submitText">Update All Forms</span>
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-primary" 
                                        onclick="nextStudent(<?= $index ?>)">Next Student</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </form>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="js/Elemform.js"></script>
</body>
</html>