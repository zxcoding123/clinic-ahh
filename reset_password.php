<?php
session_start();
require 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Validate token
$token = $_GET['token'] ?? '';
$valid = false;
$user_id = null;

if (!empty($token)) {
    $stmt = $conn->prepare("SELECT id, reset_expiry FROM users WHERE reset_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (strtotime($user['reset_expiry']) > time()) {
            $valid = true;
            $user_id = $user['id'];
        } else {
            $_SESSION['error'] = "Reset link has expired.";
        }
    } else {
        $_SESSION['error'] = "Invalid reset link.";
    }
    $stmt->close();
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    $new_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password) || empty($confirm_password)) {
        $_SESSION['error'] = "Please fill in all fields.";
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $_SESSION['error'] = "Password must be at least 8 characters.";
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE id = ?");
        $stmt->bind_param("si", $hashed, $user_id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Password reset successful. Please login.";
            header("Location: login.php");
            exit;
        } else {
            $_SESSION['error'] = "Failed to reset password. Please try again.";
        }
        $stmt->close();
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
                <h1 class="font-cinzel text-3xl font-bold text-wmsu-red mb-2">Reset Password</h1>
                <p class="text-gray-600 font-medium">Enter a new password below</p>
            </div>

            <!-- Reset Password Form -->
            <div class="bg-white rounded-2xl shadow-2xl p-8">
                <?php if ($valid): ?>
                <form action="" method="post" class="space-y-6">
                    <div class="relative">
                        <input type="password" name="password" id="password" required minlength="8"
                            class="w-full px-4 py-4 border-2 border-gray-200 rounded-xl focus:border-wmsu-red focus:outline-none"
                            placeholder="New Password">
                    </div>
                    <div class="relative">
                        <input type="password" name="confirm_password" id="confirm_password" required minlength="8"
                            class="w-full px-4 py-4 border-2 border-gray-200 rounded-xl focus:border-wmsu-red focus:outline-none"
                            placeholder="Confirm Password">
                    </div>
                    <button type="submit"
                        class="btn-hover w-full bg-gradient-to-r from-wmsu-red to-red-700 text-white py-4 rounded-xl font-semibold text-lg hover:from-red-700 hover:to-red-800 transition-all duration-300">
                        <i class="fas fa-lock mr-2"></i>Reset Password
                    </button>
                    <div class="text-center pt-4 border-t border-gray-100">
                        <a href="login.php" class="text-wmsu-red hover:text-red-800 font-semibold">Back to Login</a>
                    </div>
                </form>
                <?php else: ?>
                    <div class="text-center text-red-600 font-semibold">
                        Invalid or expired reset link.
                        <br> <br>
                        <span class="text-black font-light">Redirecting you back...</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

 <script>
    <?php if (isset($_SESSION['error'])): ?>
        Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: '<?php echo addslashes($_SESSION['error']); ?>',
             timer: 3000, // show for 3 seconds
            timerProgressBar: true,
            willClose: () => {
                window.location.href = "login.php";
            }
        });
    <?php unset($_SESSION['error']); endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: '<?php echo addslashes($_SESSION['success']); ?>',
            timer: 3000, // show for 3 seconds
            timerProgressBar: true,
            willClose: () => {
                window.location.href = "login.php";
            }
        });
    <?php unset($_SESSION['success']); endif; ?>
</script>


</body>
</html>
