<div id="sidebar" class="sidebar d-flex flex-column align-items-center">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Poppins:wght@400;500;700&display=swap');

    #sidebar {
      width: 255px !important;
      min-width: 255px !important;
    }

    .sidebar-header .brand-text .text-white {
      font-family: 'Cinzel', serif;
    }

    .sidebar-header .health-services {
      font-family: 'Poppins', sans-serif;
    }

    .sidebar,
    .sidebar-nav,
    .sidebar-footer,
    .dropdown-menu,
    .btn-crimson,
    .dropdown-item {
      font-family: 'Poppins', sans-serif;
    }
  </style>
  <div class="sidebar-header text-center mb-4">
    <img src="images/clinic.png" alt="WMSU Clinic Logo" class="logo mb-3">
    <div class="brand-text">
      <div class="text-white fw-bold text-uppercase fs-5 mb-1">WMSU</div>
      <div class="health-services text-white fw-bold text-uppercase fs-6 opacity-75">Health Services</div>
    </div>
  </div>


  <div class="sidebar-nav w-100 flex-grow-1">

    <div id="content-management-dropdown" class="dropdown w-100 mb-3">
      <button class="btn btn-crimson dropdown-toggle w-100 d-flex align-items-center justify-content-between"
        type="button" id="cmsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
        <span><i class="bi bi-gear-fill me-2"></i>Content Management</span>
      </button>
      <ul class="dropdown-menu w-100 shadow-lg" aria-labelledby="cmsDropdown">
        <li><a class="dropdown-item d-flex align-items-center" href="cms_index.php">
            <i class="bi bi-house-door me-2"></i>Landing Page
          </a></li>
        <li><a class="dropdown-item d-flex align-items-center" href="cms_homepage.php">
            <i class="bi bi-file-earmark-text me-2"></i>Homepage
          </a></li>
        <li><a class="dropdown-item d-flex align-items-center" href="cms_announcement.php">
            <i class="bi bi-megaphone me-2"></i>Announcements
          </a></li>
        <li><a class="dropdown-item d-flex align-items-center" href="cms_upload.php">
            <i class="bi bi-upload me-2"></i>Upload Medical Documents
          </a></li>
      </ul>
    </div>


    <div class="nav-buttons">
      <button class="btn btn-crimson mb-2 w-100 d-flex align-items-center justify-content-start"
        id="dashboard-btn" onclick="window.location.href='adminhome.php'">
        <i class="bi bi-speedometer2 me-2"></i>Dashboard
      </button>
      <button class="btn btn-crimson mb-2 w-100 d-flex align-items-center justify-content-start"
        id="medical-documents-btn" onclick="window.location.href='medical-documents.php'">
        <i class="bi bi-file-earmark-medical me-2"></i>Medical Documents
      </button>
      <button class="btn btn-crimson mb-2 w-100 d-flex align-items-center justify-content-start"
        id="dental-appointments-btn" onclick="window.location.href='dental-appointments.php'">
        <i class="bi bi-clipboard2-pulse me-2"></i>Dental Consultation
      </button>
      <button class="btn btn-crimson mb-2 w-100 d-flex align-items-center justify-content-start"
        id="medical-appointments-btn" onclick="window.location.href='medical-appointments.php'">
        <i class="bi bi-calendar-check me-2"></i>Medical Consultation
      </button>
      <button class="btn btn-crimson mb-2 w-100 d-flex align-items-center justify-content-start"
        id="patient-profile-btn" onclick="window.location.href='patient-profile.php'">
        <i class="bi bi-person-circle me-2"></i>Patient Profile
      </button>
      <button class="btn btn-crimson mb-2 w-100 d-flex align-items-center justify-content-start"
        id="history-btn" onclick="window.location.href='history.php'">
        <i class="bi bi-clock-history"></i> &nbsp; History
      </button>
      <button class="btn btn-crimson mb-2 w-100 d-flex align-items-center justify-content-start"
        id="admin-account-btn" onclick="window.location.href='admin-account.php'">
        <i class="bi bi-shield-lock me-2"></i>Admin Account
      </button>
    </div>
  </div>

  <div class="sidebar-footer w-100 text-center py-3">
    <div class="text-white-50 small">
      <div class="mb-1">WMSU Health Services</div>
      <div class="opacity-75">© 2024 All Rights Reserved</div>
    </div>
  </div>
</div>