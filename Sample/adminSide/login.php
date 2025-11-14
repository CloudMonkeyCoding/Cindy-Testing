<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: dashboard.php');
    exit;
}

$timeoutMessage = $_SESSION['admin_timeout_message'] ?? '';
if ($timeoutMessage !== '') {
    unset($_SESSION['admin_timeout_message']);
}

$errorMessage = $_SESSION['admin_error_message'] ?? '';
if ($errorMessage !== '') {
    unset($_SESSION['admin_error_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login - Cindy's Bakeshop</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      position: relative;
      overflow: hidden;
    }

    body::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: url('../Images/cindyslogin.jpg') no-repeat center center;
      background-size: cover;
      opacity: 0.1;
      z-index: 0;
    }

    .login-container {
      position: relative;
      z-index: 1;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(20px);
      border-radius: 24px;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
      padding: 0;
      width: 100%;
      max-width: 420px;
      border: 1px solid rgba(255, 255, 255, 0.2);
      overflow: hidden;
    }

    .header {
      background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
      padding: 40px 30px;
      text-align: center;
      position: relative;
    }

    .header::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.1"/><circle cx="10" cy="60" r="0.5" fill="white" opacity="0.1"/><circle cx="90" cy="40" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
      opacity: 0.3;
    }

    .header img {
      width: 80px;
      height: 80px;
      margin-bottom: 16px;
      border-radius: 20px;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
      position: relative;
      z-index: 1;
    }

    .header h1 {
      color: white;
      font-size: 32px;
      font-weight: 800;
      margin-bottom: 8px;
      letter-spacing: -0.02em;
      position: relative;
      z-index: 1;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .header p {
      color: rgba(255, 255, 255, 0.9);
      font-size: 16px;
      font-weight: 500;
      position: relative;
      z-index: 1;
    }

    .admin-badge {
      display: inline-block;
      background: rgba(255, 255, 255, 0.2);
      color: white;
      padding: 6px 16px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      margin-top: 12px;
      border: 1px solid rgba(255, 255, 255, 0.3);
      backdrop-filter: blur(10px);
      position: relative;
      z-index: 1;
    }

    .form-section {
      padding: 40px 30px;
    }

    .form-group {
      margin-bottom: 24px;
      position: relative;
    }

    .form-group label {
      display: block;
      margin-bottom: 8px;
      color: #374151;
      font-weight: 600;
      font-size: 14px;
      letter-spacing: 0.01em;
    }

    .form-group input {
      width: 100%;
      padding: 16px 20px;
      border: 2px solid #e5e7eb;
      border-radius: 12px;
      font-size: 16px;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      background: #f9fafb;
      color: #111827;
    }

    .form-group input:focus {
      outline: none;
      border-color: #dc2626;
      background: white;
      box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.1);
      transform: translateY(-1px);
    }

    .form-group input.error {
      border-color: #ef4444;
      box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1);
    }

    .error-message {
      color: #ef4444;
      font-size: 12px;
      margin-top: 8px;
      display: none;
      font-weight: 500;
    }

    .login-btn {
      width: 100%;
      padding: 16px 24px;
      background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
      color: white;
      border: none;
      border-radius: 12px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      margin-top: 8px;
      position: relative;
      overflow: hidden;
    }

    .login-btn::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
      transition: left 0.5s;
    }

    .login-btn:hover::before {
      left: 100%;
    }

    .login-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(220, 38, 38, 0.4);
    }

    .login-btn:active {
      transform: translateY(0);
    }

    .login-btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
    }

    .loading {
      display: none;
      text-align: center;
      margin-top: 20px;
    }

    .spinner {
      border: 3px solid #f3f4f6;
      border-top: 3px solid #dc2626;
      border-radius: 50%;
      width: 24px;
      height: 24px;
      animation: spin 1s linear infinite;
      margin: 0 auto 12px;
    }

    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }

    .links {
      text-align: center;
      margin-top: 24px;
    }

    .links a {
      color: #6b7280;
      text-decoration: none;
      font-size: 14px;
      font-weight: 500;
      transition: all 0.2s ease;
      padding: 8px 16px;
      border-radius: 8px;
    }

    .links a:hover {
      color: #dc2626;
      background: rgba(220, 38, 38, 0.1);
    }

    .success-message {
      background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
      color: #065f46;
      padding: 16px 20px;
      border-radius: 12px;
      margin-bottom: 24px;
      border: 1px solid #a7f3d0;
      font-size: 14px;
      display: none;
      font-weight: 500;
    }

    .error-alert {
      background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
      color: #991b1b;
      padding: 16px 20px;
      border-radius: 12px;
      margin-bottom: 24px;
      border: 1px solid #fecaca;
      font-size: 14px;
      display: none;
      font-weight: 500;
    }

    .divider {
      height: 1px;
      background: linear-gradient(90deg, transparent, #e5e7eb, transparent);
      margin: 24px 0;
    }

    .floating-shapes {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      overflow: hidden;
      z-index: 0;
    }

    .shape {
      position: absolute;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 50%;
      animation: float 6s ease-in-out infinite;
    }

    .shape:nth-child(1) {
      width: 80px;
      height: 80px;
      top: 20%;
      left: 10%;
      animation-delay: 0s;
    }

    .shape:nth-child(2) {
      width: 60px;
      height: 60px;
      top: 60%;
      right: 10%;
      animation-delay: 2s;
    }

    .shape:nth-child(3) {
      width: 40px;
      height: 40px;
      top: 40%;
      left: 80%;
      animation-delay: 4s;
    }

    @keyframes float {
      0%, 100% { transform: translateY(0px) rotate(0deg); }
      50% { transform: translateY(-20px) rotate(180deg); }
    }

    @media (max-width: 480px) {
      .login-container {
        margin: 10px;
        max-width: calc(100% - 20px);
      }
      
      .header {
        padding: 30px 20px;
      }
      
      .form-section {
        padding: 30px 20px;
      }
    }
  </style>
</head>
<body>
  <div class="floating-shapes">
    <div class="shape"></div>
    <div class="shape"></div>
    <div class="shape"></div>
  </div>

  <div class="login-container">
    <div class="header">
      <img src="Cindys.png" alt="Cindy's Bakeshop Logo">
      <h1>CINDY'S</h1>
      <p>Give your sweet tooth a treat</p>
      <span class="admin-badge">ADMIN PORTAL</span>
    </div>

    <div class="form-section">
      <div
        class="success-message"
        id="successMessage"
        <?php if ($timeoutMessage !== ''): ?>style="display: block;"<?php endif; ?>
      >
        <?php if ($timeoutMessage !== ''): ?>
          <?= htmlspecialchars($timeoutMessage, ENT_QUOTES, 'UTF-8'); ?>
        <?php endif; ?>
      </div>
      <div
        class="error-alert"
        id="errorAlert"
        <?php if ($errorMessage !== ''): ?>style="display: block;"<?php endif; ?>
      >
        <?php if ($errorMessage !== ''): ?>
          <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
        <?php endif; ?>
      </div>

      <form id="adminLoginForm">
        <div class="form-group">
          <label for="email">Email Address</label>
          <input type="email" id="email" name="email" placeholder="Enter your email address" required>
          <div class="error-message" id="emailError">Please enter a valid email address</div>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" placeholder="Enter your password" required>
          <div class="error-message" id="passwordError">Password is required</div>
        </div>

        <button type="submit" class="login-btn" id="loginBtn">
          <span id="btnText">Sign In to Dashboard</span>
        </button>

        <div class="loading" id="loading">
          <div class="spinner"></div>
          <p>Authenticating...</p>
        </div>
      </form>

      <div class="divider"></div>

      <div class="links">
        <a href="#" onclick="forgotPassword()">Forgot your password?</a>
      </div>
    </div>
  </div>

  <script>
    // Form elements
    const form = document.getElementById('adminLoginForm');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const loginBtn = document.getElementById('loginBtn');
    const btnText = document.getElementById('btnText');
    const loadingIndicator = document.getElementById('loading');
    const successMessage = document.getElementById('successMessage');
    const errorAlert = document.getElementById('errorAlert');

    // Error elements
    const emailError = document.getElementById('emailError');
    const passwordError = document.getElementById('passwordError');

    // Form validation
    function validateForm() {
      let isValid = true;
      
      // Clear previous errors
      clearErrors();
      
      // Email validation
      const email = emailInput.value.trim();
      if (!email) {
        showError(emailInput, emailError, 'Email is required');
        isValid = false;
      } else if (!isValidEmail(email)) {
        showError(emailInput, emailError, 'Please enter a valid email address');
        isValid = false;
      }
      
      // Password validation
      const password = passwordInput.value;
      if (!password) {
        showError(passwordInput, passwordError, 'Password is required');
        isValid = false;
      } else if (password.length < 6) {
        showError(passwordInput, passwordError, 'Password must be at least 6 characters');
        isValid = false;
      }
      
      return isValid;
    }

    function isValidEmail(email) {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return emailRegex.test(email);
    }

    function showError(input, errorElement, message) {
      input.classList.add('error');
      errorElement.textContent = message;
      errorElement.style.display = 'block';
    }

    function clearErrors() {
      [emailInput, passwordInput].forEach(input => {
        input.classList.remove('error');
      });
      [emailError, passwordError].forEach(error => {
        error.style.display = 'none';
      });
      errorAlert.style.display = 'none';
      successMessage.style.display = 'none';
    }

    function showSuccess(message) {
      successMessage.textContent = message;
      successMessage.style.display = 'block';
      errorAlert.style.display = 'none';
    }

    function showErrorAlert(message) {
      errorAlert.textContent = message;
      errorAlert.style.display = 'block';
      successMessage.style.display = 'none';
    }

    function setLoading(isLoading) {
      if (isLoading) {
        loginBtn.disabled = true;
        btnText.textContent = 'Signing In...';
        loadingIndicator.style.display = 'block';
      } else {
        loginBtn.disabled = false;
        btnText.textContent = 'Sign In to Dashboard';
        loadingIndicator.style.display = 'none';
      }
    }

    // Form submission
    form.addEventListener('submit', async function(e) {
      e.preventDefault();
      
      if (!validateForm()) {
        return;
      }
      
      setLoading(true);
      clearErrors();
      
      try {
        const response = await fetch('../PHP/admin_auth.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            email: emailInput.value.trim(),
            password: passwordInput.value
          })
        });

        const result = await response.json();

        if (!response.ok) {
          const message = result.message || 'Login failed. Please try again.';
          showErrorAlert(message);
          return;
        }

        if (result.success) {
          showSuccess('Login successful! Redirecting to dashboard...');
          setTimeout(() => {
            window.location.href = 'dashboard.php';
          }, 1500);
        } else {
          showErrorAlert(result.message || 'Login failed. Please try again.');
        }
      } catch (error) {
        console.error('Login error:', error);
        showErrorAlert('Network error. Please check your connection and try again.');
      } finally {
        setLoading(false);
      }
    });

    // Real-time validation
    emailInput.addEventListener('blur', function() {
      if (this.value.trim() && !isValidEmail(this.value.trim())) {
        showError(this, emailError, 'Please enter a valid email address');
      } else {
        this.classList.remove('error');
        emailError.style.display = 'none';
      }
    });

    passwordInput.addEventListener('blur', function() {
      if (this.value && this.value.length < 6) {
        showError(this, passwordError, 'Password must be at least 6 characters');
      } else {
        this.classList.remove('error');
        passwordError.style.display = 'none';
      }
    });

    // Forgot password function
    function forgotPassword() {
      alert('Please contact the system administrator to reset your password.');
    }

    // Clear errors on input
    [emailInput, passwordInput].forEach(input => {
      input.addEventListener('input', function() {
        this.classList.remove('error');
        const errorElement = this.nextElementSibling;
        if (errorElement && errorElement.classList.contains('error-message')) {
          errorElement.style.display = 'none';
        }
      });
    });
  </script>
</body>
</html>
