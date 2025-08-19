<?php
session_start();

// Only VP Finance can create amendments
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'vp_finance') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Only VP Finance can create amendments.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=budget_database_schema", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $pdo->beginTransaction();
    
    // Get VP Finance user ID
    $stmt = $pdo->prepare("SELECT id FROM account WHERE username_email = ?");
    $stmt->execute([$_SESSION['username']]);
    $vp_finance_id = $stmt->fetchColumn();
    
    if (!$vp_finance_id) {
        throw new Exception("VP Finance account not found");
    }
    
    // Validate inputs
    $request_id = trim($_POST['request_id'] ?? '');
    $amendment_type = trim($_POST['amendment_type'] ?? '');
    $amendment_title = trim($_POST['amendment_title'] ?? '');
    $amendment_reason = trim($_POST['amendment_reason'] ?? '');
    
    if (empty($request_id) || empty($amendment_type) || empty($amendment_title) || empty($amendment_reason)) {
        throw new Exception("All fields are required");
    }
    
    // Validate amendment type
    $valid_types = ['budget_change', 'description_change', 'timeline_change', 'general_modification'];
    if (!in_array($amendment_type, $valid_types)) {
        throw new Exception("Invalid amendment type");
    }
    
    // Check if request exists and is approved
    $stmt = $pdo->prepare("SELECT * FROM budget_request WHERE request_id = ? AND status = 'approved'");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        throw new Exception("Request not found or not approved");
    }
    
    // Get the most recent approved budget amount (either original or from latest amendment)
    $stmt = $pdo->prepare("
        SELECT amended_total_budget 
        FROM budget_amendments 
        WHERE request_id = ? AND status = 'approved' 
        ORDER BY amendment_number DESC 
        LIMIT 1
    ");
    $stmt->execute([$request_id]);
    $latest_approved_budget = $stmt->fetchColumn();
    
    // Use latest approved budget if available, otherwise use original proposed budget
    $current_approved_budget = $latest_approved_budget ? floatval($latest_approved_budget) : floatval($request['proposed_budget']);
    
    // Parse additional amendment data based on type (AFTER we have $request)
    $additional_data = [];
    $new_total_budget = $current_approved_budget; // Default to current approved amount
    
    switch ($amendment_type) {
        case 'budget_change':
            if (isset($_POST['budget_entries']) && is_array($_POST['budget_entries'])) {
                $budget_entries = $_POST['budget_entries'];
                $new_total_budget = 0;
                
                foreach ($budget_entries as $row_num => $entry_data) {
                    $new_amount = floatval($entry_data['new_amount'] ?? 0);
                    $new_total_budget += $new_amount;
                    
                    $additional_data['budget_entries'][$row_num] = [
                        'gl_code' => $entry_data['gl_code'] ?? '',
                        'description' => $entry_data['description'] ?? '',
                        'original_amount' => floatval($entry_data['original_amount'] ?? 0),
                        'new_amount' => $new_amount
                    ];
                }
            }
            break;
            
        case 'description_change':
            if (!empty($_POST['new_budget_title'])) {
                $additional_data['new_budget_title'] = trim($_POST['new_budget_title']);
            }
            if (!empty($_POST['new_description'])) {
                $additional_data['new_description'] = trim($_POST['new_description']);
            }
            break;
            
        case 'timeline_change':
            if (!empty($_POST['new_duration'])) {
                $additional_data['new_duration'] = trim($_POST['new_duration']);
            }
            if (!empty($_POST['new_academic_year'])) {
                $additional_data['new_academic_year'] = trim($_POST['new_academic_year']);
            }
            break;
            
        case 'general_modification':
            if (!empty($_POST['new_fund_account'])) {
                $additional_data['new_fund_account'] = trim($_POST['new_fund_account']);
            }
            if (!empty($_POST['new_fund_name'])) {
                $additional_data['new_fund_name'] = trim($_POST['new_fund_name']);
            }
            if (!empty($_POST['general_modification_notes'])) {
                $additional_data['general_modification_notes'] = trim($_POST['general_modification_notes']);
            }
            break;
    }
    
    // Get next amendment number for this request
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(amendment_number), 0) + 1 as next_number FROM budget_amendments WHERE request_id = ?");
    $stmt->execute([$request_id]);
    $next_amendment_number = $stmt->fetchColumn();
    
    // Create the amendment record
    $stmt = $pdo->prepare("
        INSERT INTO budget_amendments (
            request_id, amendment_number, created_by, amendment_type, 
            amendment_title, amendment_reason, original_total_budget, 
            amended_total_budget, status, approved_by, approved_timestamp, approval_comments
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, NOW(), ?)
    ");
    
    $approval_comment = "Auto-approved by VP Finance as administrative amendment";
    
    $stmt->execute([
        $request_id,
        $next_amendment_number,
        $vp_finance_id,
        $amendment_type,
        $amendment_title,
        $amendment_reason,
        $current_approved_budget, // current approved budget (from latest amendment or original)
        $new_total_budget, // amended budget (calculated above)
        $vp_finance_id, // auto-approved by creator
        $approval_comment
    ]);
    
    $amendment_id = $pdo->lastInsertId();
    
    // Store detailed amendment entries if this is a budget change
    if ($amendment_type === 'budget_change' && !empty($additional_data['budget_entries'])) {
        $stmt = $pdo->prepare("
            INSERT INTO budget_amendment_entries (
                amendment_id, row_num, gl_code, budget_description, 
                original_amount, amended_amount
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($additional_data['budget_entries'] as $row_num => $entry) {
            $stmt->execute([
                $amendment_id,
                $row_num,
                $entry['gl_code'],
                $entry['description'],
                $entry['original_amount'],
                $entry['new_amount']
            ]);
        }
    }
    
    // Store other amendment details in a JSON field (check if column exists first)
    if (!empty($additional_data)) {
        try {
            // Check if amendment_data column exists
            $stmt = $pdo->prepare("SHOW COLUMNS FROM budget_amendments LIKE 'amendment_data'");
            $stmt->execute();
            $columnExists = $stmt->fetchColumn();
            
            if ($columnExists) {
                $stmt = $pdo->prepare("
                    UPDATE budget_amendments 
                    SET amendment_data = ? 
                    WHERE amendment_id = ?
                ");
                $stmt->execute([json_encode($additional_data), $amendment_id]);
            } else {
                // Column doesn't exist, log the data for debugging
                error_log("Amendment data not stored (column missing): " . json_encode($additional_data));
            }
        } catch (Exception $e) {
            // If there's any issue with the column, just continue without storing JSON data
            error_log("Amendment data storage failed: " . $e->getMessage());
        }
    }
    
    // Create history record
    $stmt = $pdo->prepare("
        INSERT INTO history (request_id, timestamp, action, account_id) 
        VALUES (?, NOW(), ?, ?)
    ");
    
    $action = "Amendment #$next_amendment_number created: $amendment_title (Type: " . ucfirst(str_replace('_', ' ', $amendment_type)) . ")";
    $stmt->execute([$request_id, $action, $vp_finance_id]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Amendment created successfully',
        'amendment_id' => $amendment_id,
        'amendment_number' => $next_amendment_number
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    $error_message = $e->getMessage();
    $error_details = [
        'error' => $error_message,
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ];
    
    error_log("Amendment creation error: " . json_encode($error_details));
    
    // Return more helpful error message to user
    if (strpos($error_message, 'amendment_data') !== false) {
        echo json_encode([
            'success' => false, 
            'message' => 'Database column missing. Please run fix_amendment_column.php to update the database.',
            'technical_error' => $error_message
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => $error_message,
            'debug_info' => 'Check browser console for more details'
        ]);
    }
}
?>