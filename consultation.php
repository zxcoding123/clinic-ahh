<?php
// Configure session settings BEFORE starting the session
ini_set('session.cookie_lifetime', 86400); // 24 hours
ini_set('session.gc_maxlifetime', 86400); // 24 hours
ini_set('session.use_strict_mode', 1); // Prevent session fixation
ini_set('session.cookie_samesite', 'Lax'); // Mitigate CSRF

session_start();
require_once 'config.php';

// Generate CSRF token if not already set
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  session_regenerate_id(true);
}
error_log("CSRF Token in medical-documents.php: " . $_SESSION['csrf_token']);
error_log("Session ID in medical-documents.php: " . session_id());
error_log("PHPSESSID Cookie in medical-documents.php: " . ($_COOKIE['PHPSESSID'] ?? 'Not set'));

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}

$consultation_id = $_GET['id'];
// Store CSRF token in database
try {
  $sql = "INSERT INTO csrf_tokens (user_id, token, created_at) VALUES (?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE token = ?, created_at = NOW()";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('iss', $_SESSION['user_id'], $_SESSION['csrf_token'], $_SESSION['csrf_token']);
  $stmt->execute();
  $stmt->close();
} catch (Exception $e) {
  error_log("Failed to store CSRF token: " . $e->getMessage());
}

// Fetch Medical Certificate Requests (excluding admins and Incoming Freshman, only with uploads)
// Fetch Medical Certificate Requests (excluding admins and Incoming Freshman, only with uploads, and excluding issued certificates)
$medCertRequests = [];
$sql = "
    SELECT 
      
        u.id AS user_id,
        u.email,
        CASE 
            WHEN u.user_type = 'Parent' THEN CONCAT(c.last_name, ', ', c.first_name, ' ', COALESCE(c.middle_name, ''))
            ELSE CONCAT(u.last_name, ', ', u.first_name, ' ', COALESCE(u.middle_name, ''))
        END AS name,
        CASE 
            WHEN u.user_type = 'Parent' THEN c.type
            ELSE u.user_type
        END AS user_type,
        p.course AS college,
        CASE 
            WHEN u.user_type = 'Parent' THEN NULL
            ELSE p.age
        END AS age,
        CASE 
            WHEN u.user_type = 'Parent' THEN NULL
            ELSE p.sex
        END AS sex,
        md.id AS document_id,
        md.document_type,
        md.file_path,
        md.original_file_name,
        md.request_type,
        md.reason,
        md.submitted_at AS submission_date,
        md.status,
        c.id AS child_id
    FROM users u
    LEFT JOIN patients p ON u.id = p.user_id
    INNER JOIN medical_documents md ON u.id = md.user_id OR md.user_id IN (
        SELECT p2.user_id 
        FROM patients p2 
        WHERE p2.id IN (
            SELECT parent_id 
            FROM children 
            WHERE id = md.child_id
        )
    )
    LEFT JOIN children c ON md.child_id = c.id
    LEFT JOIN certificate_logs cl ON md.user_id = cl.user_id 
        AND (md.child_id = cl.child_id OR (md.child_id IS NULL AND cl.child_id IS NULL))
       WHERE md.status = 'pending'
    AND u.user_type NOT IN ('Super Admin', 'Dental Admin', 'Medical Admin', 'Incoming Freshman')
    AND md.file_path IS NOT NULL
    AND cl.id IS NULL -- Exclude requests with issued certificates
    ORDER BY md.submitted_at DESC
";

$result = $conn->query($sql);
if (!$result) {
  error_log("MedCert query error: " . $conn->error);
}

if ($result && $result->num_rows > 0) {
  $tempRequests = [];
  while ($row = $result->fetch_assoc()) {
    $user_id = $row['user_id'];
    $child_id = $row['child_id'];
    $key = $user_id . ($child_id ? '_' . $child_id : '');
    if (!isset($tempRequests[$key])) {
      $tempRequests[$key] = [
        'document_id' => $row['document_id'],
        'id' => $user_id,
        'email' => $row['email'],
        'child_id' => $child_id,
        'name' => trim($row['name']),
        'user_type' => $row['user_type'],
        'college' => $row['college'] ?: 'Not Specified',
        'age' => $row['age'] ?: 'N/A',
        'sex' => $row['sex'] ?: 'N/A',
        'reason' => $row['reason'] ?: 'Not Specified',
        'request_type' => $row['request_type'],
        'submission_date' => $row['submission_date'] ? date('Y-m-d', strtotime($row['submission_date'])) : 'N/A',
        'status' => $row['status'],
        'medical_certificate' => ['id' => null, 'file_path' => '', 'ext' => ''],
        'chest_xray' => ['id' => null, 'file_path' => '', 'ext' => '']
      ];
    }
    if ($row['document_type']) {
      $ext = pathinfo($row['original_file_name'], PATHINFO_EXTENSION);
      switch ($row['document_type']) {
        case 'medical_certificate':
          $tempRequests[$key]['medical_certificate'] = [
            'id' => $row['document_id'],
            'file_path' => $row['file_path'],
            'ext' => $ext
          ];
          break;
        case 'chest_xray_results':
          $tempRequests[$key]['chest_xray'] = [
            'id' => $row['document_id'],
            'file_path' => $row['file_path'],
            'ext' => $ext
          ];
          break;
      }
    }
  }
  $medCertRequests = array_values($tempRequests);
}
error_log("MedCertRequests count: " . count($medCertRequests));

// Fetch Issued Medical Certificates (from certificate_logs, excluding Incoming Freshman)
$issuedMedCerts = [];
$sql = "
    SELECT 
        cl.id AS log_id,
        cl.user_id,
        cl.child_id,
        cl.file_path,
        cl.sent_at AS date_issued,
        cl.recipient_email AS email,
        CASE 
            WHEN u.user_type = 'Parent' THEN CONCAT(c.last_name, ', ', c.first_name, ' ', COALESCE(c.middle_name, ''))
            ELSE CONCAT(u.last_name, ', ', u.first_name, ' ', COALESCE(u.middle_name, ''))
        END AS name,
        CASE 
            WHEN u.user_type = 'Parent' THEN c.type
            ELSE u.user_type
        END AS user_type,
        p.course AS college,
        md.reason
    FROM certificate_logs cl
    JOIN users u ON cl.user_id = u.id
    LEFT JOIN patients p ON u.id = p.user_id
    LEFT JOIN children c ON cl.child_id = c.id
    LEFT JOIN medical_documents md ON cl.user_id = md.user_id AND (cl.child_id = md.child_id OR md.child_id IS NULL)
    WHERE u.user_type NOT IN ('Super Admin', 'Dental Admin', 'Medical Admin', 'Incoming Freshman')
    ORDER BY cl.sent_at DESC
";

$result = $conn->query($sql);
if (!$result) {
  error_log("Issued MedCert query error: " . $conn->error);
}

if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $ext = pathinfo($row['file_path'], PATHINFO_EXTENSION);
    $issuedMedCerts[] = [
      'log_id' => $row['log_id'],
      'user_id' => $row['user_id'],
      'child_id' => $row['child_id'],
      'email' => $row['email'],
      'name' => trim($row['name']),
      'user_type' => $row['user_type'],
      'college' => $row['college'] ?: 'Not Specified',
      'reason' => $row['reason'] ?: 'Not Specified',
      'date_issued' => $row['date_issued'] ? date('Y-m-d', strtotime($row['date_issued'])) : 'N/A',
      'file_path' => $row['file_path'],
      'ext' => $ext
    ];
  }
}
error_log("IssuedMedCerts count: " . count($issuedMedCerts));

// Fetch Freshmen Submissions (Incoming Freshman only, excluding admins, only with uploads, and excluding those with issued certificates)
$freshmenSubmissions = [];
$sql = "
    SELECT 
        u.id AS user_id,
        u.email,
        CONCAT(u.last_name, ', ', u.first_name, ' ', COALESCE(u.middle_name, '')) AS name,
         p.department,
        p.course AS college,
        md.submitted_at AS submission_date,
        md.document_type,
        md.id AS document_id,
        md.file_path,
        md.original_file_name,
        md.status,
        (SELECT COUNT(*) 
         FROM certificate_logs cl 
         WHERE cl.user_id = u.id) AS has_completed_medcert
    FROM users u
    INNER JOIN patients p ON u.id = p.user_id
    INNER JOIN medical_documents md ON u.id = md.user_id
    WHERE u.user_type = 'Incoming Freshman'
    AND u.user_type NOT IN ('Super Admin', 'Dental Admin', 'Medical Admin')
    AND md.file_path IS NOT NULL
    AND md.document_type IN (
        'chest_xray_results',
        'complete_blood_count_results',
        'blood_typing_results',
        'urinalysis_results',
        'drug_test_results',
        'hepatitis_b_surface_antigen_test_results',
        'medical_certificate'
    )
    AND u.id NOT IN (SELECT user_id FROM certificate_logs)
    ORDER BY md.submitted_at DESC
";

$result = $conn->query($sql);
if (!$result) {
  error_log("Freshmen query error: " . $conn->error);
}

if ($result && $result->num_rows > 0) {
  $tempSubmissions = [];
  while ($row = $result->fetch_assoc()) {
    $user_id = $row['user_id'];
    if (!isset($tempSubmissions[$user_id])) {
      $tempSubmissions[$user_id] = [
        'id' => $user_id,
        'email' => $row['email'],
        'name' => trim($row['name']),
        'department' => $row['department'] ?? '',   // ✅ ADD THIS
        'course' => $row['college'] ?? '',           // ✅ AND THIS
        'submission_date' => $row['submission_date'] ? date('Y-m-d', strtotime($row['submission_date'])) : 'N/A',
        'has_completed_medcert' => $row['has_completed_medcert'] > 0,
        'chest_xray' => ['id' => null, 'file_path' => '', 'ext' => ''],
        'complete_blood_count' => ['id' => null, 'file_path' => '', 'ext' => ''],
        'blood_typing' => ['id' => null, 'file_path' => '', 'ext' => ''],
        'urinalysis' => ['id' => null, 'file_path' => '', 'ext' => ''],
        'drug_test' => ['id' => null, 'file_path' => '', 'ext' => ''],
        'hepatitis_b_surface_antigen_test_results' => ['id' => null, 'file_path' => '', 'ext' => '']
      ];
    }

    if ($row['document_type']) {
      $ext = pathinfo($row['original_file_name'], PATHINFO_EXTENSION);
      switch ($row['document_type']) {
        case 'chest_xray_results':
          $tempSubmissions[$user_id]['chest_xray'] = [
            'id' => $row['document_id'],
            'file_path' => $row['file_path'],
            'ext' => $ext
          ];
          break;
        case 'complete_blood_count_results':
          $tempSubmissions[$user_id]['complete_blood_count'] = [
            'id' => $row['document_id'],
            'file_path' => $row['file_path'],
            'ext' => $ext
          ];
          break;
        case 'blood_typing_results':
          $tempSubmissions[$user_id]['blood_typing'] = [
            'id' => $row['document_id'],
            'file_path' => $row['file_path'],
            'ext' => $ext
          ];
          break;
        case 'urinalysis_results':
          $tempSubmissions[$user_id]['urinalysis'] = [
            'id' => $row['document_id'],
            'file_path' => $row['file_path'],
            'ext' => $ext
          ];
          break;
        case 'drug_test_results':
          $tempSubmissions[$user_id]['drug_test'] = [
            'id' => $row['document_id'],
            'file_path' => $row['file_path'],
            'ext' => $ext
          ];
          break;
        case 'hepatitis_b_surface_antigen_test_results':

          $tempSubmissions[$user_id]['hepatitis_b_surface_antigen_test_results'] = [
            'id' => $row['document_id'],
            'file_path' => $row['file_path'],
            'ext' => $ext
          ];
          break;
      }
    }
  }
  $freshmenSubmissions = array_values($tempSubmissions);
}
error_log("FreshmenSubmissions count: " . count($freshmenSubmissions));

// Fetch Issued Freshmen Certificates (from certificate_logs, only Incoming Freshman)
$issuedFreshmenCerts = [];
$sql = "
   SELECT 
    cl.id AS log_id,
    cl.user_id,
    cl.file_path AS file_path_document,
    cl.sent_at AS date_issued,
    cl.recipient_email AS email,
    CONCAT(u.last_name, ', ', u.first_name, ' ', COALESCE(u.middle_name, '')) AS name,
    p.department,
    p.course AS college,
    md.document_type,
    md.id AS document_id,
    md.file_path,
    md.original_file_name,
    md.status
FROM certificate_logs cl
JOIN users u ON cl.user_id = u.id
LEFT JOIN patients p ON u.id = p.user_id
LEFT JOIN medical_documents md ON u.id = md.user_id
    AND md.document_type IN (
        'chest_xray_results',
        'complete_blood_count_results',
        'blood_typing_results',
        'urinalysis_results',
        'drug_test_results',
        'hepatitis_b_surface_antigen_test_results'
    )
WHERE u.user_type = 'Incoming Freshman'
ORDER BY cl.sent_at DESC, md.document_type
";

$result = $conn->query($sql);
if (!$result) {
  error_log("Issued Freshmen query error: " . $conn->error);
}




if ($result && $result->num_rows > 0) {
  $tempIssuedCerts = [];
  while ($row = $result->fetch_assoc()) {
    $user_id = $row['user_id'];
    if (!isset($tempIssuedCerts[$user_id])) {
      $tempIssuedCerts[$user_id] = [
        'log_id' => $row['log_id'],
        'user_id' => $row['user_id'],
        'email' => $row['email'],
        'name' => trim($row['name']),
        'college' => $row['college'] ?: 'Not Specified',
        'date_issued' => $row['date_issued'] ? date('Y-m-d', strtotime($row['date_issued'])) : 'N/A',
        'file_path_document' => $row['file_path_document'],
        'certificate_ext' => pathinfo($row['file_path_document'], PATHINFO_EXTENSION),
        'chest_xray' => ['id' => null, 'file_path' => '', 'ext' => ''],
        'complete_blood_count' => ['id' => null, 'file_path' => '', 'ext' => ''],
        'blood_typing' => ['id' => null, 'file_path' => '', 'ext' => ''],
        'urinalysis' => ['id' => null, 'file_path' => '', 'ext' => ''],
        'drug_test' => ['id' => null, 'file_path' => '', 'ext' => ''],
        'hepatitis_b' => ['id' => null, 'file_path' => '', 'ext' => '']
      ];
    }
    if ($row['document_type']) {
      $ext = pathinfo($row['original_file_name'], PATHINFO_EXTENSION);
      switch ($row['document_type']) {
        case 'chest_xray_results':
          $tempIssuedCerts[$user_id]['chest_xray'] = [
            'id' => $row['document_id'],
            'file_path' => $row['file_path'],
            'ext' => $ext
          ];
          break;
        case 'complete_blood_count_results':
          $tempIssuedCerts[$user_id]['complete_blood_count'] = [
            'id' => $row['document_id'],
            'file_path' => $row['file_path'],
            'ext' => $ext
          ];
          break;
        case 'blood_typing_results':
          $tempIssuedCerts[$user_id]['blood_typing'] = [
            'id' => $row['document_id'],
            'file_path' => $row['file_path'],
            'ext' => $ext
          ];
          break;
        case 'urinalysis_results':
          $tempIssuedCerts[$user_id]['urinalysis'] = [
            'id' => $row['document_id'],
            'file_path' => $row['file_path'],
            'ext' => $ext
          ];
          break;
        case 'drug_test_results':
          $tempIssuedCerts[$user_id]['drug_test'] = [
            'id' => $row['document_id'],
            'file_path' => $row['file_path'],
            'ext' => $ext
          ];
          break;
        case 'hepatitis_b_surface_antigen_test_results':
          $tempIssuedCerts[$user_id]['hepatitis_b'] = [
            'id' => $row['document_id'],
            'file_path' => $row['file_path'],
            'ext' => $ext
          ];
          break;
      }
    }
  }
  $issuedFreshmenCerts = array_values($tempIssuedCerts);
}


error_log("IssuedFreshmenCerts count: " . count($issuedFreshmenCerts));
// Fetch Consultation Advice Records
$consultationAdvice = [];

$user_id = $_GET['user_id'] ?? null;
// Get user_id from query parameter
$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
  die("No user_id provided.");
}

$sql = "SELECT 
    ca.id AS advice_id,
    ca.user_id,
    ca.child_id,
    ca.reason,
    ca.status,
    ca.date_advised,
    u.email,
    CASE 
        WHEN u.user_type = 'Parent' 
            THEN CONCAT(c.last_name, ', ', c.first_name, ' ', COALESCE(c.middle_name, ''))
        ELSE CONCAT(u.last_name, ', ', u.first_name, ' ', COALESCE(u.middle_name, ''))
    END AS name,
    CASE 
        WHEN u.user_type = 'Parent' 
            THEN c.type
        ELSE u.user_type
    END AS user_type,
    p.age,
    p.sex
FROM consultation_advice ca
JOIN users u ON ca.user_id = u.id
JOIN patients p ON u.id = p.user_id
LEFT JOIN children c ON ca.child_id = c.id
WHERE ca.user_id = ?
ORDER BY ca.date_advised DESC;
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $patient_name = htmlspecialchars($row['name']);
    $patient_age = htmlspecialchars($row['age']);
    $patient_gender = htmlspecialchars($row['sex']);
  }
} else {
  echo "No consultation records found for this user.";
}



$stmt->close();
$conn->close();


?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Medical Consultation - WMSU Health Services</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
  <link rel="stylesheet" href="css/medicaldocuments.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="apple-touch-icon" sizes="180x180" href="images/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="images/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="images/favicon-16x16.png">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
    .small-heading,
    .modal-title,
    .section-title {
      font-family: 'Cinzel', serif;
    }

    .consultation-form-container {
      background-color: #fff;
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    }

    .section-header {
      background-color: #a6192e;
      color: white;
      padding: 8px 15px;
      border-radius: 5px;
      margin: 15px 0;
      font-weight: bold;
    }

    .vital-signs-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 15px;
      margin-bottom: 15px;
    }

    .form-label {
      font-weight: 600;
      color: #333;
    }

    .consultation-textarea {
      min-height: 120px;
    }

    .signature-canvas {
      border: 1px solid #ddd;
      border-radius: 5px;
      margin-bottom: 10px;
    }

    .print-only {
      display: none;
    }

    @media print {
      .no-print {
        display: none !important;
      }

      .print-only {
        display: block !important;
      }

      .consultation-form-container {
        box-shadow: none;
        padding: 0;
      }
    }

    .university-header {
      text-align: center;
      margin-bottom: 20px;
    }

    .university {
      font-weight: bold;
      font-size: 18px;
    }

    .location {
      font-size: 14px;
    }

    .service-center {
      font-weight: bold;
      font-size: 16px;
    }
  </style>
</head>

<body>
  <div id="app" class="d-flex">
    <input type="hidden" id="csrf-token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
    <input type="hidden" id="session-id" value="<?php echo htmlspecialchars(session_id()); ?>">
    <input type="hidden" id="user-id" value="<?php echo htmlspecialchars($_SESSION['user_id']); ?>">
    <button id="burger-btn" class="burger-btn">☰</button>
    <?php include 'include/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content" id="main-content">
      <div class="container-fluid">
        <h2 class="small-heading">Medical Consultation</h2>

        <!-- Consultation Form -->
        <div class="consultation-form-container">
          <div class="university-header">
            <div class="row text-center">
              <div class="col-sm-2">
                <img src="images/clinic.png" alt="WMSU Clinic Logo" class="img-fluid" style="width: 100px; height: auto;">
              </div>
              <div class="col">
                <div class="prescription-header" style="text-align: center; margin-top: 10px;">
                  <div class="university">WESTERN MINDANAO STATE UNIVERSITY</div>
                  <div class="location">Zamboanga City</div>
                  <div class="service-center">UNIVERSITY HEALTH SERVICES CENTER</div>
                </div>
              </div>
              <div class="col-sm-2">
                <img src="images/Western_Mindanao_State_University.png" alt="WMSU Logo" class="img-fluid" style="width: 100px; height: auto;">
              </div>
            </div>
            <h2 class="text-center mb-4">Consultation Form</h2>
          </div>

          <hr>

          <form id="consultationForm" method="POST" action="save_consultation.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <!-- Patient Selection -->
            <div class="section-header">Patient Information</div>
            <div class="row mb-3">
              <div class="col-md-6">
                <label class="form-label" for="patient-select">Patient:</label>
                <input type="hidden" name="id" value="<?php echo $consultation_id ?>">
                <select class="form-control" id="patient-select" name="patient_id" required>



                  <option value="<?php echo $user_id ?>">
                    <?php echo $patient_name; ?>
                  </option>

                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label" for="consultation_date">Date:</label>
                <input type="date" class="form-control" id="consultation_date" name="consultation_date" value="<?php echo date('Y-m-d'); ?>" required>
              </div>
              <div class="col-md-3">
                <label class="form-label" for="consultation_time">Time:</label>
                <input type="time" class="form-control" id="consultation_time" name="consultation_time" value="<?php echo date('H:i'); ?>" required>
              </div>
            </div>

            <div class="row mb-3">

              <div class="col-md">
                <label class="form-label" for="patient-age">Age:</label>
                <input type="number" class="form-control" id="patient-age" name="patient_age" value="<?php echo $patient_age ?>">
              </div>
              <div class="col-md">
                <label class="form-label" for="patient-sex">Sex:</label>
                <input type="text"
                  class="form-control"
                  id="patient-sex"
                  name="patient_sex"
                  value="<?php echo ucfirst($patient_gender); ?>"
                  readonly>

              </div>


              <!-- Vital Signs -->
              <div class="section-header">Vital Signs</div>
              <div class="vital-signs-grid">
                <div>
                  <label class="form-label" for="weight">Weight (kg):</label>
                  <input type="text" class="form-control" id="weight" name="weight">
                </div>
                <div>
                  <label class="form-label" for="height">Height (cm):</label>
                  <input type="text" class="form-control" id="height" name="height">
                </div>
                <div>
                  <label class="form-label" for="blood_pressure">Blood Pressure:</label>
                  <input type="text" class="form-control" id="blood_pressure" name="blood_pressure" placeholder="e.g., 120/80">
                </div>
                <div>
                  <label class="form-label" for="temperature">Temperature (°C):</label>
                  <input type="text" class="form-control" id="temperature" name="temperature">
                </div>
                <div>
                  <label class="form-label" for="heart_rate">Heart Rate (bpm):</label>
                  <input type="text" class="form-control" id="heart_rate" name="heart_rate">
                </div>
                <div>
                  <label class="form-label" for="respiratory_rate">Respiratory Rate (bpm):</label>
                  <input type="text" class="form-control" id="respiratory_rate" name="respiratory_rate">
                </div>
                <div>
                  <label class="form-label" for="oxygen_saturation">O2 Saturation (%):</label>
                  <input type="text" class="form-control" id="oxygen_saturation" name="oxygen_saturation">
                </div>
              </div>

              <!-- Consultation Details -->
              <div class="section-header">Consultation Details</div>
              <div class="mb-3">
                <label class="form-label" for="complaints">Chief Complaints/Symptoms:</label>
                <textarea class="form-control consultation-textarea" id="complaints" name="complaints" required></textarea>
              </div>

              <div class="mb-3">
                <label class="form-label" for="history">History of Present Illness:</label>
                <textarea class="form-control consultation-textarea" id="history" name="history"></textarea>
              </div>

              <div class="mb-3">
                <label class="form-label" for="physical_exam">Physical Examination Findings:</label>
                <textarea class="form-control consultation-textarea" id="physical_exam" name="physical_exam"></textarea>
              </div>

              <div class="mb-3">
                <label class="form-label" for="assessment">Assessment/Diagnosis:</label>
                <textarea class="form-control consultation-textarea" id="assessment" name="assessment" required></textarea>
              </div>

              <div class="mb-3">
                <label class="form-label" for="plan">Treatment Plan/Management:</label>
                <textarea class="form-control consultation-textarea" id="plan" name="plan" required></textarea>
              </div>

              <div class="mb-3">
                <label class="form-label" for="medications">Medications Prescribed:</label>
                <textarea class="form-control consultation-textarea" id="medications" name="medications"></textarea>
              </div>

              <div class="mb-3">
                <label class="form-label" for="follow_up">Follow-up Type:</label>
                <select class="form-control" id="consultation_type" name="consultation_type">
                  <option value="">Select Type</option>
                  <option value="Medical">Medical</option>
                  <option value="Dental">Dental</option>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label" for="follow_up">Follow-up Instructions:</label>
                <textarea class="form-control consultation-textarea" id="follow_up" name="follow_up"></textarea>
              </div>

              <!-- Physician Information -->
              <div class="section-header">Physician Information</div>
              <div class="row mb-3">
                <div class="col-md-6">
                  <label class="form-label" for="physician_name">Physician Name:</label>
                  <input type="text" class="form-control" id="physician_name" name="physician_name" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="physician_license">License Number:</label>
                  <input type="text" class="form-control" id="physician_license" name="physician_license">
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Physician Signature:</label> <button type="button" class="btn btn-secondary btn-sm" onclick="clearSignature()">Clear Signature</button> <br>
                <canvas id="signature-canvas" class="signature-canvas" width="500" height="150"></canvas>
                <input type="hidden" id="signature-data" name="signature_data">
                <div class="mt-2">

                </div>
              </div>

              <div class="d-flex justify-content-end mt-4">
                <button type="button" class="btn btn-secondary me-2" onclick="printForm()">Print</button>
                <button type="submit" class="btn btn-primary">Save Consultation</button>
              </div>
          </form>
        </div>
      </div>
    </div>

    <?php include('notifications_admin.php') ?>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header" style="background-color: #a6192e; color: white;">
            <h5 class="modal-title" id="successModalLabel">Success</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            Consultation record saved successfully!
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/signature_pad/1.5.3/signature_pad.min.js"></script>
  <script>
    // Initialize signature pad
    let signaturePad;
    document.addEventListener('DOMContentLoaded', function() {
      const canvas = document.getElementById('signature-canvas');
      signaturePad = new SignaturePad(canvas, {
        backgroundColor: 'rgba(255, 255, 255, 0)',
        penColor: 'rgb(0, 0, 0)'
      });

      // Set current physician name if available
      const physicianName = "<?php echo $_SESSION['full_name'] ?? ''; ?>";
      if (physicianName) {
        document.getElementById('physician_name').value = physicianName;
      }

      // Patient selection handler
      document.getElementById('patient-select').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value) {
          document.getElementById('patient-name').value = selectedOption.getAttribute('data-name');
          // You could add additional logic here to fetch patient details from database
        }
      });

      // Form submission handler
      document.getElementById('consultationForm').addEventListener('submit', function(e) {
        e.preventDefault();

        // Save signature
        if (!signaturePad.isEmpty()) {
          document.getElementById('signature-data').value = signaturePad.toDataURL();
        }

        // Submit form via AJAX
        const formData = new FormData(this);

        fetch('save_consultation.php', {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              // Show success modal
              const successModal = new bootstrap.Modal(document.getElementById('successModal'));
              successModal.show();

              // Reset form
              this.reset();
              signaturePad.clear();

              // Redirect after 2 seconds
              setTimeout(() => {
                window.location.href = 'medical-documents.php';
              }, 2000);
            } else {
              alert('Error: ' + data.message);
            }
          })
          .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while saving the consultation.');
          });
      });
    });

    function clearSignature() {
      signaturePad.clear();
      document.getElementById('signature-data').value = '';
    }

    function printForm() {
      window.print();
    }
  </script>
</body>

</html>