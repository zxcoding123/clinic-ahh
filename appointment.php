<?php
session_start();
include 'config.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}

// Fetch user_type and patient data
$userId = (int)$_SESSION['user_id'];
$query = "SELECT user_type FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
  error_log("User query prepare failed: " . $conn->error);
  $_SESSION['error'] = "Database error. Please try again.";
  header("Location: homepage.php");
  exit;
}
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$userType = isset($user['user_type']) ? $user['user_type'] : '';


// Fetch patient data (age for non-Parent, children for Parent)
$age = null;
$children = [];
if ($userType !== 'Parent') {
  $query = "SELECT birthday, age FROM patients WHERE user_id = ?";
  $stmt = $conn->prepare($query);
  if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($patient = $result->fetch_assoc()) {
      // Prefer calculated age over stored age for accuracy
      $birthday = new DateTime($patient['birthday']);
      $today = new DateTime();
      $age = $today->diff($birthday)->y;
    }
    $stmt->close();
  } else {
    error_log("Patient query prepare failed: " . $conn->error);
    $_SESSION['error'] = "Database error fetching patient data.";
  }
} else {


  $query = "SELECT id, first_name, last_name
FROM children
WHERE parent_id = ?";
  $stmt = $conn->prepare($query);
  if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($child = $result->fetch_assoc()) {
      $children[] = [
        'id' => $child['id'],
        'name' => $child['first_name'] . ' ' . $child['last_name']
      ];
    }
    $stmt->close();
  } else {
    error_log("Children query prepare failed: " . $conn->error);
    $_SESSION['error'] = "Database error fetching children.";
  }
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Appointment - WMSU Health Services</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/appointment.css">
  <link rel="apple-touch-icon" sizes="180x180" href="images/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="images/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="images/favicon-16x16.png">
  <link rel="manifest" href="/images/site.webmanifest">
  <link rel="icon" type="image/x-icon" href="/images/favicon.ico">
</head>
<style>
  body {
    font-family: 'Poppins';
  }
</style>

<body>
  <div id="app" class="d-flex">
    <button id="sidebar-toggle" class="sidebar-toggle d-lg-none">☰</button>
    <div id="sidebar" class="sidebar d-flex flex-column align-items-center">
      <img src="images/clinic.png" alt="WMSU Clinic Logo" class="logo">
      <div class="text-white fw-bold text-uppercase text-center mt-2">WMSU</div>
      <div class="health-services text-white fw-bold text-uppercase text-center mt-2 mb-5">Health Services</div>

      <button class="btn btn-crimson mb-2 w-100" id="about-btn" onclick="window.location.href='/wmsu/homepage.php'">About Us</button>
      <button class="btn btn-crimson mb-2 w-100" id="announcement-btn" onclick="window.location.href='/wmsu/announcements.php'">Announcements</button>
      <button class="btn btn-crimson mb-2 w-100 active <?php echo ($userType === 'Incoming Freshman') ? 'btn-disabled' : ''; ?>"
        id="appointment-btn"
        <?php echo ($userType === 'Incoming Freshman') ? 'disabled' : ''; ?>
        onclick="<?php echo ($userType === 'Incoming Freshman') ? '' : 'window.location.href=\'/appointment.php\''; ?>">
        Appointment Request
      </button>
      <button class="btn btn-crimson mb-2 w-100" id="upload-btn" onclick="window.location.href='/wmsu/upload.php'">Upload Medical Documents</button>
      <button class="btn btn-crimson mb-2 w-100" id="profile-btn" onclick="window.location.href='/wmsu/profile.php'">Profile</button>
      <button class="btn btn-crimson w-100" id="logout-btn" data-bs-toggle="modal" data-bs-target="#logoutModal">Logout</button>

      <a href="https://wmsu.edu.ph" target="_blank" class="quick-link">wmsu.edu.ph</a>
    </div>

    <div class="main-content">
      <div class="header-controls d-flex align-items-center justify-content-end mb-3">
        <button class="faq-button btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#faqModal">FAQ</button>
      </div>

      <div class="appointment-container">
        <h1 class="title">Welcome to WMSU Health Services</h1>
        <h3 class="subtitle">Please Choose an Appointment</h3>
        <div class="d-flex justify-content-center flex-column mb-3">
          <?php if ($userType === 'Parent'): ?>
            <label><b>Please select your child:</b></label>
            <br>
            <select id="childSelect" class="form-select form-select-sm w-auto border border-danger border-opacity-75 me-2">
              <option value="" disabled selected>Select a Child</option>
              <?php foreach ($children as $child): ?>
                <option value="<?php echo $child['id']; ?>">
                  <?php echo htmlspecialchars($child['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>

        <div class="appointment-buttons">
          <button class="appointment-option medical" id="medicalAppointmentBtn">
            <div class="button-content">
              <span class="icon">🏥</span>
              <span class="label">Medical<br>Appointment</span>
            </div>
          </button>

          <button class="appointment-option dental" id="dentalAppointmentBtn"
            <?php if ($userType !== 'Parent'): ?>data-age="<?php echo $age ?? ''; ?>" <?php endif; ?>>
            <div class="button-content">
              <span class="icon">🦷</span>
              <span class="label">Dental<br>Appointment</span>
            </div>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- FAQ Modal -->
  <div class="modal fade" id="faqModal" tabindex="-1" aria-labelledby="faqModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header" style="background-color: #8B0000; color: white;">
          <h5 class="modal-title" id="faqModalLabel">Frequently Asked Questions</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body text-start small">
          <strong>1. How do I book an appointment?</strong>
          <p>You can book an appointment by selecting either "Dental Appointment" or "Medical Appointment" above.</p>
          <strong>2. What do I need to bring?</strong>
          <p>Please bring your student ID and any relevant medical records.</p>
          <strong>3. Can I reschedule my appointment?</strong>
          <p>Yes, please visit the "Appointment Request" section to manage your appointment.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Error Modal -->
  <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header" style="background-color: #990000; color: white;">
          <h5 class="modal-title" id="errorModalLabel">Error</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body"></div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

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
          <button type="button" class="btn btn-secondary btn-sm" style="background-color: #6c757d; color: white;" data-bs-dismiss="modal">No</button>
          <button type="button" class="btn btn-sm" style="background-color: #a6192e; color: white;" onclick="window.location.href='logout.php'">Yes</button>
        </div>
      </div>
    </div>
  </div>

    <!-- Profile Update Required Modal - Non-dismissible Version -->

  <?php
  // Fetch user data including verification status and to_change flag
  $query = "SELECT 
          profile_update_required
          FROM users 
          WHERE id = ?";
  $stmt = $conn->prepare($query);
  if (!$stmt) {
    error_log("User data query prepare failed: " . $conn->error);
    $_SESSION['error'] = "Database error. Please try again.";
    header("Location: homepage.php");
    exit();
  }
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $result = $stmt->get_result();
  $user_change = $result->fetch_assoc();
  $stmt->close();

  ?>
  <div class="modal fade" id="profileUpdateModal" tabindex="-1" aria-labelledby="profileUpdateModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-warning">
          <h5 class="modal-title" id="profileUpdateModalLabel">Profile Update Required</h5>
          <!-- Removed the close button -->
        </div>
        <div class="modal-body">
          <p>Your patient profile requires updates. Please review and update your information to ensure accuracy.</p>
          <p>You won't be able to request consultations until your profile is up-to-date.</p>
        </div>
        <div class="modal-footer">


          <?php
          $updateUrl = "update_form.php"; // default
          if ($userType == 'Incoming Freshman' || $userType == 'College' || $userType == 'Senior High School' || $userType == 'High School') {
            $updateUrl = "update_form.php";
          } elseif ($userType == 'Employee') {
            $updateUrl = "UpdateEmployee.php";
          } elseif ($userType == 'Parent') {
            $updateUrl = "update_elementary.php";
          }

          ?>
          <a href="<?= $updateUrl ?>" class="btn btn-primary">Update Profile Now</a>
          <a href="logout.php" class="btn btn-warning">Logout</a>
        </div>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const needsUpdate = <?php echo !empty($user_change['profile_update_required']) && $user_change['profile_update_required'] == 1 ? 'true' : 'false'; ?>;

      if (needsUpdate) {
        const updateModal = new bootstrap.Modal(document.getElementById('profileUpdateModal'));
        updateModal.show();
      }
    });
  </script>
  
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
  <?php include('notifications_user.php') ?>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
  <script>
    // Prevent back navigation to login or pre-login pages
    window.history.pushState(null, null, '/appointment');
    window.addEventListener('popstate', function(event) {
      window.history.pushState(null, null, '/appointment');
    });

    document.addEventListener("DOMContentLoaded", function() {
      const userType = '<?php echo $userType; ?>';
      const medicalBtn = document.getElementById('medicalAppointmentBtn');
      const dentalBtn = document.getElementById('dentalAppointmentBtn');
      const childSelect = document.getElementById('childSelect');
      const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));

      // Handle Medical Appointment button click
      medicalBtn.addEventListener('click', function() {
        if (userType === 'Parent') {
          if (!childSelect || childSelect.value === '') {
            document.querySelector('#errorModal .modal-body').textContent = 'Please select a child for the appointment.';
            errorModal.show();
            return;
          }
          window.location.href = `wmsu/MedicalRequest.php?child_id=${childSelect.value}`;
        } else {
          window.location.href = 'wmsu/MedicalRequest.php';
        }
      });

      // Handle Dental Appointment button click
      dentalBtn.addEventListener('click', function() {
        if (userType === 'Parent') {
          if (!childSelect || childSelect.value === '') {
            document.querySelector('#errorModal .modal-body').textContent = 'Please select a child for the appointment.';
            errorModal.show();
            return;
          }
          // Assume children (Kindergarten/Elementary) are under 18
          window.location.href = `wmsu/dentalConsent.php?child_id=${childSelect.value}`;
        } else {
          const age = parseInt(dentalBtn.dataset.age);
          if (!age && age !== 0) {
            document.querySelector('#errorModal .modal-body').textContent = 'Age information not available. Please update your profile.';
            errorModal.show();
            return;
          }
          window.location.href = age < 18 ? 'wmsu/dentalConsent.php' : 'wmsu/dentalrequest.php';
        }
      });

      // Sidebar toggle functionality
      const sidebarToggle = document.getElementById('sidebar-toggle');
      const sidebar = document.getElementById('sidebar');
      const mainContent = document.querySelector('.main-content');
      const overlay = document.querySelector('.content-dim');

      if (sidebarToggle && sidebar && mainContent) {
        sidebarToggle.addEventListener('click', function() {
          sidebar.classList.toggle('open');
          mainContent.classList.toggle('sidebar-open');
          if (overlay) overlay.classList.toggle('active');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
          if (window.innerWidth <= 992) {
            const isClickInsideSidebar = sidebar.contains(event.target);
            const isClickOnToggle = event.target === sidebarToggle;

            if (!isClickInsideSidebar && !isClickOnToggle && sidebar.classList.contains('open')) {
              sidebar.classList.remove('open');
              mainContent.classList.remove('sidebar-open');
              if (overlay) overlay.classList.remove('active');
            }
          }
        });
      }

      // Highlight active button
      const buttons = {
        "homepage": "about-btn",
        "announcements": "announcement-btn",
        "appointment": "appointment-btn",
        "upload": "upload-btn",
        "profile": "profile-btn",
        "logout": "logout-btn"
      };

      const currentPage = window.location.pathname.split("/").pop();
      if (buttons[currentPage]) {
        document.getElementById(buttons[currentPage]).classList.add("active");
      }

      // Handle session errors
      <?php if (isset($_SESSION['error'])): ?>
        document.querySelector('#errorModal .modal-body').textContent = '<?php echo htmlspecialchars($_SESSION['error']); ?>';
        errorModal.show();
        <?php unset($_SESSION['error']); ?>
      <?php endif; ?>
    });
  </script>
</body>

</html>
<?php
$conn->close();
?>