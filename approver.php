<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'approver') {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Approver Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Inter', sans-serif;
      margin: 0;
      padding: 0;
      background-color: #f2f2f2;
    }

    .header {
      padding: 20px;
      font-size: 28px;
      font-weight: bold;
      margin-left: 30px;
    }

    .filter-bar {
      display: flex;
      gap: 20px;
      background-color: #006837;
      color: white;
      padding: 15px 30px;
      align-items: center;
    }

    .filter-bar select {
      padding: 5px 10px;
      border-radius: 4px;
      border: none;
    }

    .budget-list {
      padding: 20px 30px;
    }

    .budget-card {
      background: #ccc;
      border-radius: 10px;
      padding: 15px 20px;
      margin-bottom: 20px;
      cursor: pointer;
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

    /* Modal */
    .modal {
      display: none;
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background-color: rgba(0,0,0,0.6);
      z-index: 999;
    }

    .modal-content {
      background: white;
      width: 80%;
      max-height: 90vh;
      overflow-y: auto;
      margin: 3% auto;
      border-radius: 10px;
      padding: 20px;
    }

    .section-title {
      background-color: #006837;
      color: white;
      padding: 10px;
      border-radius: 6px;
      margin: 20px 0 10px;
      font-weight: bold;
    }

    textarea {
      width: 100%;
      height: 60px;
      margin-bottom: 10px;
    }

    .action-buttons button {
      margin: 5px;
      padding: 8px 12px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
    }

    .approve { background: green; color: white; }
    .deny { background: darkred; color: white; }
    .request { background: gray; color: white; }

    .history p {
      background: #e7f7e7;
      padding: 10px;
      margin-bottom: 5px;
      border-left: 4px solid #4CAF50;
    }

    .close-btn {
      background: #666;
      color: white;
      padding: 8px 14px;
      border: none;
      border-radius: 5px;
      float: right;
      margin-top: 20px;
    }
  </style>
</head>
<body>

  <div class="header">Hello, Approver</div>

  <div class="filter-bar">
    <label>Sort By:
      <select>
        <option>Latest First</option>
        <option>Oldest First</option>
      </select>
    </label>
    <label>Status:
      <select>
        <option>All</option>
        <option>Pending</option>
        <option>Approved</option>
      </select>
    </label>
  </div>

  <div class="budget-list">
    <div class="budget-card" onclick="openModal()">
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

  <!-- Modal -->
  <div class="modal" id="detailsModal">
    <div class="modal-content">
      <h2>Budget Request: BR2025-078</h2>

      <div class="section-title">Basic Information</div>
      <p><strong>Submitted By:</strong> Juan Dela Cruz (juan@example.com)</p>
      <p><strong>Department:</strong> Information Technology (IT005)</p>
      <p><strong>College:</strong> College of Computer Studies</p>
      <p><strong>Academic Year:</strong> AY2025-2026</p>
      <p><strong>Submission Date:</strong> May 23, 2025</p>
      <p><strong>Request Title:</strong> Upgrade of Network Infrastructure Phase 2</p>
      <p><strong>Justification:</strong> To improve network speed and reliability across campus for all students and faculty.</p>
      <p><strong>Attached Budget Doc:</strong> network_upgrade_proposal.xlsx</p>
      <p><strong>Total Proposed Budget:</strong> <strong style="font-size: 20px;">PHP 1,500,000.00</strong></p>

      <div class="section-title">Line Item Details</div>
      <p><strong>GL Account:</strong> 51001001 - Network Equipment</p>
      <p><strong>Description:</strong> New Core Switches (2 Units)</p>
      <p><strong>Total Amount:</strong> PHP 600,000.00</p>
      <p><strong>Distribution:</strong> Annual</p>

      <div class="section-title">Approval Actions</div>
      <textarea placeholder="Enter your comments here..."></textarea>
      <div class="action-buttons">
        <button class="approve">Approve</button>
        <button class="deny">Deny</button>
        <button class="request">Request for Revision</button>
      </div>

      <div class="section-title">History and Comments Trail</div>
      <div class="history">
        <p><strong>May 23, 2025, 10:05 AM:</strong> Submitted by Juan Dela Cruz</p>
        <p><strong>May 23, 2025, 02:30 PM:</strong> Viewed by Maria Garcia<br><em>Action: Approved by Budget Unit Staff</em><br><em>Comments: Initial Review Complete</em></p>
        <p><strong>May 23, 2025, 03:50 PM:</strong> Viewed by Jirk Miranda</p>
      </div>

      <button class="close-btn" onclick="closeModal()">Close</button>
    </div>
  </div>

  <script>
    function openModal() {
      document.getElementById('detailsModal').style.display = 'block';
    }

    function closeModal() {
      document.getElementById('detailsModal').style.display = 'none';
    }
  </script>
</body>
</html>
