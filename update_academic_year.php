<?php
include 'config.php';

// Handle form submit (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_year = intval($_POST['academicYearStart']);
    $end_year = intval($_POST['academicYearEnd']);
    $grading_quarter = $_POST['grading_quarter']; // keep as string (Summer possible)
    $semester = $conn->real_escape_string($_POST['semester']);

    // Validate
    if ($start_year > 0 && $end_year > $start_year && !empty($grading_quarter) && !empty($semester)) {
        
        // 🔄 Delete all existing academic year records
        $delete = "DELETE FROM academic_years";
        $conn->query($delete);

        // Insert new academic year
        $sql = "INSERT INTO academic_years (start_year, end_year, grading_quarter, semester) 
                VALUES ('$start_year', '$end_year', '$grading_quarter', '$semester')";
        
        if ($conn->query($sql) === TRUE) {
            
            // ✅ Now notify all relevant users
            $allowed_types = "('Elementary','College','Highschool','Senior High School','Parent','Employee','Incoming Freshman')";
            $users_sql = "SELECT id FROM users WHERE user_type IN $allowed_types";
            $users_result = $conn->query($users_sql);

            if ($users_result && $users_result->num_rows > 0) {
                $title = "Academic Year Updated";
                $description = "The academic year has been updated to $start_year - $end_year ($semester, Quarter: $grading_quarter).";
                $link = "#"; // change to actual link in your system
                $type = "system"; // or "academic_year" depending on how you categorize
                $status = "unread";

                $now = date("Y-m-d H:i:s");

                while ($row = $users_result->fetch_assoc()) {
                    $user_id = $row['id'];

                    $notif_sql = "INSERT INTO user_notifications (user_id, type, title, description, link, status, created_at, updated_at) 
                                  VALUES ('$user_id', '$type', '$title', '$description', '$link', '$status', '$now', '$now')";
                    $conn->query($notif_sql);
                }
            }

            echo "success";
        } else {
            echo "Error: " . $conn->error;
        }

    } else {
        echo "Invalid input.";
    }
}

$conn->close();
?>
