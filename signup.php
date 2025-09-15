<?php
ob_start(); // Start output buffering
session_start();
// Database connection
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';

// Ensure database charset
if (!$conn->set_charset("utf8mb4")) {
    error_log("Failed to set charset: " . $conn->error . " at " . date('Y-m-d H:i:s'));
    http_response_code(500);
    die("Database error. Please contact support.");
}

// Redirect logged-in users
if (isset($_SESSION['user_id'])) {
    header("Location: homepage.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Store form data in session
    $_SESSION['form_data'] = [
        'last_name' => $_POST['last_name'] ?? '',
        'first_name' => $_POST['first_name'] ?? '',
        'middle_name' => $_POST['middle_name'] ?? '',
        'email' => $_POST['email'] ?? ''
    ];

    // Sanitize and validate inputs
    $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
    $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
    $middle_name = filter_input(INPUT_POST, 'middle_name', FILTER_SANITIZE_STRING) ?: '';
    $email = strtolower(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate required fields
    if (empty($last_name) || empty($first_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $_SESSION['error'] = "Please fill in all required fields.";
        header("Location: signup.php");
        exit();
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format.";
        header("Location: signup.php");
        exit();
    }

    // Validate password match
    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match.";
        unset($_SESSION['form_data']['password']);
        unset($_SESSION['form_data']['confirm_password']);
        header("Location: signup.php");
        exit();
    }

    // Validate password strength
    if (strlen($password) < 8) {
        $_SESSION['error'] = "Password must be at least 8 characters long.";
        unset($_SESSION['form_data']['password']);
        unset($_SESSION['form_data']['confirm_password']);
        header("Location: signup.php");
        exit();
    }

    // Check if user already exists
    $stmt = $conn->prepare("SELECT id, verified, token_created_at FROM users WHERE email = ?");
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error . " at " . date('Y-m-d H:i:s'));
        $_SESSION['error'] = "Database error. Please try again later.";
        header("Location: signup.php");
        exit();
    }
    $stmt->bind_param("s", $email);
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error . " at " . date('Y-m-d H:i:s'));
        $_SESSION['error'] = "Database error. Please try again later.";
        $stmt->close();
        header("Location: signup.php");
        exit();
    }
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if ($user['verified'] == 1) {
            $_SESSION['error'] = "This email is already registered and verified.";
            $stmt->close();
            header("Location: signup.php");
            exit();
        } else {
            // Unverified user exists; check token expiry (5 minutes)
            $token_expiry = strtotime($user['token_created_at']) + (5 * 60);
            if (time() > $token_expiry) {
                // Generate new token
                $verification_token = bin2hex(random_bytes(32));
                $token_created_at = date('Y-m-d H:i:s');

                $update_stmt = $conn->prepare("UPDATE users SET verification_token = ?, token_created_at = ? WHERE id = ?");
                if (!$update_stmt) {
                    error_log("Update prepare failed: " . $conn->error . " at " . date('Y-m-d H:i:s'));
                    $_SESSION['error'] = "Database error. Please try again later.";
                    $stmt->close();
                    header("Location: signup.php");
                    exit();
                }
                $update_stmt->bind_param("ssi", $verification_token, $token_created_at, $user['id']);
                if (!$update_stmt->execute()) {
                    error_log("Update execute failed: " . $update_stmt->error . " at " . date('Y-m-d H:i:s'));
                    $_SESSION['error'] = "Database error. Please try again later.";
                    $update_stmt->close();
                    $stmt->close();
                    header("Location: signup.php");
                    exit();
                }
                $update_stmt->close();

                // Send new verification email

                // $verification_link = "https://wmsuhealthservices.site/verify_email?token=$verification_token";

                $verification_link = "https://localhost/wmsu/verify_email.php?token=$verification_token&email=$email";

                $subject = "WMSU Health Services - Verify Your Email Address";
                $message = "
                    <html>
                    <body style='font-family: Arial, sans-serif; color: #333;'>
                        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                            <img src='https://wmsuhealthservices.site/images/clinic.png' alt='WMSU Logo' style='max-width: 150px;'>
                            <h2 style='color: #a6192e;'>Welcome to WMSU Health Services</h2>
                            <p>Dear " . htmlspecialchars($first_name) . ",</p>
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

                if (send_email($email, $subject, $message)) {
                    $_SESSION['success'] = "A new verification link has been sent to your email.";
                } else {
                    $_SESSION['error'] = "Failed to send verification email. Please contact support.";
                }
                $stmt->close();
                header("Location: signup.php");
                exit();
            } else {
                // Resend existing token
                $token_stmt = $conn->prepare("SELECT verification_token FROM users WHERE id = ?");
                if (!$token_stmt) {
                    error_log("Token prepare failed: " . $conn->error . " at " . date('Y-m-d H:i:s'));
                    $_SESSION['error'] = "Database error. Please try again later.";
                    $stmt->close();
                    header("Location: signup.php");
                    exit();
                }
                $token_stmt->bind_param("i", $user['id']);
                if (!$token_stmt->execute()) {
                    error_log("Token execute failed: " . $token_stmt->error . " at " . date('Y-m-d H:i:s'));
                    $_SESSION['error'] = "Database error. Please try again later.";
                    $token_stmt->close();
                    $stmt->close();
                    header("Location: signup.php");
                    exit();
                }
                $token_result = $token_stmt->get_result();
                $token = $token_result->fetch_assoc()['verification_token'];
                $token_stmt->close();

                // $verification_link = "https://wmsuhealthservices.site/verify_email?token=$token";

                $verification_link = "https://localhost/wmsu/verify_email.php?token=$verification_token&email=$email";
                $subject = "WMSU Health Services - Verify Your Email Address";
                $message = "
                    <html>
                    <body style='font-family: Arial, sans-serif; color: #333;'>
                        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                            <img src='https://wmsuhealthservices.site/images/clinic.png' alt='WMSU Logo' style='max-width: 150px;'>
                            <h2 style='color: #a6192e;'>Welcome to WMSU Health Services</h2>
                            <p>Dear " . htmlspecialchars($first_name) . ",</p>
                            <p>Please verify your email address to complete your registration by clicking the button below:</p>
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

                if (send_email($email, $subject, $message)) {
                    $_SESSION['success'] = "A verification link has been resent to your email.";
                } else {
                    $_SESSION['error'] = "Failed to resend verification email. Please contact support.";
                }
                $stmt->close();
                header("Location: signup.php");
                exit();
            }
        }
    }
    $stmt->close();

    // New user: Insert into database
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $verification_token = bin2hex(random_bytes(32));
    $token_created_at = date('Y-m-d H:i:s');

    $insert_stmt = $conn->prepare("
        INSERT INTO users (last_name, first_name, middle_name, email, password, verification_token, token_created_at, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    if (!$insert_stmt) {
        error_log("Insert prepare failed: " . $conn->error . " at " . date('Y-m-d H:i:s'));
        $_SESSION['error'] = "Database error. Please try again later.";
        header("Location: signup.php");
        exit();
    }
    $insert_stmt->bind_param("sssssss", $last_name, $first_name, $middle_name, $email, $hashed_password, $verification_token, $token_created_at);

    if ($insert_stmt->execute()) {
        // Clear session data
        $_SESSION = [];
        session_regenerate_id(true);

        // Send verification email
        // $verification_link = "https://wmsuhealthservices.site/verify_email?token=$verification_token";

        $verification_link = "https://localhost/wmsu/verify_email.php?token=$verification_token&email=$email";
        $subject = "WMSU Health Services - Verify Your Email Address";
        $message = "
            <html>
            <body style='font-family: Arial, sans-serif; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <img src='https://wmsuhealthservices.site/images/clinic.png' alt='WMSU Logo' style='max-width: 150px;'>
                    <h2 style='color: #a6192e;'>Welcome to WMSU Health Services</h2>
                    <p>Dear " . htmlspecialchars($first_name) . ",</p>
                    <p>Thank you for registering with WMSU Health Services. To complete your registration, please verify your email address by clicking the button below:</p>
                    <a href='$verification_link' style='display: inline-block; padding: 10px 20px; background-color: #a6192e; color: white; text-decoration: none; border-radius: 5px;'>Verify My Email</a>
                    <p>This link will expire in 5 minutes for security purposes.</p>
                    <p>If the button doesn't work, you can copy and paste this link into your browser:</p>
                    <p><a href='$verification_link'>$verification_link</a></p>
                    <p>If you did not create an account, please ignore this email or contact our support team.</p>
                    <p>Best regards,<br><strong>WMSU Health Services Team</strong></p>
                    <hr>
                    <p style='font-size: 12px; color: #777;'>For support, contact us at support@wmsuhealthservices.site</p>
                </div>
            </body>
            </html>";

        if (send_email($email, $subject, $message)) {
            $_SESSION['success'] = "Registration successful! Please check your email to verify your account.";
        } else {
            $_SESSION['error'] = "Registration successful, but we couldn't send the verification email. Please contact support.";
        }
        $insert_stmt->close();
        header("Location: signup.php");
        exit();
    } else {
        error_log("Insert failed: " . $insert_stmt->error . " at " . date('Y-m-d H:i:s'));
        $_SESSION['error'] = "Registration failed. Please try again.";
        $insert_stmt->close();
        header("Location: signup.php");
        exit();
    }
}

if (isset($_GET['clear']) && $_GET['clear'] == 1) {
    unset($_SESSION['STATUS'], $_SESSION['STATUS_MESSAGE']);
    header("Location: signup.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="apple-touch-icon" sizes="180x180" href="images/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="images/favicon-16x16.png">
    <link rel="manifest" href="images/site.webmanifest">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700;800;900&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <title>WMSU Sign Up</title>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'cinzel': ['Cinzel', 'serif'],
                        'poppins': ['Poppins', 'sans-serif'],
                    },
                    colors: {
                        'wmsu-red': '#8B0000',
                        'wmsu-gold': '#FFD700',
                        'wmsu-dark': '#1a1a1a',
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.6s ease-in-out',
                        'slide-up': 'slideUp 0.8s ease-out',
                        'bounce-gentle': 'bounceGentle 2s infinite',
                        'pulse-slow': 'pulseSlow 3s infinite',
                        'shake': 'shake 0.5s ease-in-out',
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes bounceGentle {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-5px);
            }
        }

        @keyframes pulseSlow {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.7;
            }
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-5px);
            }

            75% {
                transform: translateX(5px);
            }
        }

        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .gradient-bg {
            background: linear-gradient(135deg, #8B0000 0%, #A52A2A 50%, #8B0000 100%);
        }

        .btn-hover {
            transition: all 0.3s ease;
        }

        .btn-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(139, 0, 0, 0.3);
        }

        .input {
            margin: 5px;
            margin-bottom: 10px;
        }

        .input-focus {
            transition: all 0.3s ease;
        }

        .input-focus:focus {
            transform: scale(1.02);
            box-shadow: 0 0 0 3px rgba(139, 0, 0, 0.1);
        }

        .floating-label {
            margin-bottom: 5px !important;
            transition: all 0.3s ease;
            transform: translateY(0) scale(1);
            transform-origin: left top;
            background-color: transparent !important;
            background: none !important;
        }

        .input-field {
            padding-top: 1.5rem;
            /* or 1.25rem for tighter fit */
        }

        .input-field:focus+.floating-label,
        .input-field:not(:placeholder-shown)+.floating-label {
            transform: translateY(-2.5rem) scale(0.85);
            color: #8B0000;
            margin-top: -1.5px;
        }

        .password-toggle {
            transition: all 0.3s ease;
        }

        .password-toggle:hover {
            transform: scale(1.1);
        }

        .checkbox-custom {
            transition: all 0.3s ease;
        }

        .checkbox-custom:checked {
            background-color: #8B0000;
            border-color: #8B0000;
        }

        .modal-backdrop {
            backdrop-filter: blur(8px);
        }
    </style>
</head>

<body class="font-poppins bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <!-- Background Pattern -->
    <div class="fixed inset-0 opacity-5">
        <div class="absolute inset-0" style="background-image: radial-gradient(circle at 1px 1px, #8B0000 1px, transparent 0); background-size: 40px 40px;"></div>
    </div>
    <div class="relative z-10 min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-lg">
            <div class="text-center mb-8 animate-fade-in">
                <div class="inline-block p-4 bg-white rounded-full shadow-lg mb-6 hover:shadow-xl transition-shadow duration-300">
                    <img src="images/clinic.png" alt="WMSU Logo" class="w-16 h-16 object-contain">
                </div>
                <h1 class="font-cinzel text-3xl font-bold text-wmsu-red mb-2">WMSU Health Services</h1>
                <p class="text-gray-600 font-medium">Create an Account</p>
            </div>
            <div class="bg-white rounded-2xl shadow-2xl p-8 animate-slide-up">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="text-center">
                        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-check-circle text-3xl text-green-500"></i>
                        </div>
                        <h3 class="font-cinzel text-xl font-semibold text-gray-800 mb-2">You are already signed up!</h3>
                        <p class="text-gray-600 mb-6">You are already logged in as <span class="font-semibold text-wmsu-red"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span></p>
                        <button onclick="window.location.href='login.php?logout'" class="btn-hover bg-wmsu-red text-white px-8 py-3 rounded-full font-semibold hover:bg-red-800 w-full">
                            <i class="fas fa-sign-out-alt mr-2"></i>Log Out
                        </button>
                    </div>
                <?php else: ?>
                    <form id="signupForm" method="POST" action="signup.php" class="space-y-6">
                        <!-- Last Name -->
                        <div class="relative" style="margin-bottom: 2rem;">
                            <input type="text" name="last_name" id="last_name"
                                class="input-field input-focus w-full px-4 py-4 border-2 border-gray-200 rounded-xl focus:border-wmsu-red focus:outline-none transition-all duration-300"
                                placeholder=" " required
                                value="<?php echo htmlspecialchars($_SESSION['form_data']['last_name'] ?? ''); ?>"
                                oninput="capitalizeFirstLetter(this)">
                            <label for="last_name" class="floating-label absolute left-4 top-4 text-gray-500 pointer-events-none bg-white px-2">
                                <i class="fas fa-user mr-2"></i>Last Name
                            </label>
                        </div>
                        <!-- First Name -->
                        <div class="relative" style="margin-bottom: 2rem;">
                            <input type="text" name="first_name" id="first_name"
                                class="input-field input-focus w-full px-4 py-4 border-2 border-gray-200 rounded-xl focus:border-wmsu-red focus:outline-none transition-all duration-300"
                                placeholder=" " required
                                value="<?php echo htmlspecialchars($_SESSION['form_data']['first_name'] ?? ''); ?>"
                                oninput="capitalizeFirstLetter(this)">
                            <label for="first_name" class="floating-label absolute left-4 top-4 text-gray-500 pointer-events-none bg-white px-2">
                                <i class="fas fa-user mr-2"></i>First Name
                            </label>
                        </div>
                        <!-- Middle Name -->
                        <div class="relative" style="margin-bottom: 2rem;">
                            <input type="text" name="middle_name" id="middle_name"
                                class="input-field input-focus w-full px-4 py-4 border-2 border-gray-200 rounded-xl focus:border-wmsu-red focus:outline-none transition-all duration-300"
                                placeholder=" "
                                value="<?php echo htmlspecialchars($_SESSION['form_data']['middle_name'] ?? ''); ?>"
                                oninput="capitalizeFirstLetter(this)">
                            <label for="middle_name" class="floating-label absolute left-4 top-4 text-gray-500 pointer-events-none bg-white px-2">
                                <i class="fas fa-user mr-2"></i>Middle Name (Optional)
                            </label>
                        </div>
                        <!-- Email -->
                        <div class="relative" style="margin-bottom: 2rem;">
                            <input type="email" name="email" id="email"
                                class="input-field input-focus w-full px-4 py-4 border-2 border-gray-200 rounded-xl focus:border-wmsu-red focus:outline-none transition-all duration-300"
                                placeholder=" " required
                                value="<?php echo htmlspecialchars($_SESSION['form_data']['email'] ?? ''); ?>">
                            <label for="email" class="floating-label absolute left-4 top-4 text-gray-500 pointer-events-none bg-white px-2">
                                <i class="fas fa-envelope mr-2"></i>Email Address
                            </label>
                        </div>
                        <!-- Password -->
                        <div class="relative" style="margin-bottom: 2rem;">
                            <input type="password" id="password" name="password"
                                class="input-field input-focus w-full px-4 py-4 pr-12 border-2 border-gray-200 rounded-xl focus:border-wmsu-red focus:outline-none transition-all duration-300"
                                placeholder=" " required
                                oninput="checkPasswordStrength()">
                            <label for="password" class="floating-label absolute left-4 top-4 text-gray-500 pointer-events-none bg-white px-2">
                                <i class="fas fa-lock mr-2"></i>Password
                            </label>
                            <button type="button" onclick="togglePasswordVisibility()"
                                class="password-toggle absolute right-4 top-1/2 transform -translate-y-1/4 text-gray-400 hover:text-wmsu-red">
                                <i class="fas fa-eye" id="passwordToggleIcon"></i>
                            </button>
                            <small id="passwordStrength" class="block mt-2 text-xs text-gray-500"></small>
                        </div>
                        <!-- Confirm Password -->
                        <div class="relative" style="margin-bottom: 2rem;">
                            <input type="password" id="confirmPassword" name="confirm_password"
                                class="input-field input-focus w-full px-4 py-4 pr-12 border-2 border-gray-200 rounded-xl focus:border-wmsu-red focus:outline-none transition-all duration-300"
                                placeholder=" " required
                                oninput="checkPasswordMatch()">
                            <label for="confirmPassword" class="floating-label absolute left-4 top-4 text-gray-500 pointer-events-none bg-white px-2">
                                <i class="fas fa-lock mr-2"></i>Confirm Password
                            </label>
                            <button type="button" onclick="toggleConfirmPasswordVisbility()"
                                class="password-toggle absolute right-4 top-1/2 transform -translate-y-1/4 text-gray-400 hover:text-wmsu-red">
                                <i class="fas fa-eye" id="confirmpasswordToggleIcon"></i>
                            </button>
                            <small id="passwordMatch" class="block mt-2 text-xs text-gray-500"></small>
                        </div>
                        <!-- Privacy Agreement -->
                        <div class="flex items-start text-start">
                            <input type="checkbox" id="privacyAgreement" name="privacyAgreement" class="checkbox-custom mt-1" required>
                            <label for="privacyAgreement" class="ml-2 text-sm text-gray-600">
                                I agree to the
                                <a href="#" onclick="openAgreementModal(event)" class="text-wmsu-red font-semibold hover:underline">Privacy Policy and Terms of Service</a>
                            </label>
                        </div>
                        <button type="submit" name="submit" class="btn-hover w-full bg-gradient-to-r from-wmsu-red to-red-700 text-white py-4 rounded-xl font-semibold text-lg hover:from-red-700 hover:to-red-800 transition-all duration-300">
                            <i class="fas fa-user-plus mr-2"></i>Sign Up
                        </button>
                        <div class="text-center pt-4 border-t border-gray-100">
                            <p class="text-gray-600">
                                Already have an account?
                                <a href="login.php" class="text-wmsu-red hover:text-red-800 font-semibold transition-colors duration-300">Log in here</a>
                            </p>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
            <div class="text-center mt-6">
                <a href="index.php" class="inline-flex items-center text-gray-500 hover:text-wmsu-red transition-colors duration-300">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back to Home
                </a>
            </div>
        </div>
    </div>
    <!-- Privacy and Terms Agreement Modal -->
    <div id="agreementModal" class="fixed inset-0 z-50 hidden">
        <div class="modal-backdrop absolute inset-0 bg-black/50"></div>
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl max-w-2xl w-full p-6 relative animate-fade-in">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-wmsu-gold/20 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-file-contract text-2xl text-wmsu-gold"></i>
                    </div>
                    <div>
                        <h3 class="font-cinzel text-xl font-semibold text-gray-800">Privacy Policy & Terms</h3>
                    </div>
                </div>
                <div class="text-gray-700 mb-6 p-4 bg-gray-50 rounded-lg max-h-96 overflow-y-auto text-sm" id="agreementContent">
                    <!-- Agreement content will be populated by JS -->
                </div>
                <button onclick="closeAgreementModal()"
                    class="w-full bg-wmsu-red text-white py-3 rounded-xl font-semibold hover:bg-red-800 transition-colors duration-300">
                    Close
                </button>
            </div>
        </div>
    </div>
    <!-- Error Modal -->
    <div id="errorModal" class="fixed inset-0 z-50 hidden">
        <div class="modal-backdrop absolute inset-0 bg-black/50"></div>
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 relative animate-fade-in">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-exclamation-triangle text-2xl text-red-500"></i>
                    </div>
                    <div>
                        <h3 class="font-cinzel text-xl font-semibold text-gray-800">Error</h3>
                        <p class="text-gray-600 text-sm">Please check the details below</p>
                    </div>
                </div>
                <div id="errorMessage" class="text-gray-700 mb-6 p-4 bg-red-50 rounded-lg border-l-4 border-red-500">
                    <?php echo isset($_SESSION['error']) ? htmlspecialchars($_SESSION['error']) : ''; ?>
                </div>
                <button onclick="closeErrorModal()"
                    class="w-full bg-wmsu-red text-white py-3 rounded-xl font-semibold hover:bg-red-800 transition-colors duration-300">
                    Close
                </button>
            </div>
        </div>
    </div>
    <!-- Success Modal -->
    <div id="successModal" class="fixed inset-0 z-50 hidden">
        <div class="modal-backdrop absolute inset-0 bg-black/50"></div>
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 relative animate-fade-in">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-check-circle text-2xl text-green-500"></i>
                    </div>
                    <div>
                        <h3 class="font-cinzel text-xl font-semibold text-gray-800">Success</h3>
                        <p class="text-gray-600 text-sm">Operation completed successfully</p>
                    </div>
                </div>
                <div id="successMessage" class="text-gray-700 mb-6 p-4 bg-green-50 rounded-lg border-l-4 border-green-500">
                    <?php echo isset($_SESSION['success']) ? htmlspecialchars($_SESSION['success']) : ''; ?>
                </div>
                <button onclick="closeSuccessModal()"
                    class="w-full bg-green-600 text-white py-3 rounded-xl font-semibold hover:bg-green-700 transition-colors duration-300">
                    Continue
                </button>
            </div>
        </div>
    </div>
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-40 hidden">
        <div class="flex items-center justify-center min-h-screen">
            <div class="bg-white rounded-2xl p-8 text-center">
                <div class="w-16 h-16 border-4 border-wmsu-red border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
                <p class="text-gray-700 font-semibold">Processing...</p>
            </div>
        </div>
    </div>
    <script>
        // Capitalize first letter for name fields
        function capitalizeFirstLetter(input) {
            if (input.value.length > 0) {
                input.value = input.value.charAt(0).toUpperCase() + input.value.slice(1);
            }
        }
        // Password visibility toggle
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('passwordToggleIcon');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        function toggleConfirmPasswordVisbility() {
            const passwordInput = document.getElementById('confirmPassword');
            const toggleIcon = document.getElementById('confirmpasswordToggleIcon');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Password strength checker
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strength = document.getElementById('passwordStrength');
            if (!password) {
                strength.textContent = '';
                return;
            }
            if (password.length < 8) {
                strength.textContent = 'Password must be at least 8 characters.';
                strength.className = 'block mt-2 text-xs text-red-500';
            } else if (!/[A-Z]/.test(password) || !/[0-9]/.test(password)) {
                strength.textContent = 'Add at least one uppercase letter and one number.';
                strength.className = 'block mt-2 text-xs text-yellow-600';
            } else {
                strength.textContent = 'Strong password!';
                strength.className = 'block mt-2 text-xs text-green-600';
            }
        }
        // Password match checker
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirmPassword').value;
            const match = document.getElementById('passwordMatch');
            if (!confirm) {
                match.textContent = '';
                return;
            }
            if (password !== confirm) {
                match.textContent = 'Passwords do not match.';
                match.className = 'block mt-2 text-xs text-red-500';
            } else {
                match.textContent = 'Passwords match!';
                match.className = 'block mt-2 text-xs text-green-600';
            }
        }
        // Privacy Agreement Modal
        function openAgreementModal(e) {
            e.preventDefault();
            document.getElementById('agreementModal').classList.remove('hidden');
            document.getElementById('agreementContent').innerHTML = `
                <h4 class='font-bold mb-2'>Privacy Agreement</h4>
                <p>I agree to allow the WMSU Health Services Center to collect, store, and process my personal and sensitive health information for medical and health-related purposes. This includes but is not limited to my name, contact information, medical history, and any other relevant data necessary for providing health services.</p>
                <p>I understand that my data will be handled securely and in accordance with applicable laws. My information may be shared with authorized personnel, healthcare providers, or entities for purposes of treatment, payment, and healthcare operations. I also acknowledge that my data may be disclosed as required by law, including but not limited to situations involving public health risks or legal investigations.</p>
                <p>I acknowledge my rights under the Data Privacy Act of 2012 (RA 10173), which include the right to access my personal information, request corrections to inaccuracies, and withdraw my consent for data processing at any time. I understand that withdrawing consent may limit my ability to receive certain services.</p>
                <h4 class='font-bold mt-4 mb-2'>Terms of Service</h4>
                <ul class='list-disc ml-6 mb-2'>
                    <li>Provide accurate and truthful personal information to the best of your knowledge.</li>
                    <li>Do not share your account credentials with others to maintain the security of your account.</li>
                    <li>Use the system solely for health-related services and consultation purposes, and not for any illegal activities.</li>
                    <li>Comply with WMSU policies regarding data security and confidentiality to protect your information and that of others.</li>
                    <li>Respect the privacy of other users and maintain the confidentiality of shared information in accordance with applicable privacy laws.</li>
                    <li>Refrain from any misuse, hacking, or unauthorized modifications to the system, which could compromise data security.</li>
                    <li>Do not engage in fraudulent activities, including but not limited to providing false medical records or impersonating others.</li>
                    <li>Understand that the WMSU Health Services Center reserves the right to update the terms without prior notice, and that it is your responsibility to stay informed of any changes.</li>
                    <li>Acknowledge that any violation of these terms may result in account suspension or permanent termination, depending on the severity of the violation.</li>
                    <li>Agree to comply with all applicable laws and university regulations while using the system, including those related to health data privacy and security.</li>
                </ul>
                <p>By continuing to use the services provided by the WMSU Health Services Center, you confirm that you understand and accept these terms and conditions fully. If you have any questions or concerns regarding these terms or your privacy rights, please contact the appropriate administrative office for clarification.</p>
            `;
        }

        function closeAgreementModal() {
            document.getElementById('agreementModal').classList.add('hidden');
        }
        // Error/Success Modal Functions
        function showErrorModal(message) {
            const modal = document.getElementById('errorModal');
            const messageDiv = document.getElementById('errorMessage');
            messageDiv.textContent = message;
            modal.classList.remove('hidden');
        }

        function closeErrorModal() {
            document.getElementById('errorModal').classList.add('hidden');
        }

        function showSuccessModal(message) {
            const modal = document.getElementById('successModal');
            const messageDiv = document.getElementById('successMessage');
            messageDiv.textContent = message;
            modal.classList.remove('hidden');
        }

        function closeSuccessModal() {
            document.getElementById('successModal').classList.add('hidden');
        }
        // Show modals if there are errors or success messages
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                <?php if (isset($_SESSION['error'])): ?>
                    showErrorModal('<?php echo addslashes($_SESSION['error']); ?>');
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                <?php if (isset($_SESSION['success'])): ?>
                    showSuccessModal('<?php echo addslashes($_SESSION['success']); ?>');
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
            }, 100);
            // Add input focus effects
            const inputs = document.querySelectorAll('input[type="text"], input[type="email"], input[type="password"]');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    // this.parentElement.classList.add('ring-2', 'ring-wmsu-red/20');

                    this.parentElement.classList.add('ring-wmsu-red/20');
                });
                input.addEventListener('blur', function() {
                    // this.parentElement.classList.remove('ring-2', 'ring-wmsu-red/20');

                    this.parentElement.classList.remove('ring-wmsu-red/20');
                });
            });
        });
        // Close modals when clicking outside
        document.addEventListener('click', function(e) {
            const errorModal = document.getElementById('errorModal');
            const successModal = document.getElementById('successModal');
            const agreementModal = document.getElementById('agreementModal');
            if (e.target === errorModal) closeErrorModal();
            if (e.target === successModal) closeSuccessModal();
            if (e.target === agreementModal) closeAgreementModal();
        });
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeErrorModal();
                closeSuccessModal();
                closeAgreementModal();
            }
        });
        // Auto-focus first name field
        window.addEventListener('load', function() {
            const firstNameInput = document.getElementById('first_name');
            if (firstNameInput && !firstNameInput.value) {
                firstNameInput.focus();
            }
        });
    </script>
<?php if (isset($_SESSION['STATUS'])): ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            let status = <?= json_encode($_SESSION['STATUS']) ?>;
            let message = <?= json_encode($_SESSION['STATUS_MESSAGE'] ?? "Something went wrong.") ?>;
            let title = "";
            let icon = "info"; // default

          if (status === "SIGN_UP_SUCCESS") {
    title = "Sign Up Successful!";
    icon = "success";
} else if (status === "ALREADY_VERIFIED") {
    title = "Already Verified!";
    icon = "success";
} else if (status === "VERIFICATION_FAILED") {
    title = "Verification Failed!";
    icon = "error";
}

Swal.fire({
    title: title,
    text: message,
    icon: icon,
    // 👇 Always "Go to Login" instead of "Verify Email"
    confirmButtonText: "Go to Login",
    allowOutsideClick: false,
    allowEscapeKey: false
}).then((result) => {
    if (result.isConfirmed) {
        window.location.href = "login.php";
    }
});

        });
    </script>
    <?php unset($_SESSION['STATUS'], $_SESSION['STATUS_MESSAGE']); ?>
<?php endif; ?>



    <script>
  document.getElementById('signupForm').addEventListener('submit', function(e) {
    Swal.fire({
      title: 'Signing Up...',
      html: 'Please wait while we process your registration.',
      allowOutsideClick: false,
      allowEscapeKey: false,
      didOpen: () => {
        Swal.showLoading();
      }
    });
  });
</script>


</body>



</html>

<!-- HTML and JavaScript remain unchanged -->
<?php ob_end_flush(); ?>