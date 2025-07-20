<?php
if (!isset($_GET['request_id'])) {
    exit("Invalid request.");
}

$pdo = new PDO("mysql:host=localhost;dbname=budget_database_schema", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$request_id = $_GET['request_id'];

$stmt = $pdo->prepare("SELECT * FROM budget_entries WHERE request_id = ?");
$stmt->execute([$request_id]);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$entries) {
    echo "No entries found.";
    exit;
}

echo "<div><strong>Request ID:</strong> $request_id</div>";
echo "<table style='width:100%; margin-top:10px; border-collapse:collapse;'>";
echo "<tr style='font-weight:bold; text-align:left;'><th>Row</th><th>GL Code</th><th>Description</th><th>Amount</th><th>Fund Account</th><th>Fund Name</th></tr>";

foreach ($entries as $entry) {
    echo "<tr class='entry-row'>
            <td>{$entry['row_num']}</td>
            <td>{$entry['gl_code']}</td>
            <td>{$entry['budget_description']}</td>
            <td>₱" . number_format($entry['amount'], 2) . "</td>
            <td>{$entry['fund_account']}</td>
            <td>{$entry['fund_name']}</td>
          </tr>";
}
echo "</table>";
?>
