<?php
session_start();
require_once 'config.php';
require_once 'mailer.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if token exists
if (!isset($_GET['token']) || empty($_GET['token'])) {
    $_SESSION['error'] = "No verification token provided.";
    header("Location: signup.php");
    exit();
}

$token = $_GET['token'];
$email = $_GET['email'];



// Check if token exists in database
$stmt = $conn->prepare("SELECT id, email, first_name, verified, token_created_at FROM users WHERE verification_token = ?");
if (!$stmt) {
    $_SESSION['error'] = "Database error. Please try again.";
    header("Location: signup.php");
    exit();
}
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

// ✅ Now safely fetch the user and check verified status
$user = $result->fetch_assoc();

// Check if token exists in database
$stmt = $conn->prepare("SELECT verified FROM users WHERE email = ?");
if (!$stmt) {
    $_SESSION['error'] = "Database error. Please try again.";
    header("Location: signup.php");
    exit();
}
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

// ✅ Now safely fetch the user and check verified status
$email = $result->fetch_assoc();





if ($email['verified'] == 1) {
    $_SESSION['STATUS'] = "ALREADY_VERIFIED";
    $_SESSION['STATUS_MESSAGE'] = "Your email is already verified. You can now log in.";
    $stmt->close();
    header("Location: signup.php");
    exit();
} else {
    if ($result->num_rows !== 1) {
        $_SESSION['STATUS'] = "VERIFICATION_FAILED";
        $_SESSION['STATUS_MESSAGE'] = "Invalid or expired verification link.";
        $stmt->close();
        header("Location: signup.php");
        exit();
    }
}


// ✅ First, check if there's a match

// Check if token is expired (5 minutes)
$token_expiry = strtotime($user['token_created_at']) + (5 * 60); // 5 minutes expiry
if (time() > $token_expiry) {
    // Token expired, generate new token
    $new_token = bin2hex(random_bytes(32));
    $token_created_at = date('Y-m-d H:i:s');

    $update = $conn->prepare("UPDATE users SET verification_token = ?, token_created_at = ? WHERE id = ?");
    if (!$update) {
        $_SESSION['error'] = "Database error. Please try again.";
        $stmt->close();
        header("Location: /signup");
        exit();
    }
    $update->bind_param("ssi", $new_token, $token_created_at, $user['id']);

    if ($update->execute()) {
        $verification_link = "https://wmsuhealthservices.site/verify_email?token=$new_token";
        $subject = "WMSU Health Services - Verify Your Email Address";
        $message = "
            <html>
            <body style='font-family: Arial, sans-serif; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <img src='https://wmsuhealthservices.site/images/logo.png' alt='WMSU Logo' style='max-width: 150px;'>
                    <h2 style='color: #a6192e;'>Welcome to WMSU Health Services</h2>
                    <p>Dear {$user['first_name']},</p>
                    <p>Your previous verification link has expired. To complete your registration, please verify your email address by clicking the button below:</p>
                    <a href='$verification_link' style='display: inline-block; padding: 10px 20px; background-color: #a6192e; color: white; text-decoration: none; border-radius: 5px;'>Verify My Email</a>
                    <p>This link will expire in 5 minutes for security purposes.</p>
                    <p>If the button doesn't work, you can copy and paste this link into your browser:</p>
                    <p><a href='$verification_link'>$verification_link</a></p>
                    <p>If you did not initiate this request, please ignore this email or contact our support team.</p>
                    <p>Best regards,<br><strong>WMSU Health Services Team</strong></p>
                    <hr>
                    <p style='font-size: 12px; color: #777;'>For support, contact us at support@wmsuhealthservices.site</p>
                </div>
            </body>
            </html>";

        if (send_email($user['email'], $subject, $message)) {
            $_SESSION['success'] = "Your verification link has expired. A new verification link has been sent to your email.";
        } else {
            $_SESSION['error'] = "Verification link expired. We failed to send a new one. Please contact support.";
        }
    } else {
        $_SESSION['error'] = "Error generating new verification link. Please contact support.";
    }

    $update->close();
    $stmt->close();

    header("Location: signup.php");
    exit();
}

// Token is valid, mark email as verified
$update_stmt = $conn->prepare("UPDATE users SET verified = 1, verification_token = NULL, token_created_at = NULL, verified_at = NOW() WHERE id = ?");
if (!$update_stmt) {
    $_SESSION['error'] = "Database error. Please try again.";
    $stmt->close();
    header("Location: signup.php");
    exit();
}
$update_stmt->bind_param("i", $user['id']);

if ($update_stmt->execute()) {
    $_SESSION['STATUS'] = "SIGN_UP_SUCCESS"; 
    $_SESSION['STATUS_MESSAGE'] = "You have successfully signed up and verified your email! You can now log in.";
} else {
    $_SESSION['STATUS'] = "VERIFICATION_FAILED";
    $_SESSION['STATUS_MESSAGE'] = "Verification failed. Please try again or contact support.";
}
$update_stmt->close();
$stmt->close();
header("Location: signup.php");
exit();
