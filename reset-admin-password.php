<?php
// reset-admin-password.php - Reset Admin Password
require_once 'config.php';

$mysqli = getDB();

// Generate new password hash
$new_password = 'admin';
$password_hash = password_hash($new_password, PASSWORD_DEFAULT);

// Update both admin users
$stmt = $mysqli->prepare("UPDATE users SET password = ? WHERE role = 'admin'");
$stmt->bind_param("s", $password_hash);

if ($stmt->execute()) {
    $affected = $stmt->affected_rows;
    echo "<h2>✅ Password Reset Successful!</h2>";
    echo "<p>Updated {$affected} admin user(s)</p>";
    echo "<hr>";
    echo "<h3>Login Credentials:</h3>";
    echo "<p><strong>Email:</strong> admin@mentorbridge.com</p>";
    echo "<p><strong>Password:</strong> admin</p>";
    echo "<p>OR</p>";
    echo "<p><strong>Email:</strong> admin2@mentorbridge.com</p>";
    echo "<p><strong>Password:</strong> admin</p>";
    echo "<hr>";
    echo "<p>Generated hash: " . $password_hash . "</p>";
    echo "<br><a href='login.php' style='padding: 10px 20px; background: #395886; color: white; text-decoration: none; border-radius: 5px;'>Go to Login Page</a>";
} else {
    echo "<h2>❌ Error updating password</h2>";
    echo "<p>" . $mysqli->error . "</p>";
}

$stmt->close();
$mysqli->close();
?>
