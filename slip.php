<?php
session_start();
require 'config.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Medical Certificate Request - WMSU Health Services</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/slip.css">
  <style>
  
  </style>
</head>
<body>
  <div id="app" class="d-flex">
    <button id="burger-btn" class="d-lg-none btn btn-crimson position-fixed top-0 start-0 m-3">☰</button>
    <div id="sidebar" class="sidebar d-flex flex-column align-items-center">
      <img src="../images/clinic.png" alt="WMSU Clinic Logo" class="logo">
      <div class="text-white fw-bold text-uppercase text-center mt-2">WMSU</div>
      <div class="health-services text-white fw-bold text-uppercase text-center mt-2">Health Services</div>
      <button class="btn btn-crimson mb-1 w-100" id="dashboard-btn" onclick="window.location.href='adminhome.html'">Dashboard</button>
      <button class="btn btn-crimson mb-2 w-100 " id="announcement-btn" onclick="window.location.href='editAnnouncement.html'">Announcements</button>
      <button class="btn btn-crimson mb-2 w-100" id="medical-documents-btn" onclick="window.location.href='medical-documents.html'">Medical Documents</button>
      <button class="btn btn-crimson mb-2 w-100" id="dental-appointments-btn" onclick="window.location.href='dental-appointments.html'">Dental Appointments</button>
      <button class="btn btn-crimson mb-2 w-100" id="medical-appointments-btn" onclick="window.location.href='medical-appointments.html'">Medical Appointments</button>
      <button class="btn btn-crimson mb-2 w-100" id="notifications-btn" onclick="window.location.href='walkin.html'">Walk-in</button>
      <button class="btn btn-crimson mb-2 w-100 " id="patient-profile-btn" onclick="window.location.href='patient-profile.html'">Patient Profile</button>
      <button class="btn btn-crimson w-100" id="admin-account-btn" onclick="window.location.href='admin-account.html'">Admin Account</button>
    </div>
    <div class="main-content">
      <div class="slip-container">
        <div class="form-header">
          <div class="university-name">WESTERN MINDANAO STATE UNIVERSITY</div>
          <div class="health-center">HEALTH SERVICES CENTER</div>
          <div class="form-title">MEDICAL CERTIFICATE REQUEST SLIP</div>
        </div>
        
        <form>
          <div class="form-field">
            <label for="name">Name:</label>
            <input type="text" id="name" required>
          </div>
          
          <div class="form-field">
            <label for="course">Course & Year (for student):</label>
            <input type="text" id="course">
          </div>
          
          <div class="form-field">
            <label for="department">Department/Office (for personnel):</label>
            <input type="text" id="department">
          </div>
          
          <div class="form-field">
            <label for="contact">Contact no.:</label>
            <input type="text" id="contact" required>
          </div>
          
          <div class="request-options">
            <p>Please CHECK (✓) the appropriate box for the nature of request:</p>
            
            <div class="request-option">
              <input type="checkbox" id="absent">
              <label for="absent">Absent</label>
              <div class="option-details">
                <div>
                  <label for="absentDays">No. of days absent:</label>
                  <input type="number" id="absentDays">
                </div>
                <div>
                  <label for="absentReason">Reason for absent:</label>
                  <input type="text" id="absentReason">
                </div>
                <div>
                  <label for="absentDate">Date of consultation:</label>
                  <input type="date" id="absentDate">
                </div>
              </div>
            </div>
            
            <div class="request-option">
              <input type="checkbox" id="out">
              <label for="out">OJT</label>
              <div class="option-details">
                <div>
                  <label for="companyName">Name of Company:</label>
                  <input type="text" id="companyName">
                </div>
                <div>
                  <label for="companyAddress">Company Address:</label>
                  <input type="text" id="companyAddress">
                </div>
                <div>
                  <label for="outDate">Inclusive Date:</label>
                  <input type="date" id="outDate">
                </div>
              </div>
            </div>
            
            <div class="request-option">
              <input type="checkbox" id="others">
              <label for="others">Others (specify):</label>
              <div class="option-details" style="grid-template-columns: 1fr;">
                <div>
                  <input type="text" id="othersSpecify">
                </div>
              </div>
            </div>
          </div>
          
          <div class="signature-section">
            <div class="signature-label">SIGNATURE OF STAFF:</div>
            <canvas id="signatureCanvas" class="signature-canvas" width="400" height="150"></canvas>
            <div class="no-print">
              <button type="button" class="btn btn-sm btn-danger" onclick="clearSignature()">Clear Signature</button>
            </div>
            <div style="margin-top: 20px;">
              <label for="signatureDate">DATE:</label>
              <input type="date" id="signatureDate" style="width: 150px;">
            </div>
          </div>
          
          <div class="action-buttons no-print">
            <button type="button" class="btn btn-primary me-2" onclick="window.print()">
              <i class="bi bi-printer"></i> Print Form
            </button>
            <button type="submit" class="btn btn-success">
              <i class="bi bi-check-lg"></i> Submit Request
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    // Signature Canvas Functionality
    const canvas = document.getElementById('signatureCanvas');
    const ctx = canvas.getContext('2d');
    let isDrawing = false;
    
    // Set up the line style for drawing
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#000';
    
    // Event listeners for signature drawing
    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseout', stopDrawing);
    
    function startDrawing(e) {
      isDrawing = true;
      draw(e);
    }
    
    function draw(e) {
      if (!isDrawing) return;
      
      ctx.lineTo(e.offsetX, e.offsetY);
      ctx.stroke();
      ctx.beginPath();
      ctx.moveTo(e.offsetX, e.offsetY);
    }
    
    function stopDrawing() {
      isDrawing = false;
      ctx.beginPath();
    }
    
    function clearSignature() {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
    }
    
    // Form submission
    document.querySelector('form').addEventListener('submit', function(e) {
      e.preventDefault();
      const signatureEmpty = canvas.toDataURL() === document.createElement('canvas').toDataURL();
      
      if (signatureEmpty) {
        alert('Please provide a signature');
        return;
      }
      
      // Here you would typically submit the form data to your server
      alert('Request submitted successfully!');
      // this.submit(); // Uncomment to actually submit the form
    });
    
    // Sidebar toggle for mobile
    document.getElementById('burger-btn').addEventListener('click', () => {
      document.getElementById('app').classList.toggle('open');
    });
  </script>
</body>
</html>