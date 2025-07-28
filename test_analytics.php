<?php
// Simple test page to debug analytics
session_start();

echo "<h2>Analytics Debug Page</h2>";

echo "<h3>Session Information:</h3>";
echo "<ul>";
echo "<li>Username: " . ($_SESSION['username'] ?? 'NOT SET') . "</li>";
echo "<li>Role: " . ($_SESSION['role'] ?? 'NOT SET') . "</li>";
echo "</ul>";

echo "<h3>Database Connection Test:</h3>";
try {
    $pdo = new PDO("mysql:host=localhost;dbname=budget_database_schema", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color: green;'>✅ Database connection successful!</p>";
    
    // Test basic query
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM budget_request");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>Total budget requests in database: " . $result['count'] . "</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</p>";
}

echo "<h3>Direct Analytics Access:</h3>";
echo "<p><a href='analytics.php' target='_blank'>Open Analytics Page Directly</a></p>";

echo "<h3>Test Links:</h3>";
echo "<p><a href='approver.php'>Back to Approver Dashboard</a></p>";
?>