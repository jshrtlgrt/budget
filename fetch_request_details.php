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

// Get amendments for approved requests (so requester can see changes made by VP Finance)
$amendments = [];
if (strtolower($request['status']) === 'approved') {
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
}

// Display request header with overall status
echo "<div style='background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 25px; border-radius: 12px; margin-bottom: 30px; border: 1px solid #dee2e6; box-shadow: 0 2px 4px rgba(0,0,0,0.05);'>";
echo "<h3 style='margin: 0 0 20px 0; color: #015c2e; font-size: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px;'>";
echo "<span style='font-size: 20px;'>📋</span>";
echo "Request Overview</h3>";
echo "<div style='display: grid; grid-template-columns: 1fr 1fr; gap: 20px; row-gap: 15px;'>";
echo "<div style='background: white; padding: 12px 15px; border-radius: 6px; border-left: 4px solid #015c2e;'><strong style='color: #495057;'>Request ID:</strong><br><span style='color: #015c2e; font-weight: 600;'>$request_id</span></div>";
echo "<div style='background: white; padding: 12px 15px; border-radius: 6px; border-left: 4px solid #6c757d;'><strong style='color: #495057;'>Requester:</strong><br><span style='color: #212529;'>{$request['requester_name']}</span></div>";
echo "<div style='background: white; padding: 12px 15px; border-radius: 6px; border-left: 4px solid #17a2b8;'><strong style='color: #495057;'>Campus:</strong><br><span style='color: #212529;'>{$request['campus_code']} - {$request['campus_name']}</span></div>";
echo "<div style='background: white; padding: 12px 15px; border-radius: 6px; border-left: 4px solid #28a745;'><strong style='color: #495057;'>Department:</strong><br><span style='color: #212529;'>{$request['department_code']}</span></div>";
echo "<div style='background: white; padding: 12px 15px; border-radius: 6px; border-left: 4px solid #ffc107;'><strong style='color: #495057;'>Fund Account:</strong><br><span style='color: #212529;'>{$request['fund_account']}</span></div>";
echo "<div style='background: white; padding: 12px 15px; border-radius: 6px; border-left: 4px solid #fd7e14;'><strong style='color: #495057;'>Fund Name:</strong><br><span style='color: #212529;'>{$request['fund_name']}</span></div>";
echo "<div style='background: white; padding: 12px 15px; border-radius: 6px; border-left: 4px solid #e83e8c;'><strong style='color: #495057;'>Duration:</strong><br><span style='color: #212529;'>{$request['duration']}</span></div>";
echo "<div style='background: white; padding: 12px 15px; border-radius: 6px; border-left: 4px solid #6f42c1;'><strong style='color: #495057;'>Academic Year:</strong><br><span style='color: #212529;'>{$request['academic_year']}</span></div>";
echo "<div style='background: white; padding: 12px 15px; border-radius: 6px; border-left: 4px solid #28a745; grid-column: 1 / -1;'><strong style='color: #495057;'>Total Amount:</strong><br><span style='color: #28a745; font-weight: 700; font-size: 18px;'>₱" . number_format($request['proposed_budget'], 2) . "</span></div>";
echo "<div style='background: white; padding: 12px 15px; border-radius: 6px; border-left: 4px solid #6c757d; grid-column: 1 / -1;'><strong style='color: #495057;'>Submitted:</strong><br><span style='color: #212529;'>" . date('M j, Y g:i A', strtotime($request['timestamp'])) . "</span></div>";
echo "</div>";
echo "</div>";

if (!empty($request['budget_title']) || !empty($request['description'])) {
    echo "<div style='background: #fff; padding: 25px; border-radius: 12px; margin-bottom: 30px; border: 1px solid #dee2e6; box-shadow: 0 2px 4px rgba(0,0,0,0.05);'>";
    echo "<h3 style='margin: 0 0 20px 0; color: #015c2e; font-size: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px;'>";
    echo "<span style='font-size: 20px;'>📝</span>";
    echo "Request Details</h3>";
    if (!empty($request['budget_title'])) {
        echo "<div style='margin-bottom: 20px; background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #007bff;'>";
        echo "<strong style='color: #495057; display: block; margin-bottom: 8px;'>Budget Request Title:</strong>";
        echo "<span style='color: #212529; font-size: 16px;'>{$request['budget_title']}</span>";
        echo "</div>";
    }
    if (!empty($request['description'])) {
        echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745;'>";
        echo "<strong style='color: #495057; display: block; margin-bottom: 8px;'>Description:</strong>";
        echo "<div style='background: white; padding: 15px; border-radius: 6px; margin-top: 8px; line-height: 1.6; color: #212529; border: 1px solid #e9ecef;'>";
        echo nl2br(htmlspecialchars($request['description']));
        echo "</div></div>";
    }
    echo "</div>";
}

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
        echo "<div style='margin-top: 15px; padding-top: 15px; border-top: 1px solid #ffeaa7;'>";
        echo "<p style='margin-bottom: 10px;'><em>You can edit your request to provide the requested information:</em></p>";
        echo "<a href='edit_request.php?request_id=" . urlencode($request_id) . "' style='display: inline-block; background-color: #015c2e; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;'>✏️ Edit Request</a>";
        echo "</div>";
        echo "</div>";
    }
}

// Display workflow status
if (!empty($workflow)) {
    echo "<div style='background: #fff; border: 2px solid #015c2e; border-radius: 12px; margin-bottom: 30px; padding: 25px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);'>";
    echo "<h3 style='margin: 0 0 25px 0; color: #015c2e; font-size: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px;'>";
    echo "<span style='font-size: 20px;'>📊</span>";
    echo "Approval Workflow Status</h3>";
    
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

// Display amendments section for approved requests
if (!empty($amendments) && strtolower($request['status']) === 'approved') {
    echo "<div style='background: #fff; border: 2px solid #015c2e; border-radius: 12px; margin-bottom: 30px; padding: 25px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);'>";
    echo "<h3 style='margin: 0 0 25px 0; color: #015c2e; font-size: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px;'>";
    echo "<span style='font-size: 20px;'>✏️</span>";
    echo "Amendment History <span style='background: #28a745; color: white; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 500; margin-left: 10px;'>" . count($amendments) . " amendment" . (count($amendments) !== 1 ? 's' : '') . "</span></h3>";
    
    echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #28a745;'>";
    echo "<h4 style='margin: 0 0 8px 0; color: #155724; display: flex; align-items: center; gap: 8px;'>";
    echo "<span>📢</span>Important Notice</h4>";
    echo "<p style='margin: 0; color: #155724; font-size: 14px;'>";
    echo "Your approved request has been <strong>modified by VP Finance</strong>. The amendments below show all changes made to your original request. ";
    echo "These modifications are part of the administrative process and <strong>do not require additional approval</strong>.";
    echo "</p></div>";
    
    foreach ($amendments as $amendment) {
        $amendment_color = '#28a745'; // All amendments are approved by default
        $border_color = '#c3e6cb';
        $bg_color = '#d4edda';
        
        echo "<div style='border: 2px solid $border_color; border-radius: 8px; margin-bottom: 20px; overflow: hidden;'>";
        
        // Amendment header
        echo "<div style='background: $bg_color; padding: 15px; border-bottom: 1px solid $border_color;'>";
        echo "<div style='display: flex; justify-content: between; align-items: flex-start; gap: 15px;'>";
        echo "<div style='flex: 1;'>";
        echo "<div style='display: flex; align-items: center; gap: 10px; margin-bottom: 8px;'>";
        echo "<span style='background: $amendment_color; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold;'>";
        echo "AMENDMENT #" . $amendment['amendment_number'];
        echo "</span>";
        echo "<span style='background: #007bff; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; text-transform: uppercase;'>";
        echo str_replace('_', ' ', $amendment['amendment_type']);
        echo "</span>";
        echo "</div>";
        echo "<h4 style='margin: 0 0 5px 0; color: #155724; font-size: 16px;'>" . htmlspecialchars($amendment['amendment_title']) . "</h4>";
        echo "<div style='font-size: 12px; color: #155724;'>";
        echo "Modified on " . date('M j, Y g:i A', strtotime($amendment['created_timestamp']));
        echo " by " . htmlspecialchars($amendment['created_by_name']);
        echo "</div>";
        echo "</div>";
        
        // Budget change indicator
        $baseline_budget_check = isset($amendment['calculated_original_budget']) ? $amendment['calculated_original_budget'] : $amendment['original_total_budget'];
        if ($baseline_budget_check != $amendment['amended_total_budget']) {
            $change = floatval($amendment['amended_total_budget']) - floatval($baseline_budget_check);
            $change_color = $change >= 0 ? '#28a745' : '#dc3545';
            $change_icon = $change >= 0 ? '📈' : '📉';
            
            echo "<div style='text-align: right; flex-shrink: 0;'>";
            echo "<div style='background: white; padding: 10px; border-radius: 6px; border: 1px solid #dee2e6;'>";
            echo "<div style='font-size: 11px; color: #6c757d; margin-bottom: 2px;'>Budget Change</div>";
            echo "<div style='color: $change_color; font-weight: bold; font-size: 14px;'>";
            echo "$change_icon " . ($change >= 0 ? '+' : '') . "₱" . number_format(abs($change), 2);
            echo "</div>";
            echo "</div></div>";
        }
        echo "</div>";
        echo "</div>";
        
        // Amendment content
        echo "<div style='background: white; padding: 20px;'>";
        
        // Reason
        echo "<div style='margin-bottom: 15px;'>";
        echo "<h5 style='margin: 0 0 8px 0; color: #495057; font-size: 14px;'>📝 Reason for Amendment:</h5>";
        echo "<div style='background: #f8f9fa; padding: 12px; border-radius: 6px; border-left: 4px solid #007bff; font-style: italic;'>";
        echo "\"" . nl2br(htmlspecialchars($amendment['amendment_reason'])) . "\"";
        echo "</div></div>";
        
        // Budget summary if changed
        if ($amendment['original_total_budget'] != $amendment['amended_total_budget']) {
            echo "<div style='margin-bottom: 15px;'>";
            echo "<h5 style='margin: 0 0 8px 0; color: #495057; font-size: 14px;'>💰 Budget Summary:</h5>";
            echo "<div style='display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;'>";
            
            echo "<div style='background: #f8f9fa; padding: 10px; border-radius: 6px; text-align: center;'>";
            echo "<div style='font-size: 11px; color: #6c757d; margin-bottom: 4px;'>Original Budget</div>";
            $baseline_budget = isset($amendment['calculated_original_budget']) ? $amendment['calculated_original_budget'] : $amendment['original_total_budget'];
            echo "<div style='font-weight: bold; color: #dc3545;'>₱" . number_format($baseline_budget, 2) . "</div>";
            echo "</div>";
            
            echo "<div style='background: #e8f5e8; padding: 10px; border-radius: 6px; text-align: center;'>";
            echo "<div style='font-size: 11px; color: #155724; margin-bottom: 4px;'>Amended Budget</div>";
            echo "<div style='font-weight: bold; color: #28a745;'>₱" . number_format($amendment['amended_total_budget'], 2) . "</div>";
            echo "</div>";
            
            $change = floatval($amendment['amended_total_budget']) - floatval($baseline_budget);
            $change_color = $change >= 0 ? '#28a745' : '#dc3545';
            echo "<div style='background: #f8f9fa; padding: 10px; border-radius: 6px; text-align: center;'>";
            echo "<div style='font-size: 11px; color: #6c757d; margin-bottom: 4px;'>Change</div>";
            echo "<div style='font-weight: bold; color: $change_color;'>";
            echo ($change >= 0 ? '+' : '') . "₱" . number_format($change, 2);
            echo "</div></div>";
            
            echo "</div></div>";
        }
        
        // Amendment specific details
        if (!empty($amendment['amendment_data'])) {
            $amendment_data = json_decode($amendment['amendment_data'], true);
            if ($amendment_data) {
                echo "<div style='margin-bottom: 15px;'>";
                echo "<h5 style='margin: 0 0 8px 0; color: #495057; font-size: 14px;'>🔍 Detailed Changes:</h5>";
                
                echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 6px; border: 1px solid #dee2e6;'>";
                
                // Handle different amendment types
                switch($amendment['amendment_type']) {
                    case 'budget_change':
                        if (isset($amendment_data['budget_entries'])) {
                            echo "<strong>Modified Budget Items:</strong><br>";
                            foreach ($amendment_data['budget_entries'] as $entry) {
                                $entry_change = floatval($entry['new_amount']) - floatval($entry['original_amount']);
                                $entry_change_color = $entry_change >= 0 ? '#28a745' : '#dc3545';
                                echo "<div style='margin: 8px 0; padding: 8px; background: white; border-radius: 4px;'>";
                                echo "<strong>{$entry['gl_code']}</strong> - {$entry['description']}<br>";
                                echo "<small>Original: ₱" . number_format($entry['original_amount'], 2) . " → New: ₱" . number_format($entry['new_amount'], 2);
                                echo " <span style='color: $entry_change_color;'>(" . ($entry_change >= 0 ? '+' : '') . "₱" . number_format($entry_change, 2) . ")</span></small>";
                                echo "</div>";
                            }
                        }
                        break;
                        
                    case 'description_change':
                        if (isset($amendment_data['new_budget_title'])) {
                            echo "<strong>New Budget Title:</strong><br>";
                            echo "<em>\"" . htmlspecialchars($amendment_data['new_budget_title']) . "\"</em><br><br>";
                        }
                        if (isset($amendment_data['new_description'])) {
                            echo "<strong>New Description:</strong><br>";
                            echo "<em>\"" . nl2br(htmlspecialchars($amendment_data['new_description'])) . "\"</em>";
                        }
                        break;
                        
                    case 'timeline_change':
                        if (isset($amendment_data['new_duration'])) {
                            echo "<strong>New Duration:</strong> " . htmlspecialchars($amendment_data['new_duration']) . "<br>";
                        }
                        if (isset($amendment_data['new_academic_year'])) {
                            echo "<strong>New Academic Year:</strong> " . htmlspecialchars($amendment_data['new_academic_year']);
                        }
                        break;
                        
                    case 'general_modification':
                        if (isset($amendment_data['new_fund_account'])) {
                            echo "<strong>New Fund Account:</strong> " . htmlspecialchars($amendment_data['new_fund_account']) . "<br>";
                        }
                        if (isset($amendment_data['new_fund_name'])) {
                            echo "<strong>New Fund Name:</strong> " . htmlspecialchars($amendment_data['new_fund_name']) . "<br>";
                        }
                        if (isset($amendment_data['general_modification_notes'])) {
                            echo "<strong>Additional Notes:</strong><br>";
                            echo "<em>\"" . nl2br(htmlspecialchars($amendment_data['general_modification_notes'])) . "\"</em>";
                        }
                        break;
                }
                
                echo "</div></div>";
            }
        }
        
        // Administrative info
        echo "<div style='background: #f8f9fa; padding: 10px; border-radius: 4px; border-top: 1px solid #dee2e6;'>";
        echo "<div style='font-size: 11px; color: #6c757d;'>";
        echo "📋 Administrative Amendment • Auto-approved by " . htmlspecialchars($amendment['approved_by_name']);
        if ($amendment['approved_timestamp']) {
            echo " on " . date('M j, Y g:i A', strtotime($amendment['approved_timestamp']));
        }
        echo "</div></div>";
        
        echo "</div></div>";
    }
    
    echo "</div>";
} elseif (strtolower($request['status']) === 'approved') {
    // Show a message for approved requests with no amendments
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;'>";
    echo "<h4 style='margin: 0 0 8px 0; display: flex; align-items: center; justify-content: center; gap: 8px;'>";
    echo "<span>✅</span>Request Approved - No Amendments</h4>";
    echo "<p style='margin: 0; font-size: 14px;'>Your request was approved as submitted. No modifications have been made by VP Finance.</p>";
    echo "</div>";
}

// Display budget entries
if (!empty($entries)) {
    echo "<div style='background: #fff; border: 1px solid #dee2e6; border-radius: 12px; padding: 25px; margin-bottom: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);'>";
    echo "<h3 style='margin: 0 0 20px 0; color: #015c2e; font-size: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px;'>";
    echo "<span style='font-size: 20px;'>💰</span>";
    echo "Budget Details</h3>";
    echo "<div style='overflow-x: auto;'>";
    echo "<table style='width:100%; border-collapse:collapse; border: 1px solid #dee2e6;'>";
    echo "<thead style='background: #f8f9fa;'>";
    echo "<tr style='font-weight:bold;'>";
    echo "<th style='padding: 12px; border: 1px solid #dee2e6; text-align: left;'>Row</th>";
    echo "<th style='padding: 12px; border: 1px solid #dee2e6; text-align: left;'>GL Code</th>";
    echo "<th style='padding: 12px; border: 1px solid #dee2e6; text-align: left;'>Description</th>";
    echo "<th style='padding: 12px; border: 1px solid #dee2e6; text-align: left;'>Remarks</th>";
    echo "<th style='padding: 12px; border: 1px solid #dee2e6; text-align: right;'>Proposed Amount</th>";
    
    // Check if any entry has approved amounts to show the column
    $has_approved_amounts = false;
    foreach ($entries as $entry) {
        if (!is_null($entry['approved_amount']) && $entry['approved_amount'] != $entry['amount']) {
            $has_approved_amounts = true;
            break;
        }
    }
    
    if ($has_approved_amounts) {
        echo "<th style='padding: 12px; border: 1px solid #dee2e6; text-align: right; background-color: #28a745; color: white;'>Approved Amount</th>";
    }
    
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";

    $total_proposed = 0;
    $total_approved = 0;
    
    foreach ($entries as $entry) {
        $total_proposed += $entry['amount'];
        $approved_amount = !is_null($entry['approved_amount']) ? $entry['approved_amount'] : $entry['amount'];
        $total_approved += $approved_amount;
        
        echo "<tr>";
        echo "<td style='padding: 10px; border: 1px solid #dee2e6;'>{$entry['row_num']}</td>";
        echo "<td style='padding: 10px; border: 1px solid #dee2e6;'>{$entry['gl_code']}</td>";
        echo "<td style='padding: 10px; border: 1px solid #dee2e6;'>{$entry['budget_description']}</td>";
        echo "<td style='padding: 10px; border: 1px solid #dee2e6;'>" . ($entry['remarks'] ? htmlspecialchars($entry['remarks']) : '<em style="color: #6c757d;">No remarks</em>') . "</td>";
        echo "<td style='padding: 10px; border: 1px solid #dee2e6; text-align: right;'><span class='clickable-amount' onclick='showDistribution(\"" . htmlspecialchars($entry['gl_code']) . "\", \"" . htmlspecialchars($entry['budget_description']) . "\", " . $entry['amount'] . ", \"" . htmlspecialchars($request['duration']) . "\")'>₱" . number_format($entry['amount'], 2) . "</span></td>";
        
        if ($has_approved_amounts) {
            $amount_style = ($approved_amount != $entry['amount']) ? 'color: #28a745; font-weight: bold;' : '';
            echo "<td style='padding: 10px; border: 1px solid #dee2e6; text-align: right; $amount_style'>₱" . number_format($approved_amount, 2) . "</td>";
        }
        
        echo "</tr>";
    }
    
    echo "<tr style='background: #f8f9fa; font-weight: bold;'>";
    echo "<td colspan='" . ($has_approved_amounts ? '4' : '4') . "' style='padding: 12px; border: 1px solid #dee2e6; text-align: right;'>TOTAL PROPOSED:</td>";
    echo "<td style='padding: 12px; border: 1px solid #dee2e6; text-align: right;'>₱" . number_format($total_proposed, 2) . "</td>";
    
    if ($has_approved_amounts) {
        echo "<td style='padding: 12px; border: 1px solid #dee2e6; text-align: right; color: #28a745; font-weight: bold;'>₱" . number_format($total_approved, 2) . "</td>";
    }
    
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

// Display attachments
if (!empty($attachments)) {
    echo "<div style='background: #fff; border: 1px solid #dee2e6; border-radius: 12px; padding: 25px; margin-top: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);'>";
    echo "<h3 style='margin: 0 0 20px 0; color: #015c2e; font-size: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px;'>";
    echo "<span style='font-size: 20px;'>📎</span>";
    echo "Attachments</h3>";
    echo "<div style='display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;'>";
    
    foreach ($attachments as $attachment) {
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
        
        echo "<div style='border: 1px solid #ddd; border-radius: 8px; padding: 15px; background: #f9f9f9;'>";
        echo "<div style='display: flex; align-items: center; margin-bottom: 10px;'>";
        echo "<span style='font-size: 24px; margin-right: 10px;'>$icon</span>";
        echo "<div style='flex: 1;'>";
        echo "<strong style='color: #015c2e;'>" . htmlspecialchars($attachment['original_filename']) . "</strong>";
        echo "<br><small style='color: #666;'>";
        echo number_format($attachment['file_size'] / 1024, 1) . " KB • ";
        echo "Uploaded " . date('M j, Y g:i A', strtotime($attachment['upload_timestamp']));
        if ($attachment['uploader_name']) {
            echo " by " . htmlspecialchars($attachment['uploader_name']);
        }
        echo "</small>";
        echo "</div>";
        echo "</div>";
        echo "<div>";
        echo "<a href='download_attachment.php?id=" . $attachment['id'] . "' ";
        echo "style='background: #006633; color: white; text-decoration: none; padding: 8px 16px; border-radius: 5px; font-size: 14px; display: inline-block;'>";
        echo "📥 Download";
        echo "</a>";
        echo "</div>";
        echo "</div>";
    }
    
    echo "</div>";
    echo "</div>";
}
?>
