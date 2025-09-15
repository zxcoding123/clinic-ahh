<?php
session_start();
require 'config.php';

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Set session timeout to 30 minutes
ini_set('session.gc_maxlifetime', 1800);
session_set_cookie_params(1800);

// Define admin user types
define('ADMIN_TYPES', ['Super Admin', 'Medical Admin', 'Dental Admin']);
define('RESTRICTED_PAGES', ['homepage.php', 'Elemform.php', 'form.php', 'uploaddocs.php']);

// Function to redirect based on user type
function redirectUser($user_type, $user_id)
{
    $user_type = trim($user_type);
    error_log("Redirecting user_id: $user_id, user_type: $user_type");
    if (in_array($user_type, ADMIN_TYPES, true)) {
        error_log("Redirecting admin to /adminhome.php");
        header("Location: adminhome.php");
    } else {
        error_log("Redirecting non-admin to /homepage");
        header("Location: homepage.php");
    }
    exit();
}

// Function to block admins from restricted pages
function restrictAdminAccess($user_type, $user_id)
{
    $user_type = trim($user_type);
    if (in_array($user_type, ADMIN_TYPES, true)) {
        $current_page = basename($_SERVER['PHP_SELF']);
        if (in_array($current_page, RESTRICTED_PAGES)) {
            error_log("Admin (user_id: $user_id, user_type: $user_type) attempted to access restricted page $current_page. Redirecting to /adminhome.php");
            header("Location: adminhome.php");
            exit();
        }
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
    }
    session_destroy();
    error_log("User logged out, redirecting to /login");
    header("Location: login.php");
    exit();
}

// Clear login_email on GET request unless error exists
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_SESSION['error'])) {
    unset($_SESSION['login_email']);
}

// Validate user session
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $conn->prepare("SELECT id, user_type, verified, profile_submitted, documents_uploaded FROM users WHERE id = ?");
        if (!$stmt) {
            error_log("User session query prepare failed: " . $conn->error);
            throw new Exception("Database query error: " . $conn->error);
        }
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $user_type = trim($user['user_type']);
            error_log("Session validation for user_id: {$_SESSION['user_id']}, user_type: $user_type");

            // Restrict admins from accessing restricted pages
            restrictAdminAccess($user_type, $_SESSION['user_id']);

            // For non-admins, check verification and profile status
            if (!in_array($user_type, ADMIN_TYPES, true)) {
                if ($user['verified'] == 0) {
                    error_log("Non-admin user_id: {$_SESSION['user_id']} not verified, redirecting to /login");
                    $_SESSION['error'] = "Please verify your account.";
                    header("Location: login.php");
                    exit();
                }
                if ($user['profile_submitted'] == 0) {
                    if ($user_type === 'Parent') {
                        error_log("Parent user_id: {$_SESSION['user_id']} has not submitted profile, redirecting to /Elemform.php");
                        header("Location: Elemform.php");
                        exit();
                    } elseif ($user_type === 'Employee') {
                        error_log("Non-admin user_id: {$_SESSION['user_id']} has not submitted profile, redirecting to /form.php");
                        header("Location: EmployeeForm.php");
                        exit();
                    } elseif ($user_type === 'Highschool' || $user_type === 'Senior High School' || $user_type === 'College' || $user_type === 'Incoming Freshman') {
                        error_log("Non-admin user_id: {$_SESSION['user_id']} has not submitted profile, redirecting to /form.php");
                        header("Location: form.php");
                        exit();
                    } else {
                        error_log("Non-admin user_id: {$_SESSION['user_id']} has not uploaded documents, redirecting to /uploaddocs.php");
                        header("Location: uploaddocs.php");
                        exit();
                    }
                } else if ($user['documents_uploaded'] == 0) {
                    error_log("Non-admin user_id: {$_SESSION['user_id']} has not uploaded documents, redirecting to /uploaddocs.php");
                    header("Location: uploaddocs.php");
                    exit();
                } else {
                    $_SESSION['login_status'] = "success";
                }
            }

            // Redirect based on user type
            redirectUser($user_type, $_SESSION['user_id']);
        } else {
            error_log("No user found for user_id: {$_SESSION['user_id']}, clearing session");
            unset($_SESSION['user_id'], $_SESSION['user_email'], $_SESSION['user_name']);
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("User session validation error: " . $e->getMessage());
        $_SESSION['error'] = "Session validation failed. Please log in again.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['rememberMe']) ? true : false;

    // Store email in session for form retention on error
    $_SESSION['login_email'] = $email;

    // Validate inputs
    if (empty($email) || empty($password)) {
        error_log("Login attempt failed: Empty email or password");
        $_SESSION['error'] = "Please fill in all fields.";
    } else {
        try {
            // Check users
            $stmt = $conn->prepare("SELECT id, password, first_name, user_type, verified FROM users WHERE email = ?");
            if (!$stmt) {
                error_log("User login query prepare failed: " . $conn->error);
                throw new Exception("Database query error: " . $conn->error);
            }
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                error_log("Attempting password verification for email: $email");
                if (password_verify($password, $user['password'])) {
                    error_log("Password verification successful for email: $email");
                    unset($_SESSION['login_email']);

                    // Set user session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_name'] = $user['first_name'];

                    $user_type = trim($user['user_type']);

                    // Handle "Remember Me"
                    if ($remember) {
                        $remember_token = bin2hex(random_bytes(32));
                        $remember_expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
                        $update_stmt = $conn->prepare("UPDATE users SET remember_token = ?, remember_expiry = ? WHERE id = ?");
                        if (!$update_stmt) {
                            error_log("Remember Me update query prepare failed: " . $conn->error);
                            throw new Exception("Database query error: " . $conn->error);
                        }
                        $update_stmt->bind_param("ssi", $remember_token, $remember_expiry, $user['id']);
                        $update_stmt->execute();
                        $update_stmt->close();
                        setcookie('remember_token', $remember_token, time() + 30 * 24 * 60 * 60, '/', '', true, true);
                    }

                    // For non-admins, check verification
                    if (!in_array($user_type, ADMIN_TYPES, true) && $user['verified'] == 0) {
                        error_log("Non-admin user_id: {$user['id']} not verified, setting error message");
                        $_SESSION['error'] = "Please verify your account.";
                    } else {
                        // Redirect based on user type
                        redirectUser($user_type, $user['id']);
                    }
                } else {
                    error_log("Password verification failed for email: $email");
                    $_SESSION['error'] = "Incorrect password.";
                }
            } else {
                error_log("No user found for email: $email");
                $_SESSION['error'] = "Email not valid.";
            }
            $stmt->close();
        } catch (Exception $e) {
            error_log("Login processing error: Email=$email, Error=" . $e->getMessage());
            $_SESSION['error'] = "An error occurred. Please try again.";
        }
    }
    // Do not redirect here; let the page render to show the error modal
}

// Check for remember_token cookie
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    try {
        $stmt = $conn->prepare("SELECT id, first_name, email, user_type, verified FROM users WHERE remember_token = ? AND remember_expiry > NOW()");
        if (!$stmt) {
            error_log("Remember token query prepare failed: " . $conn->error);
            throw new Exception("Database query error: " . $conn->error);
        }
        $stmt->bind_param("s", $_COOKIE['remember_token']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['first_name'];
            $user_type = trim($user['user_type']);

            // Restrict admins from accessing restricted pages
            restrictAdminAccess($user_type, $user['id']);

            // For non-admins, check verification
            if (!in_array($user_type, ADMIN_TYPES, true) && $user['verified'] == 0) {
                error_log("Non-admin user_id: {$user['id']} not verified, redirecting to /login");
                $_SESSION['error'] = "Please verify your account.";
                header("Location: login.php");
                exit();
            }

            // Redirect based on user type
            redirectUser($user_type, $user['id']);
        }
        $stmt->close();
        error_log("Invalid or expired remember token, clearing cookie");
        setcookie('remember_token', '', time() - 3600, '/');
    } catch (Exception $e) {
        error_log("Remember token validation error: " . $e->getMessage());
        setcookie('remember_token', '', time() - 3600, '/');
    }
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
    <title>WMSU Login</title>

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

        .input-focus {
            transition: all 0.3s ease;
        }

        .input-focus:focus {
            transform: scale(1.02);
            box-shadow: 0 0 0 3px rgba(139, 0, 0, 0.1);
        }

        .floating-label {
            transition: all 0.3s ease;
            transform: translateY(0) scale(1);
            transform-origin: left top;
        }

        .input-field:focus+.floating-label,
        .input-field:not(:placeholder-shown)+.floating-label {
            transform: translateY(-2.5rem) scale(0.85);
            color: #8B0000;
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
        <!-- Login Container -->
        <div class="w-full max-w-md">
            <!-- Logo and Title Section -->
            <div class="text-center mb-8 animate-fade-in">
                <div class="inline-block p-4 bg-white rounded-full shadow-lg mb-6 hover:shadow-xl transition-shadow duration-300">
                    <img src="images/clinic.png" alt="WMSU Logo" class="w-16 h-16 object-contain">
                </div>
                <h1 class="font-cinzel text-3xl font-bold text-wmsu-red mb-2">WMSU Health Services</h1>
                <p class="text-gray-600 font-medium">Please Log In</p>
            </div>

            <!-- Login Card -->
            <div class="bg-white rounded-2xl shadow-2xl p-8 animate-slide-up">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- Already Logged In -->
                    <div class="text-center">
                        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-check-circle text-3xl text-green-500"></i>
                        </div>
                        <h3 class="font-cinzel text-xl font-semibold text-gray-800 mb-2">Welcome Back!</h3>
                        <p class="text-gray-600 mb-6">You are already logged in as <span class="font-semibold text-wmsu-red"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span></p>
                        <button onclick="window.location.href='login.php?logout'" class="btn-hover bg-wmsu-red text-white px-8 py-3 rounded-full font-semibold hover:bg-red-800 w-full">
                            <i class="fas fa-sign-out-alt mr-2"></i>Log Out
                        </button>
                    </div>
                <?php else: ?>
                    <!-- Login Form -->
                    <form action="login.php" method="post" id="loginForm" class="space-y-6">
                        <!-- Email Field -->
                        <div class="relative">
                            <input type="email" name="email" id="email"
                                class="input-field input-focus w-full px-4 py-4 border-2 border-gray-200 rounded-xl focus:border-wmsu-red focus:outline-none transition-all duration-300"
                                placeholder=" "
                                required
                                value="<?php echo htmlspecialchars($_SESSION['login_email'] ?? ''); ?>">
                            <label for="email" class="floating-label absolute left-4 top-4 text-gray-500 pointer-events-none bg-white px-2">
                                <i class="fas fa-envelope mr-2"></i>Email Address
                            </label>
                        </div>

                        <!-- Password Field -->
                        <div class="relative">
                            <input type="password" name="password" id="password"
                                class="input-field input-focus w-full px-4 py-4 pr-12 border-2 border-gray-200 rounded-xl focus:border-wmsu-red focus:outline-none transition-all duration-300"
                                placeholder=" "
                                required>
                            <label for="password" class="floating-label absolute left-4 top-4 text-gray-500 pointer-events-none bg-white px-2">
                                <i class="fas fa-lock mr-2"></i>Password
                            </label>
                            <button type="button" onclick="togglePasswordVisibility()"
                                class="password-toggle absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-wmsu-red">
                                <i class="fas fa-eye" id="passwordToggleIcon"></i>
                            </button>
                        </div>

                        <!-- Remember Me & Forgot Password -->
                        <div class="flex items-center justify-between">
                            <label class="flex items-center cursor-pointer group">
                                <input type="checkbox" name="rememberMe" id="rememberMe"
                                    class="checkbox-custom w-4 h-4 text-wmsu-red border-gray-300 rounded focus:ring-wmsu-red">
                                <span class="ml-2 text-sm text-gray-600 group-hover:text-wmsu-red transition-colors duration-300">
                                    Remember Me
                                </span>
                            </label>
                            <a href="forgot_password.php" class="text-sm text-wmsu-red hover:text-red-800 font-medium transition-colors duration-300">
                                Forgot Password?
                            </a>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" id="submitBtn"
                            class="btn-hover w-full bg-gradient-to-r from-wmsu-red to-red-700 text-white py-4 rounded-xl font-semibold text-lg hover:from-red-700 hover:to-red-800 transition-all duration-300">
                            <span id="btnText">
                                <i class="fas fa-sign-in-alt mr-2"></i>Log In
                            </span>
                            <span id="btnLoading" class="hidden">
                                <i class="fas fa-spinner fa-spin mr-2"></i>Logging In...
                            </span>
                        </button>

                        <!-- Sign Up Link -->
                        <div class="text-center pt-4 border-t border-gray-100">
                            <p class="text-gray-600">
                                Don't have an account?
                                <a href="signup.php" class="text-wmsu-red hover:text-red-800 font-semibold transition-colors duration-300">
                                    Sign up here
                                </a>
                            </p>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Back to Home -->
            <div class="text-center mt-6">
                <a href="index.php" class="inline-flex items-center text-gray-500 hover:text-wmsu-red transition-colors duration-300">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back to Home
                </a>
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
                    <!-- Error message will be populated here -->
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
                    <!-- Success message will be populated here -->
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
                <p class="text-gray-700 font-semibold">Logging you in...</p>
            </div>
        </div>
    </div>

    <script>
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

        // Form submission handling
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const btnLoading = document.getElementById('btnLoading');
            const loadingOverlay = document.getElementById('loadingOverlay');

            if (loginForm) {
                loginForm.addEventListener('submit', function(e) {
                    // Show loading state
                    submitBtn.disabled = true;
                    btnText.classList.add('hidden');
                    btnLoading.classList.remove('hidden');
                    loadingOverlay.classList.remove('hidden');
                });
            }

            // Show modals if there are errors or success messages
            <?php if (isset($_SESSION['error'])): ?>
                showErrorModal('<?php echo addslashes($_SESSION['error']); ?>');
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                showSuccessModal('<?php echo addslashes($_SESSION['success']); ?>');
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            // Add input focus effects
            const inputs = document.querySelectorAll('input[type="email"], input[type="password"]');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('ring-2', 'ring-wmsu-red/20');
                });

                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('ring-2', 'ring-wmsu-red/20');
                });
            });

            // Add form validation feedback
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');

            if (emailInput) {
                emailInput.addEventListener('input', function() {
                    validateEmail(this);
                });
            }

            if (passwordInput) {
                passwordInput.addEventListener('input', function() {
                    validatePassword(this);
                });
            }
        });

        // Email validation
        function validateEmail(input) {
            const email = input.value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            if (email && !emailRegex.test(email)) {
                input.classList.add('border-red-500', 'shake');
                setTimeout(() => input.classList.remove('shake'), 500);
            } else {
                input.classList.remove('border-red-500');
            }
        }

        // Password validation
        function validatePassword(input) {
            const password = input.value;

            if (password && password.length < 6) {
                input.classList.add('border-yellow-500');
            } else {
                input.classList.remove('border-yellow-500');
            }
        }

        // Modal functions
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

        // Close modals when clicking outside
        document.addEventListener('click', function(e) {
            const errorModal = document.getElementById('errorModal');
            const successModal = document.getElementById('successModal');

            if (e.target === errorModal) {
                closeErrorModal();
            }
            if (e.target === successModal) {
                closeSuccessModal();
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeErrorModal();
                closeSuccessModal();
            }
        });

        // Auto-focus email field
        window.addEventListener('load', function() {
            const emailInput = document.getElementById('email');
            if (emailInput && !emailInput.value) {
                emailInput.focus();
            }
        });
    </script>

    <?php
    session_start();
    $status = '';
    if (isset($_SESSION['STATUS'])) {
        $status = $_SESSION['STATUS'];
        unset($_SESSION['STATUS']); // Clear it after use
    }
    ?>


    <script>
        const status = "<?php echo $status; ?>";

        if (status === "LOGOUT_SUCCESFUL") {
            Swal.fire({
                icon: 'success',
                title: 'Logged out',
                text: 'You have been successfully logged out.',
            });
        }
    </script>




</body>

</html>