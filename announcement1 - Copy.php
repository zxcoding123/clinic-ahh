<?php
// Include database connection
require_once 'config.php';


  
  $userType = $user['user_type'] ?? '';
  
// Get announcement ID
$id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);


$announcement = null;
$error_message = '';

$stmt = $conn->prepare("SELECT * FROM announcements WHERE announcement_id = ? ");
$stmt->bind_param("i", $id); // Assuming $id is an integer
if ($stmt->execute()) {
    $result = $stmt->get_result();
    $announcement = $result->fetch_assoc();
   
  
} 

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcement - WMSU Health Services</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
      <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/announcement1.css">
    <link rel="icon" type="image/png" sizes="32x32" href="images/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="images/favicon-16x16.png">
  <style>
        *{
            font-family: 'Poppins';
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
 
      <button class="btn btn-crimson mb-2 w-100 " id="about-btn" onclick="window.location.href='/homepage'">About Us</button>
      <button class="btn btn-crimson mb-2 w-100 active" id="announcement-btn" onclick="window.location.href='/announcements'">Announcements</button>
      <button class="btn btn-crimson mb-2 w-100 <?php echo ($userType === 'Incoming Freshman') ? 'btn-disabled' : ''; ?>" 
              id="appointment-btn" 
              <?php echo ($userType === 'Incoming Freshman') ? 'disabled' : ''; ?>
              onclick="<?php echo ($userType === 'Incoming Freshman') ? '' : 'window.location.href=\'/appointment\''; ?>">
              Appointment Request
      </button>
      <button class="btn btn-crimson mb-2 w-100" id="upload-btn" onclick="window.location.href='/upload'">Upload Medical Documents</button>
      <button class="btn btn-crimson mb-2 w-100" id="profile-btn" onclick="window.location.href='/profile'">Profile</button>
      <button class="btn btn-crimson w-100" id="logout-btn" onclick="window.location.href='/logout'">Logout</button>

      <a href="https://wmsu.edu.ph" target="_blank" class="quick-link">wmsu.edu.ph</a>
    </div>
        <div class="overlay"></div>
        <div class="main-content">
            <div class="announcement-container">
                <button class="back-button" onclick="window.location.href='announcements.php'">← Back</button>
              
                    <img src="<?php echo htmlspecialchars($announcement['image_path']); ?>" alt="<?php echo htmlspecialchars($announcement['title']); ?>" class="announcement-image">
                    <h1 class="announcement-title"><?php echo htmlspecialchars($announcement['title']); ?></h1>
                    <div class="announcement-content">
                        <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($announcement['date'])); ?></p>
                        <?php echo nl2br($announcement['content']); ?>
                    </div>
              
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle functionality
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.querySelector('.main-content');
        const overlay = document.querySelector('.overlay');

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
    </script>
</body>
</html>