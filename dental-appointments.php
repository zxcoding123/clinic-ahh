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

// Fetch Current Dental Appointments
$sql_current = "
    SELECT DISTINCT a.id, a.user_id, a.child_id, a.reason, a.appointment_date, a.appointment_time, a.status, a.appointment_type,
           COALESCE(c.first_name, p.firstname) AS first_name,
           COALESCE(c.last_name, p.surname) AS last_name,
           COALESCE(c.middle_name, p.middlename) AS middle_name,
           p.id as patient_id,
           p.suffix AS suffix,
           c.type AS child_type,
           u.user_type,
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
    WHERE a.appointment_type = 'dental'
        AND a.status IN ('Pending')
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

// Fetch History Dental Appointments
$sql_history = "
    SELECT DISTINCT a.id, a.user_id, a.child_id, a.reason, a.appointment_date, a.appointment_time, a.status, a.appointment_type,
           COALESCE(c.first_name, p.firstname) AS first_name,
           COALESCE(c.last_name, p.surname) AS last_name,
           COALESCE(c.middle_name, p.middlename) AS middle_name,
           p.suffix AS suffix,
           c.type AS child_type,
                    p.id as patient_id,
           u.user_type
    FROM appointments a
    LEFT JOIN patients p ON a.user_id = p.user_id
    LEFT JOIN children c ON a.child_id = c.id
    LEFT JOIN users u ON a.user_id = u.id
    WHERE a.appointment_type = 'dental'
    AND a.status IN ('Completed', 'Cancelled')
    GROUP BY a.id, a.user_id, a.child_id
    ORDER BY a.appointment_date DESC";
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

// Handle Appointment Cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appointment'])) {
  $appointment_id = $_POST['appointment_id'];
  $reason = $_POST['reason'] ?? '';
  $stmt = mysqli_stmt_init($conn);
  if (mysqli_stmt_prepare($stmt, "UPDATE appointments SET status = 'Cancelled', reason = ? WHERE id = ?")) {
    mysqli_stmt_bind_param($stmt, "si", $reason, $appointment_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
  }
  header("Location: dental-appointments.php");
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
    $description = "Your dental appointment was cancelled. Reason: " . $reason;
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
        <p>We regret to inform you that your dental appointment has been cancelled.</p>
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

  header("Location: dental-appointments.php");
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
  <title>Dental Appointments - WMSU Health Services</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
  <link rel="stylesheet" href="css/dentalappointments.css">
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
    .form-title,
    .university-name,
    .health-center {
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
        <h2 class="small-heading">Dental Appointments</h2>

        <!-- Tabs Navigation -->
        <ul class="nav nav-tabs mb-4" id="appointmentTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="dental-tab" data-bs-toggle="tab" data-bs-target="#dental" type="button" role="tab" aria-controls="dental" aria-selected="true">Current Appointments</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab" aria-controls="history" aria-selected="false">History</button>
          </li>
          <!-- <li class="nav-item" role="presentation">
            <button class="nav-link" id="request-tab" data-bs-toggle="tab" data-bs-target="#request" type="button" role="tab" aria-controls="request" aria-selected="false">Request Slip</button>
          </li> -->
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="appointmentTabsContent">
          <!-- Dental Appointments Tab -->
          <div class="tab-pane fade show active" id="dental" role="tabpanel" aria-labelledby="dental-tab">
            <!-- Sort, Search, and Add Button -->
            <div class="d-flex justify-content-between align-items-center mb-4">
              <div class="d-flex align-items-center">
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
              </div>
              <div class="d-flex align-items-center">
                <input type="text" id="search-bar" class="form-control" placeholder="Search by patient name" onkeyup="searchTable()">
              </div>
            </div>

            <!-- Action Buttons (Select All and Reject Selected) -->
            <div class="action-buttons" id="dental-action-buttons">
              <div class="d-flex align-items-center">
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


                      <td>
                        <button class="btn btn-info btn-sm"
                          onclick="window.location.href='temp_patient_v1_admin.php?id=<?php echo $appointment['patient_id']; ?>'">
                          View Patient Profile
                        </button>
                      </td>

                      <td>
                        <button class="btn btn-primary btn-sm" onclick="window.location.href='dentalform.php?appointment_id=<?php echo $appointment['id']; ?>&patient_id=<?php echo $appointment['user_id'] ?>';">
                          Teeth Form
                        </button>
                        <button class="btn btn-outline-danger btn-sm p-1"
                          onclick="openRejectModal(<?php echo $appointment['id']; ?>, '<?php echo htmlspecialchars(formatName($appointment['last_name'], $appointment['first_name'], $appointment['middle_name'], $appointment['suffix'])); ?>')"
                          title="Reject">
                          <i class="bi bi-x-lg"></i>
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>


                </tbody>
              </table>
            </div>
          </div>

          <!-- History Tab -->
          <div class="tab-pane fade" id="history" role="tabpanel" aria-labelledby="history-tab">
            <!-- History Search and Filter -->
            <div class="d-flex justify-content-between align-items-center mb-4">
              <div class="d-flex align-items-center">
                <label for="history-sort" class="form-label me-2">Filter By:</label>
                <select id="history-sort" class="form-select w-auto">
                  <option value="all">All</option>
                  <option value="completed">Completed</option>
                  <option value="cancelled">Cancelled</option>
                  <option value="rejected">Rejected</option>
                  <option value="last-week">Last Week</option>
                  <option value="last-month">Last Month</option>
                </select>
              </div>
              <div class="d-flex align-items-center">
                <input type="text" id="history-search" class="form-control" placeholder="Search history...">
              </div>
            </div>

            <!-- History Table -->
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
                      <td>REF-<?php echo sprintf("%03d", $appointment['id']); ?></td>
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

            <!-- History Pagination -->
            <nav aria-label="History pagination">
              <ul class="pagination justify-content-center">
                <li class="page-item disabled">
                  <a class="page-link" href="#" tabindex="-1" aria-disabled="true">Previous</a>
                </li>
                <li class="page-item active"><a class="page-link" href="#">1</a></li>
                <li class="page-item"><a class="page-link" href="#">2</a></li>
                <li class="page-item"><a class="page-link" href="#">3</a></li>
                <li class="page-item">
                  <a class="page-link" href="#">Next</a>
                </li>
              </ul>
            </nav>
          </div>

          <!-- Request Slip Tab -->
          <div class="tab-pane fade" id="request" role="tabpanel" aria-labelledby="request-tab">
            <div class="slip-container">
              <div class="form-header">
                <div class="university-name">WESTERN MINDANAO STATE UNIVERSITY</div>
                <div class="health-center">HEALTH SERVICES CENTER</div>
                <div class="form-title">MEDICAL CERTIFICATE REQUEST SLIP</div>
              </div>

              <form>
                <div class="form-field">
                  <label for="name">Name:</label>
                  <input type="text" id="name" required>
                </div>

                <div class="form-field">
                  <label for="course">Course & Year (for student):</label>
                  <input type="text" id="course">
                </div>

                <div class="form-field">
                  <label for="department">Department/Office (for personnel):</label>
                  <input type="text" id="department">
                </div>

                <div class="form-field">
                  <label for="contact">Contact no.:</label>
                  <input type="text" id="contact" required>
                </div>

                <div class="request-options">
                  <p>Please CHECK (✓) the appropriate box for the nature of request:</p>

                  <div class="request-option">
                    <input type="checkbox" id="absent">
                    <label for="absent">Absent</label>
                    <div class="option-details">
                      <div>
                        <label for="absentDays">No. of days absent:</label>
                        <input type="number" id="absentDays">
                      </div>
                      <div>
                        <label for="absentReason">Reason for absent:</label>
                        <input type="text" id="absentReason">
                      </div>
                      <div>
                        <label for="absentDate">Date of consultation:</label>
                        <input type="date" id="absentDate">
                      </div>
                    </div>
                  </div>

                  <div class="request-option">
                    <input type="checkbox" id="out">
                    <label for="out">OJT</label>
                    <div class="option-details">
                      <div>
                        <label for="companyName">Name of Company:</label>
                        <input type="text" id="companyName">
                      </div>
                      <div>
                        <label for="companyAddress">Company Address:</label>
                        <input type="text" id="companyAddress">
                      </div>
                      <div>
                        <label for="outDate">Inclusive Date:</label>
                        <input type="date" id="outDate">
                      </div>
                    </div>
                  </div>

                  <div class="request-option">
                    <input type="checkbox" id="others">
                    <label for="others">Others (specify):</label>
                    <div class="option-details" style="grid-template-columns: 1fr;">
                      <div>
                        <input type="text" id="othersSpecify">
                      </div>
                    </div>
                  </div>
                </div>

                <div class="signature-section">
                  <div class="signature-label">SIGNATURE OF STAFF:</div>
                  <canvas id="signatureCanvas" class="signature-canvas" width="400" height="150"></canvas>
                  <div class="no-print">
                    <button type="button" class="btn btn-sm btn-danger" onclick="clearSignature()">Clear Signature</button>
                  </div>
                  <div style="margin-top: 20px;">
                    <label for="signatureDate">DATE:</label>
                    <input type="date" id="signatureDate" style="width: 150px;">
                  </div>
                </div>

                <div class="action-buttons no-print">
                  <button type="button" class="btn btn-primary me-2" onclick="window.print()">
                    <i class="bi bi-printer"></i> Print Form
                  </button>
                  <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-lg"></i> Submit Request
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Dark overlay -->
        <div id="modalBackdrop" class="modal-backdrop"></div>

        <!-- Reject Single Modal -->
        <div id="rejectModal" class="modal">
          <div class="modal-dialog">
            <div class="modal-content">
              <form method="POST" action="dental-appointments.php" onsubmit="showCancelLoading(); return true;">
                <div class="modal-header">
                  <h5 class="modal-title">Reject Appointment</h5>
                  <button type="button" class="btn-close" onclick="closeRejectModal()"></button>
                </div>
                <div class="modal-body">
                  <input type="hidden" name="appointment_ids[]" id="rejectAppointmentId">
                  <p>Enter reason for rejecting <strong id="rejectPatientName"></strong>'s appointment:</p>
                  <textarea class="form-control" name="reason" rows="3"></textarea>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" onclick="closeRejectModal()">Cancel</button>
                  <button type="submit" name="reject_selected" class="btn btn-danger">Confirm Reject</button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <div id="rejectSelectedModal" class="modal">
          <div class="modal-dialog">
            <div class="modal-content">
              <form method="POST" action="dental-appointments.php" onsubmit="showCancelLoading(); return setSelectedIds();"> <!-- Add this -->
                <input type="hidden" name="appointment_ids[]" id="selectedAppointmentIds">
                <div class="modal-header">
                  <h5 class="modal-title">Reject Selected Appointments</h5>
                  <button type="button" class="btn-close" onclick="closeRejectModal()"></button>
                </div>
                <div class="modal-body">
                  <p>Enter reason for rejecting the selected appointments:</p>
                  <textarea class="form-control" name="reason" rows="3" required></textarea>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" onclick="closeRejectModal()">Cancel</button>
                  <button type="submit" name="reject_selected" class="btn btn-danger">Confirm Reject</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

  <?php include('notifications_admin.php') ?>

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

    // Content Management Dropdown Functions
    function showLanding() {
      console.log("Navigating to Landing Page CMS");
      alert("Landing Page CMS clicked!");
    }

    function showHomepageCMS() {
      console.log("Navigating to Homepage CMS");
      alert("Homepage CMS clicked!");
    }

    function showAnnouncements() {
      console.log("Navigating to Announcements CMS");
      alert("Announcements CMS clicked!");
    }

    // Select all functionality
    function toggleSelectAll() {
      const selectAll = document.getElementById('selectAllCheckbox').checked;
      const checkboxes = document.querySelectorAll('.patient-checkbox');
      checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll;
      });
      toggleDentalActionButtons();
    }


    function openRejectModal(appointmentId, patientName) {
      document.getElementById('rejectAppointmentId').value = appointmentId;
      document.getElementById('rejectPatientName').innerText = patientName;
      document.getElementById('rejectModal').style.display = 'block';
    }



    function openRejectSelectedModal() {
      const selectedCheckboxes = document.querySelectorAll('.patient-checkbox:checked');
      if (selectedCheckboxes.length === 0) {
        alert('Please select at least one patient to reject');
        return;
      }
      document.getElementById("rejectSelectedModal").style.display = "flex";
      document.getElementById("modalBackdrop").style.display = "block";
    }

    // Put IDs into the hidden field before submit
    function setSelectedIds() {
      const ids = Array.from(document.querySelectorAll('.patient-checkbox:checked'))
        .map(cb => cb.getAttribute('data-patient-id'));

      document.getElementById('selectedAppointmentIds').value = ids.join(',');
      return true; // continue submitting
    }

    function closeRejectModal() {
      document.getElementById("rejectModal").style.display = "none";
      document.getElementById("rejectSelectedModal").style.display = "none";
      document.getElementById("modalBackdrop").style.display = "none";
    }

    function sortTable(order) {
      const rows = document.querySelectorAll('#dental .table tbody tr');
      const sortedRows = Array.from(rows).sort((a, b) => {
        const textA = a.querySelector('td:nth-child(2)').textContent.trim();
        const textB = b.querySelector('td:nth-child(2)').textContent.trim();
        return order === 'recent' ? textB.localeCompare(textA) : textA.localeCompare(textB);
      });

      const tbody = document.querySelector('#dental .table tbody');
      sortedRows.forEach(row => tbody.appendChild(row));
    }

    function searchTable() {
      const input = document.getElementById("search-bar").value.toLowerCase();
      const rows = document.querySelectorAll('#dental .table tbody tr');

      rows.forEach(row => {
        const name = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
        row.style.display = name.includes(input) ? "" : "none";
      });
    }

    // Search functionality for history
    document.getElementById("history-search").addEventListener("keyup", function() {
      const input = this.value.toUpperCase();
      const table = document.querySelector("#history .table");
      const tr = table.getElementsByTagName("tr");

      for (let i = 1; i < tr.length; i++) {
        const td = tr[i].getElementsByTagName("td")[0]; // Patient name column
        if (td) {
          const txtValue = td.textContent || td.innerText;
          if (txtValue.toUpperCase().indexOf(input) > -1) {
            tr[i].style.display = "";
          } else {
            tr[i].style.display = "none";
          }
        }
      }
    });

    // Toggle visibility of action buttons for Dental Appointments
    function toggleDentalActionButtons() {
      const checkboxes = document.querySelectorAll('.patient-checkbox');
      const actionButtons = document.getElementById('dental-action-buttons');
      const anyChecked = Array.from(checkboxes).some(checkbox => checkbox.checked);
      if (anyChecked) {
        actionButtons.classList.add('show-actions');
      } else {
        actionButtons.classList.remove('show-actions');
      }
    }

    // Initialize checkbox event listeners
    function initializeCheckboxListeners() {
      document.querySelectorAll('.patient-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', toggleDentalActionButtons);
      });
    }

    // Run on page load to ensure initial state
    document.addEventListener('DOMContentLoaded', () => {
      toggleDentalActionButtons();
      initializeCheckboxListeners();
    });

    // Signature Canvas Functionality
    const canvas = document.getElementById('signatureCanvas');
    const ctx = canvas.getContext('2d');
    let isDrawing = false;

    // Set up the line style for drawing
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#000';

    // Event listeners for signature drawing
    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseout', stopDrawing);

    function startDrawing(e) {
      isDrawing = true;
      draw(e);
    }

    function draw(e) {
      if (!isDrawing) return;

      ctx.lineTo(e.offsetX, e.offsetY);
      ctx.stroke();
      ctx.beginPath();
      ctx.moveTo(e.offsetX, e.offsetY);
    }

    function stopDrawing() {
      isDrawing = false;
      ctx.beginPath();
    }

    function clearSignature() {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
    }

    // Form submission
    document.querySelector('#request form').addEventListener('submit', function(e) {
      e.preventDefault();
      const signatureEmpty = canvas.toDataURL() === document.createElement('canvas').toDataURL();

      if (signatureEmpty) {
        alert('Please provide a signature');
        return;
      }

      alert('Request submitted successfully!');
      // this.submit(); // Uncomment to actually submit the form
    });

      function redirectToPatientProfile(userId, childId) {
            const url = childId ? `temp_patient_v1_admin.php?id=${childId}` : `temp_patient_v1_admin.php?id=${userId}`;
            window.location.href = url;
        }

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