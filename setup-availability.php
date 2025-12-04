<?php
// setup-availability.php - Create availability table and populate with default data
require_once 'config.php';

$mysqli = getDB();

// Create mentor_availability table
$sql = "CREATE TABLE IF NOT EXISTS mentor_availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mentor_id INT NOT NULL,
    day_of_week VARCHAR(20) NOT NULL,
    time_slot TIME NOT NULL,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mentor_id) REFERENCES mentor_profiles(id) ON DELETE CASCADE,
    UNIQUE KEY unique_slot (mentor_id, day_of_week, time_slot)
)";

if ($mysqli->query($sql)) {
    echo "<h2>✅ Table created successfully!</h2>";
} else {
    echo "<h2>❌ Error creating table: " . $mysqli->error . "</h2>";
    exit;
}

// Insert default 9:00 AM slots for all mentors
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
$result = $mysqli->query("SELECT id FROM mentor_profiles");
$mentors = $result->fetch_all(MYSQLI_ASSOC);

$inserted = 0;
foreach ($mentors as $mentor) {
    foreach ($days as $day) {
        $stmt = $mysqli->prepare("INSERT IGNORE INTO mentor_availability (mentor_id, day_of_week, time_slot) VALUES (?, ?, '09:00:00')");
        $stmt->bind_param("is", $mentor['id'], $day);
        if ($stmt->execute()) {
            $inserted++;
        }
        $stmt->close();
    }
}

echo "<h2>✅ Inserted $inserted default time slots!</h2>";
echo "<p>Each mentor now has 9:00 AM available Monday through Friday.</p>";
echo "<br><a href='mentee-dashboard.php'>Go to Dashboard</a>";

$mysqli->close();
?>
