document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('uploadForm');
    const userTypeSelect = document.getElementById('userType');
    const submitBtn = document.getElementById('submitBtn');
    const submitText = document.getElementById('submitText');
    const sections = {
        'K-6': 'k6Fields',
        'Highschool': 'highschoolFields',
        'Senior High School': 'seniorHighschoolFields',
        'College': 'collegeFields',
        'Employee': 'employeeFields',
        'Incoming Freshman': 'incomingFreshmanFields'
    };

    // Initialize - hide all sections and remove required attributes
    document.querySelectorAll('.document-section').forEach(section => {
        section.style.display = 'none';
        section.querySelectorAll('input, select').forEach(field => {
            field.removeAttribute('required');
        });
    });

    // Handle user type selection changes
    userTypeSelect.addEventListener('change', function() {
        // Remove required from all fields first
        document.querySelectorAll('.document-section input, .document-section select').forEach(field => {
            field.removeAttribute('required');
            field.classList.remove('error-border');
        });
        
        // Hide all sections
        document.querySelectorAll('.document-section').forEach(section => {
            section.style.display = 'none';
        });
        
        // Show selected section and add required to its fields
        const selectedType = this.value;
        if (selectedType && sections[selectedType]) {
            const activeSection = document.getElementById(sections[selectedType]);
            activeSection.style.display = 'block';
            activeSection.querySelectorAll('input, select').forEach(field => {
                field.setAttribute('required', 'required');
            });
            submitBtn.style.display = 'block';
        } else {
            submitBtn.style.display = 'none';
        }
    });

    // Form submission handler
    form.addEventListener('submit', function(e) {
        // Validate form before submission
        if (!validateForm()) {
            e.preventDefault();
            return;
        }
        
        // Show loading state
        submitBtn.classList.add('loading');
        submitText.textContent = 'Processing...';
        submitBtn.disabled = true;
    });

    // Initialize form if userType is preselected
    if (userTypeSelect.value) {
        userTypeSelect.dispatchEvent(new Event('change'));
    }
});

function validateForm() {
    const userType = document.getElementById('userType').value;
    if (!userType) {
        alert('Please select a user type');
        return false;
    }
    
    // Get the visible section
    const activeSection = document.querySelector('.document-section[style="display: block;"]');
    if (!activeSection) return false;
    
    let isValid = true;
    
    // Validate all required fields in active section
    activeSection.querySelectorAll('[required]').forEach(field => {
        if (field.type === 'file') {
            if (!field.files || field.files.length === 0) {
                field.classList.add('error-border');
                isValid = false;
            } else {
                field.classList.remove('error-border');
            }
        } else if (!field.value.trim()) {
            field.classList.add('error-border');
            isValid = false;
        } else {
            field.classList.remove('error-border');
        }
    });
    
    if (!isValid) {
        alert('Please complete all required fields');
    }
    
    return isValid;
}

function addStudentIdField() {
    const container = document.getElementById('studentIdContainer');
    const childCount = container.querySelectorAll('[id^="studentName"]').length;
    
    if (childCount >= 5) {
        alert('Maximum of 5 children allowed.');
        return;
    }
    
    const newField = document.createElement('div');
    newField.className = 'student-field mb-3 border-top pt-3';
    newField.innerHTML = `
        <div class="mb-3">
            <label for="studentName${childCount}" class="form-label">Student Name (${childCount + 1})</label>
            <input type="text" class="form-control" id="studentName${childCount}" name="studentName${childCount}" required>
        </div>
        <div class="mb-3">
            <label for="studentType${childCount}" class="form-label">Student Type (${childCount + 1})</label>
            <select class="form-select" id="studentType${childCount}" name="studentType${childCount}" required>
                <option value="">Select type</option>
                <option value="Kindergarten">Kindergarten</option>
                <option value="Elementary">Elementary</option>
            </select>
        </div>
        <div class="mb-3">
            <label for="studentId${childCount}" class="form-label">Student ID (${childCount + 1})</label>
            <input type="file" class="form-control" id="studentId${childCount}" name="studentId${childCount}" required>
        </div>
    `;
    
    container.appendChild(newField);
}