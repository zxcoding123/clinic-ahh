<?php
session_start();
require 'config.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Walk-in Patients - WMSU Health Services</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/walkin.css">
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
      <button class="btn btn-crimson mb-1 w-100" id="dashboard-btn" onclick="window.location.href='adminhome'">Dashboard</button>
      <button class="btn btn-crimson mb-2 w-100 " id="announcement-btn" onclick="window.location.href='editAnnouncement'">Announcements</button>
      <button class="btn btn-crimson mb-2 w-100" id="medical-documents-btn" onclick="window.location.href='medical-documents'">Medical Documents</button>
      <button class="btn btn-crimson mb-2 w-100" id="dental-appointments-btn" onclick="window.location.href='dental-appointments'">Dental Appointments</button>
      <button class="btn btn-crimson mb-2 w-100" id="medical-appointments-btn" onclick="window.location.href='medical-appointments'">Medical Appointments</button>
      <button class="btn btn-crimson mb-2 w-100 active" id="notifications-btn" onclick="window.location.href='walkin'">Walk-in</button>
      <button class="btn btn-crimson mb-2 w-100 " id="patient-profile-btn" onclick="window.location.href='patient-profilel'">Patient Profile</button>
      <button class="btn btn-crimson w-100" id="admin-account-btn" onclick="window.location.href='admin-account'">Admin Account</button>
    </div>
    <div id="main-content">
      <h1 class="walkin-title">Walk-in Patients</h1>
      
      <!-- Walk-in Form -->
      <div class="walkin-form">
        <h3 class="mb-4"><i class="bi bi-person-plus"></i> Register New Walk-in</h3>
        <form id="walkinForm">
          <div class="row mb-3">
            <div class="col-md-6">
              <label for="patientName" class="form-label">Patient Name</label>
              <input type="text" class="form-control" id="patientName" required>
            </div>
            <div class="col-md-6">
              <label for="patientContact" class="form-label">Contact Number</label>
              <input type="tel" class="form-control" id="patientContact">
            </div>
          </div>
          
          <div class="row mb-3">
            <div class="col-md-6">
              <label for="patientType" class="form-label">Patient Type</label>
              <select class="form-select" id="patientType">
                <option value="student">Student</option>
                <option value="faculty">Faculty</option>
                <option value="staff">Staff</option>
                <option value="visitor">Visitor</option>
              </select>
            </div>
            <div class="col-md-6">
              <label for="patientCourse" class="form-label">Course/Department</label>
              <input type="text" class="form-control" id="patientCourse">
            </div>
          </div>
          
          <div class="row mb-3">
            <div class="col-md-6">
              <label for="priorityLevel" class="form-label">Priority Level</label>
              <select class="form-select" id="priorityLevel" required>
                <option value="">Select priority...</option>
                <option value="emergency">Emergency (Life-threatening)</option>
                <option value="urgent">Urgent (Needs attention today)</option>
                <option value="routine">Routine (Non-urgent)</option>
              </select>
            </div>
            <div class="col-md-6">
              <label for="complaint" class="form-label">Chief Complaint</label>
              <input type="text" class="form-control" id="complaint" required>
            </div>
          </div>
          
          <div class="mb-3">
            <label for="notes" class="form-label">Additional Notes</label>
            <textarea class="form-control" id="notes" rows="2"></textarea>
          </div>
          
          <div class="text-end">
            <button type="submit" class="btn btn-crimson">
              <i class="bi bi-plus-circle"></i> Add to Queue
            </button>
          </div>
        </form>
      </div>
      
      <!-- Priority Tabs -->
      <ul class="nav nav-tabs priority-tabs mb-3" id="priorityTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab" aria-controls="all" aria-selected="true">All Patients</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="emergency-tab" data-bs-toggle="tab" data-bs-target="#emergency" type="button" role="tab" aria-controls="emergency" aria-selected="false">Emergency</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="urgent-tab" data-bs-toggle="tab" data-bs-target="#urgent" type="button" role="tab" aria-controls="urgent" aria-selected="false">Urgent</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="routine-tab" data-bs-toggle="tab" data-bs-target="#routine" type="button" role="tab" aria-controls="routine" aria-selected="false">Routine</button>
        </li>
      </ul>
      
      <!-- Queue Content -->
      <div class="tab-content" id="priorityTabContent">
        <div class="tab-pane fade show active" id="all" role="tabpanel" aria-labelledby="all-tab">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h3><i class="bi bi-people"></i> Current Queue</h3>
            <div>
              <div class="form-check d-inline-block me-3">
                <input class="form-check-input" type="checkbox" id="selectAllCheckbox" onclick="toggleSelectAll()">
                <label class="form-check-label" for="selectAllCheckbox">Select All</label>
              </div>
              <button class="btn btn-outline-danger btn-sm" onclick="removeSelected()">
                <i class="bi bi-x-lg"></i> Remove Selected
              </button>
            </div>
          </div>
          
          <div class="table-responsive">
            <table class="table table-bordered table-hover">
              <thead>
                <tr>
                  <th style="width: 40px;"></th>
                  <th>Patient Name</th>
                  <th>Priority</th>
                  <th>Complaint</th>
                  <th>Arrival Time</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="allQueue">
                <tr class="emergency-row">
                  <td><input type="checkbox" class="form-check-input patient-checkbox" data-patient-id="1"></td>
                  <td>Maria Santos</td>
                  <td><span class="priority-badge badge-emergency">Emergency</span></td>
                  <td>Chest pain, difficulty breathing</td>
                  <td>2 minutes ago</td>
                  <td>
                    <button class="btn btn-info btn-sm me-2" onclick="window.location.href='patient-profile.html'">
                      View Profile
                    </button>
                    <button class="btn btn-primary btn-sm" onclick="window.location.href='consultationForm.html'">
                      Consultation Form
                    </button>
                    <button class="btn btn-outline-danger btn-sm p-1 ms-2" onclick="removePatient(1)" title="Remove">
                      <i class="bi bi-x-lg"></i>
                    </button>
                  </td>
                </tr>
                <tr class="urgent-row">
                  <td><input type="checkbox" class="form-check-input patient-checkbox" data-patient-id="2"></td>
                  <td>Juan Dela Cruz</td>
                  <td><span class="priority-badge badge-urgent">Urgent</span></td>
                  <td>High fever (39°C), headache</td>
                  <td>15 minutes ago</td>
                  <td>
                    <button class="btn btn-info btn-sm me-2" onclick="window.location.href='patient-profile.html'">
                      View Profile
                    </button>
                    <button class="btn btn-primary btn-sm" onclick="window.location.href='consultationForm.html'">
                      Consultation Form
                    </button>
                    <button class="btn btn-outline-danger btn-sm p-1 ms-2" onclick="removePatient(2)" title="Remove">
                      <i class="bi bi-x-lg"></i>
                    </button>
                  </td>
                </tr>
                <tr class="routine-row">
                  <td><input type="checkbox" class="form-check-input patient-checkbox" data-patient-id="3"></td>
                  <td>Robert Johnson</td>
                  <td><span class="priority-badge badge-routine">Routine</span></td>
                  <td>Routine checkup</td>
                  <td>25 minutes ago</td>
                  <td>
                    <button class="btn btn-info btn-sm me-2" onclick="window.location.href='patient-profile.html'">
                      View Profile
                    </button>
                    <button class="btn btn-primary btn-sm" onclick="window.location.href='consultationForm.html'">
                      Consultation Form
                    </button>
                    <button class="btn btn-outline-danger btn-sm p-1 ms-2" onclick="removePatient(3)" title="Remove">
                      <i class="bi bi-x-lg"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        
        <!-- Emergency Tab -->
        <div class="tab-pane fade" id="emergency" role="tabpanel" aria-labelledby="emergency-tab">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h3><i class="bi bi-exclamation-triangle"></i> Emergency Cases</h3>
            <div>
              <div class="form-check d-inline-block me-3">
                <input class="form-check-input" type="checkbox" id="selectAllEmergency" onclick="toggleSelectAllEmergency()">
                <label class="form-check-label" for="selectAllEmergency">Select All</label>
              </div>
              <button class="btn btn-outline-danger btn-sm" onclick="removeSelectedEmergency()">
                <i class="bi bi-x-lg"></i> Remove Selected
              </button>
            </div>
          </div>
          
          <div class="table-responsive">
            <table class="table table-bordered table-hover">
              <thead>
                <tr>
                  <th style="width: 40px;"></th>
                  <th>Patient Name</th>
                  <th>Complaint</th>
                  <th>Arrival Time</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="emergencyQueue">
                <tr>
                  <td><input type="checkbox" class="form-check-input patient-checkbox" data-patient-id="1"></td>
                  <td>Maria Santos</td>
                  <td>Chest pain, difficulty breathing</td>
                  <td>2 minutes ago</td>
                  <td>
                    <button class="btn btn-info btn-sm me-2" onclick="window.location.href='patient-profile.html'">
                      View Profile
                    </button>
                    <button class="btn btn-primary btn-sm" onclick="window.location.href='consultationForm.html'">
                      Consultation Form
                    </button>
                    <button class="btn btn-outline-danger btn-sm p-1 ms-2" onclick="removePatient(1)" title="Remove">
                      <i class="bi bi-x-lg"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        
        <!-- Urgent Tab -->
        <div class="tab-pane fade" id="urgent" role="tabpanel" aria-labelledby="urgent-tab">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h3><i class="bi bi-clock"></i> Urgent Cases</h3>
            <div>
              <div class="form-check d-inline-block me-3">
                <input class="form-check-input" type="checkbox" id="selectAllUrgent" onclick="toggleSelectAllUrgent()">
                <label class="form-check-label" for="selectAllUrgent">Select All</label>
              </div>
              <button class="btn btn-outline-danger btn-sm" onclick="removeSelectedUrgent()">
                <i class="bi bi-x-lg"></i> Remove Selected
              </button>
            </div>
          </div>
          
          <div class="table-responsive">
            <table class="table table-bordered table-hover">
              <thead>
                <tr>
                  <th style="width: 40px;"></th>
                  <th>Patient Name</th>
                  <th>Complaint</th>
                  <th>Arrival Time</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="urgentQueue">
                <tr>
                  <td><input type="checkbox" class="form-check-input patient-checkbox" data-patient-id="2"></td>
                  <td>Juan Dela Cruz</td>
                  <td>High fever (39°C), headache</td>
                  <td>15 minutes ago</td>
                  <td>
                    <button class="btn btn-info btn-sm me-2" onclick="window.location.href='patient-profile.html'">
                      View Profile
                    </button>
                    <button class="btn btn-primary btn-sm" onclick="window.location.href='consultationForm.html'">
                      Consultation Form
                    </button>
                    <button class="btn btn-outline-danger btn-sm p-1 ms-2" onclick="removePatient(2)" title="Remove">
                      <i class="bi bi-x-lg"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        
        <!-- Routine Tab -->
        <div class="tab-pane fade" id="routine" role="tabpanel" aria-labelledby="routine-tab">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h3><i class="bi bi-calendar-check"></i> Routine Cases</h3>
            <div>
              <div class="form-check d-inline-block me-3">
                <input class="form-check-input" type="checkbox" id="selectAllRoutine" onclick="toggleSelectAllRoutine()">
                <label class="form-check-label" for="selectAllRoutine">Select All</label>
              </div>
              <button class="btn btn-outline-danger btn-sm" onclick="removeSelectedRoutine()">
                <i class="bi bi-x-lg"></i> Remove Selected
              </button>
            </div>
          </div>
          
          <div class="table-responsive">
            <table class="table table-bordered table-hover">
              <thead>
                <tr>
                  <th style="width: 40px;"></th>
                  <th>Patient Name</th>
                  <th>Complaint</th>
                  <th>Arrival Time</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="routineQueue">
                <tr>
                  <td><input type="checkbox" class="form-check-input patient-checkbox" data-patient-id="3"></td>
                  <td>Robert Johnson</td>
                  <td>Routine checkup</td>
                  <td>25 minutes ago</td>
                  <td>
                    <button class="btn btn-info btn-sm me-2" onclick="window.location.href='patient-profile.html'">
                      View Profile
                    </button>
                    <button class="btn btn-primary btn-sm" onclick="window.location.href='consultationForm.html'">
                      Consultation Form
                    </button>
                    <button class="btn btn-outline-danger btn-sm p-1 ms-2" onclick="removePatient(3)" title="Remove">
                      <i class="bi bi-x-lg"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Bootstrap JS Bundle with Popper -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
      // Sample queue data
      const queueData = [
        {
          id: 1,
          name: "Maria Santos",
          contact: "09123456789",
          type: "Student",
          course: "BS Nursing",
          priority: "emergency",
          complaint: "Chest pain, difficulty breathing",
          notes: "Patient has history of asthma",
          arrivalTime: new Date(Date.now() - 120000) // 2 minutes ago
        },
        {
          id: 2,
          name: "Juan Dela Cruz",
          contact: "09234567890",
          type: "Faculty",
          course: "College of Engineering",
          priority: "urgent",
          complaint: "High fever (39°C), headache",
          notes: "",
          arrivalTime: new Date(Date.now() - 900000) // 15 minutes ago
        },
        {
          id: 3,
          name: "Robert Johnson",
          contact: "09345678901",
          type: "Staff",
          course: "Administration Office",
          priority: "routine",
          complaint: "Routine checkup",
          notes: "Annual physical exam",
          arrivalTime: new Date(Date.now() - 1500000) // 25 minutes ago
        }
      ];
      
      // Form submission
      document.getElementById('walkinForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Get form values
        const name = document.getElementById('patientName').value;
        const contact = document.getElementById('patientContact').value;
        const type = document.getElementById('patientType').value;
        const course = document.getElementById('patientCourse').value;
        const priority = document.getElementById('priorityLevel').value;
        const complaint = document.getElementById('complaint').value;
        const notes = document.getElementById('notes').value;
        
        // Create new patient object
        const newPatient = {
          id: Date.now(), // Use timestamp as temporary ID
          name,
          contact,
          type,
          course,
          priority,
          complaint,
          notes,
          arrivalTime: new Date()
        };
        
        // Add to queue
        queueData.unshift(newPatient);
        refreshQueue();
        
        // Reset form
        this.reset();
        
        // Show success message
        alert(`${name} has been added to the ${priority} queue`);
      });
      
      // Toggle select all checkboxes
      function toggleSelectAll() {
        const selectAll = document.getElementById('selectAllCheckbox').checked;
        const checkboxes = document.querySelectorAll('#allQueue .patient-checkbox');
        checkboxes.forEach(checkbox => {
          checkbox.checked = selectAll;
        });
      }
      
      function toggleSelectAllEmergency() {
        const selectAll = document.getElementById('selectAllEmergency').checked;
        const checkboxes = document.querySelectorAll('#emergencyQueue .patient-checkbox');
        checkboxes.forEach(checkbox => {
          checkbox.checked = selectAll;
        });
      }
      
      function toggleSelectAllUrgent() {
        const selectAll = document.getElementById('selectAllUrgent').checked;
        const checkboxes = document.querySelectorAll('#urgentQueue .patient-checkbox');
        checkboxes.forEach(checkbox => {
          checkbox.checked = selectAll;
        });
      }
      
      function toggleSelectAllRoutine() {
        const selectAll = document.getElementById('selectAllRoutine').checked;
        const checkboxes = document.querySelectorAll('#routineQueue .patient-checkbox');
        checkboxes.forEach(checkbox => {
          checkbox.checked = selectAll;
        });
      }
      
      // Remove selected patients
      function removeSelected() {
        const selectedCheckboxes = document.querySelectorAll('#allQueue .patient-checkbox:checked');
        if (selectedCheckboxes.length === 0) {
          alert('Please select at least one patient to remove');
          return;
        }
        
        selectedCheckboxes.forEach(checkbox => {
          const patientId = parseInt(checkbox.getAttribute('data-patient-id'));
          removePatientFromData(patientId);
        });
        refreshQueue();
      }
      
      function removeSelectedEmergency() {
        const selectedCheckboxes = document.querySelectorAll('#emergencyQueue .patient-checkbox:checked');
        if (selectedCheckboxes.length === 0) {
          alert('Please select at least one patient to remove');
          return;
        }
        
        selectedCheckboxes.forEach(checkbox => {
          const patientId = parseInt(checkbox.getAttribute('data-patient-id'));
          removePatientFromData(patientId);
        });
        refreshQueue();
      }
      
      function removeSelectedUrgent() {
        const selectedCheckboxes = document.querySelectorAll('#urgentQueue .patient-checkbox:checked');
        if (selectedCheckboxes.length === 0) {
          alert('Please select at least one patient to remove');
          return;
        }
        
        selectedCheckboxes.forEach(checkbox => {
          const patientId = parseInt(checkbox.getAttribute('data-patient-id'));
          removePatientFromData(patientId);
        });
        refreshQueue();
      }
      
      function removeSelectedRoutine() {
        const selectedCheckboxes = document.querySelectorAll('#routineQueue .patient-checkbox:checked');
        if (selectedCheckboxes.length === 0) {
          alert('Please select at least one patient to remove');
          return;
        }
        
        selectedCheckboxes.forEach(checkbox => {
          const patientId = parseInt(checkbox.getAttribute('data-patient-id'));
          removePatientFromData(patientId);
        });
        refreshQueue();
      }
      
      // Remove individual patient
      function removePatient(patientId) {
        if (confirm('Are you sure you want to remove this patient from the queue?')) {
          removePatientFromData(patientId);
          refreshQueue();
        }
      }
      
      function removePatientFromData(patientId) {
        const index = queueData.findIndex(p => p.id === patientId);
        if (index !== -1) {
          queueData.splice(index, 1);
        }
      }
      
      // Refresh queue display
      function refreshQueue() {
        // Sort by priority (emergency first, then urgent, then routine)
        queueData.sort((a, b) => {
          const priorityOrder = { emergency: 1, urgent: 2, routine: 3 };
          return priorityOrder[a.priority] - priorityOrder[b.priority];
        });
        
        // Update all queues
        updateQueueDisplay('allQueue', queueData);
        updateQueueDisplay('emergencyQueue', queueData.filter(p => p.priority === 'emergency'));
        updateQueueDisplay('urgentQueue', queueData.filter(p => p.priority === 'urgent'));
        updateQueueDisplay('routineQueue', queueData.filter(p => p.priority === 'routine'));
      }
      
      // Update queue display
      function updateQueueDisplay(elementId, patients) {
        const container = document.getElementById(elementId);
        container.innerHTML = '';
        
        if (patients.length === 0) {
          container.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No patients in queue</td></tr>';
          return;
        }
        
        patients.forEach(patient => {
          const priorityBadge = patient.priority === 'emergency' ? 'badge-emergency' : 
                               patient.priority === 'urgent' ? 'badge-urgent' : 'badge-routine';
          
          // Format arrival time
          const minutesAgo = Math.floor((new Date() - patient.arrivalTime) / 60000);
          const arrivalText = minutesAgo < 1 ? 'Less than a minute ago' : 
                             minutesAgo === 1 ? '1 minute ago' : 
                             `${minutesAgo} minutes ago`;
          
          const row = document.createElement('tr');
          row.className = patient.priority + '-row';
          row.innerHTML = `
            <td><input type="checkbox" class="form-check-input patient-checkbox" data-patient-id="${patient.id}"></td>
            <td>${patient.name}</td>
            ${elementId === 'allQueue' ? `<td><span class="priority-badge ${priorityBadge}">${patient.priority.charAt(0).toUpperCase() + patient.priority.slice(1)}</span></td>` : ''}
            <td>${patient.complaint}</td>
            <td>${arrivalText}</td>
            <td>
              <button class="btn btn-info btn-sm me-2" onclick="window.location.href='patient-profile.html'">
                View Profile
              </button>
              <button class="btn btn-primary btn-sm" onclick="window.location.href='consultationForm.html'">
                Consultation Form
              </button>
              <button class="btn btn-outline-danger btn-sm p-1 ms-2" onclick="removePatient(${patient.id})" title="Remove">
                <i class="bi bi-x-lg"></i>
              </button>
            </td>
          `;
          container.appendChild(row);
        });
      }
      
      // Sidebar Toggle for Mobile
      document.getElementById('burger-btn').addEventListener('click', () => {
          document.getElementById('app').classList.toggle('open');
      });
      
      // Initialize queue display
      refreshQueue();
  </script>
</body>
</html>