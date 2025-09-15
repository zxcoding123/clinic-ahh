<?php
session_start();
require 'config.php';
require 'mailer.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    
    // Check if email exists and is verified
    $stmt = $conn->prepare("SELECT id, first_name FROM users WHERE email = ? AND verified = 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $token = bin2hex(random_bytes(32));
        $expiry = date("Y-m-d H:i:s", time() + 3600); // 1 hour expiry
        
        // Store token in database
        $update = $conn->prepare("UPDATE users SET reset_token = ?, reset_expiry = ? WHERE id = ?");
        $update->bind_param("ssi", $token, $expiry, $user['id']);
        
        if ($update->execute()) {
            // Send reset email
            $reset_link = "https://wmsuhealthservices.site/reset_password?token=$token";
            $subject = "WMSU Health Services - Password Reset Request";
            $message = "
                <html>
                <body style='font-family: Arial, sans-serif; color: #333;'>
                    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                        <img src='https://wmsuhealthservices.site/images/logo.png' alt='WMSU Logo' style='max-width: 150px;'>
                        <h2 style='color: #a6192e;'>Password Reset Request</h2>
                        <p>Dear {$user['first_name']},</p>
                        <p>We received a request to reset your password. Click the button below to proceed:</p>
                        <a href='$reset_link' style='display: inline-block; padding: 10px 20px; background-color: #a6192e; color: white; text-decoration: none; border-radius: 5px;'>Reset Password</a>
                        <p>This link will expire in 1 hour for security purposes.</p>
                        <p>If the button doesn't work, you can copy and paste this link into your browser:</p>
                        <p><a href='$reset_link'>$reset_link</a></p>
                        <p>If you did not request this, please ignore this email or contact our support team.</p>
                        <p>Best regards,<br><strong>WMSU Health Services Team</strong></p>
                        <hr>
                        <p style='font-size: 12px; color: #777;'>For support, contact us at support@wmsuhealthservices.site</p>
                    </div>
                </body>
                </html>";

            if (send_email($email, $subject, $message)) {
                $_SESSION['success'] = "A password reset link has been sent to your email.";
            } else {
                $_SESSION['error'] = "Failed to send reset email. Please contact support.";
            }
        } else {
            $_SESSION['error'] = "Database error. Please try again.";
        }
        $update->close();
    } else {
        $_SESSION['error'] = "No verified account found with that email.";
    }
    $stmt->close();
    
    header("Location: /forgot_password");
    exit();
}
?>