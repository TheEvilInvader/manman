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
            
            // Calculate price with 20% platform fee
            // Mentor earns their hourly_rate, mentee pays hourly_rate + 20%
            $mentee_price = $mentor['hourly_rate'] * 1.20;
            
            // Create session
            $status = 'pending';
            $payment_status = 'pending';
            $stmt = $mysqli->prepare("
                INSERT INTO sessions (mentor_id, mentee_id, scheduled_at, amount, status, payment_status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iisdss", $mentor_id, $mentee_profile['id'], $scheduled_datetime, $mentee_price, $status, $payment_status);
            
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #1e1b4b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
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
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(50px, 50px); }
        }

        .container {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            padding: 3rem;
            border-radius: 32px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 0 80px rgba(139, 92, 246, 0.2), 0 20px 60px rgba(0, 0, 0, 0.5), inset 0 0 0 1px rgba(167, 139, 250, 0.2);
            position: relative;
            z-index: 10;
        }
        h1 {
            background: linear-gradient(135deg, #a78bfa, #c4b5fd);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-align: center;
            margin-bottom: 2rem;
            font-size: 2.5rem;
            font-weight: 900;
        }
        .alert {
            padding: 1.1rem 1.5rem;
            border-radius: 14px;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(10px);
            font-weight: 500;
            border: 1px solid;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            border-color: rgba(239, 68, 68, 0.3);
            box-shadow: 0 0 30px rgba(239, 68, 68, 0.2);
        }
        a {
            color: #a78bfa;
            text-decoration: none;
            font-weight: 700;
            transition: all 0.3s ease;
        }
        a:hover {
            color: #c4b5fd;
            text-shadow: 0 0 10px rgba(167, 139, 250, 0.5);
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
