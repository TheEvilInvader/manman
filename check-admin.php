<?php
// check-admin.php - Check Admin User Status
require_once 'config.php';

$mysqli = getDB();

echo "<h2>Database Connection</h2>";
if ($mysqli->connect_error) {
    echo "❌ Connection failed: " . $mysqli->connect_error . "<br>";
} else {
    echo "✅ Connected to database successfully<br><br>";
}

echo "<h2>Admin Users in Database</h2>";
$result = $mysqli->query("SELECT id, email, role, status, created_at FROM users WHERE role = 'admin'");

if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Email</th><th>Role</th><th>Status</th><th>Created At</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['email'] . "</td>";
        echo "<td>" . $row['role'] . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "❌ No admin users found in database!<br>";
    echo "<p><strong>Action needed:</strong> <a href='create-admin.php'>Run create-admin.php</a> to create an admin user</p>";
}

echo "<br><h2>All Users in Database</h2>";
$result = $mysqli->query("SELECT id, email, role, status FROM users LIMIT 10");

if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Email</th><th>Role</th><th>Status</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['email'] . "</td>";
        echo "<td>" . $row['role'] . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No users found in database.";
}

$mysqli->close();
?>
