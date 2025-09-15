<?php
// upload.php
// Start session with secure settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');
error_reporting(E_ALL);
ini_set('display_errors', 1);


// Global error handlers
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    http_response_code(500);

    exit("Internal server error. Please try again later.");
});

set_exception_handler(function ($e) {
    error_log("Uncaught Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    http_response_code(500);
    exit("Internal server error. Please try again later.");
});

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log('Unauthorized access attempt: No user_id in session');
    header('Location: /index');
    exit;
}

require_once 'config.php';



// Verify database connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    exit("Internal server error. Please try again later.");
}

// Initialize with default value
$hepa_b_indicator = '0'; 

function sendAdminNotification($conn, $userId, $userType, $notificationType, $title, $description, $link) {
    try {
        error_log("Starting notification process for user $userId");
        
        // 1. Get patient data
        $patientQuery = $conn->prepare("SELECT firstname, surname FROM patients WHERE user_id = ?");
        if (!$patientQuery) {
            error_log("Prepare failed: " . $conn->error);
            return false;
        }
        
        $patientQuery->bind_param('i', $userId);
        if (!$patientQuery->execute()) {
            error_log("Execute failed: " . $patientQuery->error);
            return false;
        }
        
        $patientResult = $patientQuery->get_result();
        $patientData = $patientResult->fetch_assoc();
        $patientQuery->close();
        
        if (!$patientData) {
            error_log("No patient data found for user_id: $userId");
            return false;
        }
        
        error_log("Patient data: " . print_r($patientData, true));

        // 2. Get admin users
        $adminQuery = $conn->prepare("SELECT id FROM users WHERE user_type IN ('Super Admin', 'Medical Admin', 'Dental Admin')");
        if (!$adminQuery) {
            error_log("Prepare failed: " . $conn->error);
            return false;
        }
        
        if (!$adminQuery->execute()) {
            error_log("Execute failed: " . $adminQuery->error);
            return false;
        }
        
        $adminResult = $adminQuery->get_result();
        $adminCount = $adminResult->num_rows;
        error_log("Found $adminCount admin users to notify");
        
        // 3. Prepare notification statement
        $notificationStmt = $conn->prepare("
            INSERT INTO notifications_admin (
                user_id, type, title, description, link, status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, 'unread', NOW(), NOW())
        ");
        
        if (!$notificationStmt) {
            error_log("Prepare failed: " . $conn->error);
            return false;
        }
        
        // 4. Build notification content
        $fullDescription = sprintf("%s %s (%s) %s",
            $patientData['firstname'],
            $patientData['surname'],
            $userType,
            $description
        );
        
        error_log("Notification content prepared: $fullDescription");
        
        // 5. Send notifications
        $notificationsSent = 0;
        while ($adminRow = $adminResult->fetch_assoc()) {
            $targetUserId = $adminRow['id'];
            
            if (!$notificationStmt->bind_param("issss", $targetUserId, $notificationType, $title, $fullDescription, $link)) {
                error_log("Bind failed for user $targetUserId: " . $notificationStmt->error);
                continue;
            }
            
            if (!$notificationStmt->execute()) {
                error_log("Execute failed for user $targetUserId: " . $notificationStmt->error);
                continue;
            }
            
            $notificationsSent++;
        }
        
        error_log("Successfully sent $notificationsSent notifications");
        
        // Cleanup
        $notificationStmt->close();
        $adminQuery->close();
        
        return $notificationsSent > 0;
    } catch (Exception $e) {
        error_log("Notification error: " . $e->getMessage());
        return false;
    }
}

try {
    // Get user information
    $userId = (int)$_SESSION['user_id'];
    
    // Step 1: Get user_type from users table
    $query = "SELECT user_type FROM users WHERE id = ?";
    if (!$stmt = $conn->prepare($query)) {
        throw new Exception("Prepare failed for user query: " . $conn->error);
    }
    $stmt->bind_param('i', $userId);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed for user query: " . $stmt->error);
    }
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (empty($user['user_type'])) {
        error_log("No user found for user_id: $userId");
        header('Location: /index');
        exit;
    }
    
    $userType = $user['user_type'];

    // Step 2: Check patient department/course to determine Hepa B indicator
    $query = "SELECT department, course FROM patients WHERE user_id = ?";
    if (!$stmt = $conn->prepare($query)) {
        throw new Exception("Prepare failed for patient query: " . $conn->error);
    }
    $stmt->bind_param('i', $userId);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed for patient query: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $patient = $result->fetch_assoc();
        $department = isset($patient['department']) ? strtoupper(trim($patient['department'])) : '';
        $course = isset($patient['course']) ? strtoupper(trim($patient['course'])) : '';
        
        // Departments requiring Hepa B (direct match)
        $hepa_departments = ['COM', 'CON', 'CHE', 'CCJE', 'CA'];
        
        // Conditional matches
        if (in_array($department, $hepa_departments)) {
            $hepa_b_indicator = '1';
        } elseif ($department === 'CSM' && $course === 'BSBIO') {
            $hepa_b_indicator = '1';
        } elseif ($department === 'CA' && $course === 'BSFOODTECH') {
            $hepa_b_indicator = '1';
        }
    }
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Error in Hepa B indicator determination: " . $e->getMessage());
    // Fall back to default value if there's an error
    $hepa_b_indicator = '0';
}


// Check if user has submitted a waiver
try {
    $query = "SELECT id FROM waivers WHERE user_id = ?";
    if (!$stmt = $conn->prepare($query)) {
        error_log("Prepare failed for waiver check: " . $conn->error);
        throw new Exception("Database error: Unable to prepare waiver check query");
    }
    $stmt->bind_param('i', $userId);
    if (!$stmt->execute()) {
        error_log("Execute failed for waiver check: " . $stmt->error);
        throw new Exception("Database error: Unable to execute waiver check query");
    }
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        error_log("User attempted to access upload.php without waiver: user_id $userId");
        header('Location: wmsuwaiver.php');
        exit;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Waiver check error: " . $e->getMessage());
    http_response_code(500);
    exit("Internal server error. Please try again later.");
}

// Check if Incoming Freshman has already submitted
$hasSubmitted = false;
if ($userType === 'Incoming Freshman') {
    try {
        $query = "SELECT has_submitted FROM submission_status WHERE user_id = ?";
        if (!$stmt = $conn->prepare($query)) {
            error_log("Prepare failed for submission_status check: " . $conn->error);
            throw new Exception("Database error: Unable to prepare submission_status query");
        }
        $stmt->bind_param('i', $userId);
        if (!$stmt->execute()) {
            error_log("Execute failed for submission_status check: " . $stmt->error);
            throw new Exception("Database error: Unable to execute submission_status query");
        }
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $hasSubmitted = (bool)$row['has_submitted'];
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Submission status check error: " . $e->getMessage());
        http_response_code(500);
        exit("Internal server error. Please try again later.");
    }
}

// For Parent users, get their children
$children = [];
if ($userType === 'Parent') {
    try {
        $query = "SELECT c.id, CONCAT(c.first_name, ' ', COALESCE(c.middle_name, ''), ' ', c.last_name) AS name 
                  FROM children c 
                  JOIN patients pt ON c.parent_id = pt.id 
                  JOIN parents p ON p.user_id = pt.user_id 
                  WHERE p.user_id = ?";
        if (!$stmt = $conn->prepare($query)) {
            error_log("Prepare failed for children query: " . $conn->error);
            throw new Exception("Database error: Unable to prepare children query");
        }
        $stmt->bind_param('i', $userId);
        if (!$stmt->execute()) {
            error_log("Execute failed for children query: " . $stmt->error);
            throw new Exception("Database error: Unable to execute children query");
        }
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $children[] = $row;
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Children query error: " . $e->getMessage());
    }
}

// Fetch upload history for all users
$uploadHistory = [];
$selectedChildId = isset($_GET['child_id']) && $userType === 'Parent' ? (int)$_GET['child_id'] : null;
try {
    if ($userType === 'Parent' && $selectedChildId) {
        $query = "SELECT id, document_type, file_path, original_file_name, status, submitted_at, child_id 
                  FROM medical_documents 
                  WHERE user_id = ? AND child_id = ? 
                  ORDER BY submitted_at DESC";
        if (!$stmt = $conn->prepare($query)) {
            error_log("Prepare failed for upload history query: " . $conn->error);
            throw new Exception("Database error: Unable to prepare upload history query");
        }
        $stmt->bind_param('ii', $userId, $selectedChildId);
    } else {
        $query = "SELECT id, document_type, file_path, original_file_name, status, submitted_at, child_id, reason
                  FROM medical_documents 
                  WHERE user_id = ? 
                  ORDER BY submitted_at DESC";
        if (!$stmt = $conn->prepare($query)) {
            error_log("Prepare failed for upload history query: " . $conn->error);
            throw new Exception("Database error: Unable to prepare upload history query");
        }
        $stmt->bind_param('i', $userId);
    }
    if (!$stmt->execute()) {
        error_log("Execute failed for upload history query: " . $stmt->error);
        throw new Exception("Database error: Unable to execute upload history query");
    }
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $uploadHistory[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Upload history query error: " . $e->getMessage());
}

// Handle AJAX file uploads and edits
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $response = ['status' => 'success', 'messages' => []];

    try {
        $uploadDir = __DIR__ . '/uploads/medical_documents/';
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                error_log("Failed to create directory: $uploadDir");
                throw new Exception("Server error: Unable to create upload directory");
            }
        }
        if (!is_writable($uploadDir)) {
            error_log("Directory not writable: $uploadDir");
            throw new Exception("Server error: Upload directory is not writable");
        }

        if ($_POST['action'] === 'upload_documents') {
            // Check submission status for Incoming Freshman
            if ($userType === 'Incoming Freshman' && $hasSubmitted) {
                $response['status'] = 'error';
                $response['messages']['general'] = 'You have already submitted your medical documents.';
                echo json_encode($response);
                exit;
            }

            // Handle Incoming Freshman uploads
            if ($userType === 'Incoming Freshman' && $hepa_b_indicator === '0') {
                $documents = [
                    'chest_xray' => 'chest_xray_results',
                    'cbc' => 'complete_blood_count_results',
                    'blood_typing' => 'blood_typing_results',
                    'urinalysis' => 'urinalysis_results',
                    'drug_test' => 'drug_test_results',
                ];
                foreach ($documents as $inputName => $documentType) {
                    if (isset($_FILES[$inputName]) && $_FILES[$inputName]['error'] === UPLOAD_ERR_OK) {
                        $file = $_FILES[$inputName];
                        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        $originalFileName = basename($file['name']);
                        if (!in_array($ext, ['pdf', 'docx', 'jpg', 'jpeg', 'png'])) {
                            $response['messages'][$inputName] = 'Invalid file type for ' . str_replace('_', ' ', $inputName) . '. Only PDF, DOCX, JPG, JPEG, PNG allowed.';
                            $response['status'] = 'error';
                            continue;
                        }
                        if ($file['size'] > 50 * 1024 * 1024) {
                            $response['messages'][$inputName] = 'File size for ' . str_replace('_', ' ', $inputName) . ' exceeds 50MB.';
                            $response['status'] = 'error';
                            continue;
                        }

                        // Generate server-side file name
                        $fileName = $documentType . '_' . $userId . '_' . time() . '.' . $ext;
                        $filePath = $uploadDir . $fileName;

                        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                            error_log("Failed to move uploaded file to: $filePath");
                            $response['messages'][$inputName] = 'Failed to upload ' . str_replace('_', ' ', $inputName) . '.';
                            $response['status'] = 'error';
                            continue;
                        }

                        // Ensure file was written correctly
                        if (!file_exists($filePath)) {
                            error_log("Uploaded file not found after move: $filePath");
                            $response['messages'][$inputName] = 'Failed to save ' . str_replace('_', ' ', $inputName) . '.';
                            $response['status'] = 'error';
                            continue;
                        }

                        $query = "INSERT INTO medical_documents (user_id, document_type, file_path, original_file_name, status, submitted_at) VALUES (?, ?, ?, ?, 'pending', NOW())";
                        if (!$stmt = $conn->prepare($query)) {
                            error_log("Prepare failed for medical_documents insert: " . $conn->error);
                            $response['messages'][$inputName] = 'Failed to save ' . str_replace('_', ' ', $inputName) . ' to database.';
                            $response['status'] = 'error';
                            continue;
                        }
                   $relativePath = $fileUploaded ? '../Uploads/medical_documents/' . $fileName : null;
                        $stmt->bind_param('isss', $userId, $documentType, $relativePath, $originalFileName);
                        if ($stmt->execute()) {
                            sendAdminNotification(
        $conn,
        $userId,
        $userType,
        'health_profile_submission',
        'New Health Profile Submission',
        'has submitted their health profile',
        'medical-documents.php'
    );


                            $response['messages'][$inputName] = ucfirst(str_replace('_', ' ', $inputName)) . ' uploaded successfully!';
                        } else {
                            error_log("Execute failed for medical_documents insert: " . $stmt->error);
                            $response['messages'][$inputName] = 'Failed to save ' . str_replace('_', ' ', $inputName) . ' to database.';
                            $response['status'] = 'error';
                        }
                        $stmt->close();
                    } elseif (isset($_FILES[$inputName]) && $_FILES[$inputName]['error'] !== UPLOAD_ERR_NO_FILE) {
                        $response['messages'][$inputName] = 'Error uploading ' . str_replace('_', ' ', $inputName) . '.';
                        $response['status'] = 'error';
                    }
                }

                // Update submission_status if at least one document was uploaded successfully
                if ($response['status'] === 'success' && !empty($response['messages'])) {
                    $query = "INSERT INTO submission_status (user_id, has_submitted, submitted_at) 
                              VALUES (?, 1, NOW()) 
                              ON DUPLICATE KEY UPDATE has_submitted = 1, submitted_at = NOW()";
                    if (!$stmt = $conn->prepare($query)) {
                        error_log("Prepare failed for submission_status insert: " . $conn->error);
                        $response['messages']['general'] = 'Failed to update submission status.';
                        $response['status'] = 'error';
                    } else {
                        $stmt->bind_param('i', $userId);
                        if (!$stmt->execute()) {
                            error_log("Execute failed for submission_status insert: " . $stmt->error);
                            $response['messages']['general'] = 'Failed to update submission status.';
                            $response['status'] = 'error';
                        }
                        $stmt->close();
                    }
                }
            } else if ($userType === 'Incoming Freshman' && $hepa_b_indicator === '1') {
                $documents = [
                    'chest_xray' => 'chest_xray_results',
                    'cbc' => 'complete_blood_count_results',
                    'blood_typing' => 'blood_typing_results',
                    'urinalysis' => 'urinalysis_results',
                    'drug_test' => 'drug_test_results',
                    'hepatitis_b_surface_antigen_test' => 'hepatitis_b_surface_antigen_test_results'

                ];
                foreach ($documents as $inputName => $documentType) {
                    if (isset($_FILES[$inputName]) && $_FILES[$inputName]['error'] === UPLOAD_ERR_OK) {
                        $file = $_FILES[$inputName];
                        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        $originalFileName = basename($file['name']);
                        if (!in_array($ext, ['pdf', 'docx', 'jpg', 'jpeg', 'png'])) {
                            $response['messages'][$inputName] = 'Invalid file type for ' . str_replace('_', ' ', $inputName) . '. Only PDF, DOCX, JPG, JPEG, PNG allowed.';
                            $response['status'] = 'error';
                            continue;
                        }
                        if ($file['size'] > 50 * 1024 * 1024) {
                            $response['messages'][$inputName] = 'File size for ' . str_replace('_', ' ', $inputName) . ' exceeds 50MB.';
                            $response['status'] = 'error';
                            continue;
                        }

                        // Generate server-side file name
                        $fileName = $documentType . '_' . $userId . '_' . time() . '.' . $ext;
                        $filePath = $uploadDir . $fileName;

                        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                            error_log("Failed to move uploaded file to: $filePath");
                            $response['messages'][$inputName] = 'Failed to upload ' . str_replace('_', ' ', $inputName) . '.';
                            $response['status'] = 'error';
                            continue;
                        }

                        // Ensure file was written correctly
                        if (!file_exists($filePath)) {
                            error_log("Uploaded file not found after move: $filePath");
                            $response['messages'][$inputName] = 'Failed to save ' . str_replace('_', ' ', $inputName) . '.';
                            $response['status'] = 'error';
                            continue;
                        }

                        $query = "INSERT INTO medical_documents (user_id, document_type, file_path, original_file_name, status, submitted_at) VALUES (?, ?, ?, ?, 'pending', NOW())";
                        if (!$stmt = $conn->prepare($query)) {
                            error_log("Prepare failed for medical_documents insert: " . $conn->error);
                            $response['messages'][$inputName] = 'Failed to save ' . str_replace('_', ' ', $inputName) . ' to database.';
                            $response['status'] = 'error';
                            continue;
                        }
                    $relativePath = $fileUploaded ? '../Uploads/medical_documents/' . $fileName : null;
                        $stmt->bind_param('isss', $userId, $documentType, $relativePath, $originalFileName);
                        if ($stmt->execute()) {
                               sendAdminNotification(
        $conn,
        $userId,
        $userType,
        'medical_certificate_submission',
        'New Medical Certificate Submission',
        'has submitted a medical certificate',
        'medical-documents.php'
    );
                            $response['messages'][$inputName] = ucfirst(str_replace('_', ' ', $inputName)) . ' uploaded successfully!';
                        } else {
                            error_log("Execute failed for medical_documents insert: " . $stmt->error);
                            $response['messages'][$inputName] = 'Failed to save ' . str_replace('_', ' ', $inputName) . ' to database.';
                            $response['status'] = 'error';
                        }
                        $stmt->close();
                    } elseif (isset($_FILES[$inputName]) && $_FILES[$inputName]['error'] !== UPLOAD_ERR_NO_FILE) {
                        $response['messages'][$inputName] = 'Error uploading ' . str_replace('_', ' ', $inputName) . '.';
                        $response['status'] = 'error';
                    }
                }

                // Update submission_status if at least one document was uploaded successfully
                if ($response['status'] === 'success' && !empty($response['messages'])) {
                    $query = "INSERT INTO submission_status (user_id, has_submitted, submitted_at) 
                              VALUES (?, 1, NOW()) 
                              ON DUPLICATE KEY UPDATE has_submitted = 1, submitted_at = NOW()";
                    if (!$stmt = $conn->prepare($query)) {
                        error_log("Prepare failed for submission_status insert: " . $conn->error);
                        $response['messages']['general'] = 'Failed to update submission status.';
                        $response['status'] = 'error';
                    } else {
                        $stmt->bind_param('i', $userId);
                        if (!$stmt->execute()) {
                            error_log("Execute failed for submission_status insert: " . $stmt->error);
                            $response['messages']['general'] = 'Failed to update submission status.';
                            $response['status'] = 'error';
                        }
                        $stmt->close();
                    }
                }
            } else {
                // Handle other user types (medical certificate)
                $childId = isset($_POST['child_id']) && !empty($_POST['child_id']) ? (int)$_POST['child_id'] : null;
                $requestType = $_POST['request-type'] ?? '';
                $reason = $_POST['reason'] ?? '';
                $competitionScope = isset($_POST['competition-scope']) && !empty($_POST['competition-scope']) ? $_POST['competition-scope'] : null;

                // Validate inputs
                if (empty($requestType) || empty($reason)) {
                    $response['messages']['general'] = 'Request type and reason are required.';
                    $response['status'] = 'error';
                    echo json_encode($response);
                    exit;
                }

                // Validate child_id for Parent users
                if ($userType === 'Parent' && $childId) {
                    $query = "SELECT c.id 
                              FROM children c 
                              JOIN patients pt ON c.parent_id = pt.id 
                              JOIN parents p ON p.user_id = pt.user_id 
                              WHERE p.user_id = ? AND c.id = ?";
                    if (!$stmt = $conn->prepare($query)) {
                        error_log("Prepare failed for child validation: " . $conn->error);
                        $response['messages']['general'] = 'Server error during child validation.';
                        $response['status'] = 'error';
                        echo json_encode($response);
                        exit;
                    }
                    $stmt->bind_param('ii', $userId, $childId);
                    if (!$stmt->execute()) {
                        error_log("Execute failed for child validation: " . $stmt->error);
                        $response['messages']['general'] = 'Server error during child validation.';
                        $response['status'] = 'error';
                        echo json_encode($response);
                        exit;
                    }
                    $result = $stmt->get_result();
                    if ($result->num_rows === 0) {
                        $response['messages']['general'] = 'Invalid child selected.';
                        $response['status'] = 'error';
                        $stmt->close();
                        echo json_encode($response);
                        exit;
                    }
                    $stmt->close();
                }

                // Handle file upload (if required)
                $fileUploaded = false;
                if (isset($_FILES['xray-result']) && $_FILES['xray-result']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['xray-result'];
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $originalFileName = basename($file['name']);
                    if (!in_array($ext, ['pdf', 'docx', 'jpg', 'jpeg', 'png'])) {
                        $response['messages']['xray-result'] = 'Invalid file type for medical certificate. Only PDF, DOCX, JPG, JPEG, PNG allowed.';
                        $response['status'] = 'error';
                    } elseif ($file['size'] > 50 * 1024 * 1024) {
                        $response['messages']['xray-result'] = 'File size for medical certificate exceeds 50MB.';
                        $response['status'] = 'error';
                    } else {
                        $fileName = 'med_cert_' . $userId . '_' . time() . '.' . $ext;
                        $filePath = $uploadDir . $fileName;

                        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                            error_log("Failed to move uploaded file to: $filePath");
                            $response['messages']['xray-result'] = 'Failed to upload medical certificate.';
                            $response['status'] = 'error';
                        } else {
                            // Ensure file was written correctly
                            if (!file_exists($filePath)) {
                                error_log("Uploaded file not found after move: $filePath");
                                $response['messages']['xray-result'] = 'Failed to save medical certificate.';
                                $response['status'] = 'error';
                            } else {
                                $fileUploaded = true;
                            }
                        }
                    }
                } elseif (isset($_FILES['xray-result']) && $_FILES['xray-result']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $response['messages']['xray-result'] = 'Error uploading medical certificate.';
                    $response['status'] = 'error';
                }

                // Determine if file upload is required
                $requiresXray = $requestType === 'ojt-internship' ||
                    in_array($reason, ['travel-national', 'travel-international']) ||
                    ($reason === 'school-competition' && in_array($competitionScope, ['regional', 'national', 'international']));

                if ($requiresXray && !$fileUploaded) {
                    $response['messages']['xray-result'] = 'Chest X-Ray result is required for this request.';
                    $response['status'] = 'error';
                    echo json_encode($response);
                    exit;
                }

                // Save to medical_documents (even if no file for absence-illness)
                $query = "INSERT INTO medical_documents (user_id, child_id, document_type, file_path, original_file_name, request_type, reason, competition_scope, status, submitted_at) 
                          VALUES (?, ?, 'medical_certificate', ?, ?, ?, ?, ?, 'pending', NOW())";
                if (!$stmt = $conn->prepare($query)) {
                    error_log("Prepare failed for medical_documents insert: " . $conn->error);
                    $response['messages']['general'] = 'Failed to save medical certificate to database.';
                    $response['status'] = 'error';
                    echo json_encode($response);
                    exit;
                }

                $relativePath = $fileUploaded ? '../Uploads/medical_documents/' . $fileName : null;
                $originalFileName = $fileUploaded ? basename($file['name']) : null;
                $stmt->bind_param('iisssss', $userId, $childId, $relativePath, $originalFileName, $requestType, $reason, $competitionScope);
                if ($stmt->execute()) {
                       sendAdminNotification(
        $conn,
        $userId,
        $userType,
        'medical_certificate_submission',
        'New Medical Certificate Submission',
        'has submitted a medical certificate',
        'medical-documents.php'
    );
                    $response['messages']['general'] = 'Medical certificate request submitted successfully!';
                } else {
                    error_log("Execute failed for medical_documents insert: " . $stmt->error);
                    $response['messages']['general'] = 'Failed to save medical certificate to database.';
                    $response['status'] = 'error';
                }
                $stmt->close();
            }

            if (empty($response['messages'])) {
                $response['status'] = 'error';
                $response['messages']['general'] = 'No files were uploaded or request was not processed.';
            }
        } elseif ($_POST['action'] === 'edit_document') {
            // Handle document edit
            $documentId = isset($_POST['document_id']) ? (int)$_POST['document_id'] : 0;
            if (!$documentId) {
                $response['status'] = 'error';
                $response['messages']['general'] = 'Invalid document ID.';
                echo json_encode($response);
                exit;
            }

            // Verify document belongs to user
            $query = "SELECT file_path, document_type FROM medical_documents WHERE id = ? AND user_id = ?";
            if (!$stmt = $conn->prepare($query)) {
                error_log("Prepare failed for document verification: " . $conn->error);
                throw new Exception("Database error: Unable to verify document");
            }
            $stmt->bind_param('ii', $documentId, $userId);
            if (!$stmt->execute()) {
                error_log("Execute failed for document verification: " . $stmt->error);
                throw new Exception("Database error: Unable to verify document");
            }
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                $response['status'] = 'error';
                $response['messages']['general'] = 'Document not found or unauthorized.';
                echo json_encode($response);
                exit;
            }
            $row = $result->fetch_assoc();
            $oldFilePath = realpath($row['file_path']);
            $documentType = $row['document_type'];
            $stmt->close();
if (isset($_FILES['edit_file']) && $_FILES['edit_file']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['edit_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $originalFileName = basename($file['name']);

    if (!in_array($ext, ['pdf', 'docx', 'jpg', 'jpeg', 'png'])) {
        $response['status'] = 'error';
        $response['messages']['general'] = 'Invalid file type. Only PDF, DOCX, JPG, JPEG, PNG allowed.';
    } elseif ($file['size'] > 50 * 1024 * 1024) {
        $response['status'] = 'error';
        $response['messages']['general'] = 'File size exceeds 50MB.';
    } else {
        // build file path
        $fileName = $userId . '_' . time() . '.' . $ext; // removed $documentType to avoid undefined error
        $relativePath = '../Uploads/medical_documents/' . $fileName;
        $filePath = $uploadDir . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            error_log("Failed to move uploaded file to: $filePath");
            $response['status'] = 'error';
            $response['messages']['general'] = 'Failed to upload new file.';
        } else {
            // Update DB
            $query = "UPDATE medical_documents 
                      SET file_path = ?, original_file_name = ?, status = 'pending', submitted_at = NOW() 
                      WHERE id = ?";
            if (!$stmt = $conn->prepare($query)) {
                error_log("Prepare failed: " . $conn->error);
                $response['status'] = 'error';
                $response['messages']['general'] = 'Failed to update document.';
            } else {
                $stmt->bind_param('ssi', $relativePath, $originalFileName, $documentId);
                if ($stmt->execute()) {
                    // delete old file if exists
                    if (!empty($oldFilePath) && file_exists($oldFilePath)) {
                        unlink($oldFilePath);
                    }

                    // send notification
                    sendAdminNotification(
                        $conn,
                        $userId,
                        $userType,
                        'document_update',
                        'Document Update',
                        'has updated a medical document',
                        'medical-documents.php'
                    );

                    $response['messages']['general'] = 'Document updated successfully!';
                } else {
                    error_log("Execute failed: " . $stmt->error);
                    $response['status'] = 'error';
                    $response['messages']['general'] = 'Failed to update document.';
                }
                $stmt->close();
            }
        }
    }
} else {
                $response['status'] = 'error';
                $response['messages']['general'] = 'No file uploaded or upload error.';
            }
        }
    } catch (Exception $e) {
        error_log("Upload/Edit error: " . $e->getMessage());
        $response['status'] = 'error';
        $response['messages']['general'] = 'Server error occurred. Please try again later.';
    }

    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Documents - WMSU Health Services</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/upload.css">
    <link rel="apple-touch-icon" sizes="180x180" href="../images/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../images/favicon-16x16.png">
    <link rel="manifest" href="images/site.webmanifest">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<style>
    body{ font-family: 'Poppins';}
    </style>
<body>
    <div id="app" class="d-flex">
        <button id="sidebar-toggle" class="sidebar-toggle d-lg-none">☰</button>
        <div id="sidebar" class="sidebar d-flex flex-column align-items-center">
            <img src="images/clinic.png" alt="WMSU Clinic Logo" class="logo">
            <div class="text-white fw-bold text-uppercase text-center mt-2">WMSU</div>
            <div class="health-services text-white fw-bold text-uppercase text-center mt-2 mb-5">Health Services</div>
            <button class="btn btn-crimson mb-2 w-100" id="about-btn" onclick="window.location.href='homepage.php'">About Us</button>
            <button class="btn btn-crimson mb-2 w-100" id="announcement-btn" onclick="window.location.href='announcements.php'">Announcements</button>
            <button 
  class="btn btn-crimson mb-2 w-100 <?php echo ($userType === 'Incoming Freshman') ? 'btn-disabled' : ''; ?>" 
  id="appointment-btn"
  <?php if ($userType === 'Incoming Freshman'): ?>
    disabled
  <?php else: ?>
    onclick="window.location.href='/wmsu/appointment.php'"
  <?php endif; ?>
>
  Appointment Request
</button>
            <button class="btn btn-crimson mb-2 w-100 active" id="upload-btn" onclick="window.location.href='upload.php'">Upload Medical Documents</button>
            <button class="btn btn-crimson mb-2 w-100" id="profile-btn" onclick="window.location.href='profile.php'">Profile</button>
            <button class="btn btn-crimson w-100" id="logout-btn" data-bs-toggle="modal" data-bs-target="#logoutModal">Logout</button>
            <a href="https://wmsu.edu.ph" target="_blank" class="quick-link">wmsu.edu.ph</a>
        </div>

        <div class="main-content">
            <div class="content-dim"></div>
            <div class="content-wrapper">
                <div class="container">
                    <h1 class="title text-center mb-4">Medical Documents</h1>
                    <ul class="nav nav-tabs mb-4">
                        <li class="nav-item">
                            <a class="nav-link active" id="upload-tab" data-bs-toggle="tab" href="#upload">Upload Medical Documents</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="history-tab" data-bs-toggle="tab" href="#history">Upload History</a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <!-- Upload Tab -->
                        <div class="tab-pane fade show active" id="upload">
                            <?php if ($userType === 'Incoming Freshman' && $hasSubmitted): ?>
                                <p>You have already submitted your medical documents.</p>
                            <?php elseif ($userType === 'Incoming Freshman' && $hepa_b_indicator == 0): ?>
                                <!-- Incoming Freshman View -->
                                <form id="upload-form" enctype="multipart/form-data">
                                    <div class="mb-3 form-group">
                                        <label for="chest-xray" class="form-label">Chest X-Ray Results (PDF, DOCX, JPG, JPEG, PNG, Max 50MB, Valid for 6 months)</label>
                                        <input type="file" class="form-control" id="chest-xray" name="chest_xray" accept=".pdf,.docx,.jpg,.jpeg,.png">
                                        <div id="chest-xray-feedback" class="upload-feedback"></div>
                                    </div>
                                    <div class="mb-3 form-group">
                                        <label for="cbc" class="form-label">Complete Blood Count Results (PDF, DOCX, JPG, JPEG, PNG, Max 50MB)</label>
                                        <input type="file" class="form-control" id="cbc" name="cbc" accept=".pdf,.docx,.jpg,.jpeg,.png">
                                        <div id="cbc-feedback" class="upload-feedback"></div>
                                    </div>
                                    <div class="mb-3 form-group">
                                        <label for="blood-typing" class="form-label">Blood Typing Results (PDF, DOCX, JPG, JPEG, PNG, Max 50MB)</label>
                                        <input type="file" class="form-control" id="blood-typing" name="blood_typing" accept=".pdf,.docx,.jpg,.jpeg,.png">
                                        <div id="blood-typing-feedback" class="upload-feedback"></div>
                                    </div>
                                    <div class="mb-3 form-group">
                                        <label for="urinalysis" class="form-label">Urinalysis Results (PDF, DOCX, JPG, JPEG, PNG, Max 50MB)</label>
                                        <input type="file" class="form-control" id="urinalysis" name="urinalysis" accept=".pdf,.docx,.jpg,.jpeg,.png">
                                        <div id="urinalysis-feedback" class="upload-feedback"></div>
                                    </div>
                                    <div class="mb-3 form-group">
                                        <label for="drug-test" class="form-label">Drug Test Results (PDF, DOCX, JPG, JPEG, PNG, Max 50MB, Valid for 1 year)</label>
                                        <input type="file" class="form-control" id="drug-test" name="drug_test" accept=".pdf,.docx,.jpg,.jpeg,.png">
                                        <div id="drug-test-feedback" class="upload-feedback"></div>
                                    </div>
                                    <button type="submit" class="btn btn-crimson" id="submit-upload">Submit</button>
                                </form>
                            <?php elseif ($userType === 'Incoming Freshman' && $hepa_b_indicator == 1): ?>
                                <!-- Incoming Freshman View -->
                                <form id="upload-form" enctype="multipart/form-data">
                                    <div class="mb-3 form-group">
                                        <label for="chest-xray" class="form-label">Chest X-Ray Results (PDF, DOCX, JPG, JPEG, PNG, Max 50MB, Valid for 6 months)</label>
                                        <input type="file" class="form-control" id="chest-xray" name="chest_xray" accept=".pdf,.docx,.jpg,.jpeg,.png">
                                        <div id="chest-xray-feedback" class="upload-feedback"></div>
                                    </div>
                                    <div class="mb-3 form-group">
                                        <label for="cbc" class="form-label">Complete Blood Count Results (PDF, DOCX, JPG, JPEG, PNG, Max 50MB)</label>
                                        <input type="file" class="form-control" id="cbc" name="cbc" accept=".pdf,.docx,.jpg,.jpeg,.png">
                                        <div id="cbc-feedback" class="upload-feedback"></div>
                                    </div>
                                    <div class="mb-3 form-group">
                                        <label for="blood-typing" class="form-label">Blood Typing Results (PDF, DOCX, JPG, JPEG, PNG, Max 50MB)</label>
                                        <input type="file" class="form-control" id="blood-typing" name="blood_typing" accept=".pdf,.docx,.jpg,.jpeg,.png">
                                        <div id="blood-typing-feedback" class="upload-feedback"></div>
                                    </div>
                                    <div class="mb-3 form-group">
                                        <label for="urinalysis" class="form-label">Urinalysis Results (PDF, DOCX, JPG, JPEG, PNG, Max 50MB)</label>
                                        <input type="file" class="form-control" id="urinalysis" name="urinalysis" accept=".pdf,.docx,.jpg,.jpeg,.png">
                                        <div id="urinalysis-feedback" class="upload-feedback"></div>
                                    </div>
                                    <div class="mb-3 form-group">
                                        <label for="drug-test" class="form-label">Drug Test Results (PDF, DOCX, JPG, JPEG, PNG, Max 50MB, Valid for 1 year)</label>
                                        <input type="file" class="form-control" id="drug-test" name="drug_test" accept=".pdf,.docx,.jpg,.jpeg,.png">
                                        <div id="drug-test-feedback" class="upload-feedback"></div>
                                    </div>
                                    <div class="mb-3 form-group">
                                        <label for="hepatitis-b-test" class="form-label">Hepatitis B Surface Antigen Test (PDF, DOCX, JPG, JPEG, PNG, Max 50MB, Valid for 1 year)</label>
                                        <input type="file" class="form-control" id="hepatitis-b-test" name="hepatitis_b_surface_antigen_test" accept=".pdf,.docx,.jpg,.jpeg,.png">
                                        <div id="hepatitis-b-test-feedback" class="upload-feedback"></div>
                                    </div>
                                    <button type="submit" class="btn btn-crimson" id="submit-upload">Submit</button>
                                </form>
                            <?php else: ?>
                                <!-- Other User Types View -->
                                <h3 class="section-title">Medical Certificate Request</h3>
                                <form id="med-cert-form" enctype="multipart/form-data">
                                    <?php if ($userType === 'Parent' && !empty($children)): ?>
                                        <div class="mb-3">
                                            <label for="child-id" class="form-label">Select Child</label>
                                            <select class="form-select" id="child-id" name="child_id" required>
                                                <option value="" disabled selected>Select your child</option>
                                                <?php foreach ($children as $child): ?>
                                                    <option value="<?php echo htmlspecialchars($child['id']); ?>">
                                                        <?php echo htmlspecialchars(trim($child['name'])); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endif; ?>
                                    <div class="mb-3">
                                        <h4 class="section-title">Request Type</h4>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="request-type" id="ojt-internship" value="ojt-internship" required>
                                            <label class="form-check-label" for="ojt-internship">OJT/Internship</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="request-type" id="non-ojt" value="non-ojt" required>
                                            <label class="form-check-label" for="non-ojt">Non-OJT/Internship</label>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="reason" class="form-label">Reason for Medical Certificate</label>
                                        <select class="form-select" id="reason" name="reason" required>
                                            <option value="" disabled selected>Select a reason</option>
                                        </select>
                                    </div>
                                    <div class="mb-3 competition-scope" style="display: none;">
                                        <label for="competition-scope" class="form-label">Competition Scope</label>
                                        <select class="form-select" id="competition-scope" name="competition-scope">
                                            <option value="" disabled selected>Select scope</option>
                                            <option value="local">Local</option>
                                            <option value="regional">Regional</option>
                                            <option value="national">National</option>
                                            <option value="international">International</option>
                                        </select>
                                    </div>
                                    <div class="mb-3 xray-upload">
                                        <label for="xray-result" class="form-label">Upload Chest X-Ray Result (PDF, DOCX, JPG, JPEG, PNG, Max 50MB)</label>
                                        <input type="file" class="form-control" id="xray-result" name="xray-result" accept=".pdf,.docx,.jpg,.jpeg,.png">
                                        <div id="xray-result-feedback" class="upload-feedback"></div>
                                    </div>
                                    <button type="submit" class="btn btn-crimson" id="submit-med-cert">Submit Request</button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <!-- History Tab -->
                        <div class="tab-pane fade" id="history">
                            <h3 class="section-title">Upload History</h3>
                            <?php if ($userType === 'Parent' && !empty($children)): ?>
                                <div class="mb-3">
                                    <label for="history-child-id" class="form-label">Select Child</label>
                                    <select class="form-select" id="history-child-id" name="child_id">
                                        <option value="" <?php echo $selectedChildId === null ? 'selected' : ''; ?>>All Children</option>
                                        <?php foreach ($children as $child): ?>
                                            <option value="<?php echo htmlspecialchars($child['id']); ?>" <?php echo $selectedChildId === $child['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars(trim($child['name'])); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <?php if (empty($uploadHistory)): ?>
                                <p>No documents have been uploaded yet.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered text-center">
                                        <thead>
                                            <tr>
                                                <th>Document Type</th>
                                                <th>File Name</th>
                                                <th>Status</th>
                                                <th>Submitted At</th>
                                                <?php if ($userType === 'Parent'): ?>
                                                    <th>Child</th>
                                                <?php endif; ?>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            // Map child_id to child name for display
                                            $childMap = [];
                                            foreach ($children as $child) {
                                                $childMap[$child['id']] = trim($child['name']);
                                            }
                                            // Map document types to display names
                                            $documentTypeMap = [
                                                'chest_xray_results' => 'Chest X-Ray Results',
                                                'complete_blood_count_results' => 'Complete Blood Count Results',
                                                'blood_typing_results' => 'Blood Typing Results',
                                                'urinalysis_results' => 'Urinalysis Results',
                                                'drug_test_results' => 'Drug Test Results',
                                                'hepatitis_b_surface_antigen_test' => 'Hepatitis B Surface Antigen Test',
                                                'medical_certificate' => 'Medical Certificate'
                                            ];
                                            ?>
                                         <?php foreach ($uploadHistory as $upload): ?>
    <tr>
        <!-- Document Type -->
        <td>
            <?php echo htmlspecialchars(
                $documentTypeMap[$upload['document_type']] 
                ?? ucwords(str_replace('_', ' ', $upload['document_type']))
            ); ?>
        </td>

        <!-- Original File Name -->
        <td>
            <?php echo htmlspecialchars($upload['original_file_name'] ?? ucfirst($upload['reason']) . ' does not require any uploads.'); ?>
        </td>

        <!-- Status -->
        <td>
            <?php echo htmlspecialchars(ucfirst($upload['status'] ?? '')); ?>
        </td>

        <!-- Submitted At -->
        <td>
            <?php 
                echo !empty($upload['submitted_at']) 
                    ? htmlspecialchars(date('Y-m-d H:i:s', strtotime($upload['submitted_at']))) 
                    : 'N/A'; 
            ?>
        </td>

        <!-- Child Name (for Parents only) -->
        <?php if ($userType === 'Parent'): ?>
            <td>
                <?php echo htmlspecialchars($childMap[$upload['child_id']] ?? 'N/A'); ?>
            </td>
        <?php endif; ?>

        <!-- Actions -->
        <td>
            <?php if (!empty($upload['file_path'])): ?>
                <button 
                    class="btn btn-sm btn-crimson view-document"
                    data-file="serve_file.php?id=<?php echo (int)$upload['id']; ?>"
                    data-ext="<?php echo htmlspecialchars(pathinfo($upload['file_path'], PATHINFO_EXTENSION)); ?>"
                    data-bs-toggle="modal"
                    data-bs-target="#viewModal">
                    View
                </button>
            <?php endif; ?>

            <button 
                class="btn btn-sm btn-crimson edit-document"
                data-id="<?php echo (int)$upload['id']; ?>"
                data-bs-toggle="modal"
                data-bs-target="#editModal">
                Edit
            </button>
        </td>
    </tr>
<?php endforeach; ?>

                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="faq-section">
                    <h2 class="faq-title text-center">Frequently Asked Questions (FAQ)</h2>
                    <div class="container mt-4">
                        <div class="list-group">
                            <button class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#faq1">How do we fill-up the forms and annotate our signatures electronically?</button>
                            <button class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#faq2">What if I don't have a laptop or a phone with internet access?</button>
                            <button class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#faq3">May we avail old chest-x ray and/or blood typing results?</button>
                            <button class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#faq4">May we submit a medical certificate from another physician?</button>
                            <button class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#faq5">How long do I have to wait before the release of my medical certificate?</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FAQ Modals -->
    <div class="modal fade" id="faq1" tabindex="-1" aria-labelledby="faq1Label" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="faq1Label">How do we fill-up the forms and annotate our signatures electronically?</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Using your laptop/computer, tablet, or cellphone, you may open and edit the forms using any PDF reader and editor (e.g. Adobe Acrobat, Foxit, Xodo, Microsoft Edge). To annotate your electronic signatures, you may insert an image of your signature or use the "draw" tool.
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="faq2" tabindex="-1" aria-labelledby="faq2Label" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="faq2Label">What if I don't have a laptop or a phone with internet access?</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    You may visit the College of Engineering Computer Laboratory (Campus A) to accomplish the electronic forms and stop by the Health Services Center to physically submit your chest x-ray and laboratory test results.
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="faq3" tabindex="-1" aria-labelledby="faq3Label" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="faq3Label">May we avail old chest-x ray and/or blood typing results?</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Yes. You may submit old chest x-ray or laboratory results from any DOH-accredited facility provided that they were done during the past 3 months.
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="faq4" tabindex="-1" aria-labelledby="faq4Label" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="faq4Label">May we submit a medical certificate from another physician?</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Yes. We offer a free medical certificate for all incoming freshmen to minimize the students' enrollment-related expenses. However, you are allowed to avail services from a physician of your choice, provided that you submit a copy of the medical certificate to the University Health Services Center. Note that you may still be required to fill-up the "Patient Health Profile & Consultations Record" and the "Waiver for Collection of Personal and Sensitive Health Information" upon your first consultation at the university clinic.
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="faq5" tabindex="-1" aria-labelledby="faq5Label" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="faq5Label">How long do I have to wait before the release of my medical certificate?</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Given the number of the university's incoming freshmen, please allow the Health Services Center 1-3 working days to process your request for a medical certificate.
                </div>
            </div>
        </div>
    </div>

    <!-- Logout Confirmation Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #a6192e; color: white;">
                    <h5 class="modal-title" id="logoutModalLabel">Confirm Logout</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to logout?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" style="background-color: #6c757d; color: white;" data-bs-dismiss="modal">No</button>
                    <button type="button" class="btn btn-sm" style="background-color: #a6192e; color: white;" onclick="window.location.href='logout.php'">Yes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Submission Restriction Modal -->
    <div class="modal fade" id="restrictionModal" tabindex="-1" aria-labelledby="restrictionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #a6192e; color: white;">
                    <h5 class="modal-title" id="restrictionModalLabel">Submission Restricted</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    You have already submitted your medical documents. You can view your submission history or go to your profile.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" style="background-color: #6c757d; color: white;" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm" style="background-color: #a6192e; color: white;" onclick="window.location.href='profile.php'">Go to Profile</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #a6192e; color: white;">
                    <h5 class="modal-title" id="successModalLabel">Submission Successful</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Your medical documents have been submitted successfully. You can view your submission history or go to your profile.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" style="background-color: #6c757d; color: white;" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm" style="background-color: #a6192e; color: white;" onclick="window.location.href='profile.php'">Go to Profile</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Document Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #a6192e; color: white;">
                    <h5 class="modal-title" id="viewModalLabel">View Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <iframe id="view-iframe" class="view-iframe" src=""></iframe>
                    <div id="view-error" class="error-message"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" style="background-color: #6c757d; color: white;" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Document Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #a6192e; color: white;">
                    <h5 class="modal-title" id="editModalLabel">Edit Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="edit-form" enctype="multipart/form-data">
                        <input type="hidden" id="edit-document-id" name="document_id">
                        <div class="mb-3">
                            <label for="edit-file" class="form-label">Upload New File (PDF, DOCX, JPG, JPEG, PNG, Max 50MB)</label>
                            <input type="file" class="form-control" id="edit-file" name="edit_file" accept=".pdf,.docx,.jpg,.jpeg,.png" required>
                            <div id="edit-feedback" class="upload-feedback"></div>
                        </div>
                        <button type="submit" class="btn btn-crimson" id="submit-edit">Submit</button>
                    </form>
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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Sidebar toggle functionality
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            const contentDim = document.querySelector('.content-dim');

            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    sidebar.classList.toggle('open');
                    mainContent.classList.toggle('sidebar-open');
                });
            }

            if (contentDim) {
                contentDim.addEventListener('click', function() {
                    sidebar.classList.remove('open');
                    mainContent.classList.remove('sidebar-open');
                });
            }

            document.addEventListener('click', function(event) {
                if (window.innerWidth <= 992) {
                    const isClickInsideSidebar = sidebar.contains(event.target);
                    const isClickOnToggle = event.target === sidebarToggle;
                    if (!isClickInsideSidebar && !isClickOnToggle && sidebar.classList.contains('open')) {
                        sidebar.classList.remove('open');
                        mainContent.classList.remove('sidebar-open');
                    }
                }
            });

            // Set active button
            const uploadBtn = document.getElementById('upload-btn');
            if (uploadBtn) {
                uploadBtn.classList.add('active');
            }

            // Handle child selection for history (only for Parent users)
            const isParentUser = <?php echo json_encode($userType === 'Parent'); ?>;
            if (isParentUser) {
                const historyChildSelect = document.getElementById('history-child-id');
                if (historyChildSelect) {
                    historyChildSelect.addEventListener('change', function() {
                        const childId = this.value;
                        window.location.href = 'upload.php?child_id=' + (childId ? encodeURIComponent(childId) : '');
                    });
                } else {
                    console.warn('Element with ID "history-child-id" not found. Skipping event listener attachment.');
                }
            }

            // Handle view document
            document.querySelectorAll('.view-document').forEach(button => {
                button.addEventListener('click', async () => {
                    const fileUrl = button.dataset.file;
                    const ext = button.dataset.ext.toLowerCase();
                    const iframe = document.getElementById('view-iframe');
                    const modalBody = iframe.parentNode;
                    const errorDiv = document.getElementById('view-error');

                    // Reset modal content
                    modalBody.querySelectorAll('img, .download-link, p').forEach(el => el.remove());
                    errorDiv.textContent = '';
                    iframe.src = '';
                    iframe.style.display = 'block';

                    try {
                        const response = await fetch(fileUrl, {
                            method: 'HEAD',
                            credentials: 'same-origin'
                        });
                        if (!response.ok) {
                            throw new Error(
                                response.status === 404 ? 'File not found on server.' :
                                response.status === 403 ? 'Unauthorized access. Please log in again.' :
                                response.status === 500 ? 'Server error. The file may be corrupted or inaccessible.' :
                                `Server error (${response.status}).`
                            );
                        }

                        if (['jpg', 'jpeg', 'png'].includes(ext)) {
                            iframe.style.display = 'none';
                            const img = document.createElement('img');
                            img.src = fileUrl;
                            img.style.maxWidth = '100%';
                            img.style.maxHeight = '500px';
                            img.style.display = 'block';
                            img.style.margin = 'auto';
                            img.onerror = () => {
                                errorDiv.textContent = `Error loading image (ID: ${button.dataset.file.split('=')[1]}). The file may be corrupted, inaccessible, or not a valid image.`;
                                console.error(`Image load error: ${fileUrl} (ID: ${button.dataset.file.split('=')[1]})`);
                                const downloadLink = document.createElement('a');
                                downloadLink.href = fileUrl;
                                downloadLink.className = 'download-link btn btn-crimson';
                                downloadLink.textContent = 'Download instead';
                                modalBody.appendChild(downloadLink);
                            };
                            modalBody.appendChild(img);
                        } else if (ext === 'pdf') {
                            iframe.src = fileUrl;
                            iframe.style.display = 'block';
                            iframe.onerror = () => {
                                errorDiv.textContent = 'Error loading PDF. The file may be corrupted or your browser does not support PDF viewing.';
                                console.error('PDF load error:', fileUrl);
                                const downloadLink = document.createElement('a');
                                downloadLink.href = fileUrl;
                                downloadLink.className = 'download-link btn btn-crimson';
                                downloadLink.textContent = 'Download instead';
                                modalBody.appendChild(downloadLink);
                            };
                            const downloadLink = document.createElement('a');
                            downloadLink.href = fileUrl;
                            downloadLink.className = 'download-link btn btn-secondary mt-2';
                            downloadLink.textContent = 'Download PDF';
                            modalBody.appendChild(downloadLink);
                        } else {
                            iframe.style.display = 'none';
                            const message = document.createElement('p');
                            message.textContent = 'This file type cannot be previewed. Please download to view.';
                            modalBody.appendChild(message);
                            const downloadLink = document.createElement('a');
                            downloadLink.href = fileUrl;
                            downloadLink.className = 'download-link btn btn-crimson';
                            downloadLink.textContent = 'Download Document';
                            modalBody.appendChild(downloadLink);
                        }
                    } catch (error) {
                        errorDiv.textContent = error.message;
                        console.error(`Fetch error: ${error.message} (URL: ${fileUrl})`);
                        const downloadLink = document.createElement('a');
                        downloadLink.href = fileUrl;
                        downloadLink.className = 'download-link btn btn-crimson';
                        downloadLink.textContent = 'Attempt Download';
                        modalBody.appendChild(downloadLink);
                    }
                });
            });

            // Reset view modal when closed
            const viewModal = document.getElementById('viewModal');
            if (viewModal) {
                viewModal.addEventListener('hidden.bs.modal', () => {
                    const iframe = document.getElementById('view-iframe');
                    const modalBody = iframe.parentNode;
                    const errorDiv = document.getElementById('view-error');
                    iframe.src = '';
                    iframe.style.display = 'block';
                    errorDiv.textContent = '';
                    modalBody.querySelectorAll('img, .download-link, p').forEach(el => el.remove());
                    modalBody.appendChild(iframe);
                    modalBody.appendChild(errorDiv);
                });
            }

            // Handle edit document
            const editForm = document.getElementById('edit-form');
            const editFeedback = document.getElementById('edit-feedback');
            const editSubmitButton = document.getElementById('submit-edit');
            const editDocumentId = document.getElementById('edit-document-id');

            document.querySelectorAll('.edit-document').forEach(button => {
                button.addEventListener('click', () => {
                    editDocumentId.value = button.dataset.id;
                    editFeedback.textContent = '';
                    editFeedback.className = 'upload-feedback';
                });
            });

            if (editForm) {
                editForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    editFeedback.textContent = '';
                    editFeedback.className = 'upload-feedback';

                    const file = document.getElementById('edit-file').files[0];
                    const validation = validateFile(file, 'document');
                    if (validation !== true) {
                        editFeedback.textContent = validation;
                        editFeedback.className = 'upload-feedback error';
                        return;
                    }

                    editSubmitButton.classList.add('loading');
                    editSubmitButton.textContent = 'Uploading...';

                    const formData = new FormData(editForm);
                    formData.append('action', 'edit_document');

                    try {
                        const response = await fetch('upload.php', {
                            method: 'POST',
                            body: formData,
                            credentials: 'same-origin',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });

                        if (!response.ok) {
                            throw new Error(`HTTP error ${response.status}`);
                        }
                        const result = await response.json();

                        if (result.status === 'error') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Update Failed',
                                text: result.messages.general || 'Failed to update document.',
                                confirmButtonColor: '#d33'
                            });
                        } else {
                            Swal.fire({
                                icon: 'success',
                                title: 'Update Successful',
                                text: result.messages.general || 'Document updated successfully.',
                                showConfirmButton: false,
                                timer: 1200
                            }).then(() => {
                                const editModal = bootstrap.Modal.getInstance(document.getElementById('editModal'));
                                editModal.hide();
                                setTimeout(() => location.reload(), 500);
                            });
                        }

                    } catch (error) {
                        editFeedback.textContent = error;
                        editFeedback.className = 'upload-feedback error';
                        console.error('Edit error:', error);
                    } finally {
                        editSubmitButton.classList.remove('loading');
                        editSubmitButton.textContent = 'Submit';
                    }
                });
            }

            // File validation function
            function validateFile(file, inputName) {
                if (!file) return 'No file selected.';
                const ext = file.name.split('.').pop().toLowerCase();
                const maxSize = 50 * 1024 * 1024; // 50MB
                if (!['pdf', 'docx', 'jpg', 'jpeg', 'png'].includes(ext)) {
                    return `Invalid file type for ${inputName}. Only PDF, DOCX, JPG, JPEG, PNG allowed.`;
                }
                if (file.size > maxSize) {
                    return `File size for ${inputName} exceeds 50MB.`;
                }
                return true;
            }

            <?php if ($userType === 'Incoming Freshman'): ?>
                const uploadForm = document.getElementById('upload-form');
                const submitButton = document.getElementById('submit-upload');

                const inputs = {
                    'chest-xray': document.getElementById('chest-xray'),
                    'cbc': document.getElementById('cbc'),
                    'blood-typing': document.getElementById('blood-typing'),
                    'urinalysis': document.getElementById('urinalysis'),
                    'drug-test': document.getElementById('drug-test')
                };

                const feedbacks = {
                    'chest-xray': document.getElementById('chest-xray-feedback'),
                    'cbc': document.getElementById('cbc-feedback'),
                    'blood-typing': document.getElementById('blood-typing-feedback'),
                    'urinalysis': document.getElementById('urinalysis-feedback'),
                    'drug-test': document.getElementById('drug-test-feedback')
                };

                <?php if ($hepa_b_indicator === '1'): ?>
                    inputs['hepatitis_b_surface_antigen_test'] = document.getElementById('hepatitis-b-test');
                    feedbacks['hepatitis-b-test'] = document.getElementById('hepatitis-b-test-feedback');
                <?php endif; ?>

                if (uploadForm) {
                    uploadForm.addEventListener('submit', async function(e) {
                        e.preventDefault();

                        // Clear feedbacks
                        Object.values(feedbacks).forEach(fb => {
                            fb.textContent = '';
                            fb.className = 'upload-feedback';
                        });

                        // Validate files
                        let hasError = false;
                        for (const [name, input] of Object.entries(inputs)) {
                            const validation = validateFile(input.files[0], name.replace('-', ' '));
                            if (validation !== true) {
                                feedbacks[name].textContent = validation;
                                feedbacks[name].className = 'upload-feedback error';
                                hasError = true;
                            }
                        }
                        if (hasError) return;

                        const hasFile = Object.values(inputs).some(input => input.files[0]);
                        if (!hasFile) {
                            feedbacks['chest-xray'].textContent = 'Please select at least one file to upload.';
                            feedbacks['chest-xray'].className = 'upload-feedback error';
                            return;
                        }

                        // Show SweetAlert2 loading
                        Swal.fire({
                            title: 'Uploading...',
                            text: 'Please wait while your files are being uploaded.',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });

                        const formData = new FormData(uploadForm);
                        formData.append('action', 'upload_documents');

                        try {
                            const response = await fetch('upload.php', {
                                method: 'POST',
                                body: formData,
                                credentials: 'same-origin',
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            });

                            if (!response.ok) {
                                throw new Error(`HTTP error ${response.status}`);
                            }

                            const result = await response.json();

                            Swal.close();

                            if (result.status === 'error') {
                                if (result.messages.general?.includes('already submitted')) {
                                    Swal.fire({
                                        icon: 'warning',
                                        title: 'Upload Restricted',
                                        text: 'You have already submitted your requirements.'
                                    });
                                } else {
                                    for (const [inputName, message] of Object.entries(result.messages)) {
                                        const feedback = feedbacks[inputName] || feedbacks['chest-xray'];
                                        feedback.textContent = message;
                                        feedback.className = 'upload-feedback error';
                                    }
                                }
                            } else {
                                for (const [inputName, message] of Object.entries(result.messages)) {
                                    const feedback = feedbacks[inputName];
                                    if (feedback) {
                                        feedback.textContent = message;
                                        feedback.className = 'upload-feedback success';
                                        inputs[inputName].value = '';
                                    }
                                }

                                Swal.fire({
                                    icon: 'success',
                                    title: 'Upload Successful',
                                    text: 'Your files have been uploaded successfully.',
                                    confirmButtonText: 'OK'
                                }).then(() => location.reload());
                            }

                        } catch (error) {
                            console.error('Upload error:', error);
                            Swal.close();
                            Swal.fire({
                                icon: 'error',
                                title: 'Upload Failed',
                                text: 'Failed to upload documents. Please try again later.'
                            });
                        }
                    });
                }
            <?php else: ?>
                // Handle form logic for non-Incoming Freshman users
                const medCertForm = document.getElementById('med-cert-form');
                const submitButton = document.getElementById('submit-med-cert');
                const ojtRadio = document.getElementById('ojt-internship');
                const nonOjtRadio = document.getElementById('non-ojt');
                const reasonSelect = document.getElementById('reason');
                const competitionScopeDiv = document.querySelector('.competition-scope');
                const competitionScopeSelect = document.getElementById('competition-scope');
                const xrayUpload = document.querySelector('.xray-upload');
                const xrayInput = document.getElementById('xray-result');
                const xrayFeedback = document.getElementById('xray-result-feedback');
                const ojtReasons = [{
                        value: 'internship-requirement',
                        text: 'Internship Program Requirement'
                    },
                    {
                        value: 'workplace-clearance',
                        text: 'Workplace Health Clearance'
                    },
                    {
                        value: 'travel-national',
                        text: 'Travel (National)'
                    },
                    {
                        value: 'travel-international',
                        text: 'Travel (International)'
                    },
                    {
                        value: 'medical-clearance',
                        text: 'Medical Clearance'
                    },
                    {
                        value: 'absence-illness',
                        text: 'Absence Due to Illness'
                    }
                ];
                const nonOjtReasons = [{
                        value: 'school-competition',
                        text: 'School Competition'
                    },
                    {
                        value: 'employment',
                        text: 'Employment'
                    },
                    {
                        value: 'travel-national',
                        text: 'Travel (National)'
                    },
                    {
                        value: 'travel-international',
                        text: 'Travel (International)'
                    },
                    {
                        value: 'medical-clearance',
                        text: 'Medical Clearance'
                    },
                    {
                        value: 'absence-illness',
                        text: 'Absence Due to Illness'
                    }
                ];

                function updateReasonDropdown(isOjt) {
                    const reasons = isOjt ? ojtReasons : nonOjtReasons;
                    reasonSelect.innerHTML = '<option value="" disabled selected>Select a reason</option>';
                    reasons.forEach(reason => {
                        const option = document.createElement('option');
                        option.value = reason.value;
                        option.text = reason.text;
                        reasonSelect.appendChild(option);
                    });
                }

                function toggleCompetitionScope() {
                    const isCompetition = reasonSelect.value === 'school-competition';
                    competitionScopeDiv.style.display = isCompetition ? 'block' : 'none';
                    competitionScopeSelect.required = isCompetition;
                    if (!isCompetition) {
                        competitionScopeSelect.value = '';
                    }
                }

                function toggleXrayUpload() {
                    const isOjt = ojtRadio.checked;
                    const reason = reasonSelect.value;
                    const scope = competitionScopeSelect.value;
                    const requiresXray = isOjt ||
                        reason === 'travel-national' ||
                        reason === 'travel-international' ||
                        (reason === 'school-competition' && (scope === 'regional' || scope === 'national' || scope === 'international'));
                    xrayUpload.style.display = requiresXray ? 'block' : 'none';
                    xrayInput.required = requiresXray;
                }

                if (ojtRadio) {
                    ojtRadio.addEventListener('change', () => {
                        updateReasonDropdown(true);
                        toggleCompetitionScope();
                        toggleXrayUpload();
                    });
                }

                if (nonOjtRadio) {
                    nonOjtRadio.addEventListener('change', () => {
                        updateReasonDropdown(false);
                        toggleCompetitionScope();
                        toggleXrayUpload();
                    });
                }

                if (reasonSelect) {
                    reasonSelect.addEventListener('change', () => {
                        toggleCompetitionScope();
                        toggleXrayUpload();
                    });
                }

                if (competitionScopeSelect) {
                    competitionScopeSelect.addEventListener('change', toggleXrayUpload);
                }

                // Handle form submission
                if (medCertForm) {
                    medCertForm.addEventListener('submit', async function(e) {
                        e.preventDefault();
                        xrayFeedback.textContent = '';
                        xrayFeedback.className = 'upload-feedback';

                        // Validate inputs
                        const formData = new FormData(medCertForm);
                        const requestType = formData.get('request-type');
                        const reason = formData.get('reason');
                        const competitionScope = formData.get('competition-scope');
                        const xrayFile = xrayInput.files[0];

                        const requiresXray = requestType === 'ojt-internship' ||
                            reason === 'travel-national' ||
                            reason === 'travel-international' ||
                            (reason === 'school-competition' && ['regional', 'national', 'international'].includes(competitionScope));

                        if (requiresXray && xrayFile) {
                            const validation = validateFile(xrayFile, 'Chest X-Ray Result');
                            if (validation !== true) {
                                xrayFeedback.textContent = validation;
                                xrayFeedback.className = 'upload-feedback error';
                                return;
                            }
                        } else if (requiresXray && !xrayFile) {
                            xrayFeedback.textContent = 'Chest X-Ray Result is required for this request.';
                            xrayFeedback.className = 'upload-feedback error';
                            return;
                        }

                        submitButton.classList.add('loading');
                        submitButton.textContent = 'Submitting...';

                        formData.append('action', 'upload_documents');

                        try {
                            const response = await fetch('upload.php', {
                                method: 'POST',
                                body: formData,
                                credentials: 'same-origin',
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            });

                            if (!response.ok) {
                                throw new Error(`HTTP error ${response.status}`);
                            }

                            const result = await response.json();
                            if (result.status === 'error') {
                                xrayFeedback.textContent = result.messages.general || result.messages['xray-result'] || 'Failed to submit request.';
                                xrayFeedback.className = 'upload-feedback error';
                            } else {
                                xrayFeedback.textContent = result.messages.general || 'Request submitted successfully!';
                                xrayFeedback.className = 'upload-feedback success';
                                const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                                successModal.show();
                                document.getElementById('successModal').addEventListener('hidden.bs.modal', () => {
                                    location.reload();
                                }, {
                                    once: true
                                });
                            }
                        } catch (error) {
                            xrayFeedback.textContent = 'Failed to submit request. Please try again.';
                            xrayFeedback.className = 'upload-feedback error';
                            console.error('Submission error:', error);
                        } finally {
                            submitButton.classList.remove('loading');
                            submitButton.textContent = 'Submit Request';
                        }
                    });
                }

                // Initialize form state
                updateReasonDropdown(false);
                toggleCompetitionScope();
                toggleXrayUpload();
            <?php endif; ?>
        });
    </script>
</body>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
  <?php include('notifications_user.php') ?>

</html>
<?php
$conn->close();
?>