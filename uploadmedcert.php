<?php
// Include database connection
require_once '../config/db_connect.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    try {
        $request_type = filter_input(INPUT_POST, 'request-type', FILTER_SANITIZE_STRING);
        $reason = filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_STRING);
        $competition_scope = filter_input(INPUT_POST, 'competition-scope', FILTER_SANITIZE_STRING) ?: null;

        // Validate inputs
        if (empty($request_type) || empty($reason)) {
            throw new Exception("All required fields must be filled.");
        }

        // Handle file upload
        $xray_file_path = null;
        if (!empty($_FILES['xray-result']['name'])) {
            $allowed_types = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            $max_size = 5 * 1024 * 1024; // 5MB
            $file_type = $_FILES['xray-result']['type'];
            $file_size = $_FILES['xray-result']['size'];
            $file_tmp = $_FILES['xray-result']['tmp_name'];
            $file_ext = strtolower(pathinfo($_FILES['xray-result']['name'], PATHINFO_EXTENSION));
            $file_name = uniqid() . '.' . $file_ext;
            $upload_dir = '../Uploads/medical_documents/';
            $upload_path = $upload_dir . $file_name;

            if (!in_array($file_type, $allowed_types) || $file_size > $max_size) {
                throw new Exception("Invalid file type or size. Only PDF/DOCX files up to 5MB are allowed.");
            }

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            if (!move_uploaded_file($file_tmp, $upload_path)) {
                throw new Exception("Failed to upload file.");
            }

            $xray_file_path = $upload_path;
        }

        // Insert request into database (without user_id)
        $stmt = $pdo->prepare("
            INSERT INTO medical_document_requests (request_type, reason, competition_scope, xray_file_path)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$request_type, $reason, $competition_scope, $xray_file_path]);

        $success_message = "Medical certificate request submitted successfully!";
    } catch (Exception $e) {
        $error_message = "Error submitting request: " . $e->getMessage();
    }
}

// Fetch CMS content for form
try {
    $labels = [
        'request_type' => 'Request Type',
        'reason' => 'Reason for Medical Certificate',
        'competition_scope' => 'Competition Scope',
        'xray' => 'Upload Chest X-Ray Result (PDF/DOCX)'
    ];
    $ojt_reasons = [];
    $non_ojt_reasons = [];
    $competition_scopes = [];

    // Fetch labels
    $stmt = $pdo->query("SELECT key_name, value FROM medical_document_cms WHERE section = 'labels'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $labels[$row['key_name']] = $row['value'];
    }

    // Fetch OJT reasons
    $stmt = $pdo->query("SELECT value FROM medical_document_cms WHERE section = 'ojt_reasons'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $ojt_reasons[] = json_decode($row['value'], true);
    }

    // Fetch Non-OJT reasons
    $stmt = $pdo->query("SELECT value FROM medical_document_cms WHERE section = 'non_ojt_reasons'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $non_ojt_reasons[] = json_decode($row['value'], true);
    }

    // Fetch competition scopes
    $stmt = $pdo->query("SELECT value FROM medical_document_cms WHERE section = 'competition_scopes'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $competition_scopes[] = json_decode($row['value'], true);
    }
} catch (PDOException $e) {
    $error_message = "Error fetching CMS content: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Documents - WMSU Health Services</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/uploadmedcert.css">
</head>
<body>
    <div id="app">
        <button id="sidebar-toggle" class="sidebar-toggle d-lg-none">☰</button>
        <div id="sidebar" class="sidebar d-flex flex-column align-items-center">
            <img src="../images/clinic.png" alt="WMSU Clinic Logo" class="logo">
            <div class="text-white fw-bold text-uppercase text-center mt-2">WMSU</div>
            <div class="health-services text-white fw-bold text-uppercase text-center mt-2 mb-5">Health Services</div>
            <button class="btn btn-crimson mb-2 w-100" id="about-btn" onclick="window.location.href='homepage.php'">About Us</button>
            <button class="btn btn-crimson mb-2 w-100" id="announcement-btn" onclick="window.location.href='announcements.php'">Announcements</button>
            <button class="btn btn-crimson mb-2 w-100" id="appointment-btn" onclick="window.location.href='appointment.php'">Appointment Request</button>
            <button class="btn btn-crimson mb-2 w-100 active" id="upload-btn" onclick="window.location.href='uploadmedcert.php'">Upload Medical Documents</button>
            <button class="btn btn-crimson mb-2 w-100" id="profile-btn" onclick="window.location.href='profile.html'">Profile</button>
            <button class="btn btn-crimson w-100" id="logout-btn" onclick="window.location.href='logout.html'">Logout</button>
            <a href="https://wmsu.edu.ph" target="_blank" class="quick-link">wmsu.edu.ph</a>
        </div>

        <div class="main-content">
            <div class="content-dim"></div>
            <div class="content-wrapper">
                <div class="container">
                    <h1 class="title text-center mb-4">Medical Certificate Request</h1>
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($success_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php elseif (isset($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($error_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    <div class="mb-4">
                        <form action="" method="post" enctype="multipart/form-data" id="med-cert-form">
                            <div class="mb-3">
                                <h3 class="section-title"><?php echo htmlspecialchars($labels['request_type']); ?></h3>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="request-type" id="ojt-internship" value="ojt-internship" required>
                                    <label class="form-check-label" for="ojt-internship">OJT/Internship</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="request-type" id="non-ojt" value="non-ojt" required checked>
                                    <label class="form-check-label" for="non-ojt">Non-OJT/Internship</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="reason" class="form-label"><?php echo htmlspecialchars($labels['reason']); ?></label>
                                <select class="form-select" id="reason" name="reason" required>
                                    <option value="" disabled selected>Select a reason</option>
                                </select>
                            </div>
                            <div class="mb-3 competition-scope" style="display: none;">
                                <label for="competition-scope" class="form-label"><?php echo htmlspecialchars($labels['competition_scope']); ?></label>
                                <select class="form-select" id="competition-scope" name="competition-scope">
                                    <option value="" disabled selected>Select scope</option>
                                    <?php foreach ($competition_scopes as $scope): ?>
                                        <option value="<?php echo htmlspecialchars($scope['value']); ?>">
                                            <?php echo htmlspecialchars($scope['text']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3 xray-upload" style="display: none;">
                                <label for="xray-result" class="form-label"><?php echo htmlspecialchars($labels['xray']); ?></label>
                                <input type="file" class="form-control" id="xray-result" name="xray-result" accept=".pdf,.docx">
                            </div>
                            <button type="submit" name="submit_request" class="btn btn-crimson">Submit Request</button>
                        </form>
                    </div>
                </div>

                <div class="faq-section">
                    <h2 class="faq-title text-center">Frequently Asked Questions (FAQ)</h2>
                    <div class="container mt-4">
                        <div class="list-group">
                            <button class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#faq1">How do we fill-up the forms and annotate our signatures electronically?</button>
                            <button class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#faq2">What if I don't have a laptop or a phone with internet access?</button>
                            <button class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#faq3">May we avail old chest-x ray and/or blood typing results?</button>
                            <button class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#faq4">May we submit a medical certificate from another physician?</button>
                            <button class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#faq5">How long do I have to wait before the release of my medical certificate?</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FAQ Modals -->
    <div class="modal fade" id="faq1" tabindex="-1" aria-labelledby="faq1Label" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="faq1Label">How do we fill-up the forms and annotate our signatures electronically?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Using your laptop/computer, tablet, or cellphone, you may open and edit the forms using any PDF reader and editor (e.g. Adobe Acrobat, Foxit, Xodo, Microsoft Edge). To annotate your electronic signatures, you may insert an image of your signature or use the "draw" tool.
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="faq2" tabindex="-1" aria-labelledby="faq2Label" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="faq2Label">What if I don't have a laptop or a phone with internet access?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    You may visit the College of Engineering Computer Laboratory (Campus A) to accomplish the electronic forms and stop by the Health Services Center to physically submit your chest x-ray and laboratory test results.
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="faq3" tabindex="-1" aria-labelledby="faq3Label" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="faq3Label">May we avail old chest-x ray and/or blood typing results?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Yes. You may submit old chest x-ray or laboratory results from any DOH-accredited facility provided that they were done during the past 3 months.
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="faq4" tabindex="-1" aria-labelledby="faq4Label" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="faq4Label">May we submit a medical certificate from another physician?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Yes. We offer a free medical certificate for all incoming freshmen to minimize the students' enrollment-related expenses. However, you are allowed to avail services from a physician of your choice, provided that you submit a copy of the medical certificate to the University Health Services Center. Note that you may still be required to fill-up the "Patient Health Profile & Consultations Record" and the "Waiver for Collection of Personal and Sensitive Health Information" upon your first consultation at the university clinic.
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="faq5" tabindex="-1" aria-labelledby="faq5Label" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="faq5Label">How long do I have to wait before the release of my medical certificate?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Given the number of the university's incoming freshmen, please allow the Health Services Center 1-3 working days to process your request for a medical certificate.
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Sidebar toggle functionality
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            const contentDim = document.querySelector('.content-dim');

            sidebarToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                sidebar.classList.toggle('open');
                mainContent.classList.toggle('sidebar-open');
            });

            contentDim.addEventListener('click', function() {
                sidebar.classList.remove('open');
                mainContent.classList.remove('sidebar-open');
            });

            document.addEventListener('click', function(event) {
                if (window.innerWidth <= 992) {
                    const isClickInsideSidebar = sidebar.contains(event.target);
                    const isClickOnToggle = event.target === sidebarToggle;
                    if (!isClickInsideSidebar && !isClickOnToggle && sidebar.classList.contains('open')) {
                        sidebar.classList.remove('open');
                        mainContent.classList.remove('sidebar-open');
                    }
                }
            });

            // Handle form logic
            const ojtRadio = document.getElementById('ojt-internship');
            const nonOjtRadio = document.getElementById('non-ojt');
            const reasonSelect = document.getElementById('reason');
            const competitionScopeDiv = document.querySelector('.competition-scope');
            const competitionScopeSelect = document.getElementById('competition-scope');
            const xrayUpload = document.querySelector('.xray-upload');

            // Define dropdown options from PHP
            const ojtReasons = <?php echo json_encode($ojt_reasons); ?>;
            const nonOjtReasons = <?php echo json_encode($non_ojt_reasons); ?>;

            function updateReasonDropdown(isOjt) {
                const reasons = isOjt ? ojtReasons : nonOjtReasons;
                reasonSelect.innerHTML = '<option value="" disabled selected>Select a reason</option>';
                reasons.forEach(reason => {
                    const option = document.createElement('option');
                    option.value = reason.value;
                    option.text = reason.text;
                    reasonSelect.appendChild(option);
                });
            }

            function toggleCompetitionScope() {
                const isCompetition = reasonSelect.value === 'school-competition';
                competitionScopeDiv.style.display = isCompetition ? 'block' : 'none';
                competitionScopeSelect.required = isCompetition;
                if (!isCompetition) {
                    competitionScopeSelect.value = '';
                }
            }

            function toggleXrayUpload() {
                const isOjt = ojtRadio.checked;
                const reason = reasonSelect.value;
                const scope = competitionScopeSelect.value;
                const requiresXray = isOjt || 
                                    reason === 'travel-national' || 
                                    reason === 'travel-international' || 
                                    (reason === 'school-competition' && (scope === 'regional' || scope === 'national' || scope === 'international'));
                xrayUpload.style.display = requiresXray ? 'block' : 'none';
                xrayUpload.querySelector('#xray-result').required = requiresXray;
            }

            ojtRadio.addEventListener('change', () => {
                updateReasonDropdown(true);
                toggleCompetitionScope();
                toggleXrayUpload();
            });

            nonOjtRadio.addEventListener('change', () => {
                updateReasonDropdown(false);
                toggleCompetitionScope();
                toggleXrayUpload();
            });

            reasonSelect.addEventListener('change', () => {
                toggleCompetitionScope();
                toggleXrayUpload();
            });

            competitionScopeSelect.addEventListener('change', toggleXrayUpload);

            // Initialize form state
            updateReasonDropdown(false); // Default to Non-OJT/Internship
            toggleCompetitionScope();
            toggleXrayUpload();
        });
    </script>
</body>
</html>