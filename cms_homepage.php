<?php
session_start();
require_once 'config.php';

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("Unauthorized access to cms_homepage.php: No user_id in session, redirecting to /login");
    header("Location: login.php");
    exit();
}

// Check connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    $_SESSION['error'] = "Database error. Please try again.";
    header("Location: login.php");
    exit();
}

// Verify user is an admin
$userId = (int)$_SESSION['user_id'];
$query = "SELECT user_type FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    error_log("Admin verification query prepare failed: " . $conn->error);
    $_SESSION['error'] = "Database error. Please try again.";
    header("Location: login.php");
    exit();
}
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || !in_array(trim($user['user_type']), ['Super Admin', 'Medical Admin', 'Dental Admin'], true)) {
    error_log("Non-admin user_id: $userId, user_type: " . ($user['user_type'] ?? 'none') . " attempted access to cms_homepage.php, redirecting to /homepage");
    header("Location: homepage.php");
    exit();
}

// Function to get content from database
function getContent($conn, $pageName, $sectionName, $default = '') {
    $stmt = $conn->prepare("SELECT content_text FROM content WHERE page_name = ? AND section_name = ?");
    $stmt->bind_param("ss", $pageName, $sectionName);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['content_text']; // Return raw content (HTML allowed)
    }
    return $row['content_text']; 
}

// Function to get image from database
function getImage($conn, $pageName, $sectionName, $default = '') {
    $stmt = $conn->prepare("SELECT image_path, image_alt FROM images WHERE page_name = ? AND section_name = ?");
    $stmt->bind_param("ss", $pageName, $sectionName);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Check if path starts with /uploads/ and adjust if necessary
        if (strpos($row['image_path'], '/uploads/') === 0) {
            return $row;
        }
        
        // If path is relative but missing the leading slash
        if (strpos($row['image_path'], 'CMS/uploads/') === 0) {
            $row['image_path'] = '/' . $row['image_path'];
            return $row;
        }
        
        // If path is stored as absolute filesystem path
        $docRoot = $_SERVER['DOCUMENT_ROOT'];
        if (strpos($row['image_path'], $docRoot) === 0) {
            $row['image_path'] = str_replace($docRoot, '', $row['image_path']);
            return $row;
        }
        
        error_log("Image path format unexpected: " . $row['image_path']);
    }
    
    return ['image_path' => $default, 'image_alt' => ''];
}



// Function to update content in database
function updateContent($conn, $pageName, $sectionName, $content) {
    $stmt = $conn->prepare("INSERT INTO content (page_name, section_name, content_text) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE content_text = ?");
    $stmt->bind_param("ssss", $pageName, $sectionName, $content, $content);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// Function to handle file upload
function handleImageUpload($file, $uploadDir, $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp']) {
    if (!isset($file['tmp_name'])) {
        return ['success' => false, 'message' => 'No file uploaded'];
    }

    $absoluteUploadDir = $_SERVER['DOCUMENT_ROOT'] . $uploadDir;
    if (!is_dir($absoluteUploadDir)) {
        if (!mkdir($absoluteUploadDir, 0755, true)) {
            return ['success' => false, 'message' => 'Failed to create upload directory'];
        }
    }

    if (!is_writable($absoluteUploadDir)) {
        return ['success' => false, 'message' => 'Upload directory not writable'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.'];
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => 'File too large. Maximum size is 5MB.'];
    }

    $filename = preg_replace('/[^a-zA-Z0-9\.\-_]/', '', $file['name']);
    $filename = uniqid() . '_' . $filename;
    $relativePath = $uploadDir . $filename;
    $absolutePath = $_SERVER['DOCUMENT_ROOT'] . $relativePath;

    if (move_uploaded_file($file['tmp_name'], $absolutePath)) {
        return [
            'success' => true, 
            'path' => $relativePath,
            'filename' => $filename,
            'mime_type' => $mimeType,
            'size' => $file['size']
        ];
    } else {
        return ['success' => false, 'message' => 'Failed to save file'];
    }
}

// Function to update image in database
function updateImage($conn, $pageName, $sectionName, $imagePath, $imageAlt, $originalFilename, $mimeType, $fileSize) {
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM images WHERE page_name = ? AND section_name = ?");
    if (!$checkStmt) {
        error_log("Check statement preparation failed: " . $conn->error);
        return false;
    }
    
    $checkStmt->bind_param("ss", $pageName, $sectionName);
    if (!$checkStmt->execute()) {
        error_log("Check statement execution failed: " . $checkStmt->error);
        $checkStmt->close();
        return false;
    }
    
    $checkStmt->bind_result($count);
    $checkStmt->fetch();
    $checkStmt->close();

    if ($count > 0) {
        $query = "UPDATE images SET 
            image_path = ?, 
            image_alt = ?, 
            original_filename = ?, 
            mime_type = ?, 
            file_size = ?, 
            updated_date = CURRENT_TIMESTAMP 
            WHERE page_name = ? AND section_name = ?";
    } else {
        $query = "INSERT INTO images (
            page_name, 
            section_name, 
            image_path, 
            image_alt, 
            original_filename, 
            mime_type, 
            file_size
        ) VALUES (?, ?, ?, ?, ?, ?, ?)";
    }
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Image update statement preparation failed: " . $conn->error);
        return false;
    }
    
    if ($count > 0) {
        $stmt->bind_param("ssssiss", $imagePath, $imageAlt, $originalFilename, $mimeType, $fileSize, $pageName, $sectionName);
    } else {
        $stmt->bind_param("ssssssi", $pageName, $sectionName, $imagePath, $imageAlt, $originalFilename, $mimeType, $fileSize);
    }
    
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// Handle AJAX image upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_upload'])) {
    header('Content-Type: application/json');
    
    try {
        $section = $_POST['section'] ?? '';
        $imageAlt = $_POST['image_alt'] ?? '';
        
        if (!isset($_FILES['image'])) {
            throw new Exception('No file provided');
        }

        $uploadDir = 'uploads/uploads/';
        if (strpos($section, 'staff_') === 0) {
            $uploadDir .= 'staff/';
        } elseif (strpos($section, 'background_') === 0) {
            $uploadDir .= 'backgrounds/';
        } else {
            $uploadDir .= 'general/';
        }

        $uploadResult = handleImageUpload($_FILES['image'], $uploadDir);
        
        if (!$uploadResult['success']) {
            throw new Exception($uploadResult['message']);
        }

        $updateResult = updateImage(
            $conn, 
            'homepage', 
            $section, 
            $uploadResult['path'], 
            $imageAlt,
            $_FILES['image']['name'],
            $uploadResult['mime_type'],
            $uploadResult['size']
        );
        
        if (!$updateResult) {
            throw new Exception('Failed to update database');
        }

        echo json_encode([
            'success' => true, 
            'path' => $uploadResult['path'],
            'message' => 'Image uploaded successfully'
        ]);
        
    } catch (Exception $e) {
        error_log("Upload error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'Upload failed: ' . $e->getMessage()
        ]);
    }
    exit();
}

// Handle AJAX staff image upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_staff_image'])) {
    header('Content-Type: application/json');
    
    try {
        $staffId = (int)$_POST['staff_id'];
        $imageAlt = $_POST['image_alt'] ?? '';
        
        if (!isset($_FILES['image'])) {
            throw new Exception('No file provided');
        }

        $uploadDir = 'uploads/staff/';
        $uploadResult = handleImageUpload($_FILES['image'], $uploadDir);
        
        if (!$uploadResult['success']) {
            throw new Exception($uploadResult['message']);
        }

        $updateResult = updateStaffImage(
            $conn, 
            $staffId,
            $uploadResult['path'], 
            $imageAlt,
            $_FILES['image']['name'],
            $uploadResult['mime_type'],
            $uploadResult['size']
        );
        
        if (!$updateResult) {
            throw new Exception('Failed to update database');
        }

        echo json_encode([
            'success' => true, 
            'path' => $uploadResult['path'],
            'message' => 'Image uploaded successfully'
        ]);
        
    } catch (Exception $e) {
        error_log("Staff image upload error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'Upload failed: ' . $e->getMessage()
        ]);
    }
    exit();
}

// Handle AJAX staff operations
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_staff'])) {
    header('Content-Type: application/json');
    
    try {
        if ($_POST['staff_action'] == 'delete') {
            $result = deleteStaffMember($conn, (int)$_POST['staff_id']);
            echo json_encode(['success' => $result, 'message' => $result ? 'Staff member deleted' : 'Failed to delete']);
            exit();
        }
        elseif ($_POST['staff_action'] == 'add') {
            // First add the staff member
            $result = addStaffMember($conn, $_POST['name'], $_POST['position']);
            $staffId = $conn->insert_id;
            
            $imagePath = '';
            if ($result && $staffId > 0 && isset($_FILES['image'])) {
                // Handle image upload
                $uploadDir = 'uploads/staff/';
                $uploadResult = handleImageUpload($_FILES['image'], $uploadDir);
                
                if ($uploadResult['success']) {
                    $imagePath = $uploadResult['path'];
                    // Update the staff record with the image
                    updateStaffImage($conn, $staffId, $uploadResult['path'], 
                                'Staff photo', $_FILES['image']['name'],
                                $uploadResult['mime_type'], $uploadResult['size']);
                }
            }
            echo json_encode([
                'success' => $result && $staffId > 0,
                'message' => $result ? 'Staff member added' : 'Failed to add',
                'staff_id' => $staffId,
                'image_path' => $imagePath
            ]);
            exit();
        }
        elseif ($_POST['staff_action'] == 'update') {
            header('Content-Type: application/json');
            $success = updateStaffMember(
                $conn, 
                (int)$_POST['staff_id'], 
                $_POST['name'], 
                $_POST['position']
            );
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Updated successfully' : 'Update failed'
            ]);
            exit();
        }
    } catch (Exception $e) {
        error_log("Staff operation error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Operation failed: ' . $e->getMessage()]);
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_content'])) {
    $updates = [
        'about_us_title' => $_POST['about_us_title'],
        'about_us_text' => $_POST['about_us_text'],
        'welcome_title' => $_POST['welcome_title'],
        'welcome_text' => $_POST['welcome_text'],
        'staff_title' => $_POST['staff_title'],
        'staff_intro_text' => $_POST['staff_intro_text'],
        'core_values_title' => $_POST['core_values_title'],
        'core_value_1_title' => $_POST['core_value_1_title'],
        'core_value_1_desc' => $_POST['core_value_1_desc'],
        'core_value_2_title' => $_POST['core_value_2_title'],
        'core_value_2_desc' => $_POST['core_value_2_desc'],
        'core_value_3_title' => $_POST['core_value_3_title'],
        'core_value_3_desc' => $_POST['core_value_3_desc'],
        'core_value_4_title' => $_POST['core_value_4_title'],
        'core_value_4_desc' => $_POST['core_value_4_desc'],
        'vision_title' => $_POST['vision_title'],
        'vision_text' => $_POST['vision_text'],
        'mission_title' => $_POST['mission_title'],
        'mission_text' => $_POST['mission_text']
    ];

    $success = true;
    foreach ($updates as $section => $content) {
        if (!updateContent($conn, 'homepage', $section, $content)) {
            $success = false;
            break;
        }
    }

    if ($success) {
        $_SESSION['success'] = "Content updated successfully!";
    } else {
        $_SESSION['error'] = "Error updating content. Please try again.";
    }
    
    header("Location: cms_homepage.php");
    exit();
}

// Fetch current content
$content = [
    'about_us_title' => getContent($conn, 'homepage', 'about_us_title', 'About Us'),
    'about_us_text' => getContent($conn, 'homepage', 'about_us_text', 'The WMSU Health Services Center is dedicated to providing comprehensive and compassionate healthcare...'),
    'welcome_title' => getContent($conn, 'homepage', 'welcome_title', 'Welcome to WMSU Health Services'),
    'welcome_text' => getContent($conn, 'homepage', 'welcome_text', 'We are committed to providing accessible and high-quality healthcare services...'),
    'staff_title' => getContent($conn, 'homepage', 'staff_title', 'Our Dedicated Team'),
    'staff_intro_text' => getContent($conn, 'homepage', 'staff_intro_text', 'Meet the compassionate professionals who are here to serve your health needs...'),
    'core_values_title' => getContent($conn, 'homepage', 'core_values_title', 'Our Core Values'),
    'core_value_1_title' => getContent($conn, 'homepage', 'core_value_1_title', 'Excellence'),
    'core_value_1_desc' => getContent($conn, 'homepage', 'core_value_1_desc', 'We pursue excellence in everything we do, constantly striving to exceed expectations.'),
    'core_value_2_title' => getContent($conn, 'homepage', 'core_value_2_title', 'Integrity'),
    'core_value_2_desc' => getContent($conn, 'homepage', 'core_value_2_desc', 'We conduct business with honesty, transparency, and ethical standards.'),
    'core_value_3_title' => getContent($conn, 'homepage', 'core_value_3_title', 'Innovation'),
    'core_value_3_desc' => getContent($conn, 'homepage', 'core_value_3_desc', 'We embrace creativity and forward-thinking to develop cutting-edge solutions.'),
    'core_value_4_title' => getContent($conn, 'homepage', 'core_value_4_title', 'Collaboration'),
    'core_value_4_desc' => getContent($conn, 'homepage', 'core_value_4_desc', 'We believe in the power of teamwork and partnerships to achieve shared goals.'),
    'vision_title' => getContent($conn, 'homepage', 'vision_title', 'Vision'),
    'vision_text' => getContent($conn, 'homepage', 'vision_text', 'A premier university in the ASEAN region, recognized for its excellence in instruction, research, extension, and production...'),
    'mission_title' => getContent($conn, 'homepage', 'mission_title', 'Mission'),
    'mission_text' => getContent($conn, 'homepage', 'mission_text', 'WMSU commits to creating a vibrant atmosphere of learning where science, technology, innovation, research...')
];


// Fetch current images
$images = [
    'staff_felicitas' => getImage($conn, 'homepage', 'staff_felicitas', 'images/felicitas.jpg'),
    'staff_hamja' => getImage($conn, 'homepage', 'staff_hamja', 'images/hamja.jpg'),
    'staff_krishnon' => getImage($conn, 'homepage', 'staff_krishnon', 'images/krishnon.jpg'),
    'staff_hilda' => getImage($conn, 'homepage', 'staff_hilda', 'images/hilda.jpg'),
    'staff_harold' => getImage($conn, 'homepage', 'staff_harold', 'images/harold.jpg'),
    'staff_gemma' => getImage($conn, 'homepage', 'staff_gemma', 'images/gemma.jpg'),
    'staff_jac' => getImage($conn, 'homepage', 'staff_jac', 'images/jac.jpg'),
    'staff_joel' => getImage($conn, 'homepage', 'staff_joel', 'images/joel.jpg'),
    'logo' => getImage($conn, 'homepage', 'logo', 'images/clinic.png'),
    'background_about' => getImage($conn, 'homepage', 'background_about', 'images/12.jpg'),
    'background_staff' => getImage($conn, 'homepage', 'background_staff', 'images/healthservices.jpg'),
    'background_values' => getImage($conn, 'homepage', 'background_values', 'images/bg.jpg'),
    'background_vision' => getImage($conn, 'homepage', 'background_vision', 'images/staffs.jpg')
];


function updateStaffImage($conn, $staffId, $imagePath, $imageAlt, $originalFilename, $mimeType, $fileSize) {
    $query = "UPDATE staff SET 
        image_path = ?, 
        image_alt = ?, 
        original_filename = ?, 
        mime_type = ?, 
        file_size = ?, 
        updated_at = CURRENT_TIMESTAMP 
        WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Staff image update statement preparation failed: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("sssssi", $imagePath, $imageAlt, $originalFilename, $mimeType, $fileSize, $staffId);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

$staticStaff = [
    'felicitas' => [
        'id' => 'felicitas',
        'name' => 'Dr. Felicitas Asuncion C. Elago',
        'position' => 'Medical Officer III',
        'image_path' => $images['staff_felicitas']['image_path'],
        'image_alt' => $images['staff_felicitas']['image_alt']
    ],
    'hamja' => [
        'id' => 'hamja',
        'name' => 'Richard S. Hamja',
        'position' => 'University Nurse',
        'image_path' => $images['staff_hamja']['image_path'],
        'image_alt' => $images['staff_hamja']['image_alt']
    ],
    'krishnon' => [
        'id' => 'krishnon',
        'name' => 'Krishnon T. Lauron',
        'position' => 'Registered Nurse',
        'image_path' => $images['staff_krishnon']['image_path'],
        'image_alt' => $images['staff_krishnon']['image_alt']
    ],
    'hilda' => [
        'id' => 'hilda',
        'name' => 'Hilda De Jesus',
        'position' => 'Registered Nurse',
        'image_path' => $images['staff_hilda']['image_path'],
        'image_alt' => $images['staff_hilda']['image_alt']
    ],
    'harold' => [
        'id' => 'harold',
        'name' => 'Harold Mariano',
        'position' => 'Registered Nurse',
        'image_path' => $images['staff_harold']['image_path'],
        'image_alt' => $images['staff_harold']['image_alt']
    ],
    'gemma' => [
        'id' => 'gemma',
        'name' => 'Gemma Zorayda',
        'position' => 'Registered Nurse',
        'image_path' => $images['staff_gemma']['image_path'],
        'image_alt' => $images['staff_gemma']['image_alt']
    ],
    'jac' => [
        'id' => 'jac',
        'name' => 'Jacqueline Casintahan',
        'position' => 'Dental Aide',
        'image_path' => $images['staff_jac']['image_path'],
        'image_alt' => $images['staff_jac']['image_alt']
    ],
    'joel' => [
        'id' => 'joel',
        'name' => 'Joel Capa',
        'position' => 'Utilitiy Cleaning Services',
        'image_path' => $images['staff_joel']['image_path'],
        'image_alt' => $images['staff_joel']['image_alt']
    ],
];

// Fetch dynamic staff from the database
$dynamicStaff = [];
$query = "SELECT id, name, position, image_path, image_alt FROM staff ORDER BY id";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $dynamicStaff[] = $row;
    }
}


$allStaff = $staticStaff; 
foreach ($dynamicStaff as $staff) {
    $allStaff[$staff['id']] = $staff; 
}


function addStaffMember($conn, $name, $position) {
    $query = "INSERT INTO staff (name, position) VALUES (?, ?)";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Add staff member statement preparation failed: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("ss", $name, $position);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}


// Update the updateStaffMember function
function updateStaffMember($conn, $staffId, $name, $position) {
    $query = "UPDATE staff SET name=?, position=? WHERE id=?";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }

    $stmt->bind_param("ssi", $name, $position, $staffId);
    $success = $stmt->execute();
    
    if (!$success) {
        error_log("Execute failed: " . $stmt->error);
    }

    $stmt->close();
    return $success;
}

function deleteStaffMember($conn, $staffId) {
    $query = "DELETE FROM staff WHERE id = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Delete staff member statement preparation failed: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("i", $staffId);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// Fetch staff members from database
$staffMembers = [];
$query = "SELECT id, name, position, image_path, image_alt FROM staff ORDER BY id";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $staffMembers[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS - Homepage Content</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/adminhome.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
      <link rel="apple-touch-icon" sizes="180x180" href="images/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="images/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="images/favicon-16x16.png">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <style>
             .dropdown-item.d-flex.align-items-center.active {
  background-color: #8B0000; /* or whatever color */
}
        body, .cms-container, .editor-panel, .preview-panel, .form-control, .btn, .preview-content, .preview-header, .preview-footer {
            font-family: 'Poppins', sans-serif;
        }
        h1, h2, h3, .section-title, .preview-staff h3, .preview-core-values h2, .preview-vision-mission-card h2 {
            font-family: 'Cinzel', serif;
        }
        
        .cms-container {
            display: flex;
            height: calc(100vh - 60px);
        }
        
        
        .editor-panel {
            width: 40%;
            padding: 20px;
            background: #f8f9fa;
            overflow-y: auto;
            border-right: 1px solid #dee2e6;
        }
        
        .preview-panel {
            width: 60%;
            overflow-y: auto;
            position: relative;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-control:focus {
            border-radius: 0.375rem;
        }
        
        .btn-save {
            background-color: #8B0000;
            border-color: #8B0000;
            color: white;
            width: 100%;
            padding: 10px;
            font-weight: 600;
        }
        
        .btn-save:hover {
            background-color: #6B0000;
            border-color: #6B0000;
        }
        
        .btn-preview {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
            width: 100%;
            padding: 10px;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .btn-preview:hover {
            background-color: #218838;
            border-color: #218838;
        }
        
        .section-divider {
            border-top: 2px solid #8B0000;
            margin: 2rem 0 1.5rem 0;
            padding-top: 1.5rem;
        }
        
        .section-title {
            color: #8B0000;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        /* Image upload styles */
        .image-upload-container {
            border: 2px dashed #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            text-align: center;
            background: #f8f9fa;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }

        .image-upload-container:hover {
            border-color: #8B0000;
            background: #fff;
        }

        .image-upload-container.dragover {
            border-color: #8B0000;
            background: #fff3f3;
        }

        .image-preview {
            max-width: 100%;
            max-height: 150px;
            border-radius: 0.375rem;
            margin-bottom: 0.5rem;
        }

        .upload-text {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .upload-progress {
            display: none;
            margin-top: 0.5rem;
        }

        .btn-upload {
            background-color: #17a2b8;
            border-color: #17a2b8;
            color: white;
            font-size: 0.8rem;
            padding: 0.25rem 0.75rem;
        }

        .btn-upload:hover {
            background-color: #138496;
            border-color: #138496;
        }
        
        /* Preview styles */
        .preview-content {
            font-family: Arial, sans-serif;
            background: white;
            margin: 0;
            padding: 0;
            width: 100%;
        }

        /* Main Content */
        .preview-main-content {
            background-color: white;
            padding: 60px 20px;
            text-align: center;
        }

        /* Sections */
        .preview-main-content section {
            width: 100%;
            padding: 60px 10%;
            text-align: center;
            border-bottom: 1px solid #ddd;
        }

        .preview-main-content h2 {
            color: #8B0000;
            font-size: 2.5rem;
            margin-bottom: 20px;
        }

        .preview-main-content p {
            color: #333;
            font-size: 1.2rem;
            max-width: 800px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* About Section */
        .preview-about {
            background-size: cover;
            background-attachment: fixed;
            position: relative;
            color: white;
            padding: 100px 10%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 500px;
        }

        .preview-about::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.55);
            z-index: 1;
        }

        .preview-about > * {
            position: relative;
            z-index: 2;
        }

        .preview-about h2 {
            color: white;
            border-bottom: 2px solid rgba(255, 255, 255, 0.7);
        }

        .preview-about p {
            color: rgba(255, 255, 255, 0.9);
        }

        /* Staff Section */
        .preview-staff {
            background-size: cover;
            background-attachment: fixed;
            position: relative;
            color: white;
            padding: 80px 5%;
            text-align: center;
        }

        .preview-staff::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1;
        }

        .preview-staff > * {
            position: relative;
            z-index: 2;
        }

        .preview-staff h3 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 30px;
            color: #f8f8f8;
            text-transform: uppercase;
            border-bottom: 3px solid #ffffff;
            display: inline-block;
            padding-bottom: 8px;
            letter-spacing: 1px;
            text-shadow: 2px 2px 5px rgba(0, 0, 0, 0.6);
        }

        .preview-staff ul {
            list-style: none;
            padding: 0;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            max-width: 1000px;
            margin: 0 auto;
        }

        .preview-staff ul li {
            font-size: 1.1rem;
            padding: 20px;
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border: none;
            text-align: center;
            backdrop-filter: blur(4px);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-shadow: 1px 1px 4px rgba(0, 0, 0, 0.7);
        }

        .preview-staff ul li img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white; 
            margin-bottom: 12px;
        }

        .staff-member-box {
            background-color: #f8f9fa;
            transition: all 0.3s;
        }
        .staff-member-box:hover {
            background-color: #e9ecef;
        }

        /* Core Values Section */
        .preview-core-values {
            background-size: cover;
            background-attachment: fixed;
            position: relative;
            padding: 50px 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            width: 100%;
        }

        .preview-core-values::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(138, 2, 2, 0.5);
            z-index: 0;
        }

        .preview-core-values > * {
            position: relative;
            z-index: 2;
        }

        .preview-core-values h2 {
            font-size: 2.6rem;
            color: #ffffff;
            border-bottom: 3px solid #ffffff;
            text-shadow: 2px 2px 6px rgba(0, 0, 0, 0.7);
        }

        .preview-core-values ul {
            list-style: none;
            padding: 0;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 15px;
            max-width: 1100px;
            width: 100%;
            margin: 0 auto;
        }

        .preview-core-values ul li {
            font-size: 1.3rem;
            padding: 18px 20px;
            color: white;
            text-align: left;
            font-weight: 500;
            background: rgba(255, 255, 255, 0.15);
            border-left: 6px solid rgba(255, 255, 255, 0.5);
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: 1.2rem;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
            text-shadow: 1px 1px 4px rgba(0, 0, 0, 0.6);
        }

        /* Vision & Mission Section */
        .preview-vision-mission {
            position: relative;
            background-size: cover;
            padding: 80px 5%;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
        }

        .preview-vision-mission::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1;
        }

        .preview-vision-mission-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            max-width: 1100px;
            width: 100%;
        }

        .preview-vision-mission-card {
            background: rgba(255, 255, 255, 0.1);
            padding: 25px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.3);
            text-align: left;
            border-left: 6px solid #FFFFFF;
        }

        .preview-vision-mission-card h2 {
            font-size: 1.8rem;
            font-weight: bold;
            color: white;
            margin-bottom: 16px;
            text-transform: uppercase;
            border-bottom: 3px solid white;
            padding-bottom: 8px;
            text-shadow: 2px 2px 5px rgba(0, 0, 0, 0.7);
        }

        .preview-vision-mission-card p {
            font-size: 1.2rem;
            color: white !important;
            line-height: 1.8;
            text-align: justify;
            margin: 0;
            text-justify: inter-word;
            text-shadow: 1px 1px 4px rgba(0, 0, 0, 0.6);
        }

        /* Mobile Responsiveness */
        @media screen and (max-width: 992px) {
            .preview-main-content {
                margin-left: 0;
                width: 100%;
            }
            
            .preview-about {
                height: auto;
                padding: 60px 10px;
            }
            
            .preview-main-content section {
                padding: 40px 5%;
            }
            
            .preview-main-content h2,
            .preview-about h2,
            .preview-staff h2,
            .preview-core-values h2 {
                font-size: 2rem;
            }
            
            .preview-main-content p,
            .preview-about p,
            .preview-staff p,
            .preview-core-values ul li,
            .preview-vision-mission-card p {
                font-size: 1rem;
            }
            
            .preview-staff ul {
                grid-template-columns: 1fr;
            }
            
            .preview-staff ul li img {
                width: 80px;
                height: 80px;
            }
            
            .preview-core-values ul {
                grid-template-columns: 1fr;
            }
            
            .preview-vision-mission-container {
                grid-template-columns: 1fr;
            }
            
            .preview-vision-mission-card {
                text-align: center;
                padding: 25px;
            }
        }

        @media screen and (max-width: 768px) {
            .preview-main-content h2,
            .preview-about h2,
            .preview-staff h2,
            .preview-core-values h2 {
                font-size: 1.8rem;
            }
            
            .preview-staff h3 {
                font-size: 1.5rem;
            }
        }
        
        @media (max-width: 768px) {
            .cms-container {
                flex-direction: column;
                height: auto;
            }
            
            .editor-panel {
                width: 100%;
                max-height: 50vh;
            }
            
            .preview-panel {
                width: 100%;
                max-height: 50vh;
            }
            
            .preview-vision-mission {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Session Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                showModalMessage('Success', '<?php echo addslashes($_SESSION['success']); ?>', true);
            });
        </script>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                showModalMessage('Error', '<?php echo addslashes($_SESSION['error']); ?>', false);
            });
        </script>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div id="app" class="d-flex">
        <button id="burger-btn" class="burger-btn">☰</button>
        <?php include 'include/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content"  id="dashboard-content" style="margin-top: 0;">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="cms-container">
                <!-- Editor Panel -->
                <div class="editor-panel">
                    <h2 class="mb-4">Edit Homepage Content</h2>
                    
                    <form method="POST" action="">
                        <div class="section-divider">
                            <h4 class="section-title">About Us Section</h4>
                        </div>
                        
                        <div class="form-group">
                            <label for="about_us_title">About Us Title</label>
                            <input type="text" class="form-control" id="about_us_title" name="about_us_title" value="<?php echo htmlspecialchars($content['about_us_title']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="about_us_text">About Us Text</label>
                            <textarea class="form-control" id="about_us_text" name="about_us_text" rows="4" required><?php echo htmlspecialchars($content['about_us_text']); ?></textarea>
                        </div>
                        
                        <!-- Background Image Upload -->
                        <div class="form-group">
                            <label>About Section Background Image</label>
                            <div class="image-upload-container" data-section="background_about">
                                <img src="<?php echo htmlspecialchars($images['background_about']['image_path']); ?>" alt="Current Background" class="image-preview" id="background_about-preview" style="<?php echo empty($images['background_about']['image_path']) ? 'display:none;' : ''; ?>">
                                <div class="upload-text">
                                    <i class="bi bi-cloud-upload fs-2 text-muted"></i>
                                    <p class="mb-0">Click to upload or drag and drop</p>
                                    <small class="text-muted">JPG, PNG, GIF, WebP (Max 5MB)</small>
                                </div>
                                <input type="file" id="background_about-upload" accept="image/*" style="display: none;">
                                <div class="upload-progress">
                                    <div class="progress">
                                        <div class="progress-bar" role="progressbar"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-8">
                                    <input type="text" class="form-control form-control-sm" id="background_about-alt" placeholder="Image description (Alt text)" value="<?php echo htmlspecialchars($images['background_about']['image_alt']); ?>">
                                </div>
                                <div class="col-4">
                                    <button type="button" class="btn btn-upload btn-sm w-100" onclick="uploadImage('background_about')">Upload</button>
                                </div>
                            </div>
                        </div>

                        <div class="section-divider">
                            <h4 class="section-title">Welcome Section</h4>
                        </div>
                        
                        <div class="form-group">
                            <label for="welcome_title">Welcome Title</label>
                            <input type="text" class="form-control" id="welcome_title" name="welcome_title" value="<?php echo htmlspecialchars($content['welcome_title']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="welcome_text">Welcome Text</label>
                            <textarea class="form-control" id="welcome_text" name="welcome_text" rows="4" required><?php echo htmlspecialchars($content['welcome_text']); ?></textarea>
                        </div>
                        
                        <!-- Background Image Upload -->
                        <div class="form-group">
                            <label>Welcome Section Background Image</label>
                            <div class="image-upload-container" data-section="background_welcome">
                                <img src="<?php echo htmlspecialchars($images['background_welcome']['image_path']); ?>" alt="Current Background" class="image-preview" id="background_welcome-preview" style="<?php echo empty($images['background_welcome']['image_path']) ? 'display:none;' : ''; ?>">
                                <div class="upload-text">
                                    <i class="bi bi-cloud-upload fs-2 text-muted"></i>
                                    <p class="mb-0">Click to upload or drag and drop</p>
                                    <small class="text-muted">JPG, PNG, GIF, WebP (Max 5MB)</small>
                                </div>
                                <input type="file" id="background_welcome-upload" accept="image/*" style="display: none;">
                                <div class="upload-progress">
                                    <div class="progress">
                                        <div class="progress-bar" role="progressbar"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-8">
                                    <input type="text" class="form-control form-control-sm" id="background_welcome-alt" placeholder="Image description (Alt text)" value="<?php echo htmlspecialchars($images['background_welcome']['image_alt']); ?>">
                                </div>
                                <div class="col-4">
                                    <button type="button" class="btn btn-upload btn-sm w-100" onclick="uploadImage('background_welcome')">Upload</button>
                                </div>
                            </div>
                        </div>

                        <div class="section-divider">
                            <h4 class="section-title">Staff Section</h4>
                        </div>

                        <div class="form-group">
                            <label>Staff Section Background Image</label>
                            <div class="image-upload-container" data-section="background_staff">
                                <img src="<?php echo htmlspecialchars($images['background_staff']['image_path']); ?>" alt="Current Background" class="image-preview" id="background_staff-preview" style="<?php echo empty($images['background_staff']['image_path']) ? 'display:none;' : ''; ?>">
                                <div class="upload-text">
                                    <i class="bi bi-cloud-upload fs-2 text-muted"></i>
                                    <p class="mb-0">Click to upload or drag and drop</p>
                                    <small class="text-muted">JPG, PNG, GIF, WebP (Max 5MB)</small>
                                </div>
                                <input type="file" id="background_staff-upload" accept="image/*" style="display: none;">
                                <div class="upload-progress">
                                    <div class="progress">
                                        <div class="progress-bar" role="progressbar"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-8">
                                    <input type="text" class="form-control form-control-sm" id="background_staff-alt" placeholder="Image description (Alt text)" value="<?php echo htmlspecialchars($images['background_staff']['image_alt']); ?>">
                                </div>
                                <div class="col-4">
                                    <button type="button" class="btn btn-upload btn-sm w-100" onclick="uploadImage('background_staff')">Upload</button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="staff_title">Staff Title</label>
                            <input type="text" class="form-control" id="staff_title" name="staff_title" value="<?php echo htmlspecialchars($content['staff_title']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="staff_intro_text">Staff Introduction Text</label>
                            <textarea class="form-control" id="staff_intro_text" name="staff_intro_text" rows="3" required><?php echo htmlspecialchars($content['staff_intro_text']); ?></textarea>
                        </div>
                        
                        <!-- Staff Member  -->
                        <?php foreach ($allStaff as $staff): ?>
                        <div class="staff-member-box mb-4 p-3 border rounded bg-light" id="<?= $staff['id'] ?>-container">
                            <!-- Delete button (only for dynamic staff) -->
                            <?php if (!isset($staticStaff[$staff['id']])): ?>
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-danger btn-sm" onclick="removeStaffMember(<?= $staff['id'] ?>)">
                                    <i class="bi bi-trash"></i> Remove
                                </button>
                            </div>
                            <?php endif; ?>

                            <!-- Name & Position Fields -->
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" class="form-control staff-name" 
                                    name="staff_<?= $staff['id'] ?>_name" 
                                    value="<?= htmlspecialchars($staff['name']) ?>">
                            </div>
                            <div class="form-group">
                                <label>Position</label>
                                <input type="text" class="form-control staff-position" 
                                    name="staff_<?= $staff['id'] ?>_position" 
                                    value="<?= htmlspecialchars($staff['position']) ?>">
                            </div>

                            <!-- Image Upload -->
                            <div class="form-group">
                                <label>Profile Image</label>
                                <div class="image-upload-container" data-section="staff_<?= $staff['id'] ?>">
                                    <img src="<?= $staff['image_path'] ?: 'images/default-profile.jpg' ?>" 
                                        class="image-preview" 
                                        id="staff_<?= $staff['id'] ?>-preview">
                                    <div class="upload-text">
                                        <i class="bi bi-cloud-upload fs-2 text-muted"></i>
                                        <p class="mb-0">Click to upload or drag and drop</p>
                                    </div>
                                    <input type="file" id="staff_<?= $staff['id'] ?>-upload" accept="image/*" style="display: none;">
                                </div>
                                <div class="row mt-2">
                                    <div class="col-8">
                                        <input type="text" class="form-control form-control-sm staff-alt" 
                                            id="staff_<?= $staff['id'] ?>-alt" 
                                            placeholder="Image description" 
                                            value="<?= htmlspecialchars($staff['image_alt'] ?? '') ?>">
                                    </div>
                                    <div class="col-4">
                                        <button type="button" class="btn btn-upload btn-sm w-100" 
                                                onclick="uploadStaffImage('<?= $staff['id'] ?>')">
                                            Upload
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <!-- Add New Staff Button -->
                        <div class="add-staff-container">
                            <div class="input-group mb-3">
                                <input type="text" id="new-staff-name" class="form-control" placeholder="Full Name">
                                <input type="text" id="new-staff-position" class="form-control" placeholder="Position">
                            </div>
                            <div class="image-upload-container mb-3" data-section="new-staff">
                                <img src="" class="image-preview" id="new-staff-preview" style="display: none;">
                                <div class="upload-text">
                                    <i class="bi bi-cloud-upload fs-2 text-muted"></i>
                                    <p class="mb-0">Click to upload staff photo</p>
                                    <small class="text-muted">JPG, PNG, GIF, WebP (Max 5MB)</small>
                                </div>
                                <input type="file" id="new-staff-upload" accept="image/*" style="display: none;">
                                <div class="upload-progress" id="new-staff-upload-progress" style="display: none;">
                                    <div class="progress">
                                        <div class="progress-bar" role="progressbar"></div>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-success" id="confirm-add-staff">
                                <i class="bi bi-plus-circle"></i> Confirm Add Staff Member
                            </button>
                        </div>
                        
                        <div class="form-group">
                            <label for="core_values_title">Core Values Title</label>
                            <input type="text" class="form-control" id="core_values_title" name="core_values_title" value="<?php echo htmlspecialchars($content['core_values_title']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="core_value_1_title">Core Value 1 Title</label>
                            <input type="text" class="form-control" id="core_value_1_title" name="core_value_1_title" value="<?php echo htmlspecialchars($content['core_value_1_title']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="core_value_1_desc">Core Value 1 Description</label>
                            <textarea class="form-control" id="core_value_1_desc" name="core_value_1_desc" rows="2" required><?php echo htmlspecialchars($content['core_value_1_desc']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="core_value_2_title">Core Value 2 Title</label>
                            <input type="text" class="form-control" id="core_value_2_title" name="core_value_2_title" value="<?php echo htmlspecialchars($content['core_value_2_title']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="core_value_2_desc">Core Value 2 Description</label>
                            <textarea class="form-control" id="core_value_2_desc" name="core_value_2_desc" rows="2" required><?php echo htmlspecialchars($content['core_value_2_desc']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="core_value_3_title">Core Value 3 Title</label>
                            <input type="text" class="form-control" id="core_value_3_title" name="core_value_3_title" value="<?php echo htmlspecialchars($content['core_value_3_title']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="core_value_3_desc">Core Value 3 Description</label>
                            <textarea class="form-control" id="core_value_3_desc" name="core_value_3_desc" rows="2" required><?php echo htmlspecialchars($content['core_value_3_desc']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="core_value_4_title">Core Value 4 Title</label>
                            <input type="text" class="form-control" id="core_value_4_title" name="core_value_4_title" value="<?php echo htmlspecialchars($content['core_value_4_title']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="core_value_4_desc">Core Value 4 Description</label>
                            <textarea class="form-control" id="core_value_4_desc" name="core_value_4_desc" rows="2" required><?php echo htmlspecialchars($content['core_value_4_desc']); ?></textarea>
                        </div>

                        <div class="section-divider">
                            <h4 class="section-title">Vision & Mission Section</h4>
                        </div>

                        <div class="form-group">
                            <label>Vision & Mission Background Image</label>
                            <div class="image-upload-container" data-section="background_vision">
                                <img src="<?php echo htmlspecialchars($images['background_vision']['image_path']); ?>" alt="Current Background" class="image-preview" id="background_vision-preview" style="<?php echo empty($images['background_vision']['image_path']) ? 'display:none;' : ''; ?>">
                                <div class="upload-text">
                                    <i class="bi bi-cloud-upload fs-2 text-muted"></i>
                                    <p class="mb-0">Click to upload or drag and drop</p>
                                    <small class="text-muted">JPG, PNG, GIF, WebP (Max 5MB)</small>
                                </div>
                                <input type="file" id="background_vision-upload" accept="image/*" style="display: none;">
                                <div class="upload-progress">
                                    <div class="progress">
                                        <div class="progress-bar" role="progressbar"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-8">
                                    <input type="text" class="form-control form-control-sm" id="background_vision-alt" placeholder="Image description (Alt text)" value="<?php echo htmlspecialchars($images['background_vision']['image_alt']); ?>">
                                </div>
                                <div class="col-4">
                                    <button type="button" class="btn btn-upload btn-sm w-100" onclick="uploadImage('background_vision')">Upload</button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="vision_title">Vision Title</label>
                            <input type="text" class="form-control" id="vision_title" name="vision_title" value="<?php echo htmlspecialchars($content['vision_title']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="vision_text">Vision Text</label>
                            <textarea class="form-control" id="vision_text" name="vision_text" rows="4" required><?php echo htmlspecialchars($content['vision_text']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="mission_title">Mission Title</label>
                            <input type="text" class="form-control" id="mission_title" name="mission_title" value="<?php echo htmlspecialchars($content['mission_title']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="mission_text">Mission Text</label>
                            <textarea class="form-control" id="mission_text" name="mission_text" rows="4" required><?php echo htmlspecialchars($content['mission_text']); ?></textarea>
                        </div>
                        
                        <button type="submit" name="update_content" class="btn btn-save">
                            <i class="bi bi-save"></i> Save Changes
                        </button>
                    </form>
                </div>
                
                <!-- Preview Panel -->
                <div class="preview-panel">
                    <div class="preview-content" id="preview-content">
                        <!-- About Section -->
                        <section class="preview-about" style="background-image: url('<?php echo htmlspecialchars($images['background_about']['image_path']); ?>?t=<?= time() ?>')">
                            <h2 id="preview-about-us-title"><?php echo htmlspecialchars($content['about_us_title']); ?></h2>
                            <p id="preview-about-us-text"><?php echo htmlspecialchars($content['about_us_text']); ?></p>
                        </section>

                        <!-- Welcome Section -->
                        <section class="preview-main-content">
                            <h2 id="preview-welcome-title"><?php echo htmlspecialchars($content['welcome_title']); ?></h2>
                            <p id="preview-welcome-text"><?php echo htmlspecialchars($content['welcome_text']); ?></p>
                        </section>

                        <!-- Staff Section -->
                        <section class="preview-staff" style="background-image: url('<?= htmlspecialchars($images['background_staff']['image_path']) ?>?t=<?= time() ?>')">
                            <h3 id="preview-staff-title"><?= htmlspecialchars($content['staff_title']) ?></h3>
                            <p id="preview-staff-intro-text"><?= htmlspecialchars($content['staff_intro_text']) ?></p>
                            <ul>
                                <?php foreach ($allStaff as $staff): ?>
                                <li id="preview-<?= $staff['id'] ?>">
                                    <img src="<?= $staff['image_path'] ?: 'images/default-profile.jpg' ?>?t=<?= time() ?>" 
                                        alt="<?= htmlspecialchars($staff['image_alt'] ?? '') ?>" 
                                        id="preview-<?= $staff['id'] ?>-img">
                                    <strong id="preview-<?= $staff['id'] ?>-name"><?= htmlspecialchars($staff['name']) ?></strong> - 
                                    <span id="preview-<?= $staff['id'] ?>-position"><?= htmlspecialchars($staff['position']) ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </section>
                        
                        <!-- Core Values Section -->
                        <section class="preview-core-values" style="background-image: url('<?php echo htmlspecialchars($images['background_values']['image_path']); ?>?t=<?= time() ?>')">
                            <h2 id="preview-core-values-title"><?php echo htmlspecialchars($content['core_values_title']); ?></h2>
                            <ul>
                                <li><strong id="preview-core-value-1-title"><?php echo htmlspecialchars($content['core_value_1_title']); ?></strong> - <span id="preview-core-value-1-desc"><?php echo htmlspecialchars($content['core_value_1_desc']); ?></span></li>
                                <li><strong id="preview-core-value-2-title"><?php echo htmlspecialchars($content['core_value_2_title']); ?></strong> - <span id="preview-core-value-2-desc"><?php echo htmlspecialchars($content['core_value_2_desc']); ?></span></li>
                                <li><strong id="preview-core-value-3-title"><?php echo htmlspecialchars($content['core_value_3_title']); ?></strong> - <span id="preview-core-value-3-desc"><?php echo htmlspecialchars($content['core_value_3_desc']); ?></span></li>
                                <li><strong id="preview-core-value-4-title"><?php echo htmlspecialchars($content['core_value_4_title']); ?></strong> - <span id="preview-core-value-4-desc"><?php echo htmlspecialchars($content['core_value_4_desc']); ?></span></li>
                            </ul>
                        </section>

                        <!-- Vision & Mission Section -->
                        <section class="preview-vision-mission" style="background-image: url('<?php echo htmlspecialchars($images['background_vision']['image_path']); ?>?t=<?= time() ?>')">
                            <div class="preview-vision-mission-container">
                                <div class="preview-vision-mission-card">
                                    <h2 id="preview-vision-title"><?php echo htmlspecialchars($content['vision_title']); ?></h2>
                                    <p id="preview-vision-text"><?php echo htmlspecialchars($content['vision_text']); ?></p>
                                </div>

                                <div class="preview-vision-mission-card">
                                    <h2 id="preview-mission-title"><?php echo htmlspecialchars($content['mission_title']); ?></h2>
                                    <p id="preview-mission-text"><?php echo htmlspecialchars($content['mission_text']); ?></p>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </div>

      <?php include('notifications_admin.php')?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/adminSidebar.js"></script>
        <script>
        function closeSidebarOnMobile() {
            if (window.innerWidth <= 768) {
                document.getElementById('sidebar').classList.remove('active');
            }
        }
        // Show Dashboard
        function showDashboard() {
            document.getElementById('dashboard-content').style.display = 'block';
            closeSidebarOnMobile();
        }


        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            const burgerBtn = document.getElementById('burger-btn');
            const sidebar = document.getElementById('sidebar');
            const dashboardType = document.getElementById('dashboard-type');
            const medicalDashboard = document.getElementById('medical-dashboard');
            const dentalDashboard = document.getElementById('dental-dashboard');
            const medicalTimeFilter = document.getElementById('medical-time-filter');
            const dentalTimeFilter = document.getElementById('dental-time-filter');
            const medicalConsultationFilter = document.getElementById('medical-consultation-filter');
            const dentalConsultationFilter = document.getElementById('dental-consultation-filter');
            const userCategoryFilter = document.getElementById('user-category-filter');
            const printReportsBtn = document.getElementById('print-reports-btn');

            // Initialize dashboard and charts
            showDashboard();


            // Burger button toggle
            if (burgerBtn) {
                burgerBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    sidebar.classList.toggle('active');
                });
            }

            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
                    const isClickInsideSidebar = sidebar.contains(event.target);
                    const isClickOnBurgerBtn = burgerBtn.contains(event.target);
                    if (!isClickInsideSidebar && !isClickOnBurgerBtn) {
                        sidebar.classList.remove('active');
                    }
                }
            });

            // Close sidebar when clicking sidebar buttons on mobile
            const sidebarButtons = document.querySelectorAll('#sidebar .btn-crimson:not(#cmsDropdown), #sidebar .dropdown-item');
            sidebarButtons.forEach(button => {
                button.addEventListener('click', closeSidebarOnMobile);
            });



        });
    </script>
    <script>
        "use strict";
        function showModalMessage(title, message, isSuccess = true) {
            var messageModal = new bootstrap.Modal(document.getElementById('messageModal'));
            const modalEl = document.getElementById('messageModal');
            if (!modalEl) return; // Exit if modal doesn't exist
            
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modalEl.removeAttribute('aria-hidden');
            
            const modalLabel = document.getElementById('messageModalLabel');
            const modalBody = document.getElementById('messageModalBody');
            
            if (modalLabel) modalLabel.textContent = title;
            if (modalBody) modalBody.textContent = message;
            
            const header = modalEl.querySelector('.modal-header');
            if (header) header.style.backgroundColor = isSuccess ? '#28a745' : '#dc3545';
            
            modal.show();
            
            modalEl.addEventListener('shown.bs.modal', function() {
                const btn = modalEl.querySelector('.btn-primary');
                if (btn) btn.focus();
            });
            
            modalEl.addEventListener('hidden.bs.modal', function() {
                if (document.activeElement) document.activeElement.blur();
            });
        }

        function uploadImage(section) {
            const fileInput = document.getElementById(section + '-upload');
            const altInput = document.getElementById(section + '-alt');
            const preview = document.getElementById(section + '-preview');
            const container = document.querySelector(`[data-section="${section}"]`);
            
            if (!fileInput.files || !fileInput.files[0]) {
                showModalMessage('Error', 'Please select a file first', false);
                return;
            }
            
            const formData = new FormData();
            formData.append('image', fileInput.files[0]);
            formData.append('section', section);
            formData.append('image_alt', altInput.value);
            formData.append('ajax_upload', '1');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    preview.src = data.path + '?t=' + new Date().getTime();
                    preview.style.display = 'block';
                    
                    // Update preview images
                    if (section.startsWith('staff_')) {
                        document.getElementById('preview-' + section).src = data.path + '?t=' + new Date().getTime();
                    } else if (section.startsWith('background_')) {
                        const previewSection = document.querySelector('.preview-' + section.replace('_', '-') + '-section') || 
                                             document.querySelector('.preview-' + section.replace('background_', ''));
                        if (previewSection) {
                            previewSection.style.backgroundImage = `url('${data.path}?t=${new Date().getTime()}')`;
                        }
                    }
                    
                    showModalMessage('Success', data.message, true);
                } else {
                    showModalMessage('Error', data.message, false);
                }
            })
            .catch(error => {
                showModalMessage('Error', 'Upload failed: ' + error.message, false);
            });
        }

        

            const addStaffBtn = document.getElementById('confirm-add-staff');
                if (!addStaffBtn) {
                    console.error("Error: #confirm-add-staff button not found!");
                } else {
                    addStaffBtn.addEventListener('click', handleAddStaff);
                }

                function handleAddStaff() {
                    const name = document.getElementById('new-staff-name').value;
                    const position = document.getElementById('new-staff-position').value;
                    const imageFile = document.getElementById('new-staff-upload').files[0];
                    const progress = document.getElementById('new-staff-upload-progress');
                    const preview = document.getElementById('new-staff-preview');

                    if (!name || !position) {
                        showModalMessage('Error', 'Please enter name and position', false);
                        return;
                    }

                    const formData = new FormData();
                    formData.append('ajax_staff', '1');
                    formData.append('staff_action', 'add');
                    formData.append('name', name);
                    formData.append('position', position);
                    if (imageFile) {
                        formData.append('image', imageFile);
                    }

                    if (progress) progress.style.display = 'block';
                    if (preview) preview.style.display = 'none';

                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            document.getElementById('new-staff-name').value = '';
                            document.getElementById('new-staff-position').value = '';
                            document.getElementById('new-staff-upload').value = '';
                            if (preview) preview.style.display = 'none';
                            addStaffMemberToUI(data.staff_id, name, position, data.image_path || '');
                            showModalMessage('Success', data.message, true);
                        } else {
                            showModalMessage('Error', data.message || 'Failed to add staff', false);
                        }
                    })
                    .catch(error => {
                        showModalMessage('Error', 'Network error: ' + error.message, false);
                    })
                    .finally(() => {
                        if (progress) progress.style.display = 'none';
                    });
                }

            addStaffBtn.addEventListener('click', function() {
                const name = document.getElementById('new-staff-name').value;
                const position = document.getElementById('new-staff-position').value;
                
                if (!name || !position) {
                    showModalMessage('Error', 'Please enter name and position', false);
                    return;
                }
            });

        // Function to remove a staff member
        function removeStaffMember(staffId) {
            if (!confirm('Are you sure you want to remove this staff member?')) return;
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax_staff=1&staff_action=delete&staff_id=${staffId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove from editor
                    const container = document.getElementById(staffId + '-container');
                    if (container) container.remove();
                    
                    // Remove from preview
                    const previewItem = document.getElementById('preview-' + staffId);
                    if (previewItem) previewItem.remove();
                } else {
                    showModalMessage('Error', data.message, false);
                }
            })
            .catch(error => {
                showModalMessage('Error', 'Failed to remove staff member', false);
            });
        }

        function setupStaffEventListeners(staffId) {
        const container = document.querySelector(`[data-section="staff_${staffId}"]`);
        const fileInput = document.getElementById(`staff_${staffId}-upload`);
        const preview = document.getElementById(`staff_${staffId}-preview`);

        if (!container || !fileInput || !preview) {
            console.error(`Could not find elements for staff_${staffId}`);
            return;
        }

        container.addEventListener('click', () => fileInput.click());
        container.addEventListener('dragover', (e) => {
            e.preventDefault();
            container.classList.add('dragover');
        });
        container.addEventListener('dragleave', () => {
            container.classList.remove('dragover');
        });
        container.addEventListener('drop', (e) => {
            e.preventDefault();
            container.classList.remove('dragover');
            if (e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                previewImage(fileInput, `staff_${staffId}`);
            }
        });
        fileInput.addEventListener('change', (e) => {
            if (e.target.files[0]) {
                previewImage(fileInput, `staff_${staffId}`);
            }
        });
    }

        // Function to add staff member to UI
        function addStaffMemberToUI(staffId, name, position, imagePath = '') {
            const defaultImage = 'images/default-profile.jpg';
            const previewContainer = document.querySelector('.preview-staff ul');
            if (!staffId || staffId <= 0) {
                showModalMessage('Error', 'Invalid staff ID received', false);
                return;
            }

            const newStaffHtml = `
            <div class="staff-member-box mb-4 p-3 border rounded bg-light" id="${staffId}-container">
                <div class="d-flex justify-content-end">
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeStaffMember(${staffId})">
                        <i class="bi bi-trash"></i> Remove
                    </button>
                </div>
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" class="form-control staff-name" 
                        name="staff_${staffId}_name" 
                        value="${name}"
                        onchange="updateStaffMemberJS(${staffId})">
                </div>
                <div class="form-group">
                    <label>Position</label>
                    <input type="text" class="form-control staff-position" 
                        name="staff_${staffId}_position" 
                        value="${position}"
                        onchange="updateStaffMemberJS(${staffId})">
                </div>
                <div class="form-group">
                    <label>Profile Image</label>
                    <div class="image-upload-container" data-section="staff_${staffId}">
                        <img src="${imagePath || defaultImage}?t=${new Date().getTime()}" 
                            class="image-preview" 
                            id="staff_${staffId}-preview">
                        <div class="upload-text">
                            <i class="bi bi-cloud-upload fs-2 text-muted"></i>
                            <p class="mb-0">Click to upload or drag and drop</p>
                        </div>
                        <input type="file" id="staff_${staffId}-upload" accept="image/*" style="display: none;">
                        <div class="upload-progress" id="staff_${staffId}-upload-progress" style="display: none;">
                            <div class="progress">
                                <div class="progress-bar" role="progressbar"></div>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-8">
                            <input type="text" class="form-control form-control-sm staff-alt" 
                                id="staff_${staffId}-alt" 
                                placeholder="Image description" 
                                value="Staff member photo">
                        </div>
                        <div class="col-4">
                            <button type="button" class="btn btn-upload btn-sm w-100" 
                                    onclick="uploadStaffImage(${staffId})">
                                Upload
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            `;

            const addButton = document.querySelector('.add-staff-container');
            if (addButton) addButton.insertAdjacentHTML('beforebegin', newStaffHtml);

            if (previewContainer) {
                const newStaffPreview = document.createElement('li');
                newStaffPreview.id = `preview-${staffId}`;
                newStaffPreview.innerHTML = `
                    <img src="${imagePath || defaultImage}?t=${new Date().getTime()}" 
                        alt="${name}" 
                        id="preview-${staffId}-img">
                    <strong id="preview-${staffId}-name">${name}</strong> - 
                    <span id="preview-${staffId}-position">${position}</span>
                `;
                previewContainer.appendChild(newStaffPreview);
            }

            // Ensure event listeners are set up for the new staff member
            setupStaffEventListeners(staffId);
        }
        

        // Function to update staff member via AJAX
        function updateStaffMemberJS(staffId) {
            const container = document.getElementById(`${staffId}-container`);
            const name = container.querySelector('.staff-name').value;
            const position = container.querySelector('.staff-position').value;
            if (!name || !position) {
                showModalMessage('Error', 'Name and position cannot be empty', false);
                return;
            }
            const staticStaffIds = ['felicitas', 'hamja', 'krishnon', 'hilda', 'harold', 'gemma', 'jac', 'joel'];
            if (staticStaffIds.includes(staffId)) {
                const previewName = document.getElementById(`preview-${staffId}-name`);
                const previewPosition = document.getElementById(`preview-${staffId}-position`);
                if (previewName) previewName.textContent = name;
                if (previewPosition) previewPosition.textContent = position;
                showModalMessage('Success', 'Static staff updated in preview', true);
                return;
            }
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax_staff=1&staff_action=update&staff_id=${encodeURIComponent(staffId)}&name=${encodeURIComponent(name)}&position=${encodeURIComponent(position)}`
            })
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const previewName = document.getElementById(`preview-${staffId}-name`);
                    const previewPosition = document.getElementById(`preview-${staffId}-position`);
                    if (previewName) previewName.textContent = name;
                    if (previewPosition) previewPosition.textContent = position;
                    showModalMessage('Success', data.message, true);
                } else {
                    showModalMessage('Error', data.message, false);
                }
            })
            .catch(error => {
                showModalMessage('Error', 'Failed to update staff member: ' + error.message, false);
            });
        }

        // Function to upload staff image
        function uploadStaffImage(staffId) {
            const fileInput = document.getElementById(`staff_${staffId}-upload`);
            const preview = document.getElementById(`staff_${staffId}-preview`);
            const progress = document.getElementById(`staff_${staffId}-upload-progress`);

            if (!fileInput.files[0]) {
                showModalMessage('Error', 'Please select an image first!', false);
                return;
            }

            const formData = new FormData();
            formData.append('image', fileInput.files[0]);
            formData.append('staff_id', staffId);
            formData.append('ajax_staff_image', '1');

            if (progress) progress.style.display = 'block';
            if (preview) preview.style.display = 'none';

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const newSrc = `${data.path}?t=${Date.now()}`;
                    if (preview) preview.src = newSrc;
                    if (preview) preview.style.display = 'block';
                    const previewImg = document.getElementById(`preview-${staffId}-img`);
                    if (previewImg) previewImg.src = newSrc;
                    showModalMessage('Success', data.message, true);
                } else {
                    showModalMessage('Error', data.message, false);
                }
            })
            .catch(error => {
                showModalMessage('Error', 'Network error: ' + error.message, false);
            })
            .finally(() => {
                if (progress) progress.style.display = 'none';
            });
        }

    document.addEventListener('DOMContentLoaded', function() {
        // Drag and drop functionality
        document.querySelectorAll('.image-upload-container').forEach(container => {
            const section = container.dataset.section;
            const fileInput = document.getElementById(`${section}-upload`);
            const preview = document.getElementById(`${section}-preview`);

            if (!container || !fileInput) {
                console.error(`Missing elements for section: ${section}`);
                return;
            }

            container.addEventListener('click', () => fileInput.click());
            container.addEventListener('dragover', (e) => {
                e.preventDefault();
                container.classList.add('dragover');
            });
            container.addEventListener('dragleave', () => {
                container.classList.remove('dragover');
            });
            container.addEventListener('drop', (e) => {
                e.preventDefault();
                container.classList.remove('dragover');
                if (e.dataTransfer.files.length > 0) {
                    fileInput.files = e.dataTransfer.files;
                    previewImage(fileInput, section);
                }
            });
            fileInput.addEventListener('change', (e) => {
                if (e.target.files[0]) {
                    previewImage(fileInput, section);
                }
            });
        });

        // Attach change event listeners to staff name and position inputs
        document.querySelectorAll('.staff-name, .staff-position').forEach(input => {
            input.addEventListener('change', function() {
                const container = input.closest('.staff-member-box');
                if (!container) return;
                const staffId = container.id.replace('-container', '');
                updateStaffMemberJS(staffId);
            });
        });

        // Live preview update
        function updatePreview() {
            const textElements = {
                'about_us_title': 'preview-about-us-title',
                'about_us_text': 'preview-about-us-text',
                'welcome_title': 'preview-welcome-title',
                'welcome_text': 'preview-welcome-text',
                'staff_title': 'preview-staff-title',
                'staff_intro_text': 'preview-staff-intro-text',
                'core_values_title': 'preview-core-values-title',
                'core_value_1_title': 'preview-core-value-1-title',
                'core_value_1_desc': 'preview-core-value-1-desc',
                'core_value_2_title': 'preview-core-value-2-title',
                'core_value_2_desc': 'preview-core-value-2-desc',
                'core_value_3_title': 'preview-core-value-3-title',
                'core_value_3_desc': 'preview-core-value-3-desc',
                'core_value_4_title': 'preview-core-value-4-title',
                'core_value_4_desc': 'preview-core-value-4-desc',
                'vision_title': 'preview-vision-title',
                'vision_text': 'preview-vision-text',
                'mission_title': 'preview-mission-title',
                'mission_text': 'preview-mission-text'
            };

            Object.entries(textElements).forEach(([inputId, previewId]) => {
                const input = document.getElementById(inputId);
                const preview = document.getElementById(previewId);
                if (input && preview) {
                    preview.textContent = input.value;
                }
            });
        }

        if (document.getElementById('about_us_title')) {
            updatePreview();
            document.querySelectorAll('input, textarea').forEach(input => {
                if (input) input.addEventListener('input', updatePreview);
            });
        }
    });


    function previewImage(fileInput, section) {
        const preview = document.getElementById(section + '-preview');
        if (preview && fileInput.files[0]) {
            const reader = new FileReader();
            reader.onload = (e) => {
                preview.src = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(fileInput.files[0]);
        }
    }
    </script>

    <div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-modal="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="messageModalLabel">Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="messageModalBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>