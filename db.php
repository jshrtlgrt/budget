<?php
$host = "localhost";
$user = "root";
$password = "";
$database = "budget_database_schema";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
