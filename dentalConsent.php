<?php
session_start();
require 'config.php';

?>
<!DOCTYPE html>
<html lang="en">

<head>

<script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dental Consent Form</title>
    <style>
        :root {
            --primary-color: #007bff;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --text-color: #333;
            --light-gray: #f9f9f9;
            --border-color: #ddd;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 15px;
            color: var(--text-color);
            background-color: #f5f5f5;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid var(--border-color);
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            background-color: white;
            border-radius: 5px;
        }

        /* Header Styles */
        .header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            gap: 15px;
        }

        .logo {
            height: 70px;
            width: auto;
            max-width: 120px;
            object-fit: contain;
        }

        .header-text {
            text-align: center;
            flex: 1;
            min-width: 250px;
        }

        .university-name {
            font-weight: bold;
            font-size: clamp(16px, 2vw, 18px);
            margin-bottom: 5px;
        }

        .health-center {
            font-size: clamp(14px, 1.8vw, 16px);
            margin-bottom: 5px;
        }

        .contact-info {
            font-size: clamp(12px, 1.6vw, 14px);
            color: #555;
        }

        /* Form Content Styles */
        .form-title {
            text-align: center;
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 25px;
            font-size: clamp(16px, 2vw, 18px);
        }

        .consent-text {
            margin-bottom: 25px;
            font-size: clamp(14px, 1.8vw, 16px);
        }

        .blank-field {
            display: inline-block;
            min-width: 150px;
            width: 100%;
            max-width: 250px;
            margin: 0 5px;
            text-align: center;
            position: relative;
            border-bottom: 1px solid var(--text-color);
            /* Add this line */
            padding-bottom: 2px;
            /* Add this line */
        }

        /*.blank-field:after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 1px;
            background-color: var(--text-color);
        } */

        .editable {
            cursor: text;
            outline: none;
            min-height: 20px;
            display: inline-block;
        }

        /* Signature Section */
        .signature-section {
            margin-top: 40px;
        }

        .signature-line {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 30px;
        }

        .signature-box {
            flex: 1;
            min-width: 200px;
            padding-top: 5px;
            text-align: center;
        }

        .signature-label {
            margin-bottom: 5px;
            font-size: clamp(14px, 1.8vw, 16px);
        }

        .printed-name {
            margin-bottom: 8px;
            font-weight: bold;
            font-size: clamp(14px, 1.8vw, 16px);
        }

        .date-field {
            margin-top: 5px;
            font-size: clamp(13px, 1.6vw, 14px);
        }

        .signature-pad {
            width: 100%;
            height: 80px;
            border: 1px solid #ccc;
            margin-bottom: 10px;
            cursor: crosshair;
            background-color: var(--light-gray);
            touch-action: none;
        }

        .signature-line-container {
            border-top: 1px solid var(--text-color);
            padding-top: 15px;
        }

        /* Buttons */
        .button-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 30px;
            gap: 15px;
        }

        button {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: clamp(14px, 1.8vw, 16px);
            transition: opacity 0.3s;
            min-width: 120px;
        }

        .submit-btn {
            background-color: var(--primary-color);
            color: white;
        }

        .print-btn {
            background-color: var(--success-color);
            color: white;
        }

        .clear-btn {
            background-color: var(--danger-color);
            color: white;
            font-size: clamp(12px, 1.6vw, 14px);
            padding: 5px 10px;
        }

        button:hover {
            opacity: 0.9;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }

            .logo {
                max-width: 100px;
                height: auto;
            }

            .blank-field {
                min-width: 120px;
                max-width: 200px;
            }

            .signature-box {
                min-width: 150px;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 15px;
            }

            .blank-field {
                min-width: 100px;
                max-width: 150px;
                margin: 0 3px;
            }

            .signature-line {
                gap: 15px;
            }

            .signature-box {
                min-width: 100%;
            }

            button {
                width: 100%;
            }
        }

        @media print {
            .new {
                display: flex;
                flex-direction: row;
                justify-content: space-between;
            }

            body {
                background-color: white !important;
                padding: 0;
            }

            .container {
                border: none;
                box-shadow: none;
                padding: 0;
            }

            button {
                display: none;
            }

            .signature-pad {
                border: 1px solid #000 !important;
                background-color: white !important;
            }

            /* Keep blank fields visible and well-defined in print */
            .blank-field {
                border-bottom: 1px solid #000 !important;
                background: none !important;
                display: inline-block;
                min-width: 150px;
                padding-bottom: 2px;
            }

            .blank-field.editable {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            @page {
                margin: 1cm;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header new">
            <img src="images/clinic.png" alt="WMSU Logo" class="logo">
            <div class="header-text">
                <div class="university-name">WESTERN MINDANAO STATE UNIVERSITY</div>
                <div class="health-center">Zamboanga City</div>
                <div class="health-center">UNIVERSITY HEALTH SERVICES CENTER</div>
                <div class="contact-info">Tel. no. (062) 991-6736 / Email: healthservices@wmsu.edu.ph</div>
            </div>
            <img src="images/logo.png" alt="Health Center Logo" class="logo">
        </div>

        <div class="form-title">INFORMED CONSENT TO CARE</div>

        <div class="consent-text">
            <p>
                I, <span class="blank-field editable" id="parentName" contenteditable="true"></span>, parent/guardian of
                <span class="blank-field editable" id="childName" contenteditable="true"></span>, residing from
                <span class="blank-field editable" id="residence" contenteditable="true"></span>, of legal age, hereby authorized the Staff of this Health Unit to perform
                <span class="blank-field editable" id="procedure" contenteditable="true"></span> and other necessary procedures for the treatment of such dental condition.
            </p>

            <p>
                I have been explained the nature of the dental condition, the procedures, the mode of treatment, possible effects of services that may incur during treatment.
            </p>

            <p>
                I shall not hold the University Health Services Center nor its staff from any liability for such procedures or treatment.
            </p>

            <p>
                IN WITNESS THEREOF, I voluntarily sign this consent slip.
            </p>
        </div>

        <div class="signature-section">
            <div class="signature-line-container">
                <div class="signature-line">
                    <div class="signature-box">
                        <div class="signature-label">
                            <small>

                                Printed Name and Signature of Witness
                            </small>
                        </div>
                        <div class="printed-name">
                            <span class="blank-field editable" id="witnessPrintedName" contenteditable="true">Printed Name</span>
                        </div>
                        <canvas id="witnessSignature" class="signature-pad"></canvas>
                        <div class="date-field">
                            Date: <span class="editable" id="witnessDate" contenteditable="true"></span>
                        </div>
                        <button class="clear-btn" onclick="clearSignature('witnessSignature')">Clear Signature</button>
                    </div>
                    <div class="signature-box">
                        <div class="signature-label"><small> Printed Name and Signature of Parent/Guardian</small></div>
                        <div class="printed-name">
                            <span class="blank-field editable" id="parentPrintedName" contenteditable="true">Printed Name</span>
                        </div>
                        <canvas id="parentSignature" class="signature-pad"></canvas>
                        <div class="date-field">
                            Date: <span class="editable" id="parentDate" contenteditable="true"></span>
                        </div>
                        <button class="clear-btn" onclick="clearSignature('parentSignature')">Clear Signature</button>
                    </div>
                </div>
                <div class="signature-box" style="margin-left: auto; margin-right: auto; max-width: 200px;">
                    <div class="signature-label"><small>Printed Name and Signature of Patient</small> </div>
                    <div class="printed-name">
                        <span class="blank-field editable" id="patientPrintedName" contenteditable="true">Printed Name</span>
                    </div>
                    <canvas id="patientSignature" class="signature-pad"></canvas>
                    <div class="date-field">
                        Date: <span class="editable" id="patientDate" contenteditable="true"></span>
                    </div>
                    <button class="clear-btn" onclick="clearSignature('patientSignature')">Clear Signature</button>
                </div>
            </div>
        </div>

        <div class="button-container">
            <button class="print-btn" onclick="printForm()">Print Form</button>
            <button class="submit-btn" onclick="submitForm()">Submit</button>
        </div>
    </div>

    <script>
        // Initialize signature pads
        document.addEventListener('DOMContentLoaded', function() {
            initializeSignaturePad('witnessSignature');
            initializeSignaturePad('parentSignature');
            initializeSignaturePad('patientSignature');

            // Set default empty state for editable fields
            const editableFields = document.querySelectorAll('.editable');
            editableFields.forEach(field => {
                if (!field.textContent.trim() || field.textContent.trim() === "Printed Name") {
                    field.innerHTML = '&nbsp;'; // Add non-breaking space to maintain height
                }
            });

            // Set today's date as default
            const today = new Date();
            const formattedDate = today.toLocaleDateString('en-US', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit'
            });

            document.getElementById('witnessDate').textContent = formattedDate;
            document.getElementById('parentDate').textContent = formattedDate;
            document.getElementById('patientDate').textContent = formattedDate;
        });

        // Signature pad functionality with touch support
        function initializeSignaturePad(canvasId) {
            const canvas = document.getElementById(canvasId);
            const ctx = canvas.getContext('2d');
            let isDrawing = false;
            let lastX = 0;
            let lastY = 0;

            // Handle responsive canvas sizing
            function resizeCanvas() {
                const container = canvas.parentElement;
                canvas.width = container.offsetWidth;
                canvas.height = 80; // Fixed height for signature
                ctx.strokeStyle = '#000';
                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
            }

            // Initial resize
            resizeCanvas();

            // Handle window resize
            window.addEventListener('resize', function() {
                resizeCanvas();
                // Redraw signature if needed
            });

            // Event listeners for both mouse and touch
            canvas.addEventListener('mousedown', startDrawing);
            canvas.addEventListener('mousemove', draw);
            canvas.addEventListener('mouseup', stopDrawing);
            canvas.addEventListener('mouseout', stopDrawing);

            // Touch events
            canvas.addEventListener('touchstart', handleTouchStart, {
                passive: false
            });
            canvas.addEventListener('touchmove', handleTouchMove, {
                passive: false
            });
            canvas.addEventListener('touchend', stopDrawing);

            function handleTouchStart(e) {
                e.preventDefault();
                const touch = e.touches[0];
                const mouseEvent = new MouseEvent("mousedown", {
                    clientX: touch.clientX,
                    clientY: touch.clientY
                });
                canvas.dispatchEvent(mouseEvent);
            }

            function handleTouchMove(e) {
                e.preventDefault();
                const touch = e.touches[0];
                const mouseEvent = new MouseEvent("mousemove", {
                    clientX: touch.clientX,
                    clientY: touch.clientY
                });
                canvas.dispatchEvent(mouseEvent);
            }

            function startDrawing(e) {
                isDrawing = true;
                const pos = getPosition(e);
                lastX = pos.x;
                lastY = pos.y;
            }

            function draw(e) {
                if (!isDrawing) return;

                const pos = getPosition(e);
                ctx.beginPath();
                ctx.moveTo(lastX, lastY);
                ctx.lineTo(pos.x, pos.y);
                ctx.stroke();

                lastX = pos.x;
                lastY = pos.y;
            }

            function stopDrawing() {
                isDrawing = false;
            }

            function getPosition(e) {
                const rect = canvas.getBoundingClientRect();
                return {
                    x: (e.clientX || e.touches[0].clientX) - rect.left,
                    y: (e.clientY || e.touches[0].clientY) - rect.top
                };
            }
        }

        function clearSignature(canvasId) {
            const canvas = document.getElementById(canvasId);
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }

        // Function to handle form submission
        function submitForm() {
            // Get all the blank fields
            const parentName = document.getElementById('parentName').textContent.trim();
            const childName = document.getElementById('childName').textContent.trim();
            const residence = document.getElementById('residence').textContent.trim();
            const procedure = document.getElementById('procedure').textContent.trim();

            // Get printed names
            const witnessPrintedName = document.getElementById('witnessPrintedName').textContent.trim();
            const parentPrintedName = document.getElementById('parentPrintedName').textContent.trim();
            const patientPrintedName = document.getElementById('patientPrintedName').textContent.trim();

            // Get dates
            const witnessDate = document.getElementById('witnessDate').textContent.trim();
            const parentDate = document.getElementById('parentDate').textContent.trim();
            const patientDate = document.getElementById('patientDate').textContent.trim();

            // Check if signatures are provided
            const witnessSig = isCanvasBlank('witnessSignature');
            const parentSig = isCanvasBlank('parentSignature');
            const patientSig = isCanvasBlank('patientSignature');

            // Validation
            if (!parentName || !childName || !residence || !procedure) {
                swal("Missing Fields", "Please fill in all the blank fields before submitting.", "warning");
                return;
            }

            if (!witnessPrintedName || witnessPrintedName === 'Printed Name' ||
                !parentPrintedName || parentPrintedName === 'Printed Name' ||
                !patientPrintedName || patientPrintedName === 'Printed Name') {
                swal("Missing Printed Names", "Please provide all printed names before submitting.", "warning");
                return;
            }

            if (!witnessDate || !parentDate || !patientDate) {
                swal("Missing Dates", "Please provide all dates before submitting.", "warning");
                return;
            }

            if (witnessSig || parentSig || patientSig) {
                swal("Missing Signatures", "Please provide all required signatures before submitting.", "warning");
                return;
            }

            const formData = {
                parentName,
                childName,
                residence,
                procedure,
                witnessPrintedName,
                parentPrintedName,
                patientPrintedName,
                witnessDate,
                parentDate,
                patientDate,
                witnessSignature: document.getElementById('witnessSignature').toDataURL(),
                parentSignature: document.getElementById('parentSignature').toDataURL(),
                patientSignature: document.getElementById('patientSignature').toDataURL()
            };

            // Submit via fetch
            fetch('submit-consent.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData),
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        swal({
                            title: "Success!",
                            text: "Consent form submitted successfully.",
                            icon: "success",
                            button: "OK"
                        }).then(() => {
                            window.location.href = "dentalrequest.php";
                        });
                    } else {
                        swal("Error", data.message || "Error submitting form. Please try again.", "error");
                    }
                })
                .catch((error) => {
                    console.error('Error:', error);
                    swal("Error", "Error submitting form. Please try again.", "error");
                });
        }

        // Check if canvas is blank
        function isCanvasBlank(canvasId) {
            const canvas = document.getElementById(canvasId);
            const ctx = canvas.getContext('2d');

            const pixelBuffer = new Uint32Array(
                ctx.getImageData(0, 0, canvas.width, canvas.height).data.buffer
            );

            return !pixelBuffer.some(color => color !== 0);
        }

        // Function to handle printing
        function printForm() {
            window.print();
        }
    </script>
</body>

</html>