<?php
if (!isset($_GET['request_id'])) {
    exit("Invalid request.");
}

$pdo = new PDO("mysql:host=localhost;dbname=budget_database_schema", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$request_id = $_GET['request_id'];

// Get request details
$stmt = $pdo->prepare("SELECT br.*, a.name as requester_name, c.name as campus_name FROM budget_request br LEFT JOIN account a ON br.account_id = a.id LEFT JOIN campus c ON br.campus_code = c.code WHERE br.request_id = ?");
$stmt->execute([$request_id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    echo "Request not found.";
    exit;
}

// Get budget entries
$stmt = $pdo->prepare("SELECT * FROM budget_entries WHERE request_id = ?");
$stmt->execute([$request_id]);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get approval workflow status
$stmt = $pdo->prepare("
    SELECT ap.*, a.name as approver_name, a.role as approver_role
    FROM approval_progress ap
    LEFT JOIN account a ON ap.approver_id = a.id
    WHERE ap.request_id = ?
    ORDER BY ap.approval_level
");
$stmt->execute([$request_id]);
$workflow = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Display request header with overall status
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>";
echo "<h3 style='margin: 0 0 10px 0; color: #015c2e;'>📋 Request Overview</h3>";
echo "<div style='display: grid; grid-template-columns: 1fr 1fr; gap: 15px;'>";
echo "<div><strong>Request ID:</strong> $request_id</div>";
echo "<div><strong>Requester:</strong> {$request['requester_name']}</div>";
echo "<div><strong>Campus Code:</strong> {$request['campus_code']} - {$request['campus_name']}</div>";
echo "<div><strong>Department Code:</strong> {$request['department_code']}</div>";
echo "<div><strong>Fund Account Code:</strong> {$request['fund_account']}</div>";
echo "<div><strong>Fund Name:</strong> {$request['fund_name']}</div>";
echo "<div><strong>Duration:</strong> {$request['duration']}</div>";
echo "<div><strong>Academic Year:</strong> {$request['academic_year']}</div>";
echo "<div><strong>Total Amount:</strong> ₱" . number_format($request['proposed_budget'], 2) . "</div>";
echo "<div><strong>Submitted:</strong> " . date('M j, Y g:i A', strtotime($request['timestamp'])) . "</div>";
echo "</div>";

if (!empty($request['budget_title']) || !empty($request['description'])) {
    echo "<div style='background: #fff; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #dee2e6;'>";
    echo "<h3 style='margin: 0 0 10px 0; color: #015c2e;'>📝 Request Details</h3>";
    if (!empty($request['budget_title'])) {
        echo "<div style='margin-bottom: 10px;'><strong>Budget Request Title:</strong> {$request['budget_title']}</div>";
    }
    if (!empty($request['description'])) {
        echo "<div><strong>Description:</strong><br><div style='background: #f8f9fa; padding: 10px; border-radius: 4px; margin-top: 5px;'>{$request['description']}</div></div>";
    }
    echo "</div>";
}
echo "</div>";

// Check for special status alerts
if (!empty($workflow)) {
    $rejected_steps = array_filter($workflow, fn($w) => $w['status'] === 'rejected');
    $info_requested_steps = array_filter($workflow, fn($w) => $w['status'] === 'request_info');
    
    if (!empty($rejected_steps)) {
        $rejected = reset($rejected_steps);
        echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>";
        echo "<h3 style='margin: 0 0 10px 0; color: #721c24;'>❌ Request Rejected</h3>";
        echo "<p><strong>Rejected by:</strong> {$rejected['approver_name']} (" . ucwords(str_replace('_', ' ', $rejected['approver_role'])) . ")</p>";
        if ($rejected['timestamp']) {
            echo "<p><strong>Date:</strong> " . date('M j, Y g:i A', strtotime($rejected['timestamp'])) . "</p>";
        }
        if ($rejected['comments']) {
            echo "<p><strong>Reason:</strong></p>";
            echo "<div style='background: white; padding: 10px; border-radius: 4px; border: 1px solid #f5c6cb; font-style: italic;'>";
            echo "\"" . htmlspecialchars($rejected['comments']) . "\"";
            echo "</div>";
        }
        echo "</div>";
    } elseif (!empty($info_requested_steps)) {
        $info_request = reset($info_requested_steps);
        echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>";
        echo "<h3 style='margin: 0 0 10px 0; color: #856404;'>❓ More Information Requested</h3>";
        echo "<p><strong>Requested by:</strong> {$info_request['approver_name']} (" . ucwords(str_replace('_', ' ', $info_request['approver_role'])) . ")</p>";
        if ($info_request['timestamp']) {
            echo "<p><strong>Date:</strong> " . date('M j, Y g:i A', strtotime($info_request['timestamp'])) . "</p>";
        }
        if ($info_request['comments']) {
            echo "<p><strong>Information needed:</strong></p>";
            echo "<div style='background: white; padding: 10px; border-radius: 4px; border: 1px solid #ffeaa7; font-style: italic;'>";
            echo "\"" . htmlspecialchars($info_request['comments']) . "\"";
            echo "</div>";
        }
        echo "<p style='margin-top: 10px;'><em>Please contact the approver or resubmit your request with the requested information.</em></p>";
        echo "</div>";
    }
}

// Display workflow status
if (!empty($workflow)) {
    echo "<div style='background: #fff; border: 2px solid #015c2e; border-radius: 8px; margin-bottom: 20px; padding: 15px;'>";
    echo "<h3 style='margin: 0 0 15px 0; color: #015c2e;'>📊 Approval Workflow Status</h3>";
    
    $current_level = $request['current_approval_level'] ?? 1;
    $workflow_complete = $request['workflow_complete'] ?? false;
    
    echo "<div style='display: flex; align-items: center; margin-bottom: 15px;'>";
    
    foreach ($workflow as $i => $step) {
        $is_current = ($step['approval_level'] == $current_level && !$workflow_complete);
        $is_completed = ($step['status'] === 'approved');
        $is_rejected = ($step['status'] === 'rejected');
        $is_pending = ($step['status'] === 'pending');
        
        // Step circle
        $circle_color = '#e9ecef';
        $text_color = '#6c757d';
        $icon = '○';
        
        if ($is_completed) {
            $circle_color = '#28a745';
            $text_color = 'white';
            $icon = '✓';
        } elseif ($is_rejected) {
            $circle_color = '#dc3545';
            $text_color = 'white';
            $icon = '✗';
        } elseif ($step['status'] === 'request_info') {
            $circle_color = '#ffc107';
            $text_color = '#212529';
            $icon = '❓';
        } elseif ($is_pending) {
            $circle_color = '#ffc107';
            $text_color = '#212529';
            $icon = '⏳';
        }
        
        echo "<div style='display: flex; flex-direction: column; align-items: center; margin: 0 10px;'>";
        echo "<div style='width: 50px; height: 50px; border-radius: 50%; background: $circle_color; color: $text_color; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 20px; margin-bottom: 8px;'>$icon</div>";
        echo "<div style='text-align: center; font-size: 12px; max-width: 80px;'>";
        echo "<div style='font-weight: bold;'>" . ucwords(str_replace('_', ' ', $step['approver_role'])) . "</div>";
        echo "<div style='color: #6c757d;'>{$step['approver_name']}</div>";
        
        if ($step['timestamp']) {
            echo "<div style='color: #6c757d; margin-top: 4px;'>" . date('M j', strtotime($step['timestamp'])) . "</div>";
        }
        
        if ($step['comments']) {
            echo "<div style='color: #007bff; font-style: italic; margin-top: 2px; cursor: help;' title='" . htmlspecialchars($step['comments']) . "'>💬</div>";
        }
        echo "</div>";
        echo "</div>";
        
        // Arrow between steps
        if ($i < count($workflow) - 1) {
            echo "<div style='flex: 1; height: 2px; background: " . ($is_completed ? "#28a745" : "#e9ecef") . "; align-self: center; margin: 0 5px;'></div>";
        }
    }
    
    echo "</div>";
    
    // Overall status message
    if ($workflow_complete) {
        if ($request['status'] === 'approved') {
            echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 10px; border-radius: 5px; text-align: center;'>";
            echo "🎉 <strong>Request Fully Approved!</strong> All required approvals have been completed.";
            echo "</div>";
        } elseif ($request['status'] === 'rejected') {
            echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px; border-radius: 5px; text-align: center;'>";
            echo "❌ <strong>Request Rejected</strong> - Workflow has been stopped.";
            echo "</div>";
        }
    } else {
        $current_approver = array_filter($workflow, fn($w) => $w['approval_level'] == $current_level);
        if (!empty($current_approver)) {
            $approver = reset($current_approver);
            echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 10px; border-radius: 5px; text-align: center;'>";
            echo "⏳ <strong>Awaiting approval from:</strong> {$approver['approver_name']} (" . ucwords(str_replace('_', ' ', $approver['approver_role'])) . ")";
            echo "</div>";
        }
    }
    
    echo "</div>";
    
    // Display comments section
    $comments_exist = array_filter($workflow, fn($w) => !empty($w['comments']));
    if (!empty($comments_exist)) {
        echo "<div style='background: #fff; border: 1px solid #007bff; border-radius: 8px; margin-bottom: 20px; padding: 15px;'>";
        echo "<h3 style='margin: 0 0 15px 0; color: #007bff;'>💬 Approver Comments</h3>";
        
        foreach ($workflow as $step) {
            if (!empty($step['comments'])) {
                $status_color = '';
                $status_icon = '';
                switch($step['status']) {
                    case 'approved':
                        $status_color = '#28a745';
                        $status_icon = '✅';
                        break;
                    case 'rejected':
                        $status_color = '#dc3545';
                        $status_icon = '❌';
                        break;
                    case 'request_info':
                        $status_color = '#ffc107';
                        $status_icon = '❓';
                        break;
                    default:
                        $status_color = '#6c757d';
                        $status_icon = '💬';
                }
                
                echo "<div style='border-left: 4px solid $status_color; padding: 12px; margin-bottom: 10px; background: #f8f9fa;'>";
                echo "<div style='display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;'>";
                echo "<div style='font-weight: bold; color: $status_color;'>$status_icon " . ucwords(str_replace('_', ' ', $step['approver_role'])) . " - {$step['approver_name']}</div>";
                
                if ($step['timestamp']) {
                    echo "<div style='color: #6c757d; font-size: 12px;'>" . date('M j, Y g:i A', strtotime($step['timestamp'])) . "</div>";
                }
                echo "</div>";
                
                echo "<div style='color: #333; font-style: italic; background: white; padding: 8px; border-radius: 4px; border: 1px solid #e9ecef;'>";
                echo "\"" . htmlspecialchars($step['comments']) . "\"";
                echo "</div>";
                
                $action_text = '';
                switch($step['status']) {
                    case 'approved':
                        $action_text = 'Approved with comment';
                        break;
                    case 'rejected':
                        $action_text = 'Rejected';
                        break;
                    case 'request_info':
                        $action_text = 'Requested more information';
                        break;
                }
                
                if ($action_text) {
                    echo "<div style='margin-top: 6px; font-size: 12px; color: #6c757d;'><em>$action_text</em></div>";
                }
                
                echo "</div>";
            }
        }
        echo "</div>";
    }
} else {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>";
    echo "⚠️ <strong>Workflow Not Initialized</strong> - This request hasn't been set up for approval yet.";
    echo "</div>";
}

// Display budget entries
if (!empty($entries)) {
    echo "<div style='background: #fff; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px;'>";
    echo "<h3 style='margin: 0 0 15px 0; color: #015c2e;'>💰 Budget Details</h3>";
    echo "<div style='overflow-x: auto;'>";
    echo "<table style='width:100%; border-collapse:collapse; border: 1px solid #dee2e6;'>";
    echo "<thead style='background: #f8f9fa;'>";
    echo "<tr style='font-weight:bold;'><th style='padding: 12px; border: 1px solid #dee2e6; text-align: left;'>Row</th><th style='padding: 12px; border: 1px solid #dee2e6; text-align: left;'>GL Code</th><th style='padding: 12px; border: 1px solid #dee2e6; text-align: left;'>Description</th><th style='padding: 12px; border: 1px solid #dee2e6; text-align: left;'>Remarks</th><th style='padding: 12px; border: 1px solid #dee2e6; text-align: right;'>Amount</th></tr>";
    echo "</thead>";
    echo "<tbody>";

    $total = 0;
    foreach ($entries as $entry) {
        $total += $entry['amount'];
        echo "<tr>";
        echo "<td style='padding: 10px; border: 1px solid #dee2e6;'>{$entry['row_num']}</td>";
        echo "<td style='padding: 10px; border: 1px solid #dee2e6;'>{$entry['gl_code']}</td>";
        echo "<td style='padding: 10px; border: 1px solid #dee2e6;'>{$entry['budget_description']}</td>";
        echo "<td style='padding: 10px; border: 1px solid #dee2e6;'>" . ($entry['remarks'] ? htmlspecialchars($entry['remarks']) : '<em style="color: #6c757d;">No remarks</em>') . "</td>";
        echo "<td style='padding: 10px; border: 1px solid #dee2e6; text-align: right;'><span class='clickable-amount' onclick='showDistribution(\"" . htmlspecialchars($entry['gl_code']) . "\", \"" . htmlspecialchars($entry['budget_description']) . "\", " . $entry['amount'] . ", \"" . htmlspecialchars($request['duration']) . "\")'>₱" . number_format($entry['amount'], 2) . "</span></td>";
        echo "</tr>";
    }
    
    echo "<tr style='background: #f8f9fa; font-weight: bold;'>";
    echo "<td colspan='4' style='padding: 12px; border: 1px solid #dee2e6; text-align: right;'>TOTAL:</td>";
    echo "<td style='padding: 12px; border: 1px solid #dee2e6; text-align: right;'>₱" . number_format($total, 2) . "</td>";
    echo "</tr>";
    
    echo "</tbody>";
    echo "</table>";
    echo "</div>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 8px;'>";
    echo "❌ No budget entries found for this request.";
    echo "</div>";
}
?>
