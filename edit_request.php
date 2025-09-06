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

$request_id = $_GET['request_id'] ?? '';
$error_message = $_GET['error'] ?? '';

if (empty($request_id)) {
    header("Location: requester.php");
    exit;
}

// Verify this request belongs to the current user and needs more information
$stmt = $pdo->prepare("
    SELECT * FROM budget_request 
    WHERE request_id = ? AND account_id = ? AND status = 'more_info_requested'
");
$stmt->execute([$request_id, $account_id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    header("Location: requester.php?error=Request not found or cannot be edited");
    exit;
}

// Get current budget entries
$stmt = $pdo->prepare("SELECT * FROM budget_entries WHERE request_id = ? ORDER BY row_num");
$stmt->execute([$request_id]);
$current_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get the approval information that requested more info
$stmt = $pdo->prepare("
    SELECT ap.*, a.name as approver_name, a.role as approver_role
    FROM approval_progress ap
    LEFT JOIN account a ON ap.approver_id = a.id
    WHERE ap.request_id = ? AND ap.status = 'request_info'
    ORDER BY ap.approval_level DESC
    LIMIT 1
");
$stmt->execute([$request_id]);
$info_request = $stmt->fetch(PDO::FETCH_ASSOC);

// If we can't find request_info status, try to get the current approver at the current level
if (!$info_request && $request) {
    $stmt = $pdo->prepare("
        SELECT ap.*, a.name as approver_name, a.role as approver_role
        FROM approval_progress ap
        LEFT JOIN account a ON ap.approver_id = a.id
        WHERE ap.request_id = ? AND ap.approval_level = ?
        LIMIT 1
    ");
    $stmt->execute([$request_id, $request['current_approval_level']]);
    $info_request = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get departments for dropdown
$stmt = $pdo->prepare("SELECT code, college FROM department ORDER BY college, code");
$stmt->execute();
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get campus codes
$stmt = $pdo->prepare("SELECT code, name FROM campus ORDER BY name");
$stmt->execute();
$campuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Budget Request</title>
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

    .container {
      display: flex;
      flex-direction: column;
      min-height: 100vh;
      padding: 30px;
      background: white;
    }

    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }

    h1 {
      font-size: 28px;
      font-weight: 700;
      color: #015c2e;
    }

    .info-alert {
      background: #fff3cd;
      border: 1px solid #ffeaa7;
      color: #856404;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
    }

    .info-alert h3 {
      margin: 0 0 10px 0;
      color: #856404;
    }

    .form-section {
      background: white;
      border: 1px solid #dee2e6;
      border-radius: 8px;
      padding: 20px;
      margin-bottom: 20px;
    }

    .form-section h3 {
      margin: 0 0 15px 0;
      color: #015c2e;
      font-size: 18px;
    }

    .form-group {
      margin-bottom: 15px;
    }

    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 15px;
    }

    label {
      display: block;
      margin-bottom: 5px;
      font-weight: 600;
      color: #333;
    }

    input, select, textarea {
      width: 100%;
      padding: 10px;
      border: 2px solid #dee2e6;
      border-radius: 5px;
      font-size: 14px;
      background-color: white;
      color: #333;
    }

    input:focus, select:focus, textarea:focus {
      border-color: #015c2e;
      outline: none;
    }

    textarea {
      resize: vertical;
      min-height: 100px;
    }

    .budget-entries {
      border: 1px solid #dee2e6;
      border-radius: 8px;
      overflow: hidden;
    }

    .budget-entries table {
      width: 100%;
      border-collapse: collapse;
    }

    .budget-entries th {
      background-color: #015c2e;
      color: white;
      padding: 12px;
      text-align: left;
      font-weight: 600;
    }

    .budget-entries td {
      padding: 10px;
      border-bottom: 1px solid #dee2e6;
    }

    .budget-entries input {
      border: 1px solid #ccc;
      margin: 0;
    }

    .add-row-btn, .remove-row-btn {
      padding: 8px 12px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 12px;
      font-weight: 600;
    }

    .add-row-btn {
      background-color: #28a745;
      color: white;
      margin-bottom: 15px;
    }

    .remove-row-btn {
      background-color: #dc3545;
      color: white;
    }

    .total-display {
      background-color: #f8f9fa;
      padding: 15px;
      text-align: right;
      border-top: 2px solid #015c2e;
      font-size: 18px;
      font-weight: bold;
    }

    .action-buttons {
      display: flex;
      gap: 15px;
      justify-content: center;
      margin-top: 30px;
    }

    .btn {
      padding: 12px 30px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-size: 16px;
      font-weight: 600;
      text-decoration: none;
      text-align: center;
    }

    .btn-primary {
      background-color: #015c2e;
      color: white;
    }

    .btn-secondary {
      background-color: #6c757d;
      color: white;
    }

    .btn:hover {
      opacity: 0.9;
    }
  </style>
</head>
<body>

<div class="container">
  <div class="header">
    <h1>Edit Budget Request - <?php echo htmlspecialchars($request_id); ?></h1>
    <a href="requester.php" class="btn btn-secondary">← Back to Dashboard</a>
  </div>

  <?php if ($info_request): ?>
  <div class="info-alert">
    <h3>❓ Information Request from Approver</h3>
    <p><strong>Requested by:</strong> <?php echo htmlspecialchars($info_request['approver_name']); ?> (<?php echo ucwords(str_replace('_', ' ', $info_request['approver_role'])); ?>)</p>
    <?php if ($info_request['timestamp']): ?>
    <p><strong>Date:</strong> <?php echo date('M j, Y g:i A', strtotime($info_request['timestamp'])); ?></p>
    <?php endif; ?>
    <?php if ($info_request['comments']): ?>
    <p><strong>Information needed:</strong></p>
    <div style='background: white; padding: 10px; border-radius: 4px; border: 1px solid #ffeaa7; font-style: italic;'>
      "<?php echo htmlspecialchars($info_request['comments']); ?>"
    </div>
    <?php endif; ?>
    <p style="margin-top: 15px;"><em>Please update your request with the requested information and resubmit for continued approval.</em></p>
  </div>
  <?php endif; ?>

  <?php if ($error_message): ?>
  <div class="info-alert" style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24;">
    <h3>❌ Error</h3>
    <p><?php echo htmlspecialchars($error_message); ?></p>
  </div>
  <?php endif; ?>

  <form id="budgetForm" method="POST" action="update_request.php">
    <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($request_id); ?>">
    
    <div class="form-section">
      <h3>📋 Request Information</h3>
      
      <div class="form-row">
        <div class="form-group">
          <label for="budget_title">Budget Request Title *</label>
          <input type="text" id="budget_title" name="budget_title" value="<?php echo htmlspecialchars($request['budget_title']); ?>" required>
        </div>
        
        <div class="form-group">
          <label for="academic_year">Academic Year *</label>
          <select id="academic_year" name="academic_year" required>
            <option value="">Select Academic Year</option>
            <option value="2025-2026" <?php echo $request['academic_year'] === '2025-2026' ? 'selected' : ''; ?>>2025-2026</option>
            <option value="2026-2027" <?php echo $request['academic_year'] === '2026-2027' ? 'selected' : ''; ?>>2026-2027</option>
            <option value="2027-2028" <?php echo $request['academic_year'] === '2027-2028' ? 'selected' : ''; ?>>2027-2028</option>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="campus_code">Campus *</label>
          <select id="campus_code" name="campus_code" required>
            <option value="">Select Campus</option>
            <?php foreach ($campuses as $campus): ?>
            <option value="<?php echo htmlspecialchars($campus['code']); ?>" <?php echo $request['campus_code'] === $campus['code'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($campus['code'] . ' - ' . $campus['name']); ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="department_code">Department *</label>
          <select id="department_code" name="department_code" required>
            <option value="">Select Department</option>
            <?php foreach ($departments as $dept): ?>
            <option value="<?php echo htmlspecialchars($dept['code']); ?>" <?php echo $request['department_code'] === $dept['code'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($dept['code'] . ' - ' . $dept['college']); ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="fund_account">Fund Account Code *</label>
          <input type="text" id="fund_account" name="fund_account" value="<?php echo htmlspecialchars($request['fund_account']); ?>" required>
        </div>

        <div class="form-group">
          <label for="fund_name">Fund Name *</label>
          <input type="text" id="fund_name" name="fund_name" value="<?php echo htmlspecialchars($request['fund_name']); ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label for="duration">Duration *</label>
        <select id="duration" name="duration" required>
          <option value="">Select Duration</option>
          <option value="Monthly" <?php echo $request['duration'] === 'Monthly' ? 'selected' : ''; ?>>Monthly</option>
          <option value="Quarterly" <?php echo $request['duration'] === 'Quarterly' ? 'selected' : ''; ?>>Quarterly</option>
          <option value="Annually" <?php echo $request['duration'] === 'Annually' ? 'selected' : ''; ?>>Annually</option>
        </select>
      </div>

      <div class="form-group">
        <label for="description">Description</label>
        <textarea id="description" name="description" placeholder="Provide additional details about your budget request..."><?php echo htmlspecialchars($request['description']); ?></textarea>
      </div>
    </div>

    <div class="form-section">
      <h3>💰 Budget Details</h3>
      
      <button type="button" class="add-row-btn" onclick="addBudgetRow()">+ Add Row</button>
      
      <div class="budget-entries">
        <table id="budgetTable">
          <thead>
            <tr>
              <th width="8%">Row</th>
              <th width="15%">GL Code *</th>
              <th width="35%">Budget Description *</th>
              <th width="25%">Remarks</th>
              <th width="15%">Amount *</th>
              <th width="2%">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($current_entries as $index => $entry): ?>
            <tr>
              <td><?php echo $index + 1; ?></td>
              <td><input type="text" name="gl_code[]" value="<?php echo htmlspecialchars($entry['gl_code']); ?>" required></td>
              <td><input type="text" name="budget_description[]" value="<?php echo htmlspecialchars($entry['budget_description']); ?>" required></td>
              <td><input type="text" name="remarks[]" value="<?php echo htmlspecialchars($entry['remarks']); ?>"></td>
              <td><input type="number" name="amount[]" step="0.01" min="0" value="<?php echo $entry['amount']; ?>" onchange="calculateTotal()" required></td>
              <td><button type="button" class="remove-row-btn" onclick="removeRow(this)">×</button></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div class="total-display">
          Total Amount: ₱<span id="totalAmount">0.00</span>
        </div>
      </div>
    </div>

    <div class="action-buttons">
      <button type="submit" class="btn btn-primary">Resubmit Request</button>
      <a href="requester.php" class="btn btn-secondary">Cancel</a>
    </div>
  </form>
</div>

<script>
let rowCount = <?php echo count($current_entries); ?>;

function addBudgetRow() {
    rowCount++;
    const tbody = document.querySelector('#budgetTable tbody');
    const row = document.createElement('tr');
    row.innerHTML = `
        <td>${rowCount}</td>
        <td><input type="text" name="gl_code[]" required></td>
        <td><input type="text" name="budget_description[]" required></td>
        <td><input type="text" name="remarks[]"></td>
        <td><input type="number" name="amount[]" step="0.01" min="0" onchange="calculateTotal()" required></td>
        <td><button type="button" class="remove-row-btn" onclick="removeRow(this)">×</button></td>
    `;
    tbody.appendChild(row);
    updateRowNumbers();
}

function removeRow(btn) {
    if (document.querySelectorAll('#budgetTable tbody tr').length > 1) {
        btn.closest('tr').remove();
        updateRowNumbers();
        calculateTotal();
    } else {
        alert('At least one budget row is required.');
    }
}

function updateRowNumbers() {
    const rows = document.querySelectorAll('#budgetTable tbody tr');
    rows.forEach((row, index) => {
        row.cells[0].textContent = index + 1;
    });
}

function calculateTotal() {
    const amounts = document.querySelectorAll('input[name="amount[]"]');
    let total = 0;
    amounts.forEach(input => {
        if (input.value && !isNaN(parseFloat(input.value))) {
            total += parseFloat(input.value);
        }
    });
    document.getElementById('totalAmount').textContent = total.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

document.getElementById('budgetForm').addEventListener('submit', function(e) {
    const amounts = document.querySelectorAll('input[name="amount[]"]');
    let hasValidAmount = false;
    
    amounts.forEach(input => {
        if (input.value && parseFloat(input.value) > 0) {
            hasValidAmount = true;
        }
    });
    
    if (!hasValidAmount) {
        e.preventDefault();
        alert('Please enter at least one valid amount greater than 0.');
        return;
    }
    
    if (confirm('Are you sure you want to resubmit this request? It will be sent back for approval.')) {
        return true;
    } else {
        e.preventDefault();
    }
});

// Calculate initial total
calculateTotal();
</script>

</body>
</html>