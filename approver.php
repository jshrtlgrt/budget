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
                       d.college, d.budget_deck, 'vp_finance' as user_role
                FROM budget_request br 
                LEFT JOIN account a ON br.account_id = a.id 
                LEFT JOIN department d ON br.department_code = d.code 
                WHERE 1=1";
        $params = [];
    } else {
        // Other roles see requests based on approval workflow
        $sql = "SELECT br.*, a.name as requester_name, a.username_email as requester_email, 
                       d.college, d.budget_deck,
                       CASE WHEN ap.approver_id = ? THEN 'can_approve' ELSE 'view_only' END as user_role
                FROM budget_request br 
                LEFT JOIN account a ON br.account_id = a.id 
                LEFT JOIN department d ON br.department_code = d.code 
                LEFT JOIN approval_progress ap ON br.request_id = ap.request_id 
                    AND ap.approval_level = br.current_approval_level 
                    AND ap.approver_id = ?
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
  <title>Approver Dashboard</title>
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

    .user-info {
      color: #666;
      font-size: 14px;
    }

    .filters {
      background-color: #015c2e;
      padding: 15px 20px;
      display: flex;
      align-items: center;
      gap: 20px;
      flex-wrap: wrap;
      border-radius: 5px;
      margin-bottom: 20px;
    }

    .filters label {
      color: white;
      font-weight: bold;
      margin-right: 5px;
    }

    .filters select, .filters input {
      padding: 6px 10px;
      border-radius: 5px;
      border: none;
      font-size: 14px;
    }

    .filters input[type="text"] {
      min-width: 200px;
    }

    .content-box {
      flex: 1;
      border: 1px solid #ccc;
      border-radius: 5px;
      padding: 20px;
      overflow-y: auto;
    }

    .request-card {
      background-color: #f8f9fa;
      padding: 20px;
      border-radius: 5px;
      margin-bottom: 15px;
      cursor: pointer;
      border-left: 4px solid #015c2e;
      transition: all 0.2s;
    }

    .request-card:hover {
      background-color: #e9ecef;
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .request-card.pending {
      border-left-color: #ffc107;
    }

    .request-card.approved {
      border-left-color: #28a745;
    }

    .request-card.rejected {
      border-left-color: #dc3545;
    }

    .request-title {
      font-weight: 700;
      margin-bottom: 10px;
      font-size: 16px;
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

    .status-approved { color: #28a745; font-weight: bold; }
    .status-pending { color: #ffc107; font-weight: bold; }
    .status-rejected { color: #dc3545; font-weight: bold; }

    .amount {
      font-weight: bold;
      color: #015c2e;
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

<div class="dashboard-container">
  <div class="header-bar">
    <h1>APPROVER DASHBOARD</h1>
    <div class="user-info">
      Logged in as: <?php echo htmlspecialchars($_SESSION['username']); ?>
      <br><a href="logout.php" style="color: #015c2e;">Logout</a>
    </div>
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
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
    </div>
  </div>

  <!-- Tab Content: Analytics & Reports (VP Finance Only) -->
  <?php if ($_SESSION['role'] === 'vp_finance'): ?>
  <div id="analytics-tab" class="tab-content">
    <?php
    // Include analytics content directly to maintain session
    try {
        // Get overall statistics
        $stats_query = "
            SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'more_info_requested' THEN 1 ELSE 0 END) as info_requested_count,
                SUM(proposed_budget) as total_budget,
                SUM(CASE WHEN status = 'approved' THEN proposed_budget ELSE 0 END) as approved_budget,
                AVG(proposed_budget) as avg_budget
            FROM budget_request
        ";

        $stmt = $pdo->prepare($stats_query);
        $stmt->execute();
        $overall_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$overall_stats) {
            $overall_stats = [
                'total_requests' => 0,
                'approved_count' => 0,
                'rejected_count' => 0,
                'pending_count' => 0,
                'info_requested_count' => 0,
                'total_budget' => 0,
                'approved_budget' => 0,
                'avg_budget' => 0
            ];
        }

        // Get monthly statistics for all data
        $monthly_query = "
            SELECT 
                DATE_FORMAT(timestamp, '%Y-%m') as month,
                DATE_FORMAT(timestamp, '%M %Y') as month_name,
                COUNT(*) as total_requests,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(proposed_budget) as total_amount,
                SUM(CASE WHEN status = 'approved' THEN proposed_budget ELSE 0 END) as approved_amount
            FROM budget_request 
            GROUP BY DATE_FORMAT(timestamp, '%Y-%m')
            ORDER BY month ASC
        ";

        $stmt = $pdo->prepare($monthly_query);
        $stmt->execute();
        $monthly_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get department-wise statistics
        $dept_query = "
            SELECT 
                br.department_code,
                d.college,
                COUNT(*) as total_requests,
                SUM(CASE WHEN br.status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN br.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(br.proposed_budget) as total_budget,
                SUM(CASE WHEN br.status = 'approved' THEN br.proposed_budget ELSE 0 END) as approved_budget
            FROM budget_request br
            LEFT JOIN department d ON br.department_code = d.code
            GROUP BY br.department_code
            ORDER BY total_requests DESC
        ";

        $stmt = $pdo->prepare($dept_query);
        $stmt->execute();
        $dept_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get recent activity
        $recent_query = "
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

        $stmt = $pdo->prepare($recent_query);
        $stmt->execute();
        $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Prepare data for JavaScript
        $chart_data = [
            'status_distribution' => [
                'approved' => (int)$overall_stats['approved_count'],
                'rejected' => (int)$overall_stats['rejected_count'],
                'pending' => (int)$overall_stats['pending_count'],
                'info_requested' => (int)$overall_stats['info_requested_count']
            ],
            'monthly_data' => $monthly_stats,
            'department_data' => $dept_stats
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
                                <?php foreach ($dept_stats as $dept): 
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
                                    <td style="padding: 10px; border: 1px solid #dee2e6; text-align: right;">₱<?php echo number_format($dept['total_budget'], 2); ?></td>
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
                            $low_performing = array_filter($dept_stats, function($dept) {
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
                                <?php foreach ($monthly_stats as $month): ?>
                                <tr style="transition: background-color 0.2s;">
                                    <td style="padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6;"><?php echo htmlspecialchars($month['month_name']); ?></td>
                                    <td style="padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6;"><?php echo number_format($month['total_requests']); ?></td>
                                    <td style="padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6; color: #28a745; font-weight: bold;"><?php echo number_format($month['approved']); ?></td>
                                    <td style="padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6; color: #dc3545; font-weight: bold;"><?php echo number_format($month['rejected']); ?></td>
                                    <td style="padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6;">₱<?php echo number_format($month['total_amount'], 2); ?></td>
                                    <td style="padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6; color: #28a745; font-weight: bold;">₱<?php echo number_format($month['approved_amount'], 2); ?></td>
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
    if (action === 'reject' && comments.trim() === '') {
        alert('Please provide comments when rejecting a request.');
        return;
    }
    
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
</script>

</body>
</html>
