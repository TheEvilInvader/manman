<?php
// mentor-dashboard.php - Main Dashboard for Approved Mentors
require_once 'config.php';
requireRole('mentor');

$mysqli = getDB();
$user_id = getUserId();

// Get mentor profile
$stmt = $mysqli->prepare("SELECT * FROM mentor_profiles WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc();
$result->free();
$stmt->close();

// Redirect if not approved
if ($profile['status'] !== 'approved') {
    $_SESSION['error'] = 'Please complete your profile and wait for admin approval.';
    redirect('mentor-profile.php');
}

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Handle session completion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_session'])) {
    $session_id = intval($_POST['session_id']);
    $stmt = $mysqli->prepare("UPDATE sessions SET status = 'completed' WHERE id = ? AND mentor_id = ?");
    $stmt->bind_param("ii", $session_id, $profile['id']);
    if ($stmt->execute()) {
        $success = 'Session marked as completed! Mentee can now provide feedback.';
    }
    $stmt->close();
}

// Get upcoming and pending sessions
$stmt = $mysqli->prepare("
    SELECT s.*, mp.full_name as mentee_name, mp.interests, u.email as mentee_email
    FROM sessions s
    JOIN mentee_profiles mp ON s.mentee_id = mp.id
    JOIN users u ON mp.user_id = u.id
    WHERE s.mentor_id = ? AND s.status IN ('pending', 'confirmed')
    ORDER BY s.scheduled_at ASC
");
$stmt->bind_param("i", $profile['id']);
$stmt->execute();
$result = $stmt->get_result();
$upcoming_sessions = $result->fetch_all(MYSQLI_ASSOC);
$result->free();
$stmt->close();

// Get completed sessions
$stmt = $mysqli->prepare("
    SELECT s.*, mp.full_name as mentee_name, mp.interests, u.email as mentee_email,
           f.rating, f.comment as feedback_comment
    FROM sessions s
    JOIN mentee_profiles mp ON s.mentee_id = mp.id
    JOIN users u ON mp.user_id = u.id
    LEFT JOIN feedback f ON s.id = f.session_id
    WHERE s.mentor_id = ? AND s.status = 'completed'
    ORDER BY s.scheduled_at DESC
    LIMIT 20
");
$stmt->bind_param("i", $profile['id']);
$stmt->execute();
$result = $stmt->get_result();
$completed_sessions = $result->fetch_all(MYSQLI_ASSOC);
$result->free();
$stmt->close();

// Get statistics
$stmt = $mysqli->prepare("SELECT COUNT(*) as total FROM sessions WHERE mentor_id = ? AND status = 'completed'");
$stmt->bind_param("i", $profile['id']);
$stmt->execute();
$result = $stmt->get_result();
$stats = $result->fetch_assoc();
$total_sessions = $stats['total'];
$result->free();
$stmt->close();

$stmt = $mysqli->prepare("SELECT SUM(amount) as total FROM sessions WHERE mentor_id = ? AND payment_status = 'paid'");
$stmt->bind_param("i", $profile['id']);
$stmt->execute();
$result = $stmt->get_result();
$revenue_data = $result->fetch_assoc();
// Mentor earns amount / 1.20 (removing the 20% platform fee)
$total_revenue = ($revenue_data['total'] ?? 0) / 1.20;
$result->free();
$stmt->close();

$mysqli->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentor Dashboard - MentorBridge</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-primary: #638ECB;
            --color-primary-light: #8AAEE0;
            --color-primary-dark: #395886;
            --color-bg: #F0F3FA;
            --color-bg-light: #D5DEEF;
            --color-card: #FFFFFF;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--color-bg);
            min-height: 100vh;
        }

        .navbar {
            background: var(--color-card);
            padding: 1.5rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--color-primary-dark);
        }

        .nav-buttons {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: var(--color-primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--color-primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(99, 142, 203, 0.3);
        }

        .btn-secondary {
            background: var(--color-bg-light);
            color: var(--color-primary-dark);
        }

        .btn-secondary:hover {
            background: var(--color-primary-light);
            color: white;
        }

        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .header {
            margin-bottom: 2rem;
        }

        .header h1 {
            color: var(--color-primary-dark);
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--color-card);
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .stat-card h3 {
            color: #64748b;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .stat-card .value {
            color: var(--color-primary-dark);
            font-size: 2rem;
            font-weight: 700;
        }

        .section {
            background: var(--color-card);
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        .section h2 {
            color: var(--color-primary-dark);
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }

        .session-card {
            background: var(--color-bg);
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            border-left: 4px solid var(--color-primary);
        }

        .session-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .mentee-info h3 {
            color: var(--color-primary-dark);
            margin-bottom: 0.25rem;
        }

        .mentee-info p {
            color: #64748b;
            font-size: 0.9rem;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-confirmed {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-completed {
            background: #d1fae5;
            color: #065f46;
        }

        .session-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .session-details p {
            color: #475569;
        }

        .session-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .feedback-box {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
        }

        .stars {
            color: #fbbf24;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #64748b;
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo">🎓 MentorBridge</div>
        <div class="nav-buttons">
            <a href="mentor-profile.php" class="btn btn-secondary">Profile Editing</a>
            <a href="manage-availability.php" class="btn btn-secondary">Manage Availability</a>
            <a href="logout.php" class="btn btn-primary">Logout</a>
        </div>
    </nav>

    <div class="container">
        <div class="header">
            <h1>👋 Welcome, <?php echo htmlspecialchars($profile['full_name']); ?>!</h1>
            <p style="color: #64748b;">Manage your mentorship sessions and track your progress</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Sessions</h3>
                <div class="value"><?php echo $total_sessions; ?></div>
            </div>
            <div class="stat-card">
                <h3>Upcoming Sessions</h3>
                <div class="value"><?php echo count($upcoming_sessions); ?></div>
            </div>
            <div class="stat-card">
                <h3>Average Rating</h3>
                <div class="value"><?php echo number_format($profile['average_rating'], 1); ?> ⭐</div>
            </div>
            <div class="stat-card">
                <h3>Total Earnings</h3>
                <div class="value">$<?php echo number_format($total_revenue, 2); ?></div>
            </div>
        </div>

        <div class="section">
            <h2>📅 Upcoming Sessions</h2>
            <?php if (empty($upcoming_sessions)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3>No upcoming sessions</h3>
                    <p>Your booked sessions will appear here</p>
                </div>
            <?php else: ?>
                <?php foreach ($upcoming_sessions as $session): ?>
                    <div class="session-card">
                        <div class="session-header">
                            <div class="mentee-info">
                                <h3><?php echo htmlspecialchars($session['mentee_name']); ?></h3>
                                <p><?php echo htmlspecialchars($session['mentee_email']); ?></p>
                            </div>
                            <span class="status-badge status-<?php echo $session['status']; ?>">
                                <?php echo ucfirst($session['status']); ?>
                            </span>
                        </div>
                        <div class="session-details">
                            <p><strong>📅 Date:</strong> <?php echo date('M d, Y', strtotime($session['scheduled_at'])); ?></p>
                            <p><strong>🕐 Time:</strong> <?php echo date('g:i A', strtotime($session['scheduled_at'])); ?></p>
                            <p><strong>💰 Amount:</strong> $<?php echo number_format($session['amount'], 2); ?></p>
                            <p><strong>💳 Payment:</strong> <?php echo ucfirst($session['payment_status']); ?></p>
                        </div>
                        <?php if ($session['status'] === 'confirmed' || $session['status'] === 'pending'): ?>
                            <div class="session-actions">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                                    <input type="hidden" name="complete_session" value="1">
                                    <button type="submit" class="btn btn-primary">✓ Mark as Completed</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2>✅ Completed Sessions</h2>
            <?php if (empty($completed_sessions)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📝</div>
                    <h3>No completed sessions yet</h3>
                    <p>Completed sessions and feedback will appear here</p>
                </div>
            <?php else: ?>
                <?php foreach ($completed_sessions as $session): ?>
                    <div class="session-card">
                        <div class="session-header">
                            <div class="mentee-info">
                                <h3><?php echo htmlspecialchars($session['mentee_name']); ?></h3>
                                <p><?php echo htmlspecialchars($session['mentee_email']); ?></p>
                            </div>
                            <span class="status-badge status-completed">Completed</span>
                        </div>
                        <div class="session-details">
                            <p><strong>📅 Date:</strong> <?php echo date('M d, Y', strtotime($session['scheduled_at'])); ?></p>
                            <p><strong>🕐 Time:</strong> <?php echo date('g:i A', strtotime($session['scheduled_at'])); ?></p>
                            <p><strong>💰 Amount:</strong> $<?php echo number_format($session['amount'], 2); ?></p>
                        </div>
                        <?php if ($session['rating']): ?>
                            <div class="feedback-box">
                                <h4 style="color: var(--color-primary-dark); margin-bottom: 0.5rem;">⭐ Mentee Feedback</h4>
                                <div class="stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php echo $i <= $session['rating'] ? '★' : '☆'; ?>
                                    <?php endfor; ?>
                                </div>
                                <p style="color: #475569;"><?php echo htmlspecialchars($session['feedback_comment']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
