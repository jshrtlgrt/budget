<?php
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'requester') {
    header("Location: login.php");
    exit;
}

if (!isset($_POST['request_id']) || empty($_POST['request_id'])) {
    header("Location: requester.php?error=" . urlencode("Invalid request ID"));
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=budget_database_schema", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$request_id = $_POST['request_id'];
$username = $_SESSION['username'];

try {
    $pdo->beginTransaction();
    
    // Get account ID
    $stmt = $pdo->prepare("SELECT id FROM account WHERE username_email = ?");
    $stmt->execute([$username]);
    $account_id = $stmt->fetchColumn();
    
    if (!$account_id) {
        throw new Exception("User account not found");
    }
    
    // Verify that this request belongs to the current user
    $stmt = $pdo->prepare("SELECT * FROM budget_request WHERE request_id = ? AND account_id = ?");
    $stmt->execute([$request_id, $account_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        throw new Exception("Request not found or you don't have permission to delete it");
    }
    
    // Check if the request has been processed by level 1 (department head)
    $stmt = $pdo->prepare("
        SELECT * FROM approval_progress 
        WHERE request_id = ? AND approval_level = 1 
        AND status IN ('approved', 'rejected', 'request_info')
    ");
    $stmt->execute([$request_id]);
    $level1_processing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($level1_processing) {
        throw new Exception("Cannot delete request: It has already been processed by the Department Head. Once a request has been reviewed by Level 1, it cannot be deleted.");
    }
    
    // Check if request is still in initial pending state
    if ($request['status'] !== 'pending') {
        throw new Exception("Cannot delete request: Request status is not pending");
    }
    
    // Delete related records first (due to foreign key constraints)
    
    // Delete attachments files and records
    $stmt = $pdo->prepare("SELECT filename FROM attachments WHERE request_id = ?");
    $stmt->execute([$request_id]);
    $attachments = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($attachments as $filename) {
        $filepath = "uploads/" . $filename;
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }
    
    $stmt = $pdo->prepare("DELETE FROM attachments WHERE request_id = ?");
    $stmt->execute([$request_id]);
    
    // Delete approval progress records
    $stmt = $pdo->prepare("DELETE FROM approval_progress WHERE request_id = ?");
    $stmt->execute([$request_id]);
    
    // Delete budget entries
    $stmt = $pdo->prepare("DELETE FROM budget_entries WHERE request_id = ?");
    $stmt->execute([$request_id]);
    
    // Finally, delete the main request
    $stmt = $pdo->prepare("DELETE FROM budget_request WHERE request_id = ? AND account_id = ?");
    $stmt->execute([$request_id, $account_id]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception("Failed to delete request");
    }
    
    $pdo->commit();
    header("Location: requester.php?success=" . urlencode("Budget request $request_id has been deleted successfully"));
    
} catch (Exception $e) {
    $pdo->rollBack();
    header("Location: requester.php?error=" . urlencode($e->getMessage()));
}
?>