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
  <meta charset="UTF-8" />
  <title>Requester Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Inter', sans-serif;
      margin: 0;
      padding: 0;
      background-color: #fff;
    }

    .header {
      padding: 20px;
      font-size: 28px;
      font-weight: bold;
      margin-left: 30px;
    }

    .filter-bar {
      display: flex;
      justify-content: space-between;
      background-color: #006837;
      color: white;
      padding: 15px 30px;
      align-items: center;
    }

    .filters {
      display: flex;
      gap: 20px;
    }

    .filter-bar label {
      font-weight: bold;
    }

    select {
      padding: 5px;
      border-radius: 5px;
      border: none;
    }

    .create-button {
      background-color: white;
      color: #006837;
      border: none;
      padding: 8px 16px;
      font-weight: bold;
      border-radius: 6px;
      cursor: pointer;
    }

    .create-button:hover {
      background-color: #e5f6e5;
    }

    .budget-list {
      padding: 20px 30px;
    }

    .budget-card {
      background: #ccc;
      border-radius: 10px;
      padding: 15px 20px;
      margin-bottom: 20px;
    }

    .budget-card h3 {
      margin: 0 0 10px;
    }

    .budget-card p {
      margin: 5px 0;
    }

    .approved {
      background-color: #d1e7d1;
    }

    .pending {
      background-color: #f2d3a3;
    }
  </style>
</head>
<body>

  <div class="header">Hello, Requester</div>

  <div class="filter-bar">
    <div class="filters">
      <label>
        Sort By:
        <select>
          <option>Latest First</option>
          <option>Oldest First</option>
        </select>
      </label>
      <label>
        Status:
        <select>
          <option>All</option>
          <option>Pending</option>
          <option>Approved</option>
        </select>
      </label>
    </div>
    <button class="create-button" onclick="window.location.href='create.html'">+ Create</button>
  </div>

  <div class="budget-list">
    <div class="budget-card pending">
      <h3>Budget Request: BR2025-078</h3>
      <p>📅 Submitted: May 23, 2025</p>
      <p>🏫 Academic Year: 2024–2025</p>
      <p>⏳ Status: Pending</p>
    </div>

    <div class="budget-card approved">
      <h3>Budget Request: BR2024-075</h3>
      <p>📅 Submitted: May 23, 2024</p>
      <p>🏫 Academic Year: 2023–2024</p>
      <p>✅ Status: Approved</p>
    </div>
  </div>

</body>
</html>
