<?php
session_start();
date_default_timezone_set('Asia/Manila'); // <-- Add this line!
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

try {
    // DB connection
    $pdo = new PDO("mysql:host=localhost;dbname=budget_database_schema", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get account ID from session
    $username = $_SESSION['username'];
    $stmt = $pdo->prepare("SELECT id FROM account WHERE username_email = ?");
    $stmt->execute([$username]);
    $account_id = $stmt->fetchColumn();

    if (!$account_id) {
        die("Invalid account.");
    }

    // Get form data
    $campus = $_POST['campus'];
    $department = $_POST['department'];
    $fund_account = $_POST['fund_account'];
    $fund_name = $_POST['fund_name'];
    $duration = $_POST['duration'];
    $budget_title = $_POST['budget_title'];
    $description = $_POST['description'];
    $entries = json_decode($_POST['budget_entries'], true);

    // Calculate total
    $total = 0;
    foreach ($entries as $entry) {
        $total += floatval($entry['amount']);
    }

    // Generate request_id
    $dateCode = date("Ymd");
    $randomCode = strtoupper(uniqid());
    $request_id = "BR-$dateCode-" . substr($randomCode, -5);

    // Determine academic year
    $month = date("n");
    $year = date("Y");
    $academic_year = ($month >= 7) ? "$year-" . ($year + 1) : ($year - 1) . "-$year";

    // Insert into budget_request table
    $timestamp = date("Y-m-d H:i:s");
    $status = "pending"; // Changed from "Submitted" to "pending" for workflow

    $insertRequest = $pdo->prepare("
        INSERT INTO budget_request (
            request_id, account_id, timestamp, department_code, campus_code,
            fund_account, fund_name, duration, budget_title, description,
            proposed_budget, status, academic_year
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insertRequest->execute([
        $request_id,
        $account_id,
        $timestamp,
        $department,
        $campus,
        $fund_account,
        $fund_name,
        $duration,
        $budget_title,
        $description,
        $total,
        $status,
        $academic_year
    ]);

    // Initialize approval workflow
    require_once 'workflow_manager.php';
    $workflow = new WorkflowManager($pdo);
    $workflow->initializeWorkflow($request_id);

    // Insert into budget_entries table
   // Insert into budget_entries table (fully populated)
$entryInsert = $pdo->prepare("
    INSERT INTO budget_entries (
        request_id, row_num, month_year, gl_code, budget_category_code,
        budget_description, remarks, amount, monthly_upload, manual_adjustment,
        upload_multiplier, fund_type_code, nature_code, fund_account, fund_name
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$rowNum = 1;
foreach ($entries as $entry) {
    $gl = $entry['gl_code'];
    $desc = $entry['label'];
    $remarks = $entry['remarks'] ?? '';
    $amt = floatval($entry['amount']);

    // For testing purposes
    $month_year = date("Y-m-01");
    $budget_category_code = 'CAT01';
    $monthly_upload = 0;
    $manual_adjustment = 0;
    $upload_multiplier = 1.0;
    $fund_type_code = 'FT01';
    $nature_code = 'NT01';

    $entryInsert->execute([
        $request_id,
        $rowNum,
        $month_year,
        $gl,
        $budget_category_code,
        $desc,
        $remarks,
        $amt,
        $monthly_upload,
        $manual_adjustment,
        $upload_multiplier,
        $fund_type_code,
        $nature_code,
        $fund_account,
        $fund_name
    ]);
    $rowNum++;
}

    // Handle file uploads
    if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
        $uploadDir = 'uploads/';
        
        // Ensure upload directory exists
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $attachmentInsert = $pdo->prepare("
            INSERT INTO attachments (request_id, filename, original_filename, file_size, file_type, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($_FILES['attachments']['name'] as $key => $originalName) {
            if (!empty($originalName)) {
                $tmpName = $_FILES['attachments']['tmp_name'][$key];
                $fileSize = $_FILES['attachments']['size'][$key];
                $fileType = $_FILES['attachments']['type'][$key];
                
                // Generate unique filename to prevent conflicts
                $fileExtension = pathinfo($originalName, PATHINFO_EXTENSION);
                $uniqueFilename = $request_id . '_' . time() . '_' . $key . '.' . $fileExtension;
                $uploadPath = $uploadDir . $uniqueFilename;
                
                // Validate file
                $maxSize = 10 * 1024 * 1024; // 10MB
                $allowedTypes = [
                    'application/pdf', 'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'image/jpeg', 'image/jpg', 'image/png', 'image/gif',
                    'text/plain', 'text/csv'
                ];
                
                if ($fileSize > $maxSize) {
                    throw new Exception("File '$originalName' is too large. Maximum size is 10MB.");
                }
                
                if (!in_array($fileType, $allowedTypes)) {
                    throw new Exception("File '$originalName' type is not allowed.");
                }
                
                // Move uploaded file
                if (move_uploaded_file($tmpName, $uploadPath)) {
                    // Save to database
                    $attachmentInsert->execute([
                        $request_id,
                        $uniqueFilename,
                        $originalName,
                        $fileSize,
                        $fileType,
                        $account_id
                    ]);
                } else {
                    throw new Exception("Failed to upload file: $originalName");
                }
            }
        }
    }

    // Redirect on success
    header("Location: requester.php?submitted=1");
    exit;

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
