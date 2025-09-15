function togglePasswordVisibility() {
    const passwordInput = document.getElementById('password');
    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordInput.setAttribute('type', type);
}

function checkPasswordStrength() {
    const password = document.getElementById('password').value;
    const strengthText = document.getElementById('passwordStrength');
    
    // Initialize strength score
    let strength = 0;
    if (password.length >= 8) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;

    // Update strength feedback in real-time
    if (password.length === 0) {
        strengthText.textContent = '';
    } else if (strength <= 1) {
        strengthText.textContent = 'Weak';
        strengthText.style.color = 'red';
    } else if (strength <= 3) {
        strengthText.textContent = 'Moderate';
        strengthText.style.color = 'orange';
    } else {
        strengthText.textContent = 'Strong';
        strengthText.style.color = 'green';
    }

    // Update confirm password match status
    checkPasswordMatch();
}

function checkPasswordMatch() {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const matchText = document.getElementById('passwordMatch');

    if (confirmPassword.length === 0) {
        matchText.textContent = '';
    } else if (password === confirmPassword) {
        matchText.textContent = 'Passwords match';
        matchText.style.color = 'green';
    } else {
        matchText.textContent = 'Passwords do not match';
        matchText.style.color = 'red';
    }
}
    function capitalizeFirstLetter(input) {
        const words = input.value.split(' ');
        for (let i = 0; i < words.length; i++) {
            if (words[i]) {
                words[i] = words[i].charAt(0).toUpperCase() + words[i].slice(1).toLowerCase();
            }
        }
        input.value = words.join(' ');
    }


