<?php
session_start();
error_log("Session ID: " . session_id());
error_log("Session contents: " . print_r($_SESSION, true));

require_once 'config.php';

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Verify user is logged in
if (empty($_SESSION['user_id'])) {
    error_log("Unauthorized access attempt to cms_upload.php");
    $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'];
    header("Location: login.php");
    exit();
}

// Verify database connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    $_SESSION['error'] = "Database error. Please try again later.";
    header("Location: login.php");
    exit();
}

// Verify user is an admin
$userId = (int)$_SESSION['user_id'];
error_log("Checking admin status for user ID: $userId");

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

error_log("User type from DB: " . ($user['user_type'] ?? 'none'));

if (!$user || !in_array(trim($user['user_type']), ['Super Admin', 'Medical Admin', 'Dental Admin'], true)) {
    error_log("Non-admin user_id: $userId, user_type: " . ($user['user_type'] ?? 'none') . " attempted access to cms_upload.php");
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
        return $row['content_text'];
    }
    return $default;
}

// Function to update content in database
function updateContent($conn, $pageName, $sectionName, $content) {
    $stmt = $conn->prepare("INSERT INTO content (page_name, section_name, content_text) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE content_text = ?");
    $stmt->bind_param("ssss", $pageName, $sectionName, $content, $content);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// Function to get all FAQs
function getFAQs($conn) {
    $faqs = [];
    $stmt = $conn->prepare("SELECT id, question, answer, display_order FROM faqs WHERE page = 'upload' ORDER BY display_order ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $faqs[] = $row;
    }
    $stmt->close();
    return $faqs;
}

// Function to add/update FAQ
function saveFAQ($conn, $id, $question, $answer, $order) {
    if ($id) {
        $stmt = $conn->prepare("UPDATE faqs SET question = ?, answer = ?, display_order = ? WHERE id = ?");
        $stmt->bind_param("ssii", $question, $answer, $order, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO faqs (question, answer, display_order, page) VALUES (?, ?, ?, 'upload')");
        $stmt->bind_param("ssi", $question, $answer, $order);
    }
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// Function to delete FAQ
function deleteFAQ($conn, $id) {
    $stmt = $conn->prepare("DELETE FROM faqs WHERE id = ?");
    $stmt->bind_param("i", $id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// Function to get file requirements
function getFileRequirements($conn) {
    $requirements = [];
    $stmt = $conn->prepare("SELECT id, document_type, allowed_extensions, max_size_mb, validity_period_days, description, display_order FROM file_requirements ORDER BY display_order ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $requirements[] = $row;
    }
    $stmt->close();
    return $requirements;
}

// Function to save file requirement
function saveFileRequirement($conn, $id, $documentType, $allowedExtensions, $maxSizeMB, $validityDays, $description, $order) {
    if ($id) {
        $stmt = $conn->prepare("UPDATE file_requirements SET document_type = ?, allowed_extensions = ?, max_size_mb = ?, validity_period_days = ?, description = ?, display_order = ? WHERE id = ?");
        $stmt->bind_param("ssiisii", $documentType, $allowedExtensions, $maxSizeMB, $validityDays, $description, $order, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO file_requirements (document_type, allowed_extensions, max_size_mb, validity_period_days, description, display_order) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiisi", $documentType, $allowedExtensions, $maxSizeMB, $validityDays, $description, $order);
    }
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// Function to delete file requirement
function deleteFileRequirement($conn, $id) {
    $stmt = $conn->prepare("DELETE FROM file_requirements WHERE id = ?");
    $stmt->bind_param("i", $id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_content'])) {
        // Handle content updates
        $updates = [
            'upload_title' => $_POST['upload_title'],
            'incoming_freshman_title' => $_POST['incoming_freshman_title'],
            'incoming_freshman_text' => $_POST['incoming_freshman_text'],
            'other_users_title' => $_POST['other_users_title'],
            'other_users_text' => $_POST['other_users_text'],
            'history_title' => $_POST['history_title'],
            'faq_title' => $_POST['faq_title']
        ];

        $success = true;
        foreach ($updates as $section => $content) {
            if (!updateContent($conn, 'upload', $section, $content)) {
                $success = false;
                break;
            }
        }

        if ($success) {
            $_SESSION['success'] = "Content updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating content. Please try again.";
        }
    } 
    elseif (isset($_POST['save_faq'])) {
        $id = !empty($_POST['faq_id']) ? (int)$_POST['faq_id'] : null;
        $question = trim($_POST['faq_question']);
        $answer = trim($_POST['faq_answer']);
        $order = (int)$_POST['faq_order'];
        
        if (saveFAQ($conn, $id, $question, $answer, $order)) {
            $_SESSION['success'] = "FAQ saved successfully!";
        } else {
            $_SESSION['error'] = "Error saving FAQ. Please try again.";
        }
    }
    elseif (isset($_POST['delete_faq'])) {
        $id = (int)$_POST['faq_id'];
        if (deleteFAQ($conn, $id)) {
            $_SESSION['success'] = "FAQ deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting FAQ. Please try again.";
        }
    }
    elseif (isset($_POST['save_requirement'])) {
        $id = !empty($_POST['requirement_id']) ? (int)$_POST['requirement_id'] : null;
        $documentType = trim($_POST['document_type']);
        $allowedExtensions = trim($_POST['allowed_extensions']);
        $maxSizeMB = (int)$_POST['max_size_mb'];
        $validityDays = (int)$_POST['validity_period_days'];
        $description = trim($_POST['description']);
        $order = (int)$_POST['display_order'];
        
        if (saveFileRequirement($conn, $id, $documentType, $allowedExtensions, $maxSizeMB, $validityDays, $description, $order)) {
            $_SESSION['success'] = "File requirement saved successfully!";
        } else {
            $_SESSION['error'] = "Error saving file requirement. Please try again.";
        }
    }
    elseif (isset($_POST['delete_requirement'])) {
        $id = (int)$_POST['requirement_id'];
        if (deleteFileRequirement($conn, $id)) {
            $_SESSION['success'] = "File requirement deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting file requirement. Please try again.";
        }
    }
    
    header("Location: cms_upload.php");
    exit();
}

// Fetch current content
$content = [
    'upload_title' => getContent($conn, 'upload', 'upload_title', 'Medical Documents'),
    'incoming_freshman_title' => getContent($conn, 'upload', 'incoming_freshman_title', 'Incoming Freshman Requirements'),
    'incoming_freshman_text' => getContent($conn, 'upload', 'incoming_freshman_text', 'As an incoming freshman, you are required to submit the following medical documents:'),
    'other_users_title' => getContent($conn, 'upload', 'other_users_title', 'Medical Certificate Request'),
    'other_users_text' => getContent($conn, 'upload', 'other_users_text', 'For other user types, you can request a medical certificate by filling out the form below:'),
    'history_title' => getContent($conn, 'upload', 'history_title', 'Upload History'),
    'faq_title' => getContent($conn, 'upload', 'faq_title', 'Frequently Asked Questions (FAQ)')
];

// Fetch FAQs and file requirements
$faqs = getFAQs($conn);
$fileRequirements = getFileRequirements($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS - Upload Medical Documents</title>
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
        h1, h2, h3, .section-title, .preview-main-content h2, .preview-faq h2 {
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

        /* Item management */
        .item-card {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            background: white;
        }
        
        .item-actions {
            margin-top: 10px;
            display: flex;
            gap: 10px;
        }
        
        .add-item-btn {
            margin-bottom: 20px;
        }

        /* Preview styles */
        .preview-content {
            font-family: Arial, sans-serif;
            background: white;
            margin: 0;
            padding: 0;
            width: 100%;
        }

        .preview-main-content {
            background-color: white;
            padding: 60px 20px;
            text-align: center;
        }

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

        /* FAQ Section */
        .preview-faq {
            background-color: #f8f9fa;
            padding: 60px 10%;
        }

        .preview-faq h2 {
            color: #8B0000;
            font-size: 2rem;
            margin-bottom: 30px;
            text-align: center;
        }

        .preview-faq .list-group-item {
            margin-bottom: 10px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .preview-faq .list-group-item:hover {
            background-color: #e9ecef;
        }

        /* File Requirements Table */
        .requirements-table {
            width: 100%;
            margin-bottom: 20px;
        }
        
        .requirements-table th {
            background-color: #f8f9fa;
            text-align: left;
            padding: 10px;
        }
        
        .requirements-table td {
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        
        /* Mobile Responsiveness */
        @media (max-width: 992px) {
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
            
            .preview-main-content h2 {
                font-size: 2rem;
            }
            
            .preview-main-content p {
                font-size: 1rem;
            }
            
            .requirements-table {
                display: block;
                overflow-x: auto;
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
            <div class="cms-container">
                <!-- Editor Panel -->
                <div class="editor-panel">
                    <h2 class="mb-4">Edit Upload Page Content</h2>
                    
                    <ul class="nav nav-tabs mb-3" id="editorTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="content-tab" data-bs-toggle="tab" data-bs-target="#content" type="button" role="tab">Content</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="requirements-tab" data-bs-toggle="tab" data-bs-target="#requirements" type="button" role="tab">File Requirements</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="faq-tab" data-bs-toggle="tab" data-bs-target="#faq" type="button" role="tab">FAQs</button>
                        </li>
                    </ul>
                    
                    <div class="tab-content">
                        <!-- Content Tab -->
                        <div class="tab-pane fade show active" id="content" role="tabpanel">
                            <form method="POST" action="">
                                <input type="hidden" name="update_content" value="1">
                                <div class="section-divider">
                                    <h4 class="section-title">Main Title</h4>
                                </div>
                                
                                <div class="form-group">
                                    <label for="upload_title">Page Title</label>
                                    <input type="text" class="form-control" id="upload_title" name="upload_title" value="<?php echo htmlspecialchars($content['upload_title']); ?>" required>
                                </div>
                                
                                <div class="section-divider">
                                    <h4 class="section-title">Incoming Freshman Section</h4>
                                </div>
                                
                                <div class="form-group">
                                    <label for="incoming_freshman_title">Section Title</label>
                                    <input type="text" class="form-control" id="incoming_freshman_title" name="incoming_freshman_title" value="<?php echo htmlspecialchars($content['incoming_freshman_title']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="incoming_freshman_text">Section Text</label>
                                    <textarea class="form-control" id="incoming_freshman_text" name="incoming_freshman_text" rows="4" required><?php echo htmlspecialchars($content['incoming_freshman_text']); ?></textarea>
                                </div>
                                
                                <div class="section-divider">
                                    <h4 class="section-title">Other Users Section</h4>
                                </div>
                                
                                <div class="form-group">
                                    <label for="other_users_title">Section Title</label>
                                    <input type="text" class="form-control" id="other_users_title" name="other_users_title" value="<?php echo htmlspecialchars($content['other_users_title']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="other_users_text">Section Text</label>
                                    <textarea class="form-control" id="other_users_text" name="other_users_text" rows="4" required><?php echo htmlspecialchars($content['other_users_text']); ?></textarea>
                                </div>
                                
                                <div class="section-divider">
                                    <h4 class="section-title">History Section</h4>
                                </div>
                                
                                <div class="form-group">
                                    <label for="history_title">Section Title</label>
                                    <input type="text" class="form-control" id="history_title" name="history_title" value="<?php echo htmlspecialchars($content['history_title']); ?>" required>
                                </div>
                                
                                <div class="section-divider">
                                    <h4 class="section-title">FAQ Section</h4>
                                </div>
                                
                                <div class="form-group">
                                    <label for="faq_title">FAQ Title</label>
                                    <input type="text" class="form-control" id="faq_title" name="faq_title" value="<?php echo htmlspecialchars($content['faq_title']); ?>" required>
                                </div>
                                
                                <button type="submit" class="btn btn-save">
                                    <i class="bi bi-save"></i> Save Content Changes
                                </button>
                            </form>
                        </div>
                        
                        <!-- Requirements Tab -->
                        <div class="tab-pane fade" id="requirements" role="tabpanel">
                            <div class="mb-4">
                                <button type="button" class="btn btn-primary add-item-btn" data-bs-toggle="modal" data-bs-target="#addRequirementModal">
                                    <i class="bi bi-plus-circle"></i> Add New Requirement
                                </button>
                            </div>
                            
                            <?php foreach ($fileRequirements as $requirement): ?>
                                <div class="item-card">
                                    <h5><?php echo htmlspecialchars($requirement['document_type']); ?></h5>
                                    <p><strong>Allowed Extensions:</strong> <?php echo htmlspecialchars($requirement['allowed_extensions']); ?></p>
                                    <p><strong>Max Size:</strong> <?php echo htmlspecialchars($requirement['max_size_mb']); ?> MB</p>
                                    <p><strong>Validity:</strong> <?php echo htmlspecialchars($requirement['validity_period_days']); ?> days</p>
                                    <p><strong>Description:</strong> <?php echo htmlspecialchars($requirement['description']); ?></p>
                                    <p><strong>Order:</strong> <?php echo htmlspecialchars($requirement['display_order']); ?></p>
                                    
                                    <div class="item-actions">
                                        <button type="button" class="btn btn-sm btn-primary edit-requirement" 
                                            data-id="<?php echo $requirement['id']; ?>"
                                            data-document-type="<?php echo htmlspecialchars($requirement['document_type']); ?>"
                                            data-allowed-extensions="<?php echo htmlspecialchars($requirement['allowed_extensions']); ?>"
                                            data-max-size="<?php echo $requirement['max_size_mb']; ?>"
                                            data-validity="<?php echo $requirement['validity_period_days']; ?>"
                                            data-description="<?php echo htmlspecialchars($requirement['description']); ?>"
                                            data-order="<?php echo $requirement['display_order']; ?>">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="delete_requirement" value="1">
                                            <input type="hidden" name="requirement_id" value="<?php echo $requirement['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this requirement?');">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- FAQ Tab -->
                        <div class="tab-pane fade" id="faq" role="tabpanel">
                            <div class="mb-4">
                                <button type="button" class="btn btn-primary add-item-btn" data-bs-toggle="modal" data-bs-target="#addFAQModal">
                                    <i class="bi bi-plus-circle"></i> Add New FAQ
                                </button>
                            </div>
                            
                            <?php foreach ($faqs as $faq): ?>
                                <div class="item-card">
                                    <h5><?php echo htmlspecialchars($faq['question']); ?></h5>
                                    <p><?php echo htmlspecialchars($faq['answer']); ?></p>
                                    <p><strong>Order:</strong> <?php echo htmlspecialchars($faq['display_order']); ?></p>
                                    
                                    <div class="item-actions">
                                        <button type="button" class="btn btn-sm btn-primary edit-faq" 
                                            data-id="<?php echo $faq['id']; ?>"
                                            data-question="<?php echo htmlspecialchars($faq['question']); ?>"
                                            data-answer="<?php echo htmlspecialchars($faq['answer']); ?>"
                                            data-order="<?php echo $faq['display_order']; ?>">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="delete_faq" value="1">
                                            <input type="hidden" name="faq_id" value="<?php echo $faq['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this FAQ?');">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Preview Panel -->
                <div class="preview-panel">
                    <div class="preview-content" id="preview-content">
                        <div class="preview-main-content">
                            <h1 id="preview-upload-title"><?php echo htmlspecialchars($content['upload_title']); ?></h1>
                            
                            <ul class="nav nav-tabs mb-4">
                                <li class="nav-item">
                                    <a class="nav-link active" href="#">Upload Medical Documents</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="#">Upload History</a>
                                </li>
                            </ul>
                            
                            <div class="tab-content">
                                <!-- Upload Tab -->
                                <div class="tab-pane fade show active">
                                    <h2 id="preview-incoming-freshman-title"><?php echo htmlspecialchars($content['incoming_freshman_title']); ?></h2>
                                    <p id="preview-incoming-freshman-text"><?php echo htmlspecialchars($content['incoming_freshman_text']); ?></p>
                                    
                                    <div class="requirements-container mt-4">
                                        <h4>Required Documents:</h4>
                                        <ul id="preview-requirements-list">
                                            <?php foreach ($fileRequirements as $requirement): ?>
                                                <li><?php echo htmlspecialchars($requirement['description']); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    
                                    <div class="upload-section mt-4">
                                        <h4>Upload Documents:</h4>
                                        <form>
                                            <?php foreach ($fileRequirements as $requirement): ?>
                                                <div class="mb-3">
                                                    <label for="<?php echo htmlspecialchars(str_replace(' ', '_', strtolower($requirement['document_type']))); ?>" class="form-label">
                                                        <?php echo htmlspecialchars($requirement['document_type']); ?>
                                                    </label>
                                                    <input type="file" class="form-control" 
                                                        id="<?php echo htmlspecialchars(str_replace(' ', '_', strtolower($requirement['document_type']))); ?>" 
                                                        accept="<?php echo htmlspecialchars(str_replace(',', ',.', $requirement['allowed_extensions'])); ?>">
                                                    <div class="form-text">
                                                        Allowed extensions: <?php echo htmlspecialchars($requirement['allowed_extensions']); ?>, 
                                                        Max size: <?php echo htmlspecialchars($requirement['max_size_mb']); ?>MB
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                            <button type="submit" class="btn btn-primary">Submit Documents</button>
                                        </form>
                                    </div>
                                    
                                    <h2 class="mt-5" id="preview-other-users-title"><?php echo htmlspecialchars($content['other_users_title']); ?></h2>
                                    <p id="preview-other-users-text"><?php echo htmlspecialchars($content['other_users_text']); ?></p>
                                    
                                    <div class="certificate-request mt-4">
                                        <form>
                                            <div class="mb-3">
                                                <label for="request-purpose" class="form-label">Purpose of Request</label>
                                                <input type="text" class="form-control" id="request-purpose">
                                            </div>
                                            <button type="submit" class="btn btn-primary">Request Certificate</button>
                                        </form>
                                    </div>
                                </div>
                                
                                <!-- History Tab -->
                                <div class="tab-pane fade">
                                    <h2 id="preview-history-title"><?php echo htmlspecialchars($content['history_title']); ?></h2>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Document Type</th>
                                                    <th>Date Uploaded</th>
                                                    <th>Status</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>Chest X-Ray Results</td>
                                                    <td>2023-06-15</td>
                                                    <td><span class="badge bg-success">Approved</span></td>
                                                    <td><button class="btn btn-sm btn-outline-primary">View</button></td>
                                                </tr>
                                                <tr>
                                                    <td>Complete Blood Count</td>
                                                    <td>2023-06-15</td>
                                                    <td><span class="badge bg-warning">Pending</span></td>
                                                    <td><button class="btn btn-sm btn-outline-primary">View</button></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- FAQ Section -->
                            <div class="preview-faq">
                                <h2 id="preview-faq-title"><?php echo htmlspecialchars($content['faq_title']); ?></h2>
                                
                                <div class="accordion" id="faqAccordion">
                                    <?php foreach ($faqs as $index => $faq): ?>
                                        <div class="accordion-item">
                                            <h3 class="accordion-header">
                                                <button class="accordion-button <?php echo $index === 0 ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?php echo $faq['id']; ?>">
                                                    <?php echo htmlspecialchars($faq['question']); ?>
                                                </button>
                                            </h3>
                                            <div id="faq<?php echo $faq['id']; ?>" class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" data-bs-parent="#faqAccordion">
                                                <div class="accordion-body">
                                                    <p><?php echo htmlspecialchars($faq['answer']); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Requirement Modal -->
    <div class="modal fade" id="addRequirementModal" tabindex="-1" aria-labelledby="addRequirementModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" name="save_requirement" value="1">
                    <input type="hidden" name="requirement_id" id="requirement_id" value="">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="addRequirementModalLabel">Add File Requirement</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="document_type" class="form-label">Document Type</label>
                            <input type="text" class="form-control" id="document_type" name="document_type" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="allowed_extensions" class="form-label">Allowed Extensions (comma separated)</label>
                            <input type="text" class="form-control" id="allowed_extensions" name="allowed_extensions" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="max_size_mb" class="form-label">Maximum Size (MB)</label>
                            <input type="number" class="form-control" id="max_size_mb" name="max_size_mb" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="validity_period_days" class="form-label">Validity Period (days)</label>
                            <input type="number" class="form-control" id="validity_period_days" name="validity_period_days" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="display_order" class="form-label">Display Order</label>
                            <input type="number" class="form-control" id="display_order" name="display_order" required>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Requirement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Add FAQ Modal -->
    <div class="modal fade" id="addFAQModal" tabindex="-1" aria-labelledby="addFAQModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" name="save_faq" value="1">
                    <input type="hidden" name="faq_id" id="faq_id" value="">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="addFAQModalLabel">Add FAQ</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="faq_question" class="form-label">Question</label>
                            <input type="text" class="form-control" id="faq_question" name="faq_question" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="faq_answer" class="form-label">Answer</label>
                            <textarea class="form-control" id="faq_answer" name="faq_answer" rows="3" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="faq_order" class="form-label">Display Order</label>
                            <input type="number" class="form-control" id="faq_order" name="faq_order" required>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save FAQ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Message Modal -->
    <div class="modal fade" id="messageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="messageModalTitle">Modal title</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="messageModalBody">
                    ...
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

      <?php include('notifications_admin.php')?>
        
       
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
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
            // Sidebar toggle functionality
            document.getElementById('burger-btn').addEventListener('click', function() {
                const sidebar = document.getElementById('sidebar');
                const logo = document.getElementById('sidebar-logo');
                
                if (sidebar.style.width === '250px') {
                    sidebar.style.width = '80px';
                    logo.style.width = '50px';
                    document.querySelectorAll('.dropdown-toggle').forEach(el => el.style.display = 'none');
                    document.querySelectorAll('.dropdown-menu').forEach(el => el.style.display = 'none');
                    document.querySelectorAll('.btn-crimson:not(.dropdown-toggle)').forEach(el => {
                        el.innerHTML = '<i class="' + el.querySelector('i').className + '"></i>';
                    });
                } else {
                    sidebar.style.width = '250px';
                    logo.style.width = '100px';
                    document.querySelectorAll('.dropdown-toggle').forEach(el => el.style.display = 'block');
                    document.querySelectorAll('.dropdown-menu').forEach(el => el.style.display = 'block');
                    document.querySelectorAll('.btn-crimson:not(.dropdown-toggle)').forEach(el => {
                        const iconClass = el.querySelector('i').className;
                        const originalText = el.getAttribute('data-original-text') || el.textContent.trim();
                        el.innerHTML = '<i class="' + iconClass + '"></i> ' + originalText;
                    });
                }
            });
            
            // Store original button text for sidebar buttons
            document.querySelectorAll('.btn-crimson:not(.dropdown-toggle)').forEach(el => {
                el.setAttribute('data-original-text', el.textContent.trim());
            });
            
            // Function to show modal messages
            function showModalMessage(title, message, isSuccess) {
                const modal = new bootstrap.Modal(document.getElementById('messageModal'));
                document.getElementById('messageModalTitle').textContent = title;
                document.getElementById('messageModalBody').textContent = message;
                
                const header = document.querySelector('#messageModal .modal-header');
                if (isSuccess) {
                    header.className = 'modal-header bg-success text-white';
                } else {
                    header.className = 'modal-header bg-danger text-white';
                }
                
                modal.show();
            }
            
            // Edit Requirement functionality
            document.querySelectorAll('.edit-requirement').forEach(button => {
                button.addEventListener('click', function() {
                    const modal = new bootstrap.Modal(document.getElementById('addRequirementModal'));
                    document.getElementById('addRequirementModalLabel').textContent = 'Edit File Requirement';
                    document.getElementById('requirement_id').value = this.getAttribute('data-id');
                    document.getElementById('document_type').value = this.getAttribute('data-document-type');
                    document.getElementById('allowed_extensions').value = this.getAttribute('data-allowed-extensions');
                    document.getElementById('max_size_mb').value = this.getAttribute('data-max-size');
                    document.getElementById('validity_period_days').value = this.getAttribute('data-validity');
                    document.getElementById('description').value = this.getAttribute('data-description');
                    document.getElementById('display_order').value = this.getAttribute('data-order');
                    modal.show();
                });
            });
            
            // Edit FAQ functionality
            document.querySelectorAll('.edit-faq').forEach(button => {
                button.addEventListener('click', function() {
                    const modal = new bootstrap.Modal(document.getElementById('addFAQModal'));
                    document.getElementById('addFAQModalLabel').textContent = 'Edit FAQ';
                    document.getElementById('faq_id').value = this.getAttribute('data-id');
                    document.getElementById('faq_question').value = this.getAttribute('data-question');
                    document.getElementById('faq_answer').value = this.getAttribute('data-answer');
                    document.getElementById('faq_order').value = this.getAttribute('data-order');
                    modal.show();
                });
            });
            
            // Reset modal when adding new items
            document.getElementById('addRequirementModal').addEventListener('show.bs.modal', function(event) {
                if (!event.relatedTarget) {
                    // Not triggered by edit button
                    document.getElementById('addRequirementModalLabel').textContent = 'Add File Requirement';
                    document.getElementById('requirement_id').value = '';
                    this.querySelector('form').reset();
                }
            });
            
            document.getElementById('addFAQModal').addEventListener('show.bs.modal', function(event) {
                if (!event.relatedTarget) {
                    // Not triggered by edit button
                    document.getElementById('addFAQModalLabel').textContent = 'Add FAQ';
                    document.getElementById('faq_id').value = '';
                    this.querySelector('form').reset();
                }
            });
            
            // Real-time preview updates
            document.getElementById('upload_title').addEventListener('input', function() {
                document.getElementById('preview-upload-title').textContent = this.value;
            });
            
            document.getElementById('incoming_freshman_title').addEventListener('input', function() {
                document.getElementById('preview-incoming-freshman-title').textContent = this.value;
            });
            
            document.getElementById('incoming_freshman_text').addEventListener('input', function() {
                document.getElementById('preview-incoming-freshman-text').textContent = this.value;
            });
            
            document.getElementById('other_users_title').addEventListener('input', function() {
                document.getElementById('preview-other-users-title').textContent = this.value;
            });
            
            document.getElementById('other_users_text').addEventListener('input', function() {
                document.getElementById('preview-other-users-text').textContent = this.value;
            });
            
            document.getElementById('history_title').addEventListener('input', function() {
                document.getElementById('preview-history-title').textContent = this.value;
            });
            
            document.getElementById('faq_title').addEventListener('input', function() {
                document.getElementById('preview-faq-title').textContent = this.value;
            });
        </script>
    </body>
</html>