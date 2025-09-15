<?php
// Include database connection
require_once 'config.php';

// Get announcement ID
$id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

// Fetch announcement
try {
    $stmt = $conn->prepare("SELECT * FROM announcements WHERE announcement_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $announcement = $result->fetch_assoc();
    if (!$announcement) {
        $error_message = "Announcement not found.";
    }
} catch (Exception $e) {
    $error_message = "Error fetching announcement: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_announcement'])) {
    try {
        $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
        $content = filter_input(INPUT_POST, 'content', FILTER_SANITIZE_STRING);
        $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING);
        $is_active = filter_input(INPUT_POST, 'is_active', FILTER_SANITIZE_NUMBER_INT);

        // Validate inputs
        if (empty($title) || empty($description) || empty($content) || empty($date)) {
            throw new Exception("All fields are required.");
        }

        // Handle image upload
        $image_path = $announcement['image_path'];
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'images/';
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = 'announcement_' . time() . '.' . $ext;
            $image_path = $upload_dir . $filename;
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $image_path)) {
                throw new Exception("Failed to upload image.");
            }
            $image_path = 'images/' . $filename;
        }

        // Update database
        $stmt = $conn->prepare("
            UPDATE announcements
            SET title = ?, description = ?, content = ?, date = ?, image_path = ?, is_active = ?
            WHERE announcement_id = ?
        ");
        $stmt->bind_param("sssssii", $title, $description, $content, $date, $image_path, $is_active, $id);
        $stmt->execute();

        $success_message = "Announcement updated successfully!";
        header("Location: editAnnouncements.php");
        exit;
    } catch (Exception $e) {
        $error_message = "Error updating announcement: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Announcement - WMSU Health Services</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/editAnnouncement.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
      <link rel="apple-touch-icon" sizes="180x180" href="images/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="images/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="images/favicon-16x16.png">
    <style>
        body, .cms-container, .form-control, .btn, .main-content, .alert, .cms-container label, .cms-container textarea, .cms-container input, .cms-container select {
            font-family: 'Poppins', sans-serif;
        }
        h1, h2, h3, .cms-container h2, .form-label {
            font-family: 'Cinzel', serif;
        }
    </style>
</head>
<body>
    <div id="app" class="d-flex">
        <button id="burger-btn" class="burger-btn">☰</button>
        <?php include 'include/sidebar.php'; ?>
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

            <?php if ($announcement): ?>
                <div class="cms-container">
                    <h2 class="text-center mb-4">Edit Announcement</h2>
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($announcement['title']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Short Description</label>
                            <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($announcement['description']); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="content" class="form-label">Full Content</label>
                            <textarea class="form-control" id="content" name="content" rows="6" required><?php echo htmlspecialchars($announcement['content']); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="date" class="form-label">Date</label>
                            <input type="date" class="form-control" id="date" name="date" value="<?php echo htmlspecialchars($announcement['date']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="image" class="form-label">Image (Current: <?php echo htmlspecialchars(basename($announcement['image_path'])); ?>)</label>
                            <input type="file" class="form-control" id="image" name="image" accept="image/*">
                        </div>
                        <div class="mb-3">
                            <label for="is_active" class="form-label">Status</label>
                            <select class="form-control" id="is_active" name="is_active" required>
                                <option value="1" <?php echo $announcement['is_active'] ? 'selected' : ''; ?>>Active</option>
                                <option value="0" <?php echo !$announcement['is_active'] ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <button type="submit" name="edit_announcement" class="btn btn-cms btn-crimson">Save Changes</button>
                        <a href="editAnnouncements.php" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            <?php else: ?>
                <div class="alert alert-danger">Announcement not found.</div>
            <?php endif; ?>
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