<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'requester') {
    header("Location: login.php");
    exit;

    require_once 'auth_check.php';
requireLogin();

}

$pdo = new PDO("mysql:host=localhost;dbname=budget_database_schema", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$username = $_SESSION['username'];
$stmt = $pdo->prepare("SELECT id FROM account WHERE username_email = ?");
$stmt->execute([$username]);
$account_id = $stmt->fetchColumn();

$requests = [];
if ($account_id) {
    $stmt = $pdo->prepare("SELECT * FROM budget_request WHERE account_id = ? ORDER BY timestamp DESC");
    $stmt->execute([$account_id]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
      cursor: pointer;
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

    .status-approved { color: green; }
    .status-pending { color: orange; }
    .status-rejected { color: red; }

    /* Modal Styling */
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0; top: 0;
      width: 100%; height: 100%;
      background-color: rgba(0,0,0,0.5);
    }

    .modal-content {
      background-color: #fff;
      margin: 10% auto;
      padding: 20px;
      width: 80%;
      max-height: 70%;
      overflow-y: auto;
      border-radius: 10px;
      position: relative;
    }

    .close-btn {
      position: absolute;
      top: 10px; right: 20px;
      font-size: 20px;
      font-weight: bold;
      cursor: pointer;
      color: #333;
    }

    .entry-row {
      border-bottom: 1px solid #ccc;
      padding: 6px 0;
      font-size: 14px;
    }
  </style>
</head>
<body>

<div class="dashboard-container">
  <div class="header-bar">
    <h1>REQUESTER DASHBOARD</h1>
    <a href="create_request.php" class="create-btn">+ Create</a>
  </div>

  <div class="filters">
    <label for="sort">Sort By:</label>
    <select id="sort">
      <option>Latest first</option>
      <option>Oldest</option>
    </select>

    <label for="status">Status:</label>
    <select id="status">
      <option>Submitted</option>
      <option>Approved</option>
      <option>Rejected</option>
      <option>More Information</option>
    </select>
  </div>

  <div class="content-box">
    <?php foreach ($requests as $req): ?>
      <div class="request-card" onclick="showModal('<?php echo $req['request_id']; ?>')">
        <div class="request-title">BUDGET REQUEST: <?php echo $req['request_id']; ?></div>
        <div class="request-detail"><i>📅</i> Submitted: <?php echo date("F j, Y", strtotime($req['timestamp'])); ?></div>
        <div class="request-detail"><i>📖</i> Academic Year: <?php echo $req['academic_year']; ?></div>
        <div class="request-detail status-<?php echo strtolower($req['status']); ?>">
          <i>⏳</i> Status: <?php echo $req['status']; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Modal -->
<div class="modal" id="detailsModal">
  <div class="modal-content">
    <span class="close-btn" onclick="document.getElementById('detailsModal').style.display='none'">&times;</span>
    <h2>Request Details</h2>
    <div id="modalBody">Loading...</div>
  </div>
</div>

<script>
  function showModal(requestId) {
    const modal = document.getElementById("detailsModal");
    const modalBody = document.getElementById("modalBody");
    modalBody.innerHTML = "Loading...";

    fetch(`fetch_request_details.php?request_id=${requestId}`)
      .then(res => res.text())
      .then(data => {
        modalBody.innerHTML = data;
        modal.style.display = "block";
      })
      .catch(err => {
        modalBody.innerHTML = "Failed to load details.";
      });
  }
</script>

</body>
</html>
