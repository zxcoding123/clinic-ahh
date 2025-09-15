// DOM Elements
const sidebar = document.getElementById('sidebar');
const sidebarToggle = document.getElementById('sidebar-toggle');
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

// K-6 specific elements
let childSelect = null;

// User type (set via backend in MedicalRequest.php)
const userType = document.body.dataset.userType || 'College';

// Calendar State
const today = new Date();
let currentMonth = today.getMonth();
let currentYear = today.getFullYear();

// Initialize
updateMonthDisplay();
createCalendar();
if (userType === 'K-6') {
    initializeChildSelect();
}

// Event Listeners
sidebarToggle.addEventListener('click', toggleSidebar);
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
    sidebar.classList.toggle('open');
    mainContent.classList.toggle('sidebar-open');
}

function handleReasonChange() {
    if (this.value === 'other') {
        otherReasonGroup.style.display = 'block';
    } else {
        otherReasonGroup.style.display = 'none';
        otherReasonInput.value = '';
    }
}

function initializeChildSelect() {
    // Create dropdown for K-6 users
    const form = document.querySelector('.appointment-form');
    const childGroup = document.createElement('div');
    childGroup.classList.add('form-group');
    childGroup.innerHTML = `
        <select class="form-control" id="child-select">
            <option value="" disabled selected>Select a child</option>
        </select>
    `;
    form.insertBefore(childGroup, otherReasonGroup);

    childSelect = document.getElementById('child-select');

    fetch('/ajax.php?action=children')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                data.children.forEach(child => {
                    const option = document.createElement('option');
                    option.value = child.id;
                    option.textContent = child.name;
                    childSelect.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Detailed error:', error);
            if (error.message.includes('redirect')) {
                console.log('Redirect detected in AJAX call');
            }
        });
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

        const isPast = tempDate < todayDate;
        const isSunday = tempDate.getDay() === 0;

        if (isSunday || isPast) {
            dayDiv.classList.add('unavailable');
        } else {
            dayDiv.addEventListener('click', function() {
                document.querySelectorAll('.day').forEach(day => day.classList.remove('selected'));
                this.classList.add('selected');
                selectedDateInput.value = `${tempDate.getFullYear()}-${String(tempDate.getMonth() + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
                generateTimeSlots(selectedDateInput.value);
            });
        }

        calendarDiv.appendChild(dayDiv);
    }
}

function generateTimeSlots(date) {
    timeSlotsDiv.innerHTML = '';
    selectedTimeInput.value = '';

    // Fetch available time slots from backend
    fetch(`/ajax.php?action=time_slots&date=${date}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                data.time_slots.forEach(time => {
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
            } else {
                timeSlotsDiv.innerHTML = '<p>No available time slots for this date.</p>';
            }
        })
        .catch(error => {
            console.error('Error fetching time slots:', error);
            timeSlotsDiv.innerHTML = '<p>Error loading time slots.</p>';
        });
}

function confirmAppointment() {
    const reason = appointmentReasonSelect.value;
    const otherReason = otherReasonInput.value;
    const selectedDate = selectedDateInput.value;
    const selectedTime = selectedTimeInput.value;
    const childId = userType === 'K-6' ? childSelect.value : null;

    if (!reason) {
        alert('Please select a reason for your appointment.');
        return;
    }

    if (reason === 'other' && !otherReason.trim()) {
        alert('Please specify the reason for your appointment.');
        return;
    }

    if (userType === 'K-6' && !childId) {
        alert('Please select a child for the appointment.');
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

    // Convert time to 24-hour format for backend
    const time24 = convertTo24Hour(selectedTime);

    // Send appointment request to backend
    fetch('/ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            reason,
            other_reason: reason === 'other' ? otherReason : null,
            date: selectedDate,
            time: time24,
            child_id: childId
        })
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            // Reset form
            appointmentReasonSelect.value = '';
            otherReasonInput.value = '';
            otherReasonGroup.style.display = 'none';
            selectedDateInput.value = '';
            selectedTimeInput.value = '';
            if (childSelect) childSelect.value = '';
            document.querySelectorAll('.day').forEach(day => day.classList.remove('selected'));
            document.querySelectorAll('.timeslot').forEach(slot => slot.classList.remove('selected'));
            timeSlotsDiv.innerHTML = '';
        }
    })
    .catch(error => {
        console.error('Error creating appointment:', error);
        alert('Failed to submit appointment request.');
    });
}

function convertTo24Hour(time) {
    const [hourStr, period] = time.split(' ');
    let hour = parseInt(hourStr);
    if (period === 'PM' && hour !== 12) hour += 12;
    if (period === 'AM' && hour === 12) hour = 0;
    return `${hour.toString().padStart(2, '0')}:00:00`;
}

function showAppointmentsModal() {
    appointmentsModal.classList.add('active');
    loadAppointments('upcoming');
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
    loadAppointments(tabId);
}

function loadAppointments(type) {
    const tabContent = document.getElementById(`${type}-tab`);
    tabContent.innerHTML = '<p>Loading...</p>';

    fetch(`/ajax.php?action=appointments&type=${type}`)
        .then(response => response.json())
        .then(data => {
            tabContent.innerHTML = '';
            if (data.success && data.appointments.length > 0) {
                data.appointments.forEach(appt => {
                    const card = document.createElement('div');
                    card.classList.add('appointment-card');
                    card.dataset.id = appt.id;
                    if (type === 'past') card.classList.add('past');

                    const reason = appt.reason === 'other' ? appt.other_reason : appt.reason;
                    const childInfo = userType === 'K-6' && appt.child_name ? `<div class="detail-item">Child: ${appt.child_name}</div>` : '';
                    card.innerHTML = `
                        <h3>${reason}</h3>
                        <div class="appointment-details">
                            ${childInfo}
                            <div class="detail-item">Date: ${new Date(appt.appointment_date).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</div>
                            <div class="detail-item">Time: ${appt.appointment_time.slice(0, 5)}</div>
                            <div class="detail-item">Status: ${appt.status.charAt(0).toUpperCase() + appt.status.slice(1)}</div>
                        </div>
                        <div class="appointment-actions">
                            ${type === 'upcoming' && appt.status !== 'cancelled' ? `<button class="action-btn cancel-btn" onclick="cancelAppointment('${appt.id}')">Cancel Appointment</button>` : ''}
                            ${type === 'past' ? `<button class="action-btn delete-btn" onclick="deleteRecord('${appt.id}')">Delete Record</button>` : ''}
                        </div>
                    `;
                    tabContent.appendChild(card);
                });
            } else {
                tabContent.innerHTML = `<p>No ${type} appointments found.</p>`;
            }
        })
        .catch(error => {
            console.error('Error loading appointments:', error);
            tabContent.innerHTML = '<p>Error loading appointments.</p>';
        });
}

function cancelAppointment(appointmentId) {
    if (confirm("Are you sure you want to cancel this appointment?")) {
        fetch('/ajax.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'cancel', appointment_id: appointmentId })
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                loadAppointments('upcoming');
            }
        })
        .catch(error => {
            console.error('Error cancelling appointment:', error);
            alert('Failed to cancel appointment.');
        });
    }
}

function deleteRecord(recordId) {
    if (confirm("Are you sure you want to delete this record? This action cannot be undone.")) {
        // Note: Deletion not implemented in backend for simplicity
        alert('Record deletion not implemented in this version.');
    }
}

// Highlight current page in sidebar
document.addEventListener("DOMContentLoaded", function() {
    const buttons = {
        "homepage": "about-btn",
        "announcements": "announcement-btn",
        "appointment": "appointment-btn",
        "upload": "upload-btn",
        "profile": "profile-btn",
        "logout": "logout-btn"
    };
    
    const currentPage = window.location.pathname.split("/").pop().replace('.php', '');
    if (buttons[currentPage]) {
        document.getElementById(buttons[currentPage]).classList.add("active");
    }
});