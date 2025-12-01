<?php
/**
 * MySQLi Conversion Reference Guide
 * Use these patterns to convert remaining files from PDO to MySQLi
 */

// ============================================
// PATTERN 1: Simple SELECT
// ============================================

// OLD (PDO):
$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

// NEW (MySQLi):
$mysqli = getDB();
$stmt = $mysqli->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();


// ============================================
// PATTERN 2: SELECT with fetchAll
// ============================================

// OLD (PDO):
$stmt = $pdo->prepare("SELECT * FROM categories ORDER BY name");
$stmt->execute();
$categories = $stmt->fetchAll();

// NEW (MySQLi):
$stmt = $mysqli->prepare("SELECT * FROM categories ORDER BY name");
$stmt->execute();
$result = $stmt->get_result();
$categories = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();


// ============================================
// PATTERN 3: INSERT with lastInsertId
// ============================================

// OLD (PDO):
$stmt = $pdo->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, ?)");
$stmt->execute([$email, $password_hash, $role]);
$user_id = $pdo->lastInsertId();

// NEW (MySQLi):
$stmt = $mysqli->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $email, $password_hash, $role);
$stmt->execute();
$user_id = $mysqli->insert_id;
$stmt->close();


// ============================================
// PATTERN 4: UPDATE
// ============================================

// OLD (PDO):
$stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
$stmt->execute([$status, $user_id]);

// NEW (MySQLi):
$stmt = $mysqli->prepare("UPDATE users SET status = ? WHERE id = ?");
$stmt->bind_param("si", $status, $user_id);
$stmt->execute();
$stmt->close();


// ============================================
// PATTERN 5: DELETE
// ============================================

// OLD (PDO):
$stmt = $pdo->prepare("DELETE FROM sessions WHERE id = ?");
$stmt->execute([$session_id]);

// NEW (MySQLi):
$stmt = $mysqli->prepare("DELETE FROM sessions WHERE id = ?");
$stmt->bind_param("i", $session_id);
$stmt->execute();
$stmt->close();


// ============================================
// PATTERN 6: Transactions
// ============================================

// OLD (PDO):
$pdo->beginTransaction();
try {
    // queries here
    $pdo->commit();
} catch(PDOException $e) {
    $pdo->rollBack();
    throw $e;
}

// NEW (MySQLi):
$mysqli->begin_transaction();
try {
    // queries here
    $mysqli->commit();
} catch(Exception $e) {
    $mysqli->rollback();
    throw $e;
}


// ============================================
// PATTERN 7: Multiple Parameters
// ============================================

// OLD (PDO):
$stmt = $pdo->prepare("INSERT INTO mentor_profiles (user_id, full_name, bio, skills, hourly_rate) VALUES (?, ?, ?, ?, ?)");
$stmt->execute([$user_id, $full_name, $bio, $skills, $hourly_rate]);

// NEW (MySQLi):
$stmt = $mysqli->prepare("INSERT INTO mentor_profiles (user_id, full_name, bio, skills, hourly_rate) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("isssd", $user_id, $full_name, $bio, $skills, $hourly_rate);
$stmt->execute();
$stmt->close();


// ============================================
// PARAMETER TYPES FOR bind_param()
// ============================================
/*
"i" = integer
"d" = double/float
"s" = string
"b" = blob

Examples:
- bind_param("s", $string)
- bind_param("i", $integer)
- bind_param("d", $float)
- bind_param("si", $string, $integer)
- bind_param("sss", $str1, $str2, $str3)
- bind_param("isssd", $int, $str1, $str2, $str3, $double)
*/


// ============================================
// PATTERN 8: Query without prepare (for simple queries)
// ============================================

// OLD (PDO):
$result = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// NEW (MySQLi):
$result = $mysqli->query("SELECT COUNT(*) as count FROM users");
$row = $result->fetch_assoc();
$count = $row['count'];


// ============================================
// PATTERN 9: File Upload with UPDATE
// ============================================

// Example from mentor-dashboard.php
$stmt = $mysqli->prepare("UPDATE mentor_profiles SET full_name=?, bio=?, skills=?, experience=?, hourly_rate=?, profile_image=? WHERE user_id=?");
$stmt->bind_param("ssssdsi", $full_name, $bio, $skills, $experience, $hourly_rate, $profile_image, $user_id);
$stmt->execute();
$stmt->close();


// ============================================
// PATTERN 10: Complex JOIN Query
// ============================================

$sql = "SELECT mp.*, GROUP_CONCAT(DISTINCT c.name) as category_names, GROUP_CONCAT(DISTINCT c.icon) as category_icons
        FROM mentor_profiles mp
        LEFT JOIN mentor_categories mc ON mp.id = mc.mentor_id
        LEFT JOIN categories c ON mc.category_id = c.id
        WHERE mp.id = ? AND mp.status = 'approved'
        GROUP BY mp.id";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $mentor_id);
$stmt->execute();
$result = $stmt->get_result();
$mentor = $result->fetch_assoc();
$stmt->close();


// ============================================
// Error Handling
// ============================================

// Check connection
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Check query execution
if (!$stmt->execute()) {
    echo "Error: " . $stmt->error;
}

// Always close statements and connections
$stmt->close();
$mysqli->close(); // Only close connection at end of script


// ============================================
// COMMON MISTAKES TO AVOID
// ============================================

/*
1. Don't forget to bind parameters!
   WRONG: $stmt->execute();
   RIGHT: $stmt->bind_param("s", $email); $stmt->execute();

2. Don't forget get_result() for SELECT queries
   WRONG: $user = $stmt->fetch_assoc();
   RIGHT: $result = $stmt->get_result(); $user = $result->fetch_assoc();

3. Match parameter types correctly
   WRONG: bind_param("s", $user_id); // user_id is integer
   RIGHT: bind_param("i", $user_id);

4. Don't mix fetch() and fetch_assoc()
   PDO uses fetch(), MySQLi uses fetch_assoc()

5. Always close statements
   $stmt->close();
*/

?>
