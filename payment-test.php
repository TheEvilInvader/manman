<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payment Test</title>
</head>
<body>
    <h1>Payment Page - TEST</h1>
    <p>If you can see this, the page is loading correctly.</p>
    
    <h3>Session Data:</h3>
    <pre><?php print_r($_SESSION); ?></pre>
    
    <h3>User Info:</h3>
    <?php
    if (isset($_SESSION['user_id'])) {
        echo "User ID: " . $_SESSION['user_id'] . "<br>";
        echo "Role: " . ($_SESSION['role'] ?? 'not set') . "<br>";
    } else {
        echo "Not logged in";
    }
    ?>
    
    <br><br>
    <a href="mentee-dashboard.php">Back to Dashboard</a>
</body>
</html>
