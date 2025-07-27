<?php
$pdo = new PDO("mysql:host=localhost;dbname=budget_database_schema", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$request_id = $_GET['request_id'] ?? '';
if (!$request_id) exit("Invalid request.");

// Get budget request info
$stmt = $pdo->prepare("SELECT * FROM budget_request WHERE request_id = ?");
$stmt->execute([$request_id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$request) exit("No request found.");

// Get requester info
$stmt = $pdo->prepare("SELECT name, username_email, department_code FROM account WHERE id = ?");
$stmt->execute([$request['account_id']]);
$requester = $stmt->fetch(PDO::FETCH_ASSOC);

// Get department and college
$stmt = $pdo->prepare("SELECT college FROM department WHERE code = ?");
$stmt->execute([$requester['department_code']]);
$department = $stmt->fetch(PDO::FETCH_ASSOC);

// Get budget entries
$stmt = $pdo->prepare("SELECT * FROM budget_entries WHERE request_id = ?");
$stmt->execute([$request_id]);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo <<<HTML
<style>
    body { font-family: Arial, sans-serif; margin: 0; }

    .modal-overlay {
        position: fixed;
        top: 0; left: 0;
        width: 100vw; height: 100vh;
        background: rgba(0,0,0,0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 9999;
        overflow-y: auto;
    }

    .modal-content {
        position: relative;
        background: white;
        border-radius: 10px;
        padding: 30px;
        width: 75vw;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        margin: 40px 0;
    }

    .modal-close {
        position: absolute;
        top: 15px;
        right: 20px;
        font-size: 24px;
        font-weight: bold;
        color: #666;
        cursor: pointer;
    }

    h2 {
        background: #02733e;
        color: white;
        padding: 12px 20px;
        border-radius: 8px 8px 0 0;
        margin: -30px -30px 20px -30px;
    }

    .section-title {
        font-weight: bold;
        color: #333;
        margin: 30px 0 10px;
        border-bottom: 2px solid #ccc;
        padding-bottom: 5px;
    }

    .info-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }

    .info-table td {
        padding: 4px 8px; /* reduced horizontal space */
        vertical-align: top;
        text-align: left;
    }

    .info-table td:first-child {
        white-space: nowrap;
        width: 1px;
    }

    .total-budget-row td {
        font-size: 1.3em;
        font-weight: bold;
        color: #02733e;
        text-align: right;
    }

    .line-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }

    .line-table th, .line-table td {
        border: 1px solid #ccc;
        padding: 10px;
        text-align: left;
    }

    .line-table th {
        background: #f0f0f0;
    }

    .btn-group button {
        margin: 5px 10px 0 0;
        padding: 8px 16px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
    }

    .btn-approve { background: #02733e; color: white; }
    .btn-reject { background: #c62828; color: white; }
    .btn-revision { background: #c5a100; color: white; }

    .history-entry {
        margin-bottom: 20px;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
    }

    .history-date {
        color: #02733e;
        font-weight: bold;
        font-size: 15px;
    }

    .history-comment {
        margin-left: 20px;
        color: #555;
        font-size: 14px;
    }

    .history-comment p {
        margin: 3px 0;
    }
</style>

<div class='modal-content'>
    <div class="modal-close" onclick="document.querySelector('.modal-overlay').style.display='none'">&times;</div>
    <div class="modal-close" onclick="closeModal()">&times;</div>

    <h2>Budget Request: {$request['request_id']}</h2>

    <div class='section-title'>Basic Information</div>
    <table class='info-table'>
        <tr><td><strong>Submitted By:</strong></td><td>{$requester['name']} ({$requester['username_email']})</td></tr>
        <tr><td><strong>Department:</strong></td><td>{$requester['department_code']}</td></tr>
        <tr><td><strong>College:</strong></td><td>{$department['college']}</td></tr>
        <tr><td><strong>Academic Year:</strong></td><td>{$request['academic_year']}</td></tr>
        <tr><td><strong>Submission Date:</strong></td><td>{$request['timestamp']}</td></tr>
        <tr><td><strong>Request Title:</strong></td><td><em>Not yet in DB</em></td></tr>
        <tr><td><strong>Justification:</strong></td><td><em>Not yet in DB</em></td></tr>
        <tr><td><strong>Attached Budget Deck:</strong></td><td><em>Not yet in DB</em></td></tr>
HTML;

echo "<tr class='total-budget-row'><td colspan='2'>Total Proposed Budget: ₱" . number_format($request['proposed_budget'], 2) . "</td></tr>";

echo "</table>";
echo <<<HTML
    <div class='section-title'>Line Item Details</div>
    <table class="line-table">
        <thead>
            <tr>
                <th>Row</th>
                <th>GL Code</th>
                <th>Description</th>
                <th>Amount</th>
                <th>Fund Account</th>
                <th>Fund Name</th>
            </tr>
        </thead>
        <tbody>
HTML;

foreach ($entries as $entry) {
    echo "<tr>
        <td>{$entry['row_num']}</td>
        <td>{$entry['gl_code']}</td>
        <td>{$entry['budget_description']}</td>
        <td>₱" . number_format($entry['amount'], 2) . "</td>
        <td>{$entry['fund_account']}</td>
        <td>{$entry['fund_name']}</td>
    </tr>";
}

echo <<<HTML
        </tbody>
    </table>

    <div class='section-title'>Approval Actions</div>
    <textarea style="width: 100%; height: 100px;" placeholder="Enter your comments here..."></textarea><br/>
    <div class='btn-group'>
        <button class='btn-approve'>Approve</button>
        <button class='btn-reject'>Reject</button>
        <button class='btn-revision'>Request Revision</button>
    </div>

    <div class='section-title'>History & Comments Trail</div>

    <div class='history-entry'>
        <p><span class='history-date'>May 23, 2025, 10:05 AM:</span> Submitted by Juan Dela Cruz.</p>
    </div>

    <div class='history-entry'>
        <p><span class='history-date'>May 23, 2025, 02:30 PM:</span> Viewed by Maria Garcia (Budget Unit Staff).</p>
        <div class='history-comment'>
            <p><strong><em>Action:</em></strong> Approved by Budget Unit Staff.</p>
            <p><strong><em>Comments:</em></strong> Initial review complete. All documents seem to be in order. Forwarding to Head.</p>
        </div>
    </div>

    <div class='history-entry'>
        <p><span class='history-date'>May 23, 2025, 03:50 PM:</span> Viewed by Jirk Miranda.</p>
    </div>

</div>
</div>
HTML;
?>
