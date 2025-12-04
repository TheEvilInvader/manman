<?php
// book-session.php - Session Booking & Payment
require_once 'config.php';
requireRole('mentee');

$mysqli = getDB();
$user_id = getUserId();

// Get mentee profile
$stmt = $mysqli->prepare("SELECT * FROM mentee_profiles WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$mentee_profile = $result->fetch_assoc();
$stmt->close();

if (!$mentee_profile) {
    $_SESSION['error'] = 'Please complete your profile first';
    redirect('mentee-dashboard.php');
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mentor_id = intval($_POST['mentor_id'] ?? 0);
    $selected_day = sanitize($_POST['selected_day'] ?? '');
    $selected_time = sanitize($_POST['selected_time'] ?? '');
    
    if (empty($selected_day) || empty($selected_time)) {
        $_SESSION['error'] = 'Please select a date and time';
        redirect('metnor-detail.php?id=' . $mentor_id);
    } else {
        // Get mentor details
        $stmt = $mysqli->prepare("SELECT * FROM mentor_profiles WHERE id = ?");
        $stmt->bind_param("i", $mentor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $mentor = $result->fetch_assoc();
        $stmt->close();
        
        if (!$mentor) {
            $_SESSION['error'] = 'Mentor not found';
            redirect('mentee-dashboard.php');
        } else {
            // Calculate next occurrence of selected day
            $days_map = [
                'Monday' => 1,
                'Tuesday' => 2,
                'Wednesday' => 3,
                'Thursday' => 4,
                'Friday' => 5,
                'Saturday' => 6,
                'Sunday' => 0
            ];
            
            $target_day = $days_map[$selected_day];
            $current_day = date('w');
            $days_ahead = ($target_day - $current_day + 7) % 7;
            if ($days_ahead == 0) $days_ahead = 7;
            
            $scheduled_date = date('Y-m-d', strtotime("+$days_ahead days"));
            $scheduled_datetime = $scheduled_date . ' ' . $selected_time . ':00';
            
            // Create session
            $status = 'pending';
            $payment_status = 'pending';
            $stmt = $mysqli->prepare("
                INSERT INTO sessions (mentor_id, mentee_id, scheduled_at, amount, status, payment_status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iisdss", $mentor_id, $mentee_profile['id'], $scheduled_datetime, $mentor['hourly_rate'], $status, $payment_status);
            
            if ($stmt->execute()) {
                $session_id = $mysqli->insert_id;
                $stmt->close();
                
                // Mark the time slot as unavailable
                $stmt = $mysqli->prepare("
                    UPDATE mentor_availability 
                    SET is_available = 0 
                    WHERE mentor_id = ? AND day_of_week = ? AND time_slot = ?
                ");
                $time_slot_formatted = $selected_time . ':00';
                $stmt->bind_param("iss", $mentor_id, $selected_day, $time_slot_formatted);
                $stmt->execute();
                $stmt->close();
                
                // Store session info in session for payment page
                $_SESSION['pending_booking'] = [
                    'session_id' => $session_id,
                    'mentor_name' => $mentor['full_name'],
                    'scheduled_at' => $scheduled_datetime,
                    'amount' => $mentor['hourly_rate']
                ];
                
                redirect('payment.php?session_id=' . $session_id);
            } else {
                $stmt->close();
                $_SESSION['error'] = 'Booking failed: ' . $mysqli->error;
                redirect('metnor-detail.php?id=' . $mentor_id);
            }
        }
    }
}

// If GET request with mentor_id, show form
$mentor_id = intval($_GET['mentor_id'] ?? 0);
if ($mentor_id) {
    $stmt = $mysqli->prepare("SELECT * FROM mentor_profiles WHERE id = ?");
    $stmt->bind_param("i", $mentor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $mentor = $result->fetch_assoc();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Session - MentorBridge</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 3rem;
            border-radius: 20px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #667eea;
            text-align: center;
            margin-bottom: 2rem;
        }
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 2px solid #fca5a5;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Booking Session...</h1>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
            <a href="mentor-detail.php?id=<?php echo $mentor_id; ?>" style="color: #667eea;">← Go Back</a>
        <?php endif; ?>
    </div>
</body>
</html>
