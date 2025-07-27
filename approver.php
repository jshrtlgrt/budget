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
$view_mode = $_GET['view'] ?? 'pending'; // 'pending' or 'all'

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
</script>

</body>
</html>
