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

// Function to sanitize data for JSON encoding
function utf8ize($data)
{
  if ($data === null) {
    return null; // Handle null values
  }
  if (is_array($data)) {
    return array_map('utf8ize', $data);
  }
  return mb_convert_encoding($data, 'UTF-8', 'UTF-8');
}

$query = "
SELECT 
    p.id,
    p.user_id AS patient_id,
    p.surname COLLATE utf8mb4_general_ci AS surname,
    p.firstname COLLATE utf8mb4_general_ci AS firstname,
    p.middlename COLLATE utf8mb4_general_ci AS middlename,
    p.suffix COLLATE utf8mb4_general_ci AS suffix,
    p.email COLLATE utf8mb4_general_ci AS email,
    p.age,
    p.sex COLLATE utf8mb4_general_ci AS sex,
    p.contact_number COLLATE utf8mb4_general_ci AS contact_number,
    p.city_address COLLATE utf8mb4_general_ci AS city_address,
    u.user_type COLLATE utf8mb4_general_ci AS user_type,
    COALESCE(cs.cor_path, cs.school_id_path, inc.id_path) COLLATE utf8mb4_general_ci AS student_id_path,
    NULL AS parent_id_path,
    NULL AS child_id,
    NULL AS parent_user_id
FROM 
    patients p
INNER JOIN 
    users u ON p.user_id = u.id
LEFT JOIN (
    SELECT user_id, MIN(cor_path) AS cor_path, MIN(school_id_path) AS school_id_path
    FROM college_students
    GROUP BY user_id
) cs ON u.user_type = 'College' AND cs.user_id = u.id
LEFT JOIN (
    SELECT user_id, MIN(id_path) AS id_path
    FROM incoming_freshmen
    GROUP BY user_id
) inc ON u.user_type = 'Incoming Freshman' AND inc.user_id = u.id
WHERE 
    u.user_type NOT IN ('Super Admin', 'Medical Admin', 'Dental Admin', 'Parent')
    AND p.contact_number <> 'PARENT_ACC'

UNION

SELECT DISTINCT
    p.id AS id,                                  
    p.user_id AS patient_id,   -- ✅ FIX: use child’s own user_id, same as in main SELECT
    p.surname COLLATE utf8mb4_general_ci AS surname,
    p.firstname COLLATE utf8mb4_general_ci AS firstname,
    p.middlename COLLATE utf8mb4_general_ci AS middlename,
    p.suffix COLLATE utf8mb4_general_ci AS suffix,
    p.email COLLATE utf8mb4_general_ci AS email,
    p.age,
    p.sex COLLATE utf8mb4_general_ci AS sex,
    p.contact_number COLLATE utf8mb4_general_ci AS contact_number,
    p.city_address COLLATE utf8mb4_general_ci AS city_address,
    'Child' AS user_type,  
    p.photo_path COLLATE utf8mb4_general_ci AS student_id_path,
    pr.id_path COLLATE utf8mb4_general_ci AS parent_id_path,
    c.id AS child_id,
    c.parent_id AS parent_user_id
FROM 
    children c
INNER JOIN patients p 
    ON p.id = c.patient_id
LEFT JOIN parents pr 
    ON pr.user_id = c.parent_id
LEFT JOIN users u 
    ON p.user_id = u.id
WHERE p.contact_number <> 'PARENT_ACC'
GROUP BY p.id;



";


$result = mysqli_query($conn, $query);

if (!$result) {
  error_log("Query failed: " . mysqli_error($conn));
  die("Query failed: " . mysqli_error($conn));
}

$patients = [];
while ($row = mysqli_fetch_assoc($result)) {
  $patients[] = $row;
}
mysqli_free_result($result);

// Sanitize patient data for JSON
$patients = utf8ize($patients);

// Function to fetch emergency contact
function getEmergencyContact($conn, $user_id)
{
  $query = "SELECT surname, firstname, middlename, contact_number, relationship, city_address 
              FROM emergency_contacts 
              WHERE patient_id = (SELECT id FROM patients WHERE user_id = ? LIMIT 1)";
  $stmt = mysqli_prepare($conn, $query);
  if (!$stmt) {
    error_log("Prepare failed: " . mysqli_error($conn));
    return [];
  }
  mysqli_stmt_bind_param($stmt, 'i', $user_id);
  if (!mysqli_stmt_execute($stmt)) {
    error_log("Execute failed: " . mysqli_error($conn));
    mysqli_stmt_close($stmt);
    return [];
  }
  $result = mysqli_stmt_get_result($stmt);
  $emergency = mysqli_fetch_assoc($result) ?: [];
  mysqli_stmt_close($stmt);
  return $emergency;
}

// Add emergency contact data to patients
foreach ($patients as &$patient) {
  $patient['emergency_contact'] = getEmergencyContact($conn, $patient['patient_id']);
}
unset($patient); // Unset reference to avoid issues
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Patient Management - WMSU Health Services</title>
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
          <h2 class="mb-0">Patient Management</h2>
        </div>

        <div class="card shadow-sm mb-4">
          <div class="card-header bg-white border-bottom-0">
            <div class="row g-3">
              <div class="col-md-8">
                <div class="input-group">
                  <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                  <input type="text" id="patientSearch" class="form-control" placeholder="Search patients...">
                  <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                    <i class="fas fa-times"></i>
                  </button>
                </div>
              </div>
              <div class="col-md-4">
                <select id="userTypeFilter" class="form-select">
                  <option value="all">All User Types</option>
                  <option value="Kindergarten">Kindergarten</option>
                  <option value="Elementary">Elementary</option>
                  <option value="College">College</option>
                  <option value="Employee">Employee</option>
                  <option value="Incoming Freshman">Incoming Freshman</option>
                </select>
              </div>
            </div>
          </div>

          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0" id="patientsTable">
                <thead class="table-light">
                  <tr>
                    <th class="sortable" data-sort="name">Patient Name <i class="fas fa-sort ms-1"></i></th>
                    <th class="sortable" data-sort="id">Patient ID <i class="fas fa-sort ms-1"></i></th>

                    <th>Parent/Guardian Card</th>
                    <th>Student ID Card</th>
                    <th class="sortable" data-sort="type">User Type <i class="fas fa-sort ms-1"></i></th>
                    <th>Patient Profile</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($patients as $row):
                    $full_name = trim("{$row['firstname']} {$row['middlename']} {$row['surname']} {$row['suffix']}");
                    $initials = strtoupper(substr($row['firstname'] ?? '', 0, 1) . substr($row['surname'] ?? '', 0, 1));
                    $badge_color = $row['user_type'] == 'College' ? 'primary' : ($row['user_type'] == 'Incoming Freshman' ? 'success' : ($row['user_type'] == 'Kindergarten' ? 'warning' : 'info'));
                    $child_name = $row['child_id'] ? trim("{$row['firstname']} {$row['middlename']} {$row['surname']}") : 'N/A';
                  ?>
                    <tr>
                      <td>
                        <div class="d-flex align-items-center">
                          <div class="avatar me-3">
                            <span class="avatar-text bg-<?php echo $badge_color; ?>"><?php echo $initials; ?></span>
                          </div>
                          <div>
                            <div class="fw-bold"><?php echo htmlspecialchars($full_name); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($row['email'] ?? 'N/A'); ?></small>
                          </div>
                        </div>
                      </td>
                      <td>P-<?php echo sprintf('%06d', $row['id']); ?></td>

                      <td>
                        <?php if ($row['parent_id_path']): ?>
                          <button
                            class="btn btn-sm btn-link text-primary p-0"
                            onclick="viewParent('<?php echo $row['parent_id_path']; ?>')">
                            <i class="fas fa-eye me-1"></i> View
                          </button>
                        <?php else: ?>
                          <span class="text-muted">N/A</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if ($row['student_id_path']): ?>
                          <button
                            class="btn btn-sm btn-link text-primary p-0"
                            onclick="viewStudent('S-<?php echo sprintf('%06d', $row['id']); ?>')">
                            <i class="fas fa-eye me-1"></i> View
                          </button>
                        <?php else: ?>
                          <span class="text-muted">N/A</span>
                        <?php endif; ?>
                      </td>


                      <td><span class="badge bg-<?php echo $badge_color; ?> bg-opacity-10 text-<?php echo $badge_color; ?>"><?php echo htmlspecialchars($row['user_type']); ?></span></td>
                      <td>
                        <a href="temp_patient_v1_admin.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary">
                          <i class="fas fa-user-circle me-1"></i> View Profile
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <div class="card-footer bg-white border-top-0">
              <div class="d-flex justify-content-between align-items-center">
                <div class="text-muted">
                  Showing <span id="showingFrom">1</span> to <span id="showingTo"><?php echo count($patients); ?></span> of <span id="totalRecords"><?php echo count($patients); ?></span> entries
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

    <?php include('notifications_admin.php') ?>

    <!-- Shared Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="viewModalLabel">View</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-0" id="viewModalBody" style="height:80vh; overflow:auto;">
            <!-- Dynamic content goes here -->
          </div>
        </div>
      </div>
    </div>



    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
      // Sidebar Toggle for Mobile
      document.getElementById('burger-btn').addEventListener('click', () => {
        document.getElementById('sidebar').classList.toggle('active');
      });

      // Client-side search and filter
      const patientSearch = document.getElementById('patientSearch');
      const userTypeFilter = document.getElementById('userTypeFilter');
      const patientsTable = document.getElementById('patientsTable');
      const rows = patientsTable.querySelectorAll('tbody tr');
      const showingFrom = document.getElementById('showingFrom');
      const showingTo = document.getElementById('showingTo');
      const totalRecords = document.getElementById('totalRecords');

      function filterTable() {
        const searchText = patientSearch.value.toLowerCase();
        const userType = userTypeFilter.value;
        let visibleRows = 0;

        rows.forEach(row => {
          const name = row.querySelector('.fw-bold').textContent.toLowerCase();
          const email = row.querySelector('.text-muted').textContent.toLowerCase();
          const type = row.querySelector('.badge').textContent;

          const matchesSearch = name.includes(searchText) || email.includes(searchText);
          const matchesType = userType === 'all' || type === userType;

          if (matchesSearch && matchesType) {
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
      userTypeFilter.addEventListener('change', filterTable);
      document.getElementById('clearSearch').addEventListener('click', () => {
        patientSearch.value = '';
        filterTable();
      });

      function viewParent(filePath) {
        const modalBody = document.getElementById("viewModalBody");
        const modalTitle = document.getElementById("viewModalLabel");

        modalTitle.textContent = "Parent/Guardian ID View";

        // Check if file is PDF
        if (filePath.toLowerCase().endsWith(".pdf")) {
          modalBody.innerHTML = `
      <embed src="${filePath}" type="application/pdf" width="100%" height="100%">
    `;
        } else {
          modalBody.innerHTML = `
      <img src="${filePath}" alt="Parent Document" style="max-width:100%; height:auto; display:block; margin:auto;">
    `;
        }

        const modal = new bootstrap.Modal(document.getElementById('viewModal'));
        modal.show();
      }


      function viewStudent(studentId) {
        const modalBody = document.getElementById("viewModalBody");
        const modalTitle = document.getElementById("viewModalLabel");

        modalTitle.textContent = "Student ID View";
        modalBody.innerHTML = `
    <iframe src="view-student.php?id=${studentId}" 
            style="border:0; width:100%; height:100%;"></iframe>
  `;

        const modal = new bootstrap.Modal(document.getElementById('viewModal'));
        modal.show();
      }

      function showInModal(filePath, title) {
        const modalBody = document.getElementById("viewModalBody");
        const modalTitle = document.getElementById("viewModalLabel");

        modalTitle.textContent = title;

        // Detect file type
        if (filePath.endsWith(".pdf")) {
          modalBody.innerHTML = `<embed src="${filePath}" type="application/pdf" width="100%" height="600px"/>`;
        } else {
          // Assume image (png, jpg, base64, etc.)
          modalBody.innerHTML = `<img src="${filePath}" class="img-fluid rounded"/>`;
        }

        const modal = new bootstrap.Modal(document.getElementById('viewModal'));
        modal.show();
      }
    </script>
</body>

</html>