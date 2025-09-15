document.addEventListener("DOMContentLoaded", function () {
    const buttons = {
        "homepage.php": "about-btn",
        "announcements.php": "announcement-btn",
        "appointment.php": "appointment-btn",
        "upload.php": "upload-btn",
        "profile.php": "profile-btn",
        "logout.php": "logout-btn"
      };
    
    const currentPage = window.location.pathname.split("/").pop();
    if (buttons[currentPage]) {
      document.getElementById(buttons[currentPage]).classList.add("active");
    }

    // Sidebar toggle functionality
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    const overlay = document.querySelector('.overlay');

    sidebarToggle.addEventListener('click', function() {
      sidebar.classList.toggle('open');
      mainContent.classList.toggle('sidebar-open');
      overlay.classList.toggle('active');
    });

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
      if (window.innerWidth <= 992) {
        const isClickInsideSidebar = sidebar.contains(event.target);
        const isClickOnToggle = event.target === sidebarToggle;
        
        if (!isClickInsideSidebar && !isClickOnToggle && sidebar.classList.contains('open')) {
          sidebar.classList.remove('open');
          mainContent.classList.remove('sidebar-open');
          overlay.classList.remove('active');
        }
      }
    });
  });