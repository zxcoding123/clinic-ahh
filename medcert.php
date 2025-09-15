<?php
session_start();

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'config.php';

// Initialize variables with defaults
$name = '';
$age = '';
$sex = 'male'; // Default value
$day = date('j'); // Day without leading zero
$month = date('F'); // Full month name
$year = date('Y'); // Four-digit year
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Validate database connection
if (!$conn) {
    error_log("Database connection failed: " . mysqli_connect_error());
    die("Database connection failed. Please contact the administrator.");
}

// Fetch user data if user_id is provided
if ($user_id > 0) {
    $sql = "
        SELECT 
            CONCAT(u.first_name, ' ', COALESCE(u.middle_name, ''), ' ', u.last_name) AS full_name,
            p.age,
            p.sex,
            p.birthday
        FROM users u
        LEFT JOIN patients p ON u.id = p.user_id
        WHERE u.id = ?
    ";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("Prepare failed: " . $conn->error);
        die("Error preparing query. Please contact the administrator.");
    }

    $stmt->bind_param('i', $user_id);
    if (!$stmt->execute()) {
        error_log("Query execution failed: " . $stmt->error);
        die("Error executing query. Please contact the administrator.");
    }

    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $name = $row['full_name'] ?: 'Unknown User';
        $sex = !empty($row['sex']) ? strtolower($row['sex']) : 'male';
        $age = !empty($row['age']) && $row['age'] > 0 ? $row['age'] : '';
        // Validate birthday (optional, for logging purposes)
        if (!empty($row['birthday']) && $row['birthday'] !== '0000-00-00') {
            try {
                $dob = new DateTime($row['birthday']);
                $today = new DateTime();
                $calculated_age = $today->diff($dob)->y;
                // Log if stored age differs significantly from calculated age
                if ($age && abs($calculated_age - $age) > 1) {
                    error_log("Age mismatch for user_id $user_id: stored=$age, calculated=$calculated_age");
                }
            } catch (Exception $e) {
                error_log("Invalid birthday for user_id $user_id: " . $e->getMessage());
            }
        } else {
            error_log("Invalid or missing birthday for user_id $user_id");
        }
    } else {
        error_log("No user found for user_id: $user_id");
        $name = 'User Not Found';
    }
    $stmt->close();
}

// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Medical Certificate - WMSU Health Services</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
  <link rel="stylesheet" href="css/medcert.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="apple-touch-icon" sizes="180x180" href="images/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="images/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="images/favicon-16x16.png">
  <style>
    /* Button container for better layout */
    .action-buttons {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-top: 20px;
      justify-content: center;
    }
    /* Style for buttons */
    .print-btn, .save-btn, .email-btn, .comment-btn {
      padding: 8px 16px;
      font-size: 0.9rem;
      font-weight: 600;
      text-transform: uppercase;
      border-radius: 4px;
      border: none;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    .print-btn, .save-btn, .email-btn {
      background: #dc3545;
      color: white;
    }
    .print-btn:hover, .save-btn:hover, .email-btn:hover {
      background: #c82333;
    }
    .comment-btn {
      background: #007bff;
      color: white;
    }
    .comment-btn:hover {
      background: #0056b3;
    }
    /* Responsive adjustments */
    @media (max-width: 576px) {
      .action-buttons {
        flex-direction: column;
        align-items: center;
      }
      .print-btn, .save-btn, .email-btn, .comment-btn {
        width: 100%;
        max-width: 200px;
      }
    }
    /* Modal styles */
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.4);
    }
    .modal-content {
      background-color: white;
      padding: 20px;
      border-radius: 10px;
      width: 90%;
      max-width: 500px;
      margin: 10% auto;
      position: relative;
      text-align: center;
    }
    .modal-content .close {
      position: absolute;
      top: 10px;
      right: 15px;
      font-size: 20px;
      cursor: pointer;
    }
    .modal-content .close:hover {
      color: red;
    }
    .modal-content textarea {
      width: 100%;
      height: 150px;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 5px;
      resize: vertical;
      font-size: 14px;
    }
    .modal-content .save-btn {
      background: #dc3545;
      color: white;
      border: none;
      padding: 8px 16px;
      margin-top: 10px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 0.9rem;
      font-weight: 600;
      text-transform: uppercase;
      transition: all 0.2s ease;
    }
    .modal-content .save-btn:hover {
      background: #c82333;
    }
    /* Hide buttons and modal during printing */
    @media print {
      .action-buttons, .comment-btn, .modal {
        display: none !important;
      }
    }
  </style>
</head>
<body>
  <div id="app" class="d-flex">
    <button id="burger-btn" class="burger-btn">☰</button>
   
    <!-- Sidebar --> 
    <div id="sidebar" class="sidebar d-flex flex-column align-items-center">
      <img src="../images/clinic.png" alt="WMSU Clinic Logo" class="logo">
      <div class="text-white fw-bold text-uppercase text-center mt-2">WMSU</div>
      <div class="health-services text-white fw-bold text-uppercase text-center mt-2">Health Services</div>
      <!-- Content Management Dropdown -->
      <div id="content-management-dropdown" class="dropdown w-100 mb-2">
        <button class="btn btn-crimson dropdown-toggle w-100" type="button" id="cmsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
          Content Management
        </button>
        <ul class="dropdown-menu w-100" aria-labelledby="cmsDropdown">
          <li><a class="dropdown-item" href="editAnnouncement">Landing Page</a></li>
          <li><a class="dropdown-item" href="editAnnouncement">Homepage</a></li>
          <li><a class="dropdown-item" href="editAnnouncement">Announcements</a></li>
          <li><a class="dropdown-item" href="editAnnouncement">Upload Medical Documents</a></li>
        </ul>
      </div>
      <button class="btn btn-crimson mb-2 w-100" id="dashboard-btn" onclick="window.location.href='adminhome'">Dashboard</button>
      <button class="btn btn-crimson mb-2 w-100 active" id="medical-documents-btn" onclick="window.location.href='medical-documents.php'">Medical Documents</button>
      <button class="btn btn-crimson mb-2 w-100" id="dental-appointments-btn" onclick="window.location.href='dental-appointments.php'">Dental Consultations</button>
      <button class="btn btn-crimson mb-2 w-100" id="medical-appointments-btn" onclick="window.location.href='medical-appointments.php'">Medical Consultations</button>
      <button class="btn btn-crimson mb-2 w-100" id="patient-profile-btn" onclick="window.location.href='patient-profile.php'">Patient Profile</button>
      <button class="btn btn-crimson w-100" id="admin-account-btn" onclick="window.location.href='admin-account.php'">Admin Account</button>
    </div> 
     
    <div class="main-content">
      <div class="medical-certificate-container">
        <!-- Comment Section Button -->
        <button class="comment-btn" onclick="openCommentModal()">📝 Add Comment</button>

        <div class="header">
          <img src="../images/logo.png" alt="WMSU Logo" class="logo-left">
          <div class="university-info">
            <h2>WESTERN MINDANAO STATE UNIVERSITY</h2>
            <h3>ZAMBOANGA CITY</h3>
            <h4>UNIVERSITY HEALTH SERVICES CENTER</h4>
            <p>Tel. no. (062) 991-6736 / Email: healthservices@wmsu.edu.ph</p>
          </div>
          <img src="../images/clinic.png" alt="Clinic Logo" class="logo-right">
        </div>

        <h2 class="title">MEDICAL CERTIFICATE</h2>

        <form id="medical-cert-form">
          <p class="to-whom">To whom it may concern:</p>

          <p>This is to certify that 
            <input type="text" name="name" value="<?php echo htmlspecialchars($name); ?>" placeholder="Full Name" required class="underline">, 
            a <input type="number" name="age" value="<?php echo htmlspecialchars($age); ?>" placeholder="Age" required class="small-underline">-year-old 
            <select name="gender" class="small-underline">
              <option value="male" <?php echo $sex === 'male' ? 'selected' : ''; ?>>male</option>
              <option value="female" <?php echo $sex === 'female' ? 'selected' : ''; ?>>female</option>
            </select>, has been clinically assessed by the University Health Services Center and was deemed 
            <strong>physically fit for college admission</strong>.
          </p>

          <p>
            Chest radiography and laboratory test results were reviewed. 
            He/She has no unstable comorbid illnesses nor any maintenance medications. 
            Hence, there are no contraindications for school-related activities.
          </p>

          <p>
            This certification is being issued upon request of 
            <input type="text" name="requestor" value="<?php echo htmlspecialchars($name); ?>" placeholder="Full Name" required class="underline"> 
            for whatever purpose it may serve him/her best.
          </p>

          <p>
            Given this 
            <input type="number" name="day" value="<?php echo htmlspecialchars($day); ?>" placeholder="Day" required class="small-underline">th 
            day of 
            <input type="text" name="month" value="<?php echo htmlspecialchars($month); ?>" placeholder="Month" required class="small-underline">, 
            <input type="number" name="year" value="<?php echo htmlspecialchars($year); ?>" placeholder="Year" required class="small-underline">
            in the City of Zamboanga, Philippines.
          </p>

          <div class="doctor-info">
            <p class="doctor-title">FELICITAS ASUNCION C. ELAGO, M.D.</p>
            <p class="doctor-title">MEDICAL OFFICER III</p>
            <p class="doctor-title">LICENSE NO. 0160267</p>
            <p class="doctor-title">PTR NO. 2795114</p>
          </div>

          <!-- Action Buttons -->
          <div class="action-buttons">
            <button type="button" class="print-btn" onclick="printCertificate()">Print Certificate</button>
            <button type="button" class="save-btn" onclick="saveCertificate()">Save Certificate</button>
            <button type="button" class="email-btn" onclick="sendViaEmail()">Send via Email</button>
          </div>
        </form>
      </div>
    </div>
    
    <!-- Comment Modal -->
    <div id="comment-modal" class="modal">
      <div class="modal-content">
        <span class="close" onclick="closeCommentModal()">×</span>
        <h3>Add Comments</h3>
        <textarea id="comment-text" placeholder="Enter your notes here..."></textarea>
        <button class="save-btn" onclick="saveComment()">Save</button>
      </div>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
  <script>
    // Sidebar and Dropdown functionality
    document.addEventListener('DOMContentLoaded', function() {
      const burgerBtn = document.getElementById('burger-btn');
      const sidebar = document.getElementById('sidebar');
      const mainContent = document.getElementById('main-content');

      // Burger button toggle
      if (burgerBtn) {
        burgerBtn.addEventListener('click', function(e) {
          e.stopPropagation();
          sidebar.classList.toggle('active');
          mainContent.classList.toggle('sidebar-active');
        });
      }

      // Close sidebar when clicking outside on mobile
      document.addEventListener('click', function(event) {
        if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
          const isClickInsideSidebar = sidebar.contains(event.target);
          const isClickOnBurgerBtn = burgerBtn.contains(event.target);
          if (!isClickInsideSidebar && !isClickOnBurgerBtn) {
            sidebar.classList.remove('active');
            mainContent.classList.remove('sidebar-active');
          }
        }
      });

      // Close sidebar when clicking sidebar buttons on mobile
      const sidebarButtons = document.querySelectorAll('#sidebar .btn-crimson:not(#cmsDropdown), #sidebar .dropdown-item');
      sidebarButtons.forEach(button => {
        button.addEventListener('click', function() {
          if (window.innerWidth <= 768) {
            sidebar.classList.remove('active');
            mainContent.classList.remove('sidebar-active');
          }
        });
      });
    });

    // Function to print the form
    function printCertificate() {
      window.print();
    }

    // Function to save the certificate as a PDF
    function saveCertificate() {
      let element = document.querySelector(".medical-certificate-container");
      let filename = 'Medical_Certificate<?php echo $name ? '_' . str_replace(' ', '_', htmlspecialchars($name)) : ''; ?>.pdf';
      let opt = {
        margin: 10,
        filename: filename,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 },
        jsPDF: { format: 'a4', orientation: 'portrait' }
      };
      html2pdf().from(element).set(opt).save();
    }

    // Function to send the certificate via email (placeholder)
    function sendViaEmail() {
      alert("Sending certificate via email... (This is a placeholder. Actual email functionality requires server-side implementation.)");
    }

    // Open the comment modal
    function openCommentModal() {
      document.getElementById("comment-modal").style.display = "block";
    }

    // Close the comment modal
    function closeCommentModal() {
      document.getElementById("comment-modal").style.display = "none";
    }

    // Save comment
    function saveComment() {
      let comment = document.getElementById("comment-text").value;
      if (comment.trim()) {
        alert("Comment saved: " + comment);
        closeCommentModal();
      } else {
        alert("Please enter a comment before saving.");
      }
    }
  </script>
</body>
</html>