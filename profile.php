<?php
session_start();
require_once 'config.php';

// Enable error logging for debugging (disable in production)
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/php_errors.log');
// ini_set('display_errors', 1); // Uncomment for development only

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log('Unauthorized access attempt: No user_id in session');
    header('Location: /login');
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Fetch user data
try {
    $stmt = $conn->prepare("SELECT id, last_name, first_name, middle_name, email, user_type FROM users WHERE id = ?");
    if (!$stmt) {
        error_log("Prepare failed for user query: " . $conn->error);
        throw new Exception("Database error: Unable to prepare user query");
    }
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        error_log("Execute failed for user query: " . $stmt->error);
        throw new Exception("Database error: Unable to execute user query");
    }
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        error_log("No user found for user_id: $user_id");
        header('Location: /login');
        exit();
    }
} catch (Exception $e) {
    error_log("User query error: " . $e->getMessage());
    http_response_code(500);
    exit();
}

$user_type = $user['user_type'];
$patient_data = [];
$children_data = [];
$consultation_data = [];

// Fetch user-specific data
try {
    if ($user_type === 'Parent') {
        // Fetch parent data
        $stmt = $conn->prepare("SELECT id FROM parents WHERE user_id = ?");
        if (!$stmt) {
            error_log("Prepare failed for parent query: " . $conn->error);
            throw new Exception("Database error: Unable to prepare parent query");
        }
        $stmt->bind_param("i", $user_id);
        if (!$stmt->execute()) {
            error_log("Execute failed for parent query: " . $stmt->error);
            throw new Exception("Database error: Unable to execute parent query");
        }
        $parent = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($parent) {
            // Fetch children data
            $stmt = $conn->prepare("
                SELECT c.patient_id as id, c.last_name, c.first_name, c.middle_name, c.type,
                       CONCAT(c.first_name, ' ', COALESCE(c.middle_name, ''), ' ', c.last_name) AS name
                FROM children c
WHERE parent_id = ?
            ");
            if (!$stmt) {
                error_log("Prepare failed for children query: " . $conn->error);
                throw new Exception("Database error: Unable to prepare children query");
            }
            $stmt->bind_param("i", $user_id);
            if (!$stmt->execute()) {
                error_log("Execute failed for children query: " . $stmt->error);
                throw new Exception("Database error: Unable to execute children query");
            }
            $children_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    } else {
        // Fetch patient data with emergency contact and medical info
        $stmt = $conn->prepare("
            SELECT p.*, 
                   ec.surname AS ec_surname, ec.firstname AS ec_firstname, ec.middlename AS ec_middlename, 
                   ec.contact_number AS ec_contact_number, ec.relationship AS ec_relationship, 
                   ec.city_address AS ec_city_address,
                   mi.illnesses, mi.medications, mi.vaccination_status, mi.menstruation_age, 
                   mi.menstrual_pattern, mi.pregnancies, mi.live_children, mi.menstrual_symptoms, 
                   mi.past_illnesses, mi.hospital_admissions, mi.family_history, mi.other_conditions
            FROM patients p
            LEFT JOIN emergency_contacts ec ON p.id = ec.patient_id
            LEFT JOIN medical_info mi ON p.id = mi.patient_id
            WHERE p.user_id = ?
        ");
        if (!$stmt) {
            error_log("Prepare failed for patient query: " . $conn->error);
            throw new Exception("Database error: Unable to prepare patient query");
        }
        $stmt->bind_param("i", $user_id);
        if (!$stmt->execute()) {
            error_log("Execute failed for patient query: " . $stmt->error);
            throw new Exception("Database error: Unable to execute patient query");
        }
        $patient_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    // Fetch consultation records
    $stmt = $conn->prepare("
        SELECT c.id, c.consultation_date, c.name AS patient_name, c.diagnosis, c.treatment, c.complaints,
               c.staff_signature AS doctor
        FROM consultations c
        WHERE c.patient_id IN (SELECT id FROM patients WHERE user_id = ?)
           OR c.child_id IN (SELECT id FROM children WHERE parent_id IN (SELECT id FROM patients WHERE user_id = ?))
    ");
    if (!$stmt) {
        error_log("Prepare failed for consultations query: " . $conn->error);
        throw new Exception("Database error: Unable to prepare consultations query");
    }
    $stmt->bind_param("ii", $user_id, $user_id);
    if (!$stmt->execute()) {
        error_log("Execute failed for consultations query: " . $stmt->error);
        throw new Exception("Database error: Unable to execute consultations query");
    }
    $consultation_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    error_log("Data fetch error: " . $e->getMessage());
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    try {
        $surname = filter_var($_POST['surname'] ?? '', FILTER_SANITIZE_STRING);
        $firstname = filter_var($_POST['firstname'] ?? '', FILTER_SANITIZE_STRING);
        $middlename = filter_var($_POST['middlename'] ?? '', FILTER_SANITIZE_STRING);
        $birthday = filter_var($_POST['birthday'] ?? '', FILTER_SANITIZE_STRING);
        $age = filter_var($_POST['age'] ?? 0, FILTER_SANITIZE_NUMBER_INT);
        $sex = in_array($_POST['sex'] ?? '', ['male', 'female']) ? $_POST['sex'] : '';
        $blood_type = in_array($_POST['blood_type'] ?? '', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'unknown']) ? $_POST['blood_type'] : 'unknown';
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $contact_number = filter_var($_POST['contact_number'] ?? '', FILTER_SANITIZE_STRING);
        $city_address = filter_var($_POST['city_address'] ?? '', FILTER_SANITIZE_STRING);

        if (empty($surname) || empty($firstname) || empty($birthday) || empty($age) || empty($sex) || empty($email) || empty($contact_number) || empty($city_address)) {
            throw new Exception("All required fields must be filled.");
        }

        $stmt = $conn->prepare("SELECT id FROM patients WHERE user_id = ?");
        if (!$stmt) {
            error_log("Prepare failed for patient check: " . $conn->error);
            throw new Exception("Database error: Unable to check patient existence");
        }
        $stmt->bind_param("i", $user_id);
        if (!$stmt->execute()) {
            error_log("Execute failed for patient check: " . $stmt->error);
            throw new Exception("Database error: Unable to execute patient check");
        }
        $patient = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($patient) {
            $stmt = $conn->prepare("
                UPDATE patients
                SET surname = ?, firstname = ?, middlename = ?, birthday = ?, age = ?, sex = ?, blood_type = ?, 
                    email = ?, contact_number = ?, city_address = ?, updated_at = NOW()
                WHERE user_id = ?
            ");
            if (!$stmt) {
                error_log("Prepare failed for patient update: " . $conn->error);
                throw new Exception("Database error: Unable to prepare patient update");
            }
            $stmt->bind_param("sssissssssi", $surname, $firstname, $middlename, $birthday, $age, $sex, $blood_type, $email, $contact_number, $city_address, $user_id);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO patients (user_id, surname, firstname, middlename, birthday, age, sex, blood_type, email, contact_number, city_address, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            if (!$stmt) {
                error_log("Prepare failed for patient insert: " . $conn->error);
                throw new Exception("Database error: Unable to prepare patient insert");
            }
            $stmt->bind_param("issssisssss", $user_id, $surname, $firstname, $middlename, $birthday, $age, $sex, $blood_type, $email, $contact_number, $city_address);
        }

        if (!$stmt->execute()) {
            error_log("Execute failed for patient update/insert: " . $stmt->error);
            throw new Exception("Database error: Unable to save patient data");
        }
        $stmt->close();

        if ($email !== $user['email']) {
            $stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
            if (!$stmt) {
                error_log("Prepare failed for user email update: " . $conn->error);
                throw new Exception("Database error: Unable to prepare user email update");
            }
            $stmt->bind_param("si", $email, $user_id);
            if (!$stmt->execute()) {
                error_log("Execute failed for user email update: " . $stmt->error);
                throw new Exception("Database error: Unable to update user email");
            }
            $stmt->close();
        }

        header('Location: profile.php?success=Profile updated successfully');
        exit();
    } catch (Exception $e) {
        error_log("Profile update error: " . $e->getMessage());
        header('Location: profile.php?error=' . urlencode($e->getMessage()));
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - WMSU Health Services</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/profile.css">
    <link rel="apple-touch-icon" sizes="180x180" href="images/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="images/favicon-16x16.png">
    <link rel="manifest" href="images/site.webmanifest">
    <style>
        .nav-link {
            color: black !important;
        }

        body {
            font-family: 'Poppins' !important;
        }

        .active {
            background-color: #4f1515 !important;
            color: white !important;
        }

        .document-view {
            font-family: Arial, sans-serif;
            padding: 20px;
        }

        .document-view h3,
        .document-view h4 {
            text-align: center;
            font-weight: bold;
        }

        .document-view h5 {
            margin-top: 20px;
            font-weight: bold;
        }

        .document-view p {
            margin: 5px 0;
        }

        .document-view ul {
            list-style-type: disc;
            margin-left: 20px;
        }

        .document-view hr {
            margin: 20px 0;
        }
    </style>
</head>

<body>
    <div id="app" class="d-flex">
        <button id="sidebar-toggle" class="sidebar-toggle d-lg-none">☰</button>
        <div id="sidebar" class="sidebar d-flex flex-column align-items-center">
            <img src="images/clinic.png" alt="WMSU Clinic Logo" class="logo">
            <div class="text-white fw-bold text-uppercase text-center mt-2">WMSU</div>
            <div class="health-services text-white fw-bold text-uppercase text-center mt-2 mb-5">Health Services</div>
            <button class="btn btn-crimson mb-2 w-100" onclick="window.location.href='homepage.php'">About Us</button>
            <button class="btn btn-crimson mb-2 w-100" onclick="window.location.href='announcements.php'">Announcements</button>
            <button class="btn btn-crimson mb-2 w-100 <?php echo ($user_type === 'Incoming Freshman') ? 'btn-disabled' : ''; ?>"
                id="appointment-btn"
                <?php echo ($user_type === 'Incoming Freshman') ? 'disabled' : ''; ?>
                onclick="<?php echo ($user_type === 'Incoming Freshman') ? '' : 'window.location.href=\'/wmsu/appointment.php\''; ?>">
                Appointment Request
            </button>
            <button class="btn btn-crimson mb-2 w-100" onclick="window.location.href='upload.php'">Upload Medical Documents</button>
            <button class="btn btn-crimson mb-2 w-100 active" onclick="window.location.href='profile.php'">Profile</button>
            <button class="btn btn-crimson w-100" id="logout-btn" data-bs-toggle="modal" data-bs-target="#logoutModal">Logout</button>
            <a href="https://wmsu.edu.ph" target="_blank" class="quick-link">wmsu.edu.ph</a>
        </div>
        <div class="overlay"></div>
        <div class="main-content">
            <div class="profile-container">
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($_GET['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php elseif (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($_GET['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <div class="profile-card">
                    <div class="profile-header">
                        <h1 class="profile-title">My Profile</h1>
                        <div class="settings">
                            <button class="settings-btn" onclick="openSettings()">
                                <i class="bi bi-gear-fill me-1"></i> Settings
                            </button>
                        </div>
                    </div>

                    <ul class="nav nav-tabs" id="profileTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="account-tab" data-bs-toggle="tab" data-bs-target="#account" type="button" role="tab">Account Info</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="patient-tab" data-bs-toggle="tab" data-bs-target="#patient" type="button" role="tab">Patient Profile</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="consultations-tab" data-bs-toggle="tab" data-bs-target="#consultations" type="button" role="tab">Consultation Records</button>
                        </li>
                    </ul>

                    <div class="tab-content" id="profileTabsContent">
                        <!-- Account Info Tab -->
                        <div class="tab-pane fade show active" id="account" role="tabpanel">
                            <div class="profile-info">
                                <div class="info-item">
                                    <div class="info-label">Last Name</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user['last_name']); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">First Name</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user['first_name']); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Middle Name</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user['middle_name'] ?? 'N/A'); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Email Address</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">User Type</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user_type); ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Patient Profile Tab -->
                        <div class="tab-pane fade" id="patient" role="tabpanel">
                            <div class="patient-section">
                                <h4>Patient Profiles</h4>
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Patient Name</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($user_type === 'Parent'): ?>
                                                <?php foreach ($children_data as $child): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($child['name']); ?></td>
                                                        <td>
                                                            <a href="temp_patient_v2.php?id=<?php echo $child['id']; ?>" class="btn btn-sm btn-primary">
                                                                <i class="bi bi-eye"></i> View
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <?php foreach ($patient_data as $patient): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($patient['firstname'] . ' ' . $patient['surname']); ?></td>
                                                        <td>
                                                            <a href="temp_patient_v1.php?id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-primary">
                                                                <i class="bi bi-eye"></i> View
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Consultation Records Tab -->
                        <div class="tab-pane fade" id="consultations" role="tabpanel">
                            <div class="consultations-section">
                                <h4>Consultation Records</h4>
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Patient Name</th>
                                                <th>Diagnosis</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($consultation_data as $consultation): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($consultation['consultation_date']); ?></td>
                                                    <td><?php echo htmlspecialchars($consultation['patient_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($consultation['diagnosis'] ?: 'N/A'); ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-primary view-consultation-btn"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#viewConsultationModal"
                                                            data-date="<?php echo htmlspecialchars($consultation['consultation_date']); ?>"
                                                            data-doctor="<?php echo htmlspecialchars($consultation['doctor']); ?>"
                                                            data-diagnosis="<?php echo htmlspecialchars($consultation['diagnosis']); ?>"
                                                            data-prescription="<?php echo htmlspecialchars($consultation['treatment']); ?>"
                                                            data-notes="<?php echo htmlspecialchars($consultation['complaints']); ?>">
                                                            <i class="bi bi-eye"></i> View
                                                        </button>
                                                        <a href="/download_consultation?id=<?php echo $consultation['id']; ?>"
                                                            class="btn btn-sm btn-secondary">
                                                            <i class="bi bi-download"></i> Download
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Logout Confirmation Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #a6192e;">
                    <h5 class="modal-title text-white">Confirm Logout</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to logout?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>
                    <button type="button" class="btn text-white" style="background-color: #a6192e;" onclick="window.location.href='logout.php'">Yes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <div class="modal fade" id="settingsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Account Settings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="profile.php">
                        <input type="hidden" name="update_profile" value="1">
                        <div class="mb-3">
                            <label for="surname" class="form-label">Surname</label>
                            <input type="text" class="form-control" id="surname" name="surname" value="<?php echo htmlspecialchars($patient_data[0]['surname'] ?? $user['last_name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="firstname" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="firstname" name="firstname" value="<?php echo htmlspecialchars($patient_data[0]['firstname'] ?? $user['first_name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="middlename" class="form-label">Middle Name</label>
                            <input type="text" class="form-control" id="middlename" name="middlename" value="<?php echo htmlspecialchars($patient_data[0]['middlename'] ?? $user['middle_name'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="birthday" class="form-label">Birthday</label>
                            <input type="date" class="form-control" id="birthday" name="birthday" value="<?php echo htmlspecialchars($patient_data[0]['birthday'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="age" class="form-label">Age</label>
                            <input type="number" class="form-control" id="age" name="age" value="<?php echo htmlspecialchars($patient_data[0]['age'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="sex" class="form-label">Sex</label>
                            <select class="form-select" id="sex" name="sex" required>
                                <option value="male" <?php echo (isset($patient_data[0]['sex']) && $patient_data[0]['sex'] === 'male') ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo (isset($patient_data[0]['sex']) && $patient_data[0]['sex'] === 'female') ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="blood_type" class="form-label">Blood Type</label>
                            <select class="form-select" id="blood_type" name="blood_type">
                                <option value="unknown" <?php echo (isset($patient_data[0]['blood_type']) && $patient_data[0]['blood_type'] === 'unknown') ? 'selected' : ''; ?>>Unknown</option>
                                <option value="A+" <?php echo (isset($patient_data[0]['blood_type']) && $patient_data[0]['blood_type'] === 'A+') ? 'selected' : ''; ?>>A+</option>
                                <option value="A-" <?php echo (isset($patient_data[0]['blood_type']) && $patient_data[0]['blood_type'] === 'A-') ? 'selected' : ''; ?>>A-</option>
                                <option value="B+" <?php echo (isset($patient_data[0]['blood_type']) && $patient_data[0]['blood_type'] === 'B+') ? 'selected' : ''; ?>>B+</option>
                                <option value="B-" <?php echo (isset($patient_data[0]['blood_type']) && $patient_data[0]['blood_type'] === 'B-') ? 'selected' : ''; ?>>B-</option>
                                <option value="AB+" <?php echo (isset($patient_data[0]['blood_type']) && $patient_data[0]['blood_type'] === 'AB+') ? 'selected' : ''; ?>>AB+</option>
                                <option value="AB-" <?php echo (isset($patient_data[0]['blood_type']) && $patient_data[0]['blood_type'] === 'AB-') ? 'selected' : ''; ?>>AB-</option>
                                <option value="O+" <?php echo (isset($patient_data[0]['blood_type']) && $patient_data[0]['blood_type'] === 'O+') ? 'selected' : ''; ?>>O+</option>
                                <option value="O-" <?php echo (isset($patient_data[0]['blood_type']) && $patient_data[0]['blood_type'] === 'O-') ? 'selected' : ''; ?>>O-</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($patient_data[0]['email'] ?? $user['email']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="contact_number" class="form-label">Contact Number</label>
                            <input type="text" class="form-control" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($patient_data[0]['contact_number'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="city_address" class="form-label">City Address</label>
                            <input type="text" class="form-control" id="city_address" name="city_address" value="<?php echo htmlspecialchars($patient_data[0]['city_address'] ?? ''); ?>" required>
                        </div>
                        <button type="submit" class="btn btn-crimson">Save Changes</button>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Consultation Modal -->
    <div class="modal fade" id="viewConsultationModal" tabindex="-1" aria-labelledby="viewConsultationModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewConsultationModalLabel">Consultation Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Date:</strong> <span id="modal-consultation-date"></span></p>
                    <p><strong>Doctor:</strong> <span id="modal-consultation-doctor"></span></p>
                    <p><strong>Diagnosis:</strong> <span id="modal-consultation-diagnosis"></span></p>
                    <p><strong>Prescription:</strong> <span id="modal-consultation-prescription"></span></p>
                    <p><strong>Notes:</strong> <span id="modal-consultation-notes"></span></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

      <!-- Profile Update Required Modal - Non-dismissible Version -->

  <?php
  // Fetch user data including verification status and to_change flag
  $query = "SELECT 
          profile_update_required
          FROM users 
          WHERE id = ?";
  $stmt = $conn->prepare($query);
  if (!$stmt) {
    error_log("User data query prepare failed: " . $conn->error);
    $_SESSION['error'] = "Database error. Please try again.";
    header("Location: homepage.php");
    exit();
  }
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $result = $stmt->get_result();
  $user_change = $result->fetch_assoc();
  $stmt->close();

  ?>
  <div class="modal fade" id="profileUpdateModal" tabindex="-1" aria-labelledby="profileUpdateModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-warning">
          <h5 class="modal-title" id="profileUpdateModalLabel">Profile Update Required</h5>
          <!-- Removed the close button -->
        </div>
        <div class="modal-body">
          <p>Your patient profile requires updates. Please review and update your information to ensure accuracy.</p>
          <p>You won't be able to request consultations until your profile is up-to-date.</p>
        </div>
        <div class="modal-footer">


          <?php
          $updateUrl = "update_form.php"; // default
          if ($userType == 'Incoming Freshman' || $userType == 'College' || $userType == 'Senior High School' || $userType == 'High School') {
            $updateUrl = "update_form.php";
          } elseif ($userType == 'Employee') {
            $updateUrl = "UpdateEmployee.php";
          } elseif ($userType == 'Parent') {
            $updateUrl = "update_elementary.php";
          }

          ?>
          <a href="<?= $updateUrl ?>" class="btn btn-primary">Update Profile Now</a>
          <a href="logout.php" class="btn btn-warning">Logout</a>
        </div>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const needsUpdate = <?php echo !empty($user_change['profile_update_required']) && $user_change['profile_update_required'] == 1 ? 'true' : 'false'; ?>;

      if (needsUpdate) {
        const updateModal = new bootstrap.Modal(document.getElementById('profileUpdateModal'));
        updateModal.show();
      }
    });
  </script>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <?php include('notifications_user.php') ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        console.log(typeof bootstrap === 'object' ? 'Bootstrap loaded' : 'Bootstrap not loaded');

        function openSettings() {
            var settingsModal = new bootstrap.Modal(document.getElementById('settingsModal'));
            settingsModal.show();
        }

        document.getElementById('sidebar-toggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('open');
            document.querySelector('.main-content').classList.toggle('sidebar-open');
        });

        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 992) {
                const isClickInsideSidebar = document.getElementById('sidebar').contains(event.target);
                const isClickOnToggle = event.target === document.getElementById('sidebar-toggle');
                if (!isClickInsideSidebar && !isClickOnToggle && document.getElementById('sidebar').classList.contains('open')) {
                    document.getElementById('sidebar').classList.remove('open');
                    document.querySelector('.main-content').classList.remove('sidebar-open');
                }
            }
        });

        // Handle View Consultation Modal
        document.querySelectorAll('.view-consultation-btn').forEach(button => {
            button.addEventListener('click', function() {
                document.getElementById('modal-consultation-date').textContent = this.dataset.date;
                document.getElementById('modal-consultation-doctor').textContent = this.dataset.doctor;
                document.getElementById('modal-consultation-diagnosis').textContent = this.dataset.diagnosis;
                document.getElementById('modal-consultation-prescription').textContent = this.dataset.prescription;
                document.getElementById('modal-consultation-notes').textContent = this.dataset.notes;
            });
        });
    </script>
</body>

</html>