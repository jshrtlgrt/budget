<?php
session_start();
require 'db.php'; // this should contain $conn = new mysqli(...);

$login_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT * FROM account WHERE username_email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
  
    if ($user && $user['password'] === $password) { // use password_verify() if hashed
        $_SESSION['username'] = $user['username_email'];
        $_SESSION['role'] = strtolower($user['role']);
        $_SESSION['user_id'] = $user['id'];

        // Route to appropriate dashboard based on role
        $approver_roles = ['approver', 'department_head', 'dean', 'vp_finance'];
        if (in_array($_SESSION['role'], $approver_roles)) {
            header("Location: approver.php");
        } else {
            header("Location: requester.php");
        }
        exit;
    } else {
        $login_error = "Invalid email or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Budget Management System - DLSU Login</title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body, html {
      height: 100%;
      width: 100%;
      font-family: 'Montserrat', sans-serif;
      overflow-x: hidden;
    }

    /* Animated background particles */
    .particles {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      pointer-events: none;
      z-index: 1;
    }

    .particle {
      position: absolute;
      background: rgba(0, 176, 79, 0.1);
      border-radius: 50%;
      animation: float 6s ease-in-out infinite;
    }

    @keyframes float {
      0%, 100% { transform: translateY(0px) rotate(0deg); opacity: 0.3; }
      50% { transform: translateY(-20px) rotate(180deg); opacity: 0.8; }
    }

    .login-container {
      display: flex;
      height: 100vh;
      width: 100%;
      position: relative;
      z-index: 2;
    }

    /* Enhanced Left Pane */
    .left-pane {
      flex: 1;
      position: relative;
      background: linear-gradient(135deg, #00B04F 0%, #008037 50%, #004d26 100%);
      overflow: hidden;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
    }

    .bg-image {
      width: 100%;
      height: 100%;
      object-fit: cover;
      opacity: 0.15;
      position: absolute;
      top: 0;
      left: 0;
    }

    .left-content {
      position: relative;
      z-index: 3;
      text-align: center;
      color: white;
      padding: 40px;
      max-width: 500px;
    }

    .system-title {
      font-size: 3rem;
      font-weight: 700;
      margin-bottom: 20px;
      text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
      animation: slideInLeft 1s ease-out;
    }

    .system-subtitle {
      font-size: 1.2rem;
      margin-bottom: 40px;
      opacity: 0.9;
      font-weight: 400;
      animation: slideInLeft 1s ease-out 0.2s both;
    }

    .features-list {
      text-align: left;
      margin-top: 40px;
      animation: slideInLeft 1s ease-out 0.4s both;
    }

    .feature-item {
      display: flex;
      align-items: center;
      margin-bottom: 15px;
      font-size: 1rem;
      opacity: 0.9;
    }

    .feature-icon {
      font-size: 1.5rem;
      margin-right: 15px;
      background: rgba(255,255,255,0.2);
      padding: 8px;
      border-radius: 50%;
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    @keyframes slideInLeft {
      from { opacity: 0; transform: translateX(-50px); }
      to { opacity: 1; transform: translateX(0); }
    }

    /* Enhanced Right Pane */
    .right-pane {
      width: 40%;
      background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 60px 40px;
      box-shadow: -10px 0 30px rgba(0,0,0,0.1);
      position: relative;
    }

    .form-wrapper {
      text-align: center;
      width: 100%;
      max-width: 400px;
      animation: slideInRight 1s ease-out;
    }

    @keyframes slideInRight {
      from { opacity: 0; transform: translateX(50px); }
      to { opacity: 1; transform: translateX(0); }
    }

    .logo {
      width: 120px;
      height: 120px;
      margin-bottom: 30px;
      border-radius: 50%;
      box-shadow: 0 10px 30px rgba(0,176,79,0.3);
      border: 4px solid #00B04F;
      padding: 10px;
      background: white;
      object-fit: contain;
      animation: logoFloat 3s ease-in-out infinite;
    }

    @keyframes logoFloat {
      0%, 100% { transform: translateY(0px) scale(1); }
      50% { transform: translateY(-10px) scale(1.02); }
    }

    .welcome-text {
      margin-bottom: 40px;
    }

    .welcome-title {
      font-size: 2rem;
      font-weight: 600;
      color: #015c2e;
      margin-bottom: 10px;
      text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
    }

    .welcome-subtitle {
      color: #6c757d;
      font-size: 1rem;
      font-weight: 400;
    }

    /* Enhanced Form Styling */
    form {
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    .input-group {
      position: relative;
      margin-bottom: 10px;
    }

    .input-icon {
      position: absolute;
      left: 15px;
      top: 50%;
      transform: translateY(-50%);
      color: #6c757d;
      font-size: 1.2rem;
      z-index: 2;
    }

    input {
      width: 100%;
      padding: 15px 15px 15px 50px;
      border: 2px solid #e9ecef;
      border-radius: 12px;
      font-size: 1rem;
      font-family: 'Montserrat', sans-serif;
      background: white;
      transition: all 0.3s ease;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    input:focus {
      outline: none;
      border-color: #00B04F;
      box-shadow: 0 4px 20px rgba(0,176,79,0.2);
      transform: translateY(-2px);
    }

    input::placeholder {
      color: #adb5bd;
      font-weight: 400;
    }

    .login-btn {
      background: linear-gradient(135deg, #00B04F 0%, #008037 100%);
      color: white;
      border: none;
      padding: 15px;
      border-radius: 12px;
      font-size: 1.1rem;
      font-weight: 600;
      cursor: pointer;
      font-family: 'Montserrat', sans-serif;
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(0,176,79,0.3);
      margin-top: 10px;
    }

    .login-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 25px rgba(0,176,79,0.4);
    }

    .login-btn:active {
      transform: translateY(0px);
    }

    /* Enhanced Error Message */
    .error-message {
      background: linear-gradient(135deg, #f8d7da, #f5c6cb);
      color: #721c24;
      padding: 15px;
      border-radius: 12px;
      font-size: 14px;
      margin-bottom: 20px;
      border: 1px solid #f5c6cb;
      box-shadow: 0 4px 15px rgba(220,53,69,0.2);
      animation: shake 0.5s ease-in-out;
    }

    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      25% { transform: translateX(-5px); }
      75% { transform: translateX(5px); }
    }

    /* Enhanced Footer */
    .footer {
      background: linear-gradient(135deg, #015c2e 0%, #00B04F 100%);
      color: white;
      padding: 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 14px;
      font-weight: 500;
      box-shadow: 0 -4px 15px rgba(0,0,0,0.1);
    }

    .footer-left, .footer-right {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    /* Security badge */
    .security-badge {
      position: absolute;
      bottom: 20px;
      right: 20px;
      background: rgba(255,255,255,0.9);
      padding: 10px 15px;
      border-radius: 25px;
      font-size: 12px;
      color: #015c2e;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      font-weight: 500;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      .login-container {
        flex-direction: column;
      }
      .left-pane {
        height: 40%;
      }
      .right-pane {
        width: 100%;
        height: 60%;
      }
      .system-title {
        font-size: 2rem;
      }
    }
  </style>
</head>
<body>
  <!-- Animated background particles -->
  <div class="particles" id="particles"></div>

  <div class="login-container">
    <!-- Enhanced Left Pane with System Information -->
    <div class="left-pane">
      <img src="BRS_BG.png" alt="DLSU Building" class="bg-image">
      <div class="left-content">
        <h1 class="system-title">Budget Management System</h1>
        <p class="system-subtitle">Streamlined Financial Planning & Resource Allocation</p>
        
      </div>
    </div>

    <!-- Enhanced Right Pane with Modern Form -->
    <div class="right-pane">
      <div class="form-wrapper">
        <img src="assets/dlsulogo.png" alt="DLSU Logo" class="logo">
        
        <div class="welcome-text">
          <h2 class="welcome-title">Welcome Back</h2>
          <p class="welcome-subtitle">Sign in to your account to continue</p>
        </div>

        <?php if (!empty($login_error)): ?>
          <div class="error-message">
            🚨 <?php echo htmlspecialchars($login_error); ?>
          </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
          <div class="input-group">
            <div class="input-icon">👤</div>
            <input type="email" name="email" placeholder="Enter your email address" required>
          </div>
          <div class="input-group">
            <div class="input-icon">🔒</div>
            <input type="password" name="password" placeholder="Enter your password" required>
          </div>
          <button type="submit" class="login-btn">Sign In to Dashboard</button>
        </form>
      </div>
      
    </div>
  </div>

  <footer class="footer">
    <div class="footer-left">
      <span>BUDGET MANAGEMENT SYSTEM</span>
    </div>
    <div class="footer-right">
      <span>DE LA SALLE UNIVERSITY - MANILA</span>
    </div>
  </footer>

  <script>
    // Create animated background particles
    function createParticles() {
      const particlesContainer = document.getElementById('particles');
      const particleCount = 20;
      
      for (let i = 0; i < particleCount; i++) {
        const particle = document.createElement('div');
        particle.className = 'particle';
        
        // Random size and position
        const size = Math.random() * 40 + 10;
        particle.style.width = size + 'px';
        particle.style.height = size + 'px';
        particle.style.left = Math.random() * 100 + '%';
        particle.style.top = Math.random() * 100 + '%';
        
        // Random animation delay
        particle.style.animationDelay = Math.random() * 6 + 's';
        particle.style.animationDuration = (Math.random() * 4 + 4) + 's';
        
        particlesContainer.appendChild(particle);
      }
    }
    
    // Add loading animation to form inputs
    document.addEventListener('DOMContentLoaded', function() {
      createParticles();
      
      const inputs = document.querySelectorAll('input');
      inputs.forEach(input => {
        input.addEventListener('focus', function() {
          this.parentElement.style.transform = 'scale(1.02)';
        });
        
        input.addEventListener('blur', function() {
          this.parentElement.style.transform = 'scale(1)';
        });
      });
      
      // Add pulse effect to login button on hover
      const loginBtn = document.querySelector('.login-btn');
      loginBtn.addEventListener('mouseenter', function() {
        this.style.animation = 'none';
        this.offsetHeight; // Trigger reflow
        this.style.animation = 'pulse 0.6s ease-in-out';
      });
    });
    
    // CSS animation for pulse effect
    const style = document.createElement('style');
    style.textContent = `
      @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
      }
    `;
    document.head.appendChild(style);
  </script>
</body>
</html>
