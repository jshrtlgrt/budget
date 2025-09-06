<?php
session_start();

// Allow multiple approver roles
$allowed_roles = ['approver', 'department_head', 'dean', 'vp_finance'];
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header("Location: login.php");
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=budget_database_schema", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

require_once 'workflow_manager.php';
$workflow = new WorkflowManager($pdo);

$username = $_SESSION['username'];
$stmt = $pdo->prepare("SELECT id FROM account WHERE username_email = ?");
$stmt->execute([$username]);
$approver_id = $stmt->fetchColumn();

// Handle filter parameters
$status_filter = $_GET['status'] ?? 'all';
$search_query = $_GET['search'] ?? '';
$sort_by = $_GET['sort'] ?? 'latest';
// VP Finance defaults to 'all' view, others default to 'pending'
$view_mode = $_GET['view'] ?? ($_SESSION['role'] === 'vp_finance' ? 'all' : 'pending');

if ($view_mode === 'pending') {
    // Show only requests that this approver can act on
    $requests = $workflow->getRequestsForApprover($approver_id);
    
    // Apply additional filters to pending requests
    if ($status_filter !== 'all' || !empty($search_query)) {
        $requests = array_filter($requests, function($req) use ($status_filter, $search_query) {
            $status_match = ($status_filter === 'all') || (strtolower($req['status']) === $status_filter);
            $search_match = empty($search_query) || 
                           stripos($req['request_id'], $search_query) !== false ||
                           stripos($req['requester_name'], $search_query) !== false ||
                           stripos($req['college'], $search_query) !== false;
            return $status_match && $search_match;
        });
    }
} else {
    // Show all requests (for reference/history)
    if ($_SESSION['role'] === 'vp_finance') {
        // VP Finance can see ALL requests regardless of approval workflow
        $sql = "SELECT br.*, a.name as requester_name, a.username_email as requester_email, 
                       d.college, d.budget_deck, 'vp_finance' as user_role,
                       COALESCE(ba_count.amendment_count, 0) as amendment_count
                FROM budget_request br 
                LEFT JOIN account a ON br.account_id = a.id 
                LEFT JOIN department d ON br.department_code = d.code 
                LEFT JOIN (
                    SELECT request_id, COUNT(*) as amendment_count 
                    FROM budget_amendments 
                    GROUP BY request_id
                ) ba_count ON br.request_id = ba_count.request_id
                WHERE 1=1";
        $params = [];
    } else {
        // Other roles see requests based on approval workflow
        $sql = "SELECT br.*, a.name as requester_name, a.username_email as requester_email, 
                       d.college, d.budget_deck,
                       CASE WHEN ap.approver_id = ? THEN 'can_approve' ELSE 'view_only' END as user_role,
                       COALESCE(ba_count.amendment_count, 0) as amendment_count
                FROM budget_request br 
                LEFT JOIN account a ON br.account_id = a.id 
                LEFT JOIN department d ON br.department_code = d.code 
                LEFT JOIN approval_progress ap ON br.request_id = ap.request_id 
                    AND ap.approval_level = br.current_approval_level 
                    AND ap.approver_id = ?
                LEFT JOIN (
                    SELECT request_id, COUNT(*) as amendment_count 
                    FROM budget_amendments 
                    GROUP BY request_id
                ) ba_count ON br.request_id = ba_count.request_id
                WHERE 1=1";
        $params = [$approver_id, $approver_id];
    }

    if ($status_filter !== 'all') {
        $sql .= " AND br.status = ?";
        $params[] = $status_filter;
    }

    if (!empty($search_query)) {
        $sql .= " AND (br.request_id LIKE ? OR a.name LIKE ? OR d.college LIKE ?)";
        $search_param = '%' . $search_query . '%';
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }

    // Add sorting
    switch ($sort_by) {
        case 'oldest':
            $sql .= " ORDER BY br.timestamp ASC";
            break;
        case 'amount_high':
            $sql .= " ORDER BY br.proposed_budget DESC";
            break;
        case 'amount_low':
            $sql .= " ORDER BY br.proposed_budget ASC";
            break;
        default:
            $sql .= " ORDER BY br.timestamp DESC";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Approver Dashboard - Budget Management System</title>
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

    .user-info {
      color: var(--text-muted);
      font-size: 16px;
      font-weight: 500;
      background: linear-gradient(135deg, var(--bg-primary), var(--bg-tertiary));
      padding: 10px 20px;
      border-radius: 25px;
      border: 1px solid var(--border-color);
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
      margin-right: 5px;
    }

    .filters select, .filters input {
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

    .filters select:hover, .filters input:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    }

    .filters select option {
      background-color: white;
      color: #333;
      padding: 8px;
    }

    .filters input[type="text"] {
      min-width: 200px;
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

    .request-card.pending::before {
      background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
    }

    .request-card.approved::before {
      background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    }

    .request-card.rejected::before {
      background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
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

    .amount {
      font-weight: 700;
      color: var(--green-dark);
      font-size: 16px;
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

    /* Amendment Modal Styles */
    #amendmentFormModal {
      font-family: 'Montserrat', sans-serif;
    }

    #amendmentFormModal input,
    #amendmentFormModal select,
    #amendmentFormModal textarea {
      font-family: 'Montserrat', sans-serif;
    }

    #amendmentFormModal input:focus,
    #amendmentFormModal select:focus,
    #amendmentFormModal textarea:focus {
      outline: none;
      border-color: #015c2e;
      box-shadow: 0 0 0 3px rgba(1, 92, 46, 0.1);
    }

    /* Dark mode support for amendment modal */
    [data-theme="dark"] #amendmentFormModal .modal-content,
    [data-theme="dark"] #amendmentFormModal > div > div {
      background-color: var(--card-bg) !important;
      color: var(--text-primary) !important;
    }

    [data-theme="dark"] #amendmentFormModal input,
    [data-theme="dark"] #amendmentFormModal select,
    [data-theme="dark"] #amendmentFormModal textarea {
      background-color: var(--bg-secondary) !important;
      color: var(--text-primary) !important;
      border-color: var(--border-color) !important;
    }

    [data-theme="dark"] #amendmentFormModal label {
      color: var(--green-primary) !important;
    }

    /* Modal Styling */
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0; top: 0;
      width: 100%; height: 100%;
      background-color: rgba(0,0,0,0.6);
    }

    .modal-content {
      background-color: #fff;
      margin: 5% auto;
      padding: 30px;
      width: 90%;
      max-width: 800px;
      max-height: 80%;
      overflow-y: auto;
      border-radius: 10px;
      position: relative;
    }

    .close-btn {
      position: absolute;
      top: 15px; right: 25px;
      font-size: 24px;
      font-weight: bold;
      cursor: pointer;
      color: #666;
    }

    .close-btn:hover {
      color: #000;
    }

    .modal-header {
      border-bottom: 2px solid #015c2e;
      padding-bottom: 15px;
      margin-bottom: 20px;
    }

    .modal-section {
      margin-bottom: 20px;
    }

    .modal-section h3 {
      color: #015c2e;
      margin-bottom: 10px;
    }

    .info-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 15px;
      margin-bottom: 20px;
    }

    .info-item {
      background: #f8f9fa;
      padding: 12px;
      border-radius: 5px;
      border-left: 3px solid #015c2e;
    }

    .info-item strong {
      display: block;
      color: #333;
      margin-bottom: 5px;
    }

    .action-buttons {
      display: flex;
      gap: 10px;
      justify-content: center;
      margin-top: 20px;
      padding-top: 20px;
      border-top: 1px solid #eee;
    }

    .btn {
      padding: 10px 20px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-weight: bold;
      font-size: 14px;
    }

    .btn-approve {
      background-color: #28a745;
      color: white;
    }

    .btn-reject {
      background-color: #dc3545;
      color: white;
    }

    .btn-request-info {
      background-color: #ffc107;
      color: #212529;
    }

    .btn:hover {
      opacity: 0.9;
    }

    .history-section {
      background: #f8f9fa;
      padding: 15px;
      border-radius: 5px;
      margin-top: 15px;
    }

    .history-item {
      padding: 8px 0;
      border-bottom: 1px solid #ddd;
      font-size: 14px;
    }

    .history-item:last-child {
      border-bottom: none;
    }

    .no-requests {
      text-align: center;
      color: #666;
      font-style: italic;
      padding: 40px;
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

    /* Tab Styles for VP Finance */
    .tab-container {
      margin-bottom: 20px;
    }
    
    .tab-buttons {
      display: flex;
      background-color: #f8f9fa;
      border-radius: 5px 5px 0 0;
      border: 1px solid #dee2e6;
      border-bottom: none;
    }
    
    .tab-button {
      padding: 12px 24px;
      background: none;
      border: none;
      cursor: pointer;
      font-weight: 600;
      color: #6c757d;
      border-right: 1px solid #dee2e6;
      transition: all 0.3s ease;
    }
    
    .tab-button:last-child {
      border-right: none;
    }
    
    .tab-button.active {
      background-color: #015c2e;
      color: white;
    }
    
    .tab-button:hover:not(.active) {
      background-color: #e9ecef;
      color: #495057;
    }
    
    .tab-content {
      display: none;
      border: 1px solid #dee2e6;
      border-radius: 0 0 5px 5px;
      background: white;
    }
    
    .tab-content.active {
      display: block;
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
        <p class="role"><?php echo ucwords(str_replace('_', ' ', $_SESSION['role'])); ?></p>
      </div>
      <a href="logout.php" class="logout-btn">🚪 Logout</a>
    </div>
  </div>
</div>

<div class="dashboard-container">
  <div class="header-bar">
    <h1>APPROVER DASHBOARD</h1>
  </div>

  <?php if ($_SESSION['role'] === 'vp_finance'): ?>
  <!-- Tab Navigation for VP Finance -->
  <div class="tab-container">
    <div class="tab-buttons">
      <button class="tab-button active" onclick="switchTab('requests')">📋 Budget Requests</button>
      <button class="tab-button" onclick="switchTab('analytics')">📊 Analytics & Reports</button>
    </div>
  </div>
  <?php endif; ?>

  <!-- Tab Content: Budget Requests -->
  <div id="requests-tab" class="tab-content active">
    <form method="GET" class="filters">
      <label for="view">View:</label>
      <select id="view" name="view">
        <option value="pending" <?php echo $view_mode === 'pending' ? 'selected' : ''; ?>>My Pending Approvals</option>
        <option value="all" <?php echo $view_mode === 'all' ? 'selected' : ''; ?>>All Requests</option>
      </select>

    <label for="status">Status:</label>
    <select id="status" name="status">
      <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
      <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
      <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
      <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
      <option value="more_info_requested" <?php echo $status_filter === 'more_info_requested' ? 'selected' : ''; ?>>More Info Requested</option>
    </select>

    <label for="sort">Sort By:</label>
    <select id="sort" name="sort">
      <option value="latest" <?php echo $sort_by === 'latest' ? 'selected' : ''; ?>>Latest First</option>
      <option value="oldest" <?php echo $sort_by === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
      <option value="amount_high" <?php echo $sort_by === 'amount_high' ? 'selected' : ''; ?>>Amount (High-Low)</option>
      <option value="amount_low" <?php echo $sort_by === 'amount_low' ? 'selected' : ''; ?>>Amount (Low-High)</option>
    </select>

    <label for="search">Search:</label>
    <input type="text" id="search" name="search" placeholder="Request ID, Name, College..." 
           value="<?php echo htmlspecialchars($search_query); ?>">

    <button type="submit" class="btn" style="background: white; color: #015c2e; padding: 6px 12px;">Filter</button>
  </form>

  <div class="content-box">
    <?php if (empty($requests)): ?>
      <div class="no-requests">No budget requests found matching your criteria.</div>
    <?php else: ?>
      <?php foreach ($requests as $req): ?>
        <div class="request-card <?php echo strtolower($req['status']); ?>" 
             onclick="showModal('<?php echo $req['request_id']; ?>')">
          <div class="request-title">BUDGET REQUEST: <?php echo htmlspecialchars($req['request_id']); ?></div>
          <div class="request-detail">
            <i>👤</i> Requester: <?php echo htmlspecialchars($req['requester_name'] ?? 'Unknown'); ?>
          </div>
          <div class="request-detail">
            <i>🏢</i> College: <?php echo htmlspecialchars($req['college'] ?? 'N/A'); ?>
          </div>
          <div class="request-detail">
            <i>📅</i> Submitted: <?php echo date("F j, Y", strtotime($req['timestamp'])); ?>
          </div>
          <div class="request-detail">
            <i>📖</i> Academic Year: <?php echo htmlspecialchars($req['academic_year']); ?>
          </div>
          <div class="request-detail">
            <i>💰</i> Amount: <span class="amount">₱<?php echo number_format($req['proposed_budget'], 2); ?></span>
          </div>
          <div class="request-detail status-<?php echo strtolower($req['status']); ?>">
            <i>⏳</i> Status: <?php echo htmlspecialchars($req['status']); ?>
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
                BY YOU
              </div>
            </div>
          </div>
          <?php elseif ($req['status'] === 'approved'): ?>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
    </div>
  </div>

  <!-- Tab Content: Analytics & Reports (VP Finance Only) -->
  <?php if ($_SESSION['role'] === 'vp_finance'): ?>
  <div id="analytics-tab" class="tab-content">
    <?php
    // Enhanced analytics with comprehensive data
    try {
        // Overall statistics
        $stats_query = "
            SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'more_info_requested' THEN 1 ELSE 0 END) as info_requested_count,
                SUM(proposed_budget) as total_budget,
                SUM(CASE WHEN status = 'approved' THEN proposed_budget ELSE 0 END) as approved_budget,
                SUM(CASE WHEN status = 'approved' THEN COALESCE(approved_budget, proposed_budget) ELSE 0 END) as total_approved_amount,
                AVG(proposed_budget) as avg_budget,
                AVG(CASE WHEN status = 'approved' THEN COALESCE(approved_budget, proposed_budget) END) as avg_approved_budget
            FROM budget_request
        ";

        $stmt = $pdo->prepare($stats_query);
        $stmt->execute();
        $overall_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Department analysis - identify departments needing more budget
        $dept_analysis_query = "
            SELECT 
                br.department_code,
                d.college,
                COUNT(*) as total_requests,
                SUM(CASE WHEN br.status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN br.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN br.status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(br.proposed_budget) as requested_budget,
                SUM(CASE WHEN br.status = 'approved' THEN COALESCE(br.approved_budget, br.proposed_budget) ELSE 0 END) as approved_budget,
                ROUND(AVG(br.proposed_budget), 2) as avg_request,
                ROUND((SUM(CASE WHEN br.status = 'approved' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as approval_rate,
                ROUND((SUM(CASE WHEN br.status = 'rejected' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as rejection_rate
            FROM budget_request br
            LEFT JOIN department d ON br.department_code = d.code
            GROUP BY br.department_code, d.college
            HAVING COUNT(*) > 0
            ORDER BY requested_budget DESC
        ";

        $stmt = $pdo->prepare($dept_analysis_query);
        $stmt->execute();
        $dept_analysis = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Monthly trends for budget allocation
        $monthly_trends_query = "
            SELECT 
                DATE_FORMAT(timestamp, '%Y-%m') as month,
                DATE_FORMAT(timestamp, '%b %Y') as month_label,
                COUNT(*) as requests,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(proposed_budget) as total_requested,
                SUM(CASE WHEN status = 'approved' THEN COALESCE(approved_budget, proposed_budget) ELSE 0 END) as total_approved,
                ROUND(AVG(proposed_budget), 2) as avg_request_size
            FROM budget_request 
            WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(timestamp, '%Y-%m')
            ORDER BY month ASC
        ";

        $stmt = $pdo->prepare($monthly_trends_query);
        $stmt->execute();
        $monthly_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Budget category analysis
        $category_query = "
            SELECT 
                CASE 
                    WHEN proposed_budget < 50000 THEN 'Small (< ₱50K)'
                    WHEN proposed_budget < 200000 THEN 'Medium (₱50K - ₱200K)'
                    WHEN proposed_budget < 500000 THEN 'Large (₱200K - ₱500K)'
                    ELSE 'Very Large (> ₱500K)'
                END as budget_category,
                COUNT(*) as count,
                SUM(proposed_budget) as total_budget,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                ROUND(AVG(proposed_budget), 2) as avg_budget
            FROM budget_request
            GROUP BY budget_category
            ORDER BY 
                CASE 
                    WHEN proposed_budget < 50000 THEN 1
                    WHEN proposed_budget < 200000 THEN 2
                    WHEN proposed_budget < 500000 THEN 3
                    ELSE 4
                END
        ";

        $stmt = $pdo->prepare($category_query);
        $stmt->execute();
        $budget_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Recent activity query for dashboard
        $recent_activity_query = "
            SELECT 
                br.request_id,
                br.status,
                br.proposed_budget,
                br.timestamp,
                a.name as requester_name,
                d.college
            FROM budget_request br
            LEFT JOIN account a ON br.account_id = a.id
            LEFT JOIN department d ON br.department_code = d.code
            ORDER BY br.timestamp DESC
            LIMIT 10
        ";
        
        $stmt = $pdo->prepare($recent_activity_query);
        $stmt->execute();
        $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Set defaults if no data
        if (!$overall_stats) {
            $overall_stats = [
                'total_requests' => 0, 'approved_count' => 0, 'rejected_count' => 0,
                'pending_count' => 0, 'info_requested_count' => 0, 'total_budget' => 0,
                'approved_budget' => 0, 'total_approved_amount' => 0, 'avg_budget' => 0,
                'avg_approved_budget' => 0
            ];
        }

        // Prepare comprehensive chart data for JavaScript
        $chart_data = [
            'status_distribution' => [
                'approved' => intval($overall_stats['approved_count']),
                'rejected' => intval($overall_stats['rejected_count']),
                'pending' => intval($overall_stats['pending_count']),
                'info_requested' => intval($overall_stats['info_requested_count'])
            ],
            'monthly_data' => array_map(function($month) {
                return [
                    'month_name' => $month['month_label'],
                    'approved' => intval($month['approved']),
                    'rejected' => intval($month['requests'] - $month['approved']),
                    'total_requested' => floatval($month['total_requested']),
                    'total_approved' => floatval($month['total_approved'])
                ];
            }, $monthly_trends),
            'department_data' => array_map(function($dept) {
                return [
                    'department_code' => $dept['department_code'],
                    'college' => $dept['college'],
                    'total_requests' => intval($dept['total_requests']),
                    'approved' => intval($dept['approved']),
                    'total_budget' => floatval($dept['requested_budget']),
                    'approved_budget' => floatval($dept['approved_budget'])
                ];
            }, $dept_analysis),
            'budget_categories' => array_map(function($category) {
                return [
                    'category' => $category['budget_category'],
                    'count' => intval($category['count']),
                    'total_budget' => floatval($category['total_budget']),
                    'approved' => intval($category['approved'])
                ];
            }, $budget_categories)
        ];
    } catch (Exception $e) {
        echo "<div style='padding: 20px; text-align: center; color: #dc3545;'>";
        echo "<h3>Error Loading Analytics</h3>";
        echo "<p>" . $e->getMessage() . "</p>";
        echo "</div>";
    }
    ?>
    
    <div class="analytics-container" style="padding: 20px;">
        <!-- Statistics Overview -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div style="background: linear-gradient(135deg, #015c2e 0%, #28a745 100%); color: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(1, 92, 46, 0.2); text-align: center;">
                <h3 style="font-size: 2.5em; margin: 0 0 10px 0; font-weight: bold;"><?php echo number_format($overall_stats['total_requests']); ?></h3>
                <p style="margin: 0; font-size: 1.1em; opacity: 0.9;">Total Requests</p>
            </div>
            <div style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.2); text-align: center;">
                <h3 style="font-size: 2.5em; margin: 0 0 10px 0; font-weight: bold;"><?php echo number_format($overall_stats['approved_count']); ?></h3>
                <p style="margin: 0; font-size: 1.1em; opacity: 0.9;">Approved</p>
            </div>
            <div style="background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%); color: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(220, 53, 69, 0.2); text-align: center;">
                <h3 style="font-size: 2.5em; margin: 0 0 10px 0; font-weight: bold;"><?php echo number_format($overall_stats['rejected_count']); ?></h3>
                <p style="margin: 0; font-size: 1.1em; opacity: 0.9;">Rejected</p>
            </div>
            <div style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); color: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(255, 193, 7, 0.2); text-align: center;">
                <h3 style="font-size: 2.5em; margin: 0 0 10px 0; font-weight: bold;"><?php echo number_format($overall_stats['pending_count']); ?></h3>
                <p style="margin: 0; font-size: 1.1em; opacity: 0.9;">Pending</p>
            </div>
            <div style="background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%); color: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(111, 66, 193, 0.2); text-align: center;">
                <h3 style="font-size: 2.5em; margin: 0 0 10px 0; font-weight: bold;">₱<?php echo number_format($overall_stats['approved_budget'], 0); ?></h3>
                <p style="margin: 0; font-size: 1.1em; opacity: 0.9;">Approved Budget</p>
            </div>
            <div style="background: linear-gradient(135deg, #015c2e 0%, #28a745 100%); color: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(1, 92, 46, 0.2); text-align: center;">
                <h3 style="font-size: 2.5em; margin: 0 0 10px 0; font-weight: bold;">₱<?php echo number_format($overall_stats['avg_budget'], 0); ?></h3>
                <p style="margin: 0; font-size: 1.1em; opacity: 0.9;">Average Request</p>
            </div>
        </div>

        <!-- Charts Section -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
            <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);">
                <h3 style="color: #015c2e; margin-bottom: 20px; font-size: 1.4em; border-bottom: 2px solid #015c2e; padding-bottom: 10px;">📊 Status Distribution</h3>
                <canvas id="statusPieChart" width="400" height="300"></canvas>
            </div>
            <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);">
                <h3 style="color: #015c2e; margin-bottom: 20px; font-size: 1.4em; border-bottom: 2px solid #015c2e; padding-bottom: 10px;">📈 Monthly Requests</h3>
                <canvas id="monthlyBarChart" width="400" height="300"></canvas>
            </div>
        </div>

        <!-- Department Analysis Section -->
        <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); margin-bottom: 30px;">
            <h3 style="color: #015c2e; margin-bottom: 20px; font-size: 1.4em; border-bottom: 2px solid #015c2e; padding-bottom: 10px;">🏢 Department Performance Analysis</h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                <!-- Department Statistics Table -->
                <div>
                    <h4 style="color: #015c2e; margin-bottom: 15px;">📋 Department Statistics</h4>
                    <div style="overflow-x: auto; max-height: 400px; overflow-y: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead style="background: #f8f9fa; position: sticky; top: 0;">
                                <tr>
                                    <th style="padding: 12px; text-align: left; border: 1px solid #dee2e6; font-weight: bold; color: #015c2e;">Department</th>
                                    <th style="padding: 12px; text-align: center; border: 1px solid #dee2e6; font-weight: bold; color: #015c2e;">College</th>
                                    <th style="padding: 12px; text-align: center; border: 1px solid #dee2e6; font-weight: bold; color: #015c2e;">Total Requests</th>
                                    <th style="padding: 12px; text-align: center; border: 1px solid #dee2e6; font-weight: bold; color: #015c2e;">Approved</th>
                                    <th style="padding: 12px; text-align: center; border: 1px solid #dee2e6; font-weight: bold; color: #015c2e;">Success Rate</th>
                                    <th style="padding: 12px; text-align: right; border: 1px solid #dee2e6; font-weight: bold; color: #015c2e;">Total Budget</th>
                                    <th style="padding: 12px; text-align: right; border: 1px solid #dee2e6; font-weight: bold; color: #015c2e;">Approved Budget</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dept_analysis as $dept): 
                                    $success_rate = $dept['total_requests'] > 0 ? ($dept['approved'] / $dept['total_requests']) * 100 : 0;
                                    $rate_color = $success_rate >= 70 ? '#28a745' : ($success_rate >= 40 ? '#ffc107' : '#dc3545');
                                ?>
                                <tr style="border-bottom: 1px solid #dee2e6;">
                                    <td style="padding: 10px; border: 1px solid #dee2e6; font-weight: bold;"><?php echo htmlspecialchars($dept['department_code']); ?></td>
                                    <td style="padding: 10px; border: 1px solid #dee2e6; text-align: center;"><?php echo htmlspecialchars($dept['college'] ?: 'N/A'); ?></td>
                                    <td style="padding: 10px; border: 1px solid #dee2e6; text-align: center;"><?php echo number_format($dept['total_requests']); ?></td>
                                    <td style="padding: 10px; border: 1px solid #dee2e6; text-align: center; color: #28a745; font-weight: bold;"><?php echo number_format($dept['approved']); ?></td>
                                    <td style="padding: 10px; border: 1px solid #dee2e6; text-align: center; color: <?php echo $rate_color; ?>; font-weight: bold;">
                                        <?php echo number_format($success_rate, 1); ?>%
                                    </td>
                                    <td style="padding: 10px; border: 1px solid #dee2e6; text-align: right;">₱<?php echo number_format($dept['requested_budget'], 2); ?></td>
                                    <td style="padding: 10px; border: 1px solid #dee2e6; text-align: right; color: #28a745; font-weight: bold;">₱<?php echo number_format($dept['approved_budget'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Department Performance Chart -->
                <div>
                    <h4 style="color: #015c2e; margin-bottom: 15px;">📊 Department Success Rates</h4>
                    <canvas id="departmentChart" width="400" height="300"></canvas>
                    
                    <!-- Performance Indicators -->
                    <div style="margin-top: 20px;">
                        <h5 style="color: #015c2e; margin-bottom: 10px;">🚨 Departments Needing Attention:</h5>
                        <div style="max-height: 150px; overflow-y: auto;">
                            <?php 
                            $low_performing = array_filter($dept_analysis, function($dept) {
                                $success_rate = $dept['total_requests'] > 0 ? ($dept['approved'] / $dept['total_requests']) * 100 : 0;
                                return $success_rate < 50 && $dept['total_requests'] > 0;
                            });
                            
                            if (empty($low_performing)): ?>
                                <div style="padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; color: #155724;">
                                    ✅ All departments are performing well (≥50% success rate)
                                </div>
                            <?php else: ?>
                                <?php foreach ($low_performing as $dept): 
                                    $success_rate = ($dept['approved'] / $dept['total_requests']) * 100;
                                ?>
                                <div style="padding: 8px; margin-bottom: 5px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; color: #721c24;">
                                    <strong><?php echo htmlspecialchars($dept['department_code']); ?></strong> 
                                    (<?php echo htmlspecialchars($dept['college']); ?>) - 
                                    <?php echo number_format($success_rate, 1); ?>% success rate
                                    <small style="display: block; margin-top: 2px;">
                                        <?php echo $dept['approved']; ?>/<?php echo $dept['total_requests']; ?> requests approved
                                    </small>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reports Section -->
        <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); margin-bottom: 30px;">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
                <div>
                    <h3 style="color: #015c2e; margin-bottom: 20px; font-size: 1.4em; border-bottom: 2px solid #015c2e; padding-bottom: 10px;">📅 Monthly Reports</h3>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6; background-color: #f8f9fa; font-weight: bold; color: #015c2e;">Month</th>
                                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6; background-color: #f8f9fa; font-weight: bold; color: #015c2e;">Requests</th>
                                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6; background-color: #f8f9fa; font-weight: bold; color: #015c2e;">Approved</th>
                                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6; background-color: #f8f9fa; font-weight: bold; color: #015c2e;">Rejected</th>
                                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6; background-color: #f8f9fa; font-weight: bold; color: #015c2e;">Total Amount</th>
                                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6; background-color: #f8f9fa; font-weight: bold; color: #015c2e;">Approved Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($monthly_trends as $month): ?>
                                <tr style="transition: background-color 0.2s;">
                                    <td style="padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6;"><?php echo htmlspecialchars($month['month_label']); ?></td>
                                    <td style="padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6;"><?php echo number_format($month['requests']); ?></td>
                                    <td style="padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6; color: #28a745; font-weight: bold;"><?php echo number_format($month['approved']); ?></td>
                                    <td style="padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6; color: #dc3545; font-weight: bold;"><?php echo number_format($month['requests'] - $month['approved']); ?></td>
                                    <td style="padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6;">₱<?php echo number_format($month['total_requested'], 2); ?></td>
                                    <td style="padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6; color: #28a745; font-weight: bold;">₱<?php echo number_format($month['total_approved'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div>
                    <h3 style="color: #015c2e; margin-bottom: 20px; font-size: 1.4em; border-bottom: 2px solid #015c2e; padding-bottom: 10px;">🕒 Recent Activity</h3>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <?php foreach ($recent_activity as $activity): ?>
                        <div style="padding: 15px; border-left: 4px solid <?php 
                            echo $activity['status'] === 'approved' ? '#28a745' : 
                                ($activity['status'] === 'rejected' ? '#dc3545' : '#ffc107'); 
                        ?>; margin-bottom: 15px; background: #f8f9fa; border-radius: 0 8px 8px 0;">
                            <strong><?php echo htmlspecialchars($activity['request_id']); ?></strong>
                            <span style="float: right; color: <?php 
                                echo $activity['status'] === 'approved' ? '#28a745' : 
                                    ($activity['status'] === 'rejected' ? '#dc3545' : '#ffc107'); 
                            ?>; font-weight: bold;">
                                <?php echo strtoupper($activity['status']); ?>
                            </span>
                            <br>
                            <span><?php echo htmlspecialchars($activity['requester_name']); ?> - <?php echo htmlspecialchars($activity['college']); ?></span>
                            <br>
                            <strong>₱<?php echo number_format($activity['proposed_budget'], 2); ?></strong>
                            <div style="font-size: 0.9em; color: #6c757d; margin-top: 5px;">
                                <?php echo date('M j, Y g:i A', strtotime($activity['timestamp'])); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Export and Action Section -->
        <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); margin-bottom: 30px; text-align: center;">
            <h3 style="color: #015c2e; margin-bottom: 20px; font-size: 1.4em;">📊 Export Reports & Analytics</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <button onclick="exportToPDF()" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; border: none; padding: 15px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);">
                    📄 Export to PDF
                </button>
                <button onclick="exportToExcel()" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border: none; padding: 15px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);">
                    📊 Export to Excel
                </button>
                <button onclick="printReport()" style="background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%); color: white; border: none; padding: 15px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(111, 66, 193, 0.3);">
                    🖨️ Print Report
                </button>
                <button onclick="refreshAnalytics()" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); color: #212529; border: none; padding: 15px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);">
                    🔄 Refresh Data
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    // Chart data from PHP
    const chartData = <?php echo json_encode($chart_data); ?>;

    // Function to initialize charts when analytics tab is shown
    function initializeAnalyticsCharts() {
        // Status Distribution Pie Chart
        const statusCtx = document.getElementById('statusPieChart');
        if (statusCtx && !statusCtx.chartInstance) {
            statusCtx.chartInstance = new Chart(statusCtx.getContext('2d'), {
                type: 'pie',
                data: {
                    labels: ['Approved', 'Rejected', 'Pending', 'Info Requested'],
                    datasets: [{
                        data: [
                            chartData.status_distribution.approved,
                            chartData.status_distribution.rejected,
                            chartData.status_distribution.pending,
                            chartData.status_distribution.info_requested
                        ],
                        backgroundColor: [
                            '#28a745',
                            '#dc3545',
                            '#ffc107',
                            '#17a2b8'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Monthly Bar Chart
        const monthlyCtx = document.getElementById('monthlyBarChart');
        if (monthlyCtx && !monthlyCtx.chartInstance) {
            const monthlyLabels = chartData.monthly_data.map(item => item.month_name || 'No Data');
            const monthlyApproved = chartData.monthly_data.map(item => parseInt(item.approved) || 0);
            const monthlyRejected = chartData.monthly_data.map(item => parseInt(item.rejected) || 0);

            monthlyCtx.chartInstance = new Chart(monthlyCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: monthlyLabels,
                    datasets: [
                        {
                            label: 'Approved',
                            data: monthlyApproved,
                            backgroundColor: '#28a745',
                            borderColor: '#1e7e34',
                            borderWidth: 1
                        },
                        {
                            label: 'Rejected',
                            data: monthlyRejected,
                            backgroundColor: '#dc3545',
                            borderColor: '#bd2130',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    }
                }
            });
        }

        // Department Success Rate Chart
        const departmentCtx = document.getElementById('departmentChart');
        if (departmentCtx && !departmentCtx.chartInstance) {
            const departmentLabels = chartData.department_data.map(item => item.department_code || 'Unknown');
            const departmentSuccessRates = chartData.department_data.map(item => {
                const totalRequests = parseInt(item.total_requests) || 0;
                const approved = parseInt(item.approved) || 0;
                return totalRequests > 0 ? ((approved / totalRequests) * 100) : 0;
            });
            
            // Color departments based on success rate
            const departmentColors = departmentSuccessRates.map(rate => {
                if (rate >= 70) return '#28a745'; // Green for good performance
                if (rate >= 40) return '#ffc107'; // Yellow for moderate performance
                return '#dc3545'; // Red for poor performance
            });

            departmentCtx.chartInstance = new Chart(departmentCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: departmentLabels,
                    datasets: [{
                        label: 'Success Rate (%)',
                        data: departmentSuccessRates,
                        backgroundColor: departmentColors,
                        borderColor: departmentColors.map(color => color === '#ffc107' ? '#e0a800' : color),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        },
                        x: {
                            ticks: {
                                maxRotation: 45,
                                minRotation: 45
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const deptIndex = context.dataIndex;
                                    const deptData = chartData.department_data[deptIndex];
                                    return [
                                        `Success Rate: ${context.parsed.y.toFixed(1)}%`,
                                        `Approved: ${deptData.approved}/${deptData.total_requests}`,
                                        `Total Budget: ₱${parseFloat(deptData.total_budget).toLocaleString()}`,
                                        `Approved Budget: ₱${parseFloat(deptData.approved_budget).toLocaleString()}`
                                    ];
                                }
                            }
                        }
                    }
                }
            });
        }
    }

    // Initialize charts when page loads if analytics tab is active
    document.addEventListener('DOMContentLoaded', function() {
        if (document.getElementById('analytics-tab').classList.contains('active')) {
            initializeAnalyticsCharts();
        }
    });
    
    // Export and utility functions
    function exportToPDF() {
        alert('PDF Export functionality would be implemented here. This would generate a comprehensive report with all charts and analytics data.');
        // Implementation would use libraries like jsPDF to generate PDF reports
    }
    
    function exportToExcel() {
        // Create CSV data from analytics
        let csvContent = "data:text/csv;charset=utf-8,";
        
        // Add department analysis data
        csvContent += "Department Analysis\\n";
        csvContent += "Department,College,Total Requests,Approved,Success Rate,Total Budget,Approved Budget\\n";
        
        <?php foreach ($dept_analysis as $dept): ?>
        csvContent += "<?php echo addslashes($dept['department_code']); ?>,";
        csvContent += "<?php echo addslashes($dept['college'] ?: 'N/A'); ?>,";
        csvContent += "<?php echo $dept['total_requests']; ?>,";
        csvContent += "<?php echo $dept['approved']; ?>,";
        csvContent += "<?php echo number_format(($dept['total_requests'] > 0 ? ($dept['approved'] / $dept['total_requests']) * 100 : 0), 1); ?>%,";
        csvContent += "<?php echo number_format($dept['requested_budget'], 2); ?>,";
        csvContent += "<?php echo number_format($dept['approved_budget'], 2); ?>\\n";
        <?php endforeach; ?>
        
        // Download CSV
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "budget_analytics_" + new Date().toISOString().slice(0,10) + ".csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
    
    function printReport() {
        const analyticsContent = document.getElementById('analytics-tab').cloneNode(true);
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title>Budget Analytics Report</title>
                    <style>
                        body { font-family: 'Montserrat', sans-serif; margin: 20px; }
                        .analytics-container { max-width: 100%; }
                        @media print { 
                            body { margin: 0; }
                            .no-print { display: none; }
                        }
                    </style>
                </head>
                <body>
                    <h1 style="color: #015c2e; text-align: center; margin-bottom: 30px;">
                        DLSU Budget Management System - Analytics Report
                    </h1>
                    <div style="text-align: center; margin-bottom: 20px; color: #6c757d;">
                        Generated on: ${new Date().toLocaleString()}
                    </div>
                    ${analyticsContent.innerHTML}
                </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    }
    
    function refreshAnalytics() {
        // Show loading indicator
        document.body.style.cursor = 'wait';
        
        // Reload the page to refresh analytics data
        setTimeout(() => {
            window.location.reload();
        }, 500);
    }
    </script>
  </div>
  <?php endif; ?>

</div>

<!-- Modal -->
<div class="modal" id="detailsModal">
  <div class="modal-content">
    <span class="close-btn" onclick="closeModal()">&times;</span>
    <div class="modal-header">
      <h2 id="modalTitle">Request Details</h2>
    </div>
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

<!-- Amendment Form Modal -->
<div id="amendmentFormModal" style="display: none; position: fixed; z-index: 3000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8);" onclick="hideAmendmentForm(event)">
  <div style="position: relative; margin: 2% auto; width: 90%; max-width: 800px; background: white; border-radius: 12px; max-height: 90%; overflow-y: auto;" onclick="event.stopPropagation()">
    <div style="padding: 30px;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #015c2e;">
        <h2 style="margin: 0; color: #015c2e; font-size: 1.5em;">✏️ Create Amendment for <span id="amendmentRequestId">Request</span></h2>
        <button onclick="hideAmendmentForm()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #6c757d;">&times;</button>
      </div>
      
      <form id="amendmentForm" onsubmit="submitAmendment(event)">
        <input type="hidden" name="request_id" id="amendmentRequestIdInput" value="">
        
        <div style="margin-bottom: 20px;">
          <label style="display: block; margin-bottom: 5px; font-weight: bold; color: #015c2e;">Amendment Type:</label>
          <select name="amendment_type" id="amendmentType" onchange="showAmendmentFields()" style="width: 100%; padding: 10px; border: 2px solid #dee2e6; border-radius: 6px; font-size: 14px;" required>
            <option value="">-- Select Amendment Type --</option>
            <option value="budget_change">Budget Change</option>
            <option value="description_change">Description Modification</option>
            <option value="timeline_change">Timeline Adjustment</option>
            <option value="general_modification">General Modification</option>
          </select>
        </div>
        
        <div style="margin-bottom: 20px;">
          <label style="display: block; margin-bottom: 5px; font-weight: bold; color: #015c2e;">Amendment Title:</label>
          <input type="text" name="amendment_title" style="width: 100%; padding: 10px; border: 2px solid #dee2e6; border-radius: 6px; font-size: 14px;" 
                 placeholder="Brief title describing the amendment..." required>
        </div>
        
        <div style="margin-bottom: 20px;">
          <label style="display: block; margin-bottom: 5px; font-weight: bold; color: #015c2e;">Reason for Amendment:</label>
          <textarea name="amendment_reason" rows="4" style="width: 100%; padding: 10px; border: 2px solid #dee2e6; border-radius: 6px; font-size: 14px;" 
                    placeholder="Detailed explanation for why this amendment is necessary..." required></textarea>
        </div>

        <!-- Dynamic Amendment Fields -->
        <div id="amendmentSpecificFields" style="display: none;">
          
          <!-- Budget Change Fields -->
          <div id="budgetChangeFields" class="amendment-section" style="display: none;">
            <h4 style="color: #015c2e; margin-bottom: 15px; padding: 10px; background: #e8f5e8; border-radius: 6px;">💰 Budget Line Item Modifications</h4>
            <div id="budgetEntriesContainer">
              <!-- Budget entries will be dynamically loaded here -->
            </div>
            <div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span><strong>Original Total Budget:</strong></span>
                <span id="originalTotalBudget" style="font-size: 1.1em; color: #dc3545;">₱0.00</span>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 5px;">
                <span><strong>New Total Budget:</strong></span>
                <span id="newTotalBudget" style="font-size: 1.1em; color: #28a745; font-weight: bold;">₱0.00</span>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 5px;">
                <span><strong>Budget Change:</strong></span>
                <span id="budgetChange" style="font-size: 1.1em; font-weight: bold;">₱0.00</span>
              </div>
            </div>
          </div>

          <!-- Description Change Fields -->
          <div id="descriptionChangeFields" class="amendment-section" style="display: none;">
            <h4 style="color: #015c2e; margin-bottom: 15px; padding: 10px; background: #e8f5e8; border-radius: 6px;">📝 Description Modifications</h4>
            <div style="margin-bottom: 15px;">
              <label style="display: block; margin-bottom: 5px; font-weight: bold;">Original Budget Title:</label>
              <input type="text" id="originalBudgetTitle" readonly style="width: 100%; padding: 8px; border: 1px solid #ddd; background: #f8f9fa; border-radius: 4px;">
            </div>
            <div style="margin-bottom: 15px;">
              <label style="display: block; margin-bottom: 5px; font-weight: bold; color: #28a745;">New Budget Title:</label>
              <input type="text" name="new_budget_title" style="width: 100%; padding: 10px; border: 2px solid #28a745; border-radius: 6px;" placeholder="Enter new budget title...">
            </div>
            <div style="margin-bottom: 15px;">
              <label style="display: block; margin-bottom: 5px; font-weight: bold;">Original Description:</label>
              <textarea id="originalDescription" readonly rows="4" style="width: 100%; padding: 8px; border: 1px solid #ddd; background: #f8f9fa; border-radius: 4px;"></textarea>
            </div>
            <div style="margin-bottom: 15px;">
              <label style="display: block; margin-bottom: 5px; font-weight: bold; color: #28a745;">New Description:</label>
              <textarea name="new_description" rows="6" style="width: 100%; padding: 10px; border: 2px solid #28a745; border-radius: 6px;" placeholder="Enter new description..."></textarea>
            </div>
          </div>

          <!-- Timeline Change Fields -->
          <div id="timelineChangeFields" class="amendment-section" style="display: none;">
            <h4 style="color: #015c2e; margin-bottom: 15px; padding: 10px; background: #e8f5e8; border-radius: 6px;">📅 Timeline Adjustments</h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
              <div>
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Original Duration:</label>
                <input type="text" id="originalDuration" readonly style="width: 100%; padding: 8px; border: 1px solid #ddd; background: #f8f9fa; border-radius: 4px;">
              </div>
              <div>
                <label style="display: block; margin-bottom: 5px; font-weight: bold; color: #28a745;">New Duration:</label>
                <input type="text" name="new_duration" style="width: 100%; padding: 10px; border: 2px solid #28a745; border-radius: 6px;" placeholder="e.g., 12 months, Quarterly, etc.">
              </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
              <div>
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Original Academic Year:</label>
                <input type="text" id="originalAcademicYear" readonly style="width: 100%; padding: 8px; border: 1px solid #ddd; background: #f8f9fa; border-radius: 4px;">
              </div>
              <div>
                <label style="display: block; margin-bottom: 5px; font-weight: bold; color: #28a745;">New Academic Year:</label>
                <input type="text" name="new_academic_year" style="width: 100%; padding: 10px; border: 2px solid #28a745; border-radius: 6px;" placeholder="e.g., 2024-2025">
              </div>
            </div>
          </div>

          <!-- General Modification Fields -->
          <div id="generalModificationFields" class="amendment-section" style="display: none;">
            <h4 style="color: #015c2e; margin-bottom: 15px; padding: 10px; background: #e8f5e8; border-radius: 6px;">🔧 General Modifications</h4>
            <div style="margin-bottom: 15px;">
              <label style="display: block; margin-bottom: 5px; font-weight: bold;">Fund Account Code:</label>
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div>
                  <label style="display: block; margin-bottom: 5px; font-size: 12px;">Original:</label>
                  <input type="text" id="originalFundAccount" readonly style="width: 100%; padding: 8px; border: 1px solid #ddd; background: #f8f9fa; border-radius: 4px;">
                </div>
                <div>
                  <label style="display: block; margin-bottom: 5px; font-size: 12px; color: #28a745;">New:</label>
                  <input type="text" name="new_fund_account" style="width: 100%; padding: 10px; border: 2px solid #28a745; border-radius: 6px;" placeholder="New fund account code">
                </div>
              </div>
            </div>
            <div style="margin-bottom: 15px;">
              <label style="display: block; margin-bottom: 5px; font-weight: bold;">Fund Name:</label>
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div>
                  <label style="display: block; margin-bottom: 5px; font-size: 12px;">Original:</label>
                  <input type="text" id="originalFundName" readonly style="width: 100%; padding: 8px; border: 1px solid #ddd; background: #f8f9fa; border-radius: 4px;">
                </div>
                <div>
                  <label style="display: block; margin-bottom: 5px; font-size: 12px; color: #28a745;">New:</label>
                  <input type="text" name="new_fund_name" style="width: 100%; padding: 10px; border: 2px solid #28a745; border-radius: 6px;" placeholder="New fund name">
                </div>
              </div>
            </div>
            <div style="margin-bottom: 15px;">
              <label style="display: block; margin-bottom: 5px; font-weight: bold; color: #28a745;">Additional Notes:</label>
              <textarea name="general_modification_notes" rows="4" style="width: 100%; padding: 10px; border: 2px solid #28a745; border-radius: 6px;" placeholder="Describe any other changes or modifications..."></textarea>
            </div>
          </div>
        </div>
        
        <div style="display: flex; gap: 15px; justify-content: flex-end; padding-top: 20px; border-top: 1px solid #dee2e6;">
          <button type="button" onclick="hideAmendmentForm()" style="background: #6c757d; color: white; border: none; padding: 12px 24px; border-radius: 6px; font-size: 14px; cursor: pointer;">
            Cancel
          </button>
          <button type="submit" style="background: linear-gradient(135deg, #015c2e 0%, #28a745 100%); color: white; border: none; padding: 12px 24px; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer;">
            🚀 Create Amendment
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  function showModal(requestId) {
    const modal = document.getElementById("detailsModal");
    const modalBody = document.getElementById("modalBody");
    const modalTitle = document.getElementById("modalTitle");
    
    modalTitle.textContent = "Request Details - " + requestId;
    modalBody.innerHTML = "Loading...";

    fetch(`fetch_approval_details.php?request_id=${requestId}`)
      .then(res => res.text())
      .then(data => {
        modalBody.innerHTML = data;
        modal.style.display = "block";
      })
      .catch(err => {
        modalBody.innerHTML = "Failed to load details.";
      });
  }

  function closeModal() {
    document.getElementById("detailsModal").style.display = "none";
  }

  window.onclick = function(event) {
    const modal = document.getElementById("detailsModal");
    if (event.target === modal) {
      modal.style.display = "none";
    }
  };

  // Make handleApproval globally accessible
  window.handleApproval = function(requestId, action, comments = '') {
    console.log('handleApproval called:', requestId, action, comments);
    
    const formData = new FormData();
    formData.append('request_id', requestId);
    formData.append('action', action);
    formData.append('comments', comments);
    
    // If VP Finance is approving, collect approved amounts
    if (action === 'approve' && document.querySelector('.approved-amount-input')) {
      const approvedInputs = document.querySelectorAll('.approved-amount-input');
      approvedInputs.forEach(input => {
        const row = input.dataset.row;
        if (input.value && input.value.trim() !== '') {
          formData.append(`approved_amounts[${row}]`, input.value);
        }
      });
    }

    fetch('process_approval.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      console.log('Response:', data);
      if (data.success) {
        alert(data.message);
        // Close modal and reload page
        document.getElementById("detailsModal").style.display = "none";
        location.reload();
      } else {
        alert('Error: ' + data.message);
      }
    })
    .catch(err => {
      console.error('Fetch error:', err);
      alert('An error occurred while processing the request.');
    });
  }

  // Handle approval actions from modal
  window.handleApprovalFromModal = function(requestId, action) {
    console.log('handleApprovalFromModal called:', requestId, action);
    
    const comments = document.getElementById('comments').value || '';
    
    // Validation based on action
    if (action === 'request_info' && comments.trim() === '') {
        alert('Please provide specific information that is needed.');
        return;
    }
    
    // Confirmation messages
    let confirmMessage = '';
    switch(action) {
        case 'approve':
            confirmMessage = 'Are you sure you want to approve this request?';
            break;
        case 'reject':
            confirmMessage = 'Are you sure you want to reject this request?';
            break;
        case 'request_info':
            confirmMessage = 'Request more information from the requester?';
            break;
    }
    
    if (confirm(confirmMessage)) {
        window.handleApproval(requestId, action, comments);
    }
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

  // Tab switching functionality
  function switchTab(tabName) {
    // Hide all tabs
    const tabs = document.querySelectorAll('.tab-content');
    tabs.forEach(tab => tab.classList.remove('active'));
    
    // Remove active class from all buttons
    const buttons = document.querySelectorAll('.tab-button');
    buttons.forEach(button => button.classList.remove('active'));
    
    // Show selected tab
    document.getElementById(tabName + '-tab').classList.add('active');
    
    // Activate clicked button
    event.target.classList.add('active');
    
    // Initialize charts if analytics tab is selected
    if (tabName === 'analytics' && typeof initializeAnalyticsCharts === 'function') {
      // Small delay to ensure DOM elements are visible
      setTimeout(initializeAnalyticsCharts, 100);
    }
  }

  // Add CSS animation for loading spinner
  const style = document.createElement('style');
  style.textContent = `
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
  `;
  document.head.appendChild(style);

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

  // Amendment System Functions
  function createAmendmentForRequest(requestId) {
    console.log('Creating amendment for request:', requestId);
    showAmendmentForm(requestId);
  }

  function showAmendmentForm(requestId) {
    document.getElementById('amendmentRequestId').textContent = requestId;
    document.getElementById('amendmentRequestIdInput').value = requestId;
    document.getElementById('amendmentFormModal').style.display = 'block';
    
    // Clear form
    document.getElementById('amendmentForm').reset();
    document.getElementById('amendmentRequestIdInput').value = requestId;
    
    // Reset amendment fields
    document.getElementById('amendmentSpecificFields').style.display = 'none';
    document.querySelectorAll('.amendment-section').forEach(section => {
      section.style.display = 'none';
    });
    
    // Load request data for amendment form
    loadRequestDataForAmendment(requestId);
  }

  function hideAmendmentForm(event) {
    if (!event || event.target === event.currentTarget) {
      document.getElementById('amendmentFormModal').style.display = 'none';
    }
  }

  // Global variable to store current request data
  let currentRequestData = null;

  function loadRequestDataForAmendment(requestId) {
    // Fetch request data
    fetch(`get_request_data.php?request_id=${requestId}`)
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          currentRequestData = data.request;
          populateOriginalData(data.request);
        } else {
          console.error('Failed to load request data:', data.message);
        }
      })
      .catch(error => {
        console.error('Error loading request data:', error);
      });
  }

  function populateOriginalData(requestData) {
    // Populate description fields
    if (document.getElementById('originalBudgetTitle')) {
      document.getElementById('originalBudgetTitle').value = requestData.budget_title || '';
    }
    if (document.getElementById('originalDescription')) {
      document.getElementById('originalDescription').value = requestData.description || '';
    }
    
    // Populate timeline fields
    if (document.getElementById('originalDuration')) {
      document.getElementById('originalDuration').value = requestData.duration || '';
    }
    if (document.getElementById('originalAcademicYear')) {
      document.getElementById('originalAcademicYear').value = requestData.academic_year || '';
    }
    
    // Populate general modification fields
    if (document.getElementById('originalFundAccount')) {
      document.getElementById('originalFundAccount').value = requestData.fund_account || '';
    }
    if (document.getElementById('originalFundName')) {
      document.getElementById('originalFundName').value = requestData.fund_name || '';
    }
    
    // Populate budget fields - use approved total if available
    if (document.getElementById('originalTotalBudget') && requestData.proposed_budget) {
      // Calculate current approved total from budget entries
      let currentApprovedTotal = 0;
      if (requestData.budget_entries) {
        requestData.budget_entries.forEach(entry => {
          const approvedAmount = entry.approved_amount !== null && entry.approved_amount !== '' ? entry.approved_amount : entry.amount;
          currentApprovedTotal += parseFloat(approvedAmount);
        });
      } else {
        currentApprovedTotal = parseFloat(requestData.proposed_budget);
      }
      
      document.getElementById('originalTotalBudget').textContent = '₱' + currentApprovedTotal.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
      document.getElementById('newTotalBudget').textContent = '₱' + currentApprovedTotal.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
      document.getElementById('budgetChange').textContent = '₱0.00';
      document.getElementById('budgetChange').style.color = '#6c757d';
    }
    
    // Load budget entries for budget change type
    if (requestData.budget_entries) {
      populateBudgetEntries(requestData.budget_entries);
    }
  }

  function populateBudgetEntries(budgetEntries) {
    const container = document.getElementById('budgetEntriesContainer');
    if (!container) return;
    
    container.innerHTML = '';
    
    budgetEntries.forEach((entry, index) => {
      // Use approved amount if available, otherwise use original amount
      const currentAmount = entry.approved_amount !== null && entry.approved_amount !== '' ? entry.approved_amount : entry.amount;
      
      const entryDiv = document.createElement('div');
      entryDiv.style.cssText = 'border: 1px solid #dee2e6; border-radius: 6px; padding: 15px; margin-bottom: 15px; background: #f8f9fa;';
      
      entryDiv.innerHTML = `
        <div style="display: grid; grid-template-columns: 1fr 2fr 1fr; gap: 10px; align-items: center;">
          <div>
            <label style="font-weight: bold; color: #015c2e; font-size: 12px;">GL Code:</label>
            <div style="padding: 5px; background: white; border-radius: 3px; border: 1px solid #ddd;">
              ${entry.gl_code}
            </div>
          </div>
          <div>
            <label style="font-weight: bold; color: #015c2e; font-size: 12px;">Description:</label>
            <div style="padding: 5px; background: white; border-radius: 3px; border: 1px solid #ddd; font-size: 13px;">
              ${entry.budget_description}
            </div>
          </div>
          <div>
            <label style="font-weight: bold; color: #015c2e; font-size: 12px;">Current Approved Amount:</label>
            <div style="padding: 5px; background: white; border-radius: 3px; border: 1px solid #ddd; text-align: right; font-weight: bold;">
              ₱${parseFloat(currentAmount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
            </div>
          </div>
        </div>
        <div style="margin-top: 10px;">
          <label style="font-weight: bold; color: #28a745; font-size: 12px;">New Amount:</label>
          <input type="number" 
                 name="budget_entries[${entry.row_num}][new_amount]" 
                 data-original-amount="${currentAmount}"
                 data-row="${entry.row_num}"
                 class="budget-entry-input"
                 step="0.01" 
                 min="0" 
                 value="${currentAmount}"
                 onchange="updateBudgetTotals()"
                 style="width: 100%; padding: 8px; border: 2px solid #28a745; border-radius: 4px; text-align: right;">
          <input type="hidden" name="budget_entries[${entry.row_num}][gl_code]" value="${entry.gl_code}">
          <input type="hidden" name="budget_entries[${entry.row_num}][description]" value="${entry.budget_description}">
          <input type="hidden" name="budget_entries[${entry.row_num}][original_amount]" value="${currentAmount}">
        </div>
      `;
      
      container.appendChild(entryDiv);
    });
  }

  function updateBudgetTotals() {
    let newTotal = 0;
    let originalTotal = 0;
    
    document.querySelectorAll('.budget-entry-input').forEach(input => {
      const newAmount = parseFloat(input.value) || 0;
      const originalAmount = parseFloat(input.dataset.originalAmount) || 0;
      
      newTotal += newAmount;
      originalTotal += originalAmount;
    });
    
    const change = newTotal - originalTotal;
    
    document.getElementById('newTotalBudget').textContent = '₱' + newTotal.toLocaleString('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
    
    document.getElementById('budgetChange').textContent = (change >= 0 ? '+' : '') + '₱' + change.toLocaleString('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
    
    document.getElementById('budgetChange').style.color = change > 0 ? '#28a745' : change < 0 ? '#dc3545' : '#6c757d';
  }

  function showAmendmentFields() {
    const selectedType = document.getElementById('amendmentType').value;
    const specificFields = document.getElementById('amendmentSpecificFields');
    
    // Hide all sections first
    document.querySelectorAll('.amendment-section').forEach(section => {
      section.style.display = 'none';
    });
    
    if (selectedType) {
      specificFields.style.display = 'block';
      
      // Show the relevant section
      switch(selectedType) {
        case 'budget_change':
          document.getElementById('budgetChangeFields').style.display = 'block';
          break;
        case 'description_change':
          document.getElementById('descriptionChangeFields').style.display = 'block';
          break;
        case 'timeline_change':
          document.getElementById('timelineChangeFields').style.display = 'block';
          break;
        case 'general_modification':
          document.getElementById('generalModificationFields').style.display = 'block';
          break;
      }
    } else {
      specificFields.style.display = 'none';
    }
  }

  function submitAmendment(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    // Show loading indicator
    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '⏳ Creating Amendment...';
    submitBtn.disabled = true;
    
    fetch('process_amendment.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        alert('✅ Amendment created successfully!\n\nAmendment ID: ' + data.amendment_id + '\nAmendment Number: ' + data.amendment_number);
        hideAmendmentForm();
        
        // Reload the modal content to show the new amendment
        const requestId = formData.get('request_id');
        if (requestId) {
          showModal(requestId);
        }
      } else {
        let errorMessage = '❌ Error creating amendment: ' + data.message;
        if (data.technical_error) {
          errorMessage += '\n\nTechnical Error: ' + data.technical_error;
        }
        if (data.debug_info) {
          errorMessage += '\n\nDebug Info: ' + data.debug_info;
        }
        alert(errorMessage);
        console.error('Amendment creation failed:', data);
      }
    })
    .catch(error => {
      console.error('Network/JavaScript Error:', error);
      alert('❌ An error occurred while creating the amendment.\n\nPossible causes:\n- Network connection issue\n- Database column missing\n- JavaScript error\n\nCheck browser console (F12) for details.\n\nTry running fix_amendment_column.php if database column is missing.');
    })
    .finally(() => {
      submitBtn.innerHTML = originalText;
      submitBtn.disabled = false;
    });
  }

  // Make amendment functions globally available
  window.createAmendmentForRequest = createAmendmentForRequest;
  window.showAmendmentForm = showAmendmentForm;
  window.hideAmendmentForm = hideAmendmentForm;
  window.submitAmendment = submitAmendment;
</script>

</body>
</html>
