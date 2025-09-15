<?php
ob_start();
session_start();
require_once __DIR__ . '/config.php';

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');
error_reporting(E_ALL);

// Log request
error_log("medcertRequest.php: Session: " . print_r($_SESSION, true));
error_log("medcertRequest.php: GET: " . print_r($_GET, true));

// Check session
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    error_log("medcertRequest.php: No session, redirecting to login");
    header("Location: /login.php");
    ob_end_flush();
    exit();
}

$admin_id = intval($_SESSION['user_id']);
$target_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$child_id = isset($_GET['child_id']) ? intval($_GET['child_id']) : 0;

if ($target_user_id === 0) {
    error_log("medcertRequest.php: Missing target_user_id");
    echo "<script>alert('Invalid user ID. Please specify a user.'); window.location.href='adminhome.php';</script>";
    ob_end_flush();
    exit();
}

// Check database connection
if (!$conn) {
    error_log("medcertRequest.php: Database connection failed");
    echo "<script>alert('Unable to connect to database.'); window.location.href='adminhome.php';</script>";
    ob_end_flush();
    exit();
}

// Verify admin privileges
$sql_admin = "SELECT user_type FROM users WHERE id = ?";
$stmt_admin = $conn->prepare($sql_admin);
if (!$stmt_admin) {
    error_log("medcertRequest.php: Prepare admin check failed: " . $conn->error);
    echo "<script>alert('Unable to verify admin privileges.'); window.location.href='adminhome.php';</script>";
    ob_end_flush();
    exit();
}
$stmt_admin->bind_param("i", $admin_id);
$stmt_admin->execute();
$result_admin = $stmt_admin->get_result();
$admin_type = $result_admin->num_rows > 0 ? $result_admin->fetch_assoc()['user_type'] : null;
$stmt_admin->close();

if (!in_array($admin_type, ['Super Admin', 'Medical Admin', 'Dental Admin'])) {
    error_log("medcertRequest.php: User_id=$admin_id is not admin, user_type=$admin_type");
    echo "<script>alert('Unauthorized access. Admins only.'); window.location.href='login.php';</script>";
    ob_end_flush();
    exit();
}

// Generate or fetch CSRF token
function generateCsrfToken($user_id, $conn)
{
    $stmt = $conn->prepare("SELECT token FROM csrf_tokens WHERE user_id = ? AND created_at > NOW() - INTERVAL 24 HOUR");
    if (!$stmt) {
        error_log("medcertRequest.php: Prepare CSRF query failed: " . $conn->error);
        return '';
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $token = $result->num_rows > 0 ? $result->fetch_assoc()['token'] : '';
    $stmt->close();

    if (empty($token)) {
        $token = bin2hex(random_bytes(32));
        $stmt = $conn->prepare("INSERT INTO csrf_tokens (user_id, token, created_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE token = ?, created_at = NOW()");
        if ($stmt) {
            $stmt->bind_param("iss", $user_id, $token, $token);
            $stmt->execute();
            $stmt->close();
        } else {
            error_log("medcertRequest.php: Prepare CSRF insert failed: " . $conn->error);
        }
    }
    return $token;
}

$csrf_token = generateCsrfToken($admin_id, $conn);
error_log("medcertRequest.php: CSRF token for admin_id=$admin_id: $csrf_token");

// Fetch user or child info
$user_info = null;
if ($child_id > 0) {
    $sql = "
        SELECT 
            CONCAT(c.first_name, ' ', COALESCE(c.middle_name, ''), ' ', c.last_name) AS full_name,
            p.age,
            p.sex,
            c.type AS course
        FROM children c
        JOIN patients p ON c.parent_id = p.id
        WHERE c.id = ? AND p.user_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("medcertRequest.php: Prepare child query failed: " . $conn->error);
        echo "<script>alert('Unable to fetch child data.'); window.location.href='adminhome.php';</script>";
        ob_end_flush();
        exit();
    }
    $stmt->bind_param("ii", $child_id, $target_user_id);
} else {
    $sql = "
        SELECT 
            CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name) AS full_name,
            age,
            sex,
            course
        FROM users u
        JOIN patients p ON u.id = p.user_id
        WHERE u.id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("medcertRequest.php: Prepare user query failed: " . $conn->error);
        echo "<script>alert('Unable to fetch user data.'); window.location.href='adminhome.php';</script>";
        ob_end_flush();
        exit();
    }
    $stmt->bind_param("i", $target_user_id);
}

$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $user_info = $result->fetch_assoc();
    error_log("medcertRequest.php: Fetched user_info: " . print_r($user_info, true));
} else {
    error_log("medcertRequest.php: User/child not found for target_user_id=$target_user_id, child_id=$child_id");
    echo "<script>alert('User or child not found.'); window.location.href='adminhome.php';</script>";
    $stmt->close();
    $conn->close();
    ob_end_flush();
    exit();
}
$stmt->close();

// Normalize user_info
$user_info['full_name'] = !empty($user_info['full_name']) ? $user_info['full_name'] : 'Unknown';
$user_info['age'] = !empty($user_info['age']) ? $user_info['age'] : 0;
$user_info['sex'] = isset($user_info['sex']) && in_array($user_info['sex'], ['male', 'female']) ? $user_info['sex'] : 'unknown';
$user_info['course'] = !empty($user_info['course']) ? $user_info['course'] : '';

// Function to get ordinal suffix
function getOrdinalSuffix($day)
{
    if (!in_array(($day % 100), [11, 12, 13])) {
        switch ($day % 10) {
            case 1:
                return 'st';
            case 2:
                return 'nd';
            case 3:
                return 'rd';
        }
    }
    return 'th';
}

$current_date = date('j');
$current_date_with_suffix = $current_date . getOrdinalSuffix($current_date);
$current_month = date('F');
$current_year = date('Y');

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Certificate - WMSU Health Services</title>
    <link rel="icon" type="image/png" href="images/clinic.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/adminhome.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
    <link rel="apple-touch-icon" sizes="180x180" href="images/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="images/favicon-16x16.png">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            text-align: center;
            padding: 10px 20px;
        }

        .logo-left,
        .logo-right {
            width: 80px;
            height: auto;
        }

        .university-info {
            flex: 1;
            padding: 0 20px;
        }

        .university-info h2,
        .university-info h3,
        .university-info h4,
        .university-info p {
            margin: 0;
            line-height: 1.4;
        }


        .underline {
            border-bottom: 2px solid black;
            display: inline-block;
        }

        .small-underline {
            border-bottom: 1px solid black;
            display: inline-block;
        }

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
            justify-content: center;
        }

        .print-btn,
        .save-btn,
        .email-btn {
            padding: 8px 16px;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #dc3545;
            color: white;
        }

        .print-btn:hover,
        .save-btn:hover,
        .email-btn:hover {
            background: #c82333;
        }

        .email-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }

        @media (max-width: 576px) {
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }

            .print-btn,
            .save-btn,
            .email-btn {
                width: 100%;
                max-width: 200px;
            }
        }

        @media print {

            .ql-editor.ql-blank::before {
                display: none !important;
            }

            #editor-container {
                border: none !important;
                background: none !important;
                color: black !important;
            }

            strong,
            b,
            em,
            i,
            .ql-editor strong,
            .ql-editor b,
            .ql-editor em,
            .ql-editor i {
                font-weight: normal !important;
                font-style: normal !important;
            }

            .ql-toolbar,
            .ql-container {
                border: 1px solid black !important;
            }

            [contenteditable="true"] {
                outline: none !important;
                border: none !important;
            }

            .print-btn,
            .save-btn,
            .email-btn,
            .ql-toolbar {
                display: none !important;
            }

            body {
                background-image: url('images/clinic.png');
                /* Use your watermark image */
                background-size: contain;
                background-position: center center;
                background-repeat: no-repeat;
                background-attachment: fixed;
                /* Helps on multipage printouts */
                -webkit-print-color-adjust: exact;
                /* Ensures colors and bg show */
                print-color-adjust: exact;
                position: relative;
            }

            body::before {
                content: "";
                position: fixed;
                /* fixed is better than absolute for print */
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(255, 255, 255, 0.6);
                /* Optional overlay */
                z-index: -1;
                pointer-events: none;
            }

            .print-btn,
            .save-btn,
            .email-btn,
            .ql-toolbar {
                display: none !important;
            }


            #editor-container.placeholder {
                display: none !important;
            }

            #editor-container {
                display: block !important;
                border: none !important;
                background: none !important;
                color: black !important;
            }


            .logo-left,
            .logo-right {
                display: block !important;
                visibility: visible !important;
                width: 80px !important;
                height: auto !important;
            }


            body {
                /* Path to your image */
                background-image: url('images/clinic.png');
                /* Make it cover the entire container */
                background-size: contain;
                /* Center it both horizontally & vertically */
                background-position: center center;
                /* Ensure any overlapping content is readable */
                background-repeat: no-repeat;
                /* Optional: add a subtle overlay for contrast */
                position: relative;
            }

            /* If you want a faint watermark effect, you can add: */
            body::before {
                content: "";
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(255, 255, 255, 0.6);
                /* lower the opacity to let bg show through */
                z-index: 0;
            }

            /* Then make sure all inner content sits above the overlay: */
            body {
                position: relative;
                z-index: 1;
            }
        }

        #successModal .modal-dialog,
        #errorModal .modal-dialog {
            width: 400px;
            max-width: 90%;
            margin: 0 auto;
            display: flex;
            align-items: center;
            min-height: calc(100% - (1.75rem * 2));
        }

        .modal {
            background-color: transparent;
            pointer-events: none;
        }

        .modal-dialog {
            pointer-events: all;
        }

        .modal-content {
            border-radius: 8px;
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            width: 100%;
        }

        .modal-header {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 15px;
            border-top: 1px solid #dee2e6;
        }

        .logo-left,
        .logo-right {
            height: 80px;
            width: auto;
            max-height: 80px;
            max-width: 100%;
            display: block;
            object-fit: contain;
        }

        .text-bold {
            font-weight: bold;
        }

        .mc-text {
            font-weight: bold
        }

        .doctor-info p {
            margin: 0;
            font-size: 14px;
        }

        .action-buttons button {
            margin-left: 5px;
        }

        .medical-certificate-container {
            /* Path to your image */
            background-image: url('images/clinic.png');
            /* Make it cover the entire container */
            background-size: contain;
            /* Center it both horizontally & vertically */
            background-position: center center;
            /* Ensure any overlapping content is readable */
            background-repeat: no-repeat;
            /* Optional: add a subtle overlay for contrast */
            position: relative;
        }

        /* If you want a faint watermark effect, you can add: */
        .medical-certificate-container::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.6);
            /* lower the opacity to let bg show through */
            z-index: 0;
        }

        /* Then make sure all inner content sits above the overlay: */
        .medical-certificate-container>* {
            position: relative;
            z-index: 1;
        }
    </style>
</head>

<body>
    <div id="app" class="d-flex">
        <button id="burger-btn" class="burger-btn">☰</button>
        <?php include 'include/sidebar.php'; ?>
        <div class="main-content">
            <div class="medical-certificate-container">
                <div class="header">
                    <img src="images/23.png" alt="WMSU Logo" class="logo-left">
                    <div class="university-info">
                        <h6 class="text-bold">WESTERN MINDANAO STATE UNIVERSITY</h6>
                        <h6 class="text-bold">ZAMBOANGA CITY</h6>
                        <h6 class="text-bold">UNIVERSITY HEALTH SERVICES CENTER</h6>
                        <p>Tel. no. (062) 991-6736 / Email: healthservices@wmsu.edu.ph</p>
                    </div>
                    <img src="images/clinic.png" alt="Clinic Logo" class="logo-right">
                </div>
                <h2 class="title text-center mt-5 mb-5 mc-text"><u>MEDICAL CERTIFICATE</u></h2>
                <form id="medical-cert-form">
                    <input type="hidden" id="csrf-token" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" id="target-user-id" name="target_user_id" value="<?php echo htmlspecialchars($target_user_id); ?>">
                    <input type="hidden" id="child-id" name="child_id" value="<?php echo htmlspecialchars($child_id); ?>">
                    <p class="to-whom" contenteditable="true">To whom it may concern:</p>
                    <p contenteditable="true">
                        This is to certify that
                        <span class="underline"><?php echo htmlspecialchars($user_info['full_name']); ?></span>,
                        a <span class="small-underline age"><?php echo htmlspecialchars($user_info['age']); ?></span>-year-old
                        <span class="small-underline gender"><?php echo htmlspecialchars($user_info['sex']); ?></span>,
                        has been clinically assessed by the University Health Services Center and was deemed
                        <span contenteditable="true"><strong>physically fit for college admission</strong></span>.
                    </p>
                    <p contenteditable="true">
                        Chest radiography and laboratory test results were reviewed.
                        He/She has no unstable comorbid illnesses nor any maintenance medications.
                        Hence, there are no contraindications for school-related activities.
                    </p>
                    <p contenteditable="true">
                        This certification is issued upon request of
                        <span class="underline"><?php echo htmlspecialchars($user_info['full_name']); ?></span>
                        for whatever purpose it may serve him/her best.
                    </p>
                    <p contenteditable="true">
                        Given this
                        <span class="small-underline day"><?php echo htmlspecialchars($current_date_with_suffix); ?></span> day of
                        <span class="small-underline month"><?php echo htmlspecialchars($current_month); ?></span>,
                        <span class="small-underline year"><?php echo htmlspecialchars($current_year); ?></span>
                        in the City of Zamboanga, Philippines.
                    </p>
                    <div class="certificate-wrapper d-flex flex-column" style="min-height: 300px; position: relative;">
                        <div id="editor-container" class="flex-grow-1" style="margin-top: 20px; border: 1px solid #ccc; min-height: 100px;"></div>

                        <div class="d-flex flex-column align-items-end mt-3">
                            <div class="doctor-info text-end">
                                <p contenteditable="true">
                                    <span class="doctor-name">FELICITAS ASUNCION C. ELAGO, M.D.</span>
                                    <br>
                                    <span class="doctor-title">MEDICAL OFFICER III</span>
                                    <br>
                                    <span class="doctor-license">LICENSE NO. 0160267</span>
                                    <br>
                                    <span class="doctor-ptr">PTR NO. 2795114</span>
                                    <br>
                                </p>
                            </div>

                        </div>
                    </div>

                </form>
              
            </div>
              <div class="action-buttons mt-2">
                    <button type="button" class="print-btn btn btn-outline-secondary btn-sm" onclick="printCertificate()">Print Certificate</button>
                    <button type="button" class="save-btn btn btn-outline-primary btn-sm" onclick="saveCertificate()">Save Certificate</button>
                    <button type="button" class="email-btn btn btn-outline-success btn-sm" id="email-btn">Send via Email</button>
                </div>
        </div>
        
        <!-- Success Modal -->
        <div class="modal" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="successModalLabel">Success</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <span id="success-message"></span>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" style="background-color: #6c757d; color: white;" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Error Modal -->
        <div class="modal" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="errorModalLabel">Error</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <span id="error-message"></span>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" style="background-color: #6c757d; color: white;" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
    <script>
        function prepareForPrintOrSave() {
            const editor = quill.root.innerHTML; // Or however you get content
            document.getElementById("editor-container").innerHTML = editor;

            return () => {
                // optional: restore original editable state after save
                document.getElementById("editor-container").innerHTML = "";
                quill.setContents(editor); // reapply if needed
            };
        }


        // Initialize Quill editor
        const quill = new Quill('#editor-container', {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline'],
                    [{
                        'list': 'ordered'
                    }, {
                        'list': 'bullet'
                    }],
                    [{
                        'align': []
                    }],
                    ['clean']
                ]
            },
            placeholder: 'Add additional notes or content here...'
        });

        // Attach listeners to contenteditable elements
        function attachEditableListeners() {
            const editableElements = document.querySelectorAll('[contenteditable="true"]:not(.ql-editor)');
            editableElements.forEach(element => {
                element.addEventListener('focus', () => element.style.outline = '1px solid #007bff');
                element.addEventListener('blur', () => element.style.outline = 'none');
            });
        }

        function showSuccessModal(message, callback) {
            const modalElement = document.getElementById('successModal');
            const messageElement = document.getElementById('success-message');
            const modal = new bootstrap.Modal(modalElement, {
                backdrop: false,
                keyboard: true
            });
            messageElement.textContent = message;
            modal.show();
            setTimeout(() => {
                modal.hide();
                if (callback) callback();
            }, 1000);
        }

        function showErrorModal(message) {
            const modalElement = document.getElementById('errorModal');
            const messageElement = document.getElementById('error-message');
            const modal = new bootstrap.Modal(modalElement, {
                backdrop: false,
                keyboard: true
            });
            messageElement.textContent = message;
            modal.show();
            setTimeout(() => modal.hide(), 1000);
        }

        // Sidebar and responsive behavior
        document.addEventListener('DOMContentLoaded', function() {
            const burgerBtn = document.getElementById('burger-btn');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');

            if (burgerBtn) {
                burgerBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    sidebar.classList.toggle('active');
                    mainContent.classList.toggle('sidebar-active');
                });
            }

            document.addEventListener('click', function(event) {
                if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
                    const isClickInsideSidebar = sidebar.contains(event.target);
                    const isClickOnBurgerBtn = burgerBtn.contains(event.target);
                    if (!isClickInsideSidebar && !isClickOnBurgerBtn) {
                        sidebar.classList.remove('active');
                        mainContent.classList.remove('sidebar-active');
                    }
                }
            });

            const sidebarButtons = document.querySelectorAll('#sidebar .btn-crimson:not(#cmsDropdown), #sidebar .dropdown-item');
            sidebarButtons.forEach(button => {
                button.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        sidebar.classList.remove('active');
                        mainContent.classList.remove('sidebar-active');
                    }
                });
            });

            const emailBtn = document.getElementById('email-btn');
            if (emailBtn) {
                emailBtn.addEventListener('click', sendViaEmail);
            } else {
                console.error('Email button not found');
            }

            attachEditableListeners();
        });

        // Prepare content for print or save
        function prepareForPrintOrSave() {
            const editableElements = document.querySelectorAll('[contenteditable="true"]:not(.ql-editor)');
            const originalStyles = Array.from(editableElements).map(el => ({
                element: el,
                style: {
                    ...el.style
                }
            }));
            const originalQuillContent = quill.root.innerHTML;

            // Hide unnecessary elements
            document.querySelectorAll('.print-btn, .save-btn, .email-btn, .ql-toolbar').forEach(el => {
                el.style.display = 'none';
            });

            // Remove contenteditable styles
            document.querySelectorAll('[contenteditable="true"]').forEach(element => {
                element.style.outline = 'none';
                element.style.border = 'none';
            });

            // Hide Quill editor
            const editorContainer = document.getElementById('editor-container');
            if (editorContainer) editorContainer.style.display = 'none';

            // Clean Quill content
            const quillEditor = document.querySelector('.ql-editor');
            const tempContainer = document.createElement('div');
            tempContainer.innerHTML = quillEditor.innerHTML;
            tempContainer.querySelectorAll('strong, b, em, i').forEach(el => {
                const textNode = document.createTextNode(el.textContent);
                el.parentNode.replaceChild(textNode, el);
            });
            quillEditor.innerHTML = tempContainer.innerHTML;

            // Convert relative image paths to absolute and set logo size
            const baseUrl = window.location.origin;
            console.log(baseUrl);
            document.querySelectorAll('.logo-left').forEach(img => {
                img.src = baseUrl + '/wmsu/images/23.png';
                console.log(img.src);
                img.style.width = '80px';
                img.style.height = 'auto';
                img.style.verticalAlign = 'middle';
            });
            document.querySelectorAll('.logo-right').forEach(img => {
                img.src = baseUrl + '/wmsu/images/clinic.png';
                console.log(img.src);
                img.style.width = '80px';
                img.style.height = 'auto';
                img.style.verticalAlign = 'middle';
            });

            // Set header layout
            const header = document.querySelector('.header');
            if (header) {
                header.style.display = 'flex';
                header.style.alignItems = 'center';
                header.style.justifyContent = 'space-between';
            }

            return () => {
                document.querySelectorAll('.print-btn, .save-btn, .email-btn').forEach(el => {
                    el.style.display = 'block';
                });
                document.querySelector('.ql-toolbar').style.display = 'block';
                originalStyles.forEach(({
                    element,
                    style
                }) => Object.assign(element.style, style));
                quill.root.innerHTML = originalQuillContent;
                if (editorContainer) editorContainer.style.display = 'block';
                document.querySelectorAll('.underline').forEach(el => el.style.borderBottom = '2px solid #000');
                document.querySelectorAll('.small-underline').forEach(el => el.style.borderBottom = '1px solid #000');
                document.querySelectorAll('.logo-left').forEach(img => {
                    img.src = baseUrl + '/wmsu/images/23.png';
                    img.style.width = '';
                    img.style.height = '';
                    img.style.verticalAlign = '';
                });
                document.querySelectorAll('.logo-right').forEach(img => {
                    img.src = baseUrl + '/wmsu/images/clinic.png';
                    img.style.width = '25px';
                    img.style.height = '25px';
                    img.style.verticalAlign = '';
                });
                if (header) {
                    header.style.display = '';
                    header.style.alignItems = '';
                    header.style.justifyContent = '';
                }
                attachEditableListeners();
            };
        }

        // Print certificate
        function printCertificate() {
            const restore = prepareForPrintOrSave();
            window.print();
            restore();
        }

        function saveCertificate() {
            // Backup editor content and border
            const editorContent = quill.root.innerHTML;
            const editorContainer = document.getElementById("editor-container");
            const oldBorder = editorContainer.style.border;

            // Set content & remove border for PDF
            editorContainer.innerHTML = editorContent;
            editorContainer.style.border = "none";

            const element = document.querySelector(".medical-certificate-container");

            const opt = {
                margin: 10,
                filename: 'Medical_Certificate.pdf',
                image: {
                    type: 'jpeg',
                    quality: 0.98
                },
                html2canvas: {
                    scale: 2,
                    useCORS: true
                },
                jsPDF: {
                    unit: 'mm',
                    format: 'a4',
                    orientation: 'portrait'
                }
            };

            html2pdf().from(element).set(opt).save().then(() => {
                // Restore Quill and border after PDF
                editorContainer.innerHTML = "";
                quill.root.innerHTML = editorContent;
                editorContainer.style.border = oldBorder;
            }).catch((err) => {
                console.error('PDF generation error:', err);
                // Restore on failure
                editorContainer.innerHTML = "";
                quill.root.innerHTML = editorContent;
                editorContainer.style.border = oldBorder;
            });
        }


        async function saveCertificate2() {
            const restore = prepareForPrintOrSave();
            const element = document.querySelector(".medical-certificate-container");

            if (!element) {
                restore();
                alert('Certificate container not found.');
                return;
            }

            const targetUserId = document.getElementById('target-user-id').value;
            const childId = document.getElementById('child-id').value;
            const adminId = <?php echo $admin_id ?>;

            console.log('Generating and Uploading PDF...');

            try {
                const opt = {
                    margin: 10,
                    filename: 'Medical_Certificate.pdf',
                    image: {
                        type: 'jpeg',
                        quality: 0.98
                    },
                    html2canvas: {
                        scale: 2,
                        useCORS: true
                    },
                    jsPDF: {
                        unit: 'mm',
                        format: 'a4',
                        orientation: 'portrait'
                    }
                };

                // 1. Generate PDF as blob
                const pdfBlob = await html2pdf().from(element).set(opt).outputPdf('blob');

                // 2. Create FormData and append the file
                const formData = new FormData();
                formData.append('user_id', targetUserId);
                formData.append('child_id', childId);
                formData.append('admin_id', adminId);
                formData.append('file_name', pdfBlob, 'Medical_Certificate.pdf');

                // 3. Upload to server
                const response = await fetch('log_certificate.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                restore();

                if (result.success) {
                    console.log('The certificate has been saved and logged successfully.');
                } else {
                    console.log('Upload failed: ' + result.message);
                }
            } catch (err) {
                restore();
                console.log('Failed to save certificate: ' + err.message);
                console.error('Error:', err);
            }
        }


        async function sendViaEmail() {

            console.log('sendViaEmail called');
            const targetUserId = document.getElementById('target-user-id').value;
            const childId = document.getElementById('child-id').value;
            const csrfToken = document.getElementById('csrf-token').value;
            const urlParams = new URLSearchParams(window.location.search);
            const documentId = urlParams.get('document_id');

            if (!targetUserId || !csrfToken) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Input',
                    text: 'Invalid user ID or security token.'
                });
                return;
            }

            // Collect content
            const patientName = document.querySelector('[contenteditable="true"] .underline')?.textContent || '';
            const patientAge = document.querySelector('[contenteditable="true"] .age')?.textContent || '';
            const patientGender = document.querySelector('[contenteditable="true"] .gender')?.textContent || '';
            const purpose = document.querySelector('[contenteditable="true"] strong')?.textContent || 'college admission';
            const dateIssued = document.querySelector('[contenteditable="true"] .day')?.textContent || '';
            const monthIssued = document.querySelector('[contenteditable="true"] .month')?.textContent || '';
            const yearIssued = document.querySelector('[contenteditable="true"] .year')?.textContent || '';

            const doctorName = document.querySelector('[contenteditable="true"] .doctor-name')?.textContent || '';
            const doctorTitle = document.querySelector('[contenteditable="true"] .doctor-title')?.textContent || '';
            const doctorLicense = document.querySelector('[contenteditable="true"] .doctor-license')?.textContent || '';
            const doctorPtr = document.querySelector('[contenteditable="true"] .doctor-ptr')?.textContent || '';

            const additionalNotes = quill.root.innerHTML;

            const certificateHtml = `
    <style>
        body { font-family: times; font-size: 12pt; line-height: 1.5; }
        .text-center { text-align: center; }
        .text-bold { font-weight: bold; }
        .underline { border-bottom: 1px solid #000; }
        .small-underline { border-bottom: 1px solid #000; }
        .signature { margin-top: 15mm; text-align: right; }
        .signature-name { font-weight: bold; text-decoration: underline; }
        .signature-title { font-size: 10pt; }
    </style>

    <div style="text-align: justify;">
        <p>This is to certify that <span class="underline">${patientName}</span>,
        a <span class="small-underline">${patientAge}</span>-year-old
        <span class="small-underline">${patientGender.toLowerCase()}</span>,
        has been clinically assessed by the University Health Services Center and was deemed
        <strong>${purpose}</strong>.</p>
        
        <p>Chest radiography and laboratory test results were reviewed.
        He/She has no unstable comorbid illnesses nor any maintenance medications.
        Hence, there are no contraindications for school-related activities.</p>
        
        <p>This certification is issued upon request of
        <span class="underline">${patientName}</span>
        for whatever purpose it may serve him/her best.</p>
    </div>

    ${additionalNotes ? `<div id="editor-container">${additionalNotes}</div>` : ''}

    <div class="signature">
        <p>Given this ${dateIssued} day of ${monthIssued}, ${yearIssued} in the City of Zamboanga, Philippines.</p>
        
        <div style="margin-top: 10mm;">
            <p class="signature-name">${doctorName}</p>
            <p class="signature-title">${doctorTitle}</p>
            <p class="signature-title">${doctorLicense}</p>
            <p class="signature-title">${doctorPtr}</p>
        </div>
    </div>`;

            if (!certificateHtml || certificateHtml.length === 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Empty Certificate',
                    text: 'Certificate content is empty.'
                });
                return;
            }

            const formData = new FormData();
            formData.append('target_user_id', targetUserId);
            formData.append('child_id', childId);
            formData.append('html', certificateHtml);
            formData.append('csrf_token', csrfToken);
            formData.append('patient_name', patientName);
            formData.append('patient_age', patientAge);
            formData.append('patient_gender', patientGender);
            formData.append('purpose', purpose);
            formData.append('date_issued', `${dateIssued} day of ${monthIssued}, ${yearIssued}`);
            formData.append('document_id', documentId)

            Swal.fire({
                title: 'Sending Certificate...',
                text: 'Please wait while the email is being sent.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            try {
                const response = await fetch('send_certificate.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'include'
                });

                const rawResponse = await response.text();
                console.log('Raw response:', rawResponse);

                if (!response.ok) {
                    throw new Error(`HTTP error ${response.status}`);
                }

                const data = JSON.parse(rawResponse);

                if (data.success) {
                    saveCertificate2();
                    Swal.fire({
                        icon: 'success',
                        title: 'Email Sent',
                        text: 'The certificate was successfully emailed.',
                        confirmButtonText: 'OK'
                    }).then(result => {
                        if (result.isConfirmed) {
                            window.location.href = data.redirect || 'medical-documents.php';
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Sending Failed',
                        text: data.message || 'Failed to send email.'
                    });
                }
            } catch (error) {
                console.error('Fetch error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: `Failed to send email: ${error.message}`
                });
            }
        }
    </script>
</body>

</html>