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
           d.college, d.budget_deck, c.name as campus_name
    FROM budget_request br 
    LEFT JOIN account a ON br.account_id = a.id 
    LEFT JOIN department d ON br.department_code = d.code 
    LEFT JOIN campus c ON br.campus_code = c.code
    WHERE br.request_id = ?
");
$stmt->execute([$request_id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    exit("Request not found.");
}

// Get budget entries
$stmt = $pdo->prepare("SELECT request_id, row_num, gl_code, budget_description, remarks, amount, approved_amount FROM budget_entries WHERE request_id = ? ORDER BY row_num");
$stmt->execute([$request_id]);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get approval workflow history
require_once 'workflow_manager.php';
$workflow = new WorkflowManager($pdo);
$approval_history = $workflow->getApprovalHistory($request_id);

// Get attachments
$stmt = $pdo->prepare("
    SELECT at.*, a.name as uploader_name 
    FROM attachments at 
    LEFT JOIN account a ON at.uploaded_by = a.id 
    WHERE at.request_id = ? 
    ORDER BY at.upload_timestamp ASC
");
$stmt->execute([$request_id]);
$attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
            <strong>Campus Code:</strong>
            <?php echo htmlspecialchars($request['campus_code'] ?? 'N/A') . ' - ' . htmlspecialchars($request['campus_name'] ?? 'N/A'); ?>
        </div>
        <div class="info-item">
            <strong>Department Code:</strong>
            <?php echo htmlspecialchars($request['department_code']); ?>
        </div>
        <div class="info-item">
            <strong>College:</strong>
            <?php echo htmlspecialchars($request['college'] ?? 'N/A'); ?>
        </div>
        <div class="info-item">
            <strong>Fund Account Code:</strong>
            <?php echo htmlspecialchars($request['fund_account'] ?? 'N/A'); ?>
        </div>
        <div class="info-item">
            <strong>Fund Name:</strong>
            <?php echo htmlspecialchars($request['fund_name'] ?? 'N/A'); ?>
        </div>
        <div class="info-item">
            <strong>Duration:</strong>
            <?php echo htmlspecialchars($request['duration'] ?? 'N/A'); ?>
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

<?php if (!empty($request['budget_title']) || !empty($request['description'])): ?>
<div class="modal-section">
    <h3>Request Details</h3>
    <div class="info-grid">
        <?php if (!empty($request['budget_title'])): ?>
        <div class="info-item" style="grid-column: 1 / -1;">
            <strong>Budget Request Title:</strong>
            <?php echo htmlspecialchars($request['budget_title']); ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($request['description'])): ?>
        <div class="info-item" style="grid-column: 1 / -1;">
            <strong>Description:</strong>
            <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; margin-top: 5px;">
                <?php echo nl2br(htmlspecialchars($request['description'])); ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($entries)): ?>
<div class="modal-section">
    <h3>Budget Line Items</h3>
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;" id="budgetLinesTable">
            <thead>
                <tr style="background-color: #015c2e; color: white;">
                    <th style="padding: 12px; text-align: left; border: 1px solid #ddd;">Row</th>
                    <th style="padding: 12px; text-align: left; border: 1px solid #ddd;">GL Code</th>
                    <th style="padding: 12px; text-align: left; border: 1px solid #ddd;">Description</th>
                    <th style="padding: 12px; text-align: left; border: 1px solid #ddd;">Remarks</th>
                    <th style="padding: 12px; text-align: left; border: 1px solid #ddd;">Proposed Amount</th>
                    <?php
                    // Check if any entry has approved amounts to show the column
                    $has_approved_amounts = false;
                    foreach ($entries as $entry) {
                        if (!is_null($entry['approved_amount']) && $entry['approved_amount'] != $entry['amount']) {
                            $has_approved_amounts = true;
                            break;
                        }
                    }
                    
                    if ($has_approved_amounts || ($_SESSION['role'] === 'vp_finance' && strtolower($request['status']) === 'pending' && $request['current_approval_level'] == $request['total_approval_levels'])):
                    ?>
                    <th style="padding: 12px; text-align: left; border: 1px solid #ddd; background-color: #28a745;">Approved Amount</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $entry): ?>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo $entry['row_num']; ?></td>
                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo htmlspecialchars($entry['gl_code']); ?></td>
                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo htmlspecialchars($entry['budget_description']); ?></td>
                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo !empty($entry['remarks']) ? htmlspecialchars($entry['remarks']) : '<em style="color: #6c757d;">No remarks</em>'; ?></td>
                    <td style="padding: 10px; border: 1px solid #ddd; font-weight: bold;">
                        <span class="clickable-amount" onclick="showDistribution('<?php echo $entry['gl_code']; ?>', '<?php echo addslashes($entry['budget_description']); ?>', <?php echo $entry['amount']; ?>, '<?php echo $request['duration'] ?? 'Annually'; ?>')">
                            ₱<?php echo number_format($entry['amount'], 2); ?>
                        </span>
                    </td>
                    <?php if ($has_approved_amounts || ($_SESSION['role'] === 'vp_finance' && strtolower($request['status']) === 'pending' && $request['current_approval_level'] == $request['total_approval_levels'])): ?>
                    <td style="padding: 10px; border: 1px solid #ddd;">
                        <?php if ($_SESSION['role'] === 'vp_finance' && strtolower($request['status']) === 'pending' && $request['current_approval_level'] == $request['total_approval_levels']): ?>
                        <input type="number" 
                               class="approved-amount-input" 
                               data-row="<?php echo $entry['row_num']; ?>"
                               data-original="<?php echo $entry['amount']; ?>"
                               step="0.01" 
                               min="0" 
                               placeholder="<?php echo number_format($entry['amount'], 2); ?>"
                               value="<?php echo isset($entry['approved_amount']) && !is_null($entry['approved_amount']) && $entry['approved_amount'] != '' ? $entry['approved_amount'] : ''; ?>"
                               style="width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 3px; text-align: right;"
                               onchange="updateTotalApproved()">
                        <small style="color: #6c757d; font-size: 11px;">Leave blank for original amount</small>
                        <?php else: ?>
                        <?php 
                        $approved_amount = !is_null($entry['approved_amount']) ? $entry['approved_amount'] : $entry['amount'];
                        $amount_style = ($approved_amount != $entry['amount']) ? 'color: #28a745; font-weight: bold;' : '';
                        ?>
                        <span style="<?php echo $amount_style; ?>">₱<?php echo number_format($approved_amount, 2); ?></span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <?php if ($has_approved_amounts || ($_SESSION['role'] === 'vp_finance' && strtolower($request['status']) === 'pending' && $request['current_approval_level'] == $request['total_approval_levels'])): ?>
            <tfoot>
                <tr style="background-color: #f8f9fa; font-weight: bold;">
                    <td colspan="4" style="padding: 12px; text-align: right; border: 1px solid #ddd;">TOTAL PROPOSED:</td>
                    <td style="padding: 12px; border: 1px solid #ddd; text-align: right;">₱<?php echo number_format(array_sum(array_column($entries, 'amount')), 2); ?></td>
                    <td style="padding: 12px; border: 1px solid #ddd; text-align: right; color: #28a745;" id="totalApproved">
                        <?php if ($_SESSION['role'] === 'vp_finance' && strtolower($request['status']) === 'pending' && $request['current_approval_level'] == $request['total_approval_levels']): ?>
                        ₱<?php echo number_format(array_sum(array_column($entries, 'amount')), 2); ?>
                        <?php else: ?>
                        <?php 
                        $total_approved = 0;
                        foreach ($entries as $entry) {
                            $approved_amount = !is_null($entry['approved_amount']) ? $entry['approved_amount'] : $entry['amount'];
                            $total_approved += $approved_amount;
                        }
                        ?>
                        ₱<?php echo number_format($total_approved, 2); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php if (!empty($attachments)): ?>
<div class="modal-section">
    <h3>📎 Attachments</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px;">
        <?php foreach ($attachments as $attachment): ?>
        <div style="border: 1px solid #ddd; border-radius: 8px; padding: 15px; background: #f9f9f9;">
            <div style="display: flex; align-items: center; margin-bottom: 10px;">
                <?php
                $fileExtension = strtolower(pathinfo($attachment['original_filename'], PATHINFO_EXTENSION));
                $icon = '📄'; // Default file icon
                
                if (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $icon = '🖼️';
                } elseif ($fileExtension === 'pdf') {
                    $icon = '📕';
                } elseif (in_array($fileExtension, ['doc', 'docx'])) {
                    $icon = '📘';
                } elseif (in_array($fileExtension, ['xls', 'xlsx'])) {
                    $icon = '📊';
                }
                ?>
                <span style="font-size: 24px; margin-right: 10px;"><?php echo $icon; ?></span>
                <div style="flex: 1;">
                    <strong style="color: #015c2e;"><?php echo htmlspecialchars($attachment['original_filename']); ?></strong>
                    <br><small style="color: #666;">
                        <?php echo number_format($attachment['file_size'] / 1024, 1); ?> KB • 
                        Uploaded <?php echo date('M j, Y g:i A', strtotime($attachment['upload_timestamp'])); ?>
                        <?php if ($attachment['uploader_name']): ?>
                            by <?php echo htmlspecialchars($attachment['uploader_name']); ?>
                        <?php endif; ?>
                    </small>
                </div>
            </div>
            <div>
                <a href="download_attachment.php?id=<?php echo $attachment['id']; ?>" 
                   class="btn" 
                   style="background: #006633; color: white; text-decoration: none; padding: 8px 16px; border-radius: 5px; font-size: 14px; display: inline-block;">
                    📥 Download
                </a>
                <?php if (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                <button type="button" 
                        onclick="previewImage('<?php echo htmlspecialchars($attachment['filename']); ?>', '<?php echo htmlspecialchars($attachment['original_filename']); ?>')"
                        style="background: #17a2b8; color: white; border: none; padding: 8px 16px; border-radius: 5px; font-size: 14px; margin-left: 5px; cursor: pointer;">
                    👁️ Preview
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Image Preview Modal -->
<div id="imagePreviewModal" style="display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8);" onclick="closeImagePreview()">
    <div style="display: flex; justify-content: center; align-items: center; height: 100%; padding: 20px;">
        <div style="position: relative; max-width: 90%; max-height: 90%;">
            <img id="previewImage" src="" style="max-width: 100%; max-height: 100%; border-radius: 8px;">
            <div style="position: absolute; top: -40px; left: 0; color: white; font-size: 18px;" id="previewTitle"></div>
            <button onclick="closeImagePreview()" style="position: absolute; top: -40px; right: 0; background: rgba(255,255,255,0.2); color: white; border: none; padding: 8px 12px; border-radius: 5px; cursor: pointer; font-size: 16px;">✕ Close</button>
        </div>
    </div>
</div>

<script>
function previewImage(filename, originalName) {
    document.getElementById('previewImage').src = 'uploads/' + filename;
    document.getElementById('previewTitle').textContent = originalName;
    document.getElementById('imagePreviewModal').style.display = 'block';
}

function closeImagePreview() {
    document.getElementById('imagePreviewModal').style.display = 'none';
}
</script>
<?php endif; ?>

<?php if ($_SESSION['role'] === 'vp_finance' && strtolower($request['status']) === 'pending' && $request['current_approval_level'] == $request['total_approval_levels']): ?>
<script>
function updateTotalApproved() {
    let totalApproved = 0;
    const inputs = document.querySelectorAll('.approved-amount-input');
    
    inputs.forEach(input => {
        const originalAmount = parseFloat(input.dataset.original);
        const approvedAmount = input.value ? parseFloat(input.value) : originalAmount;
        totalApproved += approvedAmount;
    });
    
    document.getElementById('totalApproved').textContent = '₱' + totalApproved.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// Initialize total on page load
document.addEventListener('DOMContentLoaded', function() {
    updateTotalApproved();
});
</script>
<?php endif; ?>

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
    <?php if ($_SESSION['role'] === 'vp_finance' && strtolower($request['status']) === 'approved'): ?>
        <!-- Amendment History Section -->
        <?php
        // Get existing amendments for this request
        $stmt = $pdo->prepare("
            SELECT ba.*, a.name as created_by_name, ap.name as approved_by_name
            FROM budget_amendments ba
            LEFT JOIN account a ON ba.created_by = a.id
            LEFT JOIN account ap ON ba.approved_by = ap.id
            WHERE ba.request_id = ?
            ORDER BY ba.amendment_number DESC
        ");
        $stmt->execute([$request_id]);
        $amendments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate correct baseline budget for each amendment
        if (!empty($amendments)) {
            // First, get the initially approved budget (sum of approved amounts from budget_entries)
            $initially_approved_budget = 0;
            foreach ($entries as $entry) {
                $approved_amount = !is_null($entry['approved_amount']) ? $entry['approved_amount'] : $entry['amount'];
                $initially_approved_budget += $approved_amount;
            }
            
            // Sort amendments by amendment_number ASC to process them in chronological order
            $chronological_amendments = $amendments;
            usort($chronological_amendments, function($a, $b) {
                return $a['amendment_number'] - $b['amendment_number'];
            });
            
            // Calculate the correct baseline for each amendment
            $current_budget = $initially_approved_budget;
            foreach ($chronological_amendments as $key => $amendment) {
                // Find the amendment in the original DESC order array and update it
                foreach ($amendments as &$orig_amendment) {
                    if ($orig_amendment['amendment_number'] == $amendment['amendment_number']) {
                        $orig_amendment['calculated_original_budget'] = $current_budget;
                        break;
                    }
                }
                // Update current budget for next iteration
                $current_budget = $amendment['amended_total_budget'];
            }
        }
        ?>

        <?php if (!empty($amendments)): ?>
        <div class="modal-section">
            <h3 style="color: #015c2e; margin-bottom: 15px;">📋 Amendment History</h3>
            <div style="max-height: 600px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px;">
                <?php foreach ($amendments as $amendment): ?>
                <div style="padding: 15px; border-bottom: 1px solid #e9ecef; background: <?php echo $amendment['status'] === 'approved' ? '#d4edda' : ($amendment['status'] === 'rejected' ? '#f8d7da' : '#fff3cd'); ?>;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                        <div>
                            <strong style="color: #015c2e;">Amendment #<?php echo $amendment['amendment_number']; ?></strong>
                            <span style="margin-left: 10px; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; color: white; background: <?php echo $amendment['status'] === 'approved' ? '#28a745' : ($amendment['status'] === 'rejected' ? '#dc3545' : '#ffc107'); ?>;">
                                <?php echo strtoupper($amendment['status']); ?>
                            </span>
                        </div>
                        <small style="color: #6c757d;"><?php echo date('M j, Y g:i A', strtotime($amendment['created_timestamp'])); ?></small>
                    </div>
                    
                    <div style="margin-bottom: 10px;">
                        <strong><?php echo htmlspecialchars($amendment['amendment_title']); ?></strong>
                        <span style="margin-left: 10px; padding: 2px 6px; background: #e9ecef; border-radius: 3px; font-size: 11px;">
                            <?php echo ucfirst(str_replace('_', ' ', $amendment['amendment_type'])); ?>
                        </span>
                    </div>
                    
                    <div style="background: white; padding: 10px; border-radius: 4px; margin-bottom: 10px;">
                        <p style="margin: 0; font-style: italic; color: #495057;"><?php echo nl2br(htmlspecialchars($amendment['amendment_reason'])); ?></p>
                    </div>
                    
                    <?php if ($amendment['amendment_type'] === 'budget_change' && !is_null($amendment['original_total_budget']) && !is_null($amendment['amended_total_budget'])): ?>
                    <div style="display: flex; gap: 20px; font-size: 14px;">
                        <div>
                            <strong>Original Budget:</strong> ₱<?php 
                            $baseline_budget = isset($amendment['calculated_original_budget']) ? $amendment['calculated_original_budget'] : $amendment['original_total_budget'];
                            echo number_format($baseline_budget, 2); 
                            ?>
                        </div>
                        <div>
                            <strong>Amended Budget:</strong> ₱<?php echo number_format($amendment['amended_total_budget'], 2); ?>
                        </div>
                        <div style="color: <?php echo $amendment['amended_total_budget'] > $baseline_budget ? '#28a745' : '#dc3545'; ?>;">
                            <strong>Change:</strong> 
                            <?php 
                            $change = $amendment['amended_total_budget'] - $baseline_budget;
                            echo ($change >= 0 ? '+' : '') . '₱' . number_format($change, 2);
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php
                    // Add detailed amendment information similar to requester side
                    if (!empty($amendment['amendment_data'])) {
                        $amendment_data = json_decode($amendment['amendment_data'], true);
                        if ($amendment_data) {
                    ?>
                    <div style="margin-bottom: 15px;">
                        <h5 style="margin: 0 0 8px 0; color: #495057; font-size: 14px;">🔍 Detailed Changes:</h5>
                        
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; border: 1px solid #dee2e6;">
                        
                        <?php
                        // Handle different amendment types with detailed breakdown
                        switch($amendment['amendment_type']) {
                            case 'budget_change':
                                if (isset($amendment_data['budget_entries'])) {
                                    echo "<strong>Modified Budget Items:</strong><br>";
                                    foreach ($amendment_data['budget_entries'] as $entry) {
                                        $entry_change = floatval($entry['new_amount']) - floatval($entry['original_amount']);
                                        $entry_change_color = $entry_change >= 0 ? '#28a745' : '#dc3545';
                                        $entry_change_icon = $entry_change >= 0 ? '📈' : '📉';
                                        echo "<div style='margin: 8px 0; padding: 12px; background: white; border-radius: 4px; border-left: 4px solid " . ($entry_change >= 0 ? '#28a745' : '#dc3545') . ";'>";
                                        echo "<strong style='color: #015c2e;'>{$entry['gl_code']}</strong> - {$entry['description']}<br>";
                                        echo "<small style='color: #6c757d;'>Original: <strong>₱" . number_format($entry['original_amount'], 2) . "</strong> → New: <strong>₱" . number_format($entry['new_amount'], 2) . "</strong></small><br>";
                                        echo "<span style='color: $entry_change_color; font-weight: bold; font-size: 13px;'>$entry_change_icon " . ($entry_change >= 0 ? '+' : '') . "₱" . number_format($entry_change, 2) . "</span>";
                                        echo "</div>";
                                    }
                                }
                                break;
                                
                            case 'description_change':
                                if (isset($amendment_data['new_budget_title'])) {
                                    echo "<div style='margin-bottom: 12px; padding: 10px; background: white; border-radius: 4px; border-left: 4px solid #007bff;'>";
                                    echo "<strong style='color: #015c2e;'>📝 New Budget Title:</strong><br>";
                                    echo "<em style='color: #495057;'>\"" . htmlspecialchars($amendment_data['new_budget_title']) . "\"</em>";
                                    echo "</div>";
                                }
                                if (isset($amendment_data['new_description'])) {
                                    echo "<div style='margin-bottom: 12px; padding: 10px; background: white; border-radius: 4px; border-left: 4px solid #007bff;'>";
                                    echo "<strong style='color: #015c2e;'>📄 New Description:</strong><br>";
                                    echo "<em style='color: #495057;'>\"" . nl2br(htmlspecialchars($amendment_data['new_description'])) . "\"</em>";
                                    echo "</div>";
                                }
                                break;
                                
                            case 'timeline_change':
                                if (isset($amendment_data['new_duration'])) {
                                    echo "<div style='margin-bottom: 12px; padding: 10px; background: white; border-radius: 4px; border-left: 4px solid #ffc107;'>";
                                    echo "<strong style='color: #015c2e;'>📅 New Duration:</strong> " . htmlspecialchars($amendment_data['new_duration']);
                                    echo "</div>";
                                }
                                if (isset($amendment_data['new_academic_year'])) {
                                    echo "<div style='margin-bottom: 12px; padding: 10px; background: white; border-radius: 4px; border-left: 4px solid #ffc107;'>";
                                    echo "<strong style='color: #015c2e;'>🎓 New Academic Year:</strong> " . htmlspecialchars($amendment_data['new_academic_year']);
                                    echo "</div>";
                                }
                                break;
                                
                            case 'general_modification':
                                if (isset($amendment_data['new_fund_account'])) {
                                    echo "<div style='margin-bottom: 12px; padding: 10px; background: white; border-radius: 4px; border-left: 4px solid #17a2b8;'>";
                                    echo "<strong style='color: #015c2e;'>💰 New Fund Account:</strong> " . htmlspecialchars($amendment_data['new_fund_account']);
                                    echo "</div>";
                                }
                                if (isset($amendment_data['new_fund_name'])) {
                                    echo "<div style='margin-bottom: 12px; padding: 10px; background: white; border-radius: 4px; border-left: 4px solid #17a2b8;'>";
                                    echo "<strong style='color: #015c2e;'>🏷️ New Fund Name:</strong> " . htmlspecialchars($amendment_data['new_fund_name']);
                                    echo "</div>";
                                }
                                if (isset($amendment_data['general_modification_notes'])) {
                                    echo "<div style='margin-bottom: 12px; padding: 10px; background: white; border-radius: 4px; border-left: 4px solid #17a2b8;'>";
                                    echo "<strong style='color: #015c2e;'>📝 Additional Notes:</strong><br>";
                                    echo "<em style='color: #495057;'>\"" . nl2br(htmlspecialchars($amendment_data['general_modification_notes'])) . "\"</em>";
                                    echo "</div>";
                                }
                                break;
                        }
                        ?>
                        
                        </div>
                    </div>
                    <?php
                        }
                    }
                    ?>
                    
                    <div style="margin-top: 10px; font-size: 12px; color: #6c757d;">
                        Created by: <?php echo htmlspecialchars($amendment['created_by_name']); ?>
                        <?php if ($amendment['approved_by_name']): ?>
                            | Processed by: <?php echo htmlspecialchars($amendment['approved_by_name']); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Create New Amendment Section -->
        <div class="modal-section">
            <h3 style="color: #015c2e; margin-bottom: 15px;">✏️ Create New Amendment</h3>
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 2px dashed #015c2e;">
                <button 
                    onclick="createAmendmentForRequest('<?php echo $request['request_id']; ?>')" 
                    style="background: linear-gradient(135deg, #015c2e 0%, #28a745 100%); color: white; border: none; padding: 15px 30px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(1, 92, 46, 0.3);"
                    onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 25px rgba(1, 92, 46, 0.4)'"
                    onmouseout="this.style.transform='translateY(0px)'; this.style.boxShadow='0 4px 15px rgba(1, 92, 46, 0.3)'">
                    🚀 Create Amendment to Approved Request
                </button>
                <p style="margin: 10px 0 0 0; color: #6c757d; font-size: 14px;">
                    <strong>Note:</strong> Amendments allow you to modify approved requests while maintaining full audit trail. 
                    Original request data is preserved, and all changes are tracked separately.
                </p>
            </div>
        </div>


    <?php else: ?>
        <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 5px;">
            <strong>This request has already been processed and cannot be modified.</strong>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>