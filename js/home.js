let studentIdCount = 1; // One ID is already displayed by default
      const maxStudents = 5;
  
      function showK6Modal() {
        const k6Modal = new bootstrap.Modal(document.getElementById('k6Modal'));
        k6Modal.show();
      }
  
      function addStudentIdField() {
        if (studentIdCount < maxStudents) {
          const container = document.getElementById("studentIdContainer");
  
          const studentNameField = document.createElement("div");
          studentNameField.classList.add("mb-3");
          studentNameField.innerHTML = `
            <label for="studentName${studentIdCount}" class="form-label">Student Name (${studentIdCount + 1})</label>
            <input type="text" class="form-control" id="studentName${studentIdCount}" name="studentName${studentIdCount}" required>
          `;
          container.appendChild(studentNameField);
  
          const studentTypeField = document.createElement("div");
          studentTypeField.classList.add("mb-3");
          studentTypeField.innerHTML = `
            <label for="studentType${studentIdCount}" class="form-label">Student Type (${studentIdCount + 1})</label>
            <select class="form-select" id="studentType${studentIdCount}" name="studentType${studentIdCount}" required>
              <option value="">Select type</option>
              <option value="Kindergarten">Kindergarten</option>
              <option value="Elementary">Elementary</option>
            </select>
          `;
          container.appendChild(studentTypeField);
  
          const studentIdField = document.createElement("div");
          studentIdField.classList.add("mb-3");
          studentIdField.innerHTML = `
            <label for="studentId${studentIdCount}" class="form-label">Student ID (${studentIdCount + 1})</label>
            <input type="file" class="form-control" id="studentId${studentIdCount}" name="studentId${studentIdCount}" required>
          `;
          container.appendChild(studentIdField);
          
          studentIdCount++;
        } else {
          alert("You can only add up to " + maxStudents + " children.");
        }
      }

      const radioButtons = document.querySelectorAll('input[name="userType"]');
const continueBtn = document.querySelector('#userTypeForm button[type="submit"]');

// Disable button by default
continueBtn.disabled = true;

// Enable button only when a radio is selected
radioButtons.forEach(radio => {
    radio.addEventListener('change', () => {
        continueBtn.disabled = false;
    });
});


  

