document.addEventListener('DOMContentLoaded', function () {
    console.log('DOM loaded, initializing form, URL:', window.location.href, 'DOM ready:', document.readyState);

    // Log initial DOM state for critical elements
    const errorList = document.getElementById('missingFieldsList');
    const modalElement = document.getElementById('missingFieldsModal');
    const form = document.getElementById('healthProfileForm');
    console.log('Initial DOM check:', {
        missingFieldsListExists: !!errorList,
        missingFieldsModalExists: !!modalElement,
        healthProfileFormExists: !!form
    });

    // Preserve form step if errors exist
    const step = sessionStorage.getItem('formStep') || '1';
    if (step === '1') {
        document.getElementById('upperSections').style.display = 'block';
        document.getElementById('step2').style.display = 'none';
        document.getElementById('step3').style.display = 'none';
    } else if (step === '2') {
        document.getElementById('upperSections').style.display = 'none';
        document.getElementById('step2').style.display = 'block';
        document.getElementById('step3').style.display = 'none';
    } else if (step === '3') {
        document.getElementById('upperSections').style.display = 'none';
        document.getElementById('step2').style.display = 'none';
        document.getElementById('step3').style.display = 'block';
    }

    // Ensure email is lowercase on load
    const emailInput = document.getElementById('email');
    if (emailInput) {
        emailInput.value = emailInput.value.toLowerCase();
        emailInput.addEventListener('keydown', preventCapitalization);
    }

    // Fetch user email
    fetchUserEmail();

    // Initialize form behaviors
    toggleMenstrualSection();
    updateCourseDropdown();

    const relationshipSelect = document.getElementById('emergencyRelationship');
    if (relationshipSelect) {
        relationshipSelect.addEventListener('change', toggleOtherRelationshipInput);
        toggleOtherRelationshipInput(); // Initialize on page load
    }

    // Add form submit event listener
    if (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            submitForm();
        });
    }

    // Initialize event listeners for phone numbers and checkboxes
    ['contactNumber', 'emergencyContactNumber'].forEach(id => {
        const input = document.getElementById(id);
        if (input) {
            input.addEventListener('input', () => validatePhoneNumber(input));
        }
    });

    ['otherPastIllnessCheckbox', 'cancerCheckbox', 'allergyCheckbox', 'otherFamilyCheckbox'].forEach(id => {
        const checkbox = document.getElementById(id);
        if (checkbox) {
            checkbox.addEventListener('change', () => {
                if (id === 'otherPastIllnessCheckbox') toggleOtherPastIllness();
                if (id === 'cancerCheckbox') toggleCancerInput();
                if (id === 'allergyCheckbox') toggleAllergyInput();
                if (id === 'otherFamilyCheckbox') toggleOtherFamilyInput();
            });
        }
    });

    const departmentSelect = document.getElementById('department');
    if (departmentSelect) {
        departmentSelect.addEventListener('change', updateCourseDropdown);
    }

    // Setup real-time validation
    setupRealTimeValidation();

    // Display server-side errors if any
    const errorModal = document.getElementById('errorModal');
    if (errorModal && errorModal.querySelector('.modal-body').textContent.trim()) {
        try {
            const modal = new bootstrap.Modal(errorModal, {
                backdrop: 'static',
                keyboard: false
            });
            modal.show();
        } catch (e) {
            console.error('Failed to show errorModal:', e);
        }
    }
});

// Prevent capital letters in email
function preventCapitalization(e) {
    if (e.key.length === 1 && e.key >= 'A' && e.key <= 'Z') {
        e.preventDefault();
        const input = e.target;
        const cursorPos = input.selectionStart;
        input.value = input.value.substring(0, cursorPos) +
            e.key.toLowerCase() +
            input.value.substring(cursorPos);
        input.setSelectionRange(cursorPos + 1, cursorPos + 1);
        return false;
    }
    return true;
}

// Fetch user email via AJAX to auto-fill
function fetchUserEmail() {
    fetch(window.location.pathname + '?action=get_email', { credentials: 'same-origin' })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.email) {
                const emailInput = document.getElementById('email');
                emailInput.value = data.email.toLowerCase();
            } else if (data.error) {
                console.warn('Failed to fetch email:', data.error);
            }
        })
        .catch(error => {
            console.error('Error fetching email:', error);
        });
}

// Form step navigation
function nextStep() {
    if (validateForm(1)) {
        sessionStorage.setItem('formStep', '2');
        document.getElementById('upperSections').style.display = 'none';
        document.getElementById('step2').style.display = 'block';
        document.getElementById('step3').style.display = 'none';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

function prevStep() {
    sessionStorage.setItem('formStep', '1');
    document.getElementById('upperSections').style.display = 'block';
    document.getElementById('step2').style.display = 'none';
    document.getElementById('step3').style.display = 'none';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function nextStep2() {
    if (validateForm(2)) {
        sessionStorage.setItem('formStep', '3');
        document.getElementById('upperSections').style.display = 'none';
        document.getElementById('step2').style.display = 'none';
        document.getElementById('step3').style.display = 'block';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

function prevStep2() {
    sessionStorage.setItem('formStep', '2');
    document.getElementById('upperSections').style.display = 'none';
    document.getElementById('step2').style.display = 'block';
    document.getElementById('step3').style.display = 'none';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Form submission
async function submitForm() {
    let isValid = true;
    const errors = [];

    // Validate all steps
    [1, 2, 3].forEach(step => {
        const stepErrors = validateForm(step, true);
        if (stepErrors.length > 0) {
            isValid = false;
            errors.push(...stepErrors);
        }
    });

    if (!isValid) {
        const errorList = document.getElementById('missingFieldsList');
        const modalElement = document.getElementById('missingFieldsModal');
        const form = document.getElementById('healthProfileForm');

        if (!errorList || !modalElement) {
            console.error('Modal elements missing:', { errorListExists: !!errorList, modalElementExists: !!modalElement });
            alert('Form validation failed due to missing elements. Errors: ' + errors.join(', ') + '\nPlease refresh the page or contact support.');
            if (form) {
                const errorDiv = document.createElement('div');
                errorDiv.className = 'fallback-error';
                errorDiv.style.color = 'red';
                errorDiv.style.marginBottom = '15px';
                errorDiv.style.padding = '10px';
                errorDiv.style.border = '1px solid red';
                errorDiv.innerHTML = `<strong>Missing Fields:</strong> ${errors.join(', ')}`;
                form.prepend(errorDiv);
            }
            return;
        }

        errorList.innerHTML = '';
        errors.forEach(error => {
            const li = document.createElement('li');
            li.textContent = error;
            errorList.appendChild(li);
        });

        try {
            const modal = new bootstrap.Modal(modalElement, {
                backdrop: 'static',
                keyboard: false
            });
            modal.show();
        } catch (e) {
            console.error('Failed to initialize modal:', e);
            alert('Error displaying validation modal. Errors: ' + errors.join(', ') + '\nPlease refresh the page.');
            if (form) {
                const errorDiv = document.createElement('div');
                errorDiv.className = 'fallback-error';
                errorDiv.style.color = 'red';
                errorDiv.style.marginBottom = '15px';
                errorDiv.style.padding = '10px';
                errorDiv.style.border = '1px solid red';
                errorDiv.innerHTML = `<strong>Missing Fields:</strong> ${errors.join(', ')}`;
                form.prepend(errorDiv);
            }
        }

        const firstInvalid = document.querySelector('.is-invalid');
        if (firstInvalid) {
            const headerOffset = 100;
            const elementPosition = firstInvalid.getBoundingClientRect().top + window.pageYOffset;
            const offsetPosition = elementPosition - headerOffset;

            window.scrollTo({
                top: offsetPosition,
                behavior: 'smooth'
            });
            firstInvalid.focus();
        }
        return;
    }

    // Submit form via fetch to handle response
    try {
        const form = document.getElementById('healthProfileForm');
        const formData = new FormData(form);
        const response = await fetch('EmployeeForm.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        if (response.ok) {
            // Clear session storage and redirect
            sessionStorage.removeItem('formStep');
            window.location.href = 'uploaddocs.php';
        } else {
            const data = await response.json();
            const errorList = document.getElementById('missingFieldsList');
            errorList.innerHTML = '';
            const li = document.createElement('li');
            li.textContent = data.error || 'An error occurred during submission. Please try again.';
            errorList.appendChild(li);

            const modal = new bootstrap.Modal(document.getElementById('missingFieldsModal'), {
                backdrop: 'static',
                keyboard: false
            });
            modal.show();
        }
    } catch (error) {
        console.error('Submission error:', error);
        const errorList = document.getElementById('missingFieldsList');
        errorList.innerHTML = '';
        const li = document.createElement('li');
        li.textContent = 'An error occurred during submission. Please try again.';
        errorList.appendChild(li);

        try {
            const modal = new bootstrap.Modal(document.getElementById('missingFieldsModal'), {
                backdrop: 'static',
                keyboard: false
            });
            modal.show();
        } catch (e) {
            console.error('Failed to show submission error modal:', e);
            alert('Submission failed: ' + error.message);
        }
    }
}

function validateForm(step, collectErrorsOnly = false) {
    let isValid = true;
    const errors = [];

    // Log execution context
    console.log(`validateForm called for step ${step}, collectErrorsOnly: ${collectErrorsOnly}, URL: ${window.location.href}, DOM ready: ${document.readyState}`);

    // Check critical DOM elements
    const errorList = document.getElementById('missingFieldsList');
    const modalElement = document.getElementById('missingFieldsModal');
    const form = document.getElementById('healthProfileForm');
    console.log('validateForm DOM check:', {
        errorListExists: !!errorList,
        modalElementExists: !!modalElement,
        formExists: !!form,
        domSnippet: document.body ? document.body.innerHTML.substring(0, 200) : 'No body'
    });

    // Clear previous invalid states
    document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    document.querySelectorAll('.invalid-feedback').forEach(el => el.style.display = 'none');

    if (step === 1) {
        const requiredFields = [
            { name: 'surname', label: 'Surname' },
            { name: 'firstname', label: 'First name' },
            { name: 'birthday', label: 'Birthday' },
            { name: 'sex', label: 'Gender' },
            { name: 'religion', label: 'Religion' },
            { name: 'nationality', label: 'Nationality' },
            { name: 'civilStatus', label: 'Civil Status' },
            { name: 'email', label: 'Email address' },
            { name: 'contactNumber', label: 'Contact number' },
            { name: 'cityAddress', label: 'City address' },
       
            { name: 'emergencySurname', label: 'Emergency contact surname' },
            { name: 'emergencyFirstname', label: 'Emergency contact first name' },
            { name: 'emergencyContactNumber', label: 'Emergency contact number' },
            { name: 'emergencyRelationship', label: 'Emergency contact relationship' },
            { name: 'emergencyCityAddress', label: 'Emergency contact city address' }
        ];

        const userType = document.querySelector('input[name="user_type"]')?.value || '';
        if (userType === 'high_school') {
            requiredFields.push(
                { name: 'Grades', label: 'Grade' },
                { name: 'gradeLevel', label: 'Grading quarter' }
            );
        } else if (userType === 'senior_high' || userType === 'incoming freshman') {
            requiredFields.push(
                { name: 'Grades', label: 'Grade' },
                { name: 'Track/Strand', label: 'Track/Strand' },
                { name: 'section', label: 'Section' },
                { name: 'Sem', label: 'Semester', optional: userType === 'incoming freshman' }
            );
        } else if (userType === 'college') {
            requiredFields.push(
                { name: 'department', label: 'Department' },
                { name: 'course', label: 'Course' },
                { name: 'Sem', label: 'Semester' },
                { name: 'yearLevel', label: 'Year Level' }
            );
        } else if (userType === 'employee') {
            requiredFields.push(
                { name: 'department', label: 'Department' },
                { name: 'position', label: 'Position' },
                { name: 'employeeId', label: 'Employee ID' }
            );
        }

        requiredFields.forEach(field => {
            const input = document.querySelector(`[name="${field.name}"]`);
            if (input && !input.disabled) {
                const wrapper = input.closest('.input-wrapper');
                const feedback = wrapper ? wrapper.querySelector('.invalid-feedback') : input.nextElementSibling;
                if (!input.value.trim() && !field.optional) {
                    isValid = false;
                    errors.push(`${field.label} is required`);
                    input.classList.add('is-invalid');
                    if (feedback && feedback.classList.contains('invalid-feedback')) {
                        feedback.style.display = 'block';
                    }
                } else if (field.name === 'email') {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(input.value)) {
                        isValid = false;
                        errors.push('Please enter a valid email address');
                        input.classList.add('is-invalid');
                        if (feedback && feedback.classList.contains('invalid-feedback')) {
                            feedback.style.display = 'block';
                        }
                    }
                } else if (field.name.includes('contactNumber')) {
                    const phoneRegex = /^09[0-9]{9}$/;
                    if (!phoneRegex.test(input.value)) {
                        isValid = false;
                        errors.push(`${field.label} must be 11 digits starting with 09`);
                        input.classList.add('is-invalid');
                        if (feedback && feedback.classList.contains('invalid-feedback')) {
                            feedback.style.display = 'block';
                        }
                    }
                } else if (field.name === 'birthday') {
                    const birthday = new Date(input.value);
                    const today = new Date();
                    if (isNaN(birthday.getTime()) || birthday > today) {
                        isValid = false;
                        errors.push('Please enter a valid past birthday');
                        input.classList.add('is-invalid');
                        if (feedback && feedback.classList.contains('invalid-feedback')) {
                            feedback.style.display = 'block';
                        }
                    }
                } else if (field.name === 'yearLevel' && userType === 'college') {
                    const year = parseInt(input.value);
                    if (isNaN(year) || year < 1 || year > 5) {
                        isValid = false;
                        errors.push('Year Level must be between 1 and 5');
                        input.classList.add('is-invalid');
                        if (feedback && feedback.classList.contains('invalid-feedback')) {
                            feedback.style.display = 'block';
                        }
                    }
                }

          
                else if (field.name === 'emergencyRelationship') {
                    if (input.value === 'Other') {
                        const otherInput = document.querySelector('[name="other_relationship"]');
                        if (!otherInput.value.trim()) {
                            isValid = false;
                            errors.push('Please specify the emergency contact relationship');
                            otherInput.classList.add('is-invalid');
                            const otherFeedback = otherInput.nextElementSibling;
                            if (otherFeedback && otherFeedback.classList.contains('invalid-feedback')) {
                                otherFeedback.style.display = 'block';
                            }
                        }
                    }
                }
            }
        });

        const religion = document.querySelector('[name="religion"]');
        if (religion && religion.value === 'OTHER') {
            const otherReligion = document.querySelector('[name="other_religion"]');
            if (!otherReligion.value.trim()) {
                isValid = false;
                errors.push('Please specify the religion');
                otherReligion.classList.add('is-invalid');
                const feedback = otherReligion.nextElementSibling;
                if (feedback && feedback.classList.contains('invalid-feedback')) {
                    feedback.style.display = 'block';
                }
            }
        }

        if (userType === 'senior_high' || userType === 'incoming freshman') {
            const trackStrand = document.querySelector('[name="Track/Strand"]');
            if (trackStrand && trackStrand.value === 'OTHER') {
                const otherTrackStrand = document.querySelector('[name="other_track_strand"]');
                if (!otherTrackStrand.value.trim()) {
                    isValid = false;
                    errors.push('Please specify the track/strand');
                    otherTrackStrand.classList.add('is-invalid');
                    const feedback = otherTrackStrand.nextElementSibling;
                    if (feedback && feedback.classList.contains('invalid-feedback')) {
                        feedback.style.display = 'block';
                    }
                }
            }
        }
    }

    if (step === 2) {
        const vaccinationRadios = document.querySelectorAll('input[name="vaccination"]');
        let isVaccinationSelected = false;
        vaccinationRadios.forEach(radio => {
            if (radio.checked) isVaccinationSelected = true;
        });
        if (!isVaccinationSelected) {
            isValid = false;
            errors.push('Please select a COVID vaccination status');
            const vaccinationGroup = document.getElementById('vaccinationGroup');
            if (vaccinationGroup) {
                const feedback = vaccinationGroup.querySelector('.invalid-feedback');
                if (feedback) feedback.style.display = 'block';
            }
        }

        const medicationRows = document.querySelectorAll('#medicationsTable tbody tr');
        medicationRows.forEach((row, index) => {
            const drug = row.querySelector(`select[name="medications[${index}][drug]"]`).value;
            const otherDrug = row.querySelector(`input[name="medications[${index}][drug_other]"]`).value;
            const dose = row.querySelector(`input[name="medications[${index}][dose]"]`).value;
            const unit = row.querySelector(`select[name="medications[${index}][unit]"]`).value;
            const frequency = row.querySelector(`select[name="medications[${index}][frequency]"]`).value;

        const hasAnyValue = drug || dose || unit || frequency || otherDrug;
if (hasAnyValue) {
    // validate
    if (drug === 'other' && !otherDrug.trim()) {
        isValid = false;
        errors.push('Please specify the medication name for row ' + (index + 1));
    }
    if (drug && (!dose || !unit || !frequency)) {
        isValid = false;
        errors.push('Please fill in all medication details for row ' + (index + 1));
    }
}

        });
    }

    if (step === 3) {
        const hospitalAdmission = document.querySelector('input[name="hospital_admission"]:checked');
        if (hospitalAdmission && hospitalAdmission.value === 'Yes') {
            const surgeryRows = document.querySelectorAll('#surgeryTable tbody tr');
            surgeryRows.forEach((row, index) => {
                const year = row.querySelector(`input[name="hospital_admissions[${index}][year]"]`).value;
                const reason = row.querySelector(`input[name="hospital_admissions[${index}][reason]"]`).value;
                if (!year || !reason) {
                    isValid = false;
                    errors.push('Please fill in all fields for hospital admission/surgery ' + (index + 1));
                }
            });
        }
    }

    if (collectErrorsOnly) {
        return errors;
    }

    if (!isValid && !collectErrorsOnly) {
        // Fallback if modal elements are missing
        if (!errorList || !modalElement || !form) {
            const errorMessage = `Validation failed: Missing page elements (errorList: ${!!errorList}, modal: ${!!modalElement}, form: ${!!form}). Errors: ${errors.join(', ')}`;
            console.error(errorMessage);

            // Display alert as last resort
            alert('Form validation failed: ' + errors.join(', ') + '\nPlease refresh the page or contact support.');

            // Fallback UI: Prepend errors to form
            if (form) {
                let errorDiv = form.querySelector('.fallback-error');
                if (!errorDiv) {
                    errorDiv = document.createElement('div');
                    errorDiv.className = 'fallback-error';
                    errorDiv.style.color = 'red';
                    errorDiv.style.marginBottom = '15px';
                    errorDiv.style.padding = '10px';
                    errorDiv.style.border = '1px solid red';
                    form.prepend(errorDiv);
                }
                errorDiv.innerHTML = `<strong>Missing Fields:</strong> ${errors.join(', ')}`;
            } else {
                console.error('HealthProfileForm not found for fallback UI');
            }
            return isValid;
        }

        // Populate error list
        errorList.innerHTML = '';
        errors.forEach(error => {
            const li = document.createElement('li');
            li.textContent = error;
            errorList.appendChild(li);
        });

        console.log('Attempting to show missingFieldsModal with errors:', errors);

        // Show modal with retry mechanism
        let attempts = 0;
        const maxAttempts = 3;
        function tryShowModal() {
            try {
                if (!window.bootstrap || !window.bootstrap.Modal) {
                    throw new Error('Bootstrap Modal not available');
                }
                const modal = new bootstrap.Modal(modalElement, {
                    backdrop: 'static',
                    keyboard: false
                });
                modal.show();
                console.log('missingFieldsModal shown successfully');
            } catch (e) {
                attempts++;
                console.error(`Failed to show missingFieldsModal (attempt ${attempts}/${maxAttempts}):`, e);
                if (attempts < maxAttempts) {
                    setTimeout(tryShowModal, 500); // Retry after 500ms
                } else {
                    console.error('All attempts to show modal failed');
                    alert('Error displaying validation modal. Errors: ' + errors.join(', ') + '\nPlease refresh the page.');
                    if (form) {
                        let errorDiv = form.querySelector('.fallback-error');
                        if (!errorDiv) {
                            errorDiv = document.createElement('div');
                            errorDiv.className = 'fallback-error';
                            errorDiv.style.color = 'red';
                            errorDiv.style.marginBottom = '15px';
                            errorDiv.style.padding = '10px';
                            errorDiv.style.border = '1px solid red';
                            form.prepend(errorDiv);
                        }
                        errorDiv.innerHTML = `<strong>Missing Fields:</strong> ${errors.join(', ')}`;
                    }
                }
            }
        }
        tryShowModal();

        // Focus on first invalid field
        const firstInvalid = document.querySelector('.is-invalid');
        if (firstInvalid) {
            const headerOffset = 100;
            const elementPosition = firstInvalid.getBoundingClientRect().top + window.pageYOffset;
            const offsetPosition = elementPosition - headerOffset;
            window.scrollTo({
                top: offsetPosition,
                behavior: 'smooth'
            });
            firstInvalid.focus();
        }
    }

    return isValid;
}

// Phone number validation
function validatePhoneNumber(input) {
    input.value = input.value.replace(/[^0-9]/g, '');
    const phoneRegex = /^09[0-9]{9}$/;
    if (input.value && !phoneRegex.test(input.value)) {
        input.classList.add('is-invalid');
    } else {
        input.classList.remove('is-invalid');
    }
}

// Image preview function
function displayImage(input, previewId) {
    const preview = document.getElementById(previewId);
    const uploadText = document.getElementById('uploadText');

    if (input.files && input.files[0]) {
        const reader = new FileReader();

        reader.onload = function (e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
            uploadText.style.display = 'none';
        }

        reader.readAsDataURL(input.files[0]);
    }
}

// Calculate age based on birthday
function calculateAge() {
    const birthdayInput = document.getElementById('birthday');
    const ageInput = document.getElementById('age');
    const errorMessage = document.getElementById('birthdayError') || document.createElement('div');

    errorMessage.textContent = '';
    const birthday = new Date(birthdayInput.value);

    if (!isNaN(birthday.getTime())) {
        const today = new Date();
        if (birthday > today) {
            ageInput.value = '';
            errorMessage.textContent = 'Birthday cannot be in the future.';
            birthdayInput.classList.add('is-invalid');
        } else {
            const ageDiff = today - birthday.getTime();
            const ageDate = new Date(ageDiff);
            const calculatedAge = Math.abs(ageDate.getUTCFullYear() - 1970);
            if (calculatedAge < 11) {
                ageInput.value = '';
                errorMessage.textContent = 'Age must be at least 11 years old.';
                birthdayInput.classList.add('is-invalid');
            } else {
                ageInput.value = calculatedAge;
                birthdayInput.classList.remove('is-invalid');
            }
        }
    } else {
        ageInput.value = '';
        birthdayInput.classList.add('is-invalid');
    }
    validateField(birthdayInput);
}

// Toggle menstrual section based on sex
function toggleMenstrualSection() {
    const sex = document.getElementById('sex').value;
    document.getElementById('menstrualSection').style.display = sex === 'female' ? 'block' : 'none';
}

// Update course dropdown based on department
function updateCourseDropdown() {
    const departmentSelect = document.getElementById('department');
    const courseSelect = document.getElementById('course');
    if (!departmentSelect || !courseSelect) return;

    const department = departmentSelect.value;
    courseSelect.innerHTML = '<option value="">Select Course</option>';

    const coursesByDepartment = {
        'CLA': [
            { value: 'BAComm', name: 'BA in Communication' },
            { value: 'BAPsych', name: 'BA in Psychology' },
            { value: 'BAEnglish', name: 'BA in English' },
            { value: 'BAHistory', name: 'BA in History' }
        ],
        'CSM': [
            { value: 'BSBio', name: 'BS in Biology' },
            { value: 'BSChem', name: 'BS in Chemistry' },
            { value: 'BSMath', name: 'BS in Mathematics' }
        ],
        'COE': [
            { value: 'BSCivEng', name: 'BS in Civil Engineering' },
            { value: 'BSElecEng', name: 'BS in Electrical Engineering' }
        ],
        'CTE': [
            { value: 'BSEdEng', name: 'BSEd in English' },
            { value: 'BSEdMath', name: 'BSEd in Mathematics' }
        ],
        'COA': [
            { value: 'BSArch', name: 'BS in Architecture' }
        ],
        'CON': [
            { value: 'BSN', name: 'BS in Nursing' }
        ],
        'CA': [
            { value: 'BSAgri', name: 'BS in Agriculture' },
            { value: 'BSAgriBus', name: 'BS in Agribusiness' },
            { value: 'BSFoodTech', name: 'BS in Food Technology' }
        ],
        'CFES': [
            { value: 'BSForestry', name: 'BS in Forestry' },
            { value: 'BSEnvSci', name: 'BS in Environmental Science' }
        ],
        'CCJE': [
            { value: 'BSCrim', name: 'BS in Criminology' }
        ],
        'CHE': [
            { value: 'BSHomeEcon', name: 'BS in Home Economics' }
        ],
        'CCS': [
            { value: 'BSCompSci', name: 'BS in Computer Science' },
            { value: 'BSInfoTech', name: 'BS in Information Technology' }
        ],
        'COM': [
            { value: 'MD', name: 'Doctor of Medicine' }
        ],
        'CPADS': [
            { value: 'BSPubAdmin', name: 'BS in Public Administration' }
        ],
        'CSSPE': [
            { value: 'BSSportsSci', name: 'BS in Sports Science' }
        ],
        'CSWCD': [
            { value: 'BSSocWork', name: 'BS in Social Work' }
        ],
        'CAIS': [
            { value: 'BAIslamic', name: 'BA in Islamic Studies' }
        ]
    };

    if (coursesByDepartment[department]) {
        coursesByDepartment[department].forEach(course => {
            const option = document.createElement('option');
            option.value = course.value;
            option.textContent = course.name;

            console.log(selectedCourse)

            // ✅ Apply selected attribute if matches PHP value
            if (course.value === selectedCourse) {
                option.selected = true;
            }

            courseSelect.appendChild(option);
        });
    }
}


// Medication table functions
function addMedicationRow() {
    const table = document.getElementById('medicationsTable').getElementsByTagName('tbody')[0];
    const rowCount = table.rows.length;
    if (rowCount >= 10) {
        alert('Maximum of 10 medications allowed.');
        return;
    }

    const newRow = table.insertRow();
    newRow.innerHTML = `
        <td>
            <select class="table-input drug-select" name="medications[${rowCount}][drug]" onchange="handleDrugSelect(this)">
                <option value="">Select a drug</option>
                <option value="paracetamol">Paracetamol</option>
                <option value="ibuprofen">Ibuprofen</option>
                <option value="amoxicillin">Amoxicillin</option>
                <option value="metformin">Metformin</option>
                <option value="atorvastatin">Atorvastatin</option>
                <option value="losartan">Losartan</option>
                <option value="omeprazole">Omeprazole</option>
                <option value="simvastatin">Simvastatin</option>
                <option value="aspirin">Aspirin</option>
                <option value="levothyroxine">Levothyroxine</option>
                <option value="other">Other</option>
            </select>
            <input type="text" class="table-input other-input" name="medications[${rowCount}][drug_other]" placeholder="Enter drug name" style="display: none;">
        </td>
        <td>
            <div class="dose-options">
                <input type="number" class="table-input" name="medications[${rowCount}][dose]" placeholder="Dose" style="width: 80px;">
                <select class="table-input" name="medications[${rowCount}][unit]">
                    <option value="mg">mg</option>
                    <option value="g">g</option>
                    <option value="ml">ml</option>
                    <option value="units">units</option>
                </select>
                <select class="table-input" name="medications[${rowCount}][frequency]">
                           <option value="">Select Frequency</option>
                    <option value="once daily">Once daily</option>
                    <option value="twice daily">Twice daily</option>
                    <option value="three times daily">Three times daily</option>
                    <option value="four times daily">Four times daily</option>
                    <option value="as needed">As needed</option>
                </select>
            </div>
        </td>
        <td>
            <button type="button" class="remove-btn" onclick="removeMedicationRow(this)">×</button>
        </td>
    `;
}

function removeMedicationRow(button) {
    const row = button.parentNode.parentNode;
    const table = document.getElementById('medicationsTable').getElementsByTagName('tbody')[0];
    if (table.rows.length > 1) {
        row.remove();
    } else {
        alert('At least one medication row is required.');
    }
}

function handleDrugSelect(select) {
    const otherInput = select.parentNode.querySelector('.other-input');
    if (select.value === 'other') {
        otherInput.style.display = 'inline-block';
        otherInput.required = true;
    } else {
        otherInput.style.display = 'none';
        otherInput.required = false;
        otherInput.value = '';
    }
}

// Surgery table functions
function toggleSurgeryFields(show) {
    document.getElementById('surgeryDetails').style.display = show ? 'block' : 'none';
}

function addSurgeryRow() {
    const table = document.getElementById('surgeryTable').getElementsByTagName('tbody')[0];
    const rowCount = table.rows.length;
    if (rowCount >= 5) {
        alert('Maximum of 5 surgeries/admissions allowed.');
        return;
    }

    const newRow = table.insertRow();
    newRow.innerHTML = `
        <td>
            <input type="number" class="table-input" name="hospital_admissions[${rowCount}][year]" min="1900" max="2025" placeholder="e.g., 2015">
        </td>
        <td>
            <input type="text" class="table-input" name="hospital_admissions[${rowCount}][reason]" placeholder="e.g., Appendectomy">
        </td>
        <td>
            <button type="button" class="remove-btn" onclick="removeSurgeryRow(this)">×</button>
        </td>
    `;
}

function removeSurgeryRow(button) {
    const row = button.parentNode.parentNode;
    const table = document.getElementById('surgeryTable').getElementsByTagName('tbody')[0];
    if (table.rows.length > 1) {
        row.remove();
    } else {
        alert('At least one surgery/admission row is required when "Yes" is selected.');
    }
}

// Toggle other input fields
function toggleOtherPastIllness() {
    toggleOtherInput('otherPastIllnessCheckbox', 'otherPastIllnessInput');
}

function toggleCancerInput() {
    toggleOtherInput('cancerCheckbox', 'cancerInput');
}

function toggleAllergyInput() {
    toggleOtherInput('allergyCheckbox', 'allergyInput');
}

function toggleOtherFamilyInput() {
    toggleOtherInput('otherFamilyCheckbox', 'otherFamilyInput');
}

function toggleOtherInput(checkboxId, inputId) {
    const checkbox = document.getElementById(checkboxId);
    const input = document.getElementById(inputId);
    if (checkbox && input) {
        input.style.display = checkbox.checked ? 'block' : 'none';
        input.classList.toggle('d-none', !checkbox.checked);
        input.required = checkbox.checked;
    }
}

// Toggle other religion input
function toggleOtherReligionInput() {
    const religionSelect = document.getElementById('religion');
    const otherWrapper = document.getElementById('otherReligionWrapper');
    const otherInput = document.getElementById('otherReligion');

    if (religionSelect.value === 'OTHER') {
        otherWrapper.style.display = 'block';
        otherInput.required = true;
    } else {
        otherWrapper.style.display = 'none';
        otherInput.required = false;
        otherInput.value = '';
    }
}

// Toggle other track/strand input
function toggleOtherTrackStrandInput() {
    const select = document.getElementById('Track/Strand');
    const otherWrapper = document.getElementById('otherTrackStrandWrapper');
    const otherInput = document.getElementById('otherTrackStrand');

    if (select && otherWrapper && otherInput) {
        if (select.value === 'OTHER') {
            otherWrapper.style.display = 'block';
            otherInput.required = true;
        } else {
            otherWrapper.style.display = 'none';
            otherInput.required = false;
            otherInput.value = '';
        }
    }
}

// Toggle other relationship input
function toggleOtherRelationshipInput() {
    const relationshipSelect = document.getElementById('emergencyRelationship');
    const otherWrapper = document.getElementById('otherRelationshipWrapper');
    const otherInput = document.getElementById('otherRelationship');

    if (relationshipSelect.value === 'Other') {
        otherWrapper.style.display = 'block';
        otherInput.required = true;
    } else {
        otherWrapper.style.display = 'none';
        otherInput.required = false;
        otherInput.value = '';
    }
}

// Validate a single field
function validateField(input) {
    const wrapper = input.closest('.input-wrapper') || input.parentElement;
    const feedback = wrapper ? wrapper.querySelector('.invalid-feedback') : input.nextElementSibling;
    let isValid = true;
    let errorMessage = '';

    // Skip validation for disabled or hidden fields
    if (input.disabled || (input.closest('.form-step') && input.closest('.form-step').style.display === 'none')) {
        input.classList.remove('is-invalid');
        if (feedback && feedback.classList.contains('invalid-feedback')) {
            feedback.style.display = 'none';
        }
        return true;
    }

    const fieldName = input.name;
    const value = input.value.trim();
    const isOptional = ['middlename', 'suffix', 'bloodType', 'provincialAddress', 'emergencyMiddlename'].includes(fieldName);

    if (!isOptional && !value && input.type !== 'radio') {
        isValid = false;
        errorMessage = `Please enter a valid ${getFieldLabel(fieldName)}`;
    } else if (fieldName === 'email') {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            isValid = false;
            errorMessage = 'Please enter a valid email address';
        }
    } else if (fieldName.includes('contactNumber')) {
        const phoneRegex = /^09[0-9]{9}$/;
        if (value && !phoneRegex.test(value)) {
            isValid = false;
            errorMessage = 'Must be 11 digits starting with 09';
        }
    } else if (fieldName === 'birthday') {
        const birthday = new Date(value);
        const today = new Date();
        if (isNaN(birthday.getTime())) {
            isValid = false;
            errorMessage = 'Please enter a valid date';
        } else if (birthday > today) {
            isValid = false;
            errorMessage = 'Birthday cannot be in the future';
        } else {
            const ageDiff = today - birthday.getTime();
            const ageDate = new Date(ageDiff);
            const calculatedAge = Math.abs(ageDate.getUTCFullYear() - 1970);
            if (calculatedAge < 11) {
                isValid = false;
                errorMessage = 'Age must be at least 11 years old';
            }
        }
    } else if (fieldName === 'vaccination') {
        const radios = document.querySelectorAll('input[name="vaccination"]');
        let isChecked = false;
        radios.forEach(radio => {
            if (radio.checked) isChecked = true;
        });
        if (!isChecked) {
            isValid = false;
            errorMessage = 'Please select a COVID vaccination status';
        }
    } else if (fieldName === 'other_relationship' && document.getElementById('emergencyRelationship').value === 'Other') {
        if (!value) {
            isValid = false;
            errorMessage = 'Please specify the emergency contact relationship';
        }
    } else if (fieldName === 'other_religion' && document.getElementById('religion').value === 'OTHER') {
        if (!value) {
            isValid = false;
            errorMessage = 'Please specify the religion';
        }
    } else if (fieldName === 'other_track_strand' && document.getElementById('Track/Strand')?.value === 'OTHER') {
        if (!value) {
            isValid = false;
            errorMessage = 'Please specify the track/strand';
        }
    }

    // Update field state
    if (!isValid) {
        input.classList.add('is-invalid');
        if (feedback && feedback.classList.contains('invalid-feedback')) {
            feedback.textContent = errorMessage;
            feedback.style.display = 'block';
        }
    } else {
        input.classList.remove('is-invalid');
        if (feedback && feedback.classList.contains('invalid-feedback')) {
            feedback.style.display = 'none';
        }
    }

    return isValid;
}

// Get field label for error messages
function getFieldLabel(fieldName) {
    const labels = {
        surname: 'Surname',
        firstname: 'First name',
        middlename: 'Middle name',
        suffix: 'Suffix',
        birthday: 'Birthday',
        sex: 'Gender',
        bloodType: 'Blood type',
        religion: 'Religion',
        nationality: 'Nationality',
        civilStatus: 'Civil status',
        email: 'Email address',
        contactNumber: 'Contact number',
        cityAddress: 'City address',
        provincialAddress: 'Provincial address',
        emergencySurname: 'Emergency contact surname',
        emergencyFirstname: 'Emergency contact first name',
        emergencyMiddlename: 'Emergency contact middle name',
        emergencyContactNumber: 'Emergency contact number',
        emergencyRelationship: 'Emergency contact relationship',
        emergencyCityAddress: 'Emergency contact city address',
      
        Grades: 'Grade',
        gradeLevel: 'Grading quarter',
        'Track/Strand': 'Track/Strand',
        section: 'Section',
        Sem: 'Semester',
        department: 'Department',
        course: 'Course',
        yearLevel: 'Year level',
        position: 'Position',
        employeeId: 'Employee ID',
        vaccination: 'Vaccination status',
        other_relationship: 'Emergency contact relationship',
        other_religion: 'Religion',
        other_track_strand: 'Track/Strand'
    };
    return labels[fieldName] || fieldName;
}

// Setup real-time validation for all form fields
function setupRealTimeValidation() {
    const fields = [
        { name: 'surname', type: 'text' },
        { name: 'firstname', type: 'text' },
        { name: 'middlename', type: 'text', optional: true },
        { name: 'suffix', type: 'text', optional: true },
        { name: 'birthday', type: 'date' },
        { name: 'sex', type: 'select' },
        { name: 'bloodType', type: 'select', optional: true },
        { name: 'religion', type: 'select' },
        { name: 'nationality', type: 'text' },
        { name: 'civilStatus', type: 'select' },
        { name: 'email', type: 'email' },
        { name: 'contactNumber', type: 'tel' },
        { name: 'cityAddress', type: 'text' },
    
        { name: 'provincialAddress', type: 'text', optional: true },
        { name: 'emergencySurname', type: 'text' },
        { name: 'emergencyFirstname', type: 'text' },
        { name: 'emergencyMiddlename', type: 'text', optional: true },
        { name: 'emergencyContactNumber', type: 'tel' },
        { name: 'emergencyRelationship', type: 'select' },
        { name: 'other_relationship', type: 'text', optional: true },
        { name: 'emergencyCityAddress', type: 'text' },
        { name: 'other_religion', type: 'text', optional: true },
        { name: 'other_track_strand', type: 'text', optional: true },
        // User-type specific fields
        { name: 'Grades', type: 'select', userTypes: ['high_school', 'senior_high', 'incoming freshman'] },
        { name: 'gradeLevel', type: 'select', userTypes: ['high_school'] },
        { name: 'Track/Strand', type: 'select', userTypes: ['senior_high', 'incoming freshman'] },
        { name: 'section', type: 'text', userTypes: ['senior_high', 'incoming freshman'] },
        { name: 'Sem', type: 'select', userTypes: ['senior_high', 'incoming freshman', 'college'] },
        { name: 'department', type: 'select', userTypes: ['college'] },
        { name: 'department', type: 'text', userTypes: ['employee'] },
        { name: 'course', type: 'select', userTypes: ['college'] },
        { name: 'yearLevel', type: 'number', userTypes: ['college'] },
        { name: 'position', type: 'text', userTypes: ['employee'] },
        { name: 'employeeId', type: 'text', userTypes: ['employee'] },
        // Step 2 fields
        { name: 'vaccination', type: 'radio' }
    ];

    const userType = document.querySelector('input[name="user_type"]')?.value || '';

    fields.forEach(field => {
        if (field.userTypes && !field.userTypes.includes(userType) && field.name !== 'vaccination') {
            return; // Skip fields not applicable to the user type
        }

        if (field.type === 'radio') {
            const inputs = document.querySelectorAll(`input[name="${field.name}"]`);
            inputs.forEach(input => {
                input.addEventListener('change', () => validateField(input));
            });
        } else {
            const input = document.querySelector(`[name="${field.name}"]`);
            if (input) {
                if (field.type === 'select') {
                    input.addEventListener('change', () => validateField(input));
                } else if (field.type === 'date') {
                    input.addEventListener('change', () => {
                        validateField(input);
                        if (field.name === 'birthday') calculateAge();
                    });
                } else {
                    input.addEventListener('input', () => validateField(input));
                    input.addEventListener('blur', () => validateField(input));
                }
                // Initial validation
                if (input.value) validateField(input);
            }
        }
    });
}