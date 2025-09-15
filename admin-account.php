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
$query = "SELECT email, user_type FROM users WHERE id = ? AND user_type IN ('Super Admin', 'Medical Admin', 'Dental Admin')";
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

// Initialize message variables
$success_message = '';
$error_message = '';


// Handle password update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate password requirements
    if (
        strlen($new_password) < 8 || !preg_match("/[A-Z]/", $new_password) ||
        !preg_match("/[0-9]/", $new_password) || !preg_match("/[^A-Za-z0-9]/", $new_password)
    ) {
        $error_message = "New password must be at least 8 characters long with 1 uppercase letter, 1 number, and 1 special character.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "New password and confirmation do not match.";
    } else {
        // Verify current password
        $password_query = "SELECT password FROM users WHERE id = ?";
        $password_stmt = mysqli_prepare($conn, $password_query);
        mysqli_stmt_bind_param($password_stmt, 'i', $user_id);
        mysqli_stmt_execute($password_stmt);
        $password_result = mysqli_stmt_get_result($password_stmt);
        $user = mysqli_fetch_assoc($password_result);
        mysqli_stmt_close($password_stmt);

        if (!password_verify($current_password, $user['password'])) {
            $error_message = "Current password is incorrect.";
        } else {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE users SET password = ? WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($update_stmt, 'si', $hashed_password, $user_id);
            if (mysqli_stmt_execute($update_stmt)) {
                $success_message = "Password updated successfully.";
            } else {
                $error_message = "Failed to update password. Please try again.";
            }
            mysqli_stmt_close($update_stmt);
        }
    }
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Account - WMSU Health Services</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/adminaccount.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="icon" type="image/png" sizes="32x32" href="images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="images/favicon-16x16.png">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body,
        .cms-container,
        .form-control,
        .btn,
        .main-content,
        .alert,
        .cms-container label,
        .cms-container textarea,
        .cms-container input,
        .cms-container select,
        .nav,
        .sidebar,
        .sidebar-nav,
        .sidebar-footer,
        .dropdown-menu,
        .btn-crimson,
        .dropdown-item {
            font-family: 'Poppins', sans-serif;
        }

        h1,
        h2,
        h3,
        .mb-0,
        .card-header,
        .modal-title,
        .admin-header h2 {
            font-family: 'Cinzel', serif;
        }
    </style>
</head>

<body>
    <div id="app" class="d-flex">
        <button id="burger-btn" class="burger-btn">☰</button>
        <?php include 'include/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <div class="admin-header d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1">Admin Account</h2>
                    <p class="text-light">Manage your account settings and preferences</p>
                </div>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-4">
                    <div class="admin-card card">
                        <div class="admin-card-header card-header">
                            <h5 class="mb-0">Account Settings</h5>
                        </div>
                        <div class="card-body p-0">
                            <ul class="settings-menu list-group list-group-flush">
                                <li class="list-group-item active" onclick="showSettingsTab('profile')">
                                    <i class="fas fa-user me-2"></i> Profile Information
                                </li>
                                <li class="list-group-item" onclick="showSettingsTab('security')">
                                    <i class="fas fa-lock me-2"></i> Security & Password
                                </li>
                                <li class="list-group-item" onclick="openUpdateUserAccountsModal()" data-bs-toggle="modal" data-bs-target="#updateUserAccountsModal">
                                    <i class="fas fa-users me-2"></i> Update User Accounts
                                </li>
                                <li class="list-group-item" onclick="openUpdateAcademicSchoolYear()" data-bs-toggle="modal" data-bs-target="#UpdateAcademicSchoolYear">
                                    <i class="fas fa-graduation-cap me-2"></i> Update Academic School Year
                                </li>
                                <li class="list-group-item" onclick="openLogoutModal()" data-bs-toggle="modal" data-bs-target="#logoutModal">
                                    <i class="fas fa-sign-out-alt me-2"></i> Logout
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <!-- Profile Information Tab -->
                    <div class="admin-card card settings-tab" id="profile-tab">
                        <div class="admin-card-header card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Profile Information</h5>
                            <button class="btn btn-sm btn-light" id="edit-profile-btn" onclick="toggleEditMode()">Edit Profile</button>
                        </div>
                        <div class="card-body">
                            <form id="profile-form" method="POST" action="">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control profile-field" value="<?php echo htmlspecialchars($admin['email']); ?>" readonly required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Role</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($admin['user_type']); ?>" readonly>
                                </div>
                                <div class="text-end d-none" id="save-changes-container">
                                    <input type="hidden" name="update_profile" value="1">
                                    <button type="submit" class="btn btn-dark-crimson" id="save-changes-btn">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Security & Password Tab -->
                    <div class="admin-card card settings-tab d-none" id="security-tab">
                        <div class="admin-card-header card-header">
                            <h5 class="mb-0">Security & Password</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password" name="new_password" class="form-control" required>
                                    <small class="text-muted">Minimum 8 characters with at least 1 uppercase, 1 number, and 1 special character</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" name="confirm_password" class="form-control" required>
                                </div>
                                <div class="text-end">
                                    <input type="hidden" name="update_password" value="1">
                                    <button type="submit" class="btn btn-dark-crimson" id="update-password-btn">Update Password</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Update User Accounts Modal -->
    <div class="modal fade" id="updateUserAccountsModal" tabindex="-1" aria-labelledby="updateUserAccountsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateUserAccountsModalLabel">Update User Accounts</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    This action advises all of your registered patients to update their account meaning they will not be able to login unless they have updated their account.
                    <br><br><b>NOTE: This might take a while since it will be sending emails to your patients one-by-one.</b>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <form method="POST" action="process-updates.php" id="reminder-form" target="_blank">
                        <input type="hidden" name="send_reminder" value="1">
                        <button type="submit" class="btn btn-dark-crimson" id="confirm-update-btn">Send Reminder</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="UpdateAcademicSchoolYear" tabindex="-1" aria-labelledby="UpdateAcademicSchoolYearLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg rounded-3">
                <div class="modal-header">
                    <h5 class="modal-title" id="UpdateAcademicSchoolYearLabel">
                        <i class="fas fa-graduation-cap me-2"></i> Update Academic School Year
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form id="updateAcademicYearForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="academicYearStart" class="form-label">Academic Year</label>
                            <div class="input-group">
                                <!-- Starting Year -->
                                <select class="form-select" id="academicYearStart" name="academicYearStart" required>
                                    <option value="">Select Year</option>
                                </select>
                                <span class="input-group-text">-</span>
                                <!-- Ending Year (auto-populated, readonly) -->
                                <input type="text" class="form-control" id="academicYearEnd" name="academicYearEnd" readonly>
                            </div>
                        </div>


                        <div class="mb-3">
                            <label for="semester" class="form-label">Grading Quarter</label>
                            <select class="form-select" id="grading_quarter" name="grading_quarter" required>
                                <option value="">Select Grading Quarter</option>
                                <optgroup label="Regular Terms">
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                </optgroup>
                                <optgroup label="Summer Grading Quarter">
                                    <option value="Summer">Summer</option>
                                </optgroup>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="semester" class="form-label">Semester</label>
                            <select class="form-select" id="semester" name="semester" required>
                                <option value="">Select Semester</option>
                                <optgroup label="Regular Term - 1/2 Grading Quarter ">
                                    <option value="1st Semester">1st Semester</option>
                                </optgroup>
                                <optgroup label="Regular Term - 3/4 Grading Quarter">
                                    <option value="2nd Semester">2nd Semester</option>
                                </optgroup>
                                <optgroup label="Special Terms">
                                    <option value="Summer">Summer</option>
                                </optgroup>
                            </select>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <script>
        document.getElementById("updateAcademicYearForm").addEventListener("submit", function(e) {
            e.preventDefault();

            const form = this;
            const formData = new FormData(form);

            fetch("update_academic_year.php", {
                    method: "POST",
                    body: formData
                })
                .then(res => res.text())
                .then(data => {
                    if (data.trim() === "success") {
                        Swal.fire({
                            icon: 'success',
                            title: 'Updated!',
                            text: 'Academic year updated successfully.',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            // Close modal
                            const modal = bootstrap.Modal.getInstance(document.getElementById("UpdateAcademicSchoolYear"));
                            modal.hide();

                            // Reset form (but keep end year blank until user picks)
                            form.reset();
                            document.getElementById("academicYearEnd").value = "";

                            // 🔄 Auto-refresh population
                            loadAcademicYears();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data
                        });
                    }
                })
                .catch(err => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Request Failed',
                        text: err
                    });
                });
        });

        // Function to reload academic years dynamically
        function loadAcademicYears() {
            fetch("get_academic_years.php")
                .then(res => res.text())
                .then(html => {
                    document.getElementById("academicYearsList").innerHTML = html;
                });
        }

        // 🎓 Academic Year Fields
        const startSelect = document.getElementById("academicYearStart");
        const endInput = document.getElementById("academicYearEnd");
        const gradingQuarter = document.getElementById("grading_quarter");
        const semesterSelect = document.getElementById("semester");

        // ✅ Populate the starting year dropdown only once
        (function populateYears() {
            const currentYear = new Date().getFullYear();
            for (let year = currentYear - 5; year <= currentYear + 10; year++) {
                let option = document.createElement("option");
                option.value = year;
                option.textContent = year;
                startSelect.appendChild(option);
            }
        })();

        // Auto-update end year when start year changes
        startSelect.addEventListener("change", function() {
            endInput.value = this.value ? parseInt(this.value) + 1 : "";
        });

        // 🔄 Sync Summer between Grading Quarter and Semester
        gradingQuarter.addEventListener("change", function() {
            if (this.value === "Summer") {
                semesterSelect.value = "Summer";
            }
        });

        semesterSelect.addEventListener("change", function() {
            if (this.value === "Summer") {
                gradingQuarter.value = "Summer";
            }
        });

        // 🔄 Populate modal fields when it opens
        document.getElementById("UpdateAcademicSchoolYear").addEventListener("show.bs.modal", function() {
            fetch("get_current_academic_year.php")
                .then(res => res.json())
                .then(data => {
                    if (data) {
                        // Pre-fill form with saved values
                        startSelect.value = data.start_year;
                        endInput.value = data.end_year;
                        gradingQuarter.value = data.grading_quarter;
                        semesterSelect.value = data.semester;
                    } else {
                        // Reset if no saved record
                        document.getElementById("updateAcademicYearForm").reset();
                        endInput.value = "";
                    }
                })
                .catch(err => console.error("Error fetching academic year:", err));
        });
    </script>


    <!-- Logout Confirmation Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #a6192e; color: white;">
                    <h5 class="modal-title" id="logoutModalLabel">Confirm Logout</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to logout?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">No</button>
                    <button type="button" class="btn btn-sm" style="background-color: #a6192e; color: white;" onclick="window.location.href='logout.php'">Yes</button>
                </div>
            </div>
        </div>
    </div>

    <?php include('notifications_admin.php') ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar on burger button click
        document.getElementById('burger-btn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // Function to switch between settings tabs
        function showSettingsTab(tabId) {
            // Hide all tabs
            document.querySelectorAll('.settings-tab').forEach(tab => {
                tab.classList.add('d-none');
            });
            // Show selected tab
            document.getElementById(tabId + '-tab').classList.remove('d-none');

            // Update active menu item
            document.querySelectorAll('.settings-menu .list-group-item').forEach(item => {
                item.classList.remove('active');
                if (item.getAttribute('onclick').includes(tabId)) {
                    item.classList.add('active');
                }
            });

            // Exit edit mode if switching tabs
            if (tabId !== 'profile') {
                exitEditMode();
            }
        }

        // Toggle edit mode for profile information
        function toggleEditMode() {
            const editBtn = document.getElementById('edit-profile-btn');
            const profileFields = document.querySelectorAll('.profile-field');
            const saveChangesContainer = document.getElementById('save-changes-container');

            if (editBtn.textContent === 'Edit Profile') {
                editBtn.textContent = 'Cancel';
                profileFields.forEach(field => {
                    field.readOnly = false;
                    field.classList.add('form-control-editable');
                });
                saveChangesContainer.classList.remove('d-none');
            } else {
                exitEditMode();
            }
        }

        // Exit edit mode function
        function exitEditMode() {
            const editBtn = document.getElementById('edit-profile-btn');
            const profileFields = document.querySelectorAll('.profile-field');
            const saveChangesContainer = document.getElementById('save-changes-container');

            editBtn.textContent = 'Edit Profile';
            profileFields.forEach(field => {
                field.readOnly = true;
                field.classList.remove('form-control-editable');
            });
            saveChangesContainer.classList.add('d-none');
            // Reset form to original values
            document.getElementById('profile-form').reset();
            profileFields[0].value = '<?php echo addslashes($admin['email']); ?>';
        }

        // Open Update User Accounts modal
        function openUpdateUserAccountsModal() {
            // Modal is triggered via data-bs-toggle and data-bs-target
        }

        // Open logout confirmation modal
        function openLogoutModal() {
            // Modal is triggered via data-bs-toggle and data-bs-target
        }



        // Form validation for password
        document.querySelector('form[action=""]').addEventListener('submit', function(e) {
            if (this.querySelector('input[name="update_password"]')) {
                const newPassword = this.querySelector('input[name="new_password"]').value;
                const confirmPassword = this.querySelector('input[name="confirm_password"]').value;
                const passwordRegex = /^(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,}$/;

                if (!passwordRegex.test(newPassword)) {
                    e.preventDefault();
                    alert('New password must be at least 8 characters long with 1 uppercase letter, 1 number, and 1 special character.');
                } else if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    alert('New password and confirmation do not match.');
                }
            }
        });
    </script>

    <style>
        /* Custom styles for editable fields */
        .form-control-editable {
            background-color: #fff;
            border: 1px solid #ced4da;
        }

        .settings-menu .list-group-item {
            cursor: pointer;
            border: none;
            padding: 15px 20px;
            transition: background-color 0.2s;
        }

        .settings-menu .list-group-item:hover {
            background-color: #f8f9fa;
        }

        .settings-menu .list-group-item.active {
            background-color: #a6192e;
            color: white;
        }

        .btn-dark-crimson {
            background-color: #a6192e;
            color: white;
            border: none;
        }

        .btn-dark-crimson:hover {
            background-color: #8c1626;
        }
    </style>
</body>

</html>