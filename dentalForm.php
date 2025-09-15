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

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("Unauthorized access to adminhome.php: No user_id in session, redirecting to /login");
    header("Location: /login.php");
    exit();
}


// Check connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    $_SESSION['error'] = "Database error. Please try again.";
    header("Location: login.php");
    exit();
}

// Verify user is an admin
$userId = (int)$_SESSION['user_id'];
$query = "SELECT user_type FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    error_log("Admin verification query prepare failed: " . $conn->error);
    $_SESSION['error'] = "Database error. Please try again.";
    header("Location: login.php");
    exit();
}
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || !in_array(trim($user['user_type']), ['Super Admin', 'Medical Admin', 'Dental Admin'], true)) {
    error_log("Non-admin user_id: $userId, user_type: " . ($user['user_type'] ?? 'none') . " attempted access to adminhome.php, redirecting to /homepage");
    header("Location: homepage.php");
    exit();
}
error_log("Admin user_id: $userId, user_type: {$user['user_type']} accessed adminhome.php");

$uid = (int)$_SESSION['user_id'];

// Get appointment_id and patient_id from URL
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;
$patient_id     = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;



if ($appointment_id > 0 && $patient_id > 0) {

    // Check if consultation already exists
    $checkSql = "SELECT appointment_id FROM consultations WHERE appointment_id = $appointment_id LIMIT 1";
    $checkResult = mysqli_query($conn, $checkSql);

    if (mysqli_num_rows($checkResult) > 0) {
        // Exists → update only updated_at
        $updateSql = "UPDATE consultations SET updated_at = NOW() WHERE appointment_id = $appointment_id";
        mysqli_query($conn, $updateSql);
    } else {
        // Check if patient exists
        $checkUserSql = "SELECT id FROM patients WHERE user_id = $patient_id LIMIT 1";
        $userResult = mysqli_query($conn, $checkUserSql);

        if (mysqli_num_rows($userResult) > 0) {
            // Insert new consultation
            $insertSql = "INSERT INTO consultations 
            (appointment_id, patient_id, child_id, staff_id, consultation_date, consultation_time, type, created_at, updated_at, status) 
            VALUES 
            ($appointment_id, $patient_id, NULL, $uid, CURDATE(), CURTIME(), 'Medical', NOW(), NOW(), 'pending')";
            mysqli_query($conn, $insertSql);
        } else {
            echo "Error: Patient ID $patient_id not found in patients table.";
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dental Form</title>
    <link rel="stylesheet" href="css/dentalForm.css">
    <link rel="apple-touch-icon" sizes="180x180" href="images/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="images/favicon-16x16.png">
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
</head>

<body>
    <div class="form-container">
        <h1>Teeth Form - Part 1</h1>
        <a href="dental-appointments.php" class="back-link">Back to Appointments</a>
        <form id="dentalForm" onsubmit="return validateForm(event)">
            <!-- Personal Information Section -->
            <div class="section">
                <h2>Personal Information</h2>
                <div class="form-group">
                    <label for="fileNo">File No.</label>
                    <input type="text" id="fileNo" name="fileNo" required>
                </div>

                <div class="form-group name-group">
                    <div class="name-field">
                        <label for="surname">Surname</label>
                        <input type="text" id="surname" name="surname" required>
                    </div>
                    <div class="name-field">
                        <label for="firstname">First name</label>
                        <input type="text" id="firstname" name="firstname" required>
                    </div>
                    <div class="name-field">
                        <label for="middlename">Middle name</label>
                        <input type="text" id="middlename" name="middlename">
                    </div>
                </div>

                <div class="input-row">
                    <div class="form-group">
                        <label for="gradeSection">Course Year Level / Grade/Year & Section</label>
                        <input type="text" id="gradeSection" name="gradeSection" required>
                    </div>

                    <div class="form-group">
                        <label for="age">Age</label>
                        <input type="number" id="age" name="age" required>
                    </div>
                </div>

                <div class="form-group row-group">
                    <div class="sex-group">
                        <label>Sex</label>
                        <div class="radio-group">
                            <label><input type="radio" name="sex" value="male" required> Male</label>
                            <label><input type="radio" name="sex" value="female"> Female</label>
                        </div>
                    </div>
                    <div class="toothbrush-group">
                        <label>Have Own Toothbrush?</label>
                        <div class="radio-group">
                            <label><input type="radio" name="toothbrush" value="yes" required> Yes</label>
                            <label><input type="radio" name="toothbrush" value="no"> No</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Permanent Teeth (Dentition Status and Treatment Needs) Section -->
            <div class="section">
                <h2>Permanent Teeth (Dentition Status and Treatment Needs)</h2>
                <div class="teeth-chart">
                    <!-- Upper Right Quadrant -->
                    <div class="quadrant">
                        <h3>Upper Right Quadrant (18-11)</h3>
                        <div class="teeth-row">
                            <div class="tooth" data-tooth="18">18</div>
                            <div class="tooth" data-tooth="17">17</div>
                            <div class="tooth" data-tooth="16">16</div>
                            <div class="tooth" data-tooth="15">15</div>
                            <div class="tooth" data-tooth="14">14</div>
                            <div class="tooth" data-tooth="13">13</div>
                            <div class="tooth" data-tooth="12">12</div>
                            <div class="tooth" data-tooth="11">11</div>
                        </div>
                    </div>

                    <!-- Upper Left Quadrant -->
                    <div class="quadrant">
                        <h3>Upper Left Quadrant (21-28)</h3>
                        <div class="teeth-row">
                            <div class="tooth" data-tooth="21">21</div>
                            <div class="tooth" data-tooth="22">22</div>
                            <div class="tooth" data-tooth="23">23</div>
                            <div class="tooth" data-tooth="24">24</div>
                            <div class="tooth" data-tooth="25">25</div>
                            <div class="tooth" data-tooth="26">26</div>
                            <div class="tooth" data-tooth="27">27</div>
                            <div class="tooth" data-tooth="28">28</div>
                        </div>
                    </div>

                    <!-- Lower Left Quadrant -->
                    <div class="quadrant">
                        <h3>Lower Left Quadrant (38-31)</h3>
                        <div class="teeth-row">
                            <div class="tooth" data-tooth="38">38</div>
                            <div class="tooth" data-tooth="37">37</div>
                            <div class="tooth" data-tooth="36">36</div>
                            <div class="tooth" data-tooth="35">35</div>
                            <div class="tooth" data-tooth="34">34</div>
                            <div class="tooth" data-tooth="33">33</div>
                            <div class="tooth" data-tooth="32">32</div>
                            <div class="tooth" data-tooth="31">31</div>
                        </div>
                    </div>

                    <!-- Lower Right Quadrant -->
                    <div class="quadrant">
                        <h3>Lower Right Quadrant (48-41)</h3>
                        <div class="teeth-row">
                            <div class="tooth" data-tooth="48">48</div>
                            <div class="tooth" data-tooth="47">47</div>
                            <div class="tooth" data-tooth="46">46</div>
                            <div class="tooth" data-tooth="45">45</div>
                            <div class="tooth" data-tooth="44">44</div>
                            <div class="tooth" data-tooth="43">43</div>
                            <div class="tooth" data-tooth="42">42</div>
                            <div class="tooth" data-tooth="41">41</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Temporary Teeth Section -->
            <div class="section">
                <h2>Temporary Teeth (Dentition Status and Treatment Needs)</h2>
                <div class="teeth-chart">
                    <!-- Upper Right Quadrant (55-51) -->
                    <div class="quadrant">
                        <h3>Upper Right Quadrant (55-51)</h3>
                        <div class="teeth-row">
                            <div class="tooth" data-tooth="55">55</div>
                            <div class="tooth" data-tooth="54">54</div>
                            <div class="tooth" data-tooth="53">53</div>
                            <div class="tooth" data-tooth="52">52</div>
                            <div class="tooth" data-tooth="51">51</div>
                        </div>
                    </div>

                    <!-- Upper Left Quadrant (61-65) -->
                    <div class="quadrant">
                        <h3>Upper Left Quadrant (61-65)</h3>
                        <div class="teeth-row">
                            <div class="tooth" data-tooth="61">61</div>
                            <div class="tooth" data-tooth="62">62</div>
                            <div class="tooth" data-tooth="63">63</div>
                            <div class="tooth" data-tooth="64">64</div>
                            <div class="tooth" data-tooth="65">65</div>
                        </div>
                    </div>

                    <!-- Lower Left Quadrant (85-81) -->
                    <div class="quadrant">
                        <h3>Lower Left Quadrant (85-81)</h3>
                        <div class="teeth-row">
                            <div class="tooth" data-tooth="85">85</div>
                            <div class="tooth" data-tooth="84">84</div>
                            <div class="tooth" data-tooth="83">83</div>
                            <div class="tooth" data-tooth="82">82</div>
                            <div class="tooth" data-tooth="81">81</div>
                        </div>
                    </div>

                    <!-- Lower Right Quadrant (71-75) -->
                    <div class="quadrant">
                        <h3>Lower Right Quadrant (71-75)</h3>
                        <div class="teeth-row">
                            <div class="tooth" data-tooth="71">71</div>
                            <div class="tooth" data-tooth="72">72</div>
                            <div class="tooth" data-tooth="73">73</div>
                            <div class="tooth" data-tooth="74">74</div>
                            <div class="tooth" data-tooth="75">75</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary of Status of Oral Health Section
            <div class="section summary-section">
                <h2>Summary of Status of Oral Health</h2>
                <div class="form-group">
                    <label>Dentition:</label>
                    <div class="radio-group">
                        <label><input type="radio" name="dentition" value="satisfactory"> Satisfactory</label>
                        <label><input type="radio" name="dentition" value="fair"> Fair</label>
                        <label><input type="radio" name="dentition" value="poor"> Poor</label>
                    </div>
                </div>
                <div class="form-group">
                    <label>Periodontal:</label>
                    <div class="radio-group">
                        <label><input type="radio" name="periodontal" value="satisfactory"> Satisfactory</label>
                        <label><input type="radio" name="periodontal" value="fair"> Fair</label>
                        <label><input type="radio" name="periodontal" value="poor"> Poor</label>
                    </div>
                </div>
                <div class="form-group">
                    <label>Occlusion:</label>
                    <div class="radio-group">
                        <label><input type="radio" name="occlusion" value="normal"> Normal</label>
                        <label><input type="radio" name="occlusion" value="malocclusion"> Malocclusion</label>
                    </div>
                </div>
                <div class="form-group">
                    <label>Malocclusion Severity:</label>
                    <div class="radio-group">
                        <label><input type="radio" name="malocclusion" value="mild"> Mild</label>
                        <label><input type="radio" name="malocclusion" value="moderate"> Moderate</label>
                        <label><input type="radio" name="malocclusion" value="severe"> Severe</label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="remarks">Remarks:</label>
                    <textarea id="remarks" name="remarks" rows="4"></textarea>
                </div>
            </div> -->

            <!-- Examined By and Date
            <div class="examined-row">
                <div class="form-group">
                    <label for="examinedBy">Examined By</label>
                    <input type="text" id="examinedBy" name="examinedBy" required>
                </div>
                <div class="form-group">
                    <label for="examinedDate">Date</label>
                    <input type="date" id="examinedDate" name="examinedDate" required>
                </div>
            </div> -->

            <div class="button-container">
                <button type="button" class="nav-button" onclick="saveAndContinue()">Next</button>
            </div>
        </form>
    </div>

    <!-- Modal -->
    <div id="toothModal" class="modal">
        <div class="modal-content">
            <span class="close">×</span>
            <h2>Tooth <span id="modalToothNumber"></span></h2>
            <div class="form-group">
                <label for="modalTreatment">Treatment</label>
                <input type="text" id="modalTreatment">
            </div>
            <div class="form-group">
                <label for="modalStatus">Status</label>
                <input type="text" id="modalStatus">
            </div>
            <button type="button" onclick="saveToothInfo()">Save</button>
        </div>
    </div>

    <script src="js/dentalForm.js"></script>

</body>

</html>