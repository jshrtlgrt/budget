<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'requester') {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Requester Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat&display=swap" rel="stylesheet">
  <style>
    * {
      box-sizing: border-box;
      font-family: 'Montserrat', sans-serif;
    }

    html, body {
      height: 100%;
      margin: 0;
      padding: 0;
      background-color: #0b0f12;
    }

    .dashboard-container {
      display: flex;
      flex-direction: column;
      height: 100%;
      width: 100%;
      padding: 30px;
      background: white;
    }

    .header-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }

    h1 {
      font-size: 28px;
      font-weight: 700;
    }

    .create-btn {
    background-color: #015c2e;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 5px;
    font-size: 14px;
    cursor: pointer;
    margin-top: 5px; 
    text-decoration: none;
    display: inline-block;
    }


    .create-btn:hover {
      background-color: #013f21;
    }

    .filters {
      background-color: #015c2e;
      padding: 10px 20px;
      display: flex;
      align-items: center;
      gap: 20px;
      flex-wrap: wrap;
      border-radius: 5px;
    }

    .filters label {
      color: white;
      font-weight: bold;
    }

    .filters select {
      padding: 6px 10px;
      border-radius: 5px;
      border: none;
      font-size: 14px;
    }

    .content-box {
      flex: 1;
      border: 1px solid #ccc;
      border-radius: 5px;
      padding: 20px;
      margin-top: 20px;
      overflow-y: auto;
    }

    .request-card {
      background-color: #dcdcdc;
      padding: 20px;
      border-radius: 5px;
      margin-bottom: 15px;
    }

    .request-title {
      font-weight: 700;
      margin-bottom: 10px;
    }

    .request-detail {
      margin-bottom: 6px;
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 14px;
    }

    .request-detail i {
      font-style: normal;
    }

    .status-approved {
      color: green;
    }

    .status-pending {
      color: orange;
    }

    .status-rejected {
      color: red;
    }
  </style>
</head>
<body>

  <div class="dashboard-container">
    <div class="header-bar">
      <h1>REQUESTER DASHBOARD</h1>
      <a href="create.html" class="create-btn">+ Create</a>

    </div>

    <div class="filters">
      <label for="sort">Sort By:</label>
      <select id="sort">
        <option>Latest first</option>
        <option>Oldest</option>
      </select>

      <label for="status">Status:</label>
      <select id="status">
        <option>Approved</option>
        <option>Rejected</option>
        <option>More Information</option>
      </select>
    </div>

    <div class="content-box">

      <!-- Placeholder for dynamic cards -->
      <div class="request-card">
        <div class="request-title">BUDGET REQUEST: BR2025-078</div>
        <div class="request-detail"><i>📅</i> Submitted: May 23, 2025</div>
        <div class="request-detail"><i>📖</i> Academic Year: 2024 - 2025</div>
        <div class="request-detail status-pending"><i>⏳</i> Status: Pending</div>
      </div>

      <div class="request-card">
        <div class="request-title">BUDGET REQUEST: BR2025-075</div>
        <div class="request-detail"><i>📅</i> Submitted: May 23, 2024</div>
        <div class="request-detail"><i>📖</i> Academic Year: 2023 - 2024</div>
        <div class="request-detail status-approved"><i>✅</i> Status: Approved</div>
      </div>

    </div>
  </div>

</body>
</html>
