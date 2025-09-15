// Add Permanent Teeth row
function addPermanentRow() {
    const table = document.getElementById('permanentTeethTable').getElementsByTagName('tbody')[0];
    const newRow = table.insertRow();
    newRow.innerHTML = `
        <td><input type="date" name="permanentVisitDate[]" required></td>
        <td><input type="number" name="permanentDecayed[]" min="0" class="calc-permanent" required></td>
        <td><input type="number" name="permanentMissing[]" min="0" class="calc-permanent" required></td>
        <td><input type="number" name="permanentFilled[]" min="0" class="calc-permanent" required></td>
        <td><input type="number" name="permanentTotal[]" min="0" readonly></td>
        <td><button type="button" class="btn-delete" onclick="deleteRow(this)">✕</button></td>
    `;
    addCalculationListeners(newRow, 'permanent');
}

// Add Temporary Teeth row
function addTemporaryRow() {
    const table = document.getElementById('temporaryTeethTable').getElementsByTagName('tbody')[0];
    const newRow = table.insertRow();
    newRow.innerHTML = `
        <td><input type="date" name="temporaryVisitDate[]" required></td>
        <td><input type="number" name="temporaryDecayed[]" min="0" class="calc-temporary" required></td>
        <td><input type="number" name="temporaryFilled[]" min="0" class="calc-temporary" required></td>
        <td><input type="number" name="temporaryTotal[]" min="0" readonly></td>
        <td><button type="button" class="btn-delete" onclick="deleteRow(this)">✕</button></td>
    `;
    addCalculationListeners(newRow, 'temporary');
}

// Add Treatment Record row
function addTreatmentRow() {
    const table = document.getElementById('treatmentRecordTable').getElementsByTagName('tbody')[0];
    const newRow = table.insertRow();
    newRow.innerHTML = `
        <td><input type="date" name="treatmentDate[]" required></td>
        <td><input type="text" name="toothNumber[]" required></td>
        <td><input type="text" name="natureOfOperation[]" required></td>
        <td><input type="text" name="dentistName[]" required></td>
        <td><button type="button" class="btn-delete" onclick="deleteRow(this)">✕</button></td>
    `;
}

// Delete specific row
function deleteRow(btn) {
    const row = btn.parentNode.parentNode;
    const table = row.parentNode;
    if (table.rows.length > 1) {
        row.parentNode.removeChild(row);
    } else {
        alert("At least one row must remain.");
    }
}

// Add calculation listeners to a row
function addCalculationListeners(row, type) {
    const inputs = row.querySelectorAll(`.calc-${type}`);
    const totalInput = row.querySelector('input[name$="Total[]"]');
    
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            let total = 0;
            inputs.forEach(i => {
                total += parseInt(i.value) || 0;
            });
            totalInput.value = total;
        });
    });
}

// Initialize calculation listeners for first rows
document.addEventListener('DOMContentLoaded', function() {
    const firstPermanentRow = document.querySelector('#permanentTeethTable tbody tr');
    if (firstPermanentRow) addCalculationListeners(firstPermanentRow, 'permanent');
    
    const firstTemporaryRow = document.querySelector('#temporaryTeethTable tbody tr');
    if (firstTemporaryRow) addCalculationListeners(firstTemporaryRow, 'temporary');
});

// Form submission
// Form submission
document.getElementById('dentalFormPart2').addEventListener('submit', function(event) {
    event.preventDefault();
    
    // Get URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const appointmentId = urlParams.get('appointment_id');
    const patientId = urlParams.get('patient_id');
    
    // Get data from both form parts
    const firstFormData = JSON.parse(sessionStorage.getItem('dentalFormData') || '{}');
    const formData = new FormData(this);
    const part2Data = {};
    formData.forEach((value, key) => {
        part2Data[key] = value;
    });
    
    // Combine both parts of the form data and add URL parameters
    const combinedData = {
        ...firstFormData, 
        ...part2Data,
        appointment_id: appointmentId,
        patient_id: patientId
    };
    
    // Send data to server
    fetch('submit_dental_form.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(combinedData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Form submitted successfully!');
            // Clear session storage
            sessionStorage.removeItem('dentalFormData');
            sessionStorage.removeItem('dentalFormPart2Data');
            // Redirect to appointments page
            window.location.href = 'dental-appointments.php';
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while submitting the form.');
    });
});

// Load data from first form
document.addEventListener('DOMContentLoaded', function() {
    // Load data from first form
    const firstFormData = sessionStorage.getItem('dentalFormData');
    if (firstFormData) {
        console.log('Data from first form:', JSON.parse(firstFormData));
    }

    // Load Part 2 data if it exists
    const part2Data = sessionStorage.getItem('dentalFormPart2Data');
    if (part2Data) {
        const formData = JSON.parse(part2Data);
        for (const key in formData) {
            const elements = document.querySelectorAll(`[name="${key}"]`);
            elements.forEach((element, index) => {
                if (element.type === 'radio' || element.type === 'checkbox') {
                    const matchingInput = document.querySelector(`[name="${key}"][value="${formData[key]}"]`);
                    if (matchingInput) matchingInput.checked = true;
                } else {
                    // Handle array inputs (like treatment records)
                    if (key.endsWith('[]')) {
                        if (Array.isArray(formData[key])) {
                            element.value = formData[key][index] || '';
                        } else {
                            element.value = formData[key] || '';
                        }
                    } else {
                        element.value = formData[key] || '';
                    }
                }
            });
        }
    }
});

function goBackToPart1() {
    // Save Part 2 form data
    const formData = {};
    const formElements = document.getElementById('dentalFormPart2').elements;
    
    // Convert FormData to object, handling array inputs properly
    Array.from(formElements).forEach(element => {
        if (element.name) {
            if (element.name.endsWith('[]')) {
                if (!formData[element.name]) {
                    formData[element.name] = [];
                }
                formData[element.name].push(element.value);
            } else if (element.type === 'radio') {
                if (element.checked) {
                    formData[element.name] = element.value;
                }
            } else {
                formData[element.name] = element.value;
            }
        }
    });
    
    sessionStorage.setItem('dentalFormPart2Data', JSON.stringify(formData));
    window.location.href = 'dentalForm.php';
}

function printCombinedForms() {
    const firstFormData = JSON.parse(sessionStorage.getItem('dentalFormData') || '{}');
    const part2Data = JSON.parse(sessionStorage.getItem('dentalFormPart2Data') || '{}');
    
    // Format radio button values for display
    const formatRadioValue = (value, options) => {
        if (!value) return '';
        const option = options.find(opt => opt.value === value);
        return option ? option.label : value;
    };

    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Dental Form - Printed</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; }
                h1, h2 { color: #ba1d2d; }
                h1 { text-align: center; }
                h2 { margin-top: 20px; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
                .form-section { margin-bottom: 30px; }
                .form-group { margin-bottom: 10px; }
                label { font-weight: bold; display: inline-block; margin-right: 20px; }
                .row { display: flex; flex-wrap: wrap; margin-bottom: 10px; gap: 250px; }
                .teeth-chart { text-align: center; }
                .quadrant { margin-bottom: 20px; }
                .teeth-row { display: flex; justify-content: center; gap: 5px; margin-bottom: 5px; }
                .tooth { border: 1px solid #000; padding: 5px; text-align: center; min-width: 30px; }
                table { width: 100%; border-collapse: collapse; margin: 15px 0; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f4f4f4; }
                @page { size: auto; margin: 10mm; }
            </style>
        </head>
        <body>
            <h1>Dental Form - Complete Record</h1>
            
            <!-- Personal Information -->
            <div class="form-section">
                <h2>Personal Information</h2>
                <div class="row">
                    <div class="form-group">
                        <label>File No.:</label> ${firstFormData.fileNo || ''}
                    </div>
                    <div class="form-group">
                        <label>Name:</label> ${firstFormData.surname || ''}, ${firstFormData.firstname || ''} ${firstFormData.middlename || ''}
                    </div>
                </div>
                <div class="row">
                    <div class="form-group">
                        <label>Grade/Section:</label> ${firstFormData.gradeSection || ''}
                    </div>
                    <div class="form-group">
                        <label>Age:</label> ${firstFormData.age || ''}
                    </div>
                </div>
                <div class="row">
                    <div class="form-group">
                        <label>Sex:</label> ${firstFormData.sex === 'male' ? 'Male' : 'Female'}
                    </div>
                    <div class="form-group">
                        <label>Has Toothbrush:</label> ${firstFormData.toothbrush === 'yes' ? 'Yes' : 'No'}
                    </div>
                </div>
            </div>
            
            <!-- Permanent Teeth (Dentition Status and Treatment Needs) -->
            <div class="form-section">
                <h2>Permanent Teeth (Dentition Status and Treatment Needs)</h2>
                <div class="teeth-chart">
                    ${generateToothChart(firstFormData.permanentTeethData || [], 'Permanent')}
                </div>
            </div>
            
            <!-- Temporary Teeth (Dentition Status and Treatment Needs) -->
            <div class="form-section">
                <h2>Temporary Teeth (Dentition Status and Treatment Needs)</h2>
                <div class="teeth-chart">
                    ${generateToothChart(firstFormData.temporaryTeethData || [], 'Temporary')}
                </div>
            </div>
            
            <!-- Summary of Status of Oral Health -->
            <div class="form-section">
                <h2>Summary of Status of Oral Health</h2>
                <div class="row">
                    <div class="form-group">
                        <label>Dentition:</label> ${formatRadioValue(part2Data.dentition, [
                            {value: 'satisfactory', label: 'Satisfactory'},
                            {value: 'fair', label: 'Fair'},
                            {value: 'poor', label: 'Poor'}
                        ])}
                    </div>
                    <div class="form-group">
                        <label>Periodontal:</label> ${formatRadioValue(part2Data.periodontal, [
                            {value: 'satisfactory', label: 'Satisfactory'},
                            {value: 'fair', label: 'Fair'},
                            {value: 'poor', label: 'Poor'}
                        ])}
                    </div>
                </div>
                <div class="row">
                    <div class="form-group">
                        <label>Occlusion:</label> ${formatRadioValue(part2Data.occlusion, [
                            {value: 'normal', label: 'Normal'},
                            {value: 'malocclusion', label: 'Malocclusion'}
                        ])}
                    </div>
                </div>
                <div class="form-group">
                    <label>Remarks:</label> ${part2Data.remarks || ''}
                </div>
            </div>
            
            <!-- Permanent Teeth Records -->
            <div class="form-section">
                <h2>Permanent Teeth Records</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Decayed</th>
                            <th>Missing</th>
                            <th>Filled</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${generateTableRows(part2Data.permanentVisitDate, part2Data.permanentDecayed, part2Data.permanentMissing, part2Data.permanentFilled, part2Data.permanentTotal)}
                    </tbody>
                </table>
            </div>
            
            <!-- Temporary Teeth Records -->
            <div class="form-section">
                <h2>Temporary Teeth Records</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Decayed</th>
                            <th>Filled</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${generateTableRows(part2Data.temporaryVisitDate, part2Data.temporaryDecayed, null, part2Data.temporaryFilled, part2Data.temporaryTotal)}
                    </tbody>
                </table>
            </div>
            
            <!-- Treatment Records -->
            <div class="form-section">
                <h2>Treatment Records</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Tooth No.</th>
                            <th>Operation</th>
                            <th>Dentist</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${generateTreatmentRows(part2Data.treatmentDate, part2Data.toothNumber, part2Data.natureOfOperation, part2Data.dentistName)}
                    </tbody>
                </table>
            </div>
            
            <!-- Examined By and Date -->
            <div class="form-section">
                <div class="row">
                    <div class="form-group">
                        <label>Examined By:</label> ${part2Data.examinedBy || ''}
                    </div>
                    <div class="form-group">
                        <label>Date:</label> ${part2Data.examinedDate || ''}
                    </div>
                </div>
            </div>
        </body>
        </html>
    `);

    function generateToothChart(teethData, type) {
        const quadrants = type === 'Permanent' ? [
            { title: 'Upper Right Quadrant (18-11)', teeth: ['18', '17', '16', '15', '14', '13', '12', '11'] },
            { title: 'Upper Left Quadrant (21-28)', teeth: ['21', '22', '23', '24', '25', '26', '27', '28'] },
            { title: 'Lower Left Quadrant (38-31)', teeth: ['38', '37', '36', '35', '34', '33', '32', '31'] },
            { title: 'Lower Right Quadrant (48-41)', teeth: ['48', '47', '46', '45', '44', '43', '42', '41'] }
        ] : [
            { title: 'Upper Right Quadrant (55-51)', teeth: ['55', '54', '53', '52', '51'] },
            { title: 'Upper Left Quadrant (61-65)', teeth: ['61', '62', '63', '64', '65'] },
            { title: 'Lower Left Quadrant (85-81)', teeth: ['85', '84', '83', '82', '81'] },
            { title: 'Lower Right Quadrant (71-75)', teeth: ['71', '72', '73', '74', '75'] }
        ];

        let html = '';
        quadrants.forEach(quadrant => {
            html += `<div class="quadrant"><h3>${quadrant.title}</h3><div class="teeth-row">`;
            quadrant.teeth.forEach(toothNumber => {
                const tooth = teethData.find(t => t.number === toothNumber) || {};
                html += `
                    <div class="tooth">
                        ${toothNumber}<br>
                        <small>${tooth.treatment || ''}</small><br>
                        <small>${tooth.status || ''}</small>
                    </div>
                `;
            });
            html += '</div></div>';
        });
        return html;
    }
    
    function generateTableRows(dates, decayed, missing, filled, totals) {
        let rows = '';
        
        if (dates && Array.isArray(dates)) {
            dates.forEach((date, index) => {
                const decay = decayed && decayed[index] ? decayed[index] : '0';
                const fill = filled && filled[index] ? filled[index] : '0';
                const total = totals && totals[index] ? totals[index] : '0';
                
                if (missing) {
                    const miss = missing && missing[index] ? missing[index] : '0';
                    rows += `
                        <tr>
                            <td>${date}</td>
                            <td>${decay}</td>
                            <td>${miss}</td>
                            <td>${fill}</td>
                            <td>${total}</td>
                        </tr>
                    `;
                } else {
                    rows += `
                        <tr>
                            <td>${date}</td>
                            <td>${decay}</td>
                            <td>${fill}</td>
                            <td>${total}</td>
                        </tr>
                    `;
                }
            });
        }
        
        return rows;
    }
    
    function generateTreatmentRows(dates, toothNumbers, operations, dentists) {
        let rows = '';
        
        if (dates && Array.isArray(dates)) {
            dates.forEach((date, index) => {
                const tooth = toothNumbers && toothNumbers[index] ? toothNumbers[index] : '';
                const operation = operations && operations[index] ? operations[index] : '';
                const dentist = dentists && dentists[index] ? dentists[index] : '';
                
                rows += `
                    <tr>
                        <td>${date}</td>
                        <td>${tooth}</td>
                        <td>${operation}</td>
                        <td>${dentist}</td>
                    </tr>
                `;
            });
        }
        
        return rows;
    }
    
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
    }, 500);
}