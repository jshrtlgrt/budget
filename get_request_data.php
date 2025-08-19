<?php
session_start();

// Only VP Finance can access this
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'vp_finance') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['request_id'])) {
    echo json_encode(['success' => false, 'message' => 'Request ID required']);
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=budget_database_schema", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $request_id = $_GET['request_id'];
    
    // Get main request data
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
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit;
    }
    
    // Get budget entries
    $stmt = $pdo->prepare("
        SELECT request_id, row_num, gl_code, budget_description, remarks, amount, approved_amount 
        FROM budget_entries 
        WHERE request_id = ? 
        ORDER BY row_num
    ");
    $stmt->execute([$request_id]);
    $budget_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add budget entries to request data
    $request['budget_entries'] = $budget_entries;
    
    echo json_encode([
        'success' => true,
        'request' => $request
    ]);
    
} catch (Exception $e) {
    error_log("Get request data error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>