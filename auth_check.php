<?php
session_start();

function requireLogin() {
    if (!isset($_SESSION['username'])) {
        header("Location: login.php");
        exit();
    }
}

function requireRole($roles = []) {
    requireLogin(); // Always check login first

    $userRole = $_SESSION['role'] ?? null;

    if (!in_array($userRole, $roles)) {
        header("Location: unauthorized.php");
        exit();
    }
}
?>