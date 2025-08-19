<?php
session_start();
header('Content-Type: application/json');

// Allow multiple approver roles
$allowed_roles = ['approver', 'department_head', 'dean', 'vp_finance'];
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], $allowed_roles)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=budget_database_schema", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

require_once 'workflow_manager.php';
$workflow = new WorkflowManager($pdo);

$request_id = $_POST['request_id'] ?? '';
$action = $_POST['action'] ?? '';
$comments = $_POST['comments'] ?? '';
$approved_amounts = $_POST['approved_amounts'] ?? [];

if (empty($request_id) || empty($action)) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

try {
    // Get approver ID
    $stmt = $pdo->prepare("SELECT id FROM account WHERE username_email = ?");
    $stmt->execute([$_SESSION['username']]);
    $approver_id = $stmt->fetchColumn();
    
    if (!$approver_id) {
        throw new Exception('Approver not found');
    }
    
    // Check if request exists
    $stmt = $pdo->prepare("
        SELECT br.*, ap.status as approval_status 
        FROM budget_request br
        LEFT JOIN approval_progress ap ON br.request_id = ap.request_id 
            AND ap.approval_level = br.current_approval_level 
            AND ap.approver_id = ?
        WHERE br.request_id = ?
    ");
    $stmt->execute([$approver_id, $request_id]);
    $request_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request_data) {
        throw new Exception('Request not found or you are not authorized to approve it');
    }
    
    // Check if workflow has been initialized
    if (is_null($request_data['current_approval_level'])) {
        // Initialize workflow for existing requests
        $workflow->initializeWorkflow($request_id);
    }
    
    // Verify approver can act on this request at current level
    if ($request_data['approval_status'] !== 'pending') {
        throw new Exception('This request is not pending your approval');
    }
    
    if ($request_data['workflow_complete']) {
        throw new Exception('Request workflow has already been completed');
    }
    
    // If VP Finance is approving and provided approved amounts, save them
    if ($_SESSION['role'] === 'vp_finance' && $action === 'approve' && !empty($approved_amounts)) {
        // Update approved amounts for each budget entry
        foreach ($approved_amounts as $row_num => $approved_amount) {
            if (!empty($approved_amount) && is_numeric($approved_amount) && $approved_amount > 0) {
                $stmt = $pdo->prepare("
                    UPDATE budget_entries 
                    SET approved_amount = ? 
                    WHERE request_id = ? AND row_num = ?
                ");
                $stmt->execute([$approved_amount, $request_id, $row_num]);
            }
        }
        
        // Update the total approved budget in budget_request
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(CASE WHEN approved_amount IS NOT NULL THEN approved_amount ELSE amount END), 0) as total_approved
            FROM budget_entries 
            WHERE request_id = ?
        ");
        $stmt->execute([$request_id]);
        $total_approved = $stmt->fetchColumn();
        
        if ($total_approved > 0) {
            $stmt = $pdo->prepare("
                UPDATE budget_request 
                SET approved_budget = ? 
                WHERE request_id = ?
            ");
            $stmt->execute([$total_approved, $request_id]);
        }
    }
    
    // Process the approval through workflow
    $success = $workflow->processApproval($request_id, $approver_id, $action, $comments);
    
    if (!$success) {
        throw new Exception('Failed to process approval');
    }
    
    // Get updated status for response message
    $stmt = $pdo->prepare("
        SELECT status, current_approval_level, total_approval_levels, workflow_complete 
        FROM budget_request 
        WHERE request_id = ?
    ");
    $stmt->execute([$request_id]);
    $updated_request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $success_messages = [
        'approve' => $updated_request['workflow_complete'] 
            ? 'Request has been fully approved and completed' 
            : 'Request approved and forwarded to next approval level (' . $updated_request['current_approval_level'] . '/' . $updated_request['total_approval_levels'] . ')',
        'reject' => 'Request has been rejected',
        'request_info' => 'Information request sent to requester'
    ];
    
    echo json_encode([
        'success' => true, 
        'message' => $success_messages[$action] ?? 'Action completed',
        'workflow_status' => [
            'current_level' => $updated_request['current_approval_level'],
            'total_levels' => $updated_request['total_approval_levels'],
            'complete' => $updated_request['workflow_complete'],
            'status' => $updated_request['status']
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>