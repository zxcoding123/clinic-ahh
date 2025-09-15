<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

// Get appointment_id and patient_id from URL
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

if ($appointment_id <= 0 || $patient_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid appointment or patient ID']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dental Form - Part 2</title>
    <link rel="stylesheet" href="css/dentalForm2.css">
      <link rel="apple-touch-icon" sizes="180x180" href="images/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="images/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="images/favicon-16x16.png">
</head>
<body>
    <div class="form-container">
        <h1>Teeth Form - Part 2</h1>
      <form id="dentalFormPart2" action="submit_dental_form.php?appointment_id=<?php echo $appointment_id; ?>&patient_id=<?php echo $patient_id; ?>" method="POST">
            <!-- Permanent Teeth Section -->
            <div class="section">
                <h2>PERMANENT TEETH</h2>               
                <button type="button" class="add-row-button" onclick="addPermanentRow()">Add Visit</button>
                
                <div class="table-wrapper">
                    <table id="permanentTeethTable">
                        <thead>
                            <tr>
                                <th>Date of Visits</th>
                                <th>No. T/Decayed</th>
                                <th>No. T/Missing</th>
                                <th>No. T/Filled</th>
                                <th>Total D.M.F.</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><input type="date" name="permanentVisitDate[]" required></td>
                                <td><input type="number" name="permanentDecayed[]" min="0" class="calc-permanent" required></td>
                                <td><input type="number" name="permanentMissing[]" min="0" class="calc-permanent" required></td>
                                <td><input type="number" name="permanentFilled[]" min="0" class="calc-permanent" required></td>
                                <td><input type="number" name="permanentTotal[]" min="0" readonly></td>
                                <td><button type="button" class="btn-delete" onclick="deleteRow(this)">✕</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Temporary Teeth Section -->
            <div class="section">
                <h2>TEMPORARY TEETH</h2>
                <button type="button" class="add-row-button" onclick="addTemporaryRow()">Add Visit</button>

                <div class="table-wrapper">
                    <table id="temporaryTeethTable">
                        <thead>
                            <tr>
                                <th>Date of Visits</th>
                                <th>No. t/decayed</th>
                                <th>No. t/filled</th>
                                <th>Total d.f.t.</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><input type="date" name="temporaryVisitDate[]" required></td>
                                <td><input type="number" name="temporaryDecayed[]" min="0" class="calc-temporary" required></td>
                                <td><input type="number" name="temporaryFilled[]" min="0" class="calc-temporary" required></td>
                                <td><input type="number" name="temporaryTotal[]" min="0" readonly></td>
                                <td><button type="button" class="btn-delete" onclick="deleteRow(this)">✕</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Dental Treatment Record Section -->
            <div class="section">
                <h2>Dental Treatment Record</h2>
                <button type="button" class="add-row-button" onclick="addTreatmentRow()">Add Treatment</button>

                <div class="table-wrapper">
                    <table id="treatmentRecordTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Tooth No.</th>
                                <th>Nature of Operation</th>
                                <th>Dentist</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><input type="date" name="treatmentDate[]" required></td>
                                <td><input type="text" name="toothNumber[]" required></td>
                                <td><input type="text" name="natureOfOperation[]" required></td>
                                <td><input type="text" name="dentistName[]" required></td>
                                <td><button type="button" class="btn-delete" onclick="deleteRow(this)">✕</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Summary of Status of Oral Health Section -->
            <div class="section">
                <h2>Summary of Status of Oral Health</h2>
                <div class="form-group">
                    <label>Dentition:</label>
                    <div class="radio-group">
                        <label><input type="radio" name="dentition" value="satisfactory" required> Satisfactory</label>
                        <label><input type="radio" name="dentition" value="fair"> Fair</label>
                        <label><input type="radio" name="dentition" value="poor"> Poor</label>
                    </div>
                </div>
                <div class="form-group">
                    <label>Periodontal:</label>
                    <div class="radio-group">
                        <label><input type="radio" name="periodontal" value="satisfactory" required> Satisfactory</label>
                        <label><input type="radio" name="periodontal" value="fair"> Fair</label>
                        <label><input type="radio" name="periodontal" value="poor"> Poor</label>
                    </div>
                </div>
                <div class="form-group">
                    <label>Occlusion:</label>
                    <div class="radio-group">
                        <label><input type="radio" name="occlusion" value="normal" required> Normal</label>
                        <label><input type="radio" name="occlusion" value="malocclusion"> Malocclusion</label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="remarks">Remarks:</label>
                    <textarea id="remarks" name="remarks" rows="4"></textarea>
                </div>
            </div>

            <!-- Examined By and Date -->
            <div class="examined-row">
                <div class="form-group">
                    <label for="examinedBy">Examined By</label>
                    <input type="text" id="examinedBy" name="examinedBy" required>
                </div>
                <div class="form-group">
                    <label for="examinedDate">Date</label>
                    <input type="date" id="examinedDate" name="examinedDate" required>
                </div>
            </div>

            <div class="button-container">
                <button type="button" class="nav-button btn-back" onclick="goBackToPart1()">Back</button>
                <div>
                    <button type="submit" class="nav-button btn-submit">Submit</button>
                    <button type="button" class="nav-button btn-print" onclick="printCombinedForms()">Print Form</button>
                </div>
            </div>
        </form>
    </div>
        
    <script src="js/dentalForm2.js"></script>      
</body>
</html>