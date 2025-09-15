<?php
session_start();
require_once 'config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}



// Query to check documents_uploaded and profile_submitted status
$query = "SELECT documents_uploaded, profile_submitted, user_type, last_name, first_name, middle_name, email FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Check if user has already uploaded documents and submitted profile
if ($user && $user['documents_uploaded'] == 1) {
    if ($user['profile_submitted'] == 1) {
        header("Location: homepage.php");
        exit();
    } elseif ($user['user_type'] === 'Parent') {
        header("Location: Elemform.php");
        exit();
    }
} elseif ($user['user_type'] === 'Employee') {
    header("Location: EmployeeForm.php");
    exit();
} 


// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Function to handle file uploads with improved security
function uploadFile($file, $uploadDir = 'Uploads/documents/')
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload error: ' . $file['error']);
    }

    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Failed to create upload directory');
        }
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
    $maxSize = 40 * 1024 * 1024; // 40MB

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file($file['tmp_name']);

    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('Invalid file type. Only JPG, PNG, and PDF are allowed.');
    }

    if ($file['size'] > $maxSize) {
        throw new Exception('File size exceeds 40MB limit.');
    }

    $fileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\.\-]/', '_', basename($file['name']));
    $targetPath = $uploadDir . $fileName;

    if (!is_uploaded_file($file['tmp_name'])) {
        throw new Exception('Possible file upload attack');
    }

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('Failed to move uploaded file');
    }

    return $targetPath;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userType = $_POST['userType'] ?? '';
    $userId = $_SESSION['user_id'] ?? 0;

    $validUserTypes = ['Parent', 'Highschool', 'Senior High School', 'College', 'Employee', 'Incoming Freshman'];
    $userType = $_POST['userType'] ?? '';
    if (!in_array($userType, $validUserTypes)) {
        $_SESSION['error'] = 'Invalid user type selected';
        header('Location: uploaddocs.php');
        exit;
    }

    if (!$userId) {
        $_SESSION['error'] = 'User not logged in';
        header('Location: login.php');
        exit;
    }

    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = 'Invalid CSRF token';
        header('Location: uploaddocs.php');
        exit;
    }


    if ($userType === 'Parent' || $userType === 'Employee' || $userType == 'Senior High School' || $userType == 'Incoming Freshman' || $userType == 'College' || $userType == 'Highschool') {
        // Ensure only allowed values are saved
        $dbUserType = $userType;

        $updateUserType = $conn->prepare("UPDATE users SET user_type = ? WHERE id = ?");
        $updateUserType->bind_param("si", $dbUserType, $userId);

        if (!$updateUserType->execute()) {
            error_log("Failed to update user_type: " . $conn->error);
            $_SESSION['error'] = "Failed to update user type: " . $conn->error;
            header('Location: uploaddocs.php');
            exit;
        }

        $updateUserType->close();
    }

    try {
        if (!$conn) {
            throw new Exception("Database connection failed");
        }

        mysqli_begin_transaction($conn);

        switch ($userType) {
            case 'Parent':
                if (empty($_FILES['parentId']['name'])) {
                    throw new Exception('Parent/Guardian ID is required');
                }
                $parentIdPath = uploadFile($_FILES['parentId']);

                // Insert parent record into parents table
                $stmt = $conn->prepare("INSERT INTO parents (user_id, id_path) VALUES (?, ?)");
                $stmt->bind_param("is", $userId, $parentIdPath);
                $stmt->execute();
                $stmt->close();

                // Insert a temporary patient record for the parent to satisfy children.parent_id foreign key
                $lastName = $user['last_name'];
                $firstName = $user['first_name'];
                $middleName = $user['middle_name'] ?? null;
                $email = $user['email'];
                $dummyBirthday = '1970-01-01'; // Placeholder, to be updated in /Elemform
                $dummyAge = 30; // Placeholder
                $dummySex = 'male'; // Placeholder
                $dummyContact = 'PARENT_ACC'; // Placeholder
                $dummyAddress = 'Unknown'; // Placeholder

                $stmt = $conn->prepare("INSERT INTO patients (user_id, surname, firstname, middlename, birthday, age, sex, email, contact_number, city_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issssissss", $userId, $lastName, $firstName, $middleName, $dummyBirthday, $dummyAge, $dummySex, $email, $dummyContact, $dummyAddress);
                $stmt->execute();
                $patientId = $conn->insert_id;
                $stmt->close();

                // Process children
                $childCount = 0;
                for ($i = 0; isset($_POST["studentLastName$i"]); $i++) {
                    if (!empty($_POST["studentLastName$i"]) && !empty($_POST["studentFirstName$i"]) && !empty($_POST["studentType$i"]) && !empty($_FILES["studentId$i"]['name'])) {
                        $lastName = trim($_POST["studentLastName$i"]);
                        $firstName = trim($_POST["studentFirstName$i"]);
                        $middleName = trim($_POST["studentMiddleName$i"] ?? '');
                        $type = $_POST["studentType$i"];
                        $idPath = uploadFile($_FILES["studentId$i"]);

                        $stmt = $conn->prepare("INSERT INTO children (parent_id, last_name, first_name, middle_name, type, id_path) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("isssss", $userId, $lastName, $firstName, $middleName, $type, $idPath);

                        $stmt->execute();
                        $stmt->close();
                        $childCount++;
                    }
                }

                if ($childCount === 0) {
                    throw new Exception('At least one child with Last Name, First Name, Type, and ID must be added');
                }

                $updateStmt = $conn->prepare("UPDATE users SET documents_uploaded = 1 WHERE id = ?");
                $updateStmt->bind_param("i", $userId);
                if (!$updateStmt->execute()) {
                    throw new Exception("Failed to update documents status");
                }
                $updateStmt->close();

                $_SESSION['documents_uploaded'] = true;
                $_SESSION['user_type'] = 'Parent';
                $_SESSION['success'] = 'Documents uploaded successfully';
                header('Location: Elemform.php');
                break;

            case 'Highschool':
                if (empty($_FILES['studentIdHighschool']['name'])) {
                    throw new Exception('Student ID is required');
                }
                $idPath = uploadFile($_FILES['studentIdHighschool']);

                $stmt = $conn->prepare("INSERT INTO highschool_students (user_id, id_path) VALUES (?, ?)");
                $stmt->bind_param("is", $userId, $idPath);
                $stmt->execute();
                $stmt->close();

                $updateStmt = $conn->prepare("UPDATE users SET documents_uploaded = 1 WHERE id = ?");
                $updateStmt->bind_param("i", $userId);
                if (!$updateStmt->execute()) {
                    throw new Exception("Failed to update documents status");
                }
                $updateStmt->close();

                $_SESSION['documents_uploaded'] = true;
                $_SESSION['user_type'] = 'Highschool';
                $_SESSION['success'] = 'Documents uploaded successfully';
                header('Location: form.php');
                break;

            case 'Senior High School':
                if (empty($_FILES['studentIdSeniorHigh']['name'])) {
                    throw new Exception('Student ID is required');
                }
                $idPath = uploadFile($_FILES['studentIdSeniorHigh']);

                $stmt = $conn->prepare("INSERT INTO senior_high_students (user_id, id_path) VALUES (?, ?)");
                $stmt->bind_param("is", $userId, $idPath);
                $stmt->execute();
                $stmt->close();

                $updateStmt = $conn->prepare("UPDATE users SET documents_uploaded = 1 WHERE id = ?");
                $updateStmt->bind_param("i", $userId);
                if (!$updateStmt->execute()) {
                    throw new Exception("Failed to update documents status");
                }
                $updateStmt->close();

                $_SESSION['documents_uploaded'] = true;
                $_SESSION['user_type'] = 'Senior High School';
                $_SESSION['success'] = 'Documents uploaded successfully';
                header('Location: form.php');
                break;

            case 'College':
                if (empty($_FILES['cor']['name']) || empty($_FILES['schoolId']['name'])) {
                    throw new Exception('Both COR and School ID are required');
                }
                $corPath = uploadFile($_FILES['cor']);
                $schoolIdPath = uploadFile($_FILES['schoolId']);

                $stmt = $conn->prepare("INSERT INTO college_students (user_id, cor_path, school_id_path) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $userId, $corPath, $schoolIdPath);
                $stmt->execute();
                $stmt->close();

                $updateStmt = $conn->prepare("UPDATE users SET documents_uploaded = 1 WHERE id = ?");
                $updateStmt->bind_param("i", $userId);
                if (!$updateStmt->execute()) {
                    throw new Exception("Failed to update documents status");
                }
                $updateStmt->close();

                $_SESSION['documents_uploaded'] = true;
                $_SESSION['user_type'] = 'College';
                $_SESSION['success'] = 'Documents uploaded successfully';
                header('Location: form.php');
                break;

            case 'Employee':
                if (empty($_FILES['employeeId']['name'])) {
                    throw new Exception('Employee ID is required');
                }
                $idPath = uploadFile($_FILES['employeeId']);

                $stmt = $conn->prepare("INSERT INTO employees (user_id, id_path) VALUES (?, ?)");
                $stmt->bind_param("is", $userId, $idPath);
                $stmt->execute();
                $stmt->close();

                $updateStmt = $conn->prepare("UPDATE users SET documents_uploaded = 1 WHERE id = ?");
                $updateStmt->bind_param("i", $userId);
                if (!$updateStmt->execute()) {
                    throw new Exception("Failed to update documents status");
                }
                $updateStmt->close();

                $_SESSION['documents_uploaded'] = true;
                $_SESSION['user_type'] = 'Employee';
                $_SESSION['success'] = 'Documents uploaded successfully';
                header('Location: EmployeeForm.php');
                break;

            case 'Incoming Freshman':
                if (empty($_FILES['idFreshman']['name'])) {
                    throw new Exception('School ID is required');
                }
                $idPath = uploadFile($_FILES['idFreshman']);

                $stmt = $conn->prepare("INSERT INTO incoming_freshmen (user_id, id_path) VALUES (?, ?)");
                $stmt->bind_param("is", $userId, $idPath);
                $stmt->execute();
                $stmt->close();

                $updateStmt = $conn->prepare("UPDATE users SET documents_uploaded = 1 WHERE id = ?");
                $updateStmt->bind_param("i", $userId);
                if (!$updateStmt->execute()) {
                    throw new Exception("Failed to update documents status");
                }
                $updateStmt->close();

                $_SESSION['documents_uploaded'] = true;
                $_SESSION['user_type'] = 'Incoming Freshman';
                $_SESSION['success'] = 'Documents uploaded successfully';
                header('Location: form.php');
                break;

            default:
                throw new Exception('Invalid user type selected');
        }

        $conn->commit();
        exit;
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        error_log("Upload Error: " . $e->getMessage());
        $_SESSION['error'] = "Error: " . $e->getMessage();
        header('Location: uploaddocs.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Documents</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/uploaddocs.css">
    <link rel="apple-touch-icon" sizes="180x180" href="images/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="images/favicon-16x16.png">
    <link rel="manifest" href="images/site.webmanifest">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <style>
        .document-section {
            display: none;
        }

        #submitBtn {
            display: block;
        }

        * {
            font-family: 'Poppins'
        }
    </style>
</head>

<body class="d-flex justify-content-center align-items-center vh-100" style="background-color: #990000;">
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

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #990000; color: white;">
                    <h5 class="modal-title" id="confirmModalLabel">Confirm Submission</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to submit the form? Please ensure all information is correct.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" id="confirmSubmitBtn">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-dark p-4 bg-white" style="width: 100%; max-width: 500px;">
        <div style="display: flex; justify-content: center;">
            <img src="images/23.png" alt="WMSU Logo" class="logo mb-3">

        </div>

        <form id="uploadForm" action="uploaddocs.php" method="post" enctype="multipart/form-data" novalidate onsubmit="return confirmSubmission()">
            <!-- CSRF Protection -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div class="mb-3">
                <label for="userType" class="form-label">Select User Type</label>
                <select id="userType" name="userType" class="form-select" required>
                    <option value="" selected disabled>Select User Type</option>
                    <option value="Parent">Kindergarten/Elementary (Parent)</option>
                    <option value="Highschool">Highschool</option>
                    <option value="Senior High School">Senior High School</option>
                    <option value="College">College</option>
                    <option value="Employee">Employee</option>
                    <option value="Incoming Freshman">Incoming Freshman</option>
                </select>
                <div class="invalid-feedback">Please select a user type</div>
            </div>

            <!-- Parent Section -->
            <div id="parentFields" class="document-section">
                <h5>Parent/Guardian Information</h5>
                <div class="mb-3">
                    <label for="parentId" class="form-label">Parent/Guardian ID</label>
                    <input type="file" class="form-control" id="parentId" name="parentId" accept="image/jpeg,image/png,application/pdf" onchange="previewImage(this, 'parentIdPreview')">
                    <div class="invalid-feedback">Please upload a parent/guardian ID</div>
                    <img id="parentIdPreview" class="mt-2" style="max-width: 100%; max-height: 150px; display: none;" alt="Parent ID Preview">
                </div>
                <h5>Student Information</h5>
                <div id="studentIdContainer">
                    <div class="student-section mb-3" data-index="0">
                        <div class="mb-3">
                            <label for="studentLastName0" class="form-label">Student Last Name (1)</label>
                            <input type="text" class="form-control capitalize" id="studentLastName0" name="studentLastName0">
                            <div class="invalid-feedback">Please enter the student's last name</div>
                        </div>
                        <div class="mb-3">
                            <label for="studentFirstName0" class="form-label">Student First Name (1)</label>
                            <input type="text" class="form-control capitalize" id="studentFirstName0" name="studentFirstName0">
                            <div class="invalid-feedback">Please enter the student's first name</div>
                        </div>
                        <div class="mb-3">
                            <label for="studentMiddleName0" class="form-label">Student Middle Name (1)</label>
                            <input type="text" class="form-control capitalize" id="studentMiddleName0" name="studentMiddleName0">
                        </div>
                        <div class="mb-3">
                            <label for="studentType0" class="form-label">Student Type (1)</label>
                            <select class="form-select" id="studentType0" name="studentType0">
                                <option value="">Select type</option>
                                <option value="Kindergarten">Kindergarten</option>
                                <option value="Elementary">Elementary</option>
                            </select>
                            <div class="invalid-feedback">Please select a student type</div>
                        </div>
                        <div class="mb-3">
                            <label for="studentId0" class="form-label">Student ID (1)</label>
                            <input type="file" class="form-control" id="studentId0" name="studentId0" accept="image/jpeg,image/png,application/pdf" onchange="previewImage(this, 'studentIdPreview0')">
                            <div class="invalid-feedback">Please upload a student ID</div>
                            <img id="studentIdPreview0" class="mt-2" style="max-width: 100%; max-height: 150px; display: none;" alt="Student ID Preview">
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-outline-primary w-100 mb-3" onclick="addStudentIdField()">Add Another Student</button>
                <button type="submit" class="btn btn-crimson w-100 mb-3">Submit Parent/Student Information</button>
            </div>

            <!-- Highschool Section -->
            <div id="highschoolFields" class="document-section">
                <div class="mb-3">
                    <label for="studentIdHighschool" class="form-label">Student ID</label>
                    <input type="file" class="form-control" id="studentIdHighschool" name="studentIdHighschool" accept="image/jpeg,image/png,application/pdf">
                    <div class="invalid-feedback">Please upload a student ID</div>
                </div>
            </div>

            <!-- Senior High School Section -->
            <div id="seniorHighschoolFields" class="document-section">
                <div class="mb-3">
                    <label for="studentIdSeniorHigh" class="form-label">Upload Student ID</label>
                    <input type="file" class="form-control" id="studentIdSeniorHigh" name="studentIdSeniorHigh" accept="image/jpeg,image/png,application/pdf">
                    <div class="invalid-feedback">Please upload a student ID</div>
                </div>
            </div>

            <!-- College Section -->
            <div id="collegeFields" class="document-section">
                <div class="mb-3">
                    <label for="cor" class="form-label">Certificate of Registration (COR)</label>
                    <input type="file" class="form-control" id="cor" name="cor" accept="image/jpeg,image/png,application/pdf">
                    <div class="invalid-feedback">Please upload a Certificate of Registration (COR)</div>
                </div>
                <div class="mb-3">
                    <label for="schoolId" class="form-label">School ID</label>
                    <input type="file" class="form-control" id="schoolId" name="schoolId" accept="image/jpeg,image/png,application/pdf">
                    <div class="invalid-feedback">Please upload a school ID</div>
                </div>
            </div>

            <!-- Employee Section -->
            <div id="employeeFields" class="document-section">
                <div class="mb-3">
                    <label for="employeeId" class="form-label">Employee ID</label>
                    <input type="file" class="form-control" id="employeeId" name="employeeId" accept="image/jpeg,image/png,application/pdf">
                    <div class="invalid-feedback">Please upload an employee ID</div>
                </div>
            </div>

            <!-- Incoming Freshman Section -->
            <div id="incomingFreshmanFields" class="document-section">
                <div class="mb-3">
                    <label for="idFreshman" class="form-label">SHS School ID</label>
                    <input type="file" class="form-control" id="idFreshman" name="idFreshman" accept="image/jpeg,image/png,application/pdf">
                    <div class="invalid-feedback">Please upload an SHS school ID</div>
                </div>
            </div>

            <button id="submitBtn" type="submit" class="btn btn-crimson w-100">
                <span id="submitText">Submit</span>
            </button>
        </form>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Show modals on page load if needed
            setTimeout(function() {
                <?php if (isset($_SESSION['error'])): ?>
                    var errorModal = new bootstrap.Modal(document.getElementById('errorModal'), {});
                    errorModal.show();
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                <?php if (isset($_SESSION['success'])): ?>
                    var successModal = new bootstrap.Modal(document.getElementById('successModal'), {});
                    successModal.show();
                    setTimeout(function() {
                        window.location = <?php echo ($user['user_type'] === 'Parent') ? "'/Elemform'" : "'/form'"; ?>;
                    }, 2000);
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
            }, 100);

            // Capitalize first letter of inputs
            function capitalizeFirstLetter(input) {
                if (input.value.length > 0) {
                    input.value = input.value.charAt(0).toUpperCase() + input.value.slice(1);
                }
            }

            // Apply capitalization to existing and new inputs
            function applyCapitalization() {
                document.querySelectorAll('.capitalize').forEach(input => {
                    input.addEventListener('input', function() {
                        capitalizeFirstLetter(this);
                    });
                    input.addEventListener('blur', function() {
                        capitalizeFirstLetter(this);
                    });
                });
            }

            // Initial application of capitalization
            applyCapitalization();

            // Toggle section visibility and main submit button
            const userTypeSelect = document.getElementById('userType');
            const mainSubmitBtn = document.getElementById('submitBtn');
            userTypeSelect.addEventListener('change', function() {
                console.log('User type changed to:', this.value); // Debug
                const sections = document.querySelectorAll('.document-section');
                sections.forEach(section => section.style.display = 'none');

                const selectedType = this.value;
                if (selectedType) {
                    const sectionId = selectedType === 'Parent' ? 'parentFields' :
                        selectedType === 'Highschool' ? 'highschoolFields' :
                        selectedType === 'Senior High School' ? 'seniorHighschoolFields' :
                        selectedType === 'College' ? 'collegeFields' :
                        selectedType === 'Employee' ? 'employeeFields' :
                        selectedType === 'Incoming Freshman' ? 'incomingFreshmanFields' : '';
                    if (sectionId) {
                        document.getElementById(sectionId).style.display = 'block';
                        console.log('Showing section:', sectionId); // Debug
                    }
                    // Hide main submit button for Parent type
                    mainSubmitBtn.style.display = selectedType === 'Parent' ? 'none' : 'block';
                } else {
                    mainSubmitBtn.style.display = 'block';
                }
            });

            // Trigger change event on load
            userTypeSelect.dispatchEvent(new Event('change'));

            // Preview uploaded image
            function previewImage(input, previewId) {
                console.log('Previewing image for:', previewId); // Debug
                const preview = document.getElementById(previewId);
                if (input.files && input.files[0]) {
                    const fileType = input.files[0].type;
                    if (fileType === 'image/jpeg' || fileType === 'image/png') {
                        preview.src = URL.createObjectURL(input.files[0]);
                        preview.style.display = 'block';
                    } else {
                        preview.style.display = 'none';
                    }
                } else {
                    preview.style.display = 'none';
                }
            }

            // Add new student field set
            let studentIndex = 1;
            window.addStudentIdField = function() {
                console.log('Adding student field, index:', studentIndex); // Debug
                const container = document.getElementById('studentIdContainer');
                if (!container) {
                    console.error('studentIdContainer not found');
                    return;
                }
                const newSection = document.createElement('div');
                newSection.className = 'student-section mb-3';
                newSection.dataset.index = studentIndex;
                newSection.innerHTML = `
                    <div class="mb-3">
                        <label for="studentLastName${studentIndex}" class="form-label">Student Last Name (${studentIndex + 1})</label>
                        <input type="text" class="form-control capitalize" id="studentLastName${studentIndex}" name="studentLastName${studentIndex}">
                        <div class="invalid-feedback">Please enter the student's last name</div>
                    </div>
                    <div class="mb-3">
                        <label for="studentFirstName${studentIndex}" class="form-label">Student First Name (${studentIndex + 1})</label>
                        <input type="text" class="form-control capitalize" id="studentFirstName${studentIndex}" name="studentFirstName${studentIndex}">
                        <div class="invalid-feedback">Please enter the student's first name</div>
                    </div>
                    <div class="mb-3">
                        <label for="studentMiddleName${studentIndex}" class="form-label">Student Middle Name (${studentIndex + 1})</label>
                        <input type="text" class="form-control capitalize" id="studentMiddleName${studentIndex}" name="studentMiddleName${studentIndex}">
                    </div>
                    <div class="mb-3">
                        <label for="studentType${studentIndex}" class="form-label">Student Type (${studentIndex + 1})</label>
                        <select class="form-select" id="studentType${studentIndex}" name="studentType${studentIndex}">
                            <option value="">Select type</option>
                            <option value="Kindergarten">Kindergarten</option>
                            <option value="Elementary">Elementary</option>
                        </select>
                        <div class="invalid-feedback">Please select a student type</div>
                    </div>
                    <div class="mb-3">
                        <label for="studentId${studentIndex}" class="form-label">Student ID (${studentIndex + 1})</label>
                        <input type="file" class="form-control" id="studentId${studentIndex}" name="studentId${studentIndex}" accept="image/jpeg,image/png,application/pdf" onchange="previewImage(this, 'studentIdPreview${studentIndex}')">
                        <div class="invalid-feedback">Please upload a student ID</div>
                        <img id="studentIdPreview${studentIndex}" class="mt-2" style="max-width: 100%; max-height: 150px; display: none;" alt="Student ID Preview">
                    </div>
                    <button type="button" class="btn btn-outline-danger w-100" onclick="removeStudent(this)">Remove Student</button>
                `;
                container.appendChild(newSection);
                applyCapitalization(); // Re-apply capitalization to new inputs
                studentIndex++;
            };

            // Remove student field set
            window.removeStudent = function(button) {
                console.log('Removing student section'); // Debug
                button.closest('.student-section').remove();
            };

            // Validate form fields based on user type
            window.validateForm = function() {
                console.log('Validating form'); // Debug
                const userType = document.getElementById('userType').value;
                const missingFields = [];
                let isValid = true;

                document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

                if (!userType) {
                    missingFields.push('User Type');
                    document.getElementById('userType').classList.add('is-invalid');
                    isValid = false;
                }

                switch (userType) {
                    case 'Parent':
                        const parentId = document.getElementById('parentId');
                        if (!parentId.files.length) {
                            missingFields.push('Parent/Guardian ID');
                            parentId.classList.add('is-invalid');
                            isValid = false;
                        }

                        let childCount = 0;
                        for (let i = 0;; i++) {
                            const lastNameInput = document.getElementById(`studentLastName${i}`);
                            const firstNameInput = document.getElementById(`studentFirstName${i}`);
                            const typeInput = document.getElementById(`studentType${i}`);
                            const idInput = document.getElementById(`studentId${i}`);
                            if (!lastNameInput) break;

                            const lastName = lastNameInput.value.trim();
                            const firstName = firstNameInput.value.trim();
                            const type = typeInput.value;
                            const idFile = idInput.files.length;

                            if (lastName && firstName && type && idFile) {
                                childCount++;
                            } else if (lastName || firstName || type || idFile) {
                                if (!lastName) {
                                    missingFields.push(`Student Last Name (${i + 1})`);
                                    lastNameInput.classList.add('is-invalid');
                                }
                                if (!firstName) {
                                    missingFields.push(`Student First Name (${i + 1})`);
                                    firstNameInput.classList.add('is-invalid');
                                }
                                if (!type) {
                                    missingFields.push(`Student Type (${i + 1})`);
                                    typeInput.classList.add('is-invalid');
                                }
                                if (!idFile) {
                                    missingFields.push(`Student ID (${i + 1})`);
                                    idInput.classList.add('is-invalid');
                                }
                                isValid = false;
                            }
                        }

                        if (childCount === 0) {
                            missingFields.push('At least one student with Last Name, First Name, Type, and ID');
                            isValid = false;
                        }
                        break;

                    case 'Highschool':
                        const highschoolId = document.getElementById('studentIdHighschool');
                        if (!highschoolId.files.length) {
                            missingFields.push('Highschool Student ID');
                            highschoolId.classList.add('is-invalid');
                            isValid = false;
                        }
                        break;

                    case 'Senior High School':
                        const seniorHighId = document.getElementById('studentIdSeniorHigh');
                        if (!seniorHighId.files.length) {
                            missingFields.push('Senior High School Student ID');
                            seniorHighId.classList.add('is-invalid');
                            isValid = false;
                        }
                        break;

                    case 'College':
                        const cor = document.getElementById('cor');
                        const schoolId = document.getElementById('schoolId');
                        if (!cor.files.length) {
                            missingFields.push('Certificate of Registration (COR)');
                            cor.classList.add('is-invalid');
                            isValid = false;
                        }
                        if (!schoolId.files.length) {
                            missingFields.push('College School ID');
                            schoolId.classList.add('is-invalid');
                            isValid = false;
                        }
                        break;

                    case 'Employee':
                        const employeeId = document.getElementById('employeeId');
                        if (!employeeId.files.length) {
                            missingFields.push('Employee ID');
                            employeeId.classList.add('is-invalid');
                            isValid = false;
                        }
                        break;

                    case 'Incoming Freshman':
                        const freshmanId = document.getElementById('idFreshman');
                        if (!freshmanId.files.length) {
                            missingFields.push('SHS School ID');
                            freshmanId.classList.add('is-invalid');
                            isValid = false;
                        }
                        break;
                }

                if (!isValid) {
                    console.log('Missing fields:', missingFields); // Debug
                    const missingFieldsList = document.getElementById('missingFieldsList');
                    missingFieldsList.innerHTML = missingFields.map(field => `<li>${field}</li>`).join('');
                    var missingModal = new bootstrap.Modal(document.getElementById('missingFieldsModal'), {});
                    missingModal.show();
                }

                return isValid;
            };

            // Handle form submission with confirmation modal
            window.confirmSubmission = function() {
                console.log('Confirming submission'); // Debug
                if (!validateForm()) {
                    return false;
                }
                // Show confirmation modal
                var confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'), {});
                confirmModal.show();
                return false; // Prevent default form submission
            };

            // Handle confirm button click in modal
            document.getElementById('confirmSubmitBtn').addEventListener('click', function() {
                console.log('Confirm button clicked'); // Debug
                document.getElementById('uploadForm').submit();
            });
        });
    </script>
</body>

</html>