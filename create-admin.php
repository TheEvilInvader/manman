<?php
// create-admin.php - Create Admin User
require_once 'config.php';

$mysqli = getDB();

// Delete existing admin users
$mysqli->query("DELETE FROM users WHERE role = 'admin'");

// Create new admin user
$email = 'admin@mentorbridge.com';
$password = password_hash('admin', PASSWORD_DEFAULT);
$role = 'admin';
$status = 'active';

$stmt = $mysqli->prepare("INSERT INTO users (email, password, role, status) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $email, $password, $role, $status);

if ($stmt->execute()) {
    echo "<h2>✅ Admin user created successfully!</h2>";
    echo "<p><strong>Email:</strong> admin@mentorbridge.com</p>";
    echo "<p><strong>Password:</strong> admin</p>";
    echo "<br><a href='login.php'>Go to Login</a>";
} else {
    echo "<h2>❌ Error creating admin user</h2>";
    echo "<p>" . $mysqli->error . "</p>";
}

$stmt->close();
$mysqli->close();
?>
