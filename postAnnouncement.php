<?php
// Include database connection
require_once 'config.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_announcement'])) {
    try {
        $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
        $content = filter_input(INPUT_POST, 'content', FILTER_SANITIZE_STRING);
        $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING);

        // Validate inputs
        if (empty($title) || empty($description) || empty($content) || empty($date)) {
            throw new Exception("All fields are required.");
        }

        // Handle image upload
        $image_path = '../images/default.jpg'; // Default image
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../images/';
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = 'announcement_' . time() . '.' . $ext;
            $image_path = $upload_dir . $filename;
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $image_path)) {
                throw new Exception("Failed to upload image.");
            }
            $image_path = '../images/' . $filename; // Relative path for database
        }

        // Insert into database using mysqli
        $stmt = $conn->prepare("
            INSERT INTO announcements (title, description, content, date, image_path, created_by, is_active)
            VALUES (?, ?, ?, ?, ?, NULL, 1)
        ");
        $stmt->bind_param("sssss", $title, $description, $content, $date, $image_path);
        if (!$stmt->execute()) {
            throw new Exception("Database error: " . $stmt->error);
        }

        $success_message = "Announcement posted successfully!";
        header("Location: ../php/editAnnouncements.php");
        exit;
    } catch (Exception $e) {
        $error_message = "Error posting announcement: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Announcement - WMSU Health Services</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/editAnnouncement.css">
</head>
<body>
    <div id="app" class="d-flex">
        <button id="burger-btn" class="burger-btn">☰</button>
        <div id="sidebar" class="sidebar d-flex flex-column align-items-center">
            <img src="../images/clinic.png" alt="WMSU Clinic Logo" class="logo">
            <div class="text-white fw-bold text-uppercase text-center mt-2">WMSU</div>
            <div class="health-services text-white fw-bold text-uppercase text-center mt-2">Health Services</div>
            <div id="content-management-dropdown" class="dropdown w-100 mb-2">
                <button class="btn btn-crimson dropdown-toggle w-100" type="button" id="cmsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    Content Management
                </button>
                <ul class="dropdown-menu w-100" aria-labelledby="cmsDropdown">
                    <li><a class="dropdown-item" href="../php/editAnnouncements.php" onclick="showLanding()">Landing Page</a></li>
                    <li><a class="dropdown-item" href="../php/editAnnouncements.php" onclick="showHomepageCMS()">Homepage</a></li>
                    <li><a class="dropdown-item" href="../php/editAnnouncements.php" onclick="showAnnouncements()">Announcements</a></li>
                    <li><a class="dropdown-item" href="../php/editAnnouncements.php" onclick="showUploadMedicalCMS()">Upload Medical Documents</a></li>
                </ul>
            </div>
            <button class="btn btn-crimson mb-2 w-100" onclick="window.location.href='adminhome.php'">Dashboard</button>
            <button class="btn btn-crimson mb-2 w-100" onclick="window.location.href='medical-documents.php'">Medical Documents</button>
            <button class="btn btn-crimson mb-2 w-100" onclick="window.location.href='dental-appointments.php'">Dental Consultations</button>
            <button class="btn btn-crimson mb-2 w-100" onclick="window.location.href='medical-appointments.php'">Medical Consultations</button>
            <button class="btn btn-crimson mb-2 w-100" onclick="window.location.href='patient-profile.php'">Patient Profile</button>
            <button class="btn btn-crimson w-100" onclick="window.location.href='admin-account.php'">Admin Account</button>
        </div>
        <div class="overlay"></div>
        <div class="main-content">
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php elseif (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="cms-container">
                <h2 class="text-center mb-4">Post New Announcement</h2>
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="title" class="form-label">Title</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Short Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="content" class="form-label">Full Content</label>
                        <textarea class="form-control" id="content" name="content" rows="6" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="date" class="form-label">Date</label>
                        <input type="date" class="form-control" id="date" name="date" required>
                    </div>
                    <div class="mb-3">
                        <label for="image" class="form-label">Image</label>
                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                    </div>
                    <button type="submit" name="post_announcement" class="btn btn-cms btn-crimson">Post Announcement</button>
                    <a href="../php/editAnnouncements.php" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        const burgerBtn = document.getElementById('burger-btn');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.querySelector('.main-content');
        const overlay = document.querySelector('.overlay');

        burgerBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('active');
            mainContent.classList.toggle('sidebar-active');
            overlay.classList.toggle('active');
        });

        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
                const isClickInsideSidebar = sidebar.contains(event.target);
                const isClickOnBurgerBtn = burgerBtn.contains(event.target);
                if (!isClickInsideSidebar && !isClickOnBurgerBtn) {
                    sidebar.classList.remove('active');
                    mainContent.classList.remove('sidebar-active');
                    overlay.classList.remove('active');
                }
            }
        });
    </script>
</body>
</html>