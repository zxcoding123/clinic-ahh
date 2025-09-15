<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  if (!empty($_POST)) {
    $_SESSION['form_data'] = $_POST; // Preserve form data
  }
  header('Location: /index');
  exit;
}

// Fetch user data
$userId = $_SESSION['user_id'];
$query = 'SELECT user_type FROM users WHERE id = ?';
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  session_unset();
  session_destroy();
  header('Location: index.php?message=Invalid+session');
  exit;
}

$user = $result->fetch_assoc();
$userType = $user['user_type'] ?? '';

// Fetch Announcements
$announcements = [];
$error_message = '';

$result = $conn->query("SELECT announcement_id, title, description, date, image_path FROM announcements WHERE is_active = 1 ORDER BY date DESC");
if ($result) {
  $announcements = $result->fetch_all(MYSQLI_ASSOC);
} else {
  $error_message = "Error fetching announcements: " . $conn->error;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Announcements - WMSU Health Services</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
  <link rel="stylesheet" href="css/announcements.css">
  <link rel="apple-touch-icon" sizes="180x180" href="images/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="images/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="images/favicon-16x16.png">
  <link rel="manifest" href="images/site.webmanifest">
  <style>
    body {
      font-family: 'Poppins';
    }

    .active {
      background-color: #4f1515 !important;
      color: white !important;
    }

    .quick-link {
      margin-top: auto;
      margin-bottom: 1rem;
      color: white;
      text-decoration: none;
      font-size: 0.9rem;
    }

    .overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      z-index: 998;
      display: none;
    }

    .overlay.active {
      display: block;
    }

    .btn-disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }
  </style>
</head>

<body>
  <div id="app">
    <button id="sidebar-toggle" class="sidebar-toggle d-lg-none">☰</button>
    <div id="sidebar" class="sidebar d-flex flex-column align-items-center">
      <img src="images/clinic.png" alt="WMSU Clinic Logo" class="logo">
      <div class="text-white fw-bold text-uppercase text-center mt-2">WMSU</div>
      <div class="health-services text-white fw-bold text-uppercase text-center mt-2 mb-5">Health Services</div>

      <button class="btn btn-crimson mb-2 w-100" id="about-btn" onclick="window.location.href='homepage.php'">About Us</button>
      <button class="btn btn-crimson mb-2 w-100 active" id="announcement-btn" onclick="window.location.href='announcements.php'">Announcements</button>
      <button class="btn btn-crimson mb-2 w-100 <?php echo ($userType === 'Incoming Freshman') ? 'btn-disabled' : ''; ?>"
        id="appointment-btn"
        <?php echo ($userType === 'Incoming Freshman') ? 'disabled' : ''; ?>
        onclick="<?php echo ($userType === 'Incoming Freshman') ? '' : 'window.location.href=\'/wmsu/appointment.php\''; ?>">
        Appointment Request
      </button>
      <button class="btn btn-crimson mb-2 w-100" id="upload-btn" onclick="window.location.href='upload.php'">Upload Medical Documents</button>
      <button class="btn btn-crimson mb-2 w-100" id="profile-btn" onclick="window.location.href='profile.php'">Profile</button>
      <button class="btn btn-crimson w-100" id="logout-btn" data-bs-toggle="modal" data-bs-target="#logoutModal">Logout</button>

      <a href="https://wmsu.edu.ph" target="_blank" class="quick-link">wmsu.edu.ph</a>
    </div>
    <div class="overlay"></div>
    <div class="main-content">
      <div class="announcements-container">
        <h1 class="text-center fw-bold">Announcements</h1>
        <div class="announcement-list">
          <?php if (empty($announcements)): ?>
            <p class="text-muted text-center">No announcements available.</p>
          <?php else: ?>
            <?php foreach ($announcements as $announcement): ?>
              <div class="announcement-item" data-id="<?php echo $announcement['announcement_id']; ?>">
                <div class="announcement-image">
                  <img src="<?php echo htmlspecialchars($announcement['image_path']); ?>" alt="<?php echo htmlspecialchars($announcement['title']); ?>">
                </div>
                <div class="announcement-content">
                  <h4><?php echo htmlspecialchars($announcement['title']); ?></h4>
                  <p class="announcement-date">Date: <?php echo date('F j, Y', strtotime($announcement['date'])); ?></p>
                  <p class="announcement-description"><?php echo htmlspecialchars($announcement['description']); ?></p>
                  <a href="announcement1.php?id=<?php echo $announcement['announcement_id']; ?>" class="read-more">Read more</a>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
          <?php if (isset($error_message)): ?>
          <?php endif; ?>
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
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">No</button>
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
    // Sidebar toggle functionality
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    const overlay = document.querySelector('.overlay');

    sidebarToggle.addEventListener('click', function(e) {
      e.stopPropagation();
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

    // Navigate to single announcement
    document.querySelectorAll('.announcement-item').forEach(item => {
      item.addEventListener('click', () => {
        const id = item.getAttribute('data-id');
        window.location.href = `announcement1.php?id=${id}`;
      });
    });
  </script>
</body>

</html>