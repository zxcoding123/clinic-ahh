<?php
session_start();
require_once 'config.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("Session user_id not set. Redirecting to login.");
    header("Location: /login.php");
    exit();
}

// Fetch user data
$userId = (int)$_SESSION['user_id'];
$query = "SELECT user_type FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    die("Database error.");
}
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
$userType = $user['user_type'] ?? '';

// Check if user is a Parent by checking the parents table
$isParent = false;
$children = [];
$childId = isset($_GET['child_id']) ? (int)$_GET['child_id'] : null;
if ($userType === 'Parent') {
    $query = "SELECT id FROM parents WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $isParent = true;
        // Fetch children
        $query = "SELECT id, first_name, last_name
FROM children
WHERE parent_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($child = $result->fetch_assoc()) {
            $children[] = [
                'id' => $child['id'],
                'name' => $child['first_name'] . ' ' . $child['last_name']
            ];
        }
        $stmt->close();
    }
}

// Handle API-like requests within the same file
$action = $_GET['action'] ?? '';

if ($action === 'get_appointments') {
    ob_clean();
    header('Content-Type: application/json');
    try {
        // Validate database connection
        if (!$conn || $conn->connect_error) {
            throw new Exception("Database connection failed: " . ($conn ? $conn->connect_error : "No connection object"));
        }

        $appointments = [];
        $currentDateTime = new DateTime('now', new DateTimeZone('Asia/Manila')); // Explicit timezone
        $currentDate = $currentDateTime->format('Y-m-d');
        $currentTime = $currentDateTime->format('H:i:s');

        // Log query start
        error_log("Starting get_appointments for user_id: $userId, current time: $currentDate $currentTime");

        // Update past appointments to Cancelled
        $updateQuery = "UPDATE appointments 
                        SET status = 'Cancelled', updated_at = CURRENT_TIMESTAMP 
                        WHERE user_id = ? 
                        AND appointment_type = 'medical' 
                        AND status = 'Pending' 
                        AND (appointment_date < ? OR (appointment_date = ? AND appointment_time < ?))";
        $stmt = $conn->prepare($updateQuery);
        if (!$stmt) {
            throw new Exception("Update prepare failed: " . $conn->error);
        }
        $stmt->bind_param("isss", $userId, $currentDate, $currentDate, $currentTime);
        if (!$stmt->execute()) {
            throw new Exception("Update execute failed: " . $stmt->error);
        }
        $affectedRows = $stmt->affected_rows;
        error_log("Update query affected $affectedRows rows");
        $stmt->close();

        // Fetch all appointments
        $query = "SELECT a.id, a.reason, a.appointment_date, a.appointment_time, a.status, 
                         c.first_name AS child_first_name, c.last_name AS child_last_name
                  FROM appointments a
                  LEFT JOIN children c ON a.child_id = c.id
                  WHERE a.user_id = ? AND a.appointment_type = 'medical'";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Select prepare failed: " . $conn->error);
        }
        $stmt->bind_param("i", $userId);
        if (!$stmt->execute()) {
            throw new Exception("Select execute failed: " . $stmt->error);
        }
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $appointments[] = [
                'id' => $row['id'],
                'reason' => $row['reason'],
                'appointment_date' => $row['appointment_date'],
                'appointment_time' => date('h:i A', strtotime($row['appointment_time'])),
                'status' => $row['status'],
                'child_name' => $row['child_first_name'] ? $row['child_first_name'] . ' ' . $row['child_last_name'] : null
            ];
        }
        error_log("Fetched " . count($appointments) . " appointments");
        $stmt->close();
        echo json_encode($appointments);
    } catch (Exception $e) {
        error_log("Error in get_appointments: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch appointments: ' . $e->getMessage()]);
    }
    ob_end_flush();
    exit();
}

if ($action === 'check_appointments') {
    ob_clean();
    header('Content-Type: application/json');
    try {
        $date = $_GET['date'] ?? '';
        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new Exception('Invalid date format');
        }
        $childId = ($isParent && isset($_GET['child_id']) && $_GET['child_id'] !== '') ? (int)$_GET['child_id'] : null;
        $bookedTimes = [];
        $query = "SELECT appointment_time 
                  FROM appointments 
                  WHERE user_id = ? AND appointment_date = ? AND status != 'Cancelled'";
        $params = ["is", $userId, $date];
        if ($childId !== null) {
            $query .= " AND child_id = ?";
            $params[0] .= "i";
            $params[] = $childId;
        }
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param(...$params);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $bookedTimes[] = date('h:i A', strtotime($row['appointment_time']));
        }
        $stmt->close();
        echo json_encode($bookedTimes);
    } catch (Exception $e) {
        error_log("Error in check_appointments: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch booked times: ' . $e->getMessage()]);
    }
    ob_end_flush();
    exit();
}

if ($action === 'book_appointment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // Log incoming POST data for debugging
    error_log("POST data: " . print_r($_POST, true));

    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $childId = ($isParent && isset($_POST['child_id']) && $_POST['child_id'] !== '') ? (int)$_POST['child_id'] : null;
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
    $appointmentDate = isset($_POST['appointment_date']) ? trim($_POST['appointment_date']) : '';
    $appointmentTime = isset($_POST['appointment_time']) ? trim($_POST['appointment_time']) : '';
    $appointmentType = isset($_POST['appointment_type']) ? trim($_POST['appointment_type']) : 'medical';

    // Validate inputs
    if ($userId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
        exit();
    }
    if (!$reason) {
        echo json_encode(['success' => false, 'message' => 'Reason is required']);
        exit();
    }
    if (!$appointmentDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointmentDate)) {
        echo json_encode(['success' => false, 'message' => 'Invalid appointment date']);
        exit();
    }
    if (!$appointmentTime) {
        echo json_encode(['success' => false, 'message' => 'Appointment time is required']);
        exit();
    }

    $isStudent = in_array($userType, ['Incoming Freshman', 'Senior High School', 'College', 'Highschool']);



    // Convert appointment time to 24-hour format (HH:MM:SS)
    $timeParsed = date_parse($appointmentTime);
    if ($timeParsed['errors'] || !isset($timeParsed['hour']) || !isset($timeParsed['minute'])) {
        error_log("Invalid time format: " . $appointmentTime);
        echo json_encode(['success' => false, 'message' => 'Invalid time format']);
        exit();
    }
    $appointmentTime = sprintf('%02d:%02d:00', $timeParsed['hour'], $timeParsed['minute']);

    // Validate user_id exists
    $query = "SELECT id FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'User does not exist']);
        exit();
    }
    $stmt->close();

    // Validate child_id only for Parent users
    if ($isParent && $childId !== null) {
        $query = "SELECT id FROM children WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $childId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $stmt->close();
            echo json_encode(['success' => false, 'message' => 'Child does not exist']);
            exit();
        }
        $stmt->close();
    }

    // Check for existing appointments on the same day
    $query = "SELECT id FROM appointments 
              WHERE user_id = ? AND appointment_date = ? AND status != 'Cancelled'";
    $params = ["is", $userId, $appointmentDate];
    if ($isParent && $childId !== null) {
        $query .= " AND child_id = ?";
        $params[0] .= "i";
        $params[] = $childId;
    }
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit();
    }
    $stmt->bind_param(...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'You already have an appointment on this date']);
        exit();
    }
    $stmt->close();

    $userId = (int)$_SESSION['user_id'];

    // ✅ Fetch user full name before notification
    $userQuery = "SELECT first_name, middle_name, last_name FROM users WHERE id = ?";
    $stmt = $conn->prepare($userQuery);
    if (!$stmt) {
        error_log("Prepare failed when fetching user: " . $conn->error);
    } else {


        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        $full_name_parts = array_filter([
            $user['first_name'] ?? '',
            $user['middle_name'] ?? '',
            $user['last_name'] ?? ''
        ]);

        $full_name_for_notification = implode(' ', $full_name_parts);
    }




    // ✅ Send notifications to Super Admin, Medical Admin, and Dental Admin
    $adminQuery = $conn->prepare("
                    SELECT id 
                    FROM users 
                    WHERE user_type IN ('Super Admin',  'Medical Admin')
                ");
    $adminQuery->execute();
    $adminResult = $adminQuery->get_result();

    $notificationTitle = "Medical Appointment Request";
    $notificationDescription = "$full_name_for_notification has requested for a medical appointment!";
    $notificationLink = "#"; // Change to actual waiver view link
    $notificationType = "waiver_submission";

    $notificationStmt = $conn->prepare("
                    INSERT INTO notifications_admin (
                        user_id, type, title, description, link, status, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, 'unread', NOW(), NOW())
                ");

    while ($adminRow = $adminResult->fetch_assoc()) {
        $targetUserId = $adminRow['id'];

        $notificationStmt->bind_param(
            "issss",
            $targetUserId,
            $notificationType,
            $notificationTitle,
            $notificationDescription,
            $notificationLink
        );

        if (!$notificationStmt->execute()) {
            error_log("Failed to create admin notification for user {$targetUserId}: " . $notificationStmt->error);
        }
    }

    // ✅ Get current academic year
    $academic_sql = "SELECT id, start_year, end_year, grading_quarter, semester, created_at 
                 FROM academic_years 
                 ORDER BY created_at DESC 
                 LIMIT 1";

    $academic_result = $conn->query($academic_sql);

    if ($academic_result && $academic_result->num_rows > 0) {
        $row = $academic_result->fetch_assoc();

        $currentAcademicSchoolYear = $row['start_year'] . '-' . $row['end_year'];
        $gradingQuarter = $row['grading_quarter'];
        $semester = $row['semester'];
    } else {
        // fallback if no academic year exists
        $currentAcademicSchoolYear = null;
        $gradingQuarter = null;
        $semester = null;
    }


    // ✅ Book the appointment
    $query = "INSERT INTO appointments 
    (user_id, child_id, reason, appointment_date, appointment_time, appointment_type, academic_school_year, semester, grading_quarter, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit();
    }

    // Now bind params, including academic year values
    $stmt->bind_param(
        "iisssssss",
        $userId,
        $childId,
        $reason,
        $appointmentDate,
        $appointmentTime,
        $appointmentType,
        $currentAcademicSchoolYear,
        $semester,
        $gradingQuarter
    );

    $success = $stmt->execute();
    if (!$success) {
        error_log("Insert failed: " . $stmt->error);
        echo json_encode(['success' => false, 'message' => 'Failed to book appointment: ' . $stmt->error]);
        $stmt->close();
        exit();
    }

    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Appointment booked successfully'
    ]);
    exit();
}

if ($action === 'cancel_appointment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $appointmentId = (int)($input['id'] ?? 0);


    $userId = (int)$_SESSION['user_id'];

    // ✅ Fetch user full name before notification
    $userQuery = "SELECT first_name, middle_name, last_name FROM users WHERE id = ?";
    $stmt = $conn->prepare($userQuery);
    if (!$stmt) {
        error_log("Prepare failed when fetching user: " . $conn->error);
    } else {


        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        $full_name_parts = array_filter([
            $user['first_name'] ?? '',
            $user['middle_name'] ?? '',
            $user['last_name'] ?? ''
        ]);

        $full_name_for_notification = implode(' ', $full_name_parts);
    }


    // ✅ Send notifications to Super Admin, Medical Admin, and Dental Admin
    $adminQuery = $conn->prepare("
                    SELECT id 
                    FROM users 
                    WHERE user_type IN ('Super Admin',  'Dental Admin')
                ");
    $adminQuery->execute();
    $adminResult = $adminQuery->get_result();

    $notificationTitle = "Medical Appointment Cancellation";
    $notificationDescription = "$full_name_for_notification has canelled the medical appointment!";
    $notificationLink = "#"; // Change to actual waiver view link
    $notificationType = "waiver_submission";

    $notificationStmt = $conn->prepare("
                    INSERT INTO notifications_admin (
                        user_id, type, title, description, link, status, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, 'unread', NOW(), NOW())
                ");

    while ($adminRow = $adminResult->fetch_assoc()) {
        $targetUserId = $adminRow['id'];

        $notificationStmt->bind_param(
            "issss",
            $targetUserId,
            $notificationType,
            $notificationTitle,
            $notificationDescription,
            $notificationLink
        );

        if (!$notificationStmt->execute()) {
            error_log("Failed to create admin notification for user {$targetUserId}: " . $notificationStmt->error);
        }
    }

    $query = "UPDATE appointments SET status = 'Cancelled' WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $appointmentId, $userId);
    $success = $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Appointment cancelled successfully' : 'Failed to cancel appointment'
    ]);
    exit();
}

if ($action === 'delete_appointment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $appointmentId = (int)($input['id'] ?? 0);

    $userId = (int)$_SESSION['user_id'];

    // ✅ Fetch user full name before notification
    $userQuery = "SELECT first_name, middle_name, last_name FROM users WHERE id = ?";
    $stmt = $conn->prepare($userQuery);
    if (!$stmt) {
        error_log("Prepare failed when fetching user: " . $conn->error);
    } else {


        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        $full_name_parts = array_filter([
            $user['first_name'] ?? '',
            $user['middle_name'] ?? '',
            $user['last_name'] ?? ''
        ]);

        $full_name_for_notification = implode(' ', $full_name_parts);
    }




    // ✅ Send notifications to Super Admin, Medical Admin, and Dental Admin
    $adminQuery = $conn->prepare("
                    SELECT id 
                    FROM users 
                    WHERE user_type IN ('Super Admin',  'Medical Admin')
                ");
    $adminQuery->execute();
    $adminResult = $adminQuery->get_result();

    $notificationTitle = "Medical Appointment Request";
    $notificationDescription = "$full_name_for_notification has requested for a medical appointment!";
    $notificationLink = "#"; // Change to actual waiver view link
    $notificationType = "waiver_submission";

    $notificationStmt = $conn->prepare("
                    INSERT INTO notifications_admin (
                        user_id, type, title, description, link, status, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, 'unread', NOW(), NOW())
                ");

    while ($adminRow = $adminResult->fetch_assoc()) {
        $targetUserId = $adminRow['id'];

        $notificationStmt->bind_param(
            "issss",
            $targetUserId,
            $notificationType,
            $notificationTitle,
            $notificationDescription,
            $notificationLink
        );

        if (!$notificationStmt->execute()) {
            error_log("Failed to create admin notification for user {$targetUserId}: " . $notificationStmt->error);
        }
    }

    $query = "DELETE FROM appointments WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $appointmentId, $userId);
    $success = $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Record deleted successfully' : 'Failed to delete record'
    ]);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>University Health Services - Medical Appointment Request</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/MedicalRequest.css">
    <link rel="apple-touch-icon" sizes="180x180" href="images/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="images/favicon-16x16.png">
    <link rel="manifest" href="images/site.webmanifest">
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <style>
        body {
            font-family: 'Poppins';
        }

        .modal-header {
            background-color: #a6192e;
            color: white;
        }

        .modal-footer .btn-primary {
            background-color: #a6192e;
            border-color: #a6192e;
        }

        .modal-footer .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }

        .timeslot {
            cursor: pointer;
            padding: 10px;
            margin: 5px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        .timeslot.selected {
            background-color: #a6192e;
            color: white;
        }

        .timeslot.disabled {
            background-color: #e9ecef;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .day.unavailable {
            background-color: #f8d7da;
            cursor: not-allowed;
        }

        .calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
        }

        .day {
            text-align: center;
            cursor: pointer;
        }

        .day.unavailable {
            background-color: #f8d7da;
            cursor: not-allowed;
        }

        .day.selected {
            background-color: #a6192e;
            color: white;
        }

        #appointmentsModal {
            z-index: 1060;
        }

        #confirmCancelModal,
        #confirmDeleteModal,
        #successModal,
        #errorModal,
        #logoutModal {
            z-index: 1070;
        }

        .active {
            background-color: #4f1515 !important;
            color: white !important;
        }

        .day.unavailable {
            pointer-events: none;
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
</head>

<body>
    <div id="app" class="d-flex">
        <button id="sidebar-toggle" class="sidebar-toggle d-lg-none">☰</button>
        <div id="sidebar" class="sidebar d-flex flex-column align-items-center">
            <img src="images/clinic.png" alt="WMSU Clinic Logo" class="logo">
            <div class="text-white fw-bold text-uppercase text-center mt-2">WMSU</div>
            <div class="health-services text-white fw-bold text-uppercase text-center mt-2 mb-5">Health Services</div>
            <button class="btn btn-crimson mb-2 w-100" id="about-btn" onclick="window.location.href='homepage.php'">About Us</button>
            <button class="btn btn-crimson mb-2 w-100" id="announcement-btn" onclick="window.location.href='announcements.php'">Announcements</button>
            <button class="btn btn-crimson active mb-2 w-100" id="appointment-btn" onclick="window.location.href='appointment.php'">Appointment Request</button>
            <button class="btn btn-crimson mb-2 w-100" id="upload-btn" onclick="window.location.href='upload.php'">Upload Medical Documents</button>
            <button class="btn btn-crimson mb-2 w-100" id="profile-btn" onclick="window.location.href='profile.php'">Profile</button>
            <button class="btn btn-crimson w-100" id="logout-btn" data-bs-toggle="modal" data-bs-target="#logoutModal">Logout</button>
            <a href="https://wmsu.edu.ph" target="_blank" class="quick-link">wmsu.edu.ph</a>
        </div>

        <div class="main-content" id="mainContent">
            <div class="header">
                <h1>Medical Appointment Request</h1>
            </div>

            <section class="appointment-section">
                <button type="button" class="today-btn" id="view-appointments-btn">View My Appointments</button>

            </section>

            <section class="calendar-section">
                <h2 class="calendar-title">Select an Appointment Date & Time</h2>
                <div class="month-navigation">
                    <button class="nav-btn" id="prevMonth">Previous</button>
                    <span id="currentMonth"></span>
                    <button class="nav-btn" id="nextMonth">Next</button>
                </div>
                <div class="selected-info">
                    <div class="info-group">
                        <div class="info-label">Selected Date:</div>
                        <input type="text" id="selectedDate" class="info-input" readonly placeholder="Select a date">
                    </div>
                    <div class="info-group">
                        <div class="info-label">Selected Time:</div>
                        <input type="text" id="selectedTime" class="info-input" readonly placeholder="Select a time">
                    </div>
                </div>
                <div class="calendar-header">
                    <div class="calendar-day-name">Sun</div>
                    <div class="calendar-day-name">Mon</div>
                    <div class="calendar-day-name">Tue</div>
                    <div class="calendar-day-name">Wed</div>
                    <div class="calendar-day-name">Thu</div>
                    <div class="calendar-day-name">Fri</div>
                    <div class="calendar-day-name">Sat</div>
                </div>
                <div class="calendar" id="calendar"></div>

                <form class="appointment-form">
                    <?php if ($isParent && !empty($children)): ?>
                        <div class="form-group mb-3">
                            <br>
                            <label>Select a child below: </label>
                            <select class="form-control" id="childSelect"
                                onchange="location.href='?child_id=' + this.value" required>
                                <option value="" disabled <?php echo !$childId ? 'selected' : ''; ?>>Select a Child</option>
                                <?php foreach ($children as $child): ?>
                                    <option value="<?php echo $child['id']; ?>"
                                        <?php echo $childId === $child['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($child['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php elseif ($isParent): ?>
                        <div class="form-group mb-3">
                            <p>No children registered. Please add a child in your profile.</p>
                        </div>
                    <?php endif; ?>
                    <br>

                    <?php
                    $user_id = $_SESSION['user_id'];
                    $today = date("Y-m-d");
                    $dayOfWeek = date('N', strtotime($today)); // 1 (Mon) ... 7 (Sun)

                    // If today is Sunday (7), start next day as Monday
                    if ($dayOfWeek == 7) {
                        $week_start = date("Y-m-d", strtotime($today . ' +1 day'));
                        $week_end   = date("Y-m-d", strtotime($week_start . ' +6 days'));
                    } else {
                        $week_start = date("Y-m-d", strtotime($today . ' -' . ($dayOfWeek - 1) . ' days'));
                        $week_end   = date("Y-m-d", strtotime($week_start . ' +6 days'));
                    }

                    // Default: parent (self) appointments
                    $target_id = $user_id;
                    $id_column = "user_id";

                    // If parent selects a child
                    if ($isParent && isset($_GET['child_id']) && !empty($_GET['child_id'])) {
                        $target_id = intval($_GET['child_id']);
                        $id_column = "child_id";
                    }

                    $sql = "SELECT COUNT(*) AS total 
        FROM appointments 
        WHERE $id_column = '$target_id' 
        AND appointment_date BETWEEN '$week_start' AND '$week_end' AND appointment_type = 'dental'";

                    $result = $conn->query($sql);
                    $row = $result->fetch_assoc();
                    $appointments_count = $row['total'] ?? 0;
                    ?>

                    <script>
                        const appointmentsCount = <?php echo $appointments_count; ?>;
                    </script>

                    <label><b>Select reason for appointment below: </b></label>
                    <br>
                    <small>
                        You have <?php echo $appointments_count; ?>/3 consultations
                        <?php echo ($appointments_count < 3) ? "left" : "used"; ?> this week
                        <?php if ($isParent && isset($_GET['child_id'])): ?>
                            for <?php echo htmlspecialchars($children[array_search($_GET['child_id'], array_column($children, 'id'))]['name']); ?>
                            <?php endif; ?>.
                    </small>
                    <div class="form-group">
                        <select class="form-control" id="appointment-reason" required
                            <?php echo ($appointments_count >= 3) ? 'disabled' : ''; ?>>
                            <option value="" disabled selected>Reason for appointment</option>
                            <option value="check-up">Regular Check-up</option>
                            <option value="illness">Illness</option>
                            <option value="injury">Injury</option>
                            <option value="vaccination">Vaccination</option>
                            <option value="counseling">Counseling</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group" id="other-reason-group" style="display: none;">
                        <input type="text" class="form-control" id="other-reason"
                            placeholder="Please specify the reason"
                            <?php echo ($appointments_count >= 3) ? 'disabled' : ''; ?>>
                    </div>
                </form>

                <?php if ($appointments_count < 3): ?>
                    <div class="timeslots-section">
                        <h3 class="timeslots-title">Available Time Slots</h3>
                        <div class="timeslots-container" id="timeSlots"></div>
                    </div>
                    <button type="button" class="confirm-button" id="confirm-btn">Confirm Appointment</button>
                <?php else: ?>
                    <div class="alert alert-danger mt-3">
                        You have already reached the maximum of 3 consultations for this week.
                    </div>
                <?php endif; ?>
            </section>


            <!-- Logout Confirmation Modal -->
            <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="logoutModalLabel">Confirm Logout</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            Are you sure you want to logout?
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">No</button>
                            <button type="button" class="btn btn-sm btn-primary" onclick="window.location.href='logout.php'">Yes</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Success Modal -->
            <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="successModalLabel">Success</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body"></div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">OK</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Error Modal -->
            <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="errorModalLabel">Error</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body"></div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Confirmation Modal -->
            <div class="modal fade" id="confirmCancelModal" tabindex="-1" aria-labelledby="confirmCancelModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="confirmCancelModalLabel">Confirm Cancellation</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            Are you sure you want to cancel this appointment?
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">No</button>
                            <button type="button" class="btn btn-primary btn-sm" id="confirmCancelBtn">Yes</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delete Confirmation Modal -->
            <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="confirmDeleteModalLabel">Confirm Deletion</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            Are you sure you want to delete this record? This action cannot be undone.
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">No</button>
                            <button type="button" class="btn btn-primary btn-sm" id="confirmDeleteBtn">Yes</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Appointments Modal -->
            <div class="modal fade" id="appointmentsModal" tabindex="-1" aria-labelledby="appointmentsModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="appointmentsModalLabel">My Appointments</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="modal-tabs">
                                <div class="modal-tab active" data-tab="upcoming">Upcoming</div>
                                <div class="modal-tab" data-tab="past">History</div>
                            </div>
                            <div class="tab-content active" id="upcoming-tab"></div>
                            <div class="tab-content" id="past-tab"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <?php include('notifications_user.php') ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // DOM Elements
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const mainContent = document.getElementById('mainContent');
        const calendarDiv = document.getElementById('calendar');
        const timeSlotsDiv = document.getElementById('timeSlots');
        const confirmBtn = document.getElementById('confirm-btn');
        const appointmentReasonSelect = document.getElementById('appointment-reason');
        const otherReasonGroup = document.getElementById('other-reason-group');
        const otherReasonInput = document.getElementById('other-reason');
        const viewAppointmentsBtn = document.getElementById('view-appointments-btn');
        const prevMonthBtn = document.getElementById('prevMonth');
        const nextMonthBtn = document.getElementById('nextMonth');
        const currentMonthDisplay = document.getElementById('currentMonth');
        const selectedDateInput = document.getElementById('selectedDate');
        const selectedTimeInput = document.getElementById('selectedTime');
        const childSelect = document.getElementById('childSelect');

        // Modals
        const successModal = new bootstrap.Modal(document.getElementById('successModal'));
        const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
        const confirmCancelModal = new bootstrap.Modal(document.getElementById('confirmCancelModal'));
        const confirmDeleteModal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
        const appointmentsModal = new bootstrap.Modal(document.getElementById('appointmentsModal'));

        // Calendar State
        const today = new Date();
        let currentMonth = today.getMonth();
        let currentYear = today.getFullYear();
        let selectedAppointmentId = null;

        // Initialize
        try {
            updateMonthDisplay();
            createCalendar();
            loadAppointments();
        } catch (error) {
            console.error('Initialization error:', error);
            showErrorModal('Failed to initialize calendar. Please refresh the page.');
        }

        // Event Listeners
        sidebarToggle.addEventListener('click', toggleSidebar);
        appointmentReasonSelect.addEventListener('change', handleReasonChange);
        confirmBtn.addEventListener('click', confirmAppointment);
        viewAppointmentsBtn.addEventListener('click', () => {
            document.querySelectorAll('.modal.show').forEach(modal => {
                bootstrap.Modal.getInstance(modal).hide();
            });
            loadAppointments();
            setActiveTab('upcoming');
            appointmentsModal.show();
        });
        prevMonthBtn.addEventListener('click', () => changeMonth(-1));
        nextMonthBtn.addEventListener('click', () => changeMonth(1));
        document.querySelectorAll('.modal-tab').forEach(tab => {
            tab.addEventListener('click', switchTab);
        });
        document.getElementById('confirmCancelBtn').addEventListener('click', confirmCancel);
        document.getElementById('confirmDeleteBtn').addEventListener('click', confirmDelete);

        // Functions
        function toggleSidebar() {
            sidebar.classList.toggle('open');
            mainContent.classList.toggle('sidebar-open');
        }

        function handleReasonChange() {
            otherReasonGroup.style.display = this.value === 'other' ? 'block' : 'none';
            otherReasonInput.value = '';
        }

        function updateMonthDisplay() {
            try {
                currentMonthDisplay.textContent =
                    new Date(currentYear, currentMonth).toLocaleString('default', {
                        month: 'long',
                        year: 'numeric'
                    });
                createCalendar();
            } catch (error) {
                console.error('Error updating month display:', error);
                showErrorModal('Failed to update calendar month.');
            }
        }

        function changeMonth(change) {
            try {
                currentMonth += change;
                if (currentMonth > 11) {
                    currentMonth = 0;
                    currentYear++;
                } else if (currentMonth < 0) {
                    currentMonth = 11;
                    currentYear--;
                }
                updateMonthDisplay();
            } catch (error) {
                console.error('Error changing month:', error);
                showErrorModal('Failed to change calendar month.');
            }
        }

        function createCalendar() {
            try {
                if (!calendarDiv) {
                    throw new Error('Calendar div not found');
                }

                const calendarDisabled = appointmentsCount >= 3;

                calendarDiv.innerHTML = '';
                console.log(`Creating calendar for ${currentYear}-${currentMonth + 1}`);

                const firstDayOfMonth = new Date(currentYear, currentMonth, 1).getDay();
                const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();

                for (let i = 0; i < firstDayOfMonth; i++) {
                    const emptyDay = document.createElement('div');
                    emptyDay.classList.add('day', 'unavailable');
                    calendarDiv.appendChild(emptyDay);
                }

                for (let i = 1; i <= daysInMonth; i++) {
                    const dayDiv = document.createElement('div');
                    dayDiv.textContent = i;
                    dayDiv.classList.add('day');

                    const tempDate = new Date(currentYear, currentMonth, i);
                    const todayDate = new Date();
                    todayDate.setHours(0, 0, 0, 0);

                    const isSunday = tempDate.getDay() === 0;
                    const isPast = tempDate < todayDate;

                    if (isSunday || isPast || calendarDisabled) {
                        dayDiv.classList.add('unavailable');
                    } else {
                        dayDiv.addEventListener('click', function() {
                            document.querySelectorAll('.day').forEach(day => day.classList.remove('selected'));
                            this.classList.add('selected');
                            selectedDateInput.value = `${tempDate.getFullYear()}-${String(tempDate.getMonth() + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
                            generateTimeSlots(tempDate);
                        });
                    }

                    calendarDiv.appendChild(dayDiv);
                }
            } catch (error) {
                console.error('Error creating calendar:', error);
                calendarDiv.innerHTML = '<p>Error loading calendar. Please try again.</p>';
                showErrorModal('Failed to load calendar.');
            }
        }



        function generateTimeSlots(date) {
            try {
                timeSlotsDiv.innerHTML = '';
                selectedTimeInput.value = '';

                const isSaturday = date.getDay() === 6;
                const availableTimes = isSaturday ? ["8:00 AM", "9:00 AM", "10:00 AM", "11:00 AM"] : ["8:00 AM", "9:00 AM", "10:00 AM", "11:00 AM", "12:00 PM", "1:00 PM", "2:00 PM", "3:00 PM", "4:00 PM"];

                const childId = childSelect && childSelect.value && '<?php echo $isParent; ?>' === '1' ? childSelect.value : '';
                const url = `?action=check_appointments&date=${selectedDateInput.value}&user_id=<?php echo $userId; ?>${childId ? `&child_id=${childId}` : ''}`;
                console.log('Fetching time slots from:', url);
                fetch(url)
                    .then(response => {
                        if (!response.ok) {
                            return response.text().then(text => {
                                console.error('Non-JSON response from check_appointments:', text);
                                throw new Error(`HTTP error! status: ${response.status}, response: ${text}`);
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Time slots data:', data);
                        if (data.error) {
                            throw new Error(data.error);
                        }
                        availableTimes.forEach(time => {
                            const timeSlot = document.createElement('div');
                            timeSlot.textContent = time;
                            timeSlot.classList.add('timeslot');

                            const now = new Date();
                            const isToday = date.toDateString() === now.toDateString();
                            const [hour, period] = time.split(' ');
                            let hourNum = parseInt(hour);
                            if (period === 'PM' && hourNum !== 12) hourNum += 12;
                            if (period === 'AM' && hourNum === 12) hourNum = 0;
                            const slotTime = new Date(date);
                            slotTime.setHours(hourNum, 0, 0, 0);

                            if (isToday && slotTime < now) {
                                timeSlot.classList.add('disabled');
                            } else if (data.includes(time)) {
                                timeSlot.classList.add('disabled');
                            } else {
                                timeSlot.addEventListener('click', function() {
                                    document.querySelectorAll('.timeslot').forEach(slot => slot.classList.remove('selected'));
                                    this.classList.add('selected');
                                    selectedTimeInput.value = time;
                                });
                            }

                            timeSlotsDiv.appendChild(timeSlot);
                        });

                        if (!timeSlotsDiv.querySelector('.timeslot:not(.disabled)')) {
                            timeSlotsDiv.innerHTML = '<p>No available time slots for this date.</p>';
                        }
                    })
                    .catch(error => {
                        console.error('Fetch error in generateTimeSlots:', error);
                        timeSlotsDiv.innerHTML = '<p>Error loading time slots. Please try again.</p>';
                        showErrorModal('Failed to load time slots: ' + error.message);
                    });
            } catch (error) {
                console.error('Error generating time slots:', error);
                showErrorModal('Failed to load time slots.');
            }
        }

        function confirmAppointment() {
            try {
                const reason = appointmentReasonSelect.value;
                const otherReason = otherReasonInput.value;
                const selectedDate = selectedDateInput.value;
                const selectedTime = selectedTimeInput.value;
                const childId = childSelect && childSelect.value && '<?php echo $isParent; ?>' === '1' ? childSelect.value : null;

                if ('<?php echo $isParent; ?>' === '1' && !childId) {
                    showErrorModal('Please select a child for the appointment.');
                    return;
                }

                if (!reason) {
                    showErrorModal('Please select a reason for your appointment.');
                    return;
                }

                if (reason === 'other' && !otherReason.trim()) {
                    showErrorModal('Please specify the reason for your appointment.');
                    return;
                }

                if (!selectedDate) {
                    showErrorModal('Please select a date for your appointment.');
                    return;
                }

                if (!selectedTime) {
                    showErrorModal('Please select a time slot for your appointment.');
                    return;
                }

                const finalReason = reason === 'other' ? otherReason : reason;
                const formData = new FormData();
                formData.append('user_id', <?php echo $userId; ?>);
                if (childId) {
                    formData.append('child_id', childId);
                }
                formData.append('reason', finalReason);
                formData.append('appointment_date', selectedDate);
                formData.append('appointment_time', selectedTime);
                formData.append('appointment_type', 'medical');

                // Log form data for debugging
                console.log('Sending form data:', Object.fromEntries(formData));

                fetch('?action=book_appointment', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            return response.text().then(text => {
                                throw new Error(`HTTP error! status: ${response.status}, response: ${text}`);
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Response data:', data);
                        if (data.success) {
                            const formattedDate = new Date(selectedDate).toLocaleDateString('en-US', {
                                weekday: 'long',
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric'
                            });
                            showSuccessModal(`Appointment request submitted successfully!\n\nReason: ${finalReason}\nDate: ${formattedDate}\nTime: ${selectedTime}`);
                            resetForm();
                            setTimeout(() => {
                                loadAppointments();
                                setActiveTab('upcoming');
                                document.querySelectorAll('.modal.show').forEach(modal => {
                                    bootstrap.Modal.getInstance(modal).hide();
                                });
                                appointmentsModal.show();
                            }, 1000);
                        } else {
                            showErrorModal(data.message || 'Failed to book appointment.');
                        }
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);
                        showErrorModal('An error occurred while booking the appointment: ' + error.message);
                    });
            } catch (error) {
                console.error('Error confirming appointment:', error);
                showErrorModal('Failed to book appointment.');
            }
        }

        function loadAppointments() {
            try {
                const url = `?action=get_appointments&user_id=<?php echo $userId; ?>&type=medical&t=${new Date().getTime()}`;
                console.log('Fetching appointments from:', url);
                fetch(url)
                    .then(response => {
                        if (!response.ok) {
                            return response.text().then(text => {
                                console.error('Non-JSON response from get_appointments:', text);
                                throw new Error(`HTTP error! status: ${response.status}, response: ${text}`);
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Appointments data:', data);
                        if (data.error) {
                            throw new Error(data.error);
                        }
                        const upcomingTab = document.getElementById('upcoming-tab');
                        const pastTab = document.getElementById('past-tab');
                        upcomingTab.innerHTML = '';
                        pastTab.innerHTML = '';

                        data.forEach(appt => {
                            const isPast = appt.status === 'Cancelled' || appt.status === 'Completed';
                            const targetTab = isPast ? pastTab : upcomingTab;

                            const childName = appt.child_name ? ` for ${appt.child_name}` : '';
                            const card = document.createElement('div');
                            card.classList.add('appointment-card');
                            if (isPast) card.classList.add('past');
                            card.dataset.id = appt.id;
                            card.innerHTML = `
                        <h3>${appt.reason}${childName}</h3>
                        <div class="appointment-details">
                            <div class="detail-item">Date: ${new Date(appt.appointment_date).toLocaleDateString('en-US')}</div>
                            <div class="detail-item">Time: ${appt.appointment_time}</div>
                            <div class="detail-item">Status: ${appt.status}</div>
                        </div>
                        <div class="appointment-actions">
                            ${appt.status === 'Pending' ? `<button class="action-btn cancel-btn" onclick="showCancelModal('${appt.id}')">Cancel Appointment</button>` : ''}
                            ${appt.status === 'Cancelled' ? `<button class="action-btn delete-btn" onclick="showDeleteModal('${appt.id}')">Delete Record</button>` : ''}
                        </div>
                    `;
                            targetTab.appendChild(card);
                        });

                        if (upcomingTab.children.length === 0) {
                            upcomingTab.innerHTML = '<p>No upcoming appointments found.</p>';
                        }
                        if (pastTab.children.length === 0) {
                            pastTab.innerHTML = '<p>No past or cancelled appointments found.</p>';
                        }
                    })
                    .catch(error => {
                        console.error('Error loading appointments:', error);
                        showErrorModal('Failed to load appointments: ' + error.message);
                    });
            } catch (error) {
                console.error('Error loading appointments:', error);
                showErrorModal('Failed to load appointments.');
            }
        }

        function setActiveTab(tabName) {
            try {
                document.querySelectorAll('.modal-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                document.querySelector(`.modal-tab[data-tab="${tabName}"]`).classList.add('active');
                document.getElementById(`${tabName}-tab`).classList.add('active');
            } catch (error) {
                console.error('Error setting active tab:', error);
            }
        }

        function showCancelModal(appointmentId) {
            selectedAppointmentId = appointmentId;
            confirmCancelModal.show();
        }

        function confirmCancel() {
            try {
                fetch(`?action=cancel_appointment`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            id: selectedAppointmentId
                        })
                    })
                    .then(response => {
                        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            showSuccessModal('Appointment cancelled successfully!');
                            loadAppointments();
                            confirmCancelModal.hide();
                        } else {
                            showErrorModal(data.message || 'Failed to cancel appointment.');
                        }
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);
                        showErrorModal('An error occurred while cancelling the appointment.');
                    });
            } catch (error) {
                console.error('Error cancelling appointment:', error);
                showErrorModal('Failed to cancel appointment.');
            }
        }

        function showDeleteModal(appointmentId) {
            selectedAppointmentId = appointmentId;
            confirmDeleteModal.show();
        }

        function confirmDelete() {
            try {
                fetch(`?action=delete_appointment`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            id: selectedAppointmentId
                        })
                    })
                    .then(response => {
                        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            showSuccessModal('Record deleted successfully!');
                            loadAppointments();
                            confirmDeleteModal.hide();
                        } else {
                            showErrorModal(data.message || 'Failed to delete record.');
                        }
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);
                        showErrorModal('An error occurred while deleting the record.');
                    });
            } catch (error) {
                console.error('Error deleting appointment:', error);
                showErrorModal('Failed to delete record.');
            }
        }

        function showSuccessModal(message) {
            document.querySelector('#successModal .modal-body').textContent = message;
            successModal.show();
        }

        function showErrorModal(message) {
            document.querySelector('#errorModal .modal-body').textContent = message;
            errorModal.show();
        }

        function switchTab() {
            try {
                document.querySelectorAll('.modal-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                document.getElementById(`${this.getAttribute('data-tab')}-tab`).classList.add('active');
            } catch (error) {
                console.error('Error switching tab:', error);
            }
        }

        function resetForm() {
            try {
                appointmentReasonSelect.value = '';
                otherReasonInput.value = '';
                otherReasonGroup.style.display = 'none';
                selectedDateInput.value = '';
                selectedTimeInput.value = '';
                if (childSelect) childSelect.value = '';
                document.querySelectorAll('.day').forEach(day => day.classList.remove('selected'));
                document.querySelectorAll('.timeslot').forEach(slot => slot.classList.remove('selected'));
                timeSlotsDiv.innerHTML = '';
            } catch (error) {
                console.error('Error resetting form:', error);
            }
        }

        document.getElementById('appointment-btn').classList.add('active');
    </script>
</body>

</html>
<?php $conn->close(); ?>