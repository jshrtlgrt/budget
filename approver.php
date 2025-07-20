<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'approver') {
    header("Location: login.php");
    exit;
}
require 'db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Approver Dashboard</title>
  <link rel="stylesheet" href="style.css" />
  <style>
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      margin: 0;
      padding: 0;
      background-color: #f4f4f4;
    }

    header {
      background-color: #015c2e;
      color: #ffffff;
      padding: 1rem;
      text-align: center;
    }

    .dashboard {
      max-width: 1200px;
      margin: 20px auto;
      padding: 20px;
      background: #ffffff;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    }

    .budget-list {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 20px;
    }

    .budget-card {
      background: #eafaf0;
      padding: 20px;
      border-left: 5px solid #028c48;
      border-radius: 5px;
      cursor: pointer;
      transition: transform 0.2s;
    }

    .budget-card:hover {
      transform: scale(1.02);
      background-color: #dcf5e6;
    }

    .budget-card h3 {
      margin-top: 0;
    }

    .approved {
      border-left-color: green;
    }

    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      justify-content: center;
      align-items: center;
    }

    .modal-content {
      background: white;
      padding: 30px;
      border-radius: 10px;
      width: 500px;
      max-width: 90%;
    }

    .modal-content h2 {
      margin-top: 0;
    }

    .close {
      float: right;
      cursor: pointer;
      font-size: 1.2em;
    }
  </style>
</head>
<body>
  <header>
    <h1>Budget Approval Dashboard</h1>
  </header>
  <div class="dashboard">
    <h2>Pending Approvals</h2>
    <div class="budget-list">
      <?php
      $sql = "SELECT * FROM budget_request ORDER BY timestamp DESC";
      $result = $conn->query($sql);

      if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
          $statusClass = strtolower($row['status']) === 'approved' ? 'approved' : '';
          echo '<div class="budget-card ' . $statusClass . '" onclick="openModal()">';
          echo '<h3>Budget Request: ' . htmlspecialchars($row['request_id']) . '</h3>';
          echo '<p>📅 Submitted: ' . date("F d, Y", strtotime($row['timestamp'])) . '</p>';
          echo '<p>🏫 Academic Year: ' . htmlspecialchars($row['academic_year']) . '</p>';
          echo '<p>⏳ Status: ' . htmlspecialchars($row['status']) . '</p>';
          echo '</div>';
        }
      } else {
        echo "<p>No budget requests found.</p>";
      }
      ?>
    </div>
  </div>

  <div class="modal" id="detailsModal">
    <div class="modal-content">
      <span class="close" onclick="closeModal()">&times;</span>
      <h2>Request Details</h2>
      <p><strong>Department:</strong> Example Department</p>
      <p><strong>Amount:</strong> PHP 500,000</p>
      <p><strong>Description:</strong> Purchase of new equipment</p>
    </div>
  </div>

  <script>
    function openModal() {
      document.getElementById("detailsModal").style.display = "flex";
    }

    function closeModal() {
      document.getElementById("detailsModal").style.display = "none";
    }

    window.onclick = function(event) {
      let modal = document.getElementById("detailsModal");
      if (event.target === modal) {
        modal.style.display = "none";
      }
    };
  </script>
</body>
</html>
