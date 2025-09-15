let currentTooth = null;

document.addEventListener('DOMContentLoaded', function () {
    const teeth = document.querySelectorAll('.tooth');
    const modal = document.getElementById('toothModal');
    const modalToothNumber = document.getElementById('modalToothNumber');
    const modalTreatment = document.getElementById('modalTreatment');
    const modalStatus = document.getElementById('modalStatus');
    const closeModal = document.querySelector('.close');

    const savedData = sessionStorage.getItem('dentalFormData');
    if (savedData) {
        const formData = JSON.parse(savedData);
        for (const key in formData) {
            const element = document.querySelector(`[name="${key}"]`);
            if (element) {
                if (element.type === 'radio' || element.type === 'checkbox') {
                    const matchingInput = document.querySelector(`[name="${key}"][value="${formData[key]}"]`);
                    if (matchingInput) matchingInput.checked = true;
                } else {
                    element.value = formData[key];
                }
            }
        }

        if (formData.permanentTeethData) {
            formData.permanentTeethData.forEach(toothData => {
                const toothElement = document.querySelector(`.tooth[data-tooth="${toothData.number}"]`);
                if (toothElement) {
                    toothElement.setAttribute('data-treatment', toothData.treatment);
                    toothElement.setAttribute('data-status', toothData.status);
                    if (toothData.treatment || toothData.status) {
                        toothElement.innerHTML = `
                                    ${toothData.number}<br>
                                    <span class="treatment">${toothData.treatment}</span><br>
                                    <span class="status">${toothData.status}</span>
                                `;
                        toothElement.classList.add('active');
                    }
                }
            });
        }

        if (formData.temporaryTeethData) {
            formData.temporaryTeethData.forEach(toothData => {
                const toothElement = document.querySelector(`.tooth[data-tooth="${toothData.number}"]`);
                if (toothElement) {
                    toothElement.setAttribute('data-treatment', toothData.treatment);
                    toothElement.setAttribute('data-status', toothData.status);
                    if (toothData.treatment || toothData.status) {
                        toothElement.innerHTML = `
                                    ${toothData.number}<br>
                                    <span class="treatment">${toothData.treatment}</span><br>
                                    <span class="status">${toothData.status}</span>
                                `;
                        toothElement.classList.add('active');
                    }
                }
            });
        }
    }

    teeth.forEach(tooth => {
        tooth.addEventListener('click', function () {
            currentTooth = this;
            modalToothNumber.textContent = this.getAttribute('data-tooth');
            modalTreatment.value = this.getAttribute('data-treatment') || '';
            modalStatus.value = this.getAttribute('data-status') || '';
            modal.style.display = 'block';
        });
    });

    closeModal.addEventListener('click', function () {
        modal.style.display = 'none';
    });

    window.addEventListener('click', function (event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
});

function saveToothInfo() {
    const modal = document.getElementById('toothModal');
    const toothNumber = document.getElementById('modalToothNumber').textContent;
    const treatment = document.getElementById('modalTreatment').value;
    const status = document.getElementById('modalStatus').value;

    if (currentTooth) {
        currentTooth.setAttribute('data-treatment', treatment);
        currentTooth.setAttribute('data-status', status);
        if (treatment || status) {
            currentTooth.classList.add('active');
            currentTooth.innerHTML = `
                        ${toothNumber}<br>
                        <span class="treatment">${treatment}</span><br>
                        <span class="status">${status}</span>
                    `;
        } else {
            currentTooth.classList.remove('active');
            currentTooth.innerHTML = toothNumber;
        }
    }

    modal.style.display = 'none';
}

function validateForm(event) {
    event.preventDefault();

    const form = document.getElementById('dentalForm');

    if (!form.reportValidity()) {
        return false;
    }

    const age = document.getElementById('age').value;
    const examinedDate = document.getElementById('examinedDate').value;

    if (age < 1 || age > 120) {
        alert('Please enter a valid age (1-120).');
        return false;
    }

    const today = new Date().toISOString().split('T')[0];
    if (examinedDate > today) {
        alert('Examined date cannot be in the future.');
        return false;
    }

    saveForm();
    return false;
}

function saveForm() {
    const formData = new FormData(document.getElementById('dentalForm'));
    const data = {};
    formData.forEach((value, key) => {
        data[key] = value;
    });

    console.log('Form Data:', data);
    alert('Form data saved/submitted successfully!');
}

function saveAndContinue() {
    const permanentTeethData = [];
    document.querySelectorAll('.section:nth-child(2) .tooth').forEach(tooth => {
        const treatment = tooth.getAttribute('data-treatment') || '';
        const status = tooth.getAttribute('data-status') || '';
        if (treatment || status) {
            permanentTeethData.push({
                number: tooth.getAttribute('data-tooth'),
                treatment: treatment,
                status: status
            });
        }
    });

    const temporaryTeethData = [];
    document.querySelectorAll('.section:nth-child(3) .tooth').forEach(tooth => {
        const treatment = tooth.getAttribute('data-treatment') || '';
        const status = tooth.getAttribute('data-status') || '';
        if (treatment || status) {
            temporaryTeethData.push({
                number: tooth.getAttribute('data-tooth'),
                treatment: treatment,
                status: status
            });
        }
    });

    const formData = new FormData(document.getElementById('dentalForm'));
    const formObject = {};
    formData.forEach((value, key) => {
        formObject[key] = value;
    });

    formObject.permanentTeethData = permanentTeethData;
    formObject.temporaryTeethData = temporaryTeethData;

    sessionStorage.setItem('dentalFormData', JSON.stringify(formObject));
    
    // Get URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const appointmentId = urlParams.get('appointment_id');
    const patientId = urlParams.get('patient_id');
    
    // Redirect to dentalForm2.php with parameters
    if (appointmentId && patientId) {
        window.location.href = `dentalForm2.php?appointment_id=${appointmentId}&patient_id=${patientId}`;
    } else {
        window.location.href = 'dentalForm2.php';
    }
}

function printForm() {
    const teeth = document.querySelectorAll('.tooth');
    teeth.forEach(tooth => {
        const treatment = tooth.getAttribute('data-treatment');
        const status = tooth.getAttribute('data-status');
        if (treatment || status) {
            tooth.innerHTML = `
                        ${tooth.getAttribute('data-tooth')}<br>
                        <span class="treatment">${treatment}</span><br>
                        <span class="status">${status}</span>
                    `;
        }
    });

    window.print();
}
