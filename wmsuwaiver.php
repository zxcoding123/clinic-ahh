<?php
session_start();
require_once 'config.php';

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/php_errors.log');

// Initialize variables
$user_id = $_SESSION['user_id'] ?? null;
$user_type = $_SESSION['user_type'] ?? null;
$full_name = '';
$error = '';
$show_form = true;

// Check if user is logged in and fetch user details
if (!$user_id) {
    error_log('Unauthorized access attempt: No user_id in session');
    header('Location: index.php');
    exit;
}

// Fetch user details
try {
    $query = "SELECT user_type, first_name, middle_name, last_name FROM users WHERE id = ?";
    if (!$stmt = $conn->prepare($query)) {
        error_log("Prepare failed for user query: " . $conn->error);
        $error = "Database error: Unable to prepare user query.";
        $show_form = false;
    } else {
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        if (!$stmt->execute()) {
            error_log("Execute failed for user query: " . $stmt->error);
            $error = "Database error: Unable to execute user query.";
            $show_form = false;
        } else {
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $user_type = $user['user_type'] ?? '';
            if (empty($user_type)) {
                error_log("No user found for user_id: $user_id");
                header('Location: index.php');
                exit;
            }
            // Restrict access to non-admin users
            if (in_array($user_type, ['Super Admin', 'Medical Admin', 'Dental Admin'])) {
                error_log("Admin user attempted to access waiver form: user_id $user_id");
                header('Location: index.php');
                exit;
            }
            // Construct full name
            $full_name = trim(
                $user['last_name'] . ', ' . 
                $user['first_name'] . ' ' .
                ($user['middle_name'] ? $user['middle_name'] : '')
            );

            $new_full_name = trim($user['first_name'] . ', ' . 
                $user['last_name']
              );
        }
        mysqli_stmt_close($stmt);
    }
} catch (Exception $e) {
    error_log("User query error: " . $e->getMessage());
    $error = "Error fetching user data.";
    $show_form = false;
}

// Check if user has already submitted a waiver
if (!$error && $show_form) {
    try {
        $query = "SELECT id FROM waivers WHERE user_id = ?";
        if (!$stmt = $conn->prepare($query)) {
            error_log("Prepare failed for waiver check: " . $conn->error);
            $error = "Database error: Unable to prepare waiver check query.";
            $show_form = false;
        } else {
            mysqli_stmt_bind_param($stmt, 'i', $user_id);
            if (!$stmt->execute()) {
                error_log("Execute failed for waiver check: " . $stmt->error);
                $error = "Database error: Unable to execute waiver check query.";
                $show_form = false;
            } else {
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $error = "You have already submitted a waiver.";
                    $show_form = false;
                }
            }
            mysqli_stmt_close($stmt);
        }
    } catch (Exception $e) {
        error_log("Waiver check error: " . $e->getMessage());
        $error = "Error checking waiver status.";
        $show_form = false;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $show_form && !$error) {
    $date_signed = '2025-05-14'; // Hardcoded date
    $signature = $_POST['signature'] ?? '';

    // Validate inputs
    if (empty($full_name)) {
        $error = "Full name could not be retrieved.";
    } elseif (empty($signature)) {
        $error = "Signature is required.";
    }

    // Handle signature upload
    $signature_path = '';
    if (!$error) {
        $signature = str_replace('data:image/png;base64,', '', $signature);
        $signature = str_replace(' ', '+', $signature);
        $signature_data = base64_decode($signature);

        $upload_dir = 'Uploads/signatures/';
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                error_log("Failed to create directory: $upload_dir");
                $error = "Server error: Unable to create signature directory.";
            }
        }
        if (!$error && !is_writable($upload_dir)) {
            error_log("Directory not writable: $upload_dir");
            $error = "Server error: Signature directory is not writable.";
        }

        if (!$error) {
            $signature_filename = 'signature_' . $user_id . '_' . time() . '.png';
            $signature_path = $upload_dir . $signature_filename;

            if (!file_put_contents($signature_path, $signature_data)) {
                error_log("Failed to save signature to: $signature_path");
                $error = "Failed to save signature.";
            } else {
                $signature_path = 'Uploads/signatures/' . $signature_filename;
            }
        }
    }

    // Insert waiver into database
    if (!$error) {
        try {
            $query = "INSERT INTO waivers (user_id, full_name, signature_path, date_signed) VALUES (?, ?, ?, ?)";
            if (!$stmt = $conn->prepare($query)) {
                error_log("Prepare failed for waiver insert: " . $conn->error);
                $error = "Database error: Unable to prepare waiver insert query.";
            } else {
                mysqli_stmt_bind_param($stmt, 'isss', $user_id, $full_name, $signature_path, $date_signed);
                if (!$stmt->execute()) {
                    error_log("Execute failed for waiver insert: " . $stmt->error);
                    $error = "Database error: Unable to execute waiver insert query.";
                } else {

                    
                // ✅ Send notifications to Super Admin, Medical Admin, and Dental Admin
                $adminQuery = $conn->prepare("
                    SELECT id 
                    FROM users 
                    WHERE user_type IN ('Super Admin', 'Medical Admin', 'Dental Admin')
                ");
                $adminQuery->execute();
                $adminResult = $adminQuery->get_result();

                $notificationTitle = "New Waiver Submission";
                $notificationDescription = "$new_full_name has signed a waiver.";
                $notificationLink = "#"; // Change to actual waiver view link
                $notificationType = "waiver_submission";

                $notificationStmt = $conn->prepare("
                    INSERT INTO notifications_admin (
                        user_id, type, title, description, link, status, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, 'unread', NOW(), NOW())
                ");

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

                $notificationStmt->close();
                $adminQuery->close();


                    mysqli_stmt_close($stmt);
                    header('Location: upload.php');
                    exit;
                }
                mysqli_stmt_close($stmt);
            }
        } catch (Exception $e) {
            error_log("Waiver insert error: " . $e->getMessage());
            $error = "Error saving waiver.";
        }
    }
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waiver for Collection of Personal and Sensitive Health Information</title>
    <link rel="stylesheet" href="css/wmsuwaiver.css">
      <link rel="apple-touch-icon" sizes="180x180" href="images/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="images/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="images/favicon-16x16.png">
    <style>
        .error { 
            color: red; 
            margin: 10px 0; 
            text-align: center;
        }
        .back-button { 
            display: inline-block; 
            margin: 10px; 
            padding: 10px 20px; 
            background-color: #f0f0f0; 
            color: #333; 
            text-decoration: none; 
            border-radius: 4px; 
            font-size: 16px;
        }
        .back-button:hover { 
            background-color: #e0e0e0; 
            text-decoration: none; 
        }
        .signature-line p { 
            margin: 0; 
            font-weight: bold; 
        }
        .signature-canvas {
            border: 1px solid #ccc;
            background-color: #fff;
            width: 100%;
            max-width: 400px;
            height: 100px;
            touch-action: none; /* Prevent scrolling when drawing */
        }
        .print-button, .submit-button, .clear-button {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin: 5px;
            min-width: 100px; /* Ensure buttons are tappable on mobile */
        }
        .print-button {
            background-color: #4CAF50;
            color: white;
        }
        .print-button:hover {
            background-color: #45a049;
        }
        .submit-button {
            background-color: #008CBA;
            color: white;
        }
        .submit-button:hover {
            background-color: #007399;
        }
        .clear-button {
            background-color: #f44336;
            color: white;
        }
        .clear-button:hover {
            background-color: #d32f2f;
        }
        .buttons {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
            flex-wrap: nowrap; /* Keep buttons in one row */
        }
        .page {
            position: relative;
            min-height: 100vh; /* Ensure page takes full height */
            display: flex;
            flex-direction: column;
        }
        .content {
            flex: 1; /* Allow content to take available space */
        }
        .footer-container {
            margin-top: auto; /* Push to bottom */
            padding-bottom: 20px;
        }
        .footer-text {
            margin: 0;
            text-align: left; /* Align footer text to the left */
        }
        .footer-text p {
            margin: 2px 0;
        }
        @media print {
            .signature-canvas {
                border: none;
            }
            .back-button, .buttons {
                display: none;
            }
            .signature-wrapper {
                position: relative;
            }
            .signature-wrapper::after {
                content: '';
                position: absolute;
                bottom: -5px;
                left: 0;
                width: 100%;
                height: 1px;
                background-color: #000;
            }
            .footer-container {
                position: absolute;
                bottom: 0;
                width: 100%;
            }
            .footer-text {
                text-align: left; /* Keep left-aligned for print */
            }
        }
        @media (max-width: 768px) {
            .signature-canvas {
                max-width: 100%;
                height: 80px;
            }
            .buttons {
                flex-direction: row; /* Keep buttons in a row on mobile */
                align-items: center;
                justify-content: center;
                gap: 8px;
                flex-wrap: nowrap; /* Ensure buttons stay in one row */
            }
            .print-button, .submit-button, .clear-button {
                font-size: 14px; /* Slightly smaller font for mobile */
                padding: 8px 16px;
                min-width: 80px;
            }
            .footer-container {
                padding-bottom: 10px;
            }
            .footer-text {
                text-align: left; /* Keep left-aligned on mobile */
            }
        }
    </style>
</head>
<body>
    <!-- Back Button -->
    <a href="homepage.php" class="back-button">Back</a>

    <!-- Page 1 -->
    <div class="page">
        <div class="header-container">
            <div class="logos">
                <img src="images/clinic.png" alt="Clinic Logo" class="logo">
                <img src="images/Western_Mindanao_State_University.png" alt="WMSU Logo" class="logo">
            </div>
            <div class="header-text">
                <p>WESTERN MINDANAO STATE UNIVERSITY</p>
                <p>ZAMBOANGA CITY</p>
                <p>UNIVERSITY HEALTH SERVICES CENTER</p>
                <p>Tel. no. (062) 991-6736 / Email: healthservices@wmsu.edu.ph</p>
            </div>
            <div class="header-image">
                <img src="images/ISO.png" alt="ISO Certification" class="iso-logo">
            </div>
        </div>
        <div class="content">
            <h1>Waiver for Collection of Personal and Sensitive Health Information</h1>
            <p>In consideration of Western Mindanao State University Health Services Center's collecting and processing of personal and sensitive health information, the undersigned individual, hereby agree to the following terms and conditions:</p>
            <p><strong>Consent:</strong> By signing this waiver, the individual gives explicit consent to the University Health Services Center to collect, use, store, and process their personal and sensitive health information, as described in this document.</p>
            <p><strong>Purpose of Collection:</strong> The University Health Services Center collects personal and sensitive health information solely for the purpose of promoting and maintaining the health and general well-being of the school community.</p>
            <p><strong>Types of Information:</strong> The personal and sensitive health information that may be collected includes, but is not limited to, the following:</p>
            <ul>
                <li>Personal details: Name, date of birth, gender, address, contact information</li>
                <li>Health-related information: Medical history, current health conditions, medications, allergies, vaccination history, diagnostic reports, and test results.</li>
                <li>Other sensitive information: Information about mental health, religious beliefs, or other similar data</li>
            </ul>
            <p><strong>Collection Methods:</strong> The University Health Services Center may collect personal and sensitive health information through various means, including but not limited to face-to-face interactions, written forms, and electronic/online forms.</p>
            <p><strong>Data Storage and Security:</strong> The University Health Services Center will implement reasonable technical and organizational measures to protect the personal and sensitive health information from unauthorized access, disclosure, alteration, or destruction. However, it cannot guarantee absolute security and shall not be liable for any security breaches, provided that University Health Services Center, acting as the personal information controller, promptly notifies the National Privacy Commission and the affected data subject.</p>
            <p><strong>Data Sharing:</strong> The University Health Services Center may forward the personal and sensitive health information to authorized personnel or entities, including fellow healthcare providers, insurers, research institutions, or government authorities, as required or permitted by applicable laws and regulations provided that the University Health Services Center will inform the data subject that the personal and sensitive health information will be forwarded to another entity or institution. The University Health Services Center shall be responsible that proper safeguards are in place to ensure the confidentiality of the personal information</p>
        </div>
        <br> <br> <br>
        <div class="footer">
            <div class="footer-text">
                <p>Page 1 of 2</p>
                <p>WMSU-UHSC-FR-006</p>
                <p>Effective Date: 31-May-2023</p>
            </div>
        </div>
    </div>

    <!-- Page 2 -->
    <div class="page">
        <div class="header-container">
            <div class="logos">
                <img src="images/clinic.png" alt="Clinic Logo" class="logo">
                <img src="images/Western_Mindanao_State_University.png" alt="WMSU Logo" class="logo">
            </div>
            <div class="header-text">
                <p>WESTERN MINDANAO STATE UNIVERSITY</p>
                <p>ZAMBOANGA CITY</p>
                <p>UNIVERSITY HEALTH SERVICES CENTER</p>
                <p>Tel. no. (062) 991-6736 / Email: healthservices@wmsu.edu.ph</p>
            </div>
            <div class="header-image">
                <img src="images/ISO.png" alt="ISO Certification" class="iso-logo">
            </div>
        </div>
        <div class="content">
            <p>processed, prevent its use for unauthorized purposes, and generally comply with the Data Privacy Act of 2012 (Republic Act 10173).</p>
            <p><strong>Data Retention:</strong> The University Health Services Center will retain the personal and sensitive health information for as long as necessary to fulfill the purposes stated in this waiver or as required by law. After the retention period, it will securely dispose of or anonymize the data.</p>
            <p><strong>Rights of the Individual:</strong></p>
            <ol type="a">
                <li><strong>Access:</strong> The Individual has the right to request access to their personal and sensitive health information held by the Organization.</li>
                <li><strong>Correction:</strong> The Individual may request corrections or updates to their personal and sensitive health information if it is inaccurate or incomplete.</li>
                <li><strong>Withdrawal of Consent:</strong> The Individual may withdraw their consent for the collection and processing of their personal and sensitive health information. However, such withdrawal may affect the quality of certain health services.</li>
                <li><strong>All other rights</strong> included in the Data Privacy Act of 2012 (Republic Act 10173).</li>
            </ol>
            <p><strong>Legal Compliance:</strong> The University Health Services Center will comply with applicable laws and regulations regarding the collection, use, and disclosure of personal and sensitive health information, including but not limited to data protection and privacy laws, particularly the Data Privacy Act of 2012 (Republic Act 10173).</p>
            <p>The Individual agrees to hold harmless the University Health Services Center, its officers and employees, from any claims, damages, or liabilities arising out of or related to the collection, use, or disclosure of their personal and sensitive health information, except in cases of gross negligence or willful misconduct.</p>
            <p>By signing below, the individual acknowledges that they have read and understood the terms and conditions of this waiver, and voluntarily agree to the collection and processing of their personal and sensitive health information by the University Health Services Center.</p>

            <?php if ($error): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
                <?php if (strpos($error, 'already submitted') !== false): ?>
                    <a href="profile.php">Back to Profile</a>
                    <a href="upload.php">Proceed to Upload Documents</a>
                <?php else: ?>
                    <a href="index.php">Login</a>
                <?php endif; ?>
            <?php elseif ($show_form): ?>
                <form id="waiverForm" action="" method="POST">
                    <div class="signature">
                        <div class="signature-line">
                            <p><?php echo htmlspecialchars($full_name); ?></p>
                            <p>Individual's Full Name</p>
                        </div>
                        <div class="signature-line">
                            <div class="signature-wrapper">
                                <canvas id="signatureCanvas" class="signature-canvas"></canvas>
                                <button type="button" onclick="clearCanvas()" class="clear-button">Clear</button>
                                <input type="hidden" name="signature" id="signatureData">
                            </div>
                            <p>Individual's Signature</p>
                        </div>
                        <div class="signature-line">
                           <p><?php echo date('Y-m-d'); ?></p>
                            <p>Date Signed</p>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
        <div class="footer-container">
            <?php if ($show_form && !$error): ?>
                <div class="buttons">
                    <button type="button" onclick="handlePrint()" class="print-button">Print</button>
                    <button type="submit" form="waiverForm" class="submit-button">Submit</button>
                </div>
            <?php endif; ?>
            <div class="footer-text">
                <p>Page 2 of 2</p>
                <p>WMSU-UHSC-FR-006</p>
                <p>Effective Date: 31-May-2023</p>
            </div>
        </div>
    </div>

                <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('signatureCanvas')) {
        initializeSignaturePad('signatureCanvas');
    }

    // Handle submit button tap with touchstart for faster response
    const submitButton = document.querySelector('.submit-button');
    if (submitButton) {
        submitButton.addEventListener('click', handleSubmit);
        submitButton.addEventListener('touchstart', handleSubmit, { passive: false });
    }
});

function handleSubmit(e) {
    e.preventDefault(); // Prevent default to ensure control
    e.stopPropagation(); // Prevent canvas interference
    const signatureData = document.getElementById('signatureData');
    const canvas = document.getElementById('signatureCanvas');
    
    // Update signature data
    signatureData.value = canvas.toDataURL('image/png');
    
if (!signatureData.value || window.isCanvasEmpty()) {
    Swal.fire({
        icon: 'warning',
        title: 'Missing Signature',
        text: 'Please provide a signature before submitting.',
        confirmButtonColor: '#d33'
    });
    console.log('Submit blocked: No signature provided');
    return;
}

    console.log('Submitting form with signature data');
    document.getElementById('waiverForm').submit();
}

function initializeSignaturePad(canvasId) {
    const canvas = document.getElementById(canvasId);
    const ctx = canvas.getContext('2d');
    const signatureData = document.getElementById('signatureData');
    let isDrawing = false;
    let lastX = 0;
    let lastY = 0;

    // Handle responsive canvas sizing with device pixel ratio
    function resizeCanvas() {
        const container = canvas.parentElement;
        const dpr = window.devicePixelRatio || 1;
        canvas.width = Math.min(container.offsetWidth, 400) * dpr;
        canvas.height = 100 * dpr;
        canvas.style.width = '100%';
        canvas.style.maxWidth = '400px';
        canvas.style.height = '100px';
        ctx.scale(dpr, dpr);
        ctx.strokeStyle = '#000';
        ctx.lineWidth = 1.5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        console.log('Canvas resized: ', canvas.width, canvas.height);
    }

    // Initial resize
    resizeCanvas();

    // Handle window resize
    window.addEventListener('resize', resizeCanvas);

    // Event listeners for mouse and touch
    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseout', stopDrawing);
    canvas.addEventListener('touchstart', handleTouchStart, { passive: false });
    canvas.addEventListener('touchmove', handleTouchMove, { passive: false });
    canvas.addEventListener('touchend', stopDrawing);
    canvas.addEventListener('touchcancel', stopDrawing);

    function handleTouchStart(e) {
        e.stopPropagation(); // Prevent propagation to avoid interfering with other elements
        const touch = e.touches[0];
        const rect = canvas.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        const scaleX = canvas.width / rect.width / dpr;
        const scaleY = canvas.height / rect.height / dpr;
        const pos = {
            x: (touch.clientX - rect.left) * scaleX,
            y: (touch.clientY - rect.top) * scaleY
        };
        startDrawing({ clientX: touch.clientX, clientY: touch.clientY });
        console.log('Touch start: ', pos);
    }

    function handleTouchMove(e) {
        e.stopPropagation(); // Prevent propagation
        const touch = e.touches[0];
        const rect = canvas.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        const scaleX = canvas.width / rect.width / dpr;
        const scaleY = canvas.height / rect.height / dpr;
        const pos = {
            x: (touch.clientX - rect.left) * scaleX,
            y: (touch.clientY - rect.top) * scaleY
        };
        draw({ clientX: touch.clientX, clientY: touch.clientY });
        console.log('Touch move: ', pos);
    }

    function startDrawing(e) {
        isDrawing = true;
        const pos = getPosition(e);
        lastX = pos.x;
        lastY = pos.y;
        console.log('Start drawing at: ', pos);
    }

    function draw(e) {
        if (!isDrawing) return;
        const pos = getPosition(e);
        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
        lastX = pos.x;
        lastY = pos.y;
        signatureData.value = canvas.toDataURL('image/png');
        console.log('Drawing to: ', pos);
    }

    function stopDrawing() {
        isDrawing = false;
        signatureData.value = canvas.toDataURL('image/png');
        console.log('Drawing stopped');
    }

    function getPosition(e) {
        const rect = canvas.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        const scaleX = canvas.width / rect.width / dpr;
        const scaleY = canvas.height / rect.height / dpr;
        return {
            x: ((e.clientX || (e.touches && e.touches[0].clientX)) - rect.left) * scaleX,
            y: ((e.clientY || (e.touches && e.touches[0].clientY)) - rect.top) * scaleY
        };
    }

    // Check if canvas is empty
    function isCanvasEmpty() {
        const blank = document.createElement('canvas');
        blank.width = canvas.width;
        blank.height = canvas.height;
        return canvas.toDataURL('image/png') === blank.toDataURL('image/png');
    }

    // Expose isCanvasEmpty for form validation
    window.isCanvasEmpty = isCanvasEmpty;
}

function clearCanvas() {
    const canvas = document.getElementById('signatureCanvas');
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    document.getElementById('signatureData').value = '';
    console.log('Canvas cleared');
}

function handlePrint() {
    console.log('Print button clicked');
    window.print();
}
</script>
</body>
</html>