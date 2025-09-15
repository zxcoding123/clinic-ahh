<?php
session_start();
require_once 'config.php';

// Ensure upload directory exists
$uploadDir = 'Uploads/StaffImages/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// UPDATE existing announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_announcement'])) {
    $id = $_POST['announcement_id'];
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $content = $_POST['content'] ?? '';
    $date = $_POST['date'] ?? date('Y-m-d');
    $location = $_POST['location'] ?? '';

    // Handle optional new image
    $image_path = $_POST['current_image'] ?? '';
    if (!empty($_FILES['image']['name'])) {
        $targetDir = 'uploads/announcements/';
        $fileName = basename($_FILES['image']['name']);
        $targetFilePath = $targetDir . $fileName;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFilePath)) {
            $image_path = $targetFilePath;
        }
    }

    // Update SQL
    $stmt = $conn->prepare("UPDATE announcements SET title=?, description=?, content=?, date=?, image_path=?, location=?, updated_at=NOW() WHERE announcement_id=?");
    $stmt->bind_param("ssssssi", $title, $description, $content, $date, $image_path, $location, $id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Announcement updated successfully.";
    } else {
        $_SESSION['error'] = "Failed to update announcement.";
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $announcement_id = filter_input(INPUT_POST, 'announcement_id', FILTER_SANITIZE_NUMBER_INT);
    $action = $_POST['action']; // 'publish' or 'unpublish'

    try {
        if ($action === 'publish') {
            $stmt = $conn->prepare("UPDATE announcements SET is_active = 1 WHERE announcement_id = ?");
        } else {
            $stmt = $conn->prepare("UPDATE announcements SET is_active = 0 WHERE announcement_id = ?");
        }
        $stmt->execute([$announcement_id]);
        $success_message = ($action === 'publish' ? 'Published!' : 'Unpublished successfully!');
    } catch (PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}



// Handle announcement deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_announcement'])) {
    try {
        $announcement_id = filter_input(INPUT_POST, 'announcement_id', FILTER_SANITIZE_NUMBER_INT);
        $stmt = $conn->prepare("DELETE FROM announcements WHERE announcement_id = ?");
        $stmt->execute([$announcement_id]);
        $success_message = "Announcement deleted permanently!";
        $_SESSION['success'] =   $success_message;
    } catch (PDOException $e) {

        $error_message = "Error deleting announcement: " . $e->getMessage();
        $_SESSION['error'] =    $error_message;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_announcement'])) {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $content = $_POST['content'] ?? '';
    $date = $_POST['date'] ?? date('Y-m-d');
    $location = $_POST['location'] ?? '';
    $created_by = $_SESSION['user_id']; // Assumes user is logged in

    // Handle image upload or default
    $image_path = 'uploads/announcements/default_announcement.jpg'; // Default image
    if (!empty($_FILES['image']['name'])) {
        $targetDir = 'uploads/announcements/';
        $fileName = basename($_FILES['image']['name']);
        $targetFilePath = $targetDir . $fileName;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFilePath)) {
            $image_path = $targetFilePath;
        }
    }

    // Insert into announcements table
    $stmt = $conn->prepare("INSERT INTO announcements (title, description, content, date, image_path, location, created_by, created_at, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 0)");
    $stmt->bind_param("ssssssi", $title, $description, $content, $date, $image_path, $location, $created_by);
    if ($stmt->execute()) {
        // Optional: success message or redirect
        $_SESSION['success'] = 'Announcement created successfully.';
    } else {
        $_SESSION['error'] = 'Failed to create announcement.';
    }
    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Homepage - WMSU Health Services</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/adminhome.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Poppins:wght@400;500;700&display=swap">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
      <link rel="apple-touch-icon" sizes="180x180" href="images/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="images/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="images/favicon-16x16.png">
    <!-- Quill CSS -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet" />
    <!-- Quill JS -->
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
          <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <style>
             .dropdown-item.d-flex.align-items-center.active {
  background-color: #8B0000; /* or whatever color */
}
        body,
        .sidebar,
        .sidebar-nav,
        .sidebar-footer,
        .dropdown-menu,
        .btn-crimson,
        .dropdown-item {
            font-family: 'Poppins', sans-serif;
        }

        .dashboard-header,
        h1,
        h2,
        h3,
        .brand-text {
            font-family: 'Cinzel', serif;
        }

        .btn-dark-crimson:hover {
            color: white !important;
        }

        #preview-area {
            min-height: 200px;
            max-height: 350px;
            overflow-y: auto;
            padding: 10px;
            word-wrap: break-word;
        }


        #edit-preview-area-new {
            min-height: 200px !important;
            max-height: 350px !important;
            overflow-y: auto !important;
            padding: 10px !important;
            word-wrap: break-word !important;
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
                <div class="d-flex align-items-center">
                    <h2 class="small-heading">Announcements</h2>
                    <div class="ms-auto" aria-hidden="true">
                        <!-- Button trigger modal -->
                        <button type="button" class="btn btn-dark-crimson" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
                            Create Announcement
                        </button>




                        <div class="modal fade" id="createAnnouncementModal" tabindex="-1" aria-labelledby="createAnnouncementLabel" aria-hidden="true">
                            <div class="modal-dialog modal-xl">
                                <div class="modal-content">
                                    <form action="" method="POST" enctype="multipart/form-data" id="announcementForm">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="createAnnouncementLabel">Create Announcement</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>

                                        <div class="modal-body">
                                            <div class="row">
                                                <!-- Preview section -->
                                                <div class="col-md-6 border-end">
                                                    <h6 class="text-center">Preview of Announcement Post</h6> <br>
                                                    <img id="preview-image" src="images/wmsu.png" class="img-fluid mb-3" alt="Preview Image">
                                                    <h4 id="preview-title" class="fw-bold mb-2">Announcement Title</h4>
                                                    <p id="preview-description" class="text-muted">Short description...</p>
                                                    <hr>
                                                    <div id="preview-area" style="min-height:200px;">(Content preview here)</div>
                                                </div>


                                                <!-- Form section -->
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Title</label>
                                                        <input type="text" name="title" class="form-control" required oninput="document.getElementById('preview-title').textContent = this.value">
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Description</label>
                                                        <textarea name="description" class="form-control" rows="2" required oninput="document.getElementById('preview-description').textContent = this.value"></textarea>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Content</label>
                                                        <!-- Quill editor -->
                                                        <div id="editor" style="height:200px;"></div>
                                                        <!-- Hidden input to store HTML -->
                                                        <input type="hidden" name="content" id="content-hidden">
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Date</label>
                                                        <input type="date" name="date" class="form-control">
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Location</label>
                                                        <input type="text" name="location" class="form-control">
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Image (optional)</label>
                                                        <input type="file" name="image" class="form-control" onchange="previewImage(event)">

                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="create_announcement" class="btn btn-dark-crimson">Create Announcement</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>

                <br>
                <input type="text" id="announcementSearch" class="form-control mb-3" placeholder="Search by title or description...">
                <style>
                    table {
                        border-radius: 20px !important;
                    }

                    th {
                        background-color: #8B0000 !important;
                        color: white !important;
                        text-transform: uppercase;
                    }
                </style>
                <hr>
                <div class="container-fluid">

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="announcementsTable">
                            <thead>
                                <tr>
                                    <th style="width: 40px;"></th>
                                    <th>Title</th>
                                    <th>Description</th>
                                    <th>Date</th>

                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <?php
                            $result = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC");
                            while ($row = $result->fetch_assoc()) {

                                $isPublished = $row['is_active'];
                                $buttonLabel = $isPublished ? 'Unpublish' : 'Publish';
                                $actionType  = $isPublished ? 'unpublish' : 'publish';

                                echo "<tr>";
                                echo "<td>{$row['announcement_id']}</td>";
                                echo "<td>{$row['title']}</td>";
                                echo "<td>{$row['description']}</td>";
                     echo "<td>" . date("F j, Y", strtotime($row['date'])) . "</td>";

                                echo "
                           
<td>
    <button class='btn btn-primary btn-sm' data-bs-toggle='modal' data-bs-target='#viewModal{$row['announcement_id']}'>View</button>

    <button class='btn btn-light btn-sm publish-toggle-btn' data-id=\"{$row['announcement_id']}\" data-action=\"$actionType\">
        $buttonLabel
    </button>

    <button class='btn btn-warning btn-sm' data-bs-toggle='modal' data-bs-target='#editModal{$row['announcement_id']}'>Edit</button>

    <button class='btn btn-danger btn-sm delete-btn' data-id='{$row['announcement_id']}'>Delete</button>
</td>
";

                                echo "</tr>";

                                /* View Modal (Preview Only) */
                                echo "
    <div class='modal fade' id='viewModal{$row['announcement_id']}' tabindex='-1' aria-hidden='true'>
      <div class='modal-dialog modal-lg'>
        <div class='modal-content'>
          <div class='modal-header'>
            <h5 class='modal-title'>{$row['title']}</h5>
            <button type='button' class='btn-close' data-bs-dismiss='modal'></button>
          </div>
          <div class='modal-body'>
            <img src='{$row['image_path']}' class='img-fluid mb-3'>
            <p class='text-muted'>{$row['description']}</p>
            <div>{$row['content']}</div>
            <hr>
            <p>Date: {$row['date']} | Location: {$row['location']}</p>
          </div>
        </div>
      </div>
    </div>";

                                /* Edit Modal */
                                echo "
<div class='modal fade' id='editModal{$row['announcement_id']}' tabindex='-1' aria-hidden='true'>
  <div class='modal-dialog modal-xl'>
    <div class='modal-content'>
      <form id='editForm{$row['announcement_id']}' action='' method='POST' enctype='multipart/form-data'>
        <div class='modal-header'>
          <h5 class='modal-title'>Edit Announcement</h5>
          <button type='button' class='btn-close' data-bs-dismiss='modal'></button>
        </div>

        <div class='modal-body'>
          <input type='hidden' name='announcement_id' value='{$row['announcement_id']}'>
          <input type='hidden' name='current_image' value='{$row['image_path']}'>

          <div class='row'>
            <div class='col-md-6 border-end'>
              <h6 class='text-center'>Preview</h6><br>
              <img src='{$row['image_path']}' class='img-fluid mb-3' id='edit-preview-image{$row['announcement_id']}'>
              <h4 id='edit-preview-title{$row['announcement_id']}' class='fw-bold mb-2'>{$row['title']}</h4>
              <p id='edit-preview-description{$row['announcement_id']}' class='text-muted'>{$row['description']}</p>
              <hr>
            <div id='edit-preview-area{$row['announcement_id']}' style='min-height:200px; max-height:300px; overflow-x:auto;'>
    {$row['content']}
</div>


            </div>

            <div class='col-md-6'>
              <div class='mb-3'>
                <label class='form-label fw-semibold'>Title</label>
                <input type='text' name='title' class='form-control' value='{$row['title']}' 
                       oninput=\"document.getElementById('edit-preview-title{$row['announcement_id']}').textContent = this.value\">
              </div>

              <div class='mb-3'>
                <label class='form-label fw-semibold'>Description</label>
                <textarea name='description' class='form-control'
                          oninput=\"document.getElementById('edit-preview-description{$row['announcement_id']}').textContent = this.value\">{$row['description']}</textarea>
              </div>

              <div class='mb-3'>
                <label class='form-label fw-semibold'>Content</label>
                <div id='quill-edit{$row['announcement_id']}' class='quill-editor' style='height:200px;'></div>
                <input type='hidden' name='content' id='edit-content-hidden{$row['announcement_id']}'>
              </div>

              <div class='mb-3'>
                <label class='form-label fw-semibold'>Date</label>
                <input type='date' name='date' class='form-control' value='{$row['date']}'>
              </div>

              <div class='mb-3'>
                <label class='form-label fw-semibold'>Location</label>
                <input type='text' name='location' class='form-control' value='{$row['location']}'>
              </div>

              <div class='mb-3'>
                <label class='form-label fw-semibold'>Image</label>
                <input type='file' name='image' class='form-control'>
              </div>
            </div>
          </div>
        </div>

        <div class='modal-footer'>
          <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Cancel</button>
          <button type='submit' name='update_announcement' class='btn btn-dark-crimson'>Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>";
                            }
                            ?>


                            <?php
                            // deletion form
                            ?>
                            <form id="deleteForm" method="POST" style="display: none;">
                                <input type="hidden" name="delete_announcement" value="1">
                                <input type="hidden" name="announcement_id" id="delete-announcement-id">
                            </form>

                            <?php
                            // edition form
                            ?>

                            <form id="togglePublishForm" method="POST" style="display:none;">
                                <input type="hidden" name="action" id="publish-action">
                                <input type="hidden" name="announcement_id" id="publish-id">
                            </form>

                        </table>
                    </div>
                </div>


            </div>

            <!-- <div id="modalBackdrop" class="modal-backdrop"></div> -->


            <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
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
                document.getElementById('announcementSearch').addEventListener('keyup', function() {
                    const q = this.value.toLowerCase().trim();
                    const rows = document.querySelectorAll('#announcementsTable tbody tr');
                    let visibleCount = 0;

                    rows.forEach(row => {
                        // Skip the no-results row if it already exists
                        if (row.id === 'no-results-row') return;

                        const title = row.children[1].textContent.toLowerCase();
                        const desc = row.children[2].textContent.toLowerCase();

                        if (q === '' || title.includes(q) || desc.includes(q)) {
                            row.style.display = '';
                            visibleCount++;
                        } else {
                            row.style.display = 'none';
                        }
                    });

                    // Get any existing "no results" placeholder row
                    let emptyRow = document.getElementById('no-results-row');

                    // If search query isn't empty and no visible rows found, show "no results"
                    if (q !== '' && visibleCount === 0) {
                        if (!emptyRow) {
                            const tbody = document.querySelector('#announcementsTable tbody');
                            const tr = document.createElement('tr');
                            tr.id = 'no-results-row';
                            tr.innerHTML = `<td colspan="5" class="text-center text-muted py-4">No announcements found.</td>`;
                            tbody.appendChild(tr);
                        }
                    } else {
                        // Otherwise, remove the "no results" row if it exists
                        if (emptyRow) {
                            emptyRow.remove();
                        }
                    }
                });



                // Initialize Quill
                var quill = new Quill('#editor', {
                    theme: 'snow',
                    placeholder: 'Write the announcement content here...'
                });

                // Update preview in real-time:
                quill.on('text-change', function() {
                    document.getElementById('preview-area').innerHTML = quill.root.innerHTML;
                });

                // Set the hidden input value on form submit
                const form = document.getElementById('announcementForm');
                form.addEventListener('submit', function() {
                    document.getElementById('content-hidden').value = quill.root.innerHTML;
                });

                // Image Preview
                function previewImage(event) {
                    const imageElement = document.getElementById('preview-image');
                    const file = event.target.files[0];
                    if (file) {
                        imageElement.src = URL.createObjectURL(file);
                    } else {
                        imageElement.src = 'images/wmsu.png';
                    }
                }

                document.addEventListener("DOMContentLoaded", function() {
                    document.querySelectorAll('.quill-editor').forEach(function(editorDiv) {
                        const id = editorDiv.getAttribute('id').replace('quill-edit', '');
                        const quill = new Quill('#quill-edit' + id, {
                            theme: 'snow'
                        });

                        const previewArea = document.getElementById('edit-preview-area' + id);
                        const previewTitle = document.getElementById('edit-preview-title' + id);
                        const previewDesc = document.getElementById('edit-preview-description' + id);

                        // Load current HTML content into Quill
                        quill.root.innerHTML = previewArea.innerHTML;

                        // Live update to preview when quill text changes
                        quill.on('text-change', function() {
                            previewArea.innerHTML = quill.root.innerHTML;
                        });

                        // Live update for title & description
                        document.querySelector(`input[name="title"][data-id="${id}"]`)?.addEventListener('input', function() {
                            previewTitle.textContent = this.value;
                        });
                        document.querySelector(`textarea[name="description"][data-id="${id}"]`)?.addEventListener('input', function() {
                            previewDesc.textContent = this.value;
                        });

                        // Hook file image preview
                        document.querySelector(`input[name="image"][data-id="${id}"]`)?.addEventListener('change', function(e) {
                            const file = e.target.files[0];
                            if (file) {
                                document.getElementById('edit-preview-image' + id).src = URL.createObjectURL(file);
                            }
                        });

                        // On form submit
                        const form = document.getElementById('editForm' + id);
                        form.addEventListener('submit', function() {
                            document.getElementById('edit-content-hidden' + id).value = quill.root.innerHTML;
                        });
                    });
                });
                document.addEventListener('DOMContentLoaded', function() {
                    document.querySelectorAll('.publish-toggle-btn').forEach(function(btn) {
                        btn.addEventListener('click', function() {
                            const annId = this.getAttribute('data-id');
                            const action = this.getAttribute('data-action'); // 'publish' or 'unpublish'
                            const isPublish = action === 'publish';

                            Swal.fire({
                                title: isPublish ? 'Publish this announcement?' : 'Unpublish this announcement?',
                                icon: 'question',
                                showCancelButton: true,
                                confirmButtonText: isPublish ? 'Yes, publish!' : 'Yes, unpublish!'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    document.getElementById('publish-action').value = action;
                                    document.getElementById('publish-id').value = annId;
                                    document.getElementById('togglePublishForm').submit();
                                }
                            });
                        });
                    });
                });


                document.addEventListener('DOMContentLoaded', function() {
                    document.querySelectorAll('.delete-btn').forEach(function(btn) {
                        btn.addEventListener('click', function() {
                            const annId = this.getAttribute('data-id');
                            Swal.fire({
                                title: 'Are you sure?',
                                text: 'This announcement will be deleted.',
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonText: 'Yes, delete it!'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    // inject ID into hidden form and submit
                                    document.getElementById('delete-announcement-id').value = annId;
                                    document.getElementById('deleteForm').submit();
                                }
                            });
                        });
                    });
                });
            </script>



            <?php if (isset($_SESSION['success'])): ?>
                <script>
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: '<?php echo $_SESSION['success']; ?>'
                    });
                </script>
            <?php unset($_SESSION['success']);
            endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: '<?php echo $_SESSION['error']; ?>'
                    });
                </script>
            <?php unset($_SESSION['error']);
            endif; ?>





</body>

</html>