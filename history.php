<?php
session_start();
require_once 'config.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check database connection
if (!$conn) {
  error_log("Database connection failed: " . mysqli_connect_error());
  die("Database connection failed. Please contact the administrator.");
}

// Set UTF-8 charset
mysqli_set_charset($conn, 'utf8mb4');

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: /login.php");
  exit();
}

// Fetch all patient history
$stmt = $conn->prepare("SELECT id, user_id, surname, firstname, middlename, archived_at 
                        FROM patients_history 
                        ORDER BY archived_at DESC");
$stmt->execute();
$result = $stmt->get_result();


while ($row = $result->fetch_assoc()) {
  $history[] = $row;
}
$stmt->close();

// Count total patient history records
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM patients_history");
$stmt->execute();
$countResult = $stmt->get_result()->fetch_assoc();
$totalHistory = (int)$countResult['total'];
$stmt->close();

// Fetch all patient history


?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>History and Archiving Management - WMSU Health Services</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="css/patientprofile.css">
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
    .mb-0,
    .card-header,
    .fw-bold {
      font-family: 'Cinzel', serif;
    }
  </style>
</head>

<body>
  <div id="app" class="d-flex">
    <button id="burger-btn" class="burger-btn">☰</button>
    <?php include 'include/sidebar.php'; ?>
    <div class="main-content">
      <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h2 class="mb-0">History and Archiving Management</h2>
        </div>

        <div class="card shadow-sm mb-4">
          <div class="card-header bg-white border-bottom-0">
            <div class="row g-3">
              <div class="col-md">
                <div class="input-group">
                  <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                  <input type="text" id="patientSearch" class="form-control" placeholder="Search patients...">
                  <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                    <i class="fas fa-times"></i>
                  </button>
                </div>
              </div>

            </div>
          </div>

          <div class="card-body p-0">
            <?php if (empty($history)): ?>
              <div class="alert alert-info">No patient history found.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle" id="historyTable">
                  <thead class="table-dark">
                    <tr>
                      <th>Patient ID</th>
                      <th>Full Name</th>
                      <th>Archived At</th>
                      <th>Manage</th>
                    </tr>
                  </thead>
                  <?php
                  $seen_users = [];
                  foreach ($history as $row):
                    if (isset($seen_users[$row['user_id']])) continue; // Skip duplicates
                    $seen_users[$row['user_id']] = true;

                    $full_name = $row['firstname'] . " " . $row['middlename'] . " " . $row['surname'];
                  ?>
                    <tr>

                      <td>P-000<?= htmlspecialchars($row['user_id']) ?></td>
                      <td class="fw-bold"><?= htmlspecialchars($full_name) ?></td>
                      <td class="text-muted"><?= $row['archived_at'] ? date('F j, Y h:i A', strtotime($row['archived_at'])) : '—' ?></td>
                      <td>
                        <button
                          class="btn btn-sm btn-primary view-history-btn"
                          data-patient-id="<?= htmlspecialchars($row['user_id']) ?>"
                          data-patient-name="<?= htmlspecialchars($row['firstname'] . ' ' . $row['surname']) ?>">
                          View
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>

                  </tbody>
                </table>
              </div>
            <?php endif; ?>



            <div class="card-footer bg-white border-top-0">
              <div class="d-flex justify-content-between align-items-center">
                <div class="text-muted">
                  Showing <span id="showingFrom">1</span> to <span id="showingTo"><?php echo $totalHistory ?></span> of <span id="totalRecords"><?php echo $totalHistory ?></span> entries
                </div>
                <nav aria-label="Patient pagination">
                  <ul class="pagination pagination-sm mb-0">
                    <li class="page-item disabled">
                      <a class="page-link" href="#" tabindex="-1">Previous</a>
                    </li>
                    <li class="page-item active"><a class="page-link" href="#">1</a></li>
                    <li class="page-item disabled"><a class="page-link" href="#">Next</a></li>
                  </ul>
                </nav>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Patient History Modal -->
    <div class="modal fade" id="patientHistoryModal" tabindex="-1" aria-labelledby="patientHistoryLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered" data-bs-backdrop="static">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="patientHistoryLabel">Patient History</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div id="patientHistoryContent">
              <p class="text-muted">Loading...</p>
            </div>
          </div>
        </div>
      </div>
    </div>

      <?php include('notifications_admin.php')?>


    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
      // Sidebar Toggle for Mobile
      document.getElementById('burger-btn').addEventListener('click', () => {
        document.getElementById('sidebar').classList.toggle('active');
      });

      // Client-side search and filter
      const patientSearch = document.getElementById('patientSearch');
      const historyTable = document.getElementById('historyTable');
      const rows = historyTable.querySelectorAll('tbody tr');
      const showingFrom = document.getElementById('showingFrom');
      const showingTo = document.getElementById('showingTo');
      const totalRecords = document.getElementById('totalRecords');

      function filterTable() {
        const searchText = patientSearch.value.toLowerCase();

        let visibleRows = 0;

        rows.forEach(row => {
          const name = row.querySelector('.fw-bold').textContent.toLowerCase();
          const email = row.querySelector('.text-muted').textContent.toLowerCase();


          const matchesSearch = name.includes(searchText) || email.includes(searchText);;

          if (matchesSearch) {
            row.style.display = '';
            visibleRows++;
          } else {
            row.style.display = 'none';
          }
        });

        showingFrom.textContent = visibleRows > 0 ? 1 : 0;
        showingTo.textContent = visibleRows;
        totalRecords.textContent = rows.length;
      }

      patientSearch.addEventListener('input', filterTable);

      document.getElementById('clearSearch').addEventListener('click', () => {
        patientSearch.value = '';
        filterTable();
      });

      document.addEventListener('DOMContentLoaded', () => {
        const modal = new bootstrap.Modal(document.getElementById('patientHistoryModal'));
        const contentDiv = document.getElementById('patientHistoryContent');
        const modalTitle = document.getElementById('patientHistoryLabel');

        document.querySelectorAll('.view-history-btn').forEach(button => {
          button.addEventListener('click', () => {
            const patientId = button.getAttribute('data-patient-id');
            const patientName = button.getAttribute('data-patient-name');

            modalTitle.textContent = `Patient History - ${patientName}`;
            contentDiv.innerHTML = "<p class='text-muted'>Loading...</p>";

            fetch(`fetch_patient_history.php?id=${patientId}`)
              .then(response => response.text())
              .then(data => {
                contentDiv.innerHTML = data;
              })
              .catch(err => {
                contentDiv.innerHTML = "<p class='text-danger'>Error loading history.</p>";
              });

            modal.show();
          });
        });
      });
    </script>
</body>

</html>