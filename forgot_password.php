<?php
session_start();
require 'config.php';
require 'mailer.php'; // <-- include the file where send_email() is defined

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Handle forgot password request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

    if (empty($email)) {
        $_SESSION['error'] = "Please enter your email.";
    } else {
        try {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            if (!$stmt) {
                throw new Exception("Database query error: " . $conn->error);
            }
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                $user_id = $user['id'];

                // Generate token
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Store token in DB
                $update = $conn->prepare("UPDATE users SET reset_token = ?, reset_expiry = ? WHERE id = ?");
                $update->bind_param("ssi", $token, $expiry, $user_id);
                $update->execute();
                $update->close();

                // Reset link
                $reset_link = "http://localhost/wmsu/reset_password.php?token=" . urlencode($token);

                // Email content
                $subject = "Password Reset Request";
                $body = "
                    <p>Hello,</p>
                    <p>Click the link below to reset your password:</p>
                    <p><a href='$reset_link'>$reset_link</a></p>
                    <p>This link is valid for 1 hour.</p>
                ";

                // Use PHPMailer function instead of mail()
                if (send_email($email, $subject, $body)) {
                    $_SESSION['success'] = "Password reset link sent to your email.";
                } else {
                    $_SESSION['error'] = "Failed to send reset email. Please try again.";
                }
            } else {
                $_SESSION['error'] = "Email not found.";
            }
            $stmt->close();
        } catch (Exception $e) {
            error_log("Forgot password error: " . $e->getMessage());
            $_SESSION['error'] = "Something went wrong. Please try again.";
        }
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

    <div class="relative z-10 min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            <!-- Title -->
            <div class="text-center mb-8">
                <div class="inline-block p-4 bg-white rounded-full shadow-lg mb-6">
                    <img src="images/clinic.png" alt="Logo" class="w-16 h-16 object-contain">
                </div>
                <h1 class="font-cinzel text-3xl font-bold text-wmsu-red mb-2">Forgot Password</h1>
                <p class="text-gray-600 font-medium">Enter your email to reset your password</p>
            </div>

            <!-- Forgot Password Form -->
            <div class="bg-white rounded-2xl shadow-2xl p-8">
                <form id="forgotForm" action="forgot_password.php" method="post" class="space-y-6">
                    <div class="relative">
                        <input type="email" name="email" id="email" required
                            class="w-full px-4 py-4 border-2 border-gray-200 rounded-xl focus:border-wmsu-red focus:outline-none"
                            placeholder="Enter your email">
                    </div>
                    <button type="submit"
                        class="btn-hover w-full bg-gradient-to-r from-wmsu-red to-red-700 text-white py-4 rounded-xl font-semibold text-lg hover:from-red-700 hover:to-red-800 transition-all duration-300">
                        <i class="fas fa-paper-plane mr-2"></i>Send Reset Link
                    </button>
                    <div class="text-center pt-4 border-t border-gray-100">
                        <a href="login.php" class="text-wmsu-red hover:text-red-800 font-semibold">Back to Login</a>
                    </div>
                </form>

                <script>
                    document.getElementById('forgotForm').addEventListener('submit', function() {
                        Swal.fire({
                            title: 'Sending...',
                            text: 'Please wait while we process your request',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                    });
                </script>

            </div>
        </div>
    </div>

    <script>
        <?php if (isset($_SESSION['error'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: '<?php echo addslashes($_SESSION['error']); ?>'
            });
        <?php unset($_SESSION['error']);
        endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: '<?php echo addslashes($_SESSION['success']); ?>'
            });
        <?php unset($_SESSION['success']);
        endif; ?>
    </script>

</body>

</html>