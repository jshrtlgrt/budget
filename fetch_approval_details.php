<?php
session_start();

// Allow multiple approver roles
$allowed_roles = ['approver', 'department_head', 'dean', 'vp_finance'];
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], $allowed_roles)) {
    exit("Unauthorized access.");
}

if (!isset($_GET['request_id'])) {
    exit("Invalid request.");
}

$pdo = new PDO("mysql:host=localhost;dbname=budget_database_schema", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$request_id = $_GET['request_id'];

// Get request details with requester and department info
$stmt = $pdo->prepare("
    SELECT br.*, a.name as requester_name, a.username_email as requester_email, 
           d.college, d.budget_deck 
    FROM budget_request br 
    LEFT JOIN account a ON br.account_id = a.id 
    LEFT JOIN department d ON br.department_code = d.code 
    WHERE br.request_id = ?
");
$stmt->execute([$request_id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    exit("Request not found.");
}

// Get budget entries
$stmt = $pdo->prepare("SELECT * FROM budget_entries WHERE request_id = ? ORDER BY row_num");
$stmt->execute([$request_id]);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get approval workflow history
require_once 'workflow_manager.php';
$workflow = new WorkflowManager($pdo);
$approval_history = $workflow->getApprovalHistory($request_id);

// Get general history
$stmt = $pdo->prepare("
    SELECT h.*, a.name as approver_name 
    FROM history h 
    LEFT JOIN account a ON h.account_id = a.id 
    WHERE h.request_id = ? 
    ORDER BY h.timestamp DESC
");
$stmt->execute([$request_id]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="modal-section">
    <h3>Request Information</h3>
    <div class="info-grid">
        <div class="info-item">
            <strong>Request ID:</strong>
            <?php echo htmlspecialchars($request['request_id']); ?>
        </div>
        <div class="info-item">
            <strong>Requester:</strong>
            <?php echo htmlspecialchars($request['requester_name'] ?? 'Unknown'); ?>
            <br><small><?php echo htmlspecialchars($request['requester_email'] ?? ''); ?></small>
        </div>
        <div class="info-item">
            <strong>College:</strong>
            <?php echo htmlspecialchars($request['college'] ?? 'N/A'); ?>
        </div>
        <div class="info-item">
            <strong>Department Code:</strong>
            <?php echo htmlspecialchars($request['department_code']); ?>
        </div>
        <div class="info-item">
            <strong>Academic Year:</strong>
            <?php echo htmlspecialchars($request['academic_year']); ?>
        </div>
        <div class="info-item">
            <strong>Submitted:</strong>
            <?php echo date("F j, Y g:i A", strtotime($request['timestamp'])); ?>
        </div>
        <div class="info-item">
            <strong>Total Budget:</strong>
            <span style="font-size: 18px; color: #015c2e; font-weight: bold;">
                ₱<?php echo number_format($request['proposed_budget'], 2); ?>
            </span>
        </div>
        <div class="info-item">
            <strong>Current Status:</strong>
            <span class="status-<?php echo strtolower($request['status']); ?>">
                <?php echo htmlspecialchars($request['status']); ?>
            </span>
        </div>
        <?php if (!is_null($request['current_approval_level'])): ?>
        <div class="info-item">
            <strong>Approval Progress:</strong>
            Level <?php echo $request['current_approval_level']; ?> of <?php echo $request['total_approval_levels']; ?>
            <?php if ($request['workflow_complete']): ?>
                <br><small style="color: green;">✓ Workflow Complete</small>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($entries)): ?>
<div class="modal-section">
    <h3>Budget Line Items</h3>
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <thead>
                <tr style="background-color: #015c2e; color: white;">
                    <th style="padding: 12px; text-align: left; border: 1px solid #ddd;">Row</th>
                    <th style="padding: 12px; text-align: left; border: 1px solid #ddd;">GL Code</th>
                    <th style="padding: 12px; text-align: left; border: 1px solid #ddd;">Description</th>
                    <th style="padding: 12px; text-align: left; border: 1px solid #ddd;">Amount</th>
                    <th style="padding: 12px; text-align: left; border: 1px solid #ddd;">Fund Account</th>
                    <th style="padding: 12px; text-align: left; border: 1px solid #ddd;">Fund Name</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $entry): ?>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo $entry['row_num']; ?></td>
                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo htmlspecialchars($entry['gl_code']); ?></td>
                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo htmlspecialchars($entry['budget_description']); ?></td>
                    <td style="padding: 10px; border: 1px solid #ddd; font-weight: bold;">₱<?php echo number_format($entry['amount'], 2); ?></td>
                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo htmlspecialchars($entry['fund_account']); ?></td>
                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo htmlspecialchars($entry['fund_name']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($approval_history) || !empty($history)): ?>
<div class="modal-section">
    <h3>Approval Workflow</h3>
    <?php if (!empty($approval_history)): ?>
    <div class="history-section">
        <h4 style="color: #015c2e; margin-bottom: 10px;">Approval Levels:</h4>
        <?php foreach ($approval_history as $ah): ?>
        <div class="history-item" style="border-left: 3px solid <?php 
            echo $ah['status'] === 'approved' ? '#28a745' : 
                ($ah['status'] === 'rejected' ? '#dc3545' : 
                ($ah['status'] === 'pending' ? '#ffc107' : '#6c757d')); ?>; padding-left: 10px;">
            <strong>Level <?php echo $ah['approval_level']; ?>:</strong> 
            <?php echo htmlspecialchars($ah['approver_name'] ?? 'Unassigned'); ?>
            <span style="text-transform: capitalize; margin-left: 10px;">(<?php echo htmlspecialchars($ah['approver_role'] ?? 'Unknown Role'); ?>)</span>
            <br>
            <span style="font-weight: bold; color: <?php 
                echo $ah['status'] === 'approved' ? '#28a745' : 
                    ($ah['status'] === 'rejected' ? '#dc3545' : 
                    ($ah['status'] === 'pending' ? '#ffc107' : '#6c757d')); ?>">
                Status: <?php echo ucfirst($ah['status']); ?>
            </span>
            <?php if ($ah['timestamp'] && $ah['status'] !== 'pending'): ?>
                <br><small><?php echo date("M j, Y g:i A", strtotime($ah['timestamp'])); ?></small>
            <?php endif; ?>
            <?php if (!empty($ah['comments'])): ?>
                <br><em>"<?php echo htmlspecialchars($ah['comments']); ?>"</em>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($history)): ?>
    <div class="history-section" style="margin-top: 15px;">
        <h4 style="color: #015c2e; margin-bottom: 10px;">Activity History:</h4>
        <?php foreach ($history as $h): ?>
        <div class="history-item">
            <strong><?php echo date("M j, Y g:i A", strtotime($h['timestamp'])); ?></strong>
            - <?php echo htmlspecialchars($h['approver_name'] ?? 'System'); ?>
            <br><em><?php echo htmlspecialchars($h['action']); ?></em>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (strtolower($request['status']) === 'pending'): ?>
<div class="modal-section">
    <h3>Approval Actions</h3>
    <div style="margin-bottom: 15px;">
        <label for="comments" style="display: block; margin-bottom: 5px; font-weight: bold;">Comments (Optional):</label>
        <textarea id="comments" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" 
                  placeholder="Add any comments about your decision..."></textarea>
    </div>
    
    <div class="action-buttons">
        <button class="btn btn-approve" onclick="handleApprovalFromModal('<?php echo $request['request_id']; ?>', 'approve')">
            ✓ Approve Request
        </button>
        <button class="btn btn-reject" onclick="handleApprovalFromModal('<?php echo $request['request_id']; ?>', 'reject')">
            ✗ Reject Request
        </button>
        <button class="btn btn-request-info" onclick="handleApprovalFromModal('<?php echo $request['request_id']; ?>', 'request_info')">
            ℹ Request More Information
        </button>
    </div>
</div>

<!-- JavaScript functions moved to main approver.php page -->
<?php else: ?>
<div class="modal-section">
    <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 5px;">
        <strong>This request has already been processed and cannot be modified.</strong>
    </div>
</div>
<?php endif; ?>