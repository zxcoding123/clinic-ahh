
<?php
ob_start();
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php';

use Dotenv\Dotenv;


// Disable error display, log errors
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . 'error_log.txt');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Response function


function createUserNotification($conn, $userId, $type, $title, $description, $link = '#')
{
    try {
        $stmt = $conn->prepare("
            INSERT INTO user_notifications 
            (user_id, type, title, description, link, status, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, 'unread', NOW(), NOW())
        ");

        if (!$stmt) {
            error_log("Notification prepare failed: " . $conn->error);
            return false;
        }

        $stmt->bind_param("issss", $userId, $type, $title, $description, $link);
        if (!$stmt->execute()) {
            error_log("Notification execute failed: " . $stmt->error);
            return false;
        }

        $stmt->close();
        return true;
    } catch (Exception $e) {
        error_log("Notification error: " . $e->getMessage());
        return false;
    }
}

function formatDate($dateString)
{
    if (empty($dateString)) {
        $date = new DateTime();
    } else {
        try {
            $date = new DateTime($dateString);
        } catch (Exception $e) {
            $date = new DateTime();
        }
    }

    $day = $date->format('jS');
    $monthYear = $date->format('F, Y');

    return $day . ' day of ' . $monthYear;
}


function sendResponse($success, $message, $redirect = null)
{
    $output = ob_get_contents();
    if ($output) {
        error_log("send_certificate.php: Unexpected output: " . $output);
    }
    ob_end_clean();
    $response = ['success' => $success, 'message' => $message];
    if ($redirect) {
        $response['redirect'] = $redirect;
    }
    error_log("send_certificate.php: Response: " . json_encode($response));
    echo json_encode($response);
    exit;
}

// Log request
error_log("send_certificate.php: Request - Method: {$_SERVER['REQUEST_METHOD']}, IP: {$_SERVER['REMOTE_ADDR']}");
error_log("send_certificate.php: POST: " . print_r($_POST, true));
error_log("send_certificate.php: Session: " . print_r($_SESSION ?? [], true));

// Validate AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    error_log("send_certificate.php: Not AJAX");
    sendResponse(false, 'Invalid request: AJAX required.');
}

// Validate method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("send_certificate.php: Invalid method: {$_SERVER['REQUEST_METHOD']}");
    sendResponse(false, 'Invalid request method.');
}

// Check session
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    error_log("send_certificate.php: Session user_id not set");
    sendResponse(false, 'Session expired. Please log in.', 'login.php');
}

$admin_id = intval($_SESSION['user_id']);
$target_user_id = isset($_POST['target_user_id']) ? intval($_POST['target_user_id']) : 0;
$child_id = isset($_POST['child_id']) ? intval($_POST['child_id']) : 0;
$html_content = isset($_POST['html']) ? trim($_POST['html']) : '';
$csrf_token = isset($_POST['csrf_token']) ? trim($_POST['csrf_token']) : '';

error_log("send_certificate.php: Parsed: admin_id=$admin_id, target_user_id=$target_user_id, child_id=$child_id, html_length=" . strlen($html_content) . ", csrf_token=" . ($csrf_token ? 'present' : 'missing'));

// Validate inputs
if (!$target_user_id || !$html_content || !$csrf_token) {
    error_log("send_certificate.php: Missing required fields");
    sendResponse(false, 'Missing required fields.');
}

// Check database connection
if (!$conn) {
    error_log("send_certificate.php: Database connection failed");
    sendResponse(false, 'Database error.');
}

// Verify admin
try {
    $stmt = $conn->prepare("SELECT user_type FROM users WHERE id = ?");
    if (!$stmt) {
        error_log("send_certificate.php: Prepare admin check failed: " . $conn->error);
        sendResponse(false, 'Database error.');
    }
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin_type = $result->num_rows > 0 ? $result->fetch_assoc()['user_type'] : null;
    $stmt->close();

    if (!in_array($admin_type, ['Super Admin', 'Medical Admin', 'Dental Admin'])) {
        error_log("send_certificate.php: User_id=$admin_id not admin, type=$admin_type");
        sendResponse(false, 'Unauthorized.');
    }
} catch (Exception $e) {
    error_log("send_certificate.php: Admin check error: " . $e->getMessage());
    sendResponse(false, 'Server error.');
}

// Validate CSRF
try {
    $stmt = $conn->prepare("SELECT token FROM csrf_tokens WHERE user_id = ? AND created_at > NOW() - INTERVAL 24 HOUR");
    if (!$stmt) {
        error_log("send_certificate.php: Prepare CSRF check failed: " . $conn->error);
        sendResponse(false, 'Database error.');
    }
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0 || $result->fetch_assoc()['token'] !== $csrf_token) {
        error_log("send_certificate.php: Invalid CSRF token for admin_id=$admin_id");
        sendResponse(false, 'Invalid security token.');
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("send_certificate.php: CSRF validation error: " . $e->getMessage());
    sendResponse(false, 'Server error.');
}

// Load .env
try {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
} catch (Exception $e) {
    error_log("send_certificate.php: Failed to load .env: " . $e->getMessage());
    sendResponse(false, 'Server configuration error.');
}

// Check SMTP
if (empty($_ENV['SMTP_USER']) || empty($_ENV['SMTP_PASS'])) {
    error_log("send_certificate.php: Missing SMTP credentials");
    sendResponse(false, 'Email configuration missing.');
}

$document_id = isset($_POST['document_id']) ? intval($_POST['document_id']) : 0;

$sql = "UPDATE medical_documents SET status='approved' WHERE id=? ";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $document_id);
$stmt->execute();



// Fetch email
try {
    $email = '';
    $target_user_type = '';
    if ($child_id > 0) {
        $stmt = $conn->prepare("
            SELECT u.email 
            FROM users u 
            JOIN patients p ON u.id = p.user_id 
            JOIN children c ON c.parent_id = p.id 
            WHERE c.id = ? AND p.user_id = ?
        ");
        if (!$stmt) {
            error_log("send_certificate.php: Child query failed: " . $conn->error);
            sendResponse(false, 'Database error.');
        }
        $stmt->bind_param("ii", $child_id, $target_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $email = $result->fetch_assoc()['email'];
            $target_user_type = 'Child';
        } else {
            error_log("send_certificate.php: Child_id=$child_id not found for user_id=$target_user_id");
            sendResponse(false, 'Child not found.');
        }
        $stmt->close();
    } else {
        $stmt = $conn->prepare("SELECT email, user_type FROM users WHERE id = ?");
        if (!$stmt) {
            error_log("send_certificate.php: User query failed: " . $conn->error);
            sendResponse(false, 'Database error.');
        }
        $stmt->bind_param("i", $target_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $email = $row['email'];
            $target_user_type = $row['user_type'];
        } else {
            error_log("send_certificate.php: User_id=$target_user_id not found");
            sendResponse(false, 'User not found.');
        }
        $stmt->close();
    }

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log("send_certificate.php: Invalid email for user_id=$target_user_id: " . ($email ?? 'null'));
        sendResponse(false, 'Invalid email address.');
    }
    error_log("send_certificate.php: Using email: $email");
} catch (Exception $e) {
    error_log("send_certificate.php: Email fetch error: " . $e->getMessage());
    sendResponse(false, 'Server error.');
}
// Generate PDF
try {
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

    // Add watermark (must be an image file)
    $pdf->SetAlpha(0.2); // Set transparency (0-1)
    $pdf->Image('images/clinic.png', 50, 80, 100, '', '', '', '', false, 300, 'C');
    $pdf->SetAlpha(1); // Reset transparency

    $pdf->SetCreator('WMSU Health Services');
    $pdf->SetAuthor('WMSU Health Services');
    $pdf->SetTitle('Medical Certificate');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(20, 15, 20);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->SetFont('times', '', 12);
    $pdf->AddPage();

    // Extract the body content from the submitted HTML
    $dom = new DOMDocument();
    @$dom->loadHTML($html_content);

    // Get the body content (excluding header)
    $bodyContent = '';
    $contentDivs = $dom->getElementsByTagName('div');
    foreach ($contentDivs as $div) {
        if ($div->getAttribute('class') === 'content') {
            $bodyContent = $dom->saveHTML($div);
            break;
        }
    }

    // If we couldn't find it by class, try to get everything between header and signature
    if (empty($bodyContent)) {
        $bodyContent = $html_content;
        // Remove header if present
        $bodyContent = preg_replace('/<div class="header"[^>]*>.*?<\/div>/is', '', $bodyContent);
        // Remove signature if present
        $bodyContent = preg_replace('/<div class="doctor-info"[^>]*>.*?<\/div>/is', '', $bodyContent);
    }

    // Build the certificate with fixed header and footer, but dynamic body
    $content = '
    <style>
        .watermark {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-image: url("images/clinic.png");
        background-size: contain;
        background-position: center;
        background-repeat: no-repeat;
        opacity: 0.5;
        z-index: 1;
    }

        body { font-family: times; font-size: 12pt; line-height: 1.5; }
        .header-container { 
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15mm;
        }
        .logo { 
            width: 60px; 
            height: auto;
        }
        .university-info { 
            text-align: center;
            flex-grow: 1;
            padding: 0 10mm;
        }
        .text-bold { 
            font-weight: bold; 
            margin: 1mm 0;
            font-size: 14pt;
        }
        .university-info p { 
            font-size: 10pt; 
            margin: 1mm 0;
        }
        .content { 
            text-align: justify; 
        }
        .salutation { 
            margin-bottom: 10mm !important;
            text-align: left; 
            font-weight: bold; 
            margin-bottom: 5mm !important;
        }
        .signature { 
            margin-top: 15mm; 
            text-align: right; 
        }
        .signature p { 
            margin: 1mm 0; 
        }
        .signature-name { 
            font-weight: bold; 
            text-decoration: underline; 
        }
        .signature-title { 
            font-size: 10pt; 
        }
    </style>
<div class="watermark"></div>
    <table width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td width="20%" align="left">
                <img src="images/23.png" class="logo">
            </td>
            <td width="65%" align="center" class="university-info">
                <h6 class="text-bold">WESTERN MINDANAO STATE UNIVERSITY</h6>
                <h6 class="text-bold">ZAMBOANGA CITY</h6>
                <h6 class="text-bold">UNIVERSITY HEALTH SERVICES CENTER</h6>
                <h6 style="font-size:10pt;">Tel. no. (062) 991-6736 / Email: healthservices@wmsu.edu.ph</h6>
            </td>
            <td width="20%" align="right">
                <img src="images/clinic.png" class="logo">
            </td>
        </tr>
    </table>

    <br> <br>
    <div class="salutation">To whom it may concern:</div>';

    // Add the dynamic body content
    $content .= $bodyContent;

    $pdf->writeHTML($content, true, false, true, false, '');
    $pdf_output = $pdf->Output('', 'S');
    error_log("send_certificate.php: PDF generated, size=" . strlen($pdf_output));
} catch (Exception $e) {
    error_log("send_certificate.php: PDF generation failed: " . $e->getMessage());
    sendResponse(false, 'Failed to generate PDF.');
}
// Save PDF
try {
    $upload_dir = __DIR__ . '/Uploads/medical_documents/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            error_log("send_certificate.php: Failed to create directory: $upload_dir");
            sendResponse(false, 'Unable to create upload directory.');
        }
    }
    $file_name = 'medical_certificate_' . $target_user_id . '_' . ($child_id ?: '0') . '_' . time() . '.pdf';
    $file_path = $upload_dir . $file_name;
    if (!file_put_contents($file_path, $pdf_output)) {
        error_log("send_certificate.php: Failed to save PDF to $file_path");
        sendResponse(false, 'Unable to save PDF.');
    }
    error_log("send_certificate.php: PDF saved to $file_path");
} catch (Exception $e) {
    error_log("send_certificate.php: PDF save error: " . $e->getMessage());
    sendResponse(false, 'Server error.');
}

// Send email
try {
    $subject = 'Your Medical Certificate';
    $body = 'Dear User,<br><br>Please find your medical certificate attached.<br><br>Best regards,<br>WMSU Health Services';
    if (!send_email($email, $subject, $body, $pdf_output, 'Medical_Certificate.pdf')) {
        error_log("send_certificate.php: Failed to send email to $email");
        sendResponse(false, 'Failed to send email.');
    }
    error_log("send_certificate.php: Email sent successfully to $email");
} catch (Exception $e) {
    error_log("send_certificate.php: Email sending error: " . $e->getMessage());
    sendResponse(false, 'Failed to send email.');
}

// New CSRF token
try {
    $new_csrf_token = bin2hex(random_bytes(32));
    $stmt = $conn->prepare("
        INSERT INTO csrf_tokens (user_id, token, created_at) 
        VALUES (?, ?, NOW()) 
        ON DUPLICATE KEY UPDATE token = ?, created_at = NOW()
    ");
    if (!$stmt) {
        error_log("send_certificate.php: Prepare CSRF update failed: " . $conn->error);
        sendResponse(false, 'Database error.');
    }
    $stmt->bind_param("iss", $admin_id, $new_csrf_token, $new_csrf_token);
    if (!$stmt->execute()) {
        error_log("send_certificate.php: CSRF token update failed: " . $stmt->error);
    } else {
        error_log("send_certificate.php: New CSRF token generated for admin_id=$admin_id");
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("send_certificate.php: CSRF token error: " . $e->getMessage());
}

// Redirect
$redirect_tab = ($target_user_type === 'Incoming Freshman') ? 'freshmen-issued' : 'med-cert-issued';
$redirect_url = "medical-documents.php?tab=$redirect_tab";

// After successful email sending, add this:
$notificationTitle = "Medical Certificate Issued";
$notificationDesc = "Your medical certificate has been issued and sent to your email";

// Create notification for the target user
$notificationSent = createUserNotification(
    $conn,
    $target_user_id,
    'medical_certificate',
    $notificationTitle,
    $notificationDesc,
    '#' // Link to view certificates
);

if (!$notificationSent) {
    error_log("send_certificate.php: Failed to create notification for user $target_user_id");
    // Don't fail the whole process just because notification failed
}

// If this is for a child, also notify the parent
if ($child_id > 0) {
    $parentNotificationSent = createUserNotification(
        $conn,
        $target_user_id, // This is the parent's user_id in this case
        'child_medical_certificate',
        "Medical Certificate Issued for Your Child",
        "A medical certificate has been issued for your child and sent to your email",
        'children.php' // Link to children management
    );

    if (!$parentNotificationSent) {
        error_log("send_certificate.php: Failed to create parent notification for child $child_id");
    }
}



// Then continue with your existing success response
error_log("send_certificate.php: Success: Email sent to $email for target_user_id=$target_user_id, redirect=$redirect_url");
sendResponse(true, 'Email sent successfully.', $redirect_url);

error_log("send_certificate.php: Success: Email sent to $email for target_user_id=$target_user_id, redirect=$redirect_url");
sendResponse(true, 'Email sent successfully.', $redirect_url);
?>