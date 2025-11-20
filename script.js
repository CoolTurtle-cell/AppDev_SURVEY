document.addEventListener('DOMContentLoaded', function() {

    // Toggle password visibility - Works for all password fields
    const toggleButtons = document.querySelectorAll('.toggle-password');
    toggleButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const eyeIcon = this.querySelector('.eye-icon');
            
            if (input && eyeIcon) {
                if (input.type === 'password') {
                    input.type = 'text';
                    eyeIcon.innerHTML = `
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    `;
                } else {
                    input.type = 'password';
                    eyeIcon.innerHTML = `
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    `;
                }
            }
        });
    });

    // ADMIN LOGIN PAGE FUNCTIONALITY  
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        const errorMessage = document.getElementById('errorMessage');
        const submitBtn = loginForm.querySelector('.login-btn');
        const rememberCheckbox = document.getElementById('remember');

        // Check if user credentials are remembered
        const rememberedEmail = localStorage.getItem('rememberedEmail');
        if (rememberedEmail) {
            emailInput.value = rememberedEmail;
            rememberCheckbox.checked = true;
        }

        // Email validation with visual feedback
        emailInput.addEventListener('input', function() {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (this.value.length > 0) {
                if (!emailRegex.test(this.value)) {
                    this.style.borderColor = '#ef4444';
                } else {
                    this.style.borderColor = '#10b981';
                }
            } else {
                this.style.borderColor = '#e2e8f0';
            }
        });

        emailInput.addEventListener('blur', function() {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (this.value.length > 0 && !emailRegex.test(this.value)) {
                this.style.borderColor = '#ef4444';
            }
        });

        // Real-time validation feedback for inputs
        const inputs = loginForm.querySelectorAll('input[type="email"], input[type="password"]');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.style.borderColor = '#0080aa';
                errorMessage.classList.remove('show');
            });

            input.addEventListener('blur', function() {
                if (this.value.trim() !== '' && this.type !== 'email') {
                    this.style.borderColor = '#10b981';
                } else if (this.value.trim() === '') {
                    this.style.borderColor = '#e2e8f0';
                }
            });
        });

        // Form validation for login
        function validateLoginForm() {
            const email = emailInput.value.trim();
            const password = passwordInput.value;

            errorMessage.classList.remove('show');
            errorMessage.textContent = '';

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!email) {
                showError('Please enter your email address');
                emailInput.focus();
                return false;
            }

            if (!emailRegex.test(email)) {
                showError('Please enter a valid email address');
                emailInput.focus();
                return false;
            }

            if (!password) {
                showError('Please enter your password');
                passwordInput.focus();
                return false;
            }

            if (password.length < 8) {
                showError('Password must be at least 8 characters long');
                passwordInput.focus();
                return false;
            }

            return true;
        }

        function showError(message) {
            errorMessage.textContent = message;
            errorMessage.classList.add('show');
            errorMessage.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        // Form submission for login
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();

            if (!validateLoginForm()) {
                return;
            }

            submitBtn.classList.add('loading');
            submitBtn.disabled = true;

            const email = emailInput.value.trim();
            const password = passwordInput.value;
            const remember = rememberCheckbox.checked;

            setTimeout(() => {
                const users = JSON.parse(localStorage.getItem('users') || '[]');
                const user = users.find(u => u.email === email);

                if (!user) {
                    showError('No account found with this email address. Please check your email or sign up.');
                    submitBtn.classList.remove('loading');
                    submitBtn.disabled = false;
                    emailInput.focus();
                    return;
                }

                const decodedPassword = atob(user.password);
                if (decodedPassword !== password) {
                    showError('Incorrect password. Please try again.');
                    submitBtn.classList.remove('loading');
                    submitBtn.disabled = false;
                    passwordInput.focus();
                    passwordInput.select();
                    return;
                }

                if (remember) {
                    localStorage.setItem('rememberedEmail', email);
                } else {
                    localStorage.removeItem('rememberedEmail');
                }

                const currentUser = {
                    email: user.email,
                    fullName: user.fullName,
                    branch: user.branch,
                    role: user.role,
                    loginTime: new Date().toISOString()
                };
                localStorage.setItem('currentUser', JSON.stringify(currentUser));

                console.log('Login successful:', currentUser);

                submitBtn.classList.remove('loading');
                submitBtn.disabled = false;

                window.location.href = 'dashboard.html';
            }, 1500);
        });

        // Forgot password handler
        const forgotPasswordLink = document.querySelector('.forgot-password');
        if (forgotPasswordLink) {
            forgotPasswordLink.addEventListener('click', function(e) {
                e.preventDefault();
                
                const email = emailInput.value.trim();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                if (!email || !emailRegex.test(email)) {
                    showError('Please enter a valid email address first to reset your password');
                    emailInput.focus();
                } else {
                    alert(`Password reset link has been sent to ${email}. Please check your email.`);
                    console.log('Password reset requested for:', email);
                }
            });
        }

        // Enter key support
        emailInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                passwordInput.focus();
            }
        });

        passwordInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                loginForm.dispatchEvent(new Event('submit'));
            }
        });
    }

    // REGISTER PAGE FUNCTIONALITY
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirmPassword');
        const fullNameInput = document.getElementById('fullName');
        const emailInput = document.getElementById('email');
        const errorMessage = document.getElementById('errorMessage');
        const successModal = document.getElementById('successModal');
        const submitBtn = registerForm.querySelector('.login-btn');

        // Auto-capitalize full name
        fullNameInput.addEventListener('input', function(e) {
            let value = this.value;
            let cursorPosition = this.selectionStart;
            
            let result = value.replace(/\b\w/g, function(char) {
                return char.toUpperCase();
            });
            
            this.value = result;
            this.setSelectionRange(cursorPosition, cursorPosition);
        });

        // Email validation with visual feedback
        emailInput.addEventListener('input', function() {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (this.value.length > 0) {
                if (!emailRegex.test(this.value)) {
                    this.classList.add('invalid-email');
                } else {
                    this.classList.remove('invalid-email');
                }
            } else {
                this.classList.remove('invalid-email');
            }
        });

        emailInput.addEventListener('blur', function() {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (this.value.length > 0 && !emailRegex.test(this.value)) {
                this.classList.add('invalid-email');
            }
        });

        // Password strength checker
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            const strengthContainer = document.getElementById('passwordStrength');
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            
            if (password.length === 0) {
                strengthContainer.classList.remove('show', 'weak', 'medium', 'strong');
                return;
            }

            strengthContainer.classList.add('show');
            
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            strengthContainer.classList.remove('weak', 'medium', 'strong');
            
            if (strength <= 2) {
                strengthContainer.classList.add('weak');
                strengthText.textContent = 'Weak password';
            } else if (strength <= 3) {
                strengthContainer.classList.add('medium');
                strengthText.textContent = 'Medium password';
            } else {
                strengthContainer.classList.add('strong');
                strengthText.textContent = 'Strong password';
            }
        });

        // Form validation for register
        function validateRegisterForm() {
            const fullName = document.getElementById('fullName').value.trim();
            const email = document.getElementById('email').value.trim();
            const branch = document.getElementById('branch').value;
            const role = document.getElementById('role').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;

            errorMessage.classList.remove('show');
            errorMessage.textContent = '';

            if (fullName.length < 2) {
                showRegisterError('Please enter your full name (at least 2 characters)');
                return false;
            }

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showRegisterError('Please enter a valid email address');
                return false;
            }

            if (!branch) {
                showRegisterError('Please select your branch');
                return false;
            }

            if (!role) {
                showRegisterError('Please select your role');
                return false;
            }

            if (password.length < 8) {
                showRegisterError('Password must be at least 8 characters long');
                return false;
            }

            if (!/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/.test(password)) {
                showRegisterError('Password must contain at least one uppercase letter, one lowercase letter, and one number');
                return false;
            }

            if (password !== confirmPassword) {
                showRegisterError('Passwords do not match');
                return false;
            }

            return true;
        }

        function showRegisterError(message) {
            errorMessage.textContent = message;
            errorMessage.classList.add('show');
            errorMessage.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        // Form submission for register
        registerForm.addEventListener('submit', function(e) {
            e.preventDefault();

            if (!validateRegisterForm()) {
                return;
            }

            submitBtn.classList.add('loading');
            submitBtn.disabled = true;

            setTimeout(() => {
                const formData = {
                    fullName: document.getElementById('fullName').value.trim(),
                    email: document.getElementById('email').value.trim(),
                    branch: document.getElementById('branch').value,
                    role: document.getElementById('role').value,
                    password: document.getElementById('password').value
                };

                console.log('Registration Data:', formData);

                const users = JSON.parse(localStorage.getItem('users') || '[]');
                
                if (users.some(user => user.email === formData.email)) {
                    showRegisterError('An account with this email already exists');
                    submitBtn.classList.remove('loading');
                    submitBtn.disabled = false;
                    return;
                }

                users.push({
                    ...formData,
                    password: btoa(formData.password),
                    createdAt: new Date().toISOString()
                });
                localStorage.setItem('users', JSON.stringify(users));

                submitBtn.classList.remove('loading');
                submitBtn.disabled = false;

                successModal.classList.add('show');

                registerForm.reset();
                document.getElementById('passwordStrength').classList.remove('show', 'weak', 'medium', 'strong');
            }, 1500);
        });

        // Real-time validation feedback
        const inputs = registerForm.querySelectorAll('input, select');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                if (this.value.trim() !== '') {
                    this.style.borderColor = '#10b981';
                }
            });

            input.addEventListener('focus', function() {
                this.style.borderColor = '#0080aa';
                errorMessage.classList.remove('show');
            });
        });

        // Confirm password real-time validation
        confirmPasswordInput.addEventListener('input', function() {
            if (this.value && passwordInput.value) {
                if (this.value === passwordInput.value) {
                    this.style.borderColor = '#10b981';
                } else {
                    this.style.borderColor = '#ef4444';
                }
            }
        });
    }
});

// MODAL FUNCTIONS (Global)
function closeModal() {
    const modal = document.getElementById('successModal');
    if (modal) {
        modal.classList.remove('show');
        
        setTimeout(() => {
            window.location.href = 'admin.html';
        }, 300);
    }
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('successModal');
    if (modal && event.target === modal) {
        closeModal();
    }
});

// Keyboard accessibility for modal
document.addEventListener('keydown', function(event) {
    const modal = document.getElementById('successModal');
    if (modal && event.key === 'Escape' && modal.classList.contains('show')) {
        closeModal();
    }
});