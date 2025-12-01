<?php
// config.php - Database Configuration & Helper Functions

define('DB_HOST', 'localhost');
define('DB_NAME', 'mentorbridge');
define('DB_USER', 'root');
define('DB_PASS', '');

function getDB() {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($mysqli->connect_error) {
        die("Database connection failed: " . $mysqli->connect_error);
    }
    
    $mysqli->set_charset("utf8mb4");
    return $mysqli;
}

session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireRole($role) {
    requireLogin();
    if (getUserRole() !== $role) {
        header('Location: dashboard.php');
        exit;
    }
}

function sanitize($data) {
    $mysqli = getDB();
    return $mysqli->real_escape_string(htmlspecialchars(strip_tags(trim($data))));
}

function redirect($url) {
    header("Location: $url");
    exit;
}
?>