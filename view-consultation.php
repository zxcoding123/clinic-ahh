<?php
session_start();
require_once 'config.php';

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['Super Admin', 'Medical Admin'])) {
    header("Location: /login.php");
    exit();
}

// Check if consultation_id is provided
if (!isset($_GET['consultation_id'])) {
    header("Location: medical-appointments.php");
    exit();
}

$consultation_id = (int)$_GET['consultation_id'];

// Fetch consultation details
$sql = "
    SELECT 
        c.id, c.patient_id, c.child_id, c.staff_id, c.name, c.consultation_date, c.consultation_time,
        c.grade_course_section, c.age, c.sex, c.weight, c.birthday, c.blood_pressure, c.temperature,
        c.heart_rate, c.oxygen_saturation, c.complaints, c.diagnosis, c.treatment, c.staff_signature,
        c.consultation_type, u.first_name AS staff_first_name, u.last_name AS staff_last_name
    FROM consultations c
    LEFT JOIN users u ON c.staff_id = u.id
    WHERE c.id = ?";
$stmt = mysqli_stmt_init($conn);
if (mysqli_stmt_prepare($stmt, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $consultation_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $consultation = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

if (!$consultation) {
    header("Location: medical-appointments.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Consultation - WMSU Health Services</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
        }
        .main-content {
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
        }
        .consultation-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .section-header {
            font-size: 1.2em;
            font-weight: bold;
            margin: 20px 0 10px;
            border-bottom: 2px solid #dc3545;
            padding-bottom: 5px;
        }
        .field-label {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .field-value {
            margin-bottom: 15px;
        }
        .print-only {
            display: none;
        }
        @media print {
            .print-only {
                display: block;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="consultation-container">
            <h2 class="text-center mb-4">Consultation Details</h2>
            <div class="section-header print-only">Patient Information</div>
            <div class="row mb-2">
                <div class="col-md-6">
                    <div class="field-label">Name:</div>
                    <div class="field-value"><?php echo htmlspecialchars($consultation['name']); ?></div>
                </div>
                <div class="col-md-3">
                    <div class="field-label">Date:</div>
                    <div class="field-value"><?php echo htmlspecialchars($consultation['consultation_date']); ?></div>
                </div>
                <div class="col-md-3">
                    <div class="field-label">Time:</div>
                    <div class="field-value"><?php echo htmlspecialchars($consultation['consultation_time']); ?></div>
                </div>
            </div>
            <div class="row mb-2">
                <div class="col-md-6">
                    <div class="field-label">Grade/Course/Year & Section:</div>
                    <div class="field-value"><?php echo htmlspecialchars($consultation['grade_course_section'] ?: 'N/A'); ?></div>
                </div>
                <div class="col-md-3">
                    <div class="field-label">Age:</div>
                    <div class="field-value"><?php echo htmlspecialchars($consultation['age']); ?></div>
                </div>
                <div class="col-md-3">
                    <div class="field-label">Sex:</div>
                    <div class="field-value"><?php echo htmlspecialchars(ucfirst($consultation['sex'])); ?></div>
                </div>
            </div>
            <div class="section-header print-only">Vital Signs</div>
            <div class="row mb-2">
                <div class="col-md-3">
                    <div class="field-label">Weight:</div>
                    <div class="field-value"><?php echo htmlspecialchars($consultation['weight']); ?></div>
                </div>
                <div class="col-md-3">
                    <div class="field-label">Birthday:</div>
                    <div class="field-value"><?php echo htmlspecialchars($consultation['birthday']); ?></div>
                </div>
                <div class="col-md-3">
                    <div class="field-label">Blood Pressure:</div>
                    <div class="field-value"><?php echo htmlspecialchars($consultation['blood_pressure']); ?></div>
                </div>
                <div class="col-md-3">
                    <div class="field-label">Temperature:</div>
                    <div class="field-value"><?php echo htmlspecialchars($consultation['temperature']); ?></div>
                </div>
            </div>
            <div class="row mb-2">
                <div class="col-md-3">
                    <div class="field-label">Heart Rate:</div>
                    <div class="field-value"><?php echo htmlspecialchars($consultation['heart_rate']); ?></div>
                </div>
                <div class="col-md-3">
                    <div class="field-label">Oxygen Saturation:</div>
                    <div class="field-value"><?php echo htmlspecialchars($consultation['oxygen_saturation']); ?></div>
                </div>
            </div>
            <div class="section-header print-only">Consultation Details</div>
            <div class="mb-2">
                <div class="field-label">Complaints:</div>
                <div class="field-value"><?php echo nl2br(htmlspecialchars($consultation['complaints'])); ?></div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="field-label">Diagnosis:</div>
                    <div class="field-value"><?php echo nl2br(htmlspecialchars($consultation['diagnosis'])); ?></div>
                </div>
                <div class="col-md-6">
                    <div class="field-label">Treatment:</div>
                    <div class="field-value"><?php echo nl2br(htmlspecialchars($consultation['treatment'])); ?></div>
                </div>
            </div>
            <div class="mb-2">
                <div class="field-label">Staff Name:</div>
                <div class="field-value"><?php echo htmlspecialchars($consultation['staff_first_name'] . ' ' . $consultation['staff_last_name']); ?></div>
            </div>
            <div class="mb-2">
                <div class="field-label">Staff Signature:</div>
                <img src="<?php echo htmlspecialchars($consultation['staff_signature']); ?>" alt="Signature" style="max-width: 200px; height: auto;">
            </div>
            <div class="d-flex justify-content-end mt-4 no-print">
                <button type="button" class="btn btn-secondary me-2" onclick="window.location.href='medical-appointments.php'">Back</button>
                <button type="button" class="btn btn-primary" onclick="window.print()">Print</button>
            </div>
        </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>