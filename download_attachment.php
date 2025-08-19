<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("HTTP/1.1 403 Forbidden");
    exit("Access denied: Please log in first.");
}

// Validate attachment ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("HTTP/1.1 400 Bad Request");
    exit("Invalid attachment ID.");
}

$attachment_id = (int)$_GET['id'];

try {
    // Database connection
    $pdo = new PDO("mysql:host=localhost;dbname=budget_database_schema", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get attachment details
    $stmt = $pdo->prepare("
        SELECT at.*, br.account_id as request_owner
        FROM attachments at
        LEFT JOIN budget_request br ON at.request_id = br.request_id
        WHERE at.id = ?
    ");
    $stmt->execute([$attachment_id]);
    $attachment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$attachment) {
        header("HTTP/1.1 404 Not Found");
        exit("Attachment not found.");
    }
    
    // Check if user has permission to download this attachment
    // Allow if user is:
    // 1. The uploader of the attachment
    // 2. The owner of the budget request
    // 3. An approver (department_head, dean, vp_finance)
    
    $username = $_SESSION['username'];
    $stmt = $pdo->prepare("SELECT id, role FROM account WHERE username_email = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $allowedRoles = ['department_head', 'dean', 'vp_finance', 'approver'];
    $hasAccess = false;
    
    if ($user['id'] == $attachment['uploaded_by'] || 
        $user['id'] == $attachment['request_owner'] ||
        in_array($user['role'], $allowedRoles)) {
        $hasAccess = true;
    }
    
    if (!$hasAccess) {
        header("HTTP/1.1 403 Forbidden");
        exit("Access denied: You don't have permission to download this file.");
    }
    
    // Build file path
    $filePath = 'uploads/' . $attachment['filename'];
    
    // Check if file exists
    if (!file_exists($filePath)) {
        header("HTTP/1.1 404 Not Found");
        exit("File not found on server.");
    }
    
    // Set headers for download
    header('Content-Type: ' . $attachment['file_type']);
    header('Content-Length: ' . $attachment['file_size']);
    header('Content-Disposition: attachment; filename="' . $attachment['original_filename'] . '"');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    // Output file
    readfile($filePath);
    exit;
    
} catch (PDOException $e) {
    header("HTTP/1.1 500 Internal Server Error");
    exit("Database error: " . $e->getMessage());
} catch (Exception $e) {
    header("HTTP/1.1 500 Internal Server Error");
    exit("Error: " . $e->getMessage());
}
?>