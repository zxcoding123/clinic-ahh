<?php
ini_set('display_errors', 1); // Remove in production
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/phpmailer/phpmailer/src/Exception.php';
require 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
require 'vendor/phpmailer/phpmailer/src/SMTP.php';
require 'vendor/autoload.php';


$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("Unauthorized access to cms_homepage.php: No user_id in session, redirecting to /login");
    header("Location: login.php");
    exit();
}

// Check connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    $_SESSION['error'] = "Database error. Please try again.";
    header("Location: login.php");
    exit();
}

// Verify user is an admin
$userId = (int)$_SESSION['user_id'];
$query = "SELECT user_type FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    error_log("Admin verification query prepare failed: " . $conn->error);
    $_SESSION['error'] = "Database error. Please try again.";
    header("Location: login.php");
    exit();
}
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || !in_array(trim($user['user_type']), ['Super Admin', 'Medical Admin', 'Dental Admin'], true)) {
    error_log("Non-admin user_id: $userId, user_type: " . ($user['user_type'] ?? 'none') . " attempted access to cms_homepage.php, redirecting to /homepage");
    header("Location: homepage.php");
    exit();
}



// Define name formatting function
function formatName($last_name, $first_name, $middle_name = '', $suffix = '')
{
    $name_parts = array_filter([
        !is_null($last_name) ? trim($last_name ?? '') . ',' : '',
        trim($first_name ?? ''),
        trim($middle_name ?? ''),
        trim($suffix ?? '')
    ], function ($part) {
        return $part !== '';
    });
    return implode(' ', $name_parts);
}

// Fetch Current Medical Appointments
$sql_current = "
    SELECT DISTINCT a.id, a.user_id, a.child_id, a.reason, a.appointment_date, a.appointment_time, a.status, a.appointment_type,
           COALESCE(c.first_name, p.firstname) AS first_name,
           COALESCE(c.last_name, p.surname) AS last_name,
           COALESCE(c.middle_name, p.middlename) AS middle_name,
           p.suffix AS suffix,
           c.type AS child_type,
           u.user_type,
            p.id AS patient_id,  -- 👈 actual patients table ID
           p.age,
           p.sex,
           p.email,
           p.contact_number,
           p.city_address,
           p.course,
           p.department,
           p.year_level,
           e.surname AS emergency_surname,
           e.firstname AS emergency_firstname,
           e.middlename AS emergency_middlename,
           e.relationship AS emergency_relationship,
           e.contact_number AS emergency_contact_number,
           e.city_address AS emergency_city_address
    FROM appointments a
    LEFT JOIN patients p ON a.user_id = p.user_id
    LEFT JOIN children c ON a.child_id = c.id
    LEFT JOIN users u ON a.user_id = u.id
    LEFT JOIN emergency_contacts e ON p.id = e.patient_id
    WHERE a.appointment_type = 'medical'
    AND u.user_type != 'Incoming Freshman'
    AND (u.user_type != 'Parent' OR c.type IN ('Kindergarten', 'Elementary'))
    AND a.status = 'Pending'
    GROUP BY a.id, a.user_id, a.child_id
    ORDER BY a.appointment_date, a.appointment_time";
$result_current = mysqli_query($conn, $sql_current);
if ($result_current === false) {
    error_log("Current appointments query failed: " . mysqli_error($conn));
    die("Database error. Please contact the administrator.");
}
$current_appointments = [];
while ($row = mysqli_fetch_assoc($result_current)) {
    $current_appointments[] = $row;
}
mysqli_free_result($result_current);

// Fetch History Medical Appointments
$sql_history = "
    SELECT DISTINCT 
           a.id AS appointment_id,       -- appointment primary key
           a.user_id,                    -- user_id from appointments
           a.child_id,
           a.reason,
           a.appointment_date,
           a.appointment_time,
           a.status,
           a.appointment_type,
           COALESCE(c.first_name, p.firstname) AS first_name,
           COALESCE(c.last_name, p.surname) AS last_name,
           COALESCE(c.middle_name, p.middlename) AS middle_name,
           p.suffix AS suffix,
           p.id AS patient_id,           -- patient table primary key
           c.type AS child_type,
           u.user_type
    FROM appointments a
    LEFT JOIN patients p ON a.user_id = p.user_id
    LEFT JOIN children c ON a.child_id = c.id
    LEFT JOIN users u ON a.user_id = u.id
    WHERE a.appointment_type = 'medical'
      AND a.status IN ('Completed', 'Cancelled')
    GROUP BY a.id, a.user_id, a.child_id
    ORDER BY a.appointment_date DESC
";

$result_history = mysqli_query($conn, $sql_history);
if ($result_history === false) {
    error_log("History appointments query failed: " . mysqli_error($conn));
    die("Database error. Please contact the administrator.");
}
$history_appointments = [];
while ($row = mysqli_fetch_assoc($result_history)) {
    $history_appointments[] = $row;
}
mysqli_free_result($result_history);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appointment'])) {
    $appointment_id = $_POST['appointment_id'];
    $reason = $_POST['reason'] ?? '';

    // Update DB first
    $stmt = $conn->prepare("UPDATE appointments SET status = 'Cancelled', reason = ? WHERE id = ?");
    $stmt->bind_param("si", $reason, $appointment_id);
    $stmt->execute();
    $stmt->close();

    // Send email + notification
    notifyAndEmailCancellation($conn, $appointment_id, $reason);

    header("Location: medical-appointments.php");
    exit();
}

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

function notifyAndEmailCancellation($conn, $appointmentId, $reason)
{
    // Query for user_id + email associated with this appointment
    $sql = "SELECT u.id AS user_id, u.first_name, u.email
            FROM appointments a
            INNER JOIN users u ON a.user_id = u.id
            WHERE a.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $appointmentId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $userId = $row['user_id'];
        $patientEmail = $row['email'];
        $firstName = $row['first_name'];

        // Create notification
        $title = "Appointment Cancelled";
        $description = "Your medical appointment was cancelled. Reason: " . $reason;
        createUserNotification($conn, $userId, 'cancellation', $title, $description, 'appointments.php');

        // Send cancellation email
        sendCancellationEmail($patientEmail, $firstName, $reason);
    }

    $stmt->close();
}

function sendCancellationEmail($toEmail, $firstName, $reason)
{
    $subject = "WMSU Health Services - Appointment Cancellation";
    $body = "
        <h2>WMSU Health Services - Appointment Cancelled</h2>
        <p>Dear " . htmlspecialchars($firstName) . ",</p>
        <p>We regret to inform you that your medical appointment has been cancelled.</p>
        <p><strong>Reason:</strong> " . nl2br(htmlspecialchars($reason)) . "</p>
        <p>Please contact us or reschedule your appointment at your convenience.</p>
        <p>Thank you for your understanding.</p>
        <hr>
        <p><small>This is an automated message. Please do not reply directly to this email.</small></p>
    ";

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USER'];
        $mail->Password = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom($_ENV['SMTP_USER'], 'WMSU Health Services');
        $mail->addAddress($toEmail);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags(str_replace("<br>", "\n", $body));

        $mail->send();
    } catch (Exception $e) {
        error_log("Cancellation email failed: " . $e->getMessage());
    }
}


// Handle Selected Appointments Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_selected'])) {
    $appointment_ids = $_POST['appointment_ids'] ?? [];
    $reason = $_POST['reason'] ?? '';

    if (!empty($appointment_ids)) {
        $ids = implode(',', array_map('intval', $appointment_ids));

        // Update appointments to cancelled
        $stmt = $conn->prepare("UPDATE appointments 
                                SET status = 'Cancelled', reason = ? 
                                WHERE id IN ($ids)");
        $stmt->bind_param("s", $reason);
        $stmt->execute();
        $stmt->close();

        // Send notification + email for each cancelled appointment
        foreach ($appointment_ids as $appId) {
            notifyAndEmailCancellation($conn, $appId, $reason);
        }
    }

    header("Location: medical-appointments.php");
    exit();
}



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['emergency_reject'])) {
    $appointment_ids = $_POST['appointment_ids'] ?? [];
    $reason = $_POST['emergency_reason'] ?? '';

    if (!empty($appointment_ids)) {
        $ids = implode(',', array_map('intval', $appointment_ids));
        $stmt = $conn->prepare("UPDATE appointments SET status = 'Cancelled', reason = ? WHERE id IN ($ids)");
        $stmt->bind_param("s", $reason);
        $stmt->execute();
        $stmt->close();

        foreach ($appointment_ids as $appId) {
            notifyAndEmailCancellation($conn, $appId, $reason);
        }
    }

    header("Location: medical-appointments.php");
    exit();
}
// Function to sanitize data for JSON encoding
function utf8ize($data)
{
    if ($data === null) {
        return null;
    }
    if (is_array($data)) {
        return array_map('utf8ize', $data);
    }
    if (is_string($data)) {
        return mb_convert_encoding($data, 'UTF-8', 'UTF-8');
    }
    return $data;
}

// Sanitize and encode appointment data
$current_appointments = utf8ize($current_appointments);
$history_appointments = utf8ize($history_appointments);
$current_appointments_json = json_encode($current_appointments, JSON_INVALID_UTF8_SUBSTITUTE);
$history_appointments_json = json_encode($history_appointments, JSON_INVALID_UTF8_SUBSTITUTE);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON encoding error: " . json_last_error_msg());
    die("Data encoding error. Please contact the administrator.");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Appointments - WMSU Health Services</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/medicalappointments.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="apple-touch-icon" sizes="180x180" href="images/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="images/favicon-16x16.png">
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
        .small-heading,
        .modal-title,
        .section-title {
            font-family: 'Cinzel', serif;
        }
    </style>
</head>

<body>
    <div id="app" class="d-flex">
        <button id="burger-btn" class="burger-btn">☰</button>
        <?php include 'include/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content" id="main-content">
            <div class="container-fluid">
                <h2 class="small-heading">Medical Appointments</h2>


                <ul class="nav nav-tabs mb-4" id="medicalTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="current-tab" data-bs-toggle="tab" data-bs-target="#current-medical" type="button" role="tab" aria-controls="current-medical" aria-selected="true">Current Appointments</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#medical-history" type="button" role="tab" aria-controls="medical-history" aria-selected="false">History</button>
                    </li>
                </ul>

                <div class="tab-content" id="medicalTabsContent">
                    <div class="tab-pane fade show active" id="current-medical" role="tabpanel" aria-labelledby="current-tab">
                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                            <div class="d-flex align-items-center mb-2 mb-md-0">
                                <label for="sort-options" class="form-label me-2">Sort By:</label>
                                <select id="sort-options" class="form-select w-auto" onchange="sortTable(this.value)">
                                    <option value="none">None</option>
                                    <option value="recent">Recent</option>
                                    <option value="kinder">Kinder</option>
                                    <option value="elementary">Elementary</option>
                                    <option value="highschool">Highschool</option>
                                    <option value="senior-high">Senior High</option>
                                    <option value="college">College</option>
                                    <option value="employees">Employees</option>
                                </select>
                                <button class="btn btn-danger ms-2" id="emergency-btn" onclick="openEmergencyModal()">
                                    <i class="bi bi-exclamation-triangle-fill"></i> Emergency
                                </button>
                            </div>
                            <div class="d-flex align-items-center">
                                <input type="text" id="search-bar" class="form-control" placeholder="Search by patient name" onkeyup="searchTable()">
                            </div>
                        </div>

                        <div class="action-buttons" id="medical-action-buttons">
                            <div class="d-flex align-items-center mb-2 mb-md-0">
                                <div class="form-check me-3">
                                    <input class="form-check-input" type="checkbox" id="selectAllCheckbox" onclick="toggleSelectAll()">
                                    <label class="form-check-label" for="selectAllCheckbox">Select All</label>
                                </div>
                                <button class="btn btn-outline-danger btn-sm" onclick="openRejectSelectedModal()">
                                    <i class="bi bi-x-lg"></i> Reject Selected
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;"></th>
                                        <th>Patient Name</th>
                                        <th>Reference ID</th>
                                        <th>Appointment Date and Time</th>
                                        <th>Profile</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($current_appointments as $appointment): ?>
                                        <tr data-child-type="<?php echo htmlspecialchars($appointment['child_type'] ?? ''); ?>" data-user-type="<?php echo htmlspecialchars($appointment['user_type'] ?? ''); ?>">
                                            <td><input type="checkbox" class="form-check-input patient-checkbox" data-patient-id="<?php echo $appointment['id']; ?>"></td>
                                            <td><?php echo htmlspecialchars(formatName($appointment['last_name'], $appointment['first_name'], $appointment['middle_name'], $appointment['suffix'])); ?></td>
                                            <td>REF-<?php echo sprintf("%03d", $appointment['id']); ?></td>
                                            <td>
                                                <?php
                                                echo date('M d, Y', strtotime($appointment['appointment_date'])) . ' | ' .
                                                    date('h:i A', strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']))
                                                    . ' - ' .
                                                    date('h:i A', strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time'] . ' +9 minutes'));
                                                ?>
                                            </td>


                                            <td><button class="btn btn-info btn-sm" onclick="redirectToPatientProfile(<?php echo $appointment['patient_id']; ?>, '<?php echo $appointment['patient_id'] ?: ''; ?>')">View Patient Profile</button></td>
                                            <td>
                                                <button class="btn btn-primary btn-sm" onclick="window.location.href='consultationForm.php?appointment_id=<?php echo $appointment['id']; ?>&patient_id=<?php echo $appointment['patient_id']; ?>';">
                                                    Consultation Form
                                                </button>
                                                <button class="btn btn-outline-danger btn-sm p-1" onclick="openRejectModal(<?php echo $appointment['id']; ?>)" title="Reject">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="medical-history" role="tabpanel" aria-labelledby="history-tab">
                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                            <div class="d-flex align-items-center mb-2 mb-md-0">
                                <label for="history-sort" class="form-label me-2">Filter By:</label>
                                <select id="history-sort" class="form-select w-auto" onchange="filterHistory(this.value)">
                                    <option value="all">All</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                    <option value="last-week">Last Week</option>
                                    <option value="last-month">Last Month</option>
                                </select>
                            </div>
                            <div class="d-flex align-items-center">
                                <input type="text" id="history-search" class="form-control" placeholder="Search medical history...">
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>Patient Name</th>
                                        <th>Reference ID</th>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="history-table-body">
                                    <?php foreach ($history_appointments as $appointment): ?>
                                        <tr data-status="<?php echo htmlspecialchars($appointment['status']); ?>" data-date="<?php echo htmlspecialchars($appointment['appointment_date']); ?>">
                                            <td><?php echo htmlspecialchars(formatName($appointment['last_name'], $appointment['first_name'], $appointment['middle_name'], $appointment['suffix'])); ?></td>
                                            <td><?php echo htmlspecialchars($appointment['appointment_date']); ?></td>
                                            <td><?php echo htmlspecialchars($appointment['child_type'] ?? $appointment['user_type']); ?></td>
                                            <td><?php echo htmlspecialchars($appointment['status']); ?></td>
                                            <td><?php echo htmlspecialchars($appointment['reason'] ?? 'N/A'); ?></td>
                                            <td>
                                                <button class="btn btn-info btn-sm" onclick="redirectToPatientProfile(<?php echo $appointment['patient_id']; ?>, '<?php echo $appointment['child_id'] ?: ''; ?>')">View Profile</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <nav aria-label="Medical history pagination">
                            <ul class="pagination justify-content-center">
                                <li class="page-item disabled">
                                    <span class="page-link" tabindex="-1" aria-disabled="true">Previous</span>
                                </li>
                                <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                <li class="page-item"><a class="page-link" href="#">2</a></li>
                                <li class="page-item"><a class="page-link" href="#">3</a></li>
                                <li class="page-item">
                                    <button class="page-link" href="#">Next</button>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>

                <div id="modalBackdrop" class="modal-backdrop"></div>

                <div id="rejectModal" class="modal fade" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Reject Appointment</h5>
                                <button class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="closeModalBackdrop()"></button>
                            </div>
                            <div class="modal-body">
                                <p>Enter reason for rejecting <strong id="rejectPatientName"></strong>'s appointment:</p>
                                <form id="rejectForm" method="POST" onsubmit="showCancelLoading(); return true;">
                                    <input type="hidden" name="cancel_appointment" value="1">
                                    <input type="hidden" name="appointment_id" id="rejectAppointmentId">
                                    <textarea class="form-control" name="reason" id="rejectReason" rows="4" placeholder="Provide a detailed reason for rejection" required></textarea>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="closeModalBackdrop()">Cancel</button>
                                <button type="button" class="btn btn-danger" onclick="confirmReject()">Confirm Reject</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="rejectSelectedModal" class="modal fade" data-bs-backdrop="static" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Reject Selected Appointments</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="closeModalBackdrop()"></button>
                            </div>
                            <div class="modal-body">
                                <p>Provide a reason for rejecting the selected appointments:</p>
                                <form id="rejectSelectedForm" method="POST" onsubmit="showCancelLoading(); return setSelectedIds();">
                                    <input type="hidden" name="reject_selected" value="1">
                                    <input type="hidden" name="appointment_ids" id="rejectAppointmentIds">
                                    <textarea class="form-control" name="reason" id="rejectSelectedReason" rows="4" placeholder="Provide a detailed reason for rejecting the selected appointments" required></textarea>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="closeModalBackdrop()">Cancel</button>
                                <button type="button" class="btn btn-danger" onclick="submitRejectSelected()">Confirm Reject</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="emergencyModal" class="modal fade" data-bs-backdrop="static" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Emergency Request</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="closeModalBackdrop()"></button>
                            </div>
                            <div class="modal-body">
                                <p>Provide a detailed reason for the emergency request:</p>
                                <textarea class="form-control" id="emergencyReason" name="emergency_reason" rows="4" placeholder="Describe the emergency situation" required></textarea>
                                <div class="mt-3">
                                    <h6>Select Appointments to Reject:</h6>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="emergencySelectAll" onclick="toggleEmergencySelectAll()">
                                        <label class="form-check-label" for="emergencySelectAll">Select All</label>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th style="width: 40px;"></th>
                                                    <th>Patient Name</th>
                                                    <th>Reference ID</th>
                                                    <th>Appointment Time</th>
                                                </tr>
                                            </thead>
                                            <tbody id="emergency-appointments">
                                                <?php foreach ($current_appointments as $appointment): ?>
                                                    <tr>
                                                        <td><input type="checkbox" class="form-check-input emergency-checkbox" data-patient-id="<?php echo $appointment['id']; ?>"></td>
                                                        <td><?php echo htmlspecialchars(formatName($appointment['last_name'], $appointment['first_name'], $appointment['middle_name'], $appointment['suffix'])); ?></td>
                                                        <td>REF-<?php echo sprintf("%03d", $appointment['id']); ?></td>
                                                        <td><?php echo date('h:i A', strtotime($appointment['appointment_time'])) . ' - ' . date('h:i A', strtotime($appointment['appointment_time'] . ' +9 minutes')); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <form id="emergencyForm" method="POST" onsubmit="showCancelLoading(); return setSelectedIds();">
                                    <input type="hidden" name="emergency_reject" value="1">
                                    <input type="hidden" name="appointment_ids[]" id="emergencyAppointmentIds">
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="closeModalBackdrop()">Cancel</button>
                                <button type="button" class="btn btn-danger" onclick="submitEmergencyForm()">Submit</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include('notifications_admin.php') ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Show Dashboard
        function showDashboard() {
            document.getElementById('main-content').style.display = 'block';
            closeSidebarOnMobile();
        }

        // Close sidebar on mobile
        function closeSidebarOnMobile() {
            if (window.innerWidth <= 768) {
                document.getElementById('sidebar').classList.remove('active');
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize dashboard
            showDashboard();

            // Sidebar toggle
            const burgerBtn = document.getElementById('burger-btn');
            const sidebar = document.getElementById('sidebar');

            if (burgerBtn) {
                burgerBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    sidebar.classList.toggle('active');
                });
            }

            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
                    const isClickInsideSidebar = sidebar.contains(event.target);
                    const isClickOnBurgerBtn = burgerBtn.contains(event.target);
                    if (!isClickInsideSidebar && !isClickOnBurgerBtn) {
                        sidebar.classList.remove('active');
                    }
                }
            });

            // Close sidebar when clicking sidebar buttons on mobile
            const sidebarButtons = document.querySelectorAll('#sidebar .btn-crimson:not(#cmsDropdown), #sidebar .dropdown-item');
            sidebarButtons.forEach(button => {
                button.addEventListener('click', closeSidebarOnMobile);
            });
        });

        function showModalBackdrop() {
            const modalBackdrop = document.getElementById('modalBackdrop');
            if (modalBackdrop) {
                modalBackdrop.style.display = 'block';
            }
        }

        function closeModalBackdrop() {
            const modalBackdrop = document.getElementById('modalBackdrop');
            if (modalBackdrop) {
                modalBackdrop.style.display = 'none';
            }
        }

        function openEmergencyModal() {
            const emergencyReason = document.getElementById('emergencyReason');
            const emergencySelectAll = document.getElementById('emergencySelectAll');
            if (emergencyReason && emergencySelectAll) {
                emergencyReason.value = '';
                emergencySelectAll.checked = false;
                document.querySelectorAll('.emergency-checkbox').forEach(checkbox => checkbox.checked = false);
                new bootstrap.Modal(document.getElementById('emergencyModal')).show();
                showModalBackdrop();
            }
        }

        function submitEmergencyForm() {
            const reason = document.getElementById('emergencyReason')?.value.trim();
            const selected = document.querySelectorAll('.emergency-checkbox:checked');
            const appointmentIds = Array.from(selected).map(checkbox => checkbox.dataset.patientId);
            if (!reason) {
                alert('Please provide a reason for the emergency.');
                return;
            }
            if (appointmentIds.length === 0) {
                alert('Please select at least one appointment to reject.');
                return;
            }
            const emergencyAppointmentIds = document.getElementById('emergencyAppointmentIds');
            if (emergencyAppointmentIds) {
                emergencyAppointmentIds.value = appointmentIds.join(',');
                document.getElementById('emergencyForm')?.submit();
            }
        }

        function toggleEmergencySelectAll() {
            const selectAll = document.getElementById('emergencySelectAll')?.checked;
            if (selectAll !== undefined) {
                document.querySelectorAll('.emergency-checkbox').forEach(checkbox => {
                    checkbox.checked = selectAll;
                });
            }
        }

        function sortTable(sortOrder) {
            const tbody = document.querySelector('#current-medical .table tbody');
            if (!tbody) return;
            const rows = Array.from(tbody.querySelectorAll('tr'));

            const sortedRows = rows.sort((a, b) => {
                if (sortOrder === 'recent') {
                    const timeA = new Date('1970-01-01 ' + a.querySelector('td:nth-child(4)').textContent.split(' - ')[0]);
                    const timeB = new Date('1970-01-01 ' + b.querySelector('td:nth-child(4)').textContent.split(' - ')[0]);
                    return timeB - timeA;
                } else if (sortOrder === 'none') {
                    return 0;
                } else {
                    const typeMap = {
                        'kinder': 'Kindergarten',
                        'elementary': 'Elementary',
                        'highschool': 'Highschool',
                        'senior-high': 'Senior High',
                        'college': 'College',
                        'employees': 'Employee'
                    };
                    const targetType = typeMap[sortOrder] || sortOrder;

                    const childTypeA = (a.dataset.childType || '').toLowerCase();
                    const childTypeB = (b.dataset.childType || '').toLowerCase();
                    const userTypeA = (a.dataset.userType || '').toLowerCase();
                    const userTypeB = (b.dataset.userType || '').toLowerCase();

                    const effectiveTypeA = userTypeA === 'parent' ? childTypeA : userTypeA;
                    const effectiveTypeB = userTypeB === 'parent' ? childTypeB : userTypeB;

                    const orderMap = {
                        'kindergarten': 1,
                        'elementary': 2,
                        'highschool': 3,
                        'senior high school': 4,
                        'college': 5,
                        'employee': 6
                    };

                    const rankA = effectiveTypeA === targetType.toLowerCase() ? -1 : orderMap[effectiveTypeA] || 999;
                    const rankB = effectiveTypeB === targetType.toLowerCase() ? -1 : orderMap[effectiveTypeB] || 999;
                    return rankA - rankB || effectiveTypeA.localeCompare(effectiveTypeB);
                }
            });

            tbody.innerHTML = '';
            sortedRows.forEach(row => tbody.appendChild(row));
        }

        function searchTable() {
            const input = document.getElementById('search-bar')?.value.toLowerCase().trim();
            if (input === undefined) return;
            const rows = document.querySelectorAll('#current-medical .table tbody tr');

            rows.forEach(row => {
                const name = row.querySelector('td:nth-child(2)')?.textContent.toLowerCase() || '';
                row.style.display = name.includes(input) ? '' : 'none';
            });
        }

        let currentRejectAppointmentId = null;

        function openRejectModal(appointmentId) {
            const currentAppointments = <?php echo $current_appointments_json; ?>;
            const appointment = currentAppointments.find(a => a.id == appointmentId);
            if (!appointment) {
                alert('Appointment not found');
                return;
            }
            const patientName = `${appointment.last_name}, ${appointment.first_name}${appointment.middle_name ? ' ' + appointment.middle_name : ''}${appointment.suffix ? ' ' + appointment.suffix : ''}`;
            const rejectPatientName = document.getElementById('rejectPatientName');
            const rejectReason = document.getElementById('rejectReason');
            const rejectAppointmentId = document.getElementById('rejectAppointmentId');
            if (rejectPatientName && rejectReason && rejectAppointmentId) {
                rejectPatientName.textContent = patientName;
                rejectReason.value = '';
                rejectAppointmentId.value = appointmentId;
                currentRejectAppointmentId = appointmentId;
                new bootstrap.Modal(document.getElementById('rejectModal')).show();
                showModalBackdrop();
            }
        }

        function closeRejectModal() {
            const rejectModal = bootstrap.Modal.getInstance(document.getElementById('rejectModal'));
            const rejectSelectedModal = bootstrap.Modal.getInstance(document.getElementById('rejectSelectedModal'));
            if (rejectModal) rejectModal.hide();
            if (rejectSelectedModal) rejectSelectedModal.hide();
            closeModalBackdrop();
            currentRejectAppointmentId = null;
        }

        function confirmReject() {
            const reason = document.getElementById('rejectReason')?.value.trim();
            if (!reason) {
                alert('Please provide a reason for rejection.');
                return;
            }
            document.getElementById('rejectForm')?.submit();
        }

        function openRejectSelectedModal() {
            const selected = document.querySelectorAll('.patient-checkbox:checked');
            if (selected.length === 0) {
                alert('Please select at least one patient to reject');
                return;
            }
            const appointmentIds = Array.from(selected).map(checkbox => checkbox.dataset.patientId);
            const rejectAppointmentIds = document.getElementById('rejectAppointmentIds');
            const rejectSelectedReason = document.getElementById('rejectSelectedReason');
            if (rejectAppointmentIds && rejectSelectedReason) {
                rejectAppointmentIds.value = appointmentIds.join(',');
                rejectSelectedReason.value = '';
                new bootstrap.Modal(document.getElementById('rejectSelectedModal')).show();
                showModalBackdrop();
            }
        }

        function submitRejectSelected() {
            const reason = document.getElementById('rejectSelectedReason')?.value.trim();
            if (!reason) {
                alert('Please provide a reason for rejection.');
                return;
            }
            document.getElementById('rejectSelectedForm')?.submit();
        }

        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAllCheckbox')?.checked;
            if (selectAll === undefined) return;
            const checkboxes = document.querySelectorAll('.patient-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll;
            });
            toggleMedicalActionButtons();
        }

        function toggleMedicalActionButtons() {
            const checkboxes = document.querySelectorAll('.patient-checkbox');
            const actionButtons = document.getElementById('medical-action-buttons');
            if (!actionButtons) return;
            const anyChecked = Array.from(checkboxes).some(checkbox => checkbox.checked);
            if (anyChecked) {
                actionButtons.classList.add('show-actions');
            } else {
                actionButtons.classList.remove('show-actions');
            }
        }

        function initializeCheckboxEvents() {
            document.querySelectorAll('.patient-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', toggleMedicalActionButtons);
            });
        }

        function filterHistory(filter) {
            const rows = document.querySelectorAll('#medical-history .table tbody tr');
            const now = new Date();
            const lastWeek = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
            const lastMonth = new Date(now.getFullYear(), now.getMonth() - 1);

            rows.forEach(row => {
                const status = row.dataset.status?.toLowerCase() || '';
                const date = new Date(row.dataset.date);
                let show = true;

                if (filter === 'all') {
                    show = true;
                } else if (filter === 'completed') {
                    show = status === 'completed';
                } else if (filter === 'cancelled') {
                    show = status === 'cancelled';
                } else if (filter === 'last-week') {
                    show = date >= lastWeek;
                } else if (filter === 'last-month') {
                    show = date >= lastMonth;
                }

                row.style.display = show ? '' : 'none';
            });
        }

        document.getElementById('history-search')?.addEventListener('keyup', function() {
            const input = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('#medical-history .table tbody tr');

            rows.forEach(row => {
                const name = row.querySelector('td:nth-child(1)')?.textContent.toLowerCase() || '';
                row.style.display = name.includes(input) ? '' : 'none';
            });
        });

        function redirectToPatientProfile(userId, childId) {
            const url = childId ? `temp_patient_v1_admin.php?id=${childId}` : `temp_patient_v1_admin.php?id=${userId}`;
            window.location.href = url;
        }

        document.addEventListener('DOMContentLoaded', () => {
            toggleMedicalActionButtons();
            initializeCheckboxEvents();
        });


        function showCancelLoading() {
            Swal.fire({
                title: 'Cancelling...',
                text: 'Please wait while we process the cancellation.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        }
    </script>
</body>

</html>