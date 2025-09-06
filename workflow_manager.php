<?php
class WorkflowManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Initialize approval workflow for a new request
     */
    public function initializeWorkflow($request_id) {
        try {
            // Get request details
            $stmt = $this->pdo->prepare("
                SELECT br.*, d.code as dept_code 
                FROM budget_request br 
                LEFT JOIN department d ON br.department_code = d.code 
                WHERE br.request_id = ?
            ");
            $stmt->execute([$request_id]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$request) {
                throw new Exception("Request not found");
            }
            
            // Get required approval levels based on amount and department
            $required_levels = $this->getRequiredApprovalLevels(
                $request['department_code'], 
                $request['proposed_budget']
            );
            
            // Update request with workflow info
            $stmt = $this->pdo->prepare("
                UPDATE budget_request 
                SET current_approval_level = 1, 
                    total_approval_levels = ?,
                    workflow_complete = FALSE
                WHERE request_id = ?
            ");
            $stmt->execute([count($required_levels), $request_id]);
            
            // Create approval progress entries
            foreach ($required_levels as $level) {
                $status = ($level['approval_level'] == 1) ? 'pending' : 'waiting';
                $stmt = $this->pdo->prepare("
                    INSERT INTO approval_progress (request_id, approval_level, approver_id, status) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $request_id, 
                    $level['approval_level'], 
                    $level['approver_id'], 
                    $status
                ]);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Workflow initialization failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Process approval at current level and advance workflow
     */
    public function processApproval($request_id, $approver_id, $action, $comments = '') {
        try {
            $this->pdo->beginTransaction();
            
            // Get current request status
            $stmt = $this->pdo->prepare("
                SELECT current_approval_level, total_approval_levels, status 
                FROM budget_request 
                WHERE request_id = ?
            ");
            $stmt->execute([$request_id]);
            $request_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$request_info) {
                throw new Exception("Request not found");
            }
            
            $current_level = $request_info['current_approval_level'];
            
            // Log in history
            $action_text = $this->getActionText($action, $comments);
            $stmt = $this->pdo->prepare("
                INSERT INTO history (request_id, timestamp, action, account_id) 
                VALUES (?, NOW(), ?, ?)
            ");
            $stmt->execute([$request_id, $action_text, $approver_id]);
            
            if ($action === 'approve') {
                // Mark current level as approved
                $stmt = $this->pdo->prepare("
                    UPDATE approval_progress 
                    SET status = 'approved', comments = ?, timestamp = NOW() 
                    WHERE request_id = ? AND approval_level = ? AND approver_id = ?
                ");
                $stmt->execute([$comments, $request_id, $current_level, $approver_id]);
                
                // Check if this was the final level
                if ($current_level >= $request_info['total_approval_levels']) {
                    // Final approval - mark as complete
                    $stmt = $this->pdo->prepare("
                        UPDATE budget_request 
                        SET status = 'approved', workflow_complete = TRUE 
                        WHERE request_id = ?
                    ");
                    $stmt->execute([$request_id]);
                } else {
                    // Move to next level
                    $next_level = $current_level + 1;
                    $stmt = $this->pdo->prepare("
                        UPDATE budget_request 
                        SET current_approval_level = ? 
                        WHERE request_id = ?
                    ");
                    $stmt->execute([$next_level, $request_id]);
                    
                    // Update next level status to pending
                    $stmt = $this->pdo->prepare("
                        UPDATE approval_progress 
                        SET status = 'pending' 
                        WHERE request_id = ? AND approval_level = ?
                    ");
                    $stmt->execute([$request_id, $next_level]);
                }
            } else if ($action === 'reject') {
                // Mark current level as rejected
                $stmt = $this->pdo->prepare("
                    UPDATE approval_progress 
                    SET status = 'rejected', comments = ?, timestamp = NOW() 
                    WHERE request_id = ? AND approval_level = ? AND approver_id = ?
                ");
                $stmt->execute([$comments, $request_id, $current_level, $approver_id]);
                
                // Rejection at any level stops the workflow
                $stmt = $this->pdo->prepare("
                    UPDATE budget_request 
                    SET status = 'rejected', workflow_complete = TRUE 
                    WHERE request_id = ?
                ");
                $stmt->execute([$request_id]);
            } else if ($action === 'request_info') {
                // Mark current level as requesting info
                $stmt = $this->pdo->prepare("
                    UPDATE approval_progress 
                    SET status = 'request_info', comments = ?, timestamp = NOW() 
                    WHERE request_id = ? AND approval_level = ? AND approver_id = ?
                ");
                $stmt->execute([$comments, $request_id, $current_level, $approver_id]);
                
                // Request more info - pause workflow
                $stmt = $this->pdo->prepare("
                    UPDATE budget_request 
                    SET status = 'more_info_requested' 
                    WHERE request_id = ?
                ");
                $stmt->execute([$request_id]);
            }
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Approval processing failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get requests that current user can approve
     */
    public function getRequestsForApprover($approver_id) {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT br.*, ap.approval_level, ap.status as approval_status,
                   a.name as requester_name, d.college
            FROM budget_request br
            JOIN approval_progress ap ON br.request_id = ap.request_id
            LEFT JOIN account a ON br.account_id = a.id
            LEFT JOIN department d ON br.department_code = d.code
            WHERE ap.approver_id = ? 
            AND ap.approval_level = br.current_approval_level
            AND ap.status = 'pending'
            AND br.workflow_complete = FALSE
            ORDER BY br.timestamp DESC
        ");
        $stmt->execute([$approver_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get complete approval history for a request
     */
    public function getApprovalHistory($request_id) {
        $stmt = $this->pdo->prepare("
            SELECT ap.*, a.name as approver_name, a.role as approver_role
            FROM approval_progress ap
            LEFT JOIN account a ON ap.approver_id = a.id
            WHERE ap.request_id = ?
            ORDER BY ap.approval_level ASC
        ");
        $stmt->execute([$request_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getRequiredApprovalLevels($department_code, $amount) {
        // Get ALL workflow rules for this department - NO SKIPS
        // All requests must go through every level in sequence
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT aw.approval_level, aw.approver_role, aw.amount_threshold
            FROM approval_workflow aw
            WHERE aw.department_code = ? 
            AND aw.is_required = TRUE
            ORDER BY aw.approval_level ASC
        ");
        $stmt->execute([$department_code]);
        $workflow_rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $levels = [];
        foreach ($workflow_rules as $rule) {
            // Find an approver with this role
            $stmt = $this->pdo->prepare("
                SELECT id FROM account 
                WHERE role = ? 
                AND department_code = ?
                LIMIT 1
            ");
            $stmt->execute([$rule['approver_role'], $department_code]);
            $approver_id = $stmt->fetchColumn();
            
            
            if (!$approver_id) {
                $stmt = $this->pdo->prepare("
                    SELECT id FROM account 
                    WHERE role = ?
                    LIMIT 1
                ");
                $stmt->execute([$rule['approver_role']]);
                $approver_id = $stmt->fetchColumn();
            }
            
            if ($approver_id) {
                $levels[] = [
                    'approval_level' => $rule['approval_level'],
                    'approver_id' => $approver_id,
                    'approver_role' => $rule['approver_role']
                ];
            }
        }
        
        return $levels;
    }
    
    /**
     * Resume workflow after requester provides additional information
     */
    public function resumeWorkflowAfterInfoProvided($request_id, $requesting_approval_level) {
        try {
            // Reset the requesting level to pending
            $stmt = $this->pdo->prepare("
                UPDATE approval_progress 
                SET status = 'pending', comments = NULL, timestamp = NULL 
                WHERE request_id = ? AND approval_level = ?
            ");
            $stmt->execute([$request_id, $requesting_approval_level]);
            
            // Reset higher levels to waiting
            $stmt = $this->pdo->prepare("
                UPDATE approval_progress 
                SET status = 'waiting', comments = NULL, timestamp = NULL 
                WHERE request_id = ? AND approval_level > ?
            ");
            $stmt->execute([$request_id, $requesting_approval_level]);
            
            // Update the main request status
            $stmt = $this->pdo->prepare("
                UPDATE budget_request 
                SET status = 'pending', current_approval_level = ? 
                WHERE request_id = ?
            ");
            $stmt->execute([$requesting_approval_level, $request_id]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Workflow resume failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Resume workflow after requester provides additional information (with transaction)
     */
    public function resumeWorkflowAfterInfoProvidedWithTransaction($request_id, $requesting_approval_level) {
        try {
            $this->pdo->beginTransaction();
            
            $result = $this->resumeWorkflowAfterInfoProvided($request_id, $requesting_approval_level);
            
            if ($result) {
                $this->pdo->commit();
            } else {
                $this->pdo->rollBack();
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Workflow resume failed: " . $e->getMessage());
            return false;
        }
    }
    
    private function getActionText($action, $comments) {
        switch ($action) {
            case 'approve':
            case 'approved':
                return 'Approved' . (!empty($comments) ? ': ' . $comments : '');
            case 'reject':
            case 'rejected':
                return 'Rejected: ' . $comments;
            case 'request_info':
                return 'Requested more information: ' . $comments;
            default:
                return 'Unknown action: ' . $action; // Show what action was received for debugging
        }
    }
}
?>