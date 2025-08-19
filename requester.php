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

// Handle success/error messages
$success_message = $_GET['success'] ?? '';
$error_message = $_GET['error'] ?? '';

$requests = [];
if ($account_id) {
    $sql = "SELECT br.*, 
            CASE WHEN ap.status IN ('approved', 'rejected', 'request_info') THEN 1 ELSE 0 END as level1_processed,
            COALESCE(ba_count.amendment_count, 0) as amendment_count
            FROM budget_request br 
            LEFT JOIN approval_progress ap ON br.request_id = ap.request_id AND ap.approval_level = 1
            LEFT JOIN (
                SELECT request_id, COUNT(*) as amendment_count 
                FROM budget_amendments 
                GROUP BY request_id
            ) ba_count ON br.request_id = ba_count.request_id
            WHERE br.account_id = ?";
    $params = [$account_id];
    
    // Apply status filter
    if ($status_filter !== 'all') {
        $status_map = [
            'pending' => 'pending',
            'submitted' => 'pending', // alias for pending
            'approved' => 'approved', 
            'rejected' => 'rejected',
            'more_information' => 'more_info_requested',
            'more_info_requested' => 'more_info_requested'
        ];
        
        if (isset($status_map[$status_filter])) {
            $sql .= " AND br.status = ?";
            $params[] = $status_map[$status_filter];
        }
    }
    
    // Apply sorting
    switch ($sort_by) {
        case 'oldest':
            $sql .= " ORDER BY br.timestamp ASC"; // Oldest first (earliest dates first)
            break;
        case 'latest':
        default:
            $sql .= " ORDER BY br.timestamp DESC"; // Latest first (newest dates first)
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
  <title>Requester Dashboard - Budget Management System</title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      /* Light mode variables */
      --bg-primary: #f8f9fa;
      --bg-secondary: #ffffff;
      --bg-tertiary: #e9ecef;
      --text-primary: #212529;
      --text-secondary: #495057;
      --text-muted: #6c757d;
      --border-color: #dee2e6;
      --shadow-color: rgba(0,0,0,0.08);
      --card-bg: #ffffff;
      --header-bg: #ffffff;
      --green-primary: #00B04F;
      --green-secondary: #008037;
      --green-dark: #015c2e;
    }

    [data-theme="dark"] {
      /* Dark mode variables */
      --bg-primary: #1a1d23;
      --bg-secondary: #2c3039;
      --bg-tertiary: #3c4043;
      --text-primary: #ffffff;
      --text-secondary: #e4e6ea;
      --text-muted: #b0b3b8;
      --border-color: #495057;
      --shadow-color: rgba(0,0,0,0.3);
      --card-bg: #2c3039;
      --header-bg: #2c3039;
      --green-primary: #00B04F;
      --green-secondary: #00d055;
      --green-dark: #00B04F;
    }

    * {
      box-sizing: border-box;
      font-family: 'Montserrat', sans-serif;
    }

    html, body {
      height: 100%;
      margin: 0;
      padding: 0;
      background-color: var(--bg-primary);
      color: var(--text-primary);
      transition: all 0.3s ease;
    }

    /* Educational Header Styling - Green Pantone 355U to White */
    .institution-header {
      background: linear-gradient(135deg, #00B04F 0%, #4CAF50 30%, #ffffff 100%);
      color: #333;
      padding: 0;
      position: relative;
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
      overflow: hidden;
    }

    .institution-header::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      height: 30px;
      background: linear-gradient(45deg, transparent 0%, transparent 45%, #f8f9fa 50%, #f8f9fa 100%);
      transform: skewY(-2deg);
      transform-origin: bottom left;
    }

    .header-content {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 20px 40px;
      position: relative;
      z-index: 2;
    }

    .logo-section {
      display: flex;
      align-items: center;
      gap: 20px;
    }

    .logo-placeholder {
      width: 70px;
      height: 70px;
      background: white;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      font-weight: 600;
      color: #00B04F;
      box-shadow: 0 2px 8px rgba(0,0,0,0.2);
      text-align: center;
      line-height: 1.2;
      padding: 5px;
      border: 2px solid #FFD700;
    }

    /* Actual logo styling - replace the placeholder */
    .institution-logo {
      width: 120px;
      height: 120px;
      object-fit: contain;
    }

    .institution-info h1 {
      margin: 0;
      font-size: 24px;
      font-weight: 700;
      color: white;
      text-shadow: 2px 2px 4px rgba(0,176,79,0.8), 1px 1px 2px rgba(0,176,79,1);
    }

    .institution-info p {
      margin: 4px 0 0 0;
      font-size: 14px;
      color: #333;
      font-weight: 500;
    }

    .header-nav {
      display: flex;
      align-items: center;
      gap: 20px;
    }

    .user-info {
      text-align: right;
    }

    .user-info .username {
      font-size: 16px;
      font-weight: 600;
      margin: 0;
      color: #00B04F;
    }

    .user-info .role {
      font-size: 12px;
      color: #666;
      margin: 2px 0 0 0;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .logout-btn {
      background: #00B04F;
      border: 1px solid #00B04F;
      color: white;
      padding: 8px 16px;
      border-radius: 6px;
      text-decoration: none;
      font-size: 14px;
      font-weight: 500;
      transition: all 0.3s ease;
      box-shadow: 0 2px 4px rgba(0,176,79,0.3);
    }

    .logout-btn:hover {
      background: #009640;
      border-color: #009640;
      transform: translateY(-1px);
      box-shadow: 0 4px 8px rgba(0,176,79,0.4);
    }

    .dashboard-container {
      display: flex;
      flex-direction: column;
      min-height: calc(100vh - 140px);
      width: 100%;
      padding: 40px;
      background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-tertiary) 100%);
    }

    .header-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
      background: var(--header-bg);
      padding: 25px 30px;
      border-radius: 15px;
      box-shadow: 0 4px 20px var(--shadow-color);
      border: 1px solid var(--border-color);
    }

    h1 {
      font-size: 32px;
      font-weight: 700;
      color: var(--green-dark);
      margin: 0;
      text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
    }

    .create-btn {
      background: linear-gradient(135deg, #00B04F 0%, #008037 100%);
      color: white;
      border: none;
      padding: 12px 24px;
      border-radius: 12px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(0,176,79,0.3);
    }

    .create-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 25px rgba(0,176,79,0.4);
    }

    .create-btn:active {
      transform: translateY(0px);
    }

    .filters {
      background: linear-gradient(135deg, #00B04F 0%, #008037 100%);
      padding: 20px 25px;
      display: flex;
      align-items: center;
      gap: 25px;
      flex-wrap: wrap;
      border-radius: 15px;
      box-shadow: 0 4px 20px rgba(0,176,79,0.2);
      margin-bottom: 25px;
    }

    .filters label {
      color: white;
      font-weight: 600;
      font-size: 15px;
    }

    .filters select {
      padding: 10px 15px;
      border-radius: 10px;
      border: none;
      font-size: 14px;
      background-color: white;
      color: #333;
      cursor: pointer;
      font-weight: 500;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      transition: all 0.3s ease;
    }

    .filters select:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    }

    .filters select option {
      background-color: white;
      color: #333;
      padding: 8px;
    }

    .search-input {
      padding: 10px 15px;
      border-radius: 10px;
      border: none;
      font-size: 14px;
      background-color: white;
      color: #333;
      font-weight: 500;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      transition: all 0.3s ease;
      min-width: 200px;
    }

    .search-input:hover, .search-input:focus {
      transform: translateY(-1px);
      box-shadow: 0 4px 15px rgba(0,0,0,0.15);
      outline: none;
    }

    .search-input::placeholder {
      color: #999;
      font-style: italic;
    }

    .content-box {
      flex: 1;
      background: var(--card-bg);
      border-radius: 15px;
      padding: 25px;
      box-shadow: 0 4px 20px var(--shadow-color);
      border: 1px solid var(--border-color);
      overflow-y: auto;
    }

    .request-card {
      background: var(--card-bg);
      padding: 25px;
      border-radius: 15px;
      margin-bottom: 20px;
      cursor: pointer;
      transition: all 0.3s ease;
      border: 1px solid var(--border-color);
      box-shadow: 0 4px 15px var(--shadow-color);
      position: relative;
      overflow: hidden;
    }

    .request-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(135deg, #00B04F 0%, #008037 100%);
    }

    .request-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 30px rgba(0,176,79,0.15);
      border-color: rgba(0,176,79,0.3);
    }

    .request-title {
      font-weight: 700;
      margin-bottom: 15px;
      font-size: 18px;
      color: var(--green-dark);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .request-detail {
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 15px;
      color: var(--text-secondary);
      font-weight: 500;
    }

    .request-detail i {
      font-style: normal;
      font-size: 18px;
      width: 25px;
      text-align: center;
    }

    .status-approved { 
      color: #28a745; 
      background: rgba(40,167,69,0.1);
      padding: 6px 12px;
      border-radius: 20px;
      font-weight: 600;
    }
    
    .status-pending { 
      color: #fd7e14; 
      background: rgba(253,126,20,0.1);
      padding: 6px 12px;
      border-radius: 20px;
      font-weight: 600;
    }
    
    .status-rejected { 
      color: #dc3545; 
      background: rgba(220,53,69,0.1);
      padding: 6px 12px;
      border-radius: 20px;
      font-weight: 600;
    }
    
    .status-more_info_requested { 
      color: #856404; 
      background: rgba(133,100,4,0.1);
      padding: 6px 12px;
      border-radius: 20px;
      font-weight: 600;
    }

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
    
    /* Subtle delete button styling - for emergency use only */
    .delete-btn {
      background: none;
      border: none;
      color: #6c757d;
      font-size: 12px;
      cursor: pointer;
      text-decoration: underline;
      padding: 2px 4px;
      transition: color 0.3s ease;
    }
    
    .delete-btn:hover {
      color: #dc3545;
    }
    
    .action-buttons {
      display: flex;
      gap: 10px;
      align-items: center;
      flex-wrap: wrap;
    }

    /* Dark Mode Toggle */
    .dark-mode-toggle {
      position: fixed;
      top: 50%;
      right: 20px;
      transform: translateY(-50%);
      z-index: 1000;
      background: var(--card-bg);
      border: 2px solid var(--green-primary);
      border-radius: 50px;
      padding: 12px;
      cursor: pointer;
      box-shadow: 0 4px 20px var(--shadow-color);
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 60px;
      height: 60px;
    }

    .dark-mode-toggle:hover {
      transform: translateY(-50%) scale(1.1);
      box-shadow: 0 6px 30px var(--shadow-color);
    }

    .dark-mode-toggle .toggle-icon {
      font-size: 24px;
      transition: all 0.3s ease;
    }

    .dark-mode-toggle .sun-icon {
      display: block;
    }

    .dark-mode-toggle .moon-icon {
      display: none;
    }

    [data-theme="dark"] .dark-mode-toggle .sun-icon {
      display: none;
    }

    [data-theme="dark"] .dark-mode-toggle .moon-icon {
      display: block;
    }

    /* Dark mode modal adjustments */
    [data-theme="dark"] .modal {
      background-color: rgba(0,0,0,0.8);
    }

    [data-theme="dark"] .modal-content {
      background-color: var(--card-bg);
      color: var(--text-primary);
    }
  </style>
</head>
<body>

<!-- Dark Mode Toggle -->
<div class="dark-mode-toggle" onclick="toggleDarkMode()">
  <span class="toggle-icon sun-icon">☀️</span>
  <span class="toggle-icon moon-icon">🌙</span>
</div>

<!-- Institutional Header -->
<div class="institution-header">
  <div class="header-content">
    <div class="logo-section">
      <img src="assets/dlsulogo.png" alt="DLSU Logo" class="institution-logo">
      <div class="institution-info">
        <h1>Budget Management System</h1>
        <p>Financial Planning & Resource Allocation</p>
      </div>
    </div>
    <div class="header-nav">
      <div class="user-info">
        <p class="username">👤 <?php echo htmlspecialchars($_SESSION['name'] ?? $_SESSION['username']); ?></p>
        <p class="role">Requester</p>
      </div>
      <a href="logout.php" class="logout-btn">🚪 Logout</a>
    </div>
  </div>
</div>

<div class="dashboard-container">
  <div class="header-bar">
    <h1>REQUESTER DASHBOARD</h1>
    <div>
      <a href="create_request.php" class="create-btn">
        <span>✨</span>
        <span>Create New Request</span>
      </a>
    </div>
  </div>

  <?php if ($success_message): ?>
  <div style="background: linear-gradient(135deg, #d4edda, #c3e6cb); border: 1px solid #28a745; color: #155724; padding: 20px; border-radius: 15px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(40,167,69,0.2); font-weight: 500;">
    ✅ <?php echo htmlspecialchars($success_message); ?>
  </div>
  <?php endif; ?>

  <?php if ($error_message): ?>
  <div style="background: linear-gradient(135deg, #f8d7da, #f5c6cb); border: 1px solid #dc3545; color: #721c24; padding: 20px; border-radius: 15px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(220,53,69,0.2); font-weight: 500;">
    ❌ <?php echo htmlspecialchars($error_message); ?>
  </div>
  <?php endif; ?>

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

    <label for="search">Search:</label>
    <input type="text" id="search" placeholder="Search requests..." onkeyup="searchRequests()" class="search-input">
  </form>

  <div class="content-box">
    <?php foreach ($requests as $req): ?>
      <div class="request-card">
        <div onclick="showModal('<?php echo $req['request_id']; ?>')" style="cursor: pointer;">
          <div class="request-title">BUDGET REQUEST: <?php echo $req['request_id']; ?></div>
          <div class="request-detail"><i>📅</i> Submitted: <?php echo date("F j, Y", strtotime($req['timestamp'])); ?></div>
          <div class="request-detail"><i>📖</i> Academic Year: <?php echo $req['academic_year']; ?></div>
          <div class="request-detail status-<?php echo strtolower(str_replace('_', '_', $req['status'])); ?>" style="display: flex; justify-content: space-between; align-items: center;">
            <span>
              <i>⏳</i> Status: <?php 
                echo $req['status'] === 'more_info_requested' ? 'More Information Requested' : $req['status']; 
              ?>
            </span>
            <?php if ($req['status'] === 'pending' && !$req['level1_processed']): ?>
            <button onclick="confirmDelete('<?php echo $req['request_id']; ?>')" 
                    class="delete-btn">
              delete
            </button>
            <?php endif; ?>
          </div>
          
          <?php if ($req['status'] === 'approved' && intval($req['amendment_count']) > 0): ?>
          <div style="margin-top: 12px; padding: 10px; background: linear-gradient(135deg, #e8f5e8 0%, #d4edda 100%); border: 1px solid #28a745; border-radius: 6px;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
              <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 16px;">✏️</span>
                <span style="color: #155724; font-weight: 600; font-size: 14px;">
                  <?php echo intval($req['amendment_count']); ?> Amendment<?php echo intval($req['amendment_count']) !== 1 ? 's' : ''; ?> Applied
                </span>
              </div>
              <div style="background: #28a745; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 500;">
                VP FINANCE
              </div>
            </div>
            <div style="margin-top: 6px; font-size: 12px; color: #155724;">
              Your approved request has been modified. Click to view changes.
            </div>
          </div>
          <?php elseif ($req['status'] === 'approved'): ?>
          <?php endif; ?>
        </div>
        
        <?php if ($req['status'] === 'more_info_requested'): ?>
        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #dee2e6;">
          <div style="display: flex; gap: 10px; align-items: center;">
            <span style="color: #856404; font-weight: bold; font-size: 14px;">⚠️ Action Required:</span>
            <a href="edit_request.php?request_id=<?php echo urlencode($req['request_id']); ?>" 
               style="display: inline-block; background-color: #015c2e; color: white; padding: 8px 16px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 14px;"
               onclick="event.stopPropagation();">
              ✏️ Edit Request
            </a>
          </div>
        </div>
        <?php endif; ?>
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

  // Delete request confirmation
  function confirmDelete(requestId) {
    if (confirm(`Are you sure you want to delete budget request ${requestId}?\n\nThis action cannot be undone. All associated data including budget entries and attachments will be permanently deleted.`)) {
      // Create a form and submit it
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = 'delete_request.php';
      
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'request_id';
      input.value = requestId;
      
      form.appendChild(input);
      document.body.appendChild(form);
      form.submit();
    }
  }

  // Dark Mode Toggle Functionality
  function toggleDarkMode() {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
  }

  // Initialize theme on page load
  document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
  });

  // Search functionality
  function searchRequests() {
    const searchTerm = document.getElementById('search').value.toLowerCase();
    const requestCards = document.querySelectorAll('.request-card');
    let visibleCount = 0;
    
    requestCards.forEach(card => {
      const requestTitle = card.querySelector('.request-title').textContent.toLowerCase();
      const requestDetails = card.querySelectorAll('.request-detail');
      let detailsText = '';
      
      // Get all text content from request details
      requestDetails.forEach(detail => {
        detailsText += detail.textContent.toLowerCase() + ' ';
      });
      
      // Check if search term matches title or any detail
      const isMatch = requestTitle.includes(searchTerm) || 
                     detailsText.includes(searchTerm) ||
                     searchTerm === '';
      
      if (isMatch) {
        card.style.display = 'block';
        visibleCount++;
      } else {
        card.style.display = 'none';
      }
    });
    
    // Show/hide "no results" message
    let noResultsMsg = document.getElementById('no-results-message');
    if (visibleCount === 0 && searchTerm !== '') {
      if (!noResultsMsg) {
        noResultsMsg = document.createElement('div');
        noResultsMsg.id = 'no-results-message';
        noResultsMsg.style.cssText = `
          text-align: center;
          padding: 40px 20px;
          color: var(--text-muted);
          font-size: 18px;
          font-weight: 500;
        `;
        noResultsMsg.innerHTML = `
          <div style="font-size: 48px; margin-bottom: 20px;">🔍</div>
          <p>No requests found for "<strong>${searchTerm}</strong>"</p>
          <p style="font-size: 14px; margin-top: 10px;">Try searching for request ID, academic year, or status</p>
        `;
        document.querySelector('.content-box').appendChild(noResultsMsg);
      } else {
        noResultsMsg.innerHTML = `
          <div style="font-size: 48px; margin-bottom: 20px;">🔍</div>
          <p>No requests found for "<strong>${searchTerm}</strong>"</p>
          <p style="font-size: 14px; margin-top: 10px;">Try searching for request ID, academic year, or status</p>
        `;
        noResultsMsg.style.display = 'block';
      }
    } else if (noResultsMsg) {
      noResultsMsg.style.display = 'none';
    }
  }
</script>

</body>
</html>
