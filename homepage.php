<?php
session_start();
require_once 'config.php';

// Check if the user is logged in
if (isset($_SESSION['user_id'])) {
  $userId = (int)$_SESSION['user_id'];



  // Fetch user data including 'verified'
  $query = "SELECT user_type, documents_uploaded, profile_submitted, verified FROM users WHERE id = ?";
  $stmt = $conn->prepare($query);
  if (!$stmt) {
    error_log("User type query prepare failed: " . $conn->error);
    $_SESSION['error'] = "Database error. Please try again.";
    header("Location: homepage.php");
    exit();
  }
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $result = $stmt->get_result();
  $user = $result->fetch_assoc();
  $stmt->close();



  if (!$user || $user['verified'] != 1) {
    // Not verified, send to login
    $_SESSION['STATUS'] = 'VERIFICATION_FAILED';
    $_SESSION['STATUS_MESSAGE'] = 'Your account is not verified. Please check your email or contact support.';
    header("Location: login.php");
    exit();
  }

  $userType = $user['user_type'] ?? '';
  $documentsUploaded = $user['documents_uploaded'] ?? 0;
  $profileSubmitted = $user['profile_submitted'] ?? 0;

  // Redirect admin users
  if (in_array($userType, ['Super Admin', 'Medical Admin', 'Dental Admin'])) {
    header("Location: adminhome.php");
    exit();
  }

  // Redirect if documents are not uploaded
  if ($documentsUploaded != 1) {
    header("Location: uploaddocs.php");
    exit();
  }

  // Redirect to appropriate form based on profile status and user type
  if ($profileSubmitted != 1) {
    if ($userType === 'Parent') {
      header("Location: Elemform.php");
      exit();
    } elseif (in_array($userType, ['Employee',])) {
      header("Location: EmployeeForm.php");
    } elseif (in_array($userType, ['College', 'Incoming Freshman', 'Highschool', 'Senior High School'])) {
      header("Location: form.php");
      exit();
    }
  }
} else {
  // Not logged in
  header("Location: login.php");
  exit();
}

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


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Homepage - WMSU Health Services</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="stylesheet" href="css/homepage.css">
  <link rel="apple-touch-icon" sizes="180x180" href="images/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="images/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="images/favicon-16x16.png">
  <link rel="manifest" href="images/site.webmanifest">
  <link rel="icon" type="image/x-icon" href="images/favicon.ico">
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
      <button class="btn btn-crimson mb-2 w-100 active" id="about-btn" onclick="window.location.href='homepage.php'">About Us</button>
      <button class="btn btn-crimson mb-2 w-100" id="announcement-btn" onclick="window.location.href='announcements.php'">Announcements</button>
      <button
        class="btn btn-crimson mb-2 w-100 <?php echo ($userType === 'Incoming Freshman') ? 'btn-disabled' : ''; ?>"
        id="appointment-btn"
        <?php if ($userType === 'Incoming Freshman'): ?>
        disabled
        <?php else: ?>
        onclick="window.location.href='/wmsu/appointment.php'"
        <?php endif; ?>>

        Appointment Request
      </button>

      <button class="btn btn-crimson mb-2 w-100" id="upload-btn" onclick="window.location.href='upload.php'">Upload Medical Documents</button>
      <button class="btn btn-crimson mb-2 w-100" id="profile-btn" onclick="window.location.href='profile.php'">Profile</button>
      <button class="btn btn-crimson w-100" id="logout-btn" data-bs-toggle="modal" data-bs-target="#logoutModal">Logout</button>

      <a href="https://wmsu.edu.ph" target="_blank" class="quick-link">wmsu.edu.ph</a>
    </div>

    <div class="main-content">
      <div class="content-dim"></div>
      <section class="about">
        <h2>About Us</h2>
        <p>
          Our team of dedicated healthcare professionals is passionate about ensuring your well-being.
          We are here to address your health concerns, answer your questions, and provide guidance whenever you need it.
        </p>
      </section>

      <section class="welcome">
        <h2>Welcome to Our Community</h2>
        <p>
          We are delighted to have you as part of our online family, and we can't wait to share
          valuable information, wellness tips, and engaging content with you.
        </p>
      </section>

      <section class="staff">
        <h3>Staff of University Health Services</h3>
        <ul>
          <li>
            <img src="images/felicitas.jpg" alt="Dr. Felicitas Asuncion C. Elago">
            <strong>Dr. Felicitas Asuncion C. Elago</strong> - Medical Officer III
          </li>
          <li>
            <img src="images/hamja.jpg" alt="Richard S. Hamja">
            <strong>Richard S. Hamja</strong> - University Nurse
          </li>
          <li>
            <img src="images/krishnon.jpg" alt="Krishnon T. Lauron">
            <strong>Krishnon T. Lauron</strong> - Registered Nurse
          </li>
          <li>
            <img src="images/hilda.jpg" alt="Hilda De Jesus">
            <strong>Hilda De Jesus</strong> - RN - Campus A
          </li>
          <li>
            <img src="images/harold.jpg" alt="Harold Mariano">
            <strong>Harold Mariano</strong> - RN - Campus B
          </li>
          <li>
            <img src="images/gemma.jpg" alt="Gemma Zorayda Sarkis">
            <strong>Gemma Zorayda Sarkis</strong> - RN - Campus C
          </li>
          <li>
            <img src="images/jac.jpg" alt="Jacqueline Casintahan">
            <strong>Jacqueline Casintahan</strong> - Dental Aide
          </li>
          <li>
            <img src="images/joel.jpg" alt="Joel Capa">
            <strong>Joel Capa</strong> - Utility Cleaning Services
          </li>
        </ul>
      </section>

      <section class="core-values">
        <h2>Our Core Values</h2>
        <ul>
          <li><strong>Excellence</strong> - We pursue excellence in everything we do, constantly striving to exceed expectations.</li>
          <li><strong>Integrity</strong> - We conduct business with honesty, transparency, and ethical standards.</li>
          <li><strong>Innovation</strong> - We embrace creativity and forward-thinking to develop cutting-edge solutions.</li>
          <li><strong>Collaboration</strong> - We believe in the power of teamwork and partnerships to achieve shared goals.</li>
        </ul>
      </section>

      <section class="vision-mission">
        <div class="vision-mission-container">
          <div class="vision-mission-card">
            <h2>Vision</h2>
            <p>
              By 2040, WMSU is a Smart Research University generating competent professionals and global citizens engendered
              by knowledge from sciences and liberal education, empowering communities, promoting peace, harmony, and cultural diversity.
            </p>
          </div>

          <div class="vision-mission-card">
            <h2>Mission</h2>
            <p>
              WMSU commits to creating a vibrant atmosphere of learning where science, technology, innovation, research,
              the arts and humanities, and community engagement flourish, producing world-class professionals committed
              to sustainable development and peace.
            </p>
          </div>
        </div>
      </section>
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
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">No</button>
          <button type="button" class="btn btn-sm" style="background-color: #a6192e; color: white;" onclick="window.location.href='logout.php'">Yes</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Profile Update Required Modal - Non-dismissible Version -->
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
    window.history.pushState(null, null, '/wmsu/homepage.php');
    window.addEventListener('popstate', function(event) {
      window.history.pushState(null, null, '/wmsu/homepage.php');
    });

    document.addEventListener("DOMContentLoaded", function() {
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

      // Sidebar toggle functionality
      const sidebarToggle = document.getElementById('sidebar-toggle');
      const sidebar = document.getElementById('sidebar');
      const mainContent = document.querySelector('.main-content');
      const overlay = document.querySelector('.content-dim');

      sidebarToggle.addEventListener('click', function() {
        sidebar.classList.toggle('open');
        mainContent.classList.toggle('sidebar-open');
        overlay.classList.toggle('active');
      });

      // Close sidebar when clicking outside on mobile
      document.addEventListener('click', function(event) {
        if (window.innerWidth <= 992) {
          const isClickInsideSidebar = sidebar.contains(event.target);
          const isClickOnToggle = event.target === sidebarToggle;

          if (!isClickInsideSidebar && !isClickOnToggle && sidebar.classList.contains('open')) {
            sidebar.classList.remove('open');
            mainContent.classList.remove('sidebar-open');
            overlay.classList.remove('active');
          }
        }
      });
    });




    document.addEventListener('DOMContentLoaded', function() {
      const needsUpdate = <?php echo !empty($user_change['profile_update_required']) && $user_change['profile_update_required'] == 1 ? 'true' : 'false'; ?>;

      if (needsUpdate) {
        const updateModal = new bootstrap.Modal(document.getElementById('profileUpdateModal'));
        updateModal.show();
      }
    });
  </script>
</body>



<?php if (isset($_SESSION['login_status']) && $_SESSION['login_status'] == 'success'): ?>
  <script>
    Swal.fire({
      title: 'Login Successful!',
      text: 'Welcome back!',
      icon: 'success',
      confirmButtonText: 'OK'
    });
  </script>
<?php unset($_SESSION['login_status']);
endif; ?>

<?php if (isset($_SESSION['STATUS']) && $_SESSION['STATUS'] == 'SUBMISSION_PROFILE_SUCCESFUL'): ?>
  <script>
    Swal.fire({
      title: 'Health Profile Submission Successful!',
      text: 'Thank you for submitting your health profile!',
      icon: 'success',
      confirmButtonText: 'OK'
    });
  </script>
<?php unset($_SESSION['STATUS']);
endif; ?>

<?php if (isset($_SESSION['STATUS']) && $_SESSION['STATUS'] == 'UPDATE_PROFILE_SUCCESFUL'): ?>
  <script>
    Swal.fire({
      title: 'Health Profile Update Successful!',
      text: 'Thank you for updating your health profile!',
      icon: 'success',
      confirmButtonText: 'OK'
    });
  </script>
<?php unset($_SESSION['STATUS']);
endif; ?>







</html>