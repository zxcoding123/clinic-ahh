<?php
session_start();
require_once 'config.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check database connection
if (!$conn) {
    error_log("Database connection failed: " . mysqli_connect_error());
    die("Database connection failed. Please contact the administrator.");
}

// Set UTF-8 charset
mysqli_set_charset($conn, 'utf8mb4');

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

// Fetch admin details
$user_id = $_SESSION['user_id'];
$query = "SELECT email, user_type FROM users WHERE id = ? AND user_type IN ('Super Admin')";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$admin = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$admin) {
    session_destroy();
    header("Location: /index.php");
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Sending Progress</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Cinzel';

            background: linear-gradient(-45deg, #8B0000, white, #8B0000);
            background-size: 400% 400%;
            animation: gradientMove 16s ease infinite;
        }

        @keyframes gradientMove {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }

        .btn-crimson {
            background-color: #8B0000;
        }

        .btn-crimson:hover {
            border: 1px solid #8B0000;
            color: #8B0000 !important;
        }

        .card-header {
            background-color: #8B0000 !important;
        }

        .progress-container {
            margin-top: 50px;
        }

        .progress-bar {
            transition: width 0.3s ease;
        }

        .user-types {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .stats-card {
            margin-top: 20px;
        }

        #failedEmails {
            max-height: 200px;
            overflow-y: auto;
        }

        .bold {
            font-weight: bolder;
        }
    </style>
</head>

<body>
    <div class="container progress-container">
        <div class="row justify-content-center align-items-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header text-center text-white">
                        <h4 class="mb-0 text-center bold">Email Sending Progress</h4>
                    </div>
                    <div class="card-body">
                        <div class="user-types">
                            <h5 class="bold">Processing these user types:</h5>
                            <div class="d-flex flex-wrap">
                                <span class="badge bg-secondary me-2 mb-2">Incoming Freshman</span>
                                <span class="badge bg-secondary me-2 mb-2">Highschool</span>
                                <span class="badge bg-secondary me-2 mb-2">Senior High School</span>
                                <span class="badge bg-secondary me-2 mb-2">College</span>
                                <span class="badge bg-secondary me-2 mb-2">Employee</span>
                                <span class="badge bg-secondary me-2 mb-2">Parent</span>
                            </div>
                        </div>

                        <div id="progressSection">
                           <div class="alert alert-danger text-center fw-bold" role="alert" style="font-size: 1.2rem;">
  ⚠️ WARNING: Please do not exit this site!
</div>
                            <h5 class="mb-3">Sending emails...</h5>
                            <div class="progress mb-3" style="height: 30px;">
                                <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated"
                                    role="progressbar" style="width: 0%"></div>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <span id="progressText">Initializing...</span>
                                <span id="progressPercent">0%</span>
                            </div>

                            <div class="stats-card card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-success bg-opacity-10 p-3 rounded me-3">
                                                    <i class="fas fa-check-circle text-success fs-4"></i>
                                                </div>
                                                <div>
                                                    <p class="mb-0 text-muted">Success</p>
                                                    <h4 id="successCount" class="mb-0 text-success">0</h4>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-danger bg-opacity-10 p-3 rounded me-3">
                                                    <i class="fas fa-times-circle text-danger fs-4"></i>
                                                </div>
                                                <div>
                                                    <p class="mb-0 text-muted">Failed</p>
                                                    <h4 id="failCount" class="mb-0 text-danger">0</h4>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary bg-opacity-10 p-3 rounded me-3">
                                                    <i class="fas fa-envelope text-primary fs-4"></i>
                                                </div>
                                                <div>
                                                    <p class="mb-0 text-muted">Total</p>
                                                    <h4 id="totalCount" class="mb-0 text-primary">0</h4>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="text-center mt-3">
                                <div id="spinner" class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                        </div>

                        <div id="completedSection" class="d-none">
                            <div class="alert alert-success">
                                <h4 class="alert-heading">Process Completed!</h4>
                                <p>All emails have been processed successfully.</p>
                                <hr>
                                <div class="mb-3">
                                    <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#failedEmailsCollapse" aria-expanded="false">
                                        Show Failed Emails (<span id="failedCount">0</span>)
                                    </button>
                                </div>
                                <div class="collapse" id="failedEmailsCollapse">
                                    <div class="card card-body">
                                        <div id="failedEmails" class="text-muted"></div>
                                    </div>
                                </div>
                            </div>
                            <a href="admin-account.php" class="btn btn-crimson w-100 text-white">Return to Dashboard</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <script>
        $(document).ready(function() {
            // Start polling for progress updates
            function checkProgress() {
                $.ajax({
                    url: 'send_emails_background.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        // Update progress bar
                        $('#progressBar').css('width', response.progress + '%');
                        $('#progressPercent').text(response.progress + '%');

                        // Update counters
                        $('#totalCount').text(response.total);
                        $('#successCount').text(response.success);
                        $('#failCount').text(response.failed);

                        // Update progress text
                        if (response.status === 'initializing') {
                            $('#progressText').text('Initializing process...');
                        } else if (response.status === 'processing') {
                            $('#progressText').text(`Processing ${response.processed} of ${response.total} emails...`);
                        }

                        // Handle completion
                        if (response.status === 'completed') {
                            $('#progressBar')
                                .removeClass('progress-bar-animated progress-bar-striped')
                                .addClass('bg-success');
                            $('#progressText').text('Completed!');
                            $('#progressPercent').text('100%');
                            $('#spinner').hide();

                            // Show completion section
                            $('#progressSection').addClass('d-none');
                            $('#completedSection').removeClass('d-none');
                            $('#failedCount').text(response.failed);

                            // Display failed emails if any
                            if (response.failed > 0) {
                                let failedList = '';
                                <?php if (isset($_SESSION['email_process']['failed_emails'])): ?>
                                    <?php foreach ($_SESSION['email_process']['failed_emails'] as $email): ?>
                                        failedList += '<div><?php echo $email; ?></div>';
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                $('#failedEmails').html(failedList);
                            }
                            return;
                        }

                        // Continue polling every second
                        setTimeout(checkProgress, 1000);
                    },
                    error: function(xhr, status, error) {
                        console.error("Error checking progress:", error);
                        // Retry after 3 seconds on error
                        setTimeout(checkProgress, 3000);
                    }
                });
            }

            // Start the progress check
            checkProgress();
        });
    </script>
</body>

</html>