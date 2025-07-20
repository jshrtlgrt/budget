<!-- <?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['email']; // assuming email as username
    $_SESSION['username'] = $username;
    $_SESSION['role'] = 'requester'; // hardcoded for now (can be 'approver' or 'requester')
    
    header("Location: requester.php");
    exit;
}
?> -->

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
        height: calc(100vh - 40px); /* Leave space for footer */
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
        <form action="requester.php" method="GET">
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
