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
    error_log("Unauthorized access to cms_index.php: No user_id in session, redirecting to /login");
    header("Location: /login.php");
    exit();
}

// Check connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    $_SESSION['error'] = "Database error. Please try again.";
    header("Location: /login.php");
    exit();
}

// Verify user is an admin
$userId = (int)$_SESSION['user_id'];
$query = "SELECT user_type FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    error_log("Admin verification query prepare failed: " . $conn->error);
    $_SESSION['error'] = "Database error. Please try again.";
    header("Location: /login.php");
    exit();
}
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || !in_array(trim($user['user_type']), ['Super Admin', 'Medical Admin', 'Dental Admin'], true)) {
    error_log("Non-admin user_id: $userId, user_type: " . ($user['user_type'] ?? 'none') . " attempted access to cms_index.php, redirecting to /homepage");
    header("Location: /homepage.php");
    exit();
}

// Function to get content from database
function getContent($conn, $pageName, $sectionName, $default = '')
{
    $stmt = $conn->prepare("SELECT content_text FROM content WHERE page_name = ? AND section_name = ?");
    $stmt->bind_param("ss", $pageName, $sectionName);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['content_text'];
    }
    return $default;
}

// Function to get image from database
function getImage($conn, $pageName, $sectionName, $default = '')
{
    $stmt = $conn->prepare("SELECT image_path, image_alt FROM images WHERE page_name = ? AND section_name = ?");
    $stmt->bind_param("ss", $pageName, $sectionName);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Convert absolute paths to relative paths
        if (strpos($row['image_path'], '/uploads/') === 0) {
            $row['image_path'] = 'uploads' . substr($row['image_path'], 8);
            return $row;
        }

        // If path is stored as absolute filesystem path
        $docRoot = $_SERVER['DOCUMENT_ROOT'];
        if (strpos($row['image_path'], $docRoot) === 0) {
            $row['image_path'] = 'uploads' . str_replace($docRoot, '', $row['image_path']);
            return $row;
        }

        // If path is already relative, use as is
        if (strpos($row['image_path'], 'uploads/') === 0) {
            return $row;
        }

        error_log("Image path format unexpected: " . $row['image_path']);
    }

    return ['image_path' => $default, 'image_alt' => ''];
}

// Function to update content in database
function updateContent($conn, $pageName, $sectionName, $content)
{
    $stmt = $conn->prepare("INSERT INTO content (page_name, section_name, content_text) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE content_text = ?");
    $stmt->bind_param("ssss", $pageName, $sectionName, $content, $content);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// Function to handle file upload
function handleImageUpload($file, $uploadDir, $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
{
    // Add debug logging
    error_log("Starting file upload processing for: " . print_r($file, true));

    if (!isset($file['tmp_name'])) {
        error_log("No temporary file found in upload");
        return ['success' => false, 'message' => 'No file uploaded'];
    }

    // Check if upload directory exists and is writable
    $absoluteUploadDir = $_SERVER['DOCUMENT_ROOT'] . $uploadDir;
    if (!is_dir($absoluteUploadDir)) {
        error_log("Creating directory: " . $absoluteUploadDir);
        if (!mkdir($absoluteUploadDir, 0755, true)) {
            error_log("Failed to create directory: " . $absoluteUploadDir);
            return ['success' => false, 'message' => 'Failed to create upload directory'];
        }
    }

    if (!is_writable($absoluteUploadDir)) {
        error_log("Upload directory not writable: " . $absoluteUploadDir);
        return ['success' => false, 'message' => 'Upload directory not writable'];
    }

    // Validate file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.'];
    }

    // Validate file size (5MB max)
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => 'File too large. Maximum size is 5MB.'];
    }

    // Generate safe filename
    $filename = preg_replace('/[^a-zA-Z0-9\.\-_]/', '', $file['name']);
    $filename = uniqid() . '_' . $filename;
    $relativePath = $uploadDir . $filename;
    $absolutePath = $_SERVER['DOCUMENT_ROOT'] . $relativePath;

    // Move the file
    if (move_uploaded_file($file['tmp_name'], $absolutePath)) {
        error_log("File successfully moved to: " . $absolutePath);
        return [
            'success' => true,
            'path' => $relativePath,
            'filename' => $filename,
            'mime_type' => $mimeType,
            'size' => $file['size']
        ];
    } else {
        $error = error_get_last();
        error_log("Failed to move uploaded file. Error: " . print_r($error, true));
        return ['success' => false, 'message' => 'Failed to save file'];
    }
}


// Function to update image in database
function updateImage($conn, $pageName, $sectionName, $imagePath, $imageAlt, $originalFilename, $mimeType, $fileSize)
{
    // Check if the record exists
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
        // Update existing record
        $query = "UPDATE images SET 
            image_path = ?, 
            image_alt = ?, 
            original_filename = ?, 
            mime_type = ?, 
            file_size = ?, 
            updated_date = CURRENT_TIMESTAMP 
            WHERE page_name = ? AND section_name = ?";
    } else {
        // Insert new record
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

    if (!$result) {
        error_log("Image update failed: " . $stmt->error);
    } else {
        error_log("Image successfully updated in database for section: $sectionName, path: $imagePath");
    }

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

        // Verify the upload directory
        $uploadDir = 'uploads/images/';
        if ($section === 'logo') {
            $uploadDir .= 'logos/';
        } elseif ($section === 'hero_background') { // Fix typo if exists
            $uploadDir .= 'backgrounds/';
        } else {
            $uploadDir .= 'general/';
        }

        $uploadResult = handleImageUpload($_FILES['image'], $uploadDir);

        if (!$uploadResult['success']) {
            throw new Exception($uploadResult['message']);
        }

        // Update database
        $updateResult = updateImage(
            $conn,
            'index',
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_content'])) {
    $updates = [
        'hero_title' => $_POST['hero_title'],
        'hero_subtitle' => $_POST['hero_subtitle'],
        'services_main_title' => $_POST['services_main_title'],
        'service_1_title' => $_POST['service_1_title'],
        'service_1_desc' => $_POST['service_1_desc'],
        'service_2_title' => $_POST['service_2_title'],
        'service_2_desc' => $_POST['service_2_desc'],
        'service_3_title' => $_POST['service_3_title'],
        'service_3_desc' => $_POST['service_3_desc'],
        'service_4_title' => $_POST['service_4_title'],
        'service_4_desc' => $_POST['service_4_desc'],
        'service_5_title' => $_POST['service_5_title'],
        'service_5_desc' => $_POST['service_5_desc'],
        'service_6_title' => $_POST['service_6_title'],
        'service_6_desc' => $_POST['service_6_desc'],
        'operating_hours_mon_fri' => $_POST['operating_hours_mon_fri'],
        'operating_hours_saturday' => $_POST['operating_hours_saturday'],
        'operating_hours_sunday' => $_POST['operating_hours_sunday'],
        'contact_telephone' => $_POST['contact_telephone'],
        'contact_email' => $_POST['contact_email'],
        'contact_location' => $_POST['contact_location'],
        'footer_text' => $_POST['footer_text']
    ];

    $success = true;
    foreach ($updates as $section => $content) {
        if (!updateContent($conn, 'index', $section, $content)) {
            $success = false;
            break;
        }
    }

    if ($success) {
        $_SESSION['success'] = "Content updated successfully!";
    } else {
        $_SESSION['error'] = "Error updating content. Please try again.";
    }

    header("Location: cms_index.php");
    exit();
}

// Fetch current content
$content = [
    'hero_title' => getContent($conn, 'index', 'hero_title', 'Your Health, My Priority'),
    'hero_subtitle' => getContent($conn, 'index', 'hero_subtitle', 'Comprehensive Healthcare for the WMSU Community'),
    'services_main_title' => getContent($conn, 'index', 'services_main_title', 'OUR SERVICES'),
    'service_1_title' => getContent($conn, 'index', 'service_1_title', 'PRIMARY CARE'),
    'service_1_desc' => getContent($conn, 'index', 'service_1_desc', 'Comprehensive medical care for routine check-ups, illness treatment, and health management.'),
    'service_2_title' => getContent($conn, 'index', 'service_2_title', 'PHARMACY'),
    'service_2_desc' => getContent($conn, 'index', 'service_2_desc', 'Convenient access to prescription medications and expert pharmacist advice.'),
    'service_3_title' => getContent($conn, 'index', 'service_3_title', 'SCREENINGS'),
    'service_3_desc' => getContent($conn, 'index', 'service_3_desc', 'Early detection through regular screenings for common health concerns.'),
    'service_4_title' => getContent($conn, 'index', 'service_4_title', 'DENTAL CARE'),
    'service_4_desc' => getContent($conn, 'index', 'service_4_desc', 'Oral health services, including dental check-ups, cleanings, and treatments.'),
    'service_5_title' => getContent($conn, 'index', 'service_5_title', 'VACCINATIONS'),
    'service_5_desc' => getContent($conn, 'index', 'service_5_desc', 'Protective immunizations for various diseases, administered by qualified professionals.'),
    'service_6_title' => getContent($conn, 'index', 'service_6_title', 'EDUCATION'),
    'service_6_desc' => getContent($conn, 'index', 'service_6_desc', 'Empowering students with health knowledge through workshops and consultations.'),
    'operating_hours_mon_fri' => getContent($conn, 'index', 'operating_hours_mon_fri', '8:00 AM - 5:00 PM'),
    'operating_hours_saturday' => getContent($conn, 'index', 'operating_hours_saturday', '8:00 AM - 12:00 PM'),
    'operating_hours_sunday' => getContent($conn, 'index', 'operating_hours_sunday', 'Closed <small>(Emergency services available)</small>'),
    'contact_telephone' => getContent($conn, 'index', 'contact_telephone', '(062) 991-6736'),
    'contact_email' => getContent($conn, 'index', 'contact_email', 'healthservices@wmsu.edu.ph'),
    'contact_location' => getContent($conn, 'index', 'contact_location', 'Health Services Building, WMSU Campus, Zamboanga City, Philippines'),
    'footer_text' => getContent($conn, 'index', 'footer_text', '© 2025 Western Mindanao State University Health Services. All rights reserved. | wmsu.edu.ph')
];

// Fetch current images
$images = [
    'logo' => getImage($conn, 'index', 'logo', 'images/clinic.png'),
    'hero_background' => getImage($conn, 'index', 'hero_background', 'images/healthservices.jpg')
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS - Landing Page Content</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/adminhome.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Poppins:wght@400;500;700&display=swap">
      <link rel="apple-touch-icon" sizes="180x180" href="images/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="images/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="images/favicon-16x16.png">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <style>
        .dropdown-item.d-flex.align-items-center.active {
            background-color: #8B0000;
            /* or whatever color */
        }

        body,
        .cms-container,
        .editor-panel,
        .preview-panel,
        .form-control,
        .btn,
        .preview-content,
        .preview-header,
        .preview-footer {
            font-family: 'Poppins', sans-serif;
        }

        h1,
        h2,
        h3,
        .section-title,
        .preview-title,
        .preview-subtitle {
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

        .form-control,
        .form-control:focus {
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

        /* Preview styles - same as index.php */
        .preview-content {
            font-family: 'Poppins', sans-serif;
            background-color: white;
            overflow-x: hidden;
            margin: 0;
        }

        .preview-content :root {
            --primary: #8B0000;
            --primary-dark: #6B0000;
            --accent: #FFD700;
            --light: #F8F9FA;
            --dark: #212529;
            --white: #FFFFFF;
            --gray: #6C757D;
            --gradient-light: #f5f5f5;
            --gradient-dark: #e0e0e0;
        }

        .preview-header {
            position: sticky;
            top: 0;
            width: 100%;
            background: white;
            padding: 0.5rem 1rem;
            z-index: 1001;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .preview-header-logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .preview-header-logo img {
            width: 50px;
            height: 50px;
            object-fit: contain;
        }

        .preview-header-logo span {
            font-size: 1.2rem;
            font-weight: 700;
            color: #8B0000;
        }

        .preview-header-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .preview-hero-section {
            position: relative;
            height: 300px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            color: #212529;
            padding: 1rem;
            overflow: hidden;
            margin-bottom: 2rem;
            background-size: cover !important;
            background-position: center !important;
            background-repeat: no-repeat !important;
        }

        .preview-hero-section::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.4);
            z-index: 2;
        }

        .preview-hero-section>* {
            position: relative;
            z-index: 3;
        }

        .preview-title {
            font-family: 'Cinzel', serif;
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            line-height: 1.1;
            color: white;
            text-shadow: 0 3px 6px rgba(0, 0, 0, 0.5);
        }

        .preview-subtitle {
            font-family: 'Cinzel', serif;
            font-size: 1.5rem;
            font-weight: 400;
            margin-bottom: 1rem;
            color: white;
            text-shadow: 0 3px 6px rgba(0, 0, 0, 0.5);
        }

        .preview-layout-container {
            display: flex;
            gap: 1rem;
            padding: 0 1rem;
            margin: 0 0 2rem 0;
        }

        .preview-services-section {
            flex: 3;
            background-color: #8B0000;
            padding: 2rem;
            border-radius: 0;
            position: relative;
        }

        .preview-services-section h2 {
            color: white;
            font-size: 2rem;
            margin-bottom: 2rem;
        }

        .preview-service-card {
            background: transparent;
            text-align: center;
            padding: 1rem;
            color: white;
        }

        .preview-service-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #FFD700;
            display: block;
        }

        .preview-grid-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            padding: 0;
        }

        .preview-content-card {
            background-color: white;
            border-radius: 5px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .preview-card-header {
            background-color: #8B0000;
            color: white;
            padding: 0.75rem;
            font-weight: 600;
            text-align: center;
            font-size: 1rem;
        }

        .preview-contact-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .preview-contact-icon {
            color: #8B0000;
            font-size: 1.2rem;
            margin-right: 1rem;
            margin-top: 0.2rem;
        }

        .preview-btn-crimson {
            background-color: #8B0000;
            color: white;
            border: none;
            border-radius: 50px;
            padding: 0.5rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .preview-footer {
            background: #212529;
            color: white;
            padding: 1rem 0;
            text-align: center;
        }

        @media (max-width: 1200px) {
            .preview-layout-container {
                flex-direction: column;
            }

            .preview-services-section,
            .preview-grid-container {
                flex: none;
                width: 100%;
            }
        }

        /* Modal Styles */
        #messageModal .modal-header {
            background-color: #8B0000;
            color: white;
        }

        #messageModal .modal-header .btn-close {
            filter: invert(1);
        }

        #messageModal .modal-footer .btn-primary {
            background-color: #8B0000;
            border-color: #8B0000;
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

            .preview-title {
                font-size: 2rem;
            }

            .preview-subtitle {
                font-size: 1.2rem;
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
        <div class="main-content" id="dashboard-content" style="margin-top: 0;">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success'];
                    unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error'];
                    unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="cms-container">
                <!-- Editor Panel -->
                <div class="editor-panel">
                    <h2 class="mb-4">Edit Landing Page Content</h2>

                    <!-- Image Management Section -->
                    <h4 class="section-title">Images</h4>

                    <!-- Logo Upload -->
                    <div class="form-group">
                        <label>Logo Image</label>
                        <div class="image-upload-container" data-section="logo">
                            <img src="<?php echo htmlspecialchars($images['logo']['image_path']); ?>" alt="Current Logo" class="image-preview" id="logo-preview" style="<?php echo empty($images['logo']['image_path']) ? 'display:none;' : ''; ?>">
                            <div class="upload-text">
                                <i class="bi bi-cloud-upload fs-2 text-muted"></i>
                                <p class="mb-0">Click to upload or drag and drop</p>
                                <small class="text-muted">JPG, PNG, GIF, WebP (Max 5MB)</small>
                            </div>
                            <input type="file" id="logo-upload" accept="image/*" style="display: none;">
                            <div class="upload-progress">
                                <div class="progress">
                                    <div class="progress-bar" role="progressbar"></div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-8">
                                <input type="text" class="form-control form-control-sm" id="logo-alt" placeholder="Image description (Alt text)" value="<?php echo htmlspecialchars($images['logo']['image_alt']); ?>">
                            </div>
                            <div class="col-4">
                                <button type="button" class="btn btn-upload btn-sm w-100" onclick="uploadImage('logo')">Upload</button>
                            </div>
                        </div>
                    </div>

                    <!-- Hero Background Upload -->
                    <div class="form-group">
                        <label>Hero Background Image</label>
                        <div class="image-upload-container" data-section="hero_background">
                            <img src="<?php echo htmlspecialchars($images['hero_background']['image_path']); ?>" alt="Current Background" class="image-preview" id="hero_background-preview" style="<?php echo empty($images['hero_background']['image_path']) ? 'display:none;' : ''; ?>">
                            <div class="upload-text">
                                <i class="bi bi-cloud-upload fs-2 text-muted"></i>
                                <p class="mb-0">Click to upload or drag and drop</p>
                                <small class="text-muted">JPG, PNG, GIF, WebP (Max 5MB)</small>
                            </div>
                            <input type="file" id="hero_background-upload" accept="image/*" style="display: none;">
                            <div class="upload-progress">
                                <div class="progress">
                                    <div class="progress-bar" role="progressbar"></div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-8">
                                <input type="text" class="form-control form-control-sm" id="hero_background-alt" placeholder="Image description (Alt text)" value="<?php echo htmlspecialchars($images['hero_background']['image_alt']); ?>">
                            </div>
                            <div class="col-4">
                                <button type="button" class="btn btn-upload btn-sm w-100" onclick="uploadImage('hero_background')">Upload</button>
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="">
                        <div class="section-divider">
                            <h4 class="section-title">Hero Section</h4>
                        </div>

                        <div class="form-group">
                            <label for="hero_title">Hero Title</label>
                            <input type="text" class="form-control" id="hero_title" name="hero_title" value="<?php echo htmlspecialchars($content['hero_title']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="hero_subtitle">Hero Subtitle</label>
                            <input type="text" class="form-control" id="hero_subtitle" name="hero_subtitle" value="<?php echo htmlspecialchars($content['hero_subtitle']); ?>" required>
                        </div>

                        <div class="section-divider">
                            <h4 class="section-title">Services Section</h4>
                        </div>

                        <div class="form-group">
                            <label for="services_main_title">Services Main Title</label>
                            <input type="text" class="form-control" id="services_main_title" name="services_main_title" value="<?php echo htmlspecialchars($content['services_main_title']); ?>" required>
                        </div>

                        <!-- Service 1 -->
                        <div class="form-group">
                            <label for="service_1_title">Service 1 Title</label>
                            <input type="text" class="form-control" id="service_1_title" name="service_1_title" value="<?php echo htmlspecialchars($content['service_1_title']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="service_1_desc">Service 1 Description</label>
                            <textarea class="form-control" id="service_1_desc" name="service_1_desc" rows="2" required><?php echo htmlspecialchars($content['service_1_desc']); ?></textarea>
                        </div>

                        <!-- Service 2 -->
                        <div class="form-group">
                            <label for="service_2_title">Service 2 Title</label>
                            <input type="text" class="form-control" id="service_2_title" name="service_2_title" value="<?php echo htmlspecialchars($content['service_2_title']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="service_2_desc">Service 2 Description</label>
                            <textarea class="form-control" id="service_2_desc" name="service_2_desc" rows="2" required><?php echo htmlspecialchars($content['service_2_desc']); ?></textarea>
                        </div>

                        <!-- Service 3 -->
                        <div class="form-group">
                            <label for="service_3_title">Service 3 Title</label>
                            <input type="text" class="form-control" id="service_3_title" name="service_3_title" value="<?php echo htmlspecialchars($content['service_3_title']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="service_3_desc">Service 3 Description</label>
                            <textarea class="form-control" id="service_3_desc" name="service_3_desc" rows="2" required><?php echo htmlspecialchars($content['service_3_desc']); ?></textarea>
                        </div>

                        <!-- Service 4 -->
                        <div class="form-group">
                            <label for="service_4_title">Service 4 Title</label>
                            <input type="text" class="form-control" id="service_4_title" name="service_4_title" value="<?php echo htmlspecialchars($content['service_4_title']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="service_4_desc">Service 4 Description</label>
                            <textarea class="form-control" id="service_4_desc" name="service_4_desc" rows="2" required><?php echo htmlspecialchars($content['service_4_desc']); ?></textarea>
                        </div>

                        <!-- Service 5 -->
                        <div class="form-group">
                            <label for="service_5_title">Service 5 Title</label>
                            <input type="text" class="form-control" id="service_5_title" name="service_5_title" value="<?php echo htmlspecialchars($content['service_5_title']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="service_5_desc">Service 5 Description</label>
                            <textarea class="form-control" id="service_5_desc" name="service_5_desc" rows="2" required><?php echo htmlspecialchars($content['service_5_desc']); ?></textarea>
                        </div>

                        <!-- Service 6 -->
                        <div class="form-group">
                            <label for="service_6_title">Service 6 Title</label>
                            <input type="text" class="form-control" id="service_6_title" name="service_6_title" value="<?php echo htmlspecialchars($content['service_6_title']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="service_6_desc">Service 6 Description</label>
                            <textarea class="form-control" id="service_6_desc" name="service_6_desc" rows="2" required><?php echo htmlspecialchars($content['service_6_desc']); ?></textarea>
                        </div>

                        <div class="section-divider">
                            <h4 class="section-title">Operating Hours</h4>
                        </div>

                        <div class="form-group">
                            <label for="operating_hours_mon_fri">Monday - Friday</label>
                            <input type="text" class="form-control" id="operating_hours_mon_fri" name="operating_hours_mon_fri" value="<?php echo htmlspecialchars($content['operating_hours_mon_fri']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="operating_hours_saturday">Saturday</label>
                            <input type="text" class="form-control" id="operating_hours_saturday" name="operating_hours_saturday" value="<?php echo htmlspecialchars($content['operating_hours_saturday']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="operating_hours_sunday">Sunday</label>
                            <input type="text" class="form-control" id="operating_hours_sunday" name="operating_hours_sunday" value="<?php echo htmlspecialchars($content['operating_hours_sunday']); ?>" required>
                        </div>

                        <div class="section-divider">
                            <h4 class="section-title">Contact Information</h4>
                        </div>

                        <div class="form-group">
                            <label for="contact_telephone">Telephone</label>
                            <input type="text" class="form-control" id="contact_telephone" name="contact_telephone" value="<?php echo htmlspecialchars($content['contact_telephone']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="contact_email">Email</label>
                            <input type="email" class="form-control" id="contact_email" name="contact_email" value="<?php echo htmlspecialchars($content['contact_email']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="contact_location">Location</label>
                            <textarea class="form-control" id="contact_location" name="contact_location" rows="2" required><?php echo htmlspecialchars($content['contact_location']); ?></textarea>
                        </div>

                        <div class="section-divider">
                            <h4 class="section-title">Footer</h4>
                        </div>

                        <div class="form-group">
                            <label for="footer_text">Footer Text</label>
                            <textarea class="form-control" id="footer_text" name="footer_text" rows="2" required><?php echo htmlspecialchars($content['footer_text']); ?></textarea>
                        </div>

                        <button type="submit" name="update_content" class="btn btn-save">
                            <i class="bi bi-save"></i> Save Changes
                        </button>
                    </form>
                </div>

                <!-- Preview Panel -->
                <div class="preview-panel">
                    <div class="preview-header">
                        <div class="preview-header-logo">
                            <img src="<?php echo htmlspecialchars($images['logo']['image_path']); ?>?t=<?= time() ?>" alt="Logo" id="preview-logo">
                            <span>WMSU Health Services</span>
                        </div>
                        <div class="preview-header-buttons">
                            <button type="button" class="btn btn-preview btn-sm" onclick="updatePreview()">
                                <i class="bi bi-arrow-clockwise"></i> Refresh Preview
                            </button>
                        </div>
                    </div>

                    <div class="preview-content" id="preview-content">
                        <!-- Hero Section -->
                        <div class="preview-hero-section" id="preview-hero"
                            style="background-image: url('<?php echo !empty($images['hero_background']['image_path']) ? htmlspecialchars($images['hero_background']['image_path']) . '?t=' . time() : ''; ?>')">
                            <h1 class="preview-title" id="preview-hero-title"><?php echo htmlspecialchars($content['hero_title']); ?></h1>
                            <p class="preview-subtitle" id="preview-hero-subtitle"><?php echo htmlspecialchars($content['hero_subtitle']); ?></p>
                            <button class="preview-btn-crimson">Get Started</button>
                        </div>

                        <!-- Main Layout Container -->
                        <div class="preview-layout-container">
                            <!-- Services Section -->
                            <div class="preview-services-section">
                                <h2 id="preview-services-title"><?php echo htmlspecialchars($content['services_main_title']); ?></h2>

                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <div class="preview-service-card">
                                            <i class="bi bi-heart-pulse preview-service-icon"></i>
                                            <h5 id="preview-service-1-title"><?php echo htmlspecialchars($content['service_1_title']); ?></h5>
                                            <p id="preview-service-1-desc"><?php echo htmlspecialchars($content['service_1_desc']); ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="preview-service-card">
                                            <i class="fas fa-pills preview-service-icon"></i>
                                            <h5 id="preview-service-2-title"><?php echo htmlspecialchars($content['service_2_title']); ?></h5>
                                            <p id="preview-service-2-desc"><?php echo htmlspecialchars($content['service_2_desc']); ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="preview-service-card">
                                            <i class="bi bi-search preview-service-icon"></i>
                                            <h5 id="preview-service-3-title"><?php echo htmlspecialchars($content['service_3_title']); ?></h5>
                                            <p id="preview-service-3-desc"><?php echo htmlspecialchars($content['service_3_desc']); ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="preview-service-card">
                                            <i class="fas fa-tooth preview-service-icon"></i>
                                            <h5 id="preview-service-4-title"><?php echo htmlspecialchars($content['service_4_title']); ?></h5>
                                            <p id="preview-service-4-desc"><?php echo htmlspecialchars($content['service_4_desc']); ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="preview-service-card">
                                            <i class="bi bi-shield-plus preview-service-icon"></i>
                                            <h5 id="preview-service-5-title"><?php echo htmlspecialchars($content['service_5_title']); ?></h5>
                                            <p id="preview-service-5-desc"><?php echo htmlspecialchars($content['service_5_desc']); ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="preview-service-card">
                                            <i class="bi bi-book preview-service-icon"></i>
                                            <h5 id="preview-service-6-title"><?php echo htmlspecialchars($content['service_6_title']); ?></h5>
                                            <p id="preview-service-6-desc"><?php echo htmlspecialchars($content['service_6_desc']); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Side Grid Container -->
                            <div class="preview-grid-container">
                                <!-- Operating Hours Card -->
                                <div class="preview-content-card">
                                    <div class="preview-card-header">
                                        <i class="bi bi-clock me-2"></i>Operating Hours
                                    </div>
                                    <div class="p-3">
                                        <div class="mb-2">
                                            <strong>Mon - Fri:</strong>
                                            <span id="preview-hours-mon-fri"><?php echo htmlspecialchars($content['operating_hours_mon_fri']); ?></span>
                                        </div>
                                        <div class="mb-2">
                                            <strong>Saturday:</strong>
                                            <span id="preview-hours-saturday"><?php echo htmlspecialchars($content['operating_hours_saturday']); ?></span>
                                        </div>
                                        <div>
                                            <strong>Sunday:</strong>
                                            <span id="preview-hours-sunday"><?php echo $content['operating_hours_sunday']; ?></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Contact Information Card -->
                                <div class="preview-content-card">
                                    <div class="preview-card-header">
                                        <i class="bi bi-telephone me-2"></i>Contact Us
                                    </div>
                                    <div class="p-3">
                                        <div class="preview-contact-item">
                                            <i class="bi bi-telephone preview-contact-icon"></i>
                                            <span id="preview-contact-phone"><?php echo htmlspecialchars($content['contact_telephone']); ?></span>
                                        </div>
                                        <div class="preview-contact-item">
                                            <i class="bi bi-envelope preview-contact-icon"></i>
                                            <span id="preview-contact-email"><?php echo htmlspecialchars($content['contact_email']); ?></span>
                                        </div>
                                        <div class="preview-contact-item">
                                            <i class="bi bi-geo-alt preview-contact-icon"></i>
                                            <span id="preview-contact-location"><?php echo htmlspecialchars($content['contact_location']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="preview-footer">
                            <p class="mb-0" id="preview-footer-text"><?php echo $content['footer_text']; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include('notifications_admin.php') ?>



    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>

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
    <script src="js/adminSidebar.js"></script>

    <script>
        function showModalMessage(title, message, isSuccess = true) {
            const modalEl = document.getElementById('messageModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

            // Remove aria-hidden completely (Bootstrap will handle it)
            modalEl.removeAttribute('aria-hidden');

            // Set modal content
            document.getElementById('messageModalLabel').textContent = title;
            document.getElementById('messageModalBody').textContent = message;

            // Style header
            const header = modalEl.querySelector('.modal-header');
            header.style.backgroundColor = isSuccess ? '#28a745' : '#dc3545';

            // Show modal and handle focus
            modal.show();

            // When shown, focus the OK button
            modalEl.addEventListener('shown.bs.modal', function() {
                modalEl.querySelector('.btn-primary').focus();
            });

            // When hidden, return focus to the triggering element
            modalEl.addEventListener('hidden.bs.modal', function() {
                document.activeElement.blur();
            });
        }

        // Image upload functionality
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
                        // Update the preview image immediately
                        preview.src = data.path + '?t=' + new Date().getTime();
                        preview.style.display = 'block';

                        // Update the sidebar logo if this is the logo upload
                        if (section === 'logo') {
                            document.getElementById('sidebar-logo').src = data.path + '?t=' + new Date().getTime();
                            document.getElementById('preview-logo').src = data.path + '?t=' + new Date().getTime();
                        }

                        // Update the hero background if this is the background upload
                        if (section === 'hero_background') {
                            document.getElementById('preview-hero').style.backgroundImage = `url('${data.path}?t=${new Date().getTime()}')`;
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

        // Drag and drop functionality
        document.querySelectorAll('.image-upload-container').forEach(container => {
            const section = container.dataset.section;
            const fileInput = document.getElementById(section + '-upload');

            container.addEventListener('click', () => {
                fileInput.click();
            });

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

                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;

                    // Show preview
                    const preview = document.getElementById(section + '-preview');
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                    };
                    reader.readAsDataURL(files[0]);
                }
            });

            fileInput.addEventListener('change', (e) => {
                if (e.target.files[0]) {
                    const preview = document.getElementById(section + '-preview');
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                    };
                    reader.readAsDataURL(e.target.files[0]);
                }
            });
        });

        // Live preview update
        function updatePreview() {
            // Update hero section
            document.getElementById('preview-hero-title').textContent = document.getElementById('hero_title').value;
            document.getElementById('preview-hero-subtitle').textContent = document.getElementById('hero_subtitle').value;

            // Update services
            document.getElementById('preview-services-title').textContent = document.getElementById('services_main_title').value;

            for (let i = 1; i <= 6; i++) {
                document.getElementById(`preview-service-${i}-title`).textContent = document.getElementById(`service_${i}_title`).value;
                document.getElementById(`preview-service-${i}-desc`).textContent = document.getElementById(`service_${i}_desc`).value;
            }

            // Update operating hours
            document.getElementById('preview-hours-mon-fri').textContent = document.getElementById('operating_hours_mon_fri').value;
            document.getElementById('preview-hours-saturday').textContent = document.getElementById('operating_hours_saturday').value;
            document.getElementById('preview-hours-sunday').innerHTML = document.getElementById('operating_hours_sunday').value;

            // Update contact info
            document.getElementById('preview-contact-phone').textContent = document.getElementById('contact_telephone').value;
            document.getElementById('preview-contact-email').textContent = document.getElementById('contact_email').value;
            document.getElementById('preview-contact-location').textContent = document.getElementById('contact_location').value;

            // Update footer
            document.getElementById('preview-footer-text').innerHTML = document.getElementById('footer_text').value;
        }

        // Auto-update preview as user types
        document.querySelectorAll('input, textarea').forEach(input => {
            input.addEventListener('input', updatePreview);
        });

        // Initial preview update
        document.addEventListener('DOMContentLoaded', updatePreview);
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