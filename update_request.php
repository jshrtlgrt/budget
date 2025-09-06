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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: requester.php");
    exit;
}

$request_id = $_POST['request_id'] ?? '';
if (empty($request_id)) {
    header("Location: requester.php?error=Invalid request ID");
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

// Validate required fields
$required_fields = ['budget_title', 'academic_year', 'campus_code', 'department_code', 'fund_account', 'fund_name', 'duration'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        header("Location: edit_request.php?request_id=$request_id&error=Missing required field: $field");
        exit;
    }
}

// Validate budget entries
$gl_codes = $_POST['gl_code'] ?? [];
$budget_descriptions = $_POST['budget_description'] ?? [];
$remarks = $_POST['remarks'] ?? [];
$amounts = $_POST['amount'] ?? [];

// Debug log the received data
error_log("Debug - Received POST data: " . json_encode($_POST));

if (empty($gl_codes) || count($gl_codes) === 0) {
    header("Location: edit_request.php?request_id=$request_id&error=At least one budget entry is required");
    exit;
}

$total_amount = 0;
for ($i = 0; $i < count($gl_codes); $i++) {
    if (empty($gl_codes[$i]) || empty($budget_descriptions[$i]) || empty($amounts[$i])) {
        error_log("Debug - Missing data at index $i: GL Code: '{$gl_codes[$i]}', Description: '{$budget_descriptions[$i]}', Amount: '{$amounts[$i]}'");
        header("Location: edit_request.php?request_id=$request_id&error=All budget entries must have GL Code, Description, and Amount");
        exit;
    }
    
    if (!is_numeric($amounts[$i]) || floatval($amounts[$i]) <= 0) {
        error_log("Debug - Invalid amount at index $i: '{$amounts[$i]}'");
        header("Location: edit_request.php?request_id=$request_id&error=All amounts must be valid positive numbers");
        exit;
    }
    
    $total_amount += floatval($amounts[$i]);
}

if ($total_amount <= 0) {
    header("Location: edit_request.php?request_id=$request_id&error=Total budget amount must be greater than 0");
    exit;
}

require_once 'workflow_manager.php';
$workflow = new WorkflowManager($pdo);

$transaction_started = false;

try {
    // Get the approval level that requested more information
    // First, let's debug what we have in the database
    $stmt = $pdo->prepare("
        SELECT approval_level, approver_id, status, comments 
        FROM approval_progress 
        WHERE request_id = ?
        ORDER BY approval_level DESC
    ");
    $stmt->execute([$request_id]);
    $all_progress = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Look for the level that requested info (could be 'request_info' status)
    $info_request_level = null;
    foreach ($all_progress as $progress) {
        if ($progress['status'] === 'request_info') {
            $info_request_level = $progress;
            break;
        }
    }
    
    // If we can't find request_info status, try to find the current approval level 
    // for requests with more_info_requested status
    if (!$info_request_level) {
        $stmt = $pdo->prepare("
            SELECT current_approval_level 
            FROM budget_request 
            WHERE request_id = ? AND status = 'more_info_requested'
        ");
        $stmt->execute([$request_id]);
        $current_level = $stmt->fetchColumn();
        
        if ($current_level) {
            // Find the approver at this level
            $stmt = $pdo->prepare("
                SELECT approval_level, approver_id, status, comments
                FROM approval_progress 
                WHERE request_id = ? AND approval_level = ?
            ");
            $stmt->execute([$request_id, $current_level]);
            $info_request_level = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    
    if (!$info_request_level) {
        // Log debug information
        error_log("Debug - Request ID: $request_id");
        error_log("Debug - All approval progress: " . json_encode($all_progress));
        throw new Exception("Could not find the approval level that requested information. This might be a workflow state issue.");
    }
    
    // Start transaction only after validation
    $pdo->beginTransaction();
    $transaction_started = true;
    
    // Update the budget request
    $stmt = $pdo->prepare("
        UPDATE budget_request SET
            budget_title = ?,
            academic_year = ?,
            campus_code = ?,
            department_code = ?,
            fund_account = ?,
            fund_name = ?,
            duration = ?,
            description = ?,
            proposed_budget = ?,
            status = 'pending',
            current_approval_level = ?,
            timestamp = NOW()
        WHERE request_id = ?
    ");
    
    $stmt->execute([
        $_POST['budget_title'],
        $_POST['academic_year'],
        $_POST['campus_code'],
        $_POST['department_code'],
        $_POST['fund_account'],
        $_POST['fund_name'],
        $_POST['duration'],
        $_POST['description'] ?? '',
        $total_amount,
        $info_request_level['approval_level'], // Reset to the level that requested info
        $request_id
    ]);
    
    // Delete old budget entries
    $stmt = $pdo->prepare("DELETE FROM budget_entries WHERE request_id = ?");
    $stmt->execute([$request_id]);
    
    // Insert new budget entries
    for ($i = 0; $i < count($gl_codes); $i++) {
        $stmt = $pdo->prepare("
            INSERT INTO budget_entries (request_id, row_num, gl_code, budget_description, remarks, amount) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $request_id,
            $i + 1,
            $gl_codes[$i],
            $budget_descriptions[$i],
            $remarks[$i] ?? '',
            floatval($amounts[$i])
        ]);
    }
    
    // Resume the approval workflow from the level that requested info
    if (!$workflow->resumeWorkflowAfterInfoProvided($request_id, $info_request_level['approval_level'])) {
        throw new Exception("Failed to resume workflow");
    }
    
    // Add history entry
    $stmt = $pdo->prepare("
        INSERT INTO history (request_id, timestamp, action, account_id) 
        VALUES (?, NOW(), 'Request updated and resubmitted with additional information', ?)
    ");
    $stmt->execute([$request_id, $account_id]);
    
    $pdo->commit();
    
    header("Location: requester.php?success=Request updated and resubmitted successfully");
    
} catch (Exception $e) {
    if ($transaction_started) {
        $pdo->rollBack();
    }
    error_log("Request update failed: " . $e->getMessage());
    header("Location: edit_request.php?request_id=$request_id&error=Failed to update request: " . urlencode($e->getMessage()));
}
?>