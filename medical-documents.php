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
  header("Location: /login.php");
  exit();
}

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
$sql = "
SELECT 
    ca.id AS advice_id,
    ca.user_id,
    ca.child_id,
    ca.reason,
    ca.status,
    ca.date_advised,
    u.email,
    CASE 
        WHEN u.user_type = 'Parent' 
            THEN CONCAT(ch.last_name, ', ', ch.first_name, ' ', COALESCE(ch.middle_name, ''))
        ELSE CONCAT(u.last_name, ', ', u.first_name, ' ', COALESCE(u.middle_name, ''))
    END AS name,
    CASE 
        WHEN u.user_type = 'Parent' THEN ch.type
        ELSE u.user_type
    END AS user_type
FROM consultation_advice ca
JOIN users u 
    ON ca.user_id = u.id
LEFT JOIN children ch 
    ON ca.child_id = ch.id
WHERE u.user_type NOT IN ('Super Admin', 'Dental Admin', 'Medical Admin')
  AND (
        u.user_type != 'Incoming Freshman' 
        OR (
            u.user_type = 'Incoming Freshman' 
            AND u.id NOT IN (SELECT user_id FROM certificate_logs)
        )
      )
ORDER BY ca.date_advised DESC;


";

$result = $conn->query($sql);
if (!$result) {
  error_log("Consultation advice query error: " . $conn->error);
}

if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $consultationAdvice[] = [
      'advice_id' => $row['advice_id'],
      'user_id' => $row['user_id'],
      'child_id' => $row['child_id'],
      'email' => $row['email'],
      'name' => trim($row['name']),
      'user_type' => $row['user_type'],
      'reason' => $row['reason'] ?: 'Not Specified',
      'status' => $row['status'],
      'date_advised' => $row['date_advised'] ? date('Y-m-d', strtotime($row['date_advised'])) : 'N/A'
    ];
  }
}
error_log("ConsultationAdvice count: " . count($consultationAdvice));

// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Medical Documents - WMSU Health Services</title>
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

    .consult-modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.4);
    }

    .consult-modal-content {
      background-color: white;
      padding: 20px;
      border-radius: 10px;
      width: 90%;
      max-width: 500px;
      margin: 10% auto;
      position: relative;
      text-align: center;
    }

    .consult-modal-content textarea {
      width: 100%;
      height: 150px;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 5px;
      resize: vertical;
      font-size: 14px;
    }

    .consult-modal-content .close {
      position: absolute;
      top: 10px;
      right: 15px;
      font-size: 20px;
      cursor: pointer;
    }

    .consult-modal-content .close:hover {
      color: red;
    }

    .consult-modal-content .email-btn {
      background: #007bff;
      color: white;
      border: 2px solid #007bff;
      padding: 6px 12px;
      margin-top: 10px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 0.85rem;
      font-weight: 600;
      text-transform: uppercase;
      transition: all 0.2s ease;
    }

    .consult-modal-content .email-btn:hover {
      background: transparent;
      color: #007bff;
      border: 2px solid #007bff;
    }

    .consult-modal-content .email-btn:disabled {
      background: #6c757d;
      border-color: #6c757d;
      cursor: not-allowed;
      opacity: 0.65;
    }

    .action-buttons .btn {
      margin: 0 5px 5px 0;
      padding: 4px 8px;
      font-size: 0.8rem;
    }

    .action-buttons {
      display: flex;
      flex-wrap: wrap;
      gap: 5px;
    }

    .table td,
    .table th {
      padding: 8px;
    }

    #successModal .modal-content {
      border-radius: 10px;
    }

    #successModal .modal-body {
      text-align: center;
      font-size: 1rem;
      padding: 20px;
    }

    #successModal .modal-body svg {
      vertical-align: middle;
    }

    @media print {
      .consult-modal {
        display: none !important;
      }
    }

    .view-iframe {
      width: 100%;
      height: 600px;
      border: none;
      display: block;
    }

    .view-image {
      max-width: 100%;
      max-height: 600px;
      width: auto;
      height: auto;
      object-fit: contain;
      display: block;
      margin: 0 auto;
    }

    .error-message {
      color: red;
      margin-top: 10px;
      text-align: center;
    }

    .download-link {
      margin-top: 10px;
      display: inline-block;
      color: black !important;
    }

    .modal-body {
      position: relative;
      overflow: auto;
      max-height: 80vh;
    }

    .modal-content {
      border-radius: 10px;
    }

    .modal-dialog {
      max-width: 90vw;
    }

    .history-modal .modal-dialog {
      max-width: 700px;
    }

    .history-table {
      width: 100%;
      border-collapse: collapse;
    }

    .history-table th,
    .history-table td {
      border: 1px solid #ddd;
      padding: 8px;
      text-align: left;
    }

    .history-table th {
      background-color: #f2f2f2;
    }

    .history-table tr:nth-child(even) {
      background-color: #f9f9f9;
    }

    .history-table tr:hover {
      background-color: #f5f5f5;
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
        <h2 class="small-heading">Medical Documents</h2>

        <!-- Tab Navigation -->
        <ul class="nav nav-tabs" id="documentTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="med-cert-tab" data-bs-toggle="tab" data-bs-target="#med-cert-requests" type="button" role="tab" aria-controls="med-cert-requests" aria-selected="true">Medical Certificate Requests</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="med-cert-issued-tab" data-bs-toggle="tab" data-bs-target="#med-cert-issued" type="button" role="tab" aria-controls="med-cert-issued" aria-selected="false">Issued Medical Certificates</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="freshmen-tab" data-bs-toggle="tab" data-bs-target="#freshmen-submissions" type="button" role="tab" aria-controls="freshmen-submissions" aria-selected="false">Freshmen Submissions</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="freshmen-issued-tab" data-bs-toggle="tab" data-bs-target="#freshmen-issued" type="button" role="tab" aria-controls="freshmen-issued" aria-selected="false">Issued Freshmen Certificates</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="consultations-tab" data-bs-toggle="tab" data-bs-target="#consultations-advised" type="button" role="tab" aria-controls="consultations-advised" aria-selected="false">Advised for Consultations</button>
          </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="documentTabContent">
          <!-- Medical Certificate Requests Tab -->
          <div class="tab-pane fade show active" id="med-cert-requests" role="tabpanel" aria-labelledby="med-cert-tab">
            <div class="row mb-3 mt-3">
              <div class="col-md-4">
                <input type="text" class="form-control" id="med-cert-search" placeholder="Search by name">
              </div>
              <div class="col-md-4">
                <select class="form-select" id="med-cert-sort">
                  <option value="default">Sort by</option>
                  <option value="name-asc">Name (A-Z)</option>
                  <option value="name-desc">Name (Z-A)</option>
                  <option value="date-asc">Date (Oldest to Recent)</option>
                  <option value="Kindergarten">Kindergarten</option>
                  <option value="Elementary">Elementary</option>
                  <option value="Highschool">Highschool</option>
                  <option value="Senior High School">Senior High</option>
                  <option value="College">College</option>
                  <option value="Employee">Employee</option>
                </select>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-striped">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>User Type</th>
                    <th>Reason</th>
                    <th>View Results</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody id="med-cert-table-body">
                  <!-- Populated by JavaScript -->
                </tbody>
              </table>
            </div>
          </div>

          <!-- Issued Medical Certificates Tab -->
          <div class="tab-pane fade" id="med-cert-issued" role="tabpanel" aria-labelledby="med-cert-issued-tab">
            <div class="row mb-3 mt-3">
              <div class="col-md-4">
                <input type="text" class="form-control" id="med-cert-issued-search" placeholder="Search by name">
              </div>
              <div class="col-md-4">
                <select class="form-select" id="med-cert-issued-sort">
                  <option value="default">Sort by</option>
                  <option value="name-asc">Name (A-Z)</option>
                  <option value="name-desc">Name (Z-A)</option>
                  <option value="date-asc">Date Issued (Oldest to Recent)</option>
                  <option value="Kindergarten">Kindergarten</option>
                  <option value="Elementary">Elementary</option>
                  <option value="Highschool">Highschool</option>
                  <option value="Senior High School">Senior High</option>
                  <option value="College">College</option>
                  <option value="Employee">Employee</option>
                </select>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-striped">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>User Type</th>
                    <th>Reason</th>
                    <th>Date Issued</th>
                    <th>View Certificate</th>
                  </tr>
                </thead>
                <tbody id="med-cert-issued-table-body">
                  <!-- Populated by JavaScript -->
                </tbody>
              </table>
            </div>
          </div>

          <!-- Freshmen Submissions Tab -->
          <div class="tab-pane fade" id="freshmen-submissions" role="tabpanel" aria-labelledby="freshmen-tab">
            <div class="row mb-3 mt-3">
              <div class="col-md-4">
                <input type="text" class="form-control" id="freshmen-search" placeholder="Search by name">
              </div>
              <div class="col-md-4">
                <select class="form-select" id="freshmen-sort">
                  <option value="default">Sort by</option>
                  <option value="name-asc">Name (A-Z)</option>
                  <option value="name-desc">Name (Z-A)</option>
                  <option value="date-asc">Date (Oldest to Recent)</option>
                </select>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-striped">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Submission Date</th>
                    <th>View Results</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody id="freshmen-table-body">
                  <!-- Populated by JavaScript -->
                </tbody>
              </table>
            </div>
          </div>

          <!-- Issued Freshmen Certificates Tab -->
          <div class="tab-pane fade" id="freshmen-issued" role="tabpanel" aria-labelledby="freshmen-issued-tab">
            <div class="row mb-3 mt-3">
              <div class="col-md-4">
                <input type="text" class="form-control" id="freshmen-issued-search" placeholder="Search by name">
              </div>
              <div class="col-md-4">
                <select class="form-select" id="freshmen-issued-sort">
                  <option value="default">Sort by</option>
                  <option value="name-asc">Name (A-Z)</option>
                  <option value="name-desc">Name (Z-A)</option>
                  <option value="date-asc">Date Issued (Oldest to Recent)</option>
                </select>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-striped">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>View Results</th>
                    <th>Date Issued</th>
                    <th>View Certificate</th>
                  </tr>
                </thead>
                <tbody id="freshmen-issued-table-body">
                  <!-- Populated by JavaScript -->
                </tbody>
              </table>
            </div>
          </div>

          <!-- Advised for Consultations Tab -->
          <div class="tab-pane fade" id="consultations-advised" role="tabpanel" aria-labelledby="consultations-tab">
            <div class="row mb-3 mt-3">
              <div class="col-md-4">
                <input type="text" class="form-control" id="consultations-search" placeholder="Search by name">
              </div>
              <div class="col-md-4">
                <select class="form-select" id="consultations-sort">
                  <option value="default">Sort by</option>
                  <option value="name-asc">Name (A-Z)</option>
                  <option value="name-desc">Name (Z-A)</option>
                  <option value="date-asc">Date Advised (Oldest to Recent)</option>
                  <option value="Kindergarten">Kindergarten</option>
                  <option value="Elementary">Elementary</option>
                  <option value="Highschool">Highschool</option>
                  <option value="Senior High School">Senior High</option>
                  <option value="College">College</option>
                  <option value="Employee">Employee</option>
                  <option value="Incoming Freshman">Incoming Freshman</option>
                </select>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-striped">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>User Type</th>
                    <th>Reason</th>
                    <th>Date Advised</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody id="consultations-table-body">
                  <!-- Populated by JavaScript -->
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <?php include('notifications_admin.php') ?>

    <!-- Consult Modal -->
    <!-- Consult Modal -->
    <div id="consult-modal" class="consult-modal">
      <div class="consult-modal-content">
        <span class="close" onclick="closeConsultModal()">×</span>
        <h3>Advise Consultation</h3>
        <input type="hidden" id="current-item-id">
        <input type="hidden" id="current-child-id">
        <input type="hidden" id="current-email">
        <input type="hidden" id="user-id" value="<?php echo $_SESSION['user_id']; ?>">
        <input type="hidden" id="csrf-token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>>">
        <input type="text" id="consult-reason" placeholder="Reason for consultation..." style="display:none;">
        <textarea id="consult-message" placeholder="Enter detailed message for the appointment..."></textarea>
        <button class="email-btn" id="send-email-btn" onclick="sendConsultEmail()">Send via Email</button>
      </div>
    </div>

    <!-- View Document Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header" style="background-color: #a6192e; color: white;">
            <h5 class="modal-title" id="viewModalLabel">View Document</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <iframe id="view-iframe" class="view-iframe" src=""></iframe>
            <div id="view-error" class="error-message"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" style="background-color: #6c757d; color: white;" data-bs-dismiss="modal">Close</button>
            <a id="download-link" href="#" class="btn btn-crimson-1 btn-sm" style="display: none;">Download</a>
          </div>
        </div>
      </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" style="max-width: 500px; width: 90%;">
        <div class="modal-content">
          <div class="modal-header" style="background-color: #a6192e; color: white;">
            <h5 class="modal-title" id="successModalLabel">Success</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="d-flex align-items-center">
              <svg class="me-2" width="24" height="24" fill="#a6192e" viewBox="0 0 24 24">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
              </svg>
              <span id="success-message">Operation completed successfully!</span>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" style="background-color: #6c757d; color: white;" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>



    <!-- Confirm Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" style="max-width: 500px; width: 90%;">
        <div class="modal-content">
          <div class="modal-header" style="background-color: #a6192e; color: white;">
            <h5 class="modal-title" id="confirmModalLabel">Confirm Action</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <span id="confirm-message"></span>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-danger btn-sm" id="confirm-action">Confirm</button>
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Error Modal -->
    <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" style="max-width: 500px; width: 90%;">
        <div class="modal-content">
          <div class="modal-header" style="background-color: #a6192e; color: white;">
            <h5 class="modal-title" id="errorModalLabel">Error</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <span id="error-message"></span>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" style="background-color: #6c757d; color: white;" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
  <script>
    // Data from PHP
    const medCertRequests = <?php echo json_encode($medCertRequests); ?>;
    const issuedMedCerts = <?php echo json_encode($issuedMedCerts); ?>;
    const freshmenSubmissions = <?php echo json_encode($freshmenSubmissions); ?>;
    const issuedFreshmenCerts = <?php echo json_encode($issuedFreshmenCerts); ?>;
    const consultationAdvice = <?php echo json_encode($consultationAdvice); ?>;
    console.log('MedCert Requests:', medCertRequests);
    console.log('Issued MedCerts:', issuedMedCerts);
    console.log('Freshmen Submissions:', freshmenSubmissions);
    console.log('Issued Freshmen Certs:', issuedFreshmenCerts);
    console.log('Consultation Advice:', consultationAdvice);

    // Define user type order for sorting
    const userTypeOrder = [
      'Kindergarten',
      'Elementary',
      'Highschool',
      'Senior High School',
      'College',
      'Employee',
      'Incoming Freshman'
    ];

    function requiresHepatitisB(item) {
      const department = item.department; // renamed
      const course = item.course; // same for course if no separate field

      console.log(department)

      console.log(course);
      return (
        ['COM', 'CON', 'CHE', 'CCJE'].includes(department) ||
        (department === 'CA' && course === 'BSFoodTech') ||
        (department === 'CSM' && course === 'BSBio')
      );
    }

    // Format date to something readable (e.g., Aug 27, 2025, 2:32 PM)
function formatDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true
  });
}

    // Render Medical Certificate Requests Table
    function renderMedCertTable(data) {
      const tbody = document.getElementById('med-cert-table-body');
      tbody.innerHTML = '';
      data.forEach(item => {
        const requiresXray = item.request_type === 'ojt-internship';

        // Choose badge color based on reason (optional)
        let badgeClass = 'bg-secondary';
        let badgeName = item.reason; // fallback if not mapped

        switch (item.reason) {
          case 'local':
            badgeClass = 'bg-success';
            badgeName = 'Local';
            break;
          case 'national':
            badgeClass = 'bg-warning';
            badgeName = 'National';
            break;
          case 'travel-national':
            badgeClass = 'bg-warning';
            badgeName = 'Travel National';
            break;
          case 'international':
            badgeClass = 'bg-danger';
            badgeName = 'International';
            break;
          case 'travel-international':
            badgeClass = 'bg-danger';
            badgeName = 'Travel International';
            break;
          case 'ojt-internship':
            badgeClass = 'bg-primary';
            badgeName = 'OJT Internship';
            break;
          case 'internship-requirement':
            badgeClass = 'bg-warning';
            badgeName = 'Internship Requirement';
            break;
          default:
            badgeClass = 'bg-secondary';
            badgeName = item.reason.replace(/-/g, ' '); // fallback prettify
        }

        const row = document.createElement('tr');
        row.innerHTML = `
      <td>${item.name}</td>
      <td>${item.user_type}</td>
 <td><span class="badge ${badgeClass}">${badgeName}</span></td>
      <td>
        ${item.medical_certificate.id ? 
          `<button class="btn btn-link view-document" data-file="/serve_file.php?id=${item.medical_certificate.id}" data-ext="${item.medical_certificate.ext}" data-bs-toggle="modal" data-bs-target="#viewModal">Medical Certificate</button>` : 
          `<button class="btn btn-link disabled" disabled>Medical Certificate (Pending)</button>`}
        ${requiresXray && item.chest_xray.id ? 
          ` | <button class="btn btn-link view-document" data-file="/serve_file.php?id=${item.chest_xray.id}" data-ext="${item.chest_xray.ext}" data-bs-toggle="modal" data-bs-target="#viewModal">Chest X-Ray</button>` : 
          ''}
      </td>
      <td class="action-buttons">
        <a href="medcertRequest.php?user_id=${item.id}${item.child_id ? '&child_id=' + item.child_id : ''}&document_id=${item.document_id}" class="btn btn-sm btn-primary">Release MedCert</a>
        <button class="btn btn-sm btn-info advise-consult-btn" data-user-id="${item.id}" data-child-id="${item.child_id || ''}" data-email="${item.email}" data-reason="${item.reason}" onclick="openConsultModal(${item.id}, ${item.child_id || 'null'}, '${item.reason}', '${item.email}')">Advise Consult</button>
      </td>
    `;
        tbody.appendChild(row);
      });
    }

    // Render Issued Medical Certificates Table
    function renderMedCertIssuedTable(data) {
      const tbody = document.getElementById('med-cert-issued-table-body');
      tbody.innerHTML = '';
      data.forEach(item => {

        // Choose badge color based on reason (optional)
        let badgeClass = 'bg-secondary';
        let badgeName = item.reason; // fallback if not mapped

        switch (item.reason) {
          case 'local':
            badgeClass = 'bg-success';
            badgeName = 'Local';
            break;
          case 'national':
            badgeClass = 'bg-warning';
            badgeName = 'National';
            break;
          case 'travel-national':
            badgeClass = 'bg-warning';
            badgeName = 'Travel National';
            break;
          case 'international':
            badgeClass = 'bg-danger';
            badgeName = 'International';
            break;
          case 'travel-international':
            badgeClass = 'bg-danger';
            badgeName = 'Travel International';
            break;
          case 'ojt-internship':
            badgeClass = 'bg-primary';
            badgeName = 'OJT Internship';
            break;
          case 'internship-requirement':
            badgeClass = 'bg-warning';
            badgeName = 'Internship Requirement';
            break;
          default:
            badgeClass = 'bg-secondary';
            badgeName = item.reason.replace(/-/g, ' '); // fallback prettify
        }


        const row = document.createElement('tr');
        row.innerHTML = `
      <td>${item.name}</td>
      <td>${item.user_type}</td>
 <td><span class="badge ${badgeClass}">${badgeName}</span></td>

<td>${formatDate(item.date_issued)}</td>
      <td>
        ${item.file_path ? 
          `<button class="btn btn-link view-document" data-file="serve_file.php?id=${item.log_id}" data-ext="${item.ext}" data-bs-toggle="modal" data-bs-target="#viewModal">View</button>` : 
          'No File'}
      </td>
    `;
        tbody.appendChild(row);
      });
    }

    // Render Freshmen Submissions Table
    function renderFreshmenTable(data) {
      const tbody = document.getElementById('freshmen-table-body');
      tbody.innerHTML = '';
      data.forEach(item => {
        const needsHepaB = requiresHepatitisB(item);
        const hasHepatitisB = needsHepaB && item.hepatitis_b_surface_antigen_test_results.id;


        const row = document.createElement('tr');
        row.innerHTML = `
          <td>${item.name}</td>
          <td>${formatDate(item.submission_date)}</td>
 
          <td>
            ${item.chest_xray.id ? 
              `<button class="btn btn-link view-document" data-file="serve_file.php?id=${item.chest_xray.id}" data-ext="${item.chest_xray.ext}" data-bs-toggle="modal" data-bs-target="#viewModal">Chest X-Ray (6 months)</button>` : 
              'Chest X-Ray (Pending)'}
            | 
            ${item.complete_blood_count.id ? 
              `<button class="btn btn-link view-document" data-file="serve_file.php?id=${item.complete_blood_count.id}" data-ext="${item.complete_blood_count.ext}" data-bs-toggle="modal" data-bs-target="#viewModal">CBC</button>` : 
              'CBC (Pending)'}
            | 
            ${item.blood_typing.id ? 
              `<button class="btn btn-link view-document" data-file="serve_file.php?id=${item.blood_typing.id}" data-ext="${item.blood_typing.ext}" data-bs-toggle="modal" data-bs-target="#viewModal">Blood Typing</button>` : 
              'Blood Typing (Pending)'}
            | 
            ${item.urinalysis.id ? 
              `<button class="btn btn-link view-document" data-file="serve_file.php?id=${item.urinalysis.id}" data-ext="${item.urinalysis.ext}" data-bs-toggle="modal" data-bs-target="#viewModal">Urinalysis</button>` : 
              'Urinalysis (Pending)'}
            | 
            ${item.drug_test.id ? 
              `<button class="btn btn-link view-document" data-file="serve_file.php?id=${item.drug_test.id}" data-ext="${item.drug_test.ext}" data-bs-toggle="modal" data-bs-target="#viewModal">Drug Test (1 year)</button>` : 
              'Drug Test (Pending)'}
        ${hasHepatitisB ? 
  ` | <button class="btn btn-link view-document" data-file="serve_file.php?id=${item.hepatitis_b_surface_antigen_test_results.id}" data-ext="${item.hepatitis_b_surface_antigen_test_results.ext}" data-bs-toggle="modal" data-bs-target="#viewModal">Hepatitis B</button>` : 
  (needsHepaB ? ' | Hepatitis B (Pending)' : '')}
          </td>
          <td class="action-buttons">
            <a href="medcertRequest.php?user_id=${item.id}" class="btn btn-sm btn-primary">Release MedCert</a>
            <button class="btn btn-sm btn-info advise-consult-btn" data-user-id="${item.id}" data-email="${item.email}" onclick="openConsultModal(${item.id}, null, '', '${item.email}')">Advise Consult</button>
          </td>
        `;
        tbody.appendChild(row);
      });
    }

    // Render Issued Freshmen Certificates Table
    function renderFreshmenIssuedTable(data) {
      const tbody = document.getElementById('freshmen-issued-table-body');
      tbody.innerHTML = '';
      data.forEach(item => {
        const needsHepaB = requiresHepatitisB(item);
        const hasHepatitisB = needsHepaB && item.hepatitis_b_surface_antigen_test_results.id;
        const row = document.createElement('tr');
        row.innerHTML = `
      <td>${item.name}</td>
      <td>
        ${item.chest_xray.id ? 
          `<button class="btn btn-link view-document" data-file="serve_file.php?id=${item.chest_xray.id}" data-ext="${item.chest_xray.ext}" data-bs-toggle="modal" data-bs-target="#viewModal">Chest X-Ray (6 months)</button>` : 
          'Chest X-Ray (Pending)'}
        | 
        ${item.complete_blood_count.id ? 
          `<button class="btn btn-link view-document" data-file="serve_file.php?id=${item.complete_blood_count.id}" data-ext="${item.complete_blood_count.ext}" data-bs-toggle="modal" data-bs-target="#viewModal">CBC</button>` : 
          'CBC (Pending)'}
        | 
        ${item.blood_typing.id ? 
          `<button class="btn btn-link view-document" data-file="serve_file.php?id=${item.blood_typing.id}" data-ext="${item.blood_typing.ext}" data-bs-toggle="modal" data-bs-target="#viewModal">Blood Typing</button>` : 
          'Blood Typing (Pending)'}
        | 
        ${item.urinalysis.id ? 
          `<button class="btn btn-link view-document" data-file="serve_file.php?id=${item.urinalysis.id}" data-ext="${item.urinalysis.ext}" data-bs-toggle="modal" data-bs-target="#viewModal">Urinalysis</button>` : 
          'Urinalysis (Pending)'}
        | 
        ${item.drug_test.id ? 
          `<button class="btn btn-link view-document" data-file="serve_file.php?id=${item.drug_test.id}" data-ext="${item.drug_test.ext}" data-bs-toggle="modal" data-bs-target="#viewModal">Drug Test (1 year)</button>` : 
          'Drug Test (Pending)'}
       
       
          ${hasHepatitisB ? 
          ` | <button class="btn btn-link view-document" data-file="serve_file.php?id=${item.hepatitis_b.id}" data-ext="${item.hepatitis_b.ext}" data-bs-toggle="modal" data-bs-target="#viewModal">Hepatitis B</button>` : 
         (needsHepaB ? ' | Hepatitis B (Pending)' : '')}
      </td>
            <td>${formatDate(item.date_issued)}</td>
  
      <td>
        ${item.file_path_document ? 
          `<button class="btn btn-link view-document" data-file="serve_file.php?id=${item.log_id}" data-ext="${item.certificate_ext}" data-bs-toggle="modal" data-bs-target="#viewModal">View</button>` : 
          'No File'}
      </td>
    `;
        tbody.appendChild(row);
      });
    }



    // Render Consultation Advice Table
    function renderConsultationsTable(data) {
      const tbody = document.getElementById('consultations-table-body');
      tbody.innerHTML = '';
      data.forEach(item => {
        const row = document.createElement('tr');

        // Choose badge color based on reason (optional)
        let badgeClass = 'bg-secondary';
        let badgeName = item.reason; // fallback if not mapped

        switch (item.reason) {
          case 'local':
            badgeClass = 'bg-success';
            badgeName = 'Local';
            break;
          case 'national':
            badgeClass = 'bg-warning';
            badgeName = 'National';
            break;
          case 'travel-national':
            badgeClass = 'bg-warning';
            badgeName = 'Travel National';
            break;
          case 'international':
            badgeClass = 'bg-danger';
            badgeName = 'International';
            break;
          case 'travel-international':
            badgeClass = 'bg-danger';
            badgeName = 'Travel International';
            break;
          case 'ojt-internship':
            badgeClass = 'bg-primary';
            badgeName = 'OJT Internship';
            break;
          case 'internship-requirement':
            badgeClass = 'bg-warning';
            badgeName = 'Internship Requirement';
            break;
          default:
            badgeClass = 'bg-secondary';
            badgeName = item.reason.replace(/-/g, ' '); // fallback prettify
        }

        let actions = '';
        if (item.status === 'Completed') {
          // Show disabled/readonly state
          actions = `<span class="badge bg-success w-100">Completed</span>`;
        } else {
          // Show normal actions
          actions = `
        <a class="btn btn-sm btn-warning advise-consult-btn"
           href="consultation.php?id=${item.advice_id}&user_id=${item.user_id}&child_id=${item.child_id || ''}&reason=${encodeURIComponent(item.reason)}&email=${encodeURIComponent(item.email)}">
           Open for Consultation
        </a>
        <button class="btn btn-sm btn-info advise-consult-btn" 
          data-user-id="${item.user_id}" 
          data-child-id="${item.child_id || ''}" 
          data-email="${item.email}" 
          data-reason="${item.reason}" 
          onclick="openConsultModal(${item.user_id}, ${item.child_id || 'null'}, '${item.reason}', '${item.email}')">
          Resend Advice
        </button>
        <button class="btn btn-sm btn-danger cancel-consult-btn" 
          data-advice-id="${item.advice_id}" 
          onclick="cancelConsultation(${item.advice_id})">
          Cancel
        </button>
      `;
        }

        row.innerHTML = `
      <td>${item.name}</td>
      <td>${item.user_type}</td>
      <td><span class="badge ${badgeClass}">${badgeName}</span></td>
   <td>${formatDate(item.date_advised)}</td>
      <td>${item.status}</td>
      <td class="action-buttons">${actions}</td>
    `;

        tbody.appendChild(row);
      });
    }


    // Filter and Sort Medical Certificate Requests
    function filterAndSortMedCertTable() {
      const search = document.getElementById('med-cert-search').value.toLowerCase();
      const sort = document.getElementById('med-cert-sort').value;

      let filtered = medCertRequests.filter(item => {
        return item.name.toLowerCase().includes(search);
      });

      if (sort === 'name-asc') {
        filtered.sort((a, b) => a.name.localeCompare(b.name));
      } else if (sort === 'name-desc') {
        filtered.sort((a, b) => b.name.localeCompare(b.name));
      } else if (sort === 'date-asc') {
        filtered.sort((a, b) => new Date(a.submission_date) - new Date(b.submission_date));
      } else if (userTypeOrder.includes(sort)) {
        filtered = filtered.filter(item => item.user_type === sort);
      } else {
        filtered.sort((a, b) => {
          return userTypeOrder.indexOf(a.user_type) - userTypeOrder.indexOf(b.user_type);
        });
      }

      renderMedCertTable(filtered);
    }

    // Filter and Sort Issued Medical Certificates
    function filterAndSortMedCertIssuedTable() {
      const search = document.getElementById('med-cert-issued-search').value.toLowerCase();
      const sort = document.getElementById('med-cert-issued-sort').value;

      let filtered = issuedMedCerts.filter(item => {
        return item.name.toLowerCase().includes(search);
      });

      if (sort === 'name-asc') {
        filtered.sort((a, b) => a.name.localeCompare(b.name));
      } else if (sort === 'name-desc') {
        filtered.sort((a, b) => b.name.localeCompare(b.name));
      } else if (sort === 'date-asc') {
        filtered.sort((a, b) => new Date(a.date_issued) - new Date(b.date_issued));
      } else if (userTypeOrder.includes(sort)) {
        filtered = filtered.filter(item => item.user_type === sort);
      } else {
        filtered.sort((a, b) => {
          return userTypeOrder.indexOf(a.user_type) - userTypeOrder.indexOf(b.user_type);
        });
      }

      renderMedCertIssuedTable(filtered);
    }

    // Filter and Sort Freshmen Submissions
    function filterAndSortFreshmenTable() {
      const search = document.getElementById('freshmen-search').value.toLowerCase();
      const sort = document.getElementById('freshmen-sort').value;

      let filtered = freshmenSubmissions.filter(item => {
        return item.name.toLowerCase().includes(search);
      });

      if (sort === 'name-asc') {
        filtered.sort((a, b) => a.name.localeCompare(b.name));
      } else if (sort === 'name-desc') {
        filtered.sort((a, b) => b.name.localeCompare(b.name));
      } else if (sort === 'date-asc') {
        filtered.sort((a, b) => new Date(a.submission_date) - new Date(b.submission_date));
      }

      renderFreshmenTable(filtered);
    }

    // Filter and Sort Issued Freshmen Certificates
    function filterAndSortFreshmenIssuedTable() {
      const search = document.getElementById('freshmen-issued-search').value.toLowerCase();
      const sort = document.getElementById('freshmen-issued-sort').value;

      let filtered = issuedFreshmenCerts.filter(item => {
        return item.name.toLowerCase().includes(search);
      });

      if (sort === 'name-asc') {
        filtered.sort((a, b) => a.name.localeCompare(b.name));
      } else if (sort === 'name-desc') {
        filtered.sort((a, b) => b.name.localeCompare(b.name));
      } else if (sort === 'date-asc') {
        filtered.sort((a, b) => new Date(a.date_issued) - new Date(b.date_issued));
      }

      renderFreshmenIssuedTable(filtered);
    }

    // Filter and Sort Consultation Advice
    function filterAndSortConsultationsTable() {
      const search = document.getElementById('consultations-search').value.toLowerCase();
      const sort = document.getElementById('consultations-sort').value;

      let filtered = consultationAdvice.filter(item => {
        return item.name.toLowerCase().includes(search);
      });

      if (sort === 'name-asc') {
        filtered.sort((a, b) => a.name.localeCompare(b.name));
      } else if (sort === 'name-desc') {
        filtered.sort((a, b) => b.name.localeCompare(b.name));
      } else if (sort === 'date-asc') {
        filtered.sort((a, b) => new Date(a.date_advised) - new Date(b.date_advised));
      } else if (userTypeOrder.includes(sort)) {
        filtered = filtered.filter(item => item.user_type === sort);
      } else {
        filtered.sort((a, b) => {
          return userTypeOrder.indexOf(a.user_type) - userTypeOrder.indexOf(b.user_type);
        });
      }

      renderConsultationsTable(filtered);
    }



    // Open Consult Modal
    let currentItemId = null;
    let currentChildId = null;
    let currentEmail = null;
    let reasonPlaceholder = null;

    function openConsultModal(userId, childId = null, reason = '', email = '') {
      document.getElementById('consult-reason').value = reason;
      const consultReasonInputPrev = document.getElementById('consult-reason');
      reason = consultReasonInputPrev.value.trim();
      currentItemId = userId;
      currentChildId = childId;
      currentEmail = email;
      reasonPlaceholder = reason;

      const consultReasonInput = document.getElementById('consult-message');

      // Custom message for medical certificate requests
      if (reason == 'medical-clearance') {
        consultReasonInput.value = 'You are requested to visit the clinic for medical certificate issuance.';
      }
      // Custom message for other specific reasons
      else if (reason == 'another-specific-reason') {
        consultReasonInput.value = 'Custom message for another reason';
      }
      // Default message for other cases
      else {
        consultReasonInput.value =
          'You are advised to visit the clinic for a consultation.';
      }

      document.getElementById('consult-modal').style.display = 'block';
      const sendButton = document.getElementById('send-email-btn');
      if (sendButton) {
        // Store data as data attributes on the button
        sendButton.dataset.itemId = userId;
        sendButton.dataset.childId = childId || '';
        sendButton.dataset.email = email;
        sendButton.dataset.reason = reason;
        sendButton.disabled = false;
      }
    }

    // Close Consult Modal
    function closeConsultModal() {
      document.getElementById('consult-modal').style.display = 'none';
      currentItemId = null;
      currentChildId = null;
      currentEmail = null;
      const sendButton = document.getElementById('send-email-btn');
      if (sendButton) {
        sendButton.disabled = false;
      }
    }

    // Helper function to show modal with auto-hide delay
    function showModalWithDelay(modalElement, messageElement, message, duration = 1500, callback = null) {
      const modal = new bootstrap.Modal(modalElement);
      messageElement.textContent = message;
      modal.show();
      setTimeout(() => {
        modal.hide();
        if (callback) callback();
      }, duration);
    }

    // Show Error Modal
    function showErrorModal(message) {
      const errorModal = document.getElementById('errorModal');
      showModalWithDelay(errorModal, document.getElementById('error-message'), message);
    }

    async function sendConsultEmail() {
      const sendButton = document.getElementById('send-email-btn');
      if (!sendButton) {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'Send email button not found. Please refresh the page.',
          confirmButtonColor: '#a6192e'
        });
        return;
      }

      // Disable button to prevent multiple clicks
      sendButton.disabled = true;

      // Show loading indicator
      Swal.fire({
        title: 'Sending Email',
        html: 'Please wait while we send the consultation advice...',
        allowOutsideClick: false,
        didOpen: () => {
          Swal.showLoading();
        }
      });

      try {
        // Get all values from hidden inputs
        const currentItemId = sendButton.dataset.itemId;
        const currentChildId = sendButton.dataset.childId;
        const currentEmail = sendButton.dataset.email;
        const reason = sendButton.dataset.reason;

        const message = document.getElementById('consult-message').value.trim();
        const csrfTokenElement = document.getElementById('csrf-token');
        const adminUserId = document.getElementById('user-id').value;

        // Validate required fields
        if (!csrfTokenElement || !csrfTokenElement.value) {
          throw new Error('CSRF token not found. Please refresh the page.');
        }

        const csrfToken = csrfTokenElement.value;

        if (!reason) {
          throw new Error('Please enter a reason for the consultation.');
        }

        if (!message) {
          throw new Error('Please enter a message for the consultation.');
        }

        if (!currentEmail) {
          throw new Error('User email not found. Please try again.');
        }

        if (!currentItemId) {
          throw new Error('User ID not found. Please try again.');
        }

        if (!adminUserId) {
          throw new Error('Admin user ID not found. Please try again.');
        }

        // Create form data with all required fields
        const formData = new FormData();
        formData.append('user_id', currentItemId);

        if (currentChildId && currentChildId !== 'null' && currentChildId !== '') {
          formData.append('child_id', currentChildId);
        }

        formData.append('admin_user_id', adminUserId);
        formData.append('email', currentEmail);
        formData.append('reason', reason);
        formData.append('message', message);
        formData.append('csrf_token', csrfToken);

        const response = await fetch('send_consult_email.php', {
          method: 'POST',
          body: formData,
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          credentials: 'same-origin'
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
          throw new Error(data.message || 'Failed to send email');
        }

        // Success notification
        Swal.fire({
          icon: 'success',
          title: 'Success!',
          text: 'Consultation advice sent successfully via email.',
          confirmButtonColor: '#a6192e',
          timer: 1500,
          timerProgressBar: true
        }).then(() => {
          if (data.new_csrf_token) {
            document.getElementById('csrf-token').value = data.new_csrf_token;
          }
          closeConsultModal();
          location.reload();
        });

      } catch (error) {
        console.error('Error sending consultation email:', error);
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'An error occurred while sending the email: ' + error.message,
          confirmButtonColor: '#a6192e'
        });
      } finally {
        sendButton.disabled = false;
      }
    }

    // Cancel Consultation Advice
    async function cancelConsultation(id) {
      const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
      const successModalElement = document.getElementById('successModal');
      const successMessageElement = document.getElementById('success-message');

      document.getElementById('confirm-message').textContent = 'Are you sure you want to delete this consultation advice?';

      document.getElementById('confirm-action').onclick = async function() {
        const csrfToken = document.getElementById('csrf-token').value;
        if (!csrfToken) {
          showErrorModal('CSRF token not found. Please refresh the page.');
          confirmModal.hide();
          return;
        }

        try {
          const formData = new FormData();
          formData.append('id', id);
          formData.append('csrf_token', csrfToken);

          const response = await fetch('cancel_consultation.php', {
            method: 'POST',
            body: formData,
            headers: {
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
          });

          const data = await response.json();

          if (response.ok && data.success) {
            confirmModal.hide(); // Hide the confirmation modal immediately

            // Show success modal in front
            showModalWithDelay(successModalElement, successMessageElement,
              data.message || 'Consultation advice deleted successfully.', 1000, () => {
                if (data.new_csrf_token) {
                  document.getElementById('csrf-token').value = data.new_csrf_token;
                }

                // Update table data
                consultationAdvice.splice(
                  consultationAdvice.findIndex(item => item.advice_id == id), 1
                );
                renderConsultationsTable(consultationAdvice);
              });

          } else {
            showErrorModal(data.message || `Failed to delete consultation advice (Status: ${response.status})`);
            confirmModal.hide();
          }
        } catch (error) {
          console.error('Error cancelling consultation:', error);
          showErrorModal(`An error occurred: ${error.message}`);
          confirmModal.hide();
        }
      };

      confirmModal.show();
    }



    // View History
    async function viewHistory(userId, childId = null) {
      const tbody = document.getElementById('history-table-body');
      const errorDiv = document.getElementById('history-error');
      tbody.innerHTML = '';
      errorDiv.textContent = '';

      try {
        const url = `getMedCertHistory.php?user_id=${userId}${childId ? '&child_id=' + childId : ''}`;
        const response = await fetch(url);
        if (!response.ok) {
          throw new Error('Failed to fetch history');
        }
        const history = await response.json();

        if (history.length) {
          history.forEach(item => {
            const row = document.createElement('tr');
            row.innerHTML = `
              <td>${item.sent_at || 'N/A'}</td>
              <td>${item.reason || 'Not Specified'}</td>
              <td>
                ${item.id ? 
                  `<button class="btn btn-link view-document" data-file="${item.file_path}" data-ext="${item.ext}" data-bs-toggle="modal" data-bs-target="#viewModal">View</button>` : 
                  'No File'}
              </td>
            `;
            tbody.appendChild(row);
          });
        } else {
          errorDiv.textContent = 'No medical certificate history found.';
        }
      } catch (error) {
        errorDiv.textContent = 'Error loading history: ' + error.message;
        console.error('History fetch error:', error);
      }
    }

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
      // Render tables
      renderMedCertTable(medCertRequests);
      renderMedCertIssuedTable(issuedMedCerts);
      renderFreshmenTable(freshmenSubmissions);
      renderFreshmenIssuedTable(issuedFreshmenCerts);
      renderConsultationsTable(consultationAdvice);

      // Event listeners for Medical Certificate Requests
      document.getElementById('med-cert-search').addEventListener('input', filterAndSortMedCertTable);
      document.getElementById('med-cert-sort').addEventListener('change', filterAndSortMedCertTable);

      // Event listeners for Issued Medical Certificates
      document.getElementById('med-cert-issued-search').addEventListener('input', filterAndSortMedCertIssuedTable);
      document.getElementById('med-cert-issued-sort').addEventListener('change', filterAndSortMedCertIssuedTable);

      // Event listeners for Freshmen Submissions
      document.getElementById('freshmen-search').addEventListener('input', filterAndSortFreshmenTable);
      document.getElementById('freshmen-sort').addEventListener('change', filterAndSortFreshmenTable);

      // Event listeners for Issued Freshmen Certificates
      document.getElementById('freshmen-issued-search').addEventListener('input', filterAndSortFreshmenIssuedTable);
      document.getElementById('freshmen-issued-sort').addEventListener('change', filterAndSortFreshmenIssuedTable);

      // Event listeners for Consultation Advice
      document.getElementById('consultations-search').addEventListener('input', filterAndSortConsultationsTable);
      document.getElementById('consultations-sort').addEventListener('change', filterAndSortConsultationsTable);

      // Sidebar toggle
      const burgerBtn = document.getElementById('burger-btn');
      const sidebar = document.getElementById('sidebar');
      const mainContent = document.getElementById('main-content');

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

      // Initialize dashboard
      showDashboard();

      // Attach event listener to Send via Email button
      const sendEmailButton = document.getElementById('send-email-btn');
      if (sendEmailButton) {
        sendEmailButton.addEventListener('click', sendConsultEmail);
      }

      // Handle view document
      document.addEventListener('click', async function(event) {
        const button = event.target.closest('.view-document');
        if (!button) return;

        event.preventDefault();
        const fileUrl = button.dataset.file;
        const ext = button.dataset.ext.toLowerCase();
        const documentId = button.dataset.id || 'unknown'; // Assuming data-id is set
        console.log(`Attempting to view document: URL=${fileUrl}, Extension=${ext}, DocumentID=${documentId}`); // Debug
        const iframe = document.getElementById('view-iframe');
        const modalBody = iframe.parentNode;
        const errorDiv = document.getElementById('view-error');
        const downloadLink = document.getElementById('download-link');

        // Reset modal content
        modalBody.querySelectorAll('img, p:not(#view-error)').forEach(el => el.remove());
        errorDiv.textContent = '';
        iframe.src = '';
        iframe.style.display = 'none';
        downloadLink.style.display = 'none';
        downloadLink.href = fileUrl;

        try {
          const response = await fetch(fileUrl, {
            method: 'HEAD'
          });
          console.log(`Fetch response: Status=${response.status}, StatusText=${response.statusText}, URL=${fileUrl}`); // Debug
          if (!response.ok) {
            const errorMessage = response.status === 404 ? 'File not found on server. The file may have been deleted or moved.' :
              response.status === 403 ? 'Forbidden: Access denied.' :
              response.status === 500 ? 'Server error. The file may be corrupted or inaccessible.' :
              `Server error (${response.status}).`;
            throw new Error(errorMessage);
          }

          if (['jpg', 'jpeg', 'png'].includes(ext)) {
            iframe.style.display = 'none';
            const img = document.createElement('img');
            img.src = fileUrl;
            img.className = 'view-image';
            img.alt = 'Document Image';
            img.onerror = () => {
              errorDiv.textContent = `Error loading image (${ext.toUpperCase()}). The file may be corrupted or not a valid image.`;
              console.error(`Image load error: URL=${fileUrl}, Extension=${ext}, DocumentID=${documentId}`);
              downloadLink.style.display = 'inline-block';
              downloadLink.textContent = 'Download Instead';
            };
            img.onload = () => {
              console.log(`Image loaded successfully: URL=${fileUrl}, DocumentID=${documentId}`);
            };
            modalBody.insertBefore(img, iframe);
            downloadLink.style.display = 'inline-block';
            downloadLink.textContent = 'Download Image';
          } else if (ext === 'pdf') {
            iframe.style.display = 'block';
            iframe.src = fileUrl;
            iframe.onerror = () => {
              errorDiv.textContent = 'Error loading PDF. The file may be corrupted or your browser does not support PDF viewing.';
              console.error(`PDF load error: URL=${fileUrl}, DocumentID=${documentId}`);
              downloadLink.style.display = 'inline-block';
              downloadLink.style.display = 'text-black';
              downloadLink.textContent = 'Download PDF';
            };
            iframe.onload = () => {
              console.log(`PDF loaded successfully: URL=${fileUrl}, DocumentID=${documentId}`);
            };
            downloadLink.style.display = 'inline-block';
                 downloadLink.style.display = 'text-black';
            downloadLink.textContent = 'Download PDF';
          } else {
            iframe.style.display = 'none';
            const message = document.createElement('p');
            message.textContent = `This file type (.${ext}) cannot be previewed. Please download to view.`;
            modalBody.insertBefore(message, iframe);
            downloadLink.style.display = 'inline-block';
            downloadLink.textContent = `Download Document (.${ext})`;
          }
        } catch (error) {
          errorDiv.textContent = error.message;
          console.error(`Fetch error: ${error.message}, URL=${fileUrl}, Extension=${ext}, DocumentID=${documentId}`);
          downloadLink.style.display = 'inline-block';
          downloadLink.textContent = 'Attempt Download';
        }
      });

      // Reset view modal when closed
      const viewModal = document.getElementById('viewModal');
      if (viewModal) {
        viewModal.addEventListener('hidden.bs.modal', () => {
          const iframe = document.getElementById('view-iframe');
          const modalBody = iframe.parentNode;
          const errorDiv = document.getElementById('view-error');
          const downloadLink = document.getElementById('download-link');
          iframe.src = '';
          iframe.style.display = 'none';
          errorDiv.textContent = '';
          modalBody.querySelectorAll('img, p:not(#view-error)').forEach(el => el.remove());
          downloadLink.style.display = 'none';
          modalBody.appendChild(iframe);
          modalBody.appendChild(errorDiv);
        });
      }

      // Reset history modal when closed
      const historyModal = document.getElementById('historyModal');
      if (historyModal) {
        historyModal.addEventListener('hidden.bs.modal', () => {
          const tbody = document.getElementById('history-table-body');
          const errorDiv = document.getElementById('history-error');
          tbody.innerHTML = '';
          errorDiv.textContent = '';
        });
      }
    });
  </script>
</body>

</html>