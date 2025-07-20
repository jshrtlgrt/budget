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
    $department = $_POST['department'];
    $fund_account = $_POST['fund_account'];
    $fund_name = $_POST['fund_name'];
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
    $status = "Submitted";

    $insertRequest = $pdo->prepare("
        INSERT INTO budget_request (
            request_id, account_id, timestamp, department_code,
            proposed_budget, status, academic_year
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $insertRequest->execute([
        $request_id,
        $account_id,
        $timestamp,
        $department,
        $total,
        $status,
        $academic_year
    ]);

    // Insert into budget_entries table
   // Insert into budget_entries table (fully populated)
$entryInsert = $pdo->prepare("
    INSERT INTO budget_entries (
        request_id, row_num, month_year, gl_code, budget_category_code,
        budget_description, amount, monthly_upload, manual_adjustment,
        upload_multiplier, fund_type_code, nature_code, fund_account, fund_name
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$rowNum = 1;
foreach ($entries as $entry) {
    $gl = $entry['gl_code'];
    $desc = $entry['label'];
    $amt = floatval($entry['amount']);

    // For testing purposes, we fill the rest with defaults
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


    // Redirect on success
    header("Location: requester.php?submitted=1");
    exit;

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
