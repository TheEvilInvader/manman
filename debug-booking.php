<?php
// debug-booking.php - Debug booking issues
require_once 'config.php';
requireRole('mentee');

$mysqli = getDB();
$user_id = getUserId();

echo "<h2>Debug Information</h2>";

// Check mentee profile
$stmt = $mysqli->prepare("SELECT * FROM mentee_profiles WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$mentee_profile = $result->fetch_assoc();
$stmt->close();

echo "<h3>Mentee Profile:</h3>";
if ($mentee_profile) {
    echo "<pre>";
    print_r($mentee_profile);
    echo "</pre>";
} else {
    echo "<p style='color: red;'>NO MENTEE PROFILE FOUND! This is the issue.</p>";
    echo "<p>User ID: " . $user_id . "</p>";
}

// Check if POST data exists
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>POST Data Received:</h3>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    $mentor_id = intval($_POST['mentor_id'] ?? 0);
    $selected_day = $_POST['selected_day'] ?? '';
    $selected_time = $_POST['selected_time'] ?? '';
    
    echo "<h3>Processed Values:</h3>";
    echo "Mentor ID: " . $mentor_id . "<br>";
    echo "Selected Day: " . $selected_day . "<br>";
    echo "Selected Time: " . $selected_time . "<br>";
    
    if (empty($selected_day) || empty($selected_time)) {
        echo "<p style='color: red;'>ERROR: Day or time is empty!</p>";
    }
} else {
    echo "<h3>No POST data - form not submitted</h3>";
}

echo "<br><br><a href='mentee-dashboard.php'>Back to Dashboard</a>";
?>
