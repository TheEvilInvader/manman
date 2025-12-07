<?php
// manage-availability.php - Mentor Availability Management
require_once 'config.php';
requireRole('mentor');

$mysqli = getDB();
$user_id = getUserId();

// Get mentor profile
$stmt = $mysqli->prepare("SELECT * FROM mentor_profiles WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$mentor = $result->fetch_assoc();
$stmt->close();

if (!$mentor) {
    redirect('mentor-profile.php');
}

// Check if mentor is approved
if ($mentor['status'] !== 'approved') {
    $_SESSION['error'] = 'You must be approved by admin before managing availability.';
    redirect('mentor-profile.php');
}

$success = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $day = $_POST['day'] ?? '';
        $time = $_POST['time'] ?? '';
        
        if (!empty($day) && !empty($time)) {
            $time_formatted = $time . ':00';
            
            // Check for conflicts (slots must be at least 1 hour apart)
            $stmt = $mysqli->prepare("
                SELECT TIME_FORMAT(time_slot, '%H:%i') as existing_time
                FROM mentor_availability
                WHERE mentor_id = ? AND day_of_week = ?
            ");
            $stmt->bind_param("is", $mentor['id'], $day);
            $stmt->execute();
            $result = $stmt->get_result();
            $existing_times = [];
            while ($row = $result->fetch_assoc()) {
                $existing_times[] = $row['existing_time'];
            }
            $stmt->close();
            
            // Check if new slot conflicts with existing ones
            $conflict = false;
            $new_hour = intval(substr($time, 0, 2));
            foreach ($existing_times as $existing) {
                $existing_hour = intval(substr($existing, 0, 2));
                if (abs($new_hour - $existing_hour) < 1) {
                    $conflict = true;
                    break;
                }
            }
            
            if ($conflict) {
                $error = 'Time slots must be at least 1 hour apart!';
            } else {
                $stmt = $mysqli->prepare("INSERT IGNORE INTO mentor_availability (mentor_id, day_of_week, time_slot, is_available) VALUES (?, ?, ?, 1)");
                $stmt->bind_param("iss", $mentor['id'], $day, $time_formatted);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $success = 'Time slot added successfully!';
                } else {
                    $error = 'This time slot already exists or could not be added.';
                }
                $stmt->close();
            }
        }
    } elseif ($action === 'delete') {
        $slot_id = intval($_POST['slot_id']);
        $stmt = $mysqli->prepare("DELETE FROM mentor_availability WHERE id = ? AND mentor_id = ?");
        $stmt->bind_param("ii", $slot_id, $mentor['id']);
        $stmt->execute();
        $stmt->close();
        $success = 'Time slot removed successfully!';
    } elseif ($action === 'toggle') {
        $slot_id = intval($_POST['slot_id']);
        $stmt = $mysqli->prepare("UPDATE mentor_availability SET is_available = NOT is_available WHERE id = ? AND mentor_id = ?");
        $stmt->bind_param("ii", $slot_id, $mentor['id']);
        $stmt->execute();
        $stmt->close();
        $success = 'Availability updated!';
    }
}

// Get all availability slots with booking status
$stmt = $mysqli->prepare("
    SELECT ma.id, ma.day_of_week, TIME_FORMAT(ma.time_slot, '%H:%i') as time_slot, ma.is_available,
           COUNT(CASE WHEN s.status IN ('pending', 'confirmed') THEN 1 END) as has_booking,
           COUNT(CASE WHEN s.status = 'completed' AND f.id IS NULL THEN 1 END) as awaiting_feedback
    FROM mentor_availability ma
    LEFT JOIN sessions s ON ma.mentor_id = s.mentor_id 
        AND DAYNAME(s.scheduled_at) = ma.day_of_week 
        AND TIME_FORMAT(s.scheduled_at, '%H:%i') = TIME_FORMAT(ma.time_slot, '%H:%i')
    LEFT JOIN feedback f ON s.id = f.session_id
    WHERE ma.mentor_id = ?
    GROUP BY ma.id, ma.day_of_week, ma.time_slot, ma.is_available
    ORDER BY FIELD(ma.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), ma.time_slot
");
$stmt->bind_param("i", $mentor['id']);
$stmt->execute();
$result = $stmt->get_result();
$slots = $result->fetch_all(MYSQLI_ASSOC);
$result->free();
$stmt->close();

// Organize by day
$slots_by_day = [];
foreach ($slots as $slot) {
    $slots_by_day[$slot['day_of_week']][] = $slot;
}

$mysqli->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Availability - MentorBridge</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #1e1b4b 100%);
            min-height: 100vh;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        body::-webkit-scrollbar {
            width: 8px;
        }

        body::-webkit-scrollbar-track {
            background: rgba(30, 27, 75, 0.5);
        }

        body::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #6366f1 0%, #8b5cf6 100%);
            border-radius: 4px;
        }

        body::before {
            content: '';
            position: fixed;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.15), transparent 70%);
            border-radius: 50%;
            top: -250px;
            right: -250px;
            animation: float 20s ease-in-out infinite;
            z-index: 0;
        }

        body::after {
            content: '';
            position: fixed;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.15), transparent 70%);
            border-radius: 50%;
            bottom: -200px;
            left: -200px;
            animation: float 15s ease-in-out infinite reverse;
            z-index: 0;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(50px, 50px); }
        }

        .nav-bar {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            padding: 1.25rem 2.5rem;
            border-radius: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            box-shadow: 0 0 40px rgba(139, 92, 246, 0.2), inset 0 0 0 1px rgba(139, 92, 246, 0.1);
            position: relative;
            z-index: 100;
        }

        .logo {
            font-size: 1.75rem;
            font-weight: 900;
            background: linear-gradient(135deg, #a78bfa, #c4b5fd);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 0 20px rgba(139, 92, 246, 0.5));
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
            z-index: 10;
        }

        .header {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            padding: 2rem;
            border-radius: 24px;
            margin-bottom: 2rem;
            box-shadow: 0 0 40px rgba(139, 92, 246, 0.2), inset 0 0 0 1px rgba(139, 92, 246, 0.1);
            border: 1px solid rgba(139, 92, 246, 0.2);
        }

        .header h1 {
            background: linear-gradient(135deg, #a78bfa, #c4b5fd);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
            font-size: 2.5rem;
            font-weight: 900;
        }

        .header p {
            color: #94a3b8;
        }

        .alert {
            padding: 1.1rem 1.5rem;
            border-radius: 14px;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(10px);
            font-weight: 500;
            border: 1px solid;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.15);
            color: #86efac;
            border-color: rgba(34, 197, 94, 0.3);
            box-shadow: 0 0 30px rgba(34, 197, 94, 0.2);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            border-color: rgba(239, 68, 68, 0.3);
            box-shadow: 0 0 30px rgba(239, 68, 68, 0.2);
        }

        .add-slot-form {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            padding: 2rem;
            border-radius: 24px;
            margin-bottom: 2rem;
            box-shadow: 0 0 40px rgba(139, 92, 246, 0.2), inset 0 0 0 1px rgba(139, 92, 246, 0.1);
            border: 1px solid rgba(139, 92, 246, 0.2);
        }

        .add-slot-form h2 {
            background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            font-weight: 800;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.75rem;
            color: #c7d2fe;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr 150px;
            gap: 1rem;
            align-items: end;
        }

        select, input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid rgba(139, 92, 246, 0.3);
            border-radius: 12px;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(30, 27, 75, 0.6);
            color: #e0e7ff;
        }

        select:focus, input:focus {
            outline: none;
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
            background: rgba(30, 27, 75, 0.8);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
            color: white;
            box-shadow: 0 4px 15px rgba(99, 142, 203, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(99, 142, 203, 0.4);
        }

        .btn-secondary {
            background: var(--color-bg-light);
            color: var(--color-primary-dark);
        }

        .btn-secondary:hover {
            background: var(--color-bg-lighter);
        }

        .btn-danger {
            background: #ef4444;
            color: white;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }

        .btn-toggle {
            background: #f59e0b;
            color: white;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }

        .slots-section {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(99, 142, 203, 0.08);
        }

        .slots-section h2 {
            color: var(--color-primary-dark);
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
        }

        .day-group {
            margin-bottom: 2rem;
        }

        .day-header {
            font-weight: 700;
            color: var(--color-primary-dark);
            font-size: 1.1rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--color-bg-light);
        }

        .slots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
        }

        .slot-card {
            background: var(--color-bg-light);
            padding: 1rem;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .slot-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(99, 142, 203, 0.15);
        }

        .slot-card.unavailable {
            opacity: 0.6;
            background: #fee2e2;
        }

        .slot-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .slot-time {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--color-primary-dark);
            white-space: nowrap;
        }

        .slot-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-available {
            background: #d1fae5;
            color: #065f46;
        }

        .status-booked {
            background: #fef3c7;
            color: #92400e;
        }

        .status-disabled {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-feedback {
            background: #fef3c7;
            color: #92400e;
        }

        .slot-actions {
            display: flex;
            gap: 0.5rem;
        }

        .no-slots {
            text-align: center;
            padding: 3rem;
            color: #64748b;
        }

        .no-slots-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .slots-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="nav-bar">
        <div class="logo">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="width: 32px; height: 32px; display: inline-block; vertical-align: middle; margin-right: 8px;">
                <path d="M12 2L14 8L20 10L14 12L12 18L10 12L4 10L10 8L12 2Z" stroke="url(#logoGradient)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M16 6L17 8L19 9L17 10L16 12L15 10L13 9L15 8L16 6Z" stroke="url(#logoGradient)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                <defs>
                    <linearGradient id="logoGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" style="stop-color:#6366f1;stop-opacity:1" />
                        <stop offset="100%" style="stop-color:#8b5cf6;stop-opacity:1" />
                    </linearGradient>
                </defs>
            </svg>
            MentorBridge
        </div>
        <a href="mentor-dashboard.php" class="btn btn-secondary" style="padding: 0.75rem 1.5rem; background: rgba(139, 92, 246, 0.2); border: 2px solid rgba(139, 92, 246, 0.4); color: #c4b5fd; font-weight: 600; box-shadow: 0 0 20px rgba(139, 92, 246, 0.2);">← Back to Dashboard</a>
    </nav>

    <div class="container">
        <div class="header">
            <h1>Manage Your Availability</h1>
            <p>Add or remove time slots to control when mentees can book sessions with you</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="add-slot-form">
            <h2>Add New Time Slot</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-row">
                    <div class="form-group">
                        <label>Day of Week</label>
                        <select name="day" required>
                            <option value="">Select Day</option>
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                            <option value="Saturday">Saturday</option>
                            <option value="Sunday">Sunday</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Time</label>
                        <input type="time" name="time" required>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Add Slot</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="slots-section">
            <h2>Your Time Slots</h2>

            <?php if (empty($slots)): ?>
                <div class="no-slots">
                    <div class="no-slots-icon">🕐</div>
                    <p><strong>No time slots available</strong></p>
                    <p>Add your first time slot above to start accepting bookings</p>
                </div>
            <?php else: ?>
                <?php 
                $all_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                foreach ($all_days as $day): 
                    if (isset($slots_by_day[$day])):
                ?>
                    <div class="day-group">
                        <div class="day-header"><?php echo $day; ?></div>
                        <div class="slots-grid">
                            <?php foreach ($slots_by_day[$day] as $slot): ?>
                                <div class="slot-card <?php echo $slot['is_available'] ? '' : 'unavailable'; ?>">
                                    <div class="slot-info">
                                        <div class="slot-time">
                                            <?php 
                                            $start = $slot['time_slot'];
                                            $end_hour = intval(substr($start, 0, 2)) + 1;
                                            $end = str_pad($end_hour, 2, '0', STR_PAD_LEFT) . substr($start, 2);
                                            echo $start . ' - ' . $end;
                                            ?>
                                        </div>
                                        <span class="slot-status <?php 
                                            if ($slot['awaiting_feedback'] > 0) {
                                                echo 'status-feedback';
                                            } elseif ($slot['has_booking'] > 0) {
                                                echo 'status-booked';
                                            } else {
                                                echo $slot['is_available'] ? 'status-available' : 'status-disabled';
                                            }
                                        ?>">
                                            <?php 
                                            if ($slot['awaiting_feedback'] > 0) {
                                                echo 'Waiting for Feedback';
                                            } elseif ($slot['has_booking'] > 0) {
                                                echo 'Booked';
                                            } else {
                                                echo $slot['is_available'] ? 'Available' : 'Disabled';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <div class="slot-actions">
                                        <?php if ($slot['has_booking'] == 0 && $slot['awaiting_feedback'] == 0): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="slot_id" value="<?php echo $slot['id']; ?>">
                                            <button type="submit" class="btn btn-toggle" title="Toggle availability">
                                                <?php echo $slot['is_available'] ? 'Disable' : 'Enable'; ?>
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this time slot?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="slot_id" value="<?php echo $slot['id']; ?>">
                                            <button type="submit" class="btn btn-danger">Delete</button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php 
                    endif;
                endforeach; 
                ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
