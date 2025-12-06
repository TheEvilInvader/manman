<?php
// dashboard.php - Main Dashboard Router
require_once 'config.php';
requireLogin();

$role = getUserRole();

// Redirect based on user role
switch ($role) {
    case 'mentor':
        // Check if mentor is approved
        $mysqli = getDB();
        $user_id = getUserId();
        $stmt = $mysqli->prepare("SELECT status FROM mentor_profiles WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $profile = $result->fetch_assoc();
        $stmt->close();
        $mysqli->close();
        
        // Redirect to home if approved, dashboard if new/pending
        if ($profile && $profile['status'] === 'approved') {
            redirect('mentor-home.php');
        } else {
            redirect('mentor-dashboard.php');
        }
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