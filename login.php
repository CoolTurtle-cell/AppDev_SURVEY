<?php
session_start();
require 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Query admin table
    $stmt = $conn->prepare("SELECT * FROM admin WHERE Email = ?");
    if (!$stmt) {
        die("SQL Error: " . $conn->error);
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    // Check if email exists
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verify password
        if (password_verify($password, $user['Password'])) {

            // Check if account is approved
            if ($user['status'] !== "Approved") {
                echo "<script>alert('Your account is not approved yet.'); window.history.back();</script>";
                exit();
            }

            // Save user session
            $_SESSION['admin_id']   = $user['AdminID'];
            $_SESSION['admin_name'] = $user['Name'];
            $_SESSION['account_type'] = $user['AccountType'];

            echo "<script>alert('Login successful! Welcome, {$user['Name']}'); 
                  window.location.href='user_management.html';</script>";
            exit();

        } else {
            echo "<script>alert('Incorrect password! Try again.'); window.history.back();</script>";
            exit();
        }

    } else {
        echo "<script>alert('No account found with that email.'); window.history.back();</script>";
        exit();
    }

    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Login - Survey System</title>
  <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="styles.css" />
</head>

<body class="login-page">
  <div class="login-container">
    <div class="login-left">
      <h1>Survey System</h1>
      <p>
        Welcome to the Survey Admin Panel. Manage your surveys, view responses,
        and generate insightful reports all in one place.
      </p>
    </div>

    <div class="login-right">
      <div class="login-header">
        <h2>Admin Login</h2>
        <p>Enter your credentials to access the admin panel.</p>
      </div>

      <div class="error-message" id="errorMessage"></div>

      <!-- LOGIN FORM -->
      <form class="login-form" method="POST" action="">
        
        <!-- Email -->
        <div class="form-group">
          <label for="email">Email</label>
          <div class="input-with-icon">
            <input type="email" id="email" name="email" placeholder="Enter your Email" required />
          </div>
        </div>

        <!-- Password -->
        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-with-icon password-wrapper">
            <input type="password" id="password" name="password" placeholder="Enter your password" required />
            <button type="button" class="toggle-password" data-target="password">Show</button>
          </div>
        </div>

        <!-- Login Button -->
        <button type="submit" class="login-btn">
          <span class="btn-text">Login</span>
        </button>

        <!-- Sign Up Link -->
        <div class="login-link">
          Don't have an account? <a href="register.php">Sign Up</a>
        </div>

      </form>
    </div>
  </div>

<script>
document.querySelectorAll(".toggle-password").forEach(button => {
    button.addEventListener("click", function () {
        const targetId = this.getAttribute("data-target");
        const input = document.getElementById(targetId);

        if (input.type === "password") {
            input.type = "text";
            this.textContent = "Hide";
        } else {
            input.type = "password";
            this.textContent = "Show";
        }
    });
});
</script>

</body>
</html>
