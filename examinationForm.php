<?php
session_start();
require_once 'config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/phpmailer/phpmailer/src/Exception.php';
require 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
require 'vendor/phpmailer/phpmailer/src/SMTP.php';
require 'vendor/autoload.php';


$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();



function generateConsultationPDF($appointment, $consultationData)
{
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('WMSU Health Services');
    $pdf->SetAuthor('WMSU Health Services');
    $pdf->SetTitle('Consultation Form and Prescription Form');
    $pdf->SetSubject('Dental Consultation');

    // Add a page
    $pdf->AddPage();

    // Set font
    $pdf->SetFont('helvetica', '', 12);
    // Left logo
    $pdf->Image('images/wmsu_logo.png', 10, 10, 20, 20, 'PNG');

    // Right logo
    $pdf->Image('images/clinic.png', 180, 10, 20, 20, 'PNG');

    // Set font for title
    $pdf->SetFont('helvetica', '', 10);

    // Move cursor down so text doesn't overlap logos
    $pdf->SetY(15);

    // Center text
    $pdf->Cell(0, 5, 'WESTERN MINDANAO STATE UNIVERSITY', 0, 1, 'C');
    $pdf->Cell(0, 5, 'Zamboanga City', 0, 1, 'C');
    $pdf->Cell(0, 5, 'UNIVERSITY HEALTH SERVICES CENTER', 0, 1, 'C');

    // Add small space under header before body content
    $pdf->Ln(20);
    // Title
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'CONSULTATION FORM AND PRESCRIPTION FORM', 0, 1, 'C');
    $pdf->Ln(5);

    // Patient Information Section
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'PATIENT INFORMATION', 0, 1);
    $pdf->SetFont('helvetica', '', 12);

    $pdf->Cell(60, 10, 'Name:', 0, 0);
    $pdf->Cell(0, 10, $appointment['first_name'] . ' ' . $appointment['last_name'], 0, 1);

    $pdf->Cell(60, 10, 'Date:', 0, 0);
    $pdf->Cell(0, 10, date('F j, Y'), 0, 1);

    $pdf->Cell(60, 10, 'Time:', 0, 0);
    $pdf->Cell(0, 10, date('g:i A'), 0, 1);

    $pdf->Cell(60, 10, 'Grade/Course/Year & Section:', 0, 0);
    $pdf->Cell(0, 10, $appointment['grade_course_section'], 0, 1);

    $pdf->Cell(60, 10, 'Age:', 0, 0);
    $pdf->Cell(0, 10, $consultationData['age'], 0, 1);

    $pdf->Cell(60, 10, 'Sex:', 0, 0);
    $pdf->Cell(0, 10, ucfirst($appointment['sex']), 0, 1);
    $pdf->Ln(10);

    // Vital Signs Section
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'VITAL SIGNS', 0, 1);
    $pdf->SetFont('helvetica', '', 12);

    $pdf->Cell(60, 10, 'Weight (Wt):', 0, 0);
    $pdf->Cell(0, 10, $consultationData['weight'], 0, 1);

    $pdf->Cell(60, 10, 'Birthday:', 0, 0);
    $pdf->Cell(0, 10, date('F j, Y', strtotime($appointment['birthday'])), 0, 1);

    $pdf->Cell(60, 10, 'B/P:', 0, 0);
    $pdf->Cell(0, 10, $consultationData['blood_pressure'], 0, 1);

    $pdf->Cell(60, 10, 'Temperature (TEMP):', 0, 0);
    $pdf->Cell(0, 10, $consultationData['temperature'], 0, 1);

    $pdf->Cell(60, 10, 'Heart Rate (HR):', 0, 0);
    $pdf->Cell(0, 10, $consultationData['heart_rate'], 0, 1);

    $pdf->Cell(60, 10, 'O2 SAT:', 0, 0);
    $pdf->Cell(0, 10, $consultationData['oxygen_saturation'], 0, 1);
    $pdf->Ln(10);

    // Consultation Details Section
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'CONSULTATION DETAILS', 0, 1);
    $pdf->SetFont('helvetica', '', 12);

    $pdf->Cell(0, 10, 'Complaints:', 0, 1);
    $pdf->MultiCell(0, 10, $consultationData['complaints'], 0, 'L');
    $pdf->Ln(5);

    $pdf->Cell(0, 10, 'Diagnosis:', 0, 1);
    $pdf->MultiCell(0, 10, $consultationData['diagnosis'], 0, 'L');
    $pdf->Ln(5);

    $pdf->Cell(0, 10, 'Treatment:', 0, 1);
    $pdf->MultiCell(0, 10, $consultationData['treatment'], 0, 'L');
    $pdf->Ln(15);

    // Add small space under header before body content
    $pdf->Ln(20);
    // Title
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'DENTAL CONSULTATION FORM', 0, 1, 'C');
    $pdf->Ln(5);

    // [Rest of your existing consultation form content...]

    // Signature Section - Modified to include both signatures
    $pdf->Ln(15);
    // Set smaller font first
    $pdf->SetFont('helvetica', '', 10);

    // Calculate positions
    $col1_width = 70;  // First column width
    $col2_width = 80;  // Second column width
    $spacing = 10;     // Space between columns

    // First row - Titles
    $pdf->Cell($col1_width, 5, 'Attending Staff:', 0, 0, 'L');
    $pdf->Cell($col2_width, 5, 'Prescribing Physician:', 0, 1, 'L');

    // Second row - Signature lines
    $pdf->Cell($col1_width, 2, '', 'T');  // Staff line
    $pdf->Cell($spacing, 2, '');          // Space
    $pdf->Cell($col2_width, 2, '', 'T');  // Physician line
    $pdf->Ln(4);                         // Small space

    // Third row - Names
    $pdf->Cell($col1_width, 5, $consultationData['staff_name'] ?? '________________', 0, 0, 'L');
    $pdf->Cell($spacing, 5, '');
    $pdf->Cell($col2_width, 5, 'FELICITAS ASUNCION C. ELAGO, MD', 0, 1, 'L');

    // Fourth row - License numbers
    $pdf->Cell($col1_width, 5, 'License No.: ________________', 0, 0, 'L');
    $pdf->Cell($spacing, 5, '');
    $pdf->Cell($col2_width, 5, 'License No. 0160267', 0, 1, 'L');

    // Return the PDF as a string
    return $pdf->Output('', 'S');
}

function generatePrescriptionPDF($appointment, $prescriptionData)
{
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('WMSU Health Services');
    $pdf->SetAuthor('WMSU Health Services');
    $pdf->SetTitle('Prescription');
    $pdf->SetSubject('Dental Prescription');

    // Add a page
    $pdf->AddPage();

    // Set font
    $pdf->SetFont('helvetica', '', 12);

    // Left logo
    $pdf->Image('images/wmsu_logo.png', 10, 10, 20, 20, 'PNG');

    // Right logo
    $pdf->Image('images/clinic.png', 180, 10, 20, 20, 'PNG');

    // Set font for title
    $pdf->SetFont('helvetica', '', 10);

    // Move cursor down so text doesn't overlap logos
    $pdf->SetY(15);

    // Center text
    $pdf->Cell(0, 5, 'WESTERN MINDANAO STATE UNIVERSITY', 0, 1, 'C');
    $pdf->Cell(0, 5, 'Zamboanga City', 0, 1, 'C');
    $pdf->Cell(0, 5, 'UNIVERSITY HEALTH SERVICES CENTER', 0, 1, 'C');

    // Title
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'PRESCRIPTION', 0, 1, 'C');
    $pdf->Ln(5);

    // Patient Information - with all required fields
    $pdf->SetFont('helvetica', '', 12);

    // Name
    $pdf->Cell(40, 10, 'Name:', 0, 0);
    $pdf->Cell(0, 10, $appointment['first_name'] . ' ' . $appointment['last_name'], 0, 1);

    // Grade/Course/Year & Section
    $pdf->Cell(80, 10, 'Grade/Course/Year & Section:', 0, 0);
    $pdf->Cell(0, 10, $appointment['grade_course_section'] ?? 'Not specified', 0, 1);

    // Age
    $pdf->Cell(40, 10, 'Age:', 0, 0);
    $pdf->Cell(0, 10, $prescriptionData['age'] ?? 'Not specified', 0, 1);

    // Sex
    $pdf->Cell(40, 10, 'Sex:', 0, 0);
    $pdf->Cell(0, 10, ucfirst($appointment['sex'] ?? 'Not specified'), 0, 1);

    // Date
    $pdf->Cell(40, 10, 'Date:', 0, 0);
    $pdf->Cell(0, 10, date('F j, Y'), 0, 1);
    $pdf->Ln(10);

    // Prescription Content
    $pdf->SetFont('times', 'B', 24);
    $pdf->Cell(0, 10, 'Rx', 0, 1);
    $pdf->Ln(5);

    $pdf->SetFont('helvetica', '', 12);

    // Handle prescription text (JSON or plain text)
    $prescriptionText = 'No prescription provided';
    if (!empty($prescriptionData['prescription_text'])) {
        try {
            $prescriptionArray = json_decode($prescriptionData['prescription_text'], true);
            if (isset($prescriptionArray['medications'])) {
                $prescriptionText = implode("\n", $prescriptionArray['medications']);
            } else {
                $prescriptionText = $prescriptionData['prescription_text'];
            }
        } catch (Exception $e) {
            $prescriptionText = $prescriptionData['prescription_text'];
        }
    }

    $pdf->MultiCell(0, 10, $prescriptionText, 0, 'L');


    // Signature Section - Modified to include both signatures
    $pdf->Ln(1);

    // Set smaller font first
    $pdf->SetFont('helvetica', '', 10);

    // Calculate positions
    $col1_width = 100;  // First column width
    $col2_width = 80;  // Second column width
    $spacing = 10;     // Space between columns

    // First row - Titles
    $pdf->Cell($col1_width, 5, 'Attending Staff:', 0, 0, 'L');
    $pdf->Cell($col2_width, 5, 'Prescribing Physician:', 0, 1, 'L');

    // Third row - Names
    $pdf->Cell($col1_width, 5, $consultationData['staff_name'] ?? '________________', 0, 0, 'L');
    $pdf->Cell($spacing, 5, '');
    $pdf->Cell($col2_width, 5, 'FELICITAS ASUNCION C. ELAGO, MD', 0, 1, 'L');

    // Fourth row - License numbers
    $pdf->Cell($col1_width, 5, 'License No.: ________________', 0, 0, 'L');
    $pdf->Cell($spacing, 5, '');
    $pdf->Cell($col2_width, 5, 'License No. 0160267', 0, 1, 'L');
    // Return the PDF as a string
    return $pdf->Output('', 'S');
}



// Check database connection
if (!$conn || !empty(mysqli_connect_error())) {
    error_log('Database connection failed: ' . (!empty($_SESSION['user_id']) ? mysqli_connect_error($conn) : 'unknown error'));
    http_response_code(500);
    die('Internal Server Error: Database connection failed.');
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if appointment_id is provided
if (!isset($_GET['appointment_id']) && !isset($_POST['appointment_id'])) {
    header("Location: dental-appointments.php");
    exit();
}

$appointment_id = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : (int)$_GET['appointment_id'];
$success_message = '';
$error_message = '';

// Handle Form Submission (Consultation Form with Prescription Data)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'consultation') {
    // Fetch patient_id from patients table using appointments.user_id
    $sql = "
        SELECT p.id AS patient_id
        FROM appointments a
        INNER JOIN patients p ON a.user_id = p.user_id
        WHERE a.id = ?
    ";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        error_log('Failed to prepare patient_id query: ' . mysqli_error($conn));
        $error_message = 'Database error while fetching patient ID.';
    } else {
        mysqli_stmt_bind_param($stmt, "i", $appointment_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $patient = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$patient) {
            $error_message = 'No patient record found for this appointment.';
        } else {
            $patient_id = (int)$patient['patient_id'];
            $child_id = !empty($_POST['child_id']) ? (int)$_POST['child_id'] : null;
            $staff_id = (int)$_POST['staff_id'];
            $name = trim($_POST['name'] ?? '');
            $consultation_date = $_POST['consultation_date'] ?? '';
            $consultation_time = $_POST['consultation_time'] ?? '';
            $grade_course_section = trim($_POST['grade_course_section'] ?? '');
            $age = !empty($_POST['age']) ? (int)$_POST['age'] : null;
            $sex = trim($_POST['sex'] ?? '');
            $weight = trim($_POST['weight'] ?? '');
            $birthday = $_POST['birthday'] ?? '';
            $blood_pressure = trim($_POST['blood_pressure'] ?? '');
            $temperature = trim($_POST['temperature'] ?? '');
            $heart_rate = trim($_POST['heart_rate'] ?? '');
            $oxygen_saturation = trim($_POST['oxygen_saturation'] ?? '');
            $complaints = trim($_POST['complaints'] ?? '');
            $diagnosis = trim($_POST['diagnosis'] ?? '');
            $treatment = trim($_POST['treatment'] ?? '');
            $staff_signature = trim($_POST['staff_signature'] ?? '');
            $consultation_type = trim($_POST['consultation_type'] ?? '');
            $prescription_text = trim($_POST['prescription_text'] ?? '');
            $signature_data = trim($_POST['signature_data'] ?? '');

            // Log the received prescription_text for debugging
            error_log("Received prescription_text: '$prescription_text'");

            // Start transaction
            mysqli_begin_transaction($conn);
            try {
                // Insert into consultations table
                $sql = "
                    INSERT INTO consultations (
                        patient_id, child_id, staff_id, name, consultation_date, consultation_time,
                        grade_course_section, age, sex, weight, birthday, blood_pressure,
                        temperature, heart_rate, oxygen_saturation, complaints, diagnosis,
                        treatment, staff_signature, consultation_type
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_stmt_init($conn);
                if (!mysqli_stmt_prepare($stmt, $sql)) {
                    throw new Exception('Failed to prepare consultation query: ' . mysqli_error($conn));
                }
                mysqli_stmt_bind_param(
                    $stmt,
                    "iissssisisssssssssss",
                    $patient_id,
                    $child_id,
                    $staff_id,
                    $name,
                    $consultation_date,
                    $consultation_time,
                    $grade_course_section,
                    $age,
                    $sex,
                    $weight,
                    $birthday,
                    $blood_pressure,
                    $temperature,
                    $heart_rate,
                    $oxygen_saturation,
                    $complaints,
                    $diagnosis,
                    $treatment,
                    $staff_signature,
                    $consultation_type
                );
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception('Failed to execute consultation query: ' . mysqli_error($conn));
                }
                $consultation_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);


                if (!empty($prescription_text)) {
                    // fuck this
                    $sql = "
SELECT 
    COALESCE(c.first_name, p.firstname) AS first_name, 
    COALESCE(c.last_name, p.surname) AS last_name,
    p.birthday,
    p.sex,
    CASE
        WHEN c.id IS NOT NULL THEN c.type
        ELSE CONCAT(
            COALESCE(p.course, ''),
            CASE 
                WHEN p.year_level IS NOT NULL OR p.grade_level IS NOT NULL 
                THEN CONCAT(' ', COALESCE(p.year_level, p.grade_level, '')) 
                ELSE '' 
            END,
            CASE 
                WHEN p.track_strand IS NOT NULL 
                THEN CONCAT(' - ', p.track_strand) 
                ELSE '' 
            END
        )
    END AS grade_course_section
FROM appointments a
LEFT JOIN patients p ON a.user_id = p.user_id
LEFT JOIN children c ON a.child_id = c.id
WHERE a.id = ?";
                    $stmt = mysqli_stmt_init($conn);
                    if (!mysqli_stmt_prepare($stmt, $sql)) {
                        throw new Exception('Failed to prepare appointment query: ' . mysqli_error($conn));
                    }
                    mysqli_stmt_bind_param($stmt, "i", $appointment_id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $appointment = mysqli_fetch_assoc($result);
                    mysqli_stmt_close($stmt);

                    if ($appointment) {
                        // Calculate age
                        $age = '';
                        if (!empty($appointment['birthday'])) {
                            $birthday = new DateTime($appointment['birthday']);
                            $today = new DateTime();
                            $interval = $today->diff($birthday);
                            $age = $interval->y;
                        }

                        // Prepare prescription data
                        $patient_name = $appointment['first_name'] . ' ' . $appointment['last_name'];
                        $sex = $appointment['sex'];

                        // Split prescription text into individual medications and store as JSON array
                        // Replace this section:
                        $medication_array = array_filter(array_map('trim', explode("\n", $prescription_text)), function ($item) {
                            return !empty($item);
                        });
                        if (empty($medication_array)) {
                            throw new Exception('No valid medications provided in prescription text.');
                        }
                        $medications = json_encode(['medications' => array_values($medication_array)]);


                        if (!empty($_POST['prescription_text'])) {
                            $prescription_text = trim($_POST['prescription_text']);

                            // Split by newlines and create a proper JSON structure
                            $medication_array = array_filter(
                                array_map('trim', explode("\n", $prescription_text)),
                                function ($item) {
                                    return !empty($item);
                                }
                            );

                            $medications = json_encode(['medications' => array_values($medication_array)]);

                            // Validate the JSON
                            if (json_last_error() !== JSON_ERROR_NONE) {
                                throw new Exception('Invalid medication data: ' . json_last_error_msg());
                            }
                        } else {
                            // Empty array if no prescription text
                            $medications = json_encode(['medications' => []]);
                        }


                        // Then use $medications in your SQL query

                        // Log the JSON medications for debugging
                        error_log("Prepared medications JSON: '$medications'");

                        $prescribing_physician = 'FELICITAS ASUNCION C. ELAGO, MD';
                        $physician_signature = $signature_data;
                        $prescription_date = date('Y-m-d');

                        // Insert into prescriptions table
                        $sql = "
                            INSERT INTO prescriptions (
                                consultation_id, patient_name, age, sex, diagnosis, 
                                medications, prescribing_physician, physician_signature, prescription_date
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = mysqli_stmt_init($conn);
                        if (!mysqli_stmt_prepare($stmt, $sql)) {
                            throw new Exception('Failed to prepare prescription query: ' . mysqli_error($conn));
                        }
                        mysqli_stmt_bind_param(
                            $stmt,
                            "isississs",
                            $consultation_id,
                            $patient_name,
                            $age,
                            $sex,
                            $diagnosis,
                            $medications,
                            $prescribing_physician,
                            $physician_signature,
                            $prescription_date
                        );
                        if (!mysqli_stmt_execute($stmt)) {
                            throw new Exception('Failed to execute prescription query: ' . mysqli_error($conn));
                        }
                        mysqli_stmt_close($stmt);
                    } else {
                        throw new Exception('Failed to fetch appointment details for prescription.');
                    }
                } else {
                    // Log if no prescription text is provided
                    error_log("No prescription text provided for consultation_id: $consultation_id");
                }

                // Update appointment status to Completed
                $sql = "UPDATE appointments SET status = 'Completed' WHERE id = ?";
                $stmt = mysqli_stmt_init($conn);
                if (!mysqli_stmt_prepare($stmt, $sql)) {
                    throw new Exception('Failed to prepare appointment update query: ' . mysqli_error($conn));
                }
                mysqli_stmt_bind_param($stmt, "i", $appointment_id);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception('Failed to execute appointment update query: ' . mysqli_error($conn));
                }
                mysqli_stmt_close($stmt);

                // Commit transaction
                mysqli_commit($conn);


              // Get patient email AND user ID
$emailSql = "SELECT u.id AS user_id, u.email 
             FROM appointments a
             INNER JOIN users u ON a.user_id = u.id
             WHERE a.id = ?";
$emailStmt = mysqli_prepare($conn, $emailSql);
mysqli_stmt_bind_param($emailStmt, "i", $appointment_id);
mysqli_stmt_execute($emailStmt);
$emailResult = mysqli_stmt_get_result($emailStmt);

$emailData = mysqli_fetch_assoc($emailResult);
$patientEmail = $emailData['email'] ?? null;
$userId = $emailData['user_id'] ?? null;



                if ($patientEmail) {
                    // Prepare consultation data
                    $consultationData = [
                        'age' => $age,
                        'weight' => $weight,
                        'blood_pressure' => $blood_pressure,
                        'temperature' => $temperature,
                        'heart_rate' => $heart_rate,
                        'oxygen_saturation' => $oxygen_saturation,
                        'complaints' => $complaints,
                        'diagnosis' => $diagnosis,
                        'treatment' => $treatment,
                        'staff_name' => $_POST['staff_name'] ?? 'Staff Member'
                    ];

                    // Generate PDFs
                    $consultationPDF = generateConsultationPDF($appointment, $consultationData);

                    $prescriptionPDF = null;
                    if (!empty($prescription_text)) {
                        $prescriptionData = [
                            'age' => $age,
                            'prescription_text' => $prescription_text
                        ];
                        $prescriptionPDF = generatePrescriptionPDF($appointment, $prescriptionData);
                    }

                    // Prepare email content
                    $subject = "WMSU Health Services - Dental Consultation Summary";
                    $body = "
        <h2>WMSU Health Services - Dental Consultation Summary</h2>
        <p>Dear " . htmlspecialchars($appointment['first_name']) . ",</p>
        <p>Your dental consultation on " . date('F j, Y') . " has been completed. Please find attached your consultation summary and prescription (if applicable).</p>
        
        <h3>Consultation Details</h3>
        <p><strong>Diagnosis:</strong> " . (!empty($diagnosis) ? nl2br(htmlspecialchars($diagnosis)) : 'Not specified') . "</p>
        <p><strong>Treatment:</strong> " . (!empty($treatment) ? nl2br(htmlspecialchars($treatment)) : 'Not specified') . "</p>
        
        <p>If you have any questions, please contact the University Health Services.</p>
        <p>Thank you!</p>
        <hr>
        <p><small>This is an automated message. Please do not reply directly to this email.</small></p>
    ";

                    try {
                        // Send email with PHPMailer
                        $mail = new PHPMailer(true);
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = $_ENV['SMTP_USER'];
                        $mail->Password = $_ENV['SMTP_PASS'];
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = 587;

                        $mail->setFrom($_ENV['SMTP_USER'], 'WMSU Health Services');
                        $mail->addAddress($patientEmail);
                        $mail->isHTML(true);
                        $mail->Subject = $subject;
                        $mail->Body = $body;
                        $mail->AltBody = strip_tags(str_replace("<br>", "\n", $body));

                        // Attach consultation PDF
                        $mail->addStringAttachment($consultationPDF, 'consultation_' . date('Y-m-d') . '.pdf');

                        // Attach prescription PDF if exists
                        if ($prescriptionPDF) {
                            $mail->addStringAttachment($prescriptionPDF, 'prescription_' . date('Y-m-d') . '.pdf');
                        }

                        $mail->send();
                        $success_message = 'Consultation submitted successfully. PDF documents sent to patient.';
                    } catch (Exception $e) {
                        error_log("Email sending failed: " . $e->getMessage());
                        $success_message = 'Consultation submitted successfully, but email with PDFs failed to send.';
                    }
                } else {
                    $success_message = 'Consultation submitted successfully (no patient email found).';
                }

                function createUserNotification($conn, $userId, $type, $title, $description, $link = '#')
                {
                    try {
                        $stmt = $conn->prepare("
            INSERT INTO user_notifications 
            (user_id, type, title, description, link, status, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, 'unread', NOW(), NOW())
        ");

                        if (!$stmt) {
                            error_log("Notification prepare failed: " . $conn->error);
                            return false;
                        }

                        $stmt->bind_param("issss", $userId, $type, $title, $description, $link);
                        if (!$stmt->execute()) {
                            error_log("Notification execute failed: " . $stmt->error);
                            return false;
                        }

                        $stmt->close();
                        return true;
                    } catch (Exception $e) {
                        error_log("Notification error: " . $e->getMessage());
                        return false;
                    }
                } // After successful email sending, add this:
                $notificationTitle = "Dental Consultation Form Issued!";
                $notificationDesc = "Your dental consultation and certificate has been issued and sent to your email!";

                // Create notification for the target user
                $notificationSent = createUserNotification(
                    $conn,
                    $userId,
                    'dental_certificate',
                    $notificationTitle,
                    $notificationDesc,
                    '#' // Link to view certificates
                );


                // Clear session data
                unset($_SESSION['consultation_data']);

                // Redirect to dental-appointments.php
                header("Location: dental-appointments.php");
                exit();
            } catch (Exception $e) {
                mysqli_rollback($conn);
                error_log('Error in consultation submission: ' . $e->getMessage());
                $error_message = 'Database error while saving consultation: ' . $e->getMessage();
                // Retain form data in session to repopulate fields
                // In your error handling section:
                $_SESSION['consultation_data'] = [
                    'weight' => $weight,
                    'blood_pressure' => $blood_pressure,
                    'temperature' => $temperature,
                    'heart_rate' => $heart_rate,
                    'oxygen_saturation' => $oxygen_saturation,
                    'complaints' => $complaints,
                    'diagnosis' => $diagnosis,
                    'treatment' => $treatment,
                    'staff_signature' => $staff_signature,
                    'prescription_text' => $_POST['prescription_text'] ?? '', // Keep the raw text
                    'signature_data' => $signature_data
                ];
            }
        }
    }
}

$sql = "
    SELECT 
        a.id, 
        a.user_id, 
        a.child_id, 
        a.reason, 
        a.appointment_type,
        COALESCE(c.first_name, p.firstname) AS first_name, 
        COALESCE(c.last_name, p.surname) AS last_name,
      COALESCE(
  c.type,
  CONCAT(
    COALESCE(p.course, ''),
    ' ',
    COALESCE(p.year_level, p.grade_level, ''),
    ' - ',
    COALESCE(p.track_strand, '')
  )
) AS grade_course_section,

        p.birthday,
        p.sex,
        p.city_address,
        p.course,
        p.year_level,
        u.user_type,
        p.id AS patient_id
    FROM appointments a
    LEFT JOIN patients p ON a.user_id = p.user_id
    LEFT JOIN children c ON a.child_id = c.id
    LEFT JOIN users u ON a.user_id = u.id
    WHERE a.id = ? AND a.appointment_type = 'dental' AND a.status = 'Pending'";
$stmt = mysqli_stmt_init($conn);
if (mysqli_stmt_prepare($stmt, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $appointment_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $appointment = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// if (!$appointment || empty($appointment['patient_id'])) {
//     header("Location: dental-appointments.php");
//     exit();
// }

// Calculate age based on birthday
$age = '';
if (!empty($appointment['birthday'])) {
    $birthday = new DateTime($appointment['birthday']);
    $today = new DateTime();
    $interval = $today->diff($birthday);
    $age = $interval->y;
}

// Get current date and time
$current_date = date('Y-m-d');
$current_time = date('H:i');

// Set address fallback if not available
$address = isset($appointment['city_address']) ? htmlspecialchars($appointment['city_address']) : 'N/A';

// Common medications
$common_medications = [
    "Paracetamol 500mg, 1 tablet every 6 hours as needed for fever/pain",
    "Ibuprofen 200mg, 1 tablet every 8 hours after meals for pain/inflammation",
    "Amoxicillin 500mg, 1 capsule every 8 hours for 7 days for infection",
    "Cetirizine 10mg, 1 tablet at bedtime for allergies",
    "Omeprazole 20mg, 1 capsule daily before breakfast for acid reflux",
    "Salbutamol Inhaler, 2 puffs every 4-6 hours as needed for asthma",
    "None, the patient is healthy."
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultation Form - WMSU Health Services</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/consultationForm.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/medicalappointments.css">
      <link rel="apple-touch-icon" sizes="180x180" href="images/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="images/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="images/favicon-16x16.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
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

        .prescription-container {
            font-family: 'Poppins', sans-serif !important;
        }

        h1,
        h2,
        h3,
        .small-heading,
        .modal-title,
        .section-title {
            font-family: 'Cinzel', serif;
        }

        .rx-section {
            font-family: 'Times New Roman' !important;
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        #consultation-container h2.text-center {
            position: relative;
            top: 0;
            left: 0;
            width: 100%;
            text-align: center;
            margin-bottom: 1rem;
            z-index: 1;
        }

        @media print {
            #consultation-container h2.text-center {
                position: relative !important;
                top: 0 !important;
                margin-bottom: 1rem !important;
            }
        }
    </style>
</head>

<body>
    <div id="app" class="d-flex">
        <button id="burger-btn" class="burger-btn">☰</button>


        <?php include 'include/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content w-100">
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <div class="print-container" id="consultation-container">
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
                <h2 class="text-center mb-4">Dental Consultation Form</h2>
                <hr>
                <form id="consultationForm" method="POST">
                    <input type="hidden" name="form_type" value="consultation">
                    <input type="hidden" name="patient_id" value="<?php echo htmlspecialchars($appointment['patient_id']); ?>">
                    <input type="hidden" name="child_id" value="<?php echo htmlspecialchars($appointment['child_id'] ?: ''); ?>">
                    <input type="hidden" name="appointment_id" value="<?php echo htmlspecialchars($appointment['id']); ?>">
                    <input type="hidden" name="staff_id" value="<?php echo htmlspecialchars($_SESSION['user_id']); ?>">
                    <input type="hidden" name="consultation_type" value="dental">
                    <input type="hidden" name="prescription_text" id="consultation-prescription-text" value="">
                    <input type="hidden" name="signature_data" id="consultation-signature-confirm">
                    <div class="section-header print-only">Patient Information</div>
                    <div class="row mb-2">
                        <div class="col-md-6">
                            <label class="fw-bold" for="name">Name:</label>
                            <input id="name" type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?>" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold" for="consultation_date">Date:</label>
                            <input id="consultation_date" type="date" class="form-control" name="consultation_date" value="<?php echo $current_date; ?>" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold" for="consultation_time">Time:</label>
                            <input id="consultation_time" type="time" class="form-control" name="consultation_time" value="<?php echo $current_time; ?>" readonly>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-6">
                            <label class="fw-bold" for="grade_course_section">Grade/Course/Year & Section:</label>
                            <input id="grade_course_section" type="text" class="form-control" name="grade_course_section" value="<?php echo htmlspecialchars($appointment['grade_course_section']); ?>" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold" for="age">Age:</label>
                            <input id="age" type="number" class="form-control" name="age" value="<?php echo htmlspecialchars($age); ?>" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold" for="sex">Sex:</label>
                            <select id="sex" class="form-control" name="sex" readonly>
                                <option value="<?php echo htmlspecialchars(ucfirst($appointment['sex'])); ?>" selected><?php echo htmlspecialchars(ucfirst($appointment['sex'])); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="section-header print-only">Vital Signs</div>
                    <div class="row mb-2">
                        <div class="col-md-3">
                            <label class="fw-bold" for="weight">Weight (Wt):</label>
                            <input id="weight" type="text" class="form-control" name="weight" value="<?php echo htmlspecialchars(isset($_SESSION['consultation_data']['weight']) ? $_SESSION['consultation_data']['weight'] : ''); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold" for="birthdate">Birthday (BDAY):</label>
                            <input id="birthdate" type="date" class="form-control" name="birthday" value="<?php echo htmlspecialchars($appointment['birthday']); ?>" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold" for="blood_pressure">B/P:</label>
                            <input id="blood_pressure" type="text" class="form-control" name="blood_pressure" value="<?php echo htmlspecialchars(isset($_SESSION['consultation_data']['blood_pressure']) ? $_SESSION['consultation_data']['blood_pressure'] : ''); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold" for="temperature">Temperature (TEMP):</label>
                            <input id="temperature" type="text" class="form-control" name="temperature" value="<?php echo htmlspecialchars(isset($_SESSION['consultation_data']['temperature']) ? $_SESSION['consultation_data']['temperature'] : ''); ?>">
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-3">
                            <label class="fw-bold" for="heart_rate">Heart Rate (HR):</label>
                            <input id="heart_rate" type="text" class="form-control" name="heart_rate" value="<?php echo htmlspecialchars(isset($_SESSION['consultation_data']['heart_rate']) ? $_SESSION['consultation_data']['heart_rate'] : ''); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold" for="oxygen_saturation">O2 SAT:</label>
                            <input id="oxygen_saturation" type="text" class="form-control" name="oxygen_saturation" value="<?php echo htmlspecialchars(isset($_SESSION['consultation_data']['oxygen_saturation']) ? $_SESSION['consultation_data']['oxygen_saturation'] : ''); ?>">
                        </div>
                    </div>
                    <div class="section-header print-only">Consultation Details</div>
                    <div class="mb-2">
                        <label class="fw-bold" for="complaints">Complaints:</label>
                        <textarea id="complaints" class="form-control" name="complaints" rows="3"><?php echo htmlspecialchars(isset($_SESSION['consultation_data']['complaints']) ? $_SESSION['consultation_data']['complaints'] : $appointment['reason']); ?></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="fw-bold" for="diagnosis">Diagnosis:</label>
                            <textarea id="diagnosis" class="form-control" name="diagnosis" rows="5"><?php echo htmlspecialchars(isset($_SESSION['consultation_data']['diagnosis']) ? $_SESSION['consultation_data']['diagnosis'] : ''); ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold" for="treatment">Treatment:</label>
                            <textarea id="treatment" class="form-control" name="treatment" rows="5"><?php echo htmlspecialchars(isset($_SESSION['consultation_data']['treatment']) ? $_SESSION['consultation_data']['treatment'] : ''); ?></textarea>
                        </div>
                    </div>
                    <div class="mt-3">

                        <label class="fw-bold print-hide">Draw Signature:</label><br>
                        <canvas id="signature-canvas" width="400" height="75" style="border: 1px solid #ccc; border-radius: 5px;"></canvas>
                        <input type="hidden" name="staff_signature" id="signature-data" value="<?php echo htmlspecialchars(isset($_SESSION['consultation_data']['staff_signature']) ? $_SESSION['consultation_data']['staff_signature'] : ''); ?>">
                        <img id="signature-image" class="print-only" src="<?php echo htmlspecialchars(isset($_SESSION['consultation_data']['staff_signature']) ? $_SESSION['consultation_data']['staff_signature'] : ''); ?>" alt="Signature" style="display: none; max-width: 200px; height: auto;">
                        <br>
                        <label class="fw-bold" for="staff-name">Name/Signature of Staff:</label>

                        <input id="staff-name" type="text" class="form-control mb-3" name="staff_name" placeholder="Enter Name" value="">
                        <div class="mt-2 print-hide">
                            <button type="button" class="btn btn-danger w-100" onclick="clearCanvas()"> <i class="bi bi-x-circle"></i> Clear Signature</button>
                        </div>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-end mt-4 no-print">
                        <button type="submit" class="btn btn-success me-2">
                            <i class="bi bi-check-lg"></i> Submit Form
                        </button>
                        <button type="button" class="btn btn-primary me-2" onclick="showPrescriptionForm()">
                            <i class="bi 	bi-capsule"></i> Prescription
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="printConsultationForm()">
                            <i class="bi bi-printer"></i> Print
                        </button>
                    </div>
                </form>
            </div>

            <!-- Prescription Form -->
            <div class="print-container prescription-container" id="prescription-container" style="display: none;">
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
                <h2 class="text-center mb-4">Prescription Form</h2>
                <hr>
                <div class="patient-info">
                    <div class="name-row">
                        <span class="label">Name:</span>
                        <span class="value"><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Address:</span>
                        <span class="value"><?php echo $address; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Age:</span>
                        <span class="value"><?php echo htmlspecialchars($age); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Sex:</span>
                        <span class="value"><?php echo htmlspecialchars(ucfirst($appointment['sex'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Date:</span>
                        <span class="value"><?php echo date('m/d/Y'); ?></span>
                    </div>
                </div>
                <div class="rx-section">Rx</div>
                <div class="prescription-content">
                    <form id="prescriptionForm">
                        <input type="hidden" name="form_type" value="consultation">
                        <input type="hidden" name="appointment_id" value="<?php echo htmlspecialchars($appointment['id']); ?>">
                        <input type="hidden" name="signature_data" id="prescription-signature-data" value="<?php echo htmlspecialchars(isset($_SESSION['consultation_data']['signature_data']) ? $_SESSION['consultation_data']['signature_data'] : ''); ?>">
                        <select id="common-medications" class="no-print">
                            <option value="">Select common medication</option>
                            <?php foreach ($common_medications as $med): ?>
                                <option value="<?php echo htmlspecialchars($med); ?>"><?php echo htmlspecialchars($med); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <textarea id="prescription-text" name="prescription_text" placeholder="Enter prescription details..."><?php echo htmlspecialchars(isset($_SESSION['consultation_data']['prescription_text']) ? $_SESSION['consultation_data']['prescription_text'] : ''); ?></textarea>
                        <div id="prescription-text-display" class="prescription-text"><?php echo htmlspecialchars(isset($_SESSION['consultation_data']['prescription_text']) ? $_SESSION['consultation_data']['prescription_text'] : ''); ?></div>
                        <label class="fw-bold print-hide">Physician Signature:</label> <br>
                        <canvas id="prescription-signature-canvas" class="print-hide" width="400" height="150" style="border: 1px solid #ccc; border-radius: 5px;"></canvas>
                        <br>
                        <button type="button" class="btn btn-danger w-100 print-hide" onclick="clearPrescriptionCanvas()">
                            <i class="bi bi-x-circle"></i> Clear Signature
                        </button>
                    </form>
                    <div class="footer">
                        <img id="prescription-signature-image" class="print-only" src="<?php echo htmlspecialchars(isset($_SESSION['consultation_data']['signature_data']) ? $_SESSION['consultation_data']['signature_data'] : ''); ?>" alt="Signature" style="max-width: 200px; height: auto;">
                        <div>
                            FELICITAS ASUNCION C. ELAGO, MD
                            <span>MEDICAL OFFICER III</span>
                            <span>License No. 0160267</span>
                            <span>PTR No. 2795114</span>
                        </div>
                    </div>
                </div>

                <div class="container" id="prescription-buttons" style="display: none;">
                    <button class="btn btn-warning me-2" onclick="returnPrescription()">
                        <i class="bi bi-arrow-left"></i> Back
                    </button>
                    <button class="btn btn-secondary me-2" onclick="printPrescription()">
                        <i class="bi bi-printer"></i> Print
                    </button>
                    <button class="btn btn-primary" onclick="downloadPrescription()">
                        <i class="bi bi-download"></i> Download
                    </button>
                </div>

                <!-- Footer Buttons for Prescription -->

            </div>
            <!-- Footer Buttons for Prescription -->

        </div>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
        <script>
            // Sidebar toggle
            document.getElementById('burger-btn').addEventListener('click', () => {
                document.getElementById('sidebar').classList.toggle('active');
            });

            // Consultation Form Signature
            const canvas = document.getElementById('signature-canvas');
            const ctx = canvas.getContext('2d');
            let isDrawing = false;

            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.strokeStyle = 'black';

            canvas.addEventListener('mousedown', (e) => {
                isDrawing = true;
                ctx.beginPath();
                ctx.moveTo(e.offsetX, e.offsetY);
            });

            canvas.addEventListener('mousemove', (e) => {
                if (isDrawing) {
                    ctx.lineTo(e.offsetX, e.offsetY);
                    ctx.stroke();
                }
            });

            canvas.addEventListener('mouseup', () => {
                isDrawing = false;
                updateSignature();
            });

            canvas.addEventListener('mouseleave', () => {
                isDrawing = false;
            });

            canvas.addEventListener('contextmenu', (e) => e.preventDefault());

            function clearCanvas() {
                prescriptionCtx.clearRect(0, 0, prescriptionCanvas.width, prescriptionCanvas.height);
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                document.getElementById('signature-image').src = '';
                document.getElementById('signature-image').style.display = 'none';
                document.getElementById('signature-data').value = '';
                document.getElementById('consultation-signature-confirm').value = '';
                document.getElementById('prescription-signature-data').value = '';
                document.getElementById('prescription-signature-image').src = '';
            }

            function updateSignature() {
                const signatureData = canvas.toDataURL();
                document.getElementById('signature-image').src = signatureData;
                document.getElementById('signature-data').value = signatureData;
                document.getElementById('consultation-signature-confirm').value = signatureData;
                document.getElementById('prescription-signature-data').value = signatureData;
                document.getElementById('prescription-signature-image').src = signatureData;
                const prescriptionCanvas = document.getElementById('prescription-signature-canvas');
                const prescriptionCtx = prescriptionCanvas.getContext('2d');
                const img = new Image();
                img.src = signatureData;
                img.onload = () => {
                    prescriptionCtx.clearRect(0, 0, prescriptionCanvas.width, prescriptionCanvas.height);
                    prescriptionCtx.drawImage(img, 0, 0);
                };
            }

            // Restore consultation signature if exists
            const signatureData = document.getElementById('signature-data').value;
            if (signatureData) {
                const img = new Image();
                img.src = signatureData;
                img.onload = () => {
                    ctx.drawImage(img, 0, 0);
                    document.getElementById('signature-image').src = signatureData;
                    document.getElementById('consultation-signature-confirm').value = signatureData;
                    document.getElementById('prescription-signature-data').value = signatureData;
                    document.getElementById('prescription-signature-image').src = signatureData;
                };
            }

            // Prescription Signature Canvas
            const prescriptionCanvas = document.getElementById('prescription-signature-canvas');
            const prescriptionCtx = prescriptionCanvas.getContext('2d');
            let isPrescriptionDrawing = false;

            prescriptionCtx.lineWidth = 2;
            prescriptionCtx.lineCap = 'round';
            prescriptionCtx.strokeStyle = 'black';

            prescriptionCanvas.addEventListener('mousedown', (e) => {
                isPrescriptionDrawing = true;
                prescriptionCtx.beginPath();
                prescriptionCtx.moveTo(e.offsetX, e.offsetY);
            });

            prescriptionCanvas.addEventListener('mousemove', (e) => {
                if (isPrescriptionDrawing) {
                    prescriptionCtx.lineTo(e.offsetX, e.offsetY);
                    prescriptionCtx.stroke();
                }
            });

            prescriptionCanvas.addEventListener('mouseup', () => {
                isPrescriptionDrawing = false;
                updatePrescriptionSignature();
            });

            prescriptionCanvas.addEventListener('mouseleave', () => {
                isPrescriptionDrawing = false;
            });

            prescriptionCanvas.addEventListener('contextmenu', (e) => e.preventDefault());

            function clearPrescriptionCanvas() {
                prescriptionCtx.clearRect(0, 0, prescriptionCanvas.width, prescriptionCanvas.height);
                document.getElementById('prescription-signature-image').src = '';
                document.getElementById('prescription-signature-data').value = '';
                document.getElementById('consultation-signature-confirm').value = '';
                document.getElementById('signature-data').value = '';
                document.getElementById('signature-image').src = '';
                const consultationCanvas = document.getElementById('prescription-signature-canvas');
                const consultationCtx = consultationCanvas.getContext('2d');
                consultationCtx.clearRect(0, 0, consultationCanvas.width, consultationCanvas.height);
            }

            function updatePrescriptionSignature() {
                const signatureData = prescriptionCanvas.toDataURL();
                document.getElementById('prescription-signature-image').src = signatureData;
                document.getElementById('prescription-signature-data').value = signatureData;
                document.getElementById('consultation-signature-confirm').value = signatureData;
                document.getElementById('signature-data').value = signatureData;
                document.getElementById('signature-image').src = signatureData;
                const consultationCanvas = document.getElementById('prescription-signature-canvas');
                const consultationCtx = consultationCanvas.getContext('2d');
                const img = new Image();
                img.src = signatureData;
                img.onload = () => {
                    consultationCtx.clearRect(0, 0, consultationCanvas.width, consultationCanvas.height);
                    consultationCtx.drawImage(img, 0, 0);
                };
            }

            // Restore prescription signature if exists
            const prescriptionSignatureData = document.getElementById('prescription-signature-data').value;
            if (prescriptionSignatureData) {
                const img = new Image();
                img.src = prescriptionSignatureData;
                img.onload = () => {
                    prescriptionCtx.drawImage(img, 0, 0);
                    document.getElementById('prescription-signature-image').src = prescriptionSignatureData;
                };
            }

            // Prescription Medication Selection
            const medicationSelect = document.getElementById('common-medications');
            const prescriptionText = document.getElementById('prescription-text');
            const prescriptionTextDisplay = document.getElementById('prescription-text-display');
            const consultationPrescriptionText = document.getElementById('consultation-prescription-text');

            medicationSelect.addEventListener('change', function() {
                if (this.value) {
                    const currentText = prescriptionText.value.trim();
                    prescriptionText.value = currentText ? `${currentText}\n${this.value}` : this.value;
                    prescriptionTextDisplay.textContent = prescriptionText.value;
                    consultationPrescriptionText.value = prescriptionText.value;
                    this.value = '';
                }
            });

            prescriptionText.addEventListener('input', function() {
                prescriptionTextDisplay.textContent = this.value;

                // Convert to proper JSON format for the hidden field
                const lines = this.value.split('\n').filter(line => line.trim() !== '');
                consultationPrescriptionText.value = JSON.stringify({
                    medications: lines
                });
            });
            // Initialize prescription text
            consultationPrescriptionText.value = prescriptionText.value;

            // Form Navigation
            function showPrescriptionForm() {
                // clearPrescriptionCanvas()
                const form = document.getElementById('consultationForm');
                const signatureEmpty = canvas.toDataURL() === document.createElement('canvas').toDataURL();

                if (signatureEmpty) {
                    alert('Please provide a signature for the consultation form.');
                    return;
                }

                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }

                // updateSignature();
                consultationPrescriptionText.value = prescriptionText.value;
                document.getElementById('consultation-container').style.display = 'none';
                document.getElementById('prescription-container').style.display = 'block';
                document.getElementById('prescription-buttons').style.display = 'flex';
                prescriptionTextDisplay.textContent = prescriptionText.value;
            }

            function returnPrescription() {
                // clearPrescriptionCanvas()
                prescriptionText.value = prescriptionText.value;
                document.getElementById('consultation-container').style.display = 'block';
                document.getElementById('prescription-container').style.display = 'none';
                document.getElementById('prescription-buttons').style.display = 'none';
            }

            function printConsultationForm() {
                const requiredFields = {
                    'staff-name': 'Staff Name',
                    'signature-data': 'Signature',
                    'name': 'Patient Name',
                    'consultation_date': 'Date',
                    'consultation_time': 'Time',
                    'grade_course_section': 'Grade/Course/Year & Section',
                    'age': 'Age',
                    'sex': 'Sex',
                    'weight': 'Weight',
                    'birthdate': 'Birthday',
                    'blood_pressure': 'Blood Pressure',
                    'temperature': 'Temperature',
                    'heart_rate': 'Heart Rate',
                    'oxygen_saturation': 'O2 Saturation',
                    'complaints': 'Complaints',
                    'diagnosis': 'Diagnosis',
                    'treatment': 'Treatment'
                };

                const missingFields = [];
                for (const [id, label] of Object.entries(requiredFields)) {
                    const el = document.getElementById(id);
                    if (!el || !el.value.trim()) {
                        missingFields.push(label);
                    }
                }

                if (missingFields.length > 0) {
                    const msg = `<ul style="text-align:left">${missingFields.map(f => `<li>${f}</li>`).join('')}</ul>`;
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Missing Required Fields',
                            html: msg,
                            confirmButtonColor: '#a6192e'
                        });
                    } else {
                        alert(`Missing fields:\n${missingFields.join(', ')}`);
                    }
                    return;
                }



                // Show the signature image if not already visible
                const signatureImage = document.getElementById('signature-image');
                if (signatureImage && signatureData) {
                    signatureImage.src = signatureData;
                    signatureImage.style.display = 'block';
                }

                const printContent = document.getElementById('consultation-container').outerHTML;
                const printWindow = window.open('', '_blank');

                // Open document and write HTML
                printWindow.document.open();
                printWindow.document.write(`
        <!DOCTYPE html>
        <html>
            <head>
                <title>Dental Consultation Form</title>
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
                <link rel="stylesheet" href="css/consultationForm.css">
                <link rel="stylesheet" href="css/medicalappointments.css">
                <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
                <style>
                    body {
                        font-family: 'Poppins', sans-serif;
                  
                    }

                    h1, h2, h3 {
                        font-family: 'Cinzel', serif;
                    }

                    @media print {
                        .no-print, .print-hide, button {
                            display: none !important;
                        }
                        .print-only {
                            display: block !important;
                        }
                    }
                </style>
            </head>
            <body>
                ${printContent}
            </body>
        </html>
    `);
                printWindow.document.close();

                // Wait until new window finishes loading
                printWindow.onload = () => {
                    printWindow.focus();
                    printWindow.print();
                    printWindow.close();
                };
            }



            function printPrescription() {
                const prescriptionTextValue = prescriptionText.value.trim();

                if (!prescriptionTextValue) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Missing Prescription',
                        text: 'Please enter a prescription before printing.'
                    });
                    return;
                }

                if (prescriptionCanvas.toDataURL() === document.createElement('canvas').toDataURL()) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Missing Signature',
                        text: 'Please provide a signature before printing the prescription.'
                    });
                    return;
                }

                updatePrescriptionSignature();
                consultationPrescriptionText.value = prescriptionText.value;
                prescriptionTextDisplay.textContent = prescriptionTextValue;
                document.getElementById('prescription-signature-image').style.display = 'block';

                const consultationContainer = document.getElementById('consultation-container');
                const prescriptionContainer = document.getElementById('prescription-container');
                const prescriptionButtons = document.getElementById('prescription-buttons');

                consultationContainer.style.display = 'none';
                prescriptionContainer.style.display = 'block';
                prescriptionButtons.style.display = 'none';

                const restoreFunction = () => {
                    consultationContainer.style.display = 'none';
                    prescriptionContainer.style.display = 'block';
                    prescriptionButtons.style.display = 'flex';
                    document.getElementById('prescription-signature-image').style.display = 'none';
                    window.removeEventListener('afterprint', restoreFunction);
                };

                window.addEventListener('afterprint', restoreFunction);

                window.print();
            }

            function downloadPrintedPrescription() {
                const prescriptionTextValue = prescriptionText.value.trim();

                if (!prescriptionTextValue) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Missing Prescription',
                        text: 'Please enter a prescription before downloading.'
                    });
                    return;
                }

                if (prescriptionCanvas.toDataURL() === document.createElement('canvas').toDataURL()) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Missing Signature',
                        text: 'Please provide a signature before downloading the prescription.'
                    });
                    return;
                }

                updatePrescriptionSignature();
                consultationPrescriptionText.value = prescriptionText.value;
                prescriptionTextDisplay.textContent = prescriptionTextValue;
                document.getElementById('prescription-signature-image').style.display = 'block';

                const consultationContainer = document.getElementById('consultation-container');
                const prescriptionContainer = document.getElementById('prescription-container');
                const prescriptionButtons = document.getElementById('prescription-buttons');

                consultationContainer.style.display = 'none';
                prescriptionContainer.style.display = 'block';
                prescriptionButtons.style.display = 'none';

                const restoreFunction = () => {
                    consultationContainer.style.display = 'none';
                    prescriptionContainer.style.display = 'block';
                    prescriptionButtons.style.display = 'flex';
                    document.getElementById('prescription-signature-image').style.display = 'none';
                    window.removeEventListener('afterprint', restoreFunction);
                };

                window.addEventListener('afterprint', restoreFunction);

                window.print();
            }



            function downloadPrescription() {
                Swal.fire({
                    icon: 'info',
                    title: 'Save as PDF',
                    text: 'When the print dialog appears, select **"Save as PDF"** as your destination and rename as your desired file name!',
                    confirmButtonText: 'Continue',
                    allowOutsideClick: false
                }).then(() => {
                    downloadPrintedPrescription(); // Call your existing function that sets up and calls window.print()
                });
            }


document.getElementById('consultationForm').addEventListener('submit', function(e) {
    e.preventDefault(); // <-- Always prevent first

    const requiredFields = {
        'staff-name': 'Staff Name',
        'signature-data': 'Signature',
        'name': 'Patient Name',
        'consultation_date': 'Date',
        'consultation_time': 'Time',
        'grade_course_section': 'Grade/Course/Year & Section',
        'age': 'Age',
        'sex': 'Sex',
        'weight': 'Weight',
        'birthdate': 'Birthday',
        'blood_pressure': 'Blood Pressure',
        'temperature': 'Temperature',
        'heart_rate': 'Heart Rate',
        'oxygen_saturation': 'O2 Saturation',
        'complaints': 'Complaints',
        'diagnosis': 'Diagnosis',
        'treatment': 'Treatment',
        'prescription-text': 'Prescription Text'
    };

    const missingFields = [];
    for (const [id, label] of Object.entries(requiredFields)) {
        const el = document.getElementById(id);
        if (!el || !el.value.trim()) {
            missingFields.push(label);
        }
    }

    if (missingFields.length > 0) {
        const msg = `<ul style="text-align:left">${missingFields.map(f => `<li>${f}</li>`).join('')}</ul>`;
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'Missing Required Fields',
                html: msg,
                confirmButtonColor: '#a6192e'
            });
        } else {
            alert(`Missing fields:\n${missingFields.join(', ')}`);
        }
        return; // stop here
    }

    // Validate signature (optional if you want separate check)
    const signatureEmpty = canvas.toDataURL() === document.createElement('canvas').toDataURL();
    if (signatureEmpty) {
        alert('Please provide a signature before submitting the consultation form.');
        return;
    }

    
    // show loading swal
    Swal.fire({
        title: 'Submitting...',
        text: 'Please wait while we process the form.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    updateSignature();

    const prescriptionText = document.getElementById('prescription-text').value;
    const medications = prescriptionText.split('\n')
        .filter(line => line.trim() !== '')
        .map(line => line.trim());

    document.getElementById('consultation-prescription-text').value = JSON.stringify({
        medications: medications
    });

    // finally submit
    this.submit();
});

        </script>
    </div>
</body>

</html>