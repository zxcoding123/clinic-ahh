<?php
session_start();
require_once 'config.php';

// Enable strict MySQLi error reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Check database connection
if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error);
    header("Location: /error.php");
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("No session user_id found");
    header("Location: /login.php");
    exit();
}

// Fetch user data
$userId = (int)$_SESSION['user_id'];
$query = "SELECT user_type FROM users WHERE id = ?";
try {
    $stmt = $conn->prepare($query);
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    $userType = $user['user_type'] ?? '';
    if (!$userType) {
        error_log("Error: User type not found for user_id $userId");
        header("Location: /login.php");
        exit();
    }
} catch (Exception $e) {
    error_log("Error fetching user: " . $e->getMessage());
    header("Location: /login.php");
    exit();
}

// Fetch children for Parent user
$children = [];
$childId = isset($_GET['child_id']) ? (int)$_GET['child_id'] : null;
if ($userType === 'Parent') {
    $query = "SELECT id, first_name, last_name
FROM children
WHERE parent_id = ?";
    try {
        $stmt = $conn->prepare($query);
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
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
    } catch (Exception $e) {
        error_log("Error fetching children: " . $e->getMessage());
    }
}

// Handle API requests
$action = $_GET['action'] ?? '';

if ($action === 'get_appointments') {
    header('Content-Type: application/json');
    $appointments = [];
    $query = "SELECT a.id, a.reason, a.appointment_date, a.appointment_time, a.status, 
                     c.first_name AS child_first_name, c.last_name AS child_last_name
              FROM appointments a
              LEFT JOIN children c ON a.child_id = c.id
              WHERE a.user_id = ? AND a.appointment_type = 'dental'";
    try {
        $stmt = $conn->prepare($query);
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $appointments[] = [
                'id' => $row['id'],
                'reason' => $row['reason'],
                'appointment_date' => $row['appointment_date'],
                'appointment_time' => date('h:i A', strtotime($row['appointment_time'])), // Convert to AM/PM
                'status' => $row['status'],
                'child_name' => $row['child_first_name'] ? $row['child_first_name'] . ' ' . $row['child_last_name'] : null
            ];
        }
        $stmt->close();
        echo json_encode($appointments);
    } catch (Exception $e) {
        error_log("Error fetching appointments: " . $e->getMessage());
        echo json_encode(['error' => 'Failed to fetch appointments']);
    }
    exit();
}

if ($action === 'check_appointments') {
    header('Content-Type: application/json');
    $date = $_GET['date'] ?? '';
    $childId = isset($_GET['child_id']) && $_GET['child_id'] !== '' ? (int)$_GET['child_id'] : null;
    $bookedTimes = [];
    $query = "SELECT appointment_time 
              FROM appointments 
              WHERE user_id = ? AND appointment_date = ? AND status != 'Cancelled'";
    $params = ["is", $userId, $date];
    if ($childId) {
        $query .= " AND child_id = ?";
        $params[0] .= "i";
        $params[] = $childId;
    }
    try {
        $stmt = $conn->prepare($query);
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
        $stmt->bind_param(...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $bookedTimes[] = date('h:i A', strtotime($row['appointment_time'])); // Convert to AM/PM
        }
        $stmt->close();
        echo json_encode($bookedTimes);
    } catch (Exception $e) {
        error_log("Error checking appointments: " . $e->getMessage());
        echo json_encode(['error' => 'Failed to check appointments']);
    }
    exit();
}

   $isStudent = in_array($userType, ['Incoming Freshman', 'Senior High School', 'College', 'Highschool']);

if ($action === 'book_appointment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $childId = isset($_POST['child_id']) && $_POST['child_id'] !== '' ? (int)$_POST['child_id'] : null;
    $reason = $_POST['reason'] ?? '';
    $appointmentDate = $_POST['appointment_date'] ?? '';
    $appointmentTime = $_POST['appointment_time'] ?? '';
    $appointmentType = $_POST['appointment_type'] ?? 'dental';

    // Log received data
    error_log("Booking attempt: user_id=$userId, child_id=" . ($childId ?? 'null') . ", reason=$reason, date=$appointmentDate, time=$appointmentTime, type=$appointmentType");

    // Validate inputs
    if ($userId <= 0) {
        error_log("Invalid user_id: $userId");
        echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
        exit();
    }
    if (!$reason) {
        error_log("Missing reason");
        echo json_encode(['success' => false, 'message' => 'Missing reason']);
        exit();
    }
    if (!$appointmentDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointmentDate)) {
        error_log("Invalid appointment_date: $appointmentDate");
        echo json_encode(['success' => false, 'message' => 'Invalid appointment date']);
        exit();
    }
    if (!$appointmentTime || !preg_match('/^\d{1,2}:\d{2} (AM|PM)$/', $appointmentTime)) {
        error_log("Invalid appointment_time: $appointmentTime");
        echo json_encode(['success' => false, 'message' => 'Invalid appointment time']);
        exit();
    }
    if ($userType === 'Parent' && !$childId) {
        error_log("Missing child_id for Parent user");
        echo json_encode(['success' => false, 'message' => 'Please select a child']);
        exit();
    }
    if (!in_array($appointmentType, ['dental', 'medical'])) {
        error_log("Invalid appointment_type: $appointmentType");
        echo json_encode(['success' => false, 'message' => 'Invalid appointment type']);
        exit();
    }

    // Validate user_id exists
    try {
        $query = "SELECT id FROM users WHERE id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            error_log("User not found: user_id=$userId");
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit();
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error validating user_id: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit();
    }

    // Validate child_id for Parents
    if ($childId) {
        try {

            $query = "SELECT id FROM children WHERE id = ? AND parent_id IN (SELECT user_id FROM patients WHERE user_id = ?)";
            $stmt = $conn->prepare($query);
            if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
            $stmt->bind_param("ii", $childId, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                error_log("Invalid child_id: $childId for user_id=$userId");
                echo json_encode(['success' => false, 'message' => 'Invalid child selected']);
                exit();
            }
            $stmt->close();
        } catch (Exception $e) {
            error_log("Error validating child_id: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit();
        }
    }

    // Convert appointment_time to HH:MM:SS
    try {
        $appointmentTime = date('H:i:s', strtotime($appointmentTime));
        if (!$appointmentTime) throw new Exception("Invalid time conversion: $appointmentTime");
    } catch (Exception $e) {
        error_log("Time conversion error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Invalid time format']);
        exit();
    }

    // Check for existing appointments
    $query = "SELECT id FROM appointments 
              WHERE user_id = ? AND appointment_date = ? AND status != 'Cancelled'";
    $params = ["is", $userId, $appointmentDate];
    if ($childId) {
        $query .= " AND child_id = ?";
        $params[0] .= "i";
        $params[] = $childId;
    }
    try {
        $stmt = $conn->prepare($query);
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
        $stmt->bind_param(...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $stmt->close();
            error_log("Existing appointment found for user_id=$userId, date=$appointmentDate");
            echo json_encode(['success' => false, 'message' => 'You already have an appointment on this date']);
            exit();
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error checking existing appointments: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to check existing appointments']);
        exit();
    }


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

    $notificationTitle = "Dental Appointment Request";
    $notificationDescription = "$full_name_for_notification has requested for a dental appointment!";
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

    // Book appointment
    $query = "INSERT INTO appointments (user_id, child_id, reason, appointment_date, appointment_time, appointment_type, status)
              VALUES (?, ?, ?, ?, ?, ?, 'Pending')";
    try {
        $stmt = $conn->prepare($query);
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
        $stmt->bind_param("iissss", $userId, $childId, $reason, $appointmentDate, $appointmentTime, $appointmentType);
        $success = $stmt->execute();
        if (!$success) throw new Exception("Execute failed: " . $stmt->error);
        $stmt->close();
        error_log("Appointment booked successfully: user_id=$userId, date=$appointmentDate, time=$appointmentTime");
        echo json_encode(['success' => true, 'message' => 'Appointment booked successfully']);
    } catch (Exception $e) {
        error_log("Error booking appointment: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to book appointment: ' . $e->getMessage()]);
        exit();
    }
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

    $notificationTitle = "Dental Appointment Cancellation";
    $notificationDescription = "$full_name_for_notification has cancelled for the dental appointment!";
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
    try {
        $stmt = $conn->prepare($query);
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
        $stmt->bind_param("ii", $appointmentId, $userId);
        $success = $stmt->execute();
        $stmt->close();
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Appointment cancelled successfully' : 'Failed to cancel appointment'
        ]);
    } catch (Exception $e) {
        error_log("Error cancelling appointment: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to cancel appointment']);
    }
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
                    WHERE user_type IN ('Super Admin',  'Dental Admin')
                ");
    $adminQuery->execute();
    $adminResult = $adminQuery->get_result();

    $notificationTitle = "Dental Appointment Deletion";
    $notificationDescription = "$full_name_for_notification has deleted the dental appointment!";
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
    try {
        $stmt = $conn->prepare($query);
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
        $stmt->bind_param("ii", $appointmentId, $userId);
        $success = $stmt->execute();
        $stmt->close();
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Record deleted successfully' : 'Failed to delete record'
        ]);
    } catch (Exception $e) {
        error_log("Error deleting appointment: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to delete record']);
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>University Health Services - Dental Appointment Request</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/DentalRequest.css?t=<?php echo time(); ?>">
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

        /* Calendar and Time Slot Styling */
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
            background-color: #e0e0e0;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .day.unavailable {
            background-color: #f8d7da;
            cursor: not-allowed;
        }

        .active {
            background-color: #4f1515 !important;
            color: white !important;
        }
    </style>
</head>

<body>
    <div id="app" class="d-flex">
        <!-- Sidebar Toggle Button -->
        <button id="sidebar-toggle" class="sidebar-toggle d-lg-none">☰</button>

        <!-- Sidebar Navigation -->
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

        <!-- Main Content Area -->
        <div class="main-content" id="mainContent">
            <!-- Page Header -->
            <div class="header">
                <h1>Dental Appointment Request</h1>
            </div>

            <!-- Appointment Form Section -->
            <section class="appointment-section">
                <button type="button" class="today-btn" id="view-appointments-btn">View My Appointments</button>

            </section>

            <!-- Calendar and Time Slots Section -->
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
                <br>

                <form class="appointment-form">
                    <?php if ($userType === 'Parent'): ?>
                        <div class="form-group mb-3">
                            <br>
                            <label><b>Select a child below: </b></label>
                            <select class="form-control" id="childSelect" required>
                                <option value="" disabled <?php echo !$childId ? 'selected' : ''; ?>>Select a Child</option>
                                <?php foreach ($children as $child): ?>
                                    <option value="<?php echo $child['id']; ?>" <?php echo $childId === $child['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($child['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="form-group">

                        <label><b>Select reason for appointment below: </b></label>
                        <select class="form-control" id="appointment-reason" required>
                            <option value="" disabled selected>Reason for appointment</option>
                            <option value="check-up">Dental Check-up</option>
                            <option value="cleaning">Teeth Cleaning</option>
                            <option value="filling">Cavity Filling</option>
                            <option value="extraction">Tooth Extraction</option>
                            <option value="braces">Braces Consultation</option>
                            <option value="whitening">Teeth Whitening</option>
                            <option value="pain">Tooth Pain</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group" id="other-reason-group" style="display: none;">
                        <input type="text" class="form-control" id="other-reason" placeholder="Please specify the reason">
                    </div>
                </form>
                <div class="timeslots-section">
                    <h3 class="timeslots-title">Available Time Slots</h3>
                    <div class="timeslots-container" id="timeSlots"></div>
                </div>
                <button type="button" class="confirm-button" id="confirm-btn">Confirm Appointment</button>
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

            <!-- Cancel Confirmation Modal -->
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
                <div class="modal-dialog modal-lg">
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

        // Initialize application
        function init() {
            console.log('Initializing application...');
            if (!calendarDiv) {
                console.error('Calendar div not found');
                showErrorModal('Calendar container not found.');
                return;
            }
            updateMonthDisplay();
            createCalendar();
            loadAppointments();
            console.log('Initialization complete');
        }

        // Run init on DOM load or immediately if loaded
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }

        // Event Listeners
        sidebarToggle.addEventListener('click', toggleSidebar);
        appointmentReasonSelect.addEventListener('change', handleReasonChange);
        confirmBtn.addEventListener('click', confirmAppointment);
        viewAppointmentsBtn.addEventListener('click', () => {
            console.log('Opening appointments modal');
            document.querySelectorAll('.modal.show').forEach(modal => {
                bootstrap.Modal.getInstance(modal).hide();
            });
            loadAppointments();
            setActiveTab('upcoming');
            appointmentsModal.show();
        });
        prevMonthBtn.addEventListener('click', () => {
            console.log('Navigating to previous month');
            changeMonth(-1);
        });
        nextMonthBtn.addEventListener('click', () => {
            console.log('Navigating to next month');
            changeMonth(1);
        });
        document.querySelectorAll('.modal-tab').forEach(tab => {
            tab.addEventListener('click', switchTab);
        });
        document.getElementById('confirmCancelBtn').addEventListener('click', confirmCancel);
        document.getElementById('confirmDeleteBtn').addEventListener('click', confirmDelete);

        // Toggle sidebar
        function toggleSidebar() {
            console.log('Toggling sidebar');
            sidebar.classList.toggle('open');
            mainContent.classList.toggle('sidebar-open');
        }

        // Handle reason change
        function handleReasonChange() {
            console.log('Reason changed:', this.value);
            otherReasonGroup.style.display = this.value === 'other' ? 'block' : 'none';
            otherReasonInput.value = '';
        }

        // Update month display
        function updateMonthDisplay() {
            try {
                console.log('Updating month display:', {
                    currentMonth,
                    currentYear
                });
                currentMonthDisplay.textContent =
                    new Date(currentYear, currentMonth).toLocaleString('default', {
                        month: 'long',
                        year: 'numeric'
                    });
                createCalendar();
            } catch (error) {
                console.error('Error updating month:', error);
                showErrorModal('Failed to update calendar.');
            }
        }

        // Change month
        function changeMonth(change) {
            console.log('Changing month by:', change);
            currentMonth += change;
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            } else if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
            updateMonthDisplay();
        }

        // Create calendar
        function createCalendar() {
            try {
                console.log('Creating calendar for:', {
                    currentMonth,
                    currentYear
                });
                if (!calendarDiv) {
                    console.error('Calendar div is null');
                    showErrorModal('Calendar container not found.');
                    return;
                }

                calendarDiv.innerHTML = '';
                const firstDayOfMonth = new Date(currentYear, currentMonth, 1).getDay();
                const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
                console.log('Calendar params:', {
                    firstDayOfMonth,
                    daysInMonth
                });

                // Validate date
                if (isNaN(firstDayOfMonth) || isNaN(daysInMonth)) {
                    console.error('Invalid calendar parameters:', {
                        firstDayOfMonth,
                        daysInMonth
                    });
                    showErrorModal('Invalid calendar configuration.');
                    return;
                }

                // Add empty slots
                for (let i = 0; i < firstDayOfMonth; i++) {
                    const emptyDay = document.createElement('div');
                    emptyDay.classList.add('day', 'unavailable');
                    calendarDiv.appendChild(emptyDay);
                    console.log('Added empty day:', i);
                }

                // Add days
                const todayDate = new Date();
                todayDate.setHours(0, 0, 0, 0);
                for (let i = 1; i <= daysInMonth; i++) {
                    const dayDiv = document.createElement('div');
                    dayDiv.textContent = i;
                    dayDiv.classList.add('day');

                    const tempDate = new Date(currentYear, currentMonth, i);
                    const isSunday = tempDate.getDay() === 0;
                    const isPast = tempDate < todayDate;
                    console.log('Day:', i, {
                        isSunday,
                        isPast,
                        classes: dayDiv.className
                    });

                    if (isSunday || isPast) {
                        dayDiv.classList.add('unavailable');
                    } else {
                        dayDiv.addEventListener('click', function() {
                            console.log('Selected date:', tempDate);
                            document.querySelectorAll('.day').forEach(day => day.classList.remove('selected'));
                            this.classList.add('selected');
                            selectedDateInput.value = `${tempDate.getFullYear()}-${String(tempDate.getMonth() + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
                            generateTimeSlots(tempDate);
                        });
                    }

                    calendarDiv.appendChild(dayDiv);
                }

                console.log('Calendar created successfully');
            } catch (error) {
                console.error('Error creating calendar:', error);
                showErrorModal('Failed to render calendar.');
            }
        }

        // Generate time slots
        function generateTimeSlots(date) {
            try {
                console.log('Generating time slots for:', date);
                timeSlotsDiv.innerHTML = '';
                selectedTimeInput.value = '';

                const isSaturday = date.getDay() === 6;
                const availableTimes = isSaturday ? ["8:00 AM", "9:00 AM", "10:00 AM", "11:00 AM"] : ["8:00 AM", "9:00 AM", "10:00 AM", "11:00 AM", "12:00 PM", "1:00 PM", "2:00 PM", "3:00 PM", "4:00 PM"];

                const childId = childSelect ? childSelect.value : '';
                fetch(`?action=check_appointments&date=${selectedDateInput.value}&user_id=<?php echo $userId; ?>&child_id=${childId}`)
                    .then(response => response.json())
                    .then(data => {
                        console.log('Booked time slots:', data);
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
                                    console.log('Selected time:', time);
                                    document.querySelectorAll('.timeslot').forEach(slot => slot.classList.remove('selected'));
                                    this.classList.add('selected');
                                    selectedTimeInput.value = time;
                                });
                            }

                            console.log('Time slot:', time, {
                                classes: timeSlot.className
                            });
                            timeSlotsDiv.appendChild(timeSlot);
                        });

                        if (!timeSlotsDiv.querySelector('.timeslot:not(.disabled)')) {
                            timeSlotsDiv.innerHTML = '<p>No available time slots for this date.</p>';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching time slots:', error);
                        timeSlotsDiv.innerHTML = '<p>Error loading time slots. Please try again.</p>';
                        showErrorModal('Failed to load time slots.');
                    });
            } catch (error) {
                console.error('Error generating time slots:', error);
                showErrorModal('Failed to generate time slots.');
            }
        }

        // Confirm appointment
        function confirmAppointment() {
            try {
                console.log('Confirming appointment');
                const reason = appointmentReasonSelect.value;
                const otherReason = otherReasonInput.value;
                const selectedDate = selectedDateInput.value;
                const selectedTime = selectedTimeInput.value;
                const childId = childSelect && childSelect.value ? childSelect.value : null;

                console.log('Form data:', {
                    reason,
                    otherReason,
                    selectedDate,
                    selectedTime,
                    childId
                });

                if (childSelect && !childId) {
                    console.log('Validation failed: Child not selected');
                    showErrorModal('Please select a child for the appointment.');
                    return;
                }
                if (!reason) {
                    console.log('Validation failed: Reason not selected');
                    showErrorModal('Please select a reason for your appointment.');
                    return;
                }
                if (reason === 'other' && !otherReason.trim()) {
                    console.log('Validation failed: Other reason not specified');
                    showErrorModal('Please specify the reason for your appointment.');
                    return;
                }
                if (!selectedDate || !selectedDate.match(/^\d{4}-\d{2}-\d{2}$/)) {
                    console.log('Validation failed: Invalid date');
                    showErrorModal('Please select a valid date for your appointment.');
                    return;
                }
                if (!selectedTime || !selectedTime.match(/^\d{1,2}:\d{2} (AM|PM)$/)) {
                    console.log('Validation failed: Invalid time');
                    showErrorModal('Please select a valid time slot for your appointment.');
                    return;
                }

                const finalReason = reason === 'other' ? otherReason : reason;
                const formData = new FormData();
                formData.append('user_id', <?php echo $userId; ?>);
                if (childId) formData.append('child_id', childId);
                formData.append('reason', finalReason);
                formData.append('appointment_date', selectedDate);
                formData.append('appointment_time', selectedTime);
                formData.append('appointment_type', 'dental');

                const formDataEntries = {};
                for (let [key, value] of formData.entries()) {
                    formDataEntries[key] = value;
                }
                console.log('Sending FormData:', formDataEntries);

                fetch('?action=book_appointment', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        console.log('Fetch response status:', response.status);
                        if (!response.ok) {
                            return response.text().then(text => {
                                throw new Error(`HTTP error ${response.status}: ${text}`);
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Booking response:', data);
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
                                console.log('Showing appointments after 1000ms');
                                loadAppointments();
                                setActiveTab('upcoming');
                                document.querySelectorAll('.modal.show').forEach(modal => {
                                    bootstrap.Modal.getInstance(modal).hide();
                                });
                                appointmentsModal.show();
                            }, 1000);
                        } else {
                            console.log('Booking failed:', data.message);
                            showErrorModal(data.message || 'Failed to book appointment.');
                        }
                    })
                    .catch(error => {
                        console.error('Booking error:', error);
                        showErrorModal(`An error occurred while booking the appointment: ${error.message}`);
                    });
            } catch (error) {
                console.error('Error in confirmAppointment:', error);
                showErrorModal('Failed to process appointment request.');
            }
        }

        // Load appointments
        function loadAppointments() {
            try {
                console.log('Loading appointments');
                fetch(`?action=get_appointments&user_id=<?php echo $userId; ?>&type=dental&t=${new Date().getTime()}`)
                    .then(response => response.json())
                    .then(data => {
                        console.log('Fetched appointments:', data);
                        const upcomingTab = document.getElementById('upcoming-tab');
                        const pastTab = document.getElementById('past-tab');
                        upcomingTab.innerHTML = '';
                        pastTab.innerHTML = '';

                        data.forEach(appt => {
                            const isCancelled = appt.status === 'Cancelled';
                            const targetTab = isCancelled ? pastTab : upcomingTab;

                            const childName = appt.child_name ? ` for ${appt.child_name}` : '';
                            const card = document.createElement('div');
                            card.classList.add('appointment-card');
                            if (isCancelled) card.classList.add('past');
                            card.dataset.id = appt.id;
                            card.innerHTML = `
                                <h3>${appt.reason}${childName}</h3>
                                <div class="appointment-details">
                                    <div class="detail-item">Date: ${new Date(appt.appointment_date).toLocaleDateString('en-US')}</div>
                                    <div class="detail-item">Time: ${appt.appointment_time}</div>
                                    <div class="detail-item">Status: ${appt.status}</div>
                                </div>
                                <div class="appointment-actions">
                                    ${!isCancelled ? `<button class="action-btn cancel-btn" onclick="showCancelModal('${appt.id}')">Cancel Appointment</button>` : ''}
                                    ${isCancelled ? `<button class="action-btn delete-btn" onclick="showDeleteModal('${appt.id}')">Delete Record</button>` : ''}
                                </div>
                            `;
                            targetTab.appendChild(card);
                        });

                        if (upcomingTab.children.length === 0) {
                            upcomingTab.innerHTML = '<p>No upcoming appointments found.</p>';
                        }
                        if (pastTab.children.length === 0) {
                            pastTab.innerHTML = '<p>No cancelled appointments found.</p>';
                        }
                    })
                    .catch(error => {
                        console.error('Error loading appointments:', error);
                        showErrorModal('Failed to load appointments.');
                    });
            } catch (error) {
                console.error('Error in loadAppointments:', error);
                showErrorModal('Failed to load appointments.');
            }
        }

        // Set active tab
        function setActiveTab(tabName) {
            console.log('Setting active tab:', tabName);
            document.querySelectorAll('.modal-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.querySelector(`.modal-tab[data-tab="${tabName}"]`).classList.add('active');
            document.getElementById(`${tabName}-tab`).classList.add('active');
        }

        // Show cancel modal
        function showCancelModal(appointmentId) {
            console.log('Showing cancel modal for:', appointmentId);
            selectedAppointmentId = appointmentId;
            confirmCancelModal.show();
        }

        // Confirm cancellation
        function confirmCancel() {
            try {
                console.log('Confirming cancellation:', selectedAppointmentId);
                fetch(`?action=cancel_appointment`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            id: selectedAppointmentId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Cancellation response:', data);
                        if (data.success) {
                            showSuccessModal('Appointment cancelled successfully!');
                            loadAppointments();
                            confirmCancelModal.hide();
                        } else {
                            showErrorModal(data.message || 'Failed to cancel appointment.');
                        }
                    })
                    .catch(error => {
                        console.error('Cancellation error:', error);
                        showErrorModal('An error occurred while cancelling the appointment.');
                    });
            } catch (error) {
                console.error('Error in confirmCancel:', error);
                showErrorModal('Failed to cancel appointment.');
            }
        }

        // Show delete modal
        function showDeleteModal(appointmentId) {
            console.log('Showing delete modal for:', appointmentId);
            selectedAppointmentId = appointmentId;
            confirmDeleteModal.show();
        }

        // Confirm deletion
        function confirmDelete() {
            try {
                console.log('Confirming deletion:', selectedAppointmentId);
                fetch(`?action=delete_appointment`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            id: selectedAppointmentId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Deletion response:', data);
                        if (data.success) {
                            showSuccessModal('Record deleted successfully!');
                            loadAppointments();
                            confirmDeleteModal.hide();
                        } else {
                            showErrorModal(data.message || 'Failed to delete record.');
                        }
                    })
                    .catch(error => {
                        console.error('Deletion error:', error);
                        showErrorModal('An error occurred while deleting the record.');
                    });
            } catch (error) {
                console.error('Error in confirmDelete:', error);
                showErrorModal('Failed to delete record.');
            }
        }

        // Show success modal
        function showSuccessModal(message) {
            console.log('Showing success modal:', message);
            document.querySelector('#successModal .modal-body').textContent = message;
            successModal.show();
        }

        // Show error modal
        function showErrorModal(message) {
            console.log('Showing error modal:', message);
            document.querySelector('#errorModal .modal-body').textContent = message;
            errorModal.show();
        }

        // Switch tabs
        function switchTab() {
            console.log('Switching tab to:', this.getAttribute('data-tab'));
            document.querySelectorAll('.modal-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            document.getElementById(`${this.getAttribute('data-tab')}-tab`).classList.add('active');
        }

        // Reset form
        function resetForm() {
            console.log('Resetting form');
            appointmentReasonSelect.value = '';
            otherReasonInput.value = '';
            otherReasonGroup.style.display = 'none';
            selectedDateInput.value = '';
            selectedTimeInput.value = '';
            if (childSelect) childSelect.value = '';
            document.querySelectorAll('.day').forEach(day => day.classList.remove('selected'));
            document.querySelectorAll('.timeslot').forEach(slot => slot.classList.remove('selected'));
            timeSlotsDiv.innerHTML = '';
        }

        // Highlight current page
        document.getElementById('appointment-btn').classList.add('active');
    </script>
</body>

</html>
<?php $conn->close(); ?>