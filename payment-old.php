<?php
// ============================================
// payment.php - Payment Processing (Placeholder)
// ============================================

require_once 'config.php';
requireLogin();

$mysqli = getDB();
$session_id = intval($_GET['session_id'] ?? 0);

// Default session for testing/viewing
$session = [
    'id' => 0,
    'mentor_name' => 'Sample Mentor',
    'scheduled_at' => date('Y-m-d H:i:s', strtotime('+7 days 10:00')),
    'amount' => 50.00
];

if ($session_id) {
    // Get session details
    $stmt = $mysqli->prepare("
        SELECT s.*, mp.full_name as mentor_name
        FROM sessions s
        JOIN mentor_profiles mp ON s.mentor_id = mp.id
        WHERE s.id = ?
    ");
    $stmt->bind_param("i", $session_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $real_session = $result->fetch_assoc();
    $stmt->close();
    
    if ($real_session) {
        $session = $real_session;
    }
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update session status (in production, integrate real payment gateway)
    $stmt = $mysqli->prepare("
        UPDATE sessions 
        SET status = 'confirmed', payment_status = 'paid' 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $session_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        unset($_SESSION['pending_booking']);
        $_SESSION['booking_success'] = true;
        
        redirect('my-sessions.php');
    } else {
        $stmt->close();
        $error = 'Payment processing failed';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - MentorBridge</title>
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
        .payment-container {
            background: white;
            padding: 3rem;
            border-radius: 20px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #667eea;
            text-align: center;
            margin-bottom: 2rem;
        }
        .booking-details {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            color: #666;
        }
        .detail-row strong {
            color: #333;
        }
        .total {
            font-size: 1.5rem;
            color: #667eea;
            font-weight: bold;
            padding-top: 1rem;
            border-top: 2px solid #e0e0e0;
        }
        .payment-note {
            background: #fff3cd;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            color: #856404;
            text-align: center;
        }
        .btn {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <h1>💳 Payment</h1>
        
        <div class="booking-details">
            <div class="detail-row">
                <span>Mentor:</span>
                <strong><?php echo htmlspecialchars($session['mentor_name']); ?></strong>
            </div>
            <div class="detail-row">
                <span>Date & Time:</span>
                <strong><?php echo date('M d, Y - H:i', strtotime($session['scheduled_at'])); ?></strong>
            </div>
            <div class="detail-row">
                <span>Duration:</span>
                <strong>1 hour</strong>
            </div>
            <div class="detail-row total">
                <span>Total Amount:</span>
                <span>$<?php echo number_format($session['amount'], 2); ?></span>
            </div>
        </div>

        <div class="payment-note">
            ℹ️ This is a demo. In production, integrate with Stripe, PayPal, or other payment gateways.
        </div>

        <form method="POST">
            <button type="submit" class="btn">
                ✓ Confirm Payment & Book Session
            </button>
        </form>
    </div>
</body>
</html>
*/

// ============================================
// admin-dashboard.php - Admin Panel
// ============================================
/*
<?php
require_once 'config.php';
requireRole('admin');

$pdo = getDB();

// Get pending mentors
$pending_mentors = $pdo->query("
    SELECT mp.*, u.email 
    FROM mentor_profiles mp 
    JOIN users u ON mp.user_id = u.id 
    WHERE mp.status = 'pending' 
    ORDER BY mp.created_at DESC
")->fetchAll();

// Handle mentor approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $mentor_id = intval($_POST['mentor_id']);
    $action = $_POST['action'];
    
    $new_status = ($action === 'approve') ? 'approved' : 'rejected';
    
    $stmt = $pdo->prepare("UPDATE mentor_profiles SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $mentor_id]);
    
    redirect('admin-dashboard.php');
}

// Get statistics
$stats = [
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'total_mentors' => $pdo->query("SELECT COUNT(*) FROM mentor_profiles WHERE status = 'approved'")->fetchColumn(),
    'total_mentees' => $pdo->query("SELECT COUNT(*) FROM mentee_profiles")->fetchColumn(),
    'total_sessions' => $pdo->query("SELECT COUNT(*) FROM sessions")->fetchColumn(),
    'pending_mentors' => count($pending_mentors)
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MentorBridge</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .nav-bar {
            background: white;
            padding: 1rem 2rem;
            border-radius: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .container { max-width: 1400px; margin: 0 auto; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
        }
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #667eea;
        }
        .stat-label {
            color: #666;
            margin-top: 0.5rem;
        }
        .section {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }
        .section h2 {
            color: #667eea;
            margin-bottom: 1.5rem;
        }
        .mentor-item {
            border: 2px solid #f0f0f0;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1rem;
        }
        .mentor-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }
        .mentor-info h3 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        .mentor-actions {
            display: flex;
            gap: 0.5rem;
        }
        .btn {
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-approve {
            background: #10b981;
            color: white;
        }
        .btn-reject {
            background: #ef4444;
            color: white;
        }
        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }
    </style>
</head>
<body>
    <nav class="nav-bar">
        <div class="logo">🎓 MentorBridge Admin</div>
        <a href="logout.php" class="btn btn-secondary">Logout</a>
    </nav>

    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_mentors']; ?></div>
                <div class="stat-label">Active Mentors</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_mentees']; ?></div>
                <div class="stat-label">Mentees</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_sessions']; ?></div>
                <div class="stat-label">Total Sessions</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['pending_mentors']; ?></div>
                <div class="stat-label">Pending Approvals</div>
            </div>
        </div>

        <div class="section">
            <h2>⏳ Pending Mentor Approvals</h2>
            <?php if (empty($pending_mentors)): ?>
                <p style="color: #666;">No pending approvals</p>
            <?php else: ?>
                <?php foreach ($pending_mentors as $mentor): ?>
                    <div class="mentor-item">
                        <div class="mentor-header">
                            <div class="mentor-info">
                                <h3><?php echo htmlspecialchars($mentor['full_name']); ?></h3>
                                <p style="color: #666;"><?php echo htmlspecialchars($mentor['email']); ?></p>
                                <p style="color: #666; margin-top: 0.5rem;">
                                    <strong>Skills:</strong> <?php echo htmlspecialchars($mentor['skills']); ?>
                                </p>
                            </div>
                            <div class="mentor-actions">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="mentor_id" value="<?php echo $mentor['id']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-approve">✓ Approve</button>
                                </form>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="mentor_id" value="<?php echo $mentor['id']; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn btn-reject">✗ Reject</button>
                                </form>
                            </div>
                        </div>
                        <p><strong>Bio:</strong> <?php echo htmlspecialchars(substr($mentor['bio'], 0, 200)); ?>...</p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
*/

// ============================================
// logout.php - Logout Handler
// ============================================
/*
<?php
session_start();
session_destroy();
header('Location: index.php');
exit;

?>