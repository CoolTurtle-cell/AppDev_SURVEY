<?php 
require 'db_connect.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$response = ['success' => false, 'message' => ''];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $fullName = trim($_POST['fullName']);
    $email = trim($_POST['email']);
    $accountType = trim($_POST['accountType']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];

    // Basic validations
    if (empty($fullName) || empty($email) || empty($accountType) || empty($password)) {
        $response['message'] = 'All fields are required';
    }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Invalid email format';
    }
    elseif (!in_array($accountType, ['Admin', 'SuperAdmin'])) {
        $response['message'] = 'Invalid account type';
    }
    elseif ($password !== $confirmPassword) {
        $response['message'] = 'Passwords do not match';
    }
    elseif (strlen($password) < 8) {
        $response['message'] = 'Password must be at least 8 characters';
    }
    else {
        // Check duplicate email
        $checkEmail = $conn->prepare("SELECT AdminID FROM admin WHERE Email = ?");
        $checkEmail->bind_param("s", $email);
        $checkEmail->execute();
        $result = $checkEmail->get_result();

        if ($result->num_rows > 0) {
            $response['message'] = 'Email already registered';
            $checkEmail->close();
        } else {
            $checkEmail->close();

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            // Insert new admin
            $stmt = $conn->prepare(
                "INSERT INTO admin (Name, Email, Password, AccountType, status)
                 VALUES (?, ?, ?, ?, 'Pending')"
            );
            $stmt->bind_param("ssss", $fullName, $email, $passwordHash, $accountType);

            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Registration successful! Pending approval.';
                $response['admin_id'] = $stmt->insert_id;
            } else {
                $response['message'] = 'Registration failed: ' . $stmt->error;
            }

            $stmt->close();
        }
    }

    $conn->close();
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Register Page - Survey System</title>
  <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="styles.css" />
  <style>
    .password-indicator {
      margin-top: 8px;
      padding: 10px 14px;
      border-radius: 6px;
      font-size: 13px;
      font-weight: 500;
      display: none;
      transition: all 0.3s ease;
    }

    .password-indicator.show {
      display: block;
    }

    .password-indicator.match {
      background-color: #dcfce7;
      color: #16a34a;
      border: 1px solid #86efac;
    }

    .password-indicator.match::before {
      content: "✓ ";
      font-weight: bold;
    }

    .password-indicator.no-match {
      background-color: #fee2e2;
      color: #dc2626;
      border: 1px solid #fca5a5;
    }

    .password-indicator.no-match::before {
      content: "✗ ";
      font-weight: bold;
    }

    .form-group input.password-match {
      border-color: #16a34a !important;
    }

    .form-group input.password-no-match {
      border-color: #dc2626 !important;
    }
  </style>
</head>
<body class="login-page">
  <div class="register-container">
    <div class="register-card">
      
      <div class="register-header">
        <div class="icon-circle">
          <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32"
            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
            <circle cx="12" cy="7" r="4"></circle>
          </svg>
        </div>
        <h2>Create Account</h2>
        <p>Register to access the survey system.</p>
      </div>

      <div class="error-message" id="errorMessage"></div>

      <form class="register-form" id="registerForm" method="POST">

        <div class="form-group">
          <label for="fullName">Full Name</label>
          <div class="input-with-icon">
            <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
              viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
            <input type="text" id="fullName" name="fullName" placeholder="Enter your full name" required />
          </div>
        </div>

        <div class="form-group">
          <label for="email">Email</label>
          <div class="input-with-icon">
            <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
              viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round">
              <rect x="2" y="4" width="20" height="16" rx="2"></rect>
              <path d="m2 7 10 7 10-7"></path>
            </svg>
            <input type="email" id="email" name="email" placeholder="Enter email address" required />
          </div>
        </div>

        <div class="form-group">
          <label for="accountType">Account Type</label>
          <div class="input-with-icon select-wrapper">
            <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
              viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="3"></circle>
              <path d="M12 1v6M12 17v6M4.22 4.22l4.24 4.25M15.54 15.54l4.24 4.25M1 12h6M17 12h6M4.22 19.78l4.24-4.25M15.54 8.46l4.24-4.25"></path>
            </svg>
            <select id="accountType" name="accountType" required>
              <option value="">Select account type</option>
              <option value="Admin">Admin</option>
              <option value="SuperAdmin">Super Admin</option>
            </select>
            <svg class="select-arrow" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
              viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round">
              <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
          </div>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-with-icon password-wrapper">
            <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
              viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            <input type="password" id="password" name="password" placeholder="Create a password" required />
            <button type="button" class="toggle-password" data-target="password">
              <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
              </svg>
            </button>
          </div>
          <div class="password-strength" id="passwordStrength">
            <div class="strength-bar">
              <div class="strength-fill" id="strengthFill"></div>
            </div>
            <span class="strength-text" id="strengthText"></span>
          </div>
        </div>

        <div class="form-group">
          <label for="confirmPassword">Confirm Password</label>
          <div class="input-with-icon password-wrapper">
            <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
              viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm your password" required />
            <button type="button" class="toggle-password" data-target="confirmPassword">
              <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
              </svg>
            </button>
          </div>
          <div class="password-indicator" id="passwordIndicator"></div>
        </div>

        <button type="submit" class="login-btn" id="submitBtn">
          <span class="btn-text">Sign Up</span>
          <span class="btn-loader" style="display: none;">
            <svg class="spinner" xmlns="http://www.w3.org/2000/svg" width="20" height="20"
              viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
            </svg>
          </span>
        </button>

        <div class="login-link">
          Already have an account? <a href="admin.html">Login</a>
        </div>
      </form>
    </div>
  </div>

  <div id="successModal" class="modal">
    <div class="modal-content">
      <div class="success-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64"
          viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
          stroke-linecap="round" stroke-linejoin="round">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
          <polyline points="22 4 12 14.01 9 11.01"></polyline>
        </svg>
      </div>
      <h3>Registration Successful!</h3>
      <p>Your account has been created and is pending approval. You will be notified once approved.</p>
      <button class="modal-btn" onclick="closeModal()">Continue to Login</button>
    </div>
  </div>

  <script>
    // === AUTOMATIC NAME CAPITALIZATION ===
    const fullNameInput = document.getElementById('fullName');

    fullNameInput.addEventListener('input', function(e) {
      let value = this.value;
      let capitalized = value.split(' ').map(word => {
        if (word.length > 0) {
          return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
        }
        return word;
      }).join(' ');
      
      if (this.value !== capitalized) {
        const cursorPos = this.selectionStart;
        this.value = capitalized;
        this.setSelectionRange(cursorPos, cursorPos);
      }
    });

    // === PASSWORD STRENGTH INDICATOR ===
    const passwordInput = document.getElementById('password');
    const passwordStrength = document.getElementById('passwordStrength');
    const strengthFill = document.getElementById('strengthFill');
    const strengthText = document.getElementById('strengthText');

    passwordInput.addEventListener('input', function() {
      const password = this.value;
      
      if (password.length === 0) {
        passwordStrength.classList.remove('show', 'weak', 'medium', 'strong');
        return;
      }

      passwordStrength.classList.add('show');
      
      let strength = 0;
      if (password.length >= 8) strength++;
      if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
      if (password.match(/[0-9]/)) strength++;
      if (password.match(/[^a-zA-Z0-9]/)) strength++;

      passwordStrength.classList.remove('weak', 'medium', 'strong');
      
      if (strength <= 2) {
        passwordStrength.classList.add('weak');
        strengthText.textContent = 'Weak password';
      } else if (strength === 3) {
        passwordStrength.classList.add('medium');
        strengthText.textContent = 'Medium password';
      } else {
        passwordStrength.classList.add('strong');
        strengthText.textContent = 'Strong password';
      }
    });

    // === PASSWORD MATCHING WITH REAL-TIME INDICATOR ===
    const confirmPasswordInput = document.getElementById('confirmPassword');
    const passwordIndicator = document.getElementById('passwordIndicator');
    const errorMessage = document.getElementById('errorMessage');

    function checkPasswordMatch() {
      const password = passwordInput.value;
      const confirmPassword = confirmPasswordInput.value;

      if (confirmPassword.length === 0) {
        passwordIndicator.classList.remove('show', 'match', 'no-match');
        passwordIndicator.textContent = '';
        confirmPasswordInput.classList.remove('password-match', 'password-no-match');
        errorMessage.classList.remove('show');
        return;
      }

      passwordIndicator.classList.add('show');

      if (password === confirmPassword) {
        passwordIndicator.textContent = 'Passwords match!';
        passwordIndicator.classList.remove('no-match');
        passwordIndicator.classList.add('match');
        confirmPasswordInput.classList.remove('password-no-match');
        confirmPasswordInput.classList.add('password-match');
        errorMessage.classList.remove('show');
      } else {
        passwordIndicator.textContent = 'Passwords do not match';
        passwordIndicator.classList.remove('match');
        passwordIndicator.classList.add('no-match');
        confirmPasswordInput.classList.remove('password-match');
        confirmPasswordInput.classList.add('password-no-match');
      }
    }

    passwordInput.addEventListener('input', checkPasswordMatch);
    confirmPasswordInput.addEventListener('input', checkPasswordMatch);

    // === TOGGLE PASSWORD VISIBILITY ===
    document.querySelectorAll('.toggle-password').forEach(button => {
      button.addEventListener('click', function() {
        const targetId = this.getAttribute('data-target');
        const input = document.getElementById(targetId);
        const icon = this.querySelector('.eye-icon');
        
        if (input.type === 'password') {
          input.type = 'text';
          icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
        } else {
          input.type = 'password';
          icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
        }
      });
    });

    // === FORM SUBMISSION ===
    document.getElementById('registerForm').addEventListener('submit', function(e) {
      e.preventDefault();
      
      const password = passwordInput.value;
      const confirmPassword = confirmPasswordInput.value;
      
      if (password !== confirmPassword) {
        errorMessage.textContent = 'Passwords do not match!';
        errorMessage.classList.add('show');
        confirmPasswordInput.focus();
        return false;
      }

      const submitBtn = document.getElementById('submitBtn');
      submitBtn.disabled = true;
      submitBtn.querySelector('.btn-text').style.display = 'none';
      submitBtn.querySelector('.btn-loader').style.display = 'flex';

      const formData = new FormData(this);

      fetch('register.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        submitBtn.disabled = false;
        submitBtn.querySelector('.btn-text').style.display = 'inline';
        submitBtn.querySelector('.btn-loader').style.display = 'none';

        if (data.success) {
          document.getElementById('successModal').classList.add('show');
        } else {
          errorMessage.textContent = data.message;
          errorMessage.classList.add('show');
        }
      })
      .catch(error => {
        submitBtn.disabled = false;
        submitBtn.querySelector('.btn-text').style.display = 'inline';
        submitBtn.querySelector('.btn-loader').style.display = 'none';
        
        errorMessage.textContent = 'Registration failed. Please try again.';
        errorMessage.classList.add('show');
        console.error('Error:', error);
      });
    });

    function closeModal() {
      document.getElementById('successModal').classList.remove('show');
      window.location.href = 'admin.html';
    }
  </script>
</body>
</html>