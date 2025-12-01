<?php
// dashboard.php - Main Dashboard Router
require_once 'config.php';
requireLogin();

$role = getUserRole();

// Redirect based on user role
switch ($role) {
    case 'mentor':
        redirect('mentor-dashboard.php');
        break;
    case 'mentee':
        redirect('mentee-dashboard.php');
        break;
    case 'admin':
        redirect('admin-dashboard.php');
        break;
    default:
        session_destroy();
        redirect('login.php');
}
?>