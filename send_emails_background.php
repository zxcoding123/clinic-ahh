<?php
session_start();
require_once 'config.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Only allow this to be called via AJAX
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    die(json_encode(['error' => 'Unauthorized access']));
}

// Initialize or continue email process
if (!isset($_SESSION['email_process'])) {
    $_SESSION['email_process'] = [
        'total' => 0,
        'processed' => 0,
        'success_count' => 0,
        'fail_count' => 0,
        'failed_emails' => [],
        'status' => 'initializing'
    ];
}

// Define user types to process
$user_types = ['Incoming Freshman', 'Highschool', 'Senior High School', 'College', 'Employee', 'Parent'];
$placeholders = implode(',', array_fill(0, count($user_types), '?'));
$types_str = str_repeat('s', count($user_types));

// Get total count if not already done
if ($_SESSION['email_process']['total'] == 0) {
    $total_query = "SELECT COUNT(*) as total FROM users u 
                   JOIN patients p ON u.id = p.user_id
                   WHERE u.user_type IN ($placeholders)";

    $stmt = mysqli_prepare($conn, $total_query);
    mysqli_stmt_bind_param($stmt, $types_str, ...$user_types);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    $_SESSION['email_process']['total'] = $row['total'];
    $_SESSION['email_process']['status'] = 'processing';
    mysqli_stmt_close($stmt);
}

// Process a batch of emails (10 at a time)
if (
    $_SESSION['email_process']['status'] == 'processing' &&
    $_SESSION['email_process']['processed'] < $_SESSION['email_process']['total']
) {

    $batch_size = 10;
    $offset = $_SESSION['email_process']['processed'];

    $query = "SELECT u.id, u.email FROM users u
             JOIN patients p ON u.id = p.user_id
             WHERE  u.user_type IN ($placeholders)
             LIMIT $offset, $batch_size";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, $types_str, ...$user_types);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        if (sendUpdateEmail($row['email'], $row['id'])) {
            $_SESSION['email_process']['success_count']++;
        } else {
            $_SESSION['email_process']['fail_count']++;
            $_SESSION['email_process']['failed_emails'][] = $row['email'];
        }
        $_SESSION['email_process']['processed']++;
    }
    mysqli_stmt_close($stmt);
}

// Check if completed
if ($_SESSION['email_process']['processed'] >= $_SESSION['email_process']['total']) {
    $_SESSION['email_process']['status'] = 'completed';
}

// Return current progress
header('Content-Type: application/json');
echo json_encode([
    'total' => $_SESSION['email_process']['total'],
    'processed' => $_SESSION['email_process']['processed'],
    'success' => $_SESSION['email_process']['success_count'],
    'failed' => $_SESSION['email_process']['fail_count'],
    'status' => $_SESSION['email_process']['status'],
    'progress' => ($_SESSION['email_process']['total'] > 0) ?
        round(($_SESSION['email_process']['processed'] / $_SESSION['email_process']['total']) * 100) : 0
]);

// Email sending function
function sendUpdateEmail($email, $userId)
{
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USER'];
        $mail->Password = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom($_ENV['SMTP_USER'], 'University Health Services');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = "Action Required: Update Your Account";

        // Example link to account update page
        $updateLink = "http://localhost/wmsu/login.php";

        // Email body with styling
        $body = "
        <div style='font-family: Arial, sans-serif; font-size: 15px; color: #333;'>
            <p>Dear user,</p>
            <p>We noticed that your account details may be outdated. For security and proper record management, 
            please update your account information as soon as possible.</p>
            
            <p style='text-align: center; margin: 20px 0;'>
                <a href='{$updateLink}' 
                   style='background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; 
                          border-radius: 5px; font-weight: bold;'>
                   Update My Account
                </a>
            </p>

            <p>If you cannot click the button above, copy and paste this link into your browser:</p>
            <p><a href='{$updateLink}'>{$updateLink}</a></p>

            <p>Thank you,<br>University Health Services</p>
        </div>";

        $mail->Body = $body;

        return $mail->send();
    } catch (Exception $e) {
        error_log("Mail Error: " . $e->getMessage());
        return false;
    }
}
