<?php
session_start();
require_once 'config.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

// Get the student ID from the URL parameter
$student_id = isset($_GET['id']) ? $_GET['id'] : '';

if (empty($student_id)) {
    die("Error: No student ID provided.");
}

// Extract the patient ID from the student ID (format: S-000069)
$patient_id = str_replace('S-', '', $student_id);

// Fetch student data
$sql = "
    SELECT 
        p.user_id,
        p.student_id,
        p.surname,
        p.firstname,
        p.middlename,
        p.suffix,
        p.email,
        p.age,
        p.sex,
        p.contact_number,
        p.city_address,
        p.course,
        p.department,
        p.year_level,
        u.user_type,
        cs.cor_path,
        cs.school_id_path,
        inc.id_path
    FROM patients p
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN college_students cs ON u.user_type = 'College' AND cs.user_id = u.id
    LEFT JOIN incoming_freshmen inc ON u.user_type = 'Incoming Freshman' AND inc.user_id = u.id
    WHERE p.user_id = ?
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $patient_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$student = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$student) {
    die("Error: Student not found.");
}

// Determine which ID path to use based on user type
$id_path = '';
if ($student['user_type'] === 'College') {
    $id_path = $student['cor_path'] ?: $student['school_id_path'];
} elseif ($student['user_type'] === 'Incoming Freshman') {
    $id_path = $student['id_path'];
}

$full_name = trim("{$student['firstname']} {$student['middlename']} {$student['surname']} {$student['suffix']}");
$full_name = $full_name !== '' ? $full_name : '-';

$email = trim($student['email']);
$email = $email !== '' ? $email : '-';

$course_department_year = trim($student['course'] . " " . $student['department'] . " - " . $student['year_level']);
$course_department_year = $course_department_year !== '' ? $course_department_year : '-';

$year_level = trim($student['year_level']);
$year_level = $year_level !== '' ? $year_level : '-';

$student_id = trim($student['student_id']);
$student_id = $student_id !== '' ? $student_id : '-';

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student ID Card - WMSU Health Services</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #222;
        }

        .id-card-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .id-card {
            width: 370px;
            height: 540px;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            border: 3px solid #a6192e;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .id-header {
            background: #a6192e;
            color: #fff;
            padding: 18px 16px 10px 16px;
            text-align: left;
            font-family: 'Cinzel', serif;
        }

        .id-header .wmsu-logo {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 8px;
        }

        .id-header .wmsu-title {
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }

        .id-header .wmsu-sub {
            font-size: 0.95rem;
            font-weight: 500;
            letter-spacing: 0.2px;
        }

        .id-body {
            flex: 1;
            display: flex;
            flex-direction: row;
            padding: 18px 16px 0 16px;
        }

        .id-photo {
            width: 110px;
            height: 140px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid #a6192e;
            background: #eee;
            margin-right: 18px;
        }

        .id-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
        }

        .id-info .student-name {
            font-family: 'Cinzel', serif;
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 2px;
            color: #222;
        }

        .id-info .student-course {
            font-size: 1.05rem;
            font-weight: 700;
            color: #a6192e;
            margin-bottom: 2px;
            letter-spacing: 1px;
        }

        .id-info .student-id {
            font-size: 0.95rem;
            color: #333;
            margin-bottom: 2px;
        }

        .id-info .student-year {
            font-size: 0.95rem;
            color: #333;
            margin-bottom: 2px;
        }

        .id-info .student-email {
            font-size: 0.8rem;
            color: #555;
            margin-bottom: 2px;
        }

        .id-info .student-address {
            font-size: 0.9rem;
            color: #555;
            margin-bottom: 2px;
        }

        .id-footer {
            padding: 10px 16px 16px 16px;
            font-size: 0.95rem;
            color: #222;
            font-family: 'Poppins', sans-serif;
        }

        .id-footer .sig-label {
            font-size: 0.85rem;
            color: #888;
            margin-bottom: 2px;
        }

        .id-footer .sig {
            font-family: 'Cinzel', serif;
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 0;
        }

        .id-footer .sig-title {
            font-size: 0.85rem;
            color: #a6192e;
            font-weight: 600;
        }

        .id-footer .id-year {
            font-size: 0.9rem;
            color: #a6192e;
            font-weight: 600;
            margin-top: 8px;
        }

        @media (max-width: 500px) {
            .id-card {
                width: 98vw;
                height: auto;
            }
        }
    </style>
</head>

<body>
    <div class="id-card-container">
        <div class="id-card">
            <div class="id-header">
                <img src="images/Western_Mindanao_State_University copy.png" alt="WMSU Logo" class="wmsu-logo">
                <div class="wmsu-title">REPUBLIC OF THE PHILIPPINES</div>
                <div class="wmsu-title">WESTERN MINDANAO STATE UNIVERSITY</div>
                <div class="wmsu-sub">ZAMBOANGA CITY</div>
            </div>
            <div class="id-body">
                <img src="<?php echo $id_path && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $id_path) ? '/' . htmlspecialchars($id_path) : 'images/default-profile.png'; ?>" alt="Student Photo" class="img-fluid id-photo">
                <div class="id-info">
                    <div class="student-course"><?php echo $course_department_year?></div>
                    <div class="student-name"><?php echo htmlspecialchars($full_name); ?></div>
                    <div class="student-id"><?php echo $student_id ?></div>
                    <div class="student-year">Year Level: <?php echo $year_level?></div>
                    <div class="student-email"><?php echo htmlspecialchars(string: $email); ?></div>
                </div>
            </div>
            <div class="id-footer">
                <div class="sig-label">Director, Student Affairs</div>
                <div class="sig">Prof. MAHMOR N. EDDING</div>
                <div class="sig-title">Signature</div>
                <!-- <div class="id-year">2021-00123</div> -->
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>

</html>