document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing forms');
    // Initialize first step of first student
    if (document.getElementById('step1-0')) {
        document.getElementById('step1-0').classList.add('active');
    }

    // Initialize all student forms
    const studentCount = document.querySelectorAll('.student-form').length;
    console.log(`Found ${studentCount} student forms`);
    for (let i = 0; i < studentCount; i++) {
        toggleMenstrualSection(i); // Initialize menstrual section
        if (i > 0) toggleEmergencyContactCopy(i); // Initialize emergency contact copying
        toggleOtherInput(`otherPastIllnessCheckbox${i}`, `otherPastIllnessInput${i}`); // Initialize other illness
        toggleOtherInput(`otherFamilyCheckbox${i}`, `otherFamilyInput${i}`); // Initialize other family history
        updateGradeLevelDropdown(i); // Initialize grade level dropdown
    }

    // Add form submit event listener
    const form = document.getElementById('healthProfileForm');
    if (form) {
        form.addEventListener('submit', function(event) {
            event.preventDefault(); // Prevent default submission
            submitForm();
        });
    }
});

// Tab switching functionality
document.querySelectorAll('.student-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.student-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.student-form').forEach(f => f.classList.remove('active'));
        
        this.classList.add('active');
        const targetForm = document.getElementById(this.dataset.target);
        if (targetForm) targetForm.classList.add('active');
    });
});

// Navigation between students
function nextStudent(currentIndex) {
    const nextIndex = currentIndex + 1;
    const studentForms = document.querySelectorAll('.student-form');
    if (nextIndex < studentForms.length) {
        document.querySelector(`.student-tab[data-target="studentForm${currentIndex}"]`)?.classList.remove('active');
        document.querySelector(`.student-tab[data-target="studentForm${nextIndex}"]`)?.classList.add('active');
        
        document.getElementById(`studentForm${currentIndex}`)?.classList.remove('active');
        document.getElementById(`studentForm${nextIndex}`)?.classList.add('active');
        
        // Reset to step 1 for the next student
        document.getElementById(`step1-${nextIndex}`).style.display = 'block';
        document.getElementById(`step2-${nextIndex}`).style.display = 'none';
        document.getElementById(`step3-${nextIndex}`).style.display = 'none';
    }
}

function prevStudent(currentIndex) {
    const prevIndex = currentIndex - 1;
    if (prevIndex >= 0) {
        document.querySelector(`.student-tab[data-target="studentForm${currentIndex}"]`)?.classList.remove('active');
        document.querySelector(`.student-tab[data-target="studentForm${prevIndex}"]`)?.classList.add('active');
        
        document.getElementById(`studentForm${currentIndex}`)?.classList.remove('active');
        document.getElementById(`studentForm${prevIndex}`)?.classList.add('active');
        
        // Reset to step 1 for the previous student
        document.getElementById(`step1-${prevIndex}`).style.display = 'block';
        document.getElementById(`step2-${prevIndex}`).style.display = 'none';
        document.getElementById(`step3-${prevIndex}`).style.display = 'none';
    }
}

// Form step navigation
function nextStep(index) {
    if (validateForm(index)) {
        document.getElementById(`step1-${index}`).style.display = 'none';
        document.getElementById(`step2-${index}`).style.display = 'block';
    }
}

function prevStep(index) {
    document.getElementById(`step1-${index}`).style.display = 'block';
    document.getElementById(`step2-${index}`).style.display = 'none';
}

function nextStep2(index) {
    if (validateForm(index)) {
        document.getElementById(`step2-${index}`).style.display = 'none';
        document.getElementById(`step3-${index}`).style.display = 'block';
    }
}

function prevStep2(index) {
    document.getElementById(`step2-${index}`).style.display = 'block';
    document.getElementById(`step3-${index}`).style.display = 'none';
}

// Form submission
function submitForm() {
    const studentForms = document.querySelectorAll('.student-form');
    let isValid = true;
    const errors = [];

    // Validate all students' required fields
    studentForms.forEach((form, index) => {
        if (!validateForm(index, true)) {
            isValid = false;
            const formErrors = validateForm(index, true); // Collect errors
            errors.push(...formErrors);
        }
    });

    if (!isValid) {
        // Display errors in modal
        const errorList = document.getElementById('errorList');
        errorList.innerHTML = '';
        errors.forEach(error => {
            const li = document.createElement('li');
            li.textContent = error;
            errorList.appendChild(li);
        });

        const modal = new bootstrap.Modal(document.getElementById('validationModal'), {
            backdrop: 'static',
            keyboard: false
        });
        modal.show();

        // Scroll to the first invalid field
        const firstInvalid = document.querySelector('.is-invalid');
        if (firstInvalid) {
            const headerOffset = 100; // Adjust based on fixed header height
            const elementPosition = firstInvalid.getBoundingClientRect().top + window.pageYOffset;
            const offsetPosition = elementPosition - headerOffset;

            window.scrollTo({
                top: offsetPosition,
                behavior: 'smooth'
            });

            firstInvalid.focus();
        }
    } else {
        // Submit the form
        document.getElementById('healthProfileForm').submit();
    }
}

// Update grade level dropdown based on student type
function updateGradeLevelDropdown(index) {
    const studentTypeInput = document.querySelector(`[name="studentType${index}"]`);
    const gradeLevelSelect = document.querySelector(`[name="gradeLevel${index}"]`);
    
    if (!studentTypeInput || !gradeLevelSelect) {
        console.warn(`Student type input or grade level select not found for student ${index}`);
        return;
    }

    const studentType = studentTypeInput.value;
    const currentValue = gradeLevelSelect.value;
    console.log(`Updating grade level dropdown for student ${index}`);
    console.log(`Student type: ${studentType}, current grade level: ${currentValue}`);

    // Define options based on student type
    const options = studentType === 'Kindergarten' 
        ? [
            { value: '', text: 'Select' },
            { value: 'kinder1', text: 'Kindergarten 1' },
            { value: 'kinder2', text: 'Kindergarten 2' }
        ]
        : studentType === 'Elementary'
        ? [
            { value: '', text: 'Select' },
            { value: '1', text: 'Grade 1' },
            { value: '2', text: 'Grade 2' },
            { value: '3', text: 'Grade 3' },
            { value: '4', text: 'Grade 4' },
            { value: '5', text: 'Grade 5' },
            { value: '6', text: 'Grade 6' }
        ]
        : [
            { value: '', text: 'Select' },
            { value: 'kinder1', text: 'Kindergarten 1' },
            { value: 'kinder2', text: 'Kindergarten 2' },
            { value: '1', text: 'Grade 1' },
            { value: '2', text: 'Grade 2' },
            { value: '3', text: 'Grade 3' },
            { value: '4', text: 'Grade 4' },
            { value: '5', text: 'Grade 5' },
            { value: '6', text: 'Grade 6' }
        ];

    console.log(`Filtered options for ${studentType}:`, options);

    // Clear and repopulate dropdown
    gradeLevelSelect.innerHTML = '';
    options.forEach(option => {
        const opt = document.createElement('option');
        opt.value = option.value;
        opt.textContent = option.text;
        if (option.value === currentValue) {
            opt.selected = true;
        }
        gradeLevelSelect.appendChild(opt);
    });

    console.log(`Set grade level value to: ${gradeLevelSelect.value}`);
}

// Image preview function
function displayImage(input, previewId) {
    const preview = document.getElementById(previewId);
    const uploadText = document.getElementById(`uploadText${previewId.replace('previewImage', '')}`);
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
            uploadText.style.display = 'none';
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Calculate age based on birthday
function calculateAge(index) {
    const birthdayInput = document.getElementById(`birthday${index}`);
    const ageInput = document.getElementById(`age${index}`);
    const errorMessage = document.getElementById(`birthdayError${index}`);

    errorMessage.textContent = '';
    const birthday = new Date(birthdayInput.value);
    
    if (!isNaN(birthday.getTime())) {
        const today = new Date();
        if (birthday > today) {
            ageInput.value = '';
            errorMessage.textContent = "Birthday cannot be in the future.";
            birthdayInput.classList.add('is-invalid');
        } else {
            const ageDiff = today - birthday.getTime();
            const ageDate = new Date(ageDiff);
            const calculatedAge = Math.abs(ageDate.getUTCFullYear() - 1970);
            ageInput.value = calculatedAge;
            birthdayInput.classList.remove('is-invalid');
        }
    } else {
        ageInput.value = '';
    }
}

// Toggle menstrual section based on sex
function toggleMenstrualSection(index) {
    const sex = document.getElementById(`sex${index}`)?.value;
    const menstrualSection = document.getElementById(`menstrualSection${index}`);
    
    if (menstrualSection) {
        menstrualSection.style.display = sex === 'female' ? 'block' : 'none';
    }
}

// Medication table functions
function addMedicationRow(index) {
    const table = document.getElementById(`medicationsTable${index}`)?.getElementsByTagName('tbody')[0];
    if (!table) return;
    
    const rowCount = table.rows.length;
    if (rowCount >= 10) {
        alert('Maximum of 10 medications allowed.');
        return;
    }
    
    const newRow = table.insertRow();
    newRow.innerHTML = `
        <td>
            <select class="table-input drug-select" name="medications${index}[${rowCount}][drug]" onchange="handleDrugSelect(this)">
                <option value="">Select a drug</option>
                <option value="paracetamol">Paracetamol</option>
                <option value="ibuprofen">Ibuprofen</option>
                <option value="amoxicillin">Amoxicillin</option>
                <option value="fluticasone">Fluticasone</option>
                <option value="budesonide">Budesonide</option>
                <option value="montelukast">Montelukast</option>
                <option value="cetirizine">Cetirizine</option>
                <option value="methylphenidate">Methylphenidate</option>
                <option value="lisdexamfetamine">Lisdexamfetamine</option>
                <option value="guanfacine">Guanfacine</option>
                <option value="insulin">Insulin</option>
                <option value="levetiracetam">Levetiracetam</option>
                <option value="valproic_acid">Valproic Acid</option>
                <option value="other">Other</option>
            </select>
            <input type="text" class="table-input other-input" name="medications${index}[${rowCount}][drug_other]" placeholder="Enter drug name" style="display: none;">
        </td>
        <td>
            <div class="dose-options">
                <input type="number" class="table-input" name="medications${index}[${rowCount}][dose]" placeholder="Dose" style="width: 80px;">
                <select class="table-input" name="medications${index}[${rowCount}][unit]">
                    <option value="mg">mg</option>
                    <option value="g">g</option>
                    <option value="ml">ml</option>
                    <option value="units">units</option>
                </select>
                <select class="table-input" name="medications${index}[${rowCount}][frequency]">
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
            <button type="button" class="remove-btn" onclick="removeMedicationRow(this, ${index})">×</button>
        </td>
    `;
}

function removeMedicationRow(button, index) {
    const row = button.parentNode.parentNode;
    const table = document.getElementById(`medicationsTable${index}`)?.getElementsByTagName('tbody')[0];
    
    if (table && table.rows.length > 1) {
        row.remove();
    } else {
        alert("At least one medication row is required.");
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
function toggleSurgeryFields(index, show) {
    const surgeryDetails = document.getElementById(`surgeryDetails${index}`);
    if (surgeryDetails) {
        surgeryDetails.style.display = show ? 'block' : 'none';
    }
}

function addSurgeryRow(index) {
    const table = document.getElementById(`surgeryTable${index}`)?.getElementsByTagName('tbody')[0];
    if (!table) return;
    
    const rowCount = table.rows.length;
    if (rowCount >= 5) {
        alert('Maximum of 5 surgeries/admissions allowed.');
        return;
    }
    
    const newRow = table.insertRow();
    newRow.innerHTML = `
        <td>
            <input type="number" class="table-input" name="hospital_admissions${index}[${rowCount}][year]" min="1900" max="2025" placeholder="e.g., 2015">
        </td>
        <td>
            <input type="text" class="table-input" name="hospital_admissions${index}[${rowCount}][reason]" placeholder="e.g., Appendectomy">
        </td>
        <td>
            <button type="button" class="remove-btn" onclick="removeSurgeryRow(this, ${index})">×</button>
        </td>
    `;
}

function removeSurgeryRow(button, index) {
    const row = button.parentNode.parentNode;
    const table = document.getElementById(`surgeryTable${index}`)?.getElementsByTagName('tbody')[0];
    
    if (table && table.rows.length > 1) {
        row.remove();
    } else {
        alert("At least one surgery/admission row is required when 'Yes' is selected.");
    }
}

// Toggle functions for other inputs
function toggleOtherPastIllness(index) {
    const checkbox = document.getElementById(`otherPastIllnessCheckbox${index}`);
    const input = document.getElementById(`otherPastIllnessInput${index}`);
    
    if (checkbox && input) {
        input.style.display = checkbox.checked ? 'block' : 'none';
        input.required = checkbox.checked;
    }
}

function toggleOtherFamilyInput(index) {
    const checkbox = document.getElementById(`otherFamilyCheckbox${index}`);
    const input = document.getElementById(`otherFamilyInput${index}`);
    
    if (checkbox && input) {
        input.style.display = checkbox.checked ? 'block' : 'none';
        input.required = checkbox.checked;
    }
}

function toggleOtherRelationshipInput(index) {
    const select = document.getElementById(`visibleEmergencyRelationship${index}`);
    const otherWrapper = document.getElementById(`otherRelationshipWrapper${index}`);
    const otherInput = document.getElementById(`otherRelationship${index}`);

    if (select && otherWrapper && otherInput) {
        if (select.value === 'Other') {
            otherWrapper.style.display = 'block';
            otherInput.required = true;
        } else {
            otherWrapper.style.display = 'none';
            otherInput.required = false;
            otherInput.value = '';
        }
    }
}

function copyEmergencyContactToAll() {
    // Get values from the first student's visible fields
    const firstSurname = document.getElementById('visibleEmergencySurname0')?.value || '';
    const firstFirstname = document.getElementById('visibleEmergencyFirstname0')?.value || '';
    const firstMiddlename = document.getElementById('visibleEmergencyMiddlename0')?.value || '';
    const firstContactNumber = document.getElementById('visibleEmergencyContactNumber0')?.value || '';
    const firstRelationship = document.getElementById('visibleEmergencyRelationship0')?.value || '';
    const firstCityAddress = document.getElementById('visibleEmergencyCityAddress0')?.value || '';
    const firstOtherRelationship = document.getElementById('otherRelationship0')?.value || '';

    // Update hidden fields for the first student
    const hiddenFields0 = {
        surname: document.getElementById('hiddenEmergencySurname0'),
        firstname: document.getElementById('hiddenEmergencyFirstname0'),
        middlename: document.getElementById('hiddenEmergencyMiddlename0'),
        contactNumber: document.getElementById('hiddenEmergencyContactNumber0'),
        relationship: document.getElementById('hiddenEmergencyRelationship0'),
        cityAddress: document.getElementById('hiddenEmergencyCityAddress0'),
        otherRelationship: document.getElementById('hiddenOtherRelationship0')
    };

    if (hiddenFields0.surname) hiddenFields0.surname.value = firstSurname;
    if (hiddenFields0.firstname) hiddenFields0.firstname.value = firstFirstname;
    if (hiddenFields0.middlename) hiddenFields0.middlename.value = firstMiddlename;
    if (hiddenFields0.contactNumber) hiddenFields0.contactNumber.value = firstContactNumber;
    if (hiddenFields0.relationship) hiddenFields0.relationship.value = firstRelationship;
    if (hiddenFields0.cityAddress) hiddenFields0.cityAddress.value = firstCityAddress;
    if (hiddenFields0.otherRelationship) hiddenFields0.otherRelationship.value = firstOtherRelationship;

    // Copy to other students if sameEmergencyContact is checked
    const studentCount = document.querySelectorAll('.student-form').length;
    for (let i = 1; i < studentCount; i++) {
        if (document.getElementById(`sameEmergencyContact${i}`)?.checked) {
            // Update visible fields
            const visibleFields = {
                surname: document.getElementById(`visibleEmergencySurname${i}`),
                firstname: document.getElementById(`visibleEmergencyFirstname${i}`),
                middlename: document.getElementById(`visibleEmergencyMiddlename${i}`),
                contactNumber: document.getElementById(`visibleEmergencyContactNumber${i}`),
                relationship: document.getElementById(`visibleEmergencyRelationship${i}`),
                cityAddress: document.getElementById(`visibleEmergencyCityAddress${i}`),
                otherRelationship: document.getElementById(`otherRelationship${i}`)
            };

            if (visibleFields.surname) visibleFields.surname.value = firstSurname;
            if (visibleFields.firstname) visibleFields.firstname.value = firstFirstname;
            if (visibleFields.middlename) visibleFields.middlename.value = firstMiddlename;
            if (visibleFields.contactNumber) visibleFields.contactNumber.value = firstContactNumber;
            if (visibleFields.relationship) visibleFields.relationship.value = firstRelationship;
            if (visibleFields.cityAddress) visibleFields.cityAddress.value = firstCityAddress;
            if (visibleFields.otherRelationship) visibleFields.otherRelationship.value = firstOtherRelationship;

            // Update hidden fields
            const hiddenFields = {
                surname: document.getElementById(`hiddenEmergencySurname${i}`),
                firstname: document.getElementById(`hiddenEmergencyFirstname${i}`),
                middlename: document.getElementById(`hiddenEmergencyMiddlename${i}`),
                contactNumber: document.getElementById(`hiddenEmergencyContactNumber${i}`),
                relationship: document.getElementById(`hiddenEmergencyRelationship${i}`),
                cityAddress: document.getElementById(`hiddenEmergencyCityAddress${i}`),
                otherRelationship: document.getElementById(`hiddenOtherRelationship${i}`)
            };

            if (hiddenFields.surname) hiddenFields.surname.value = firstSurname;
            if (hiddenFields.firstname) hiddenFields.firstname.value = firstFirstname;
            if (hiddenFields.middlename) hiddenFields.middlename.value = firstMiddlename;
            if (hiddenFields.contactNumber) hiddenFields.contactNumber.value = firstContactNumber;
            if (hiddenFields.relationship) hiddenFields.relationship.value = firstRelationship;
            if (hiddenFields.cityAddress) hiddenFields.cityAddress.value = firstCityAddress;
            if (hiddenFields.otherRelationship) hiddenFields.otherRelationship.value = firstOtherRelationship;

            // Toggle visibility of otherRelationshipWrapper
            toggleOtherRelationshipInput(i);
        }
    }
}

// Toggle other input fields
function toggleOtherInput(checkboxId, inputId) {
    const checkbox = document.getElementById(checkboxId);
    const input = document.getElementById(inputId);
    
    if (checkbox && input) {
        if (checkbox.checked) {
            input.style.display = 'block';
            input.required = true;
        } else {
            input.style.display = 'none';
            input.required = false;
            input.value = '';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const studentCount = document.querySelectorAll('.student-form').length;
    for (let i = 0; i < studentCount; i++) {
        // Restore menstrual section visibility
        toggleMenstrualSection(i);
        // Restore other input visibility
        toggleOtherInput(`otherPastIllnessCheckbox${i}`, `otherPastIllnessInput${i}`);
        toggleOtherInput(`otherFamilyCheckbox${i}`, `otherFamilyInput${i}`);
        toggleOtherRelationshipInput(i);
        toggleOtherReligionInput(i);
        // Restore surgery details visibility
        const surgeryYes = document.querySelector(`input[name="hospital_admission${i}"][value="Yes"]:checked`);
        toggleSurgeryFields(i, surgeryYes);
        // Update grade level dropdown
        updateGradeLevelDropdown(i);
        // Restore emergency contact copy state
        if (i > 0) toggleEmergencyContactCopy(i);
    }
});

// Copy address to all students
function copyAddressToAll() {
    const studentCount = document.querySelectorAll('.student-form').length;
    if (studentCount <= 1) return;

    const cityAddress = document.querySelector('[name="cityAddress0"]')?.value || '';
    const provincialAddress = document.querySelector('[name="provincialAddress0"]')?.value || '';

    for (let i = 1; i < studentCount; i++) {
        document.querySelector(`[name="cityAddress${i}"]`).value = cityAddress;
        document.querySelector(`[name="provincialAddress${i}"]`).value = provincialAddress;
    }
}

document.querySelector('[name="cityAddress0"]')?.addEventListener('change', copyAddressToAll);
document.querySelector('[name="provincialAddress0"]')?.addEventListener('change', copyAddressToAll);

// Phone number validation
function validatePhoneNumber(input) {
    const phoneRegex = /^09[0-9]{9}$/;
    input.value = input.value.replace(/[^0-9]/g, '');
    if (input.value && !phoneRegex.test(input.value)) {
        input.classList.add('is-invalid');
    } else {
        input.classList.remove('is-invalid');
    }
}

document.querySelectorAll('input[type="tel"][name^="contactNumber"], input[type="tel"][name^="emergencyContactNumber"]').forEach(input => {
    input.addEventListener('input', function() {
        validatePhoneNumber(this);
    });
});

function toggleEmergencyContactCopy(index) {
    if (index === 0) return;
    
    const checkbox = document.getElementById(`sameEmergencyContact${index}`);
    if (!checkbox) return;
    
    const inputs = [
        `visibleEmergencySurname${index}`, 
        `visibleEmergencyFirstname${index}`, 
        `visibleEmergencyMiddlename${index}`, 
        `visibleEmergencyContactNumber${index}`, 
        `visibleEmergencyRelationship${index}`, 
        `visibleEmergencyCityAddress${index}`,
        `otherRelationship${index}`
    ];
    
    const hiddenInputs = [
        `hiddenEmergencySurname${index}`, 
        `hiddenEmergencyFirstname${index}`, 
        `hiddenEmergencyMiddlename${index}`, 
        `hiddenEmergencyContactNumber${index}`, 
        `hiddenEmergencyRelationship${index}`, 
        `hiddenEmergencyCityAddress${index}`,
        `hiddenOtherRelationship${index}`
    ];
    
    inputs.forEach(name => {
        const input = document.getElementById(name);
        if (!input) return;
        
        input.disabled = checkbox.checked;
        if (checkbox.checked) {
            input.removeAttribute('required');
            const sourceName = name.replace(`Emergency${index}`, 'Emergency0').replace(`otherRelationship${index}`, 'otherRelationship0');
            const sourceInput = document.getElementById(sourceName);
            if (sourceInput) input.value = sourceInput.value;
        } else {
            input.setAttribute('required', name.includes('Middlename') || name.includes('otherRelationship') ? '' : 'required');
        }
    });
    
    hiddenInputs.forEach(name => {
        const hiddenInput = document.getElementById(name);
        if (!hiddenInput) return;
        
        if (checkbox.checked) {
            const sourceName = name.replace(`Emergency${index}`, 'Emergency0').replace(`hiddenOtherRelationship${index}`, 'otherRelationship0');
            const sourceInput = document.getElementById(sourceName);
            if (sourceInput) hiddenInput.value = sourceInput.value;
        } else {
            const visibleInput = document.getElementById(name.replace('hidden', 'visible').replace(`hiddenOtherRelationship${index}`, `otherRelationship${index}`));
            if (visibleInput) hiddenInput.value = visibleInput.value;
        }
    });
    
    toggleOtherRelationshipInput(index);
    
    const vaccinationRadios = document.querySelectorAll(`input[name="vaccination${index}"]`);
    vaccinationRadios.forEach(radio => {
        if (checkbox.checked) {
            radio.removeAttribute('required');
            const firstStudentRadio = document.querySelector(`input[name="vaccination0"]:checked`);
            if (firstStudentRadio) {
                radio.checked = (radio.value === firstStudentRadio.value);
            }
        } else {
            radio.setAttribute('required', 'required');
        }
    });
}

// Initialize emergency contact copying
document.addEventListener('DOMContentLoaded', function() {
    const studentCount = document.querySelectorAll('.student-form').length;
    for (let i = 1; i < studentCount; i++) {
        toggleEmergencyContactCopy(i);
    }
});

function validateForm(index, collectErrorsOnly = false) {
    let isValid = true;
    const errors = [];
    
    // Clear previous invalid states
    document.querySelectorAll('.is-invalid').forEach(el => {
        el.classList.remove('is-invalid');
    });
    document.querySelectorAll('.invalid-feedback').forEach(el => {
        el.style.display = 'none';
    });

    const form = document.getElementById(`studentForm${index}`);
    if (!form || !form.classList.contains('active')) return true;
    
    // Determine current step
    let currentStep = 1;
    if (document.getElementById(`step2-${index}`).style.display === 'block') {
        currentStep = 2;
    } else if (document.getElementById(`step3-${index}`).style.display === 'block') {
        currentStep = 3;
    }


    // Step 1 validation
    if (currentStep === 1) {
        const requiredFields = [
            { name: `surname${index}`, label: 'Surname' },
            { name: `firstname${index}`, label: 'First name' },
            { name: `birthday${index}`, label: 'Birthday' },
            { name: `sex${index}`, label: 'Gender' },
            { name: `gradeLevel${index}`, label: 'Grade level' },
            { name: `gradingQuarter${index}`, label: 'Grading quarter' },
            { name: `religion${index}`, label: 'Religion' },
            { name: `nationality${index}`, label: 'Nationality' },
            { name: `email${index}`, label: 'Email address' },
            { name: `contactNumber${index}`, label: 'Contact number' },
            { name: `cityAddress${index}`, label: 'City address' }
        ];

        const religionSelect = document.getElementById(`religion${index}`);
        const otherReligionInput = document.getElementById(`otherReligion${index}`);
        if (religionSelect && !religionSelect.value) {
            isValid = false;
            errors.push(`Student ${index + 1}: Religion is required`);
            religionSelect.classList.add('is-invalid');
        } else if (religionSelect.value === 'OTHER' && (!otherReligionInput || !otherReligionInput.value.trim())) {
            isValid = false;
            errors.push(`Student ${index + 1}: Please specify your religion`);
            otherReligionInput.classList.add('is-invalid');
        }

        requiredFields.forEach(field => {
            const input = document.querySelector(`[name="${field.name}"]`);
            if (input && !input.disabled) {
                if (!input.value.trim()) {
                    isValid = false;
                    errors.push(`Student ${index + 1}: ${field.label} is required`);
                    input.classList.add('is-invalid');
                } else if (field.name.includes('email')) {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(input.value)) {
                        isValid = false;
                        errors.push(`Student ${index + 1}: Please enter a valid email address`);
                        input.classList.add('is-invalid');
                    }
                } else if (field.name.includes('contactNumber')) {
                    const phoneRegex = /^09[0-9]{9}$/;
                    if (!phoneRegex.test(input.value)) {
                        isValid = false;
                        errors.push(`Student ${index + 1}: Contact number must be 11 digits starting with 09`);
                        input.classList.add('is-invalid');
                    }
                } else if (field.name.includes('birthday')) {
                    const birthday = new Date(input.value);
                    const today = new Date();
                    if (isNaN(birthday.getTime()) || birthday > today) {
                        isValid = false;
                        errors.push(`Student ${index + 1}: Please enter a valid past birthday`);
                        input.classList.add('is-invalid');
                    }
                } else if (field.name.includes('gradeLevel')) {
                    const studentType = document.querySelector(`[name="studentType${index}"]`)?.value;
                    const validGrades = studentType === 'Kindergarten' 
                        ? ['kinder1', 'kinder2'] 
                        : studentType === 'Elementary' 
                        ? ['1', '2', '3', '4', '5', '6'] 
                        : [];
                    if (!validGrades.includes(input.value)) {
                        isValid = false;
                        errors.push(`Student ${index + 1}: Please select a valid grade level for ${studentType}`);
                        input.classList.add('is-invalid');
                    }
                }
            }
        });

        // Emergency contact validation
        const isEmergencyCopied = index > 0 && document.getElementById(`sameEmergencyContact${index}`)?.checked;
        if (!isEmergencyCopied || index === 0) {
            const emergencyFields = [
                { name: `emergencySurname${index}`, label: 'Emergency contact surname' },
                { name: `emergencyFirstname${index}`, label: 'Emergency contact first name' },
                { name: `emergencyContactNumber${index}`, label: 'Emergency contact number' },
                { name: `emergencyRelationship${index}`, label: 'Emergency contact relationship' },
                { name: `emergencyCityAddress${index}`, label: 'Emergency contact city address' }
            ];
            
            emergencyFields.forEach(field => {
                const input = document.querySelector(`[name="${field.name}"]`);
                if (input && !input.disabled) {
                    if (!input.value.trim()) {
                        isValid = false;
                        errors.push(`Student ${index + 1}: ${field.label} is required`);
                        input.classList.add('is-invalid');
                    } else if (field.name.includes('emergencyContactNumber')) {
                        const phoneRegex = /^09[0-9]{9}$/;
                        if (!phoneRegex.test(input.value)) {
                            isValid = false;
                            errors.push(`Student ${index + 1}: Emergency contact number must be 11 digits starting with 09`);
                            input.classList.add('is-invalid');
                        }
                    }
                }
            });
        
            // Additional check for "Other" relationship
            const relationshipSelect = document.getElementById(`visibleEmergencyRelationship${index}`);
            const otherRelationshipInput = document.getElementById(`otherRelationship${index}`);
            if (relationshipSelect && relationshipSelect.value === 'Other' && 
                (!otherRelationshipInput || !otherRelationshipInput.value.trim())) {
                isValid = false;
                errors.push(`Student ${index + 1}: Please specify the emergency contact relationship`);
                otherRelationshipInput.classList.add('is-invalid');
            }
        }
    }

    // Step 2 validation
if (currentStep === 2) {
    const isEmergencyCopied = index > 0 && document.getElementById(`sameEmergencyContact${index}`)?.checked;
    if (isEmergencyCopied && index > 0) {
        // Check first student's vaccination status
        const firstStudentVaccination = document.querySelector(`input[name="vaccination0"]:checked`);
        if (!firstStudentVaccination) {
            isValid = false;
            errors.push(`Student ${index + 1}: Please select a COVID vaccination status for the first student`);
            const vaccinationGroup = document.getElementById(`vaccinationGroup${index}`);
            if (vaccinationGroup) {
                const feedback = vaccinationGroup.querySelector('.invalid-feedback');
                if (feedback) feedback.style.display = 'block';
            }
        }
    } else {
        const vaccinationRadios = document.querySelectorAll(`input[name="vaccination${index}"]`);
        let isVaccinationSelected = false;
        vaccinationRadios.forEach(radio => {
            if (radio.checked) isVaccinationSelected = true;
        });
        if (!isVaccinationSelected) {
            isValid = false;
            errors.push(`Student ${index + 1}: Please select a COVID vaccination status`);
            const vaccinationGroup = document.getElementById(`vaccinationGroup${index}`);
            if (vaccinationGroup) {
                const feedback = vaccinationGroup.querySelector('.invalid-feedback');
                if (feedback) feedback.style.display = 'block';
            }
        }
    }
}

    // Step 3 has no required fields
    if (currentStep === 3) {
        return true;
    }

    // If collecting errors only, return them
    if (collectErrorsOnly) {
        return errors.length > 0 ? errors : true;
    }

    // If validation fails, show modal
    if (!isValid) {
        const errorList = document.getElementById('errorList');
        errorList.innerHTML = '';
        errors.forEach(error => {
            const li = document.createElement('li');
            li.textContent = error;
            errorList.appendChild(li);
        });

        const modal = new bootstrap.Modal(document.getElementById('validationModal'), {
            backdrop: 'static',
            keyboard: false
        });
        modal.show();

        const firstInvalid = form.querySelector('.is-invalid');
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

// Update initialization in DOMContentLoaded
document.addEventListener('DOMContentLoaded', function() {
    const studentCount = document.querySelectorAll('.student-form').length;
    for (let i = 0; i < studentCount; i++) {
        toggleOtherRelationshipInput(i);
        document.getElementById(`emergencyRelationship${i}`)?.addEventListener('change', function() {
            toggleOtherRelationshipInput(i);
        });
    }
});

document.addEventListener('DOMContentLoaded', function() {
    // ... existing initialization
    
    const studentCount = document.querySelectorAll('.student-form').length;
    for (let i = 0; i < studentCount; i++) {
        toggleOtherReligionInput(i);
        document.getElementById(`religion${i}`)?.addEventListener('change', function() {
            toggleOtherReligionInput(i);
        });
    }
});

function toggleOtherReligionInput(index) {
    const religionSelect = document.getElementById(`religion${index}`);
    const otherWrapper = document.getElementById(`otherReligionWrapper${index}`);
    const otherInput = document.getElementById(`otherReligion${index}`);
    
    if (religionSelect.value === 'OTHER') {
        otherWrapper.style.display = 'block';
        otherInput.required = true;
    } else {
        otherWrapper.style.display = 'none';
        otherInput.required = false;
        otherInput.value = '';
    }
}

