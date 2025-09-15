<?php
session_start();
require 'config.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>University Health Services - Appointment Request</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;500;700&display=swap" rel="stylesheet">
      <link rel="apple-touch-icon" sizes="180x180" href="images/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="images/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="images/favicon-16x16.png">
    <style>
        :root {
            --primary-color: #8b0000; /* Updated to dark red */
            --primary-light: #ffd5db;
            --primary-dark: #5a0000;
            --text-light: #ffffff;
            --text-dark: #333333;
            --gray-light: #f5f5f5;
            --gray-medium: #e0e0e0;
            --gray-dark: #888888;
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 8px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 6px 12px rgba(0, 0, 0, 0.15);
            --border-radius-sm: 5px;
            --border-radius-md: 10px;
            --border-radius-xl: 25px;
        }

        * {
            margin: 0;
            padding: 0;
            font-family: 'Montserrat', sans-serif;
            box-sizing: border-box;
      /* General Styles */
body {
    font-family: Arial, sans-serif;
    background: white;
    margin: 0;
    padding: 0;
    display: flex;
    height: 100vh;
}

#app {
    display: flex;
    width: 100%;
}

/* Sidebar */
.sidebar {
    width: 250px;
    background: #8B0000;
    padding: 20px;
    position: fixed;
    top: 0;
    bottom: 0;
    left: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
}

/* Logo - Lowered & Added Space */
.logo {
    width: 80px;
    height: auto;
    border-radius: 50%;
    border: 2px solid #fff;
    margin-top: 10px;
    margin-bottom: 15px; /* Added space between logo and WMSU text */
}

/* Sidebar Buttons */
.btn-crimson {
    background: none;
    color: white;
    border: none;
    padding: 10px;
    margin: 5px 0;
    border-radius: 5px;
    width: 100%;
    text-align: center;
    font-weight: normal; /* Removed bold */
    text-transform: none; /* Removed uppercase */
    transition: background-color 0.2s, color 0.2s;
}

.btn-crimson:hover {
    background: #fbfbfb;
}

        /* Main Content */
        .main-content {
            margin-left: 290px;
            padding: 20px;
            width: calc(100% - 290px);
            display: flex;
            flex-direction: column;
            color: black;
        }

        .title {
            font-size: 2rem;
        }

        /* Health Services - Added more space below */
        .health-services {
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 30px; /* Increased space between title and buttons */
            font-size: 1rem;
            text-align: center;
            color: white;
        }

        /* Sidebar Buttons - Ensure spacing from title */
        .btn-crimson:first-child {
            margin-top: 10px; /* Adds additional gap above the first button */
        }

/* Quick Link */
.quick-link {
    margin-top: auto;
    margin-bottom: 1rem;
    color: white;
    text-decoration: none;
    font-size: 0.9rem;
}

.quick-link:hover {
    opacity: 1;
}


        /* Appointment Form Styles */
        .appointment-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        /* Calendar Styles */
        .calendar-section {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .month-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .nav-btn {
            background: #8B0000;
            color: white;
            border: none;
            padding: 5px 15px;
            border-radius: 4px;
            cursor: pointer;
        }

        .calendar-header, .calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
            margin-bottom: 15px;
        }

        .calendar-day-name {
            text-align: center;
            font-weight: bold;
        }

        .day {
            padding: 10px;
            text-align: center;
            border-radius: 4px;
            cursor: pointer;
        }

        .day:hover {
            background: #f0f0f0;
        }

        .day.selected {
            background: #8B0000;
            color: white;
        }

        .day.unavailable {
            color: #ccc;
            cursor: not-allowed;
        }

        /* Time Slots */
        .timeslots-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }

        .timeslot {
            padding: 8px 15px;
            background: #f0f0f0;
            border-radius: 4px;
            cursor: pointer;
        }

        .timeslot:hover {
            background: #ddd;
        }

        .timeslot.selected {
            background: #8B0000;
            color: white;
        }

        /* Buttons */
        .confirm-button, .today-btn {
            background: #8B0000;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 15px;
        }

        .confirm-button:hover, .today-btn:hover {
            background: #6d0000;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: white;
            width: 80%;
            max-width: 600px;
            border-radius: 8px;
            overflow: hidden;
        }

        .modal-header {
            padding: 15px 20px;
            background: #8B0000;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-tabs {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 15px;
        }

        .modal-tab {
            padding: 10px 15px;
            cursor: pointer;
        }

        .modal-tab.active {
            border-bottom: 2px solid #8B0000;
            font-weight: bold;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .appointment-card {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .action-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .cancel-btn {
            background: #dc3545;
            color: white;
        }

        .delete-btn {
            background: #6c757d;
            color: white;
        }

        /* Selected Info */
        .selected-info {
            margin: 15px 0;
        }

        .info-group {
            margin-bottom: 10px;
        }

        .info-label {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .info-input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .main-content {
            margin-left: 250px;
            flex: 1;
            padding: 20px;
            transition: margin 0.3s ease;
        }

        .main-content.expanded {
            margin-left: 0;
        }

        /* Header */
        .header {
            background-color: var(--primary-color);
            color: var(--text-light);
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
            border-radius: var(--border-radius-md);
        }

        .header h1 {
            font-size: 2rem;
            font-weight: 700;
        }

        /* Appointment Section */
        .appointment-section,
        .calendar-section {
            background-color: var(--text-light);
            border-radius: var(--border-radius-md);
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-md);
        }

        .appointment-title,
        .calendar-title {
            color: var(--primary-color);
            font-size: 2rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border-radius: var(--border-radius-sm);
            border: 1px solid var(--gray-medium);
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        /* Month Navigation */
        .month-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--primary-color);
            color: var(--text-light);
            padding: 10px 15px;
            border-radius: var(--border-radius-md);
            margin-bottom: 15px;
        }

        .month-navigation span {
            font-weight: 700;
        }

        .nav-btn {
            background: var(--text-light);
            color: var(--primary-color);
            border: none;
            padding: 5px 15px;
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            font-weight: 700;
            transition: all 0.3s;
        }

        .nav-btn:hover {
            background: var(--primary-light);
        }

        /* Calendar */
        .calendar-header {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            margin-bottom: 10px;
        }

        .calendar-day-name {
            text-align: center;
            font-weight: 700;
            color: var(--primary-color);
        }

        .calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
        }

        .day {
            text-align: center;
            padding: 15px;
            background-color: var(--gray-light);
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            transition: all 0.3s;
        }

        .day:hover {
            background-color: var(--gray-medium);
            transform: translateY(-2px);
        }

        .day.selected {
            background-color: var(--primary-color);
            color: var(--text-light);
        }

        .day.unavailable {
            background-color: var(--gray-dark);
            color: var(--text-light);
            cursor: not-allowed;
        }

        /* Time Slots */
        .timeslots-section {
            margin-top: 20px;
        }

        .timeslots-title {
            color: var(--primary-color);
            font-size: 1.2rem;
            margin-bottom: 15px;
            font-weight: 500;
        }

        .timeslots-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .timeslot {
            padding: 10px 15px;
            background-color: var(--gray-light);
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            transition: all 0.3s;
        }

        .timeslot.selected {
            background-color: var(--primary-color);
            color: var(--text-light);
        }

        .timeslot.unavailable {
            background-color: var(--gray-dark);
            color: var(--text-light);
            cursor: not-allowed;
        }

        /* Selected Info */
        .selected-info {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-group {
            flex: 1;
        }

        .info-label {
            color: var(--text-dark);
            font-size: 0.95rem;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .info-input {
            width: 100%;
            padding: 12px;
            border-radius: var(--border-radius-sm);
            border: 1px solid var(--gray-medium);
            font-size: 1rem;
            background-color: var(--gray-light);
            box-shadow: var(--shadow-sm);
        }

        /* Buttons */
        .confirm-button {
            background-color: var(--primary-color);
            color: var(--text-light);
            border: none;
            border-radius: var(--border-radius-xl);
            padding: 15px 30px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 20px;
            width: 100%;
        }

        .confirm-button:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }

        .today-btn {
            background-color: var(--primary-color);
            color: var(--text-light);
            border: none;
            border-radius: var(--border-radius-xl);
            padding: 12px 25px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: block;
            margin: 15px auto;
            box-shadow: var(--shadow-md);
        }

        .today-btn:hover {
            background-color: var(--primary-dark);
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background-color: white;
            width: 90%;
            max-width: 800px;
            border-radius: var(--border-radius-md);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            transform: scale(0.8);
            transition: all 0.3s ease;
        }

        .modal-overlay.active .modal-content {
            transform: scale(1);
        }

        .modal-header {
            background-color: var(--primary-color);
            color: var(--text-light);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.5rem;
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--text-light);
            font-size: 1.5rem;
            cursor: pointer;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-tabs {
            display: flex;
            border-bottom: 1px solid var(--gray-medium);
            margin-bottom: 20px;
        }

        .modal-tab {
            padding: 10px 20px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
        }

        .modal-tab.active {
            border-bottom: 3px solid var(--primary-color);
            color: var(--primary-color);
        }

        .modal-tab:hover {
            background-color: var(--gray-light);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .appointment-card {
            background-color: var(--gray-light);
            border-left: 4px solid var(--primary-color);
            padding: 15px;
            margin-bottom: 15px;
            border-radius: var(--border-radius-sm);
            box-shadow: var(--shadow-sm);
        }

        .appointment-card.past {
            border-left-color: var(--gray-dark);
        }

        .appointment-details {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .detail-item {
            background-color: var(--gray-medium);
            padding: 5px 10px;
            border-radius: var(--border-radius-sm);
            font-size: 0.9rem;
            font-weight: 500;
        }

        .appointment-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .action-btn {
            padding: 8px 15px;
            border-radius: var(--border-radius-sm);
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }

        .cancel-btn {
            background-color: var(--primary-color);
            color: var(--text-light);
        }

        .cancel-btn:hover {
            background-color: var(--primary-dark);
        }

        .delete-btn {
            background-color: var(--gray-dark);
            color: var(--text-light);
        }

        .delete-btn:hover {
            background-color: var(--gray-medium);
        }

        /* Footer */
        footer {
            background-color: var(--primary-dark);
            color: var(--text-light);
            padding: 20px;
            text-align: center;
            margin-top: 40px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-250px);
            }

            .sidebar.hidden {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .main-content.expanded {
                margin-left: 250px;
            }
        }
    </style>
</head>
<body>
    <div id="app" class="d-flex">
        <button id="burger-btn" class="d-lg-none btn btn-crimson position-fixed top-0 start-0 m-3">☰</button>
        <div id="sidebar" class="sidebar d-flex flex-column align-items-center">
            <img src="../images/clinic.png" alt="WMSU Clinic Logo" class="logo">
            <div class="text-white fw-bold text-uppercase text-center mt-2">WMSU</div>
            <div class="health-services text-white fw-bold text-uppercase text-center mt-2 mb-5">Health Services</div>
            <button class="btn btn-crimson mb-2 w-100" id="about-btn" onclick="window.location.href='/homepage'">About Us</button>
            <button class="btn btn-crimson mb-2 w-100" id="announcement-btn" onclick="window.location.href='/announcements'">Announcements</button>
            <button class="btn btn-crimson mb-2 w-100" id="appointment-btn" onclick="window.location.href='/appointment'">Appointment Request</button>
            <button class="btn btn-crimson mb-2 w-100" id="upload-btn" onclick="window.location.href='/upload'">Upload Medical Documents</button>
            <button class="btn btn-crimson mb-2 w-100" id="profile-btn" onclick="window.location.href='/profile'">Profile</button>
            <button class="btn btn-crimson w-100" id="logout-btn" onclick="window.location.href='/logout'">Logout</button>
            <a href="https://wmsu.edu.ph" target="_blank" class="quick-link">wmsu.edu.ph</a>
        </div>

        <!-- Main Content -->
        <div class="main-content" id="mainContent">
            <!-- Header -->
            <div class="header">
                <h1>Appointment Request</h1>
            </div>

            <!-- Appointment Section -->
            <section class="appointment-section">
                <form class="appointment-form">
                    <div class="form-group">
                        <select class="form-control" id="appointment-reason">
                            <option value="" disabled selected>Reason for appointment</option>
                            <option value="check-up">Regular Check-up</option>
                            <option value="illness">Illness</option>
                            <option value="injury">Injury</option>
                            <option value="vaccination">Vaccination</option>
                            <option value="counseling">Counseling</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group" id="other-reason-group" style="display: none;">
                        <input type="text" class="form-control" id="other-reason" placeholder="Please specify the reason">
                    </div>
                </form>
            </section>

            <!-- Calendar Section -->
            <section class="calendar-section">
                <h2 class="calendar-title">Select an Appointment Date & Time</h2>
                
                <!-- Month Navigation -->
                <div class="month-navigation">
                    <button class="nav-btn" id="prevMonth">Previous</button>
                    <span id="currentMonth"></span>
                    <button class="nav-btn" id="nextMonth">Next</button>
                </div>

                <!-- Selected Info -->
                <div class="selected-info">
                    <div class="info-group">
                        <div class="info-label">Selected Date:</div>
                        <input type="text" id="selectedDate" class="info-input" readonly placeholder="Select a date">
                    </div>
                    <div class="info-group">
                        <div class="info-label">Selected Time:</div>
                        <input type="text" id="selectedTime" class="info-input" readonly placeholder="Select a time">
                    </div>
                </div>

                <!-- Calendar Grid -->
                <div class="calendar-header">
                    <div class="calendar-day-name">Sun</div>
                    <div class="calendar-day-name">Mon</div>
                    <div class="calendar-day-name">Tue</div>
                    <div class="calendar-day-name">Wed</div>
                    <div class="calendar-day-name">Thu</div>
                    <div class="calendar-day-name">Fri</div>
                    <div class="calendar-day-name">Sat</div>
                </div>
                <div class="calendar" id="calendar"></div>

                <!-- Time Slots -->
                <div class="timeslots-section">
                    <h3 class="timeslots-title">Available Time Slots</h3>
                    <div class="timeslots-container" id="timeSlots"></div>
                </div>

                <button type="button" class="confirm-button" id="confirm-btn">Confirm Appointment</button>
            </section>

            <!-- View Appointments Button -->
            <button type="button" class="today-btn" id="view-appointments-btn">View My Appointments</button>

            <!-- Appointments Modal -->
            <div class="modal-overlay" id="appointments-modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>My Appointments</h2>
                        <button class="modal-close" id="close-modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="modal-tabs">
                            <div class="modal-tab active" data-tab="upcoming">Upcoming</div>
                            <div class="modal-tab" data-tab="past">Past</div>
                        </div>

                        <div class="tab-content active" id="upcoming-tab">
                            <div class="appointment-card">
                                <h3>General Checkup</h3>
                                <div class="appointment-details">
                                    <div class="detail-item">Date: March 10, 2025</div>
                                    <div class="detail-item">Time: 11:00 AM</div>
                                </div>
                                <div class="appointment-actions">
                                    <button class="action-btn cancel-btn">Cancel Appointment</button>
                                </div>
                            </div>
                        </div>

                        <div class="tab-content" id="past-tab">
                            <div class="appointment-card past">
                                <h3>Vaccination - Flu Shot</h3>
                                <div class="appointment-details">
                                    <div class="detail-item">Date: February 10, 2025</div>
                                    <div class="detail-item">Time: 10:00 AM</div>
                                </div>
                                <div class="appointment-actions">
                                    <button class="action-btn delete-btn">Delete Record</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <footer>
                &copy; 2025 University Health Services. All rights reserved.
            </footer>
        </div>
    </div>

    <script>
        // DOM Elements
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const calendarDiv = document.getElementById('calendar');
        const timeSlotsDiv = document.getElementById('timeSlots');
        const confirmBtn = document.getElementById('confirm-btn');
        const appointmentReasonSelect = document.getElementById('appointment-reason');
        const otherReasonGroup = document.getElementById('other-reason-group');
        const otherReasonInput = document.getElementById('other-reason');
        const viewAppointmentsBtn = document.getElementById('view-appointments-btn');
        const appointmentsModal = document.getElementById('appointments-modal');
        const closeModal = document.getElementById('close-modal');
        const modalTabs = document.querySelectorAll('.modal-tab');
        const tabContents = document.querySelectorAll('.tab-content');
        const selectedDateInput = document.getElementById('selectedDate');
        const selectedTimeInput = document.getElementById('selectedTime');
        const prevMonthBtn = document.getElementById('prevMonth');
        const nextMonthBtn = document.getElementById('nextMonth');
        const currentMonthDisplay = document.getElementById('currentMonth');

        // Calendar State
        const today = new Date();
        let currentMonth = today.getMonth();
        let currentYear = today.getFullYear();

        // Initialize
        updateMonthDisplay();
        createCalendar();

        // Event Listeners
        appointmentReasonSelect.addEventListener('change', handleReasonChange);
        confirmBtn.addEventListener('click', confirmAppointment);
        viewAppointmentsBtn.addEventListener('click', showAppointmentsModal);
        closeModal.addEventListener('click', hideAppointmentsModal);
        prevMonthBtn.addEventListener('click', () => changeMonth(-1));
        nextMonthBtn.addEventListener('click', () => changeMonth(1));

        modalTabs.forEach(tab => {
            tab.addEventListener('click', switchTab);
        });

        // Functions
        function toggleSidebar() {
            sidebar.classList.toggle('hidden');
            mainContent.classList.toggle('expanded');
        }

        function handleReasonChange() {
            if (this.value === 'other') {
                otherReasonGroup.style.display = 'block';
            } else {
                otherReasonGroup.style.display = 'none';
                otherReasonInput.value = '';
            }
        }

        function updateMonthDisplay() {
            currentMonthDisplay.textContent = 
                new Date(currentYear, currentMonth).toLocaleString('default', { 
                    month: 'long', 
                    year: 'numeric' 
                });
            createCalendar();
        }

        function changeMonth(change) {
            currentMonth += change;
            
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            } else if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
            
            updateMonthDisplay();
        }

        function createCalendar() {
            calendarDiv.innerHTML = '';
            const firstDayOfMonth = new Date(currentYear, currentMonth, 1).getDay();
            const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();

            // Add empty slots for alignment
            for (let i = 0; i < firstDayOfMonth; i++) {
                const emptyDay = document.createElement('div');
                emptyDay.classList.add('day', 'unavailable');
                calendarDiv.appendChild(emptyDay);
            }

            // Add days of the month
            for (let i = 1; i <= daysInMonth; i++) {
                const dayDiv = document.createElement('div');
                dayDiv.textContent = i;
                dayDiv.classList.add('day');

                const tempDate = new Date(currentYear, currentMonth, i);
                const todayDate = new Date();
                todayDate.setHours(0, 0, 0, 0);

                const isWeekend = tempDate.getDay() === 0 || tempDate.getDay() === 6;
                const isPast = tempDate < todayDate;

                if (isWeekend || isPast) {
                    dayDiv.classList.add('unavailable');
                } else {
                    dayDiv.addEventListener('click', function() {
                        document.querySelectorAll('.day').forEach(day => day.classList.remove('selected'));
                        this.classList.add('selected');
                        selectedDateInput.value = `${tempDate.getFullYear()}-${String(tempDate.getMonth() + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
                        generateTimeSlots();
                    });
                }

                calendarDiv.appendChild(dayDiv);
            }
        }

        function generateTimeSlots() {
            timeSlotsDiv.innerHTML = '';
            selectedTimeInput.value = '';

            const availableTimes = ["8:00 AM", "9:00 AM", "10:00 AM", "11:00 AM", "1:00 PM", "2:00 PM", "3:00 PM", "4:00 PM"];

            availableTimes.forEach(time => {
                const timeSlot = document.createElement('div');
                timeSlot.textContent = time;
                timeSlot.classList.add('timeslot');

                timeSlot.addEventListener('click', function() {
                    document.querySelectorAll('.timeslot').forEach(slot => slot.classList.remove('selected'));
                    this.classList.add('selected');
                    selectedTimeInput.value = time;
                });

                timeSlotsDiv.appendChild(timeSlot);
            });
        }

        function confirmAppointment() {
            const reason = appointmentReasonSelect.value;
            const otherReason = otherReasonInput.value;
            const selectedDate = selectedDateInput.value;
            const selectedTime = selectedTimeInput.value;

            if (!reason) {
                alert('Please select a reason for your appointment.');
                return;
            }

            if (reason === 'other' && !otherReason.trim()) {
                alert('Please specify the reason for your appointment.');
                return;
            }

            if (!selectedDate) {
                alert('Please select a date for your appointment.');
                return;
            }

            if (!selectedTime) {
                alert('Please select a time slot for your appointment.');
                return;
            }

            const formattedDate = new Date(selectedDate).toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });

            alert(`✅ Appointment request submitted successfully!\n\nReason: ${reason === 'other' ? otherReason : reason}\nDate: ${formattedDate}\nTime: ${selectedTime}`);

            // Reset form
            appointmentReasonSelect.value = '';
            otherReasonInput.value = '';
            otherReasonGroup.style.display = 'none';
            selectedDateInput.value = '';
            selectedTimeInput.value = '';
            document.querySelectorAll('.day').forEach(day => day.classList.remove('selected'));
            document.querySelectorAll('.timeslot').forEach(slot => slot.classList.remove('selected'));
            timeSlotsDiv.innerHTML = '';
        }

        function showAppointmentsModal() {
            appointmentsModal.classList.add('active');
        }

        function hideAppointmentsModal() {
            appointmentsModal.classList.remove('active');
        }

        function switchTab() {
            modalTabs.forEach(t => t.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));

            this.classList.add('active');
            const tabId = this.getAttribute('data-tab');
            document.getElementById(`${tabId}-tab`).classList.add('active');
        }
    </script>
</body>
</html>