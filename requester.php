<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'requester') {
    header("Location: login.php");
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=budget_database_schema", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$username = $_SESSION['username'];
$stmt = $pdo->prepare("SELECT id FROM account WHERE username_email = ?");
$stmt->execute([$username]);
$account_id = $stmt->fetchColumn();

// Handle filter parameters
$status_filter = $_GET['status'] ?? 'all';
$sort_by = $_GET['sort'] ?? 'latest';

$requests = [];
if ($account_id) {
    $sql = "SELECT * FROM budget_request WHERE account_id = ?";
    $params = [$account_id];
    
    // Apply status filter
    if ($status_filter !== 'all') {
        $status_map = [
            'pending' => 'pending',
            'submitted' => 'pending', // alias for pending
            'approved' => 'approved', 
            'rejected' => 'rejected',
            'more_information' => 'more_info_requested'
        ];
        
        if (isset($status_map[$status_filter])) {
            $sql .= " AND status = ?";
            $params[] = $status_map[$status_filter];
        }
    }
    
    // Apply sorting
    switch ($sort_by) {
        case 'oldest':
            $sql .= " ORDER BY timestamp ASC"; // Oldest first (earliest dates first)
            break;
        case 'latest':
        default:
            $sql .= " ORDER BY timestamp DESC"; // Latest first (newest dates first)
            break;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
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
      margin: 5% auto;
      padding: 20px;
      width: 90%;
      max-width: 1200px;
      max-height: 85%;
      overflow-y: auto;
      border-radius: 10px;
      position: relative;
      box-shadow: 0 4px 20px rgba(0,0,0,0.15);
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

    /* Distribution Modal Styles */
    .distribution-modal {
      display: none;
      position: fixed;
      z-index: 2000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0,0,0,0.5);
    }
    
    .distribution-modal-content {
      background-color: #fff;
      margin: 5% auto;
      padding: 30px;
      border-radius: 10px;
      width: 80%;
      max-width: 600px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    }
    
    .distribution-close {
      color: #aaa;
      float: right;
      font-size: 28px;
      font-weight: bold;
      cursor: pointer;
    }
    
    .distribution-close:hover {
      color: #000;
    }
    
    .clickable-amount {
      color: #015c2e;
      text-decoration: underline;
      cursor: pointer;
    }
    
    .clickable-amount:hover {
      color: #004d26;
      font-weight: bold;
    }
    
    .distribution-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
    }
    
    .distribution-table th,
    .distribution-table td {
      padding: 10px;
      border: 1px solid #ddd;
      text-align: left;
    }
    
    .distribution-table th {
      background-color: #f8f9fa;
      font-weight: bold;
    }
  </style>
</head>
<body>

<div class="dashboard-container">
  <div class="header-bar">
    <h1>REQUESTER DASHBOARD</h1>
    <div>
      <a href="create_request.php" class="create-btn">+ Create</a>
      <a href="logout.php" class="create-btn" style="background-color: #dc3545; margin-left: 10px;">Logout</a>
    </div>
  </div>

  <form method="GET" class="filters">
    <label for="sort">Sort By:</label>
    <select id="sort" name="sort" onchange="this.form.submit()">
      <option value="latest" <?php echo $sort_by === 'latest' ? 'selected' : ''; ?>>Latest first</option>
      <option value="oldest" <?php echo $sort_by === 'oldest' ? 'selected' : ''; ?>>Oldest</option>
    </select>

    <label for="status">Status:</label>
    <select id="status" name="status" onchange="this.form.submit()">
      <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
      <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
      <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
      <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
      <option value="more_information" <?php echo $status_filter === 'more_information' ? 'selected' : ''; ?>>More Information</option>
    </select>
  </form>

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
    <div id="modalBody">Loading...</div>
  </div>
</div>

<!-- Distribution Modal -->
<div id="distributionModal" class="distribution-modal">
  <div class="distribution-modal-content">
    <span class="distribution-close" onclick="closeDistributionModal()">&times;</span>
    <h2>Budget Distribution Details</h2>
    <div id="distributionDetails">
      <!-- Distribution details will be populated here -->
    </div>
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

  // Budget Distribution Functions
  function getAcademicYearMonths() {
    // Academic year runs from September 2025 to August 2026
    const months = [];
    
    // September to December 2025
    for (let month = 9; month <= 12; month++) {
      months.push({
        month: month,
        year: 2025,
        name: new Date(2025, month - 1).toLocaleString('default', { month: 'long' }) + ' 2025'
      });
    }
    
    // January to August 2026
    for (let month = 1; month <= 8; month++) {
      months.push({
        month: month,
        year: 2026,
        name: new Date(2026, month - 1).toLocaleString('default', { month: 'long' }) + ' 2026'
      });
    }
    
    return months;
  }

  function calculateDistribution(totalAmount, duration) {
    const months = getAcademicYearMonths();
    const distribution = [];
    
    switch(duration) {
      case 'Monthly':
        const monthlyAmount = totalAmount / months.length;
        months.forEach(month => {
          distribution.push({
            period: month.name,
            amount: monthlyAmount
          });
        });
        break;
        
      case 'Quarterly':
        const quarterlyAmount = totalAmount / 4;
        const quarters = [
          { name: 'Q1 (Sep-Nov 2025)', months: months.slice(0, 3) },
          { name: 'Q2 (Dec 2025 - Feb 2026)', months: months.slice(3, 6) },
          { name: 'Q3 (Mar-May 2026)', months: months.slice(6, 9) },
          { name: 'Q4 (Jun-Aug 2026)', months: months.slice(9, 12) }
        ];
        
        quarters.forEach(quarter => {
          distribution.push({
            period: quarter.name,
            amount: quarterlyAmount
          });
        });
        break;
        
      case 'Annually':
      default:
        distribution.push({
          period: 'Annual (Sep 2025 - Aug 2026)',
          amount: totalAmount
        });
        break;
    }
    
    return distribution;
  }

  function showDistribution(glCode, description, amount, duration) {
    const distribution = calculateDistribution(amount, duration);
    
    let detailsHTML = `
      <div style="margin-bottom: 20px;">
        <p><strong>Line Item:</strong> ${glCode}</p>
        <p><strong>Description:</strong> ${description}</p>
        <p><strong>Total Amount:</strong> PHP ${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</p>
        <p><strong>Distribution Chosen:</strong> ${duration}</p>
      </div>
      
      <table class="distribution-table">
        <thead>
          <tr>
            <th>Period</th>
            <th>Amount</th>
          </tr>
        </thead>
        <tbody>
    `;
    
    distribution.forEach(item => {
      detailsHTML += `
        <tr>
          <td>${item.period}</td>
          <td>PHP ${item.amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
        </tr>
      `;
    });
    
    detailsHTML += `
        </tbody>
      </table>
    `;
    
    document.getElementById('distributionDetails').innerHTML = detailsHTML;
    document.getElementById('distributionModal').style.display = 'block';
  }

  function closeDistributionModal() {
    document.getElementById('distributionModal').style.display = 'none';
  }

  // Close distribution modal when clicking outside of it
  window.addEventListener('click', function(event) {
    const distributionModal = document.getElementById('distributionModal');
    const detailsModal = document.getElementById('detailsModal');
    
    if (event.target === distributionModal) {
      distributionModal.style.display = 'none';
    }
    if (event.target === detailsModal) {
      detailsModal.style.display = 'none';
    }
  });
</script>

</body>
</html>
