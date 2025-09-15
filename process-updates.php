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
    header("Location: /index.php");
    exit();
}

// Fetch admin details
$user_id = $_SESSION['user_id'];
$query = "SELECT email, user_type FROM users WHERE id = ? AND user_type IN ('Super Admin')";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$admin = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$admin) {
    session_destroy();
    header("Location: /index.php");
    exit();
}


// Start the email sending process
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reminder'])) {
    // Get total number of patients

// Define the allowed user types
$allowed_types = [
    'Incoming Freshman',
    'Senior High School',
    'High School',
    'College',
    'Parent'
];

// Prepare the IN clause placeholders
$placeholders = implode(',', array_fill(0, count($allowed_types), '?'));

// 1. Fetch users with specific types
$users_query = "SELECT u.id, p.id as patient_id 
               FROM users u
               LEFT JOIN patients p ON u.id = p.user_id 
               WHERE u.user_type IN ($placeholders)";
$users_stmt = mysqli_prepare($conn, $users_query);

// Bind parameters dynamically
$types = str_repeat('s', count($allowed_types));
mysqli_stmt_bind_param($users_stmt, $types, ...$allowed_types);

mysqli_stmt_execute($users_stmt);
$users_result = mysqli_stmt_get_result($users_stmt);

// Prepare notification message
$message = "Please review and update your patient profile and account details to ensure they are accurate.";
$title = "Profile Update Reminder";
$type = "profile_reminder";

$notification_stmt = mysqli_prepare(
    $conn,
    "INSERT INTO user_notifications 
    (user_id, type, title, description, status, created_at, updated_at)
    VALUES (?, ?, ?, ?, 'unread', NOW(), NOW())"
);


// Prepare user update statement for profile_update_required flag
$user_types_for_update = ['Incoming Freshman', 'High School', 'Senior High School', 'College', 'Parent', 'Employee'];
$update_user_placeholders = implode(',', array_fill(0, count($user_types_for_update), '?'));
$update_user_stmt = mysqli_prepare(
    $conn,
    "UPDATE users SET profile_update_required = 1 WHERE user_type IN ($update_user_placeholders)"
);

// Bind parameters for user update
$update_types = str_repeat('s', count($user_types_for_update));
mysqli_stmt_bind_param($update_user_stmt, $update_types, ...$user_types_for_update);
mysqli_stmt_execute($update_user_stmt);

$success = true;
$notification_count = 0;
$update_count = 0;

while ($user = mysqli_fetch_assoc($users_result)) {
    // Send notification
    mysqli_stmt_bind_param($notification_stmt, 'isss', $user['id'], $type, $title, $message);
    if (!mysqli_stmt_execute($notification_stmt)) {
        error_log("Failed to send notification to user ID: " . $user['id']);
        $success = false;
    } else {
        $notification_count++;
    }

  
}

// Close statements
mysqli_stmt_close($notification_stmt);
mysqli_stmt_close($users_stmt);
mysqli_stmt_close($update_user_stmt);
mysqli_free_result($users_result);

// Set result message
if ($success) {
    $success_message = "Successfully sent $notification_count notifications and marked $update_count patient records for update.";
} else {
    $error_message = "Completed with some errors: Sent $notification_count notifications and marked $update_count patient records.";
}


    // Define the user types we want to process
    $user_types = ['Incoming Freshman', 'Highschool', 'Senior High School', 'College', 'Employee', 'Parent'];
    $placeholders = implode(',', array_fill(0, count($user_types), '?'));

    // Get total count of patients with specified user types
    $total_query = "SELECT COUNT(*) as total FROM users u 
                JOIN patients p ON u.id = p.user_id 
                WHERE u.user_type IN ($placeholders)";

    $total_stmt = mysqli_prepare($conn, $total_query);
    $types = str_repeat('s', count($user_types));
    mysqli_stmt_bind_param($total_stmt, $types, ...$user_types);

    mysqli_stmt_execute($total_stmt);
    $total_result = mysqli_stmt_get_result($total_stmt);
    $total_row = mysqli_fetch_assoc($total_result);
    $total_patients = $total_row['total'];
    mysqli_stmt_close($total_stmt);
    // Store in session for progress tracking
    $_SESSION['email_process'] = [
        'total' => 0,
        'processed' => 0,
        'success_count' => 0,
        'fail_count' => 0,
        'failed_emails' => [],
        'status' => 'initializing'
    ];

    // Redirect to progress page
    header('Location: email_progress.php');
    exit();
}
