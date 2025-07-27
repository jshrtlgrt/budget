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
  <title>Login - Budget Request System</title>
  <link rel="stylesheet" href="style.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300&display=swap');

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: Arial, sans-serif;
    }

    body, html {
      height: 100%;
      width: 100%;
    }

    .login-container {
      display: flex;
      height: calc(100vh - 40px);
      width: 100%;
    }

    .left-pane {
      flex: 1;
      position: relative;
      background-color: #006633;
      overflow: hidden;
    }

    .bg-image {
      width: 100%;
      height: 100%;
      object-fit: cover;
      opacity: 0.4;
      position: absolute;
      top: 0;
      left: 0;
    }

    .right-pane {
      width: 35%;
      background-color: #ffffff;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 40px;
    }

    .form-wrapper {
      text-align: center;
      width: 100%;
    }

    .logo {
      width: 200px;
      margin-bottom: 150px;
    }

    h2 {
      margin-top: -100px;
      margin-bottom: 50px;
    }

    form {
      display: flex;
      flex-direction: column;
      gap: 15px;
    }

    input {
      padding: 10px;
      border-radius: 5px;
      border: 1px solid #999;
      width: 80%;
      display: block;
      margin: 0 auto;
    }

    button {
      padding: 10px;
      border: none;
      background-color: #006633;
      color: white;
      border-radius: 5px;
      cursor: pointer;
      width: 80%;
      display: block;
      margin: 0 auto;
    }

    button:hover {
      background-color: #004d26;
    }

    footer.footer {
      height: 40px;
      background-color: #ffffff;
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0 20px;
      font-size: 12px;
      font-weight: 300;
      font-family: 'Montserrat', sans-serif;
      border-top: 1px solid #ccc;
    }

    .error-message {
      color: #a94442;
      font-size: 14px;
      margin-bottom: 10px;
    }
  </style>
</head>
<body>
  <div class="login-container">
    <div class="left-pane">
      <img src="BRS_BG.png" alt="DLSU Building" class="bg-image">
    </div>
    <div class="right-pane">
      <div class="form-wrapper">
        <img src="DLSU_Logo.png" alt="DLSU Logo" class="logo">
        <h2>Login</h2>

        <?php if (!empty($login_error)): ?>
          <div class="error-message"><?php echo htmlspecialchars($login_error); ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST">
          <input type="email" name="email" placeholder="Email" required>
          <input type="password" name="password" placeholder="Password" required>
          <button type="submit">Login</button>
        </form>
      </div>
    </div>
  </div>

  <footer class="footer">
    <div class="footer-left">BUDGET REQUEST SYSTEM</div>
    <div class="footer-right">DE LA SALLE UNIVERSITY - MANILA</div>
  </footer>
</body>
</html>
