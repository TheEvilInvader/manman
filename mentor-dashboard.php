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
            position: relative;
            overflow-x: hidden;
        }

        /* Custom Scrollbar */
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

        body::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, #8b5cf6 0%, #6366f1 100%);
        }

        /* Floating Gradient Orbs */
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

        .navbar {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            padding: 1.25rem 2.5rem;
            box-shadow: 0 0 40px rgba(139, 92, 246, 0.2), inset 0 0 0 1px rgba(139, 92, 246, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            font-size: 0.95rem;
            font-family: 'Inter', sans-serif;
        }

        .btn-primary {
            background: linear-gradient(135deg, #8b5cf6, #6366f1);
            color: white;
            box-shadow: 0 10px 30px rgba(139, 92, 246, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 50px rgba(139, 92, 246, 0.5);
        }

        .btn-secondary {
            background: rgba(139, 92, 246, 0.15);
            color: #c4b5fd;
            border: 1px solid rgba(139, 92, 246, 0.3);
        }

        .btn-secondary:hover {
            background: rgba(139, 92, 246, 0.25);
            border-color: rgba(139, 92, 246, 0.5);
            box-shadow: 0 0 30px rgba(139, 92, 246, 0.3);
            transform: translateY(-2px);
        }

        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
            position: relative;
            z-index: 10;
        }

        .header {
            margin-bottom: 2rem;
        }

        .header h1 {
            background: linear-gradient(135deg, #a78bfa, #c4b5fd);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            font-weight: 900;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 0 40px rgba(139, 92, 246, 0.2), inset 0 0 0 1px rgba(139, 92, 246, 0.1);
            border: 1px solid rgba(139, 92, 246, 0.2);
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0 60px rgba(139, 92, 246, 0.4), 0 20px 40px rgba(0, 0, 0, 0.3);
            border-color: rgba(139, 92, 246, 0.5);
        }

        .stat-card h3 {
            color: #94a3b8;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card .value {
            background: linear-gradient(135deg, #a78bfa, #c4b5fd);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 2.5rem;
            font-weight: 900;
        }

        .section {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            padding: 2.5rem;
            border-radius: 24px;
            box-shadow: 0 0 40px rgba(139, 92, 246, 0.2), inset 0 0 0 1px rgba(139, 92, 246, 0.1);
            margin-bottom: 2rem;
            border: 1px solid rgba(139, 92, 246, 0.2);
        }

        .section h2 {
            background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
            font-weight: 800;
        }

        .session-card {
            background: rgba(30, 27, 75, 0.6);
            backdrop-filter: blur(10px);
            padding: 1.5rem;
            border-radius: 16px;
            margin-bottom: 1rem;
            border-left: 4px solid #8b5cf6;
            border: 1px solid rgba(139, 92, 246, 0.2);
            transition: all 0.3s ease;
        }

        .session-card:hover {
            background: rgba(30, 27, 75, 0.8);
            border-color: rgba(139, 92, 246, 0.5);
            box-shadow: 0 0 30px rgba(139, 92, 246, 0.3);
        }

        .session-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .mentee-info h3 {
            background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .mentee-info p {
            color: #94a3b8;
            font-size: 0.9rem;
        }

        .status-badge {
            padding: 0.6rem 1.2rem;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background: rgba(251, 191, 36, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(251, 191, 36, 0.3);
        }

        .status-confirmed {
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .status-completed {
            background: rgba(34, 197, 94, 0.15);
            color: #4ade80;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .session-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .session-details p {
            color: #cbd5e1;
        }

        .session-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .feedback-box {
            background: rgba(30, 27, 75, 0.5);
            padding: 1.2rem;
            border-radius: 12px;
            margin-top: 1rem;
            border: 1px solid rgba(139, 92, 246, 0.2);
        }

        .stars {
            color: #fbbf24;
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            filter: drop-shadow(0 0 5px rgba(251, 191, 36, 0.5));
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

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #94a3b8;
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            filter: drop-shadow(0 0 10px rgba(139, 92, 246, 0.3));
        }

        @media (max-width: 768px) {
            .navbar {
                padding: 1rem 1.5rem;
            }

            .container {
                padding: 0 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .session-header {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
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
        <div class="nav-buttons">
            <a href="mentor-profile.php" class="btn btn-secondary">Profile Editing</a>
            <a href="manage-availability.php" class="btn btn-secondary">Manage Availability</a>
            <a href="logout.php" class="btn btn-primary">Logout</a>
        </div>
    </nav>

    <div class="container">
        <div class="header">
            <h1>Welcome, <?php echo htmlspecialchars($profile['full_name']); ?>!</h1>
            <p style="color: #94a3b8;">Manage your mentorship sessions and track your progress</p>
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
            <h2>Upcoming Sessions</h2>
            <?php if (empty($upcoming_sessions)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">💭</div>
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
                            <p><strong>Date:</strong> <?php echo date('M d, Y', strtotime($session['scheduled_at'])); ?></p>
                            <p><strong>Time:</strong> <?php echo date('g:i A', strtotime($session['scheduled_at'])); ?></p>
                            <p><strong>Amount:</strong> $<?php echo number_format($session['amount'], 2); ?></p>
                            <p><strong>Payment:</strong> <?php echo ucfirst($session['payment_status']); ?></p>
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
            <h2>Completed Sessions</h2>
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
                            <p><strong>Date:</strong> <?php echo date('M d, Y', strtotime($session['scheduled_at'])); ?></p>
                            <p><strong>Time:</strong> <?php echo date('g:i A', strtotime($session['scheduled_at'])); ?></p>
                            <p><strong>Amount:</strong> $<?php echo number_format($session['amount'], 2); ?></p>
                        </div>
                        <?php if ($session['rating']): ?>
                            <div class="feedback-box">
                                <h4 style="background: linear-gradient(135deg, #a78bfa, #c4b5fd); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 0.5rem; font-weight: 700;">Mentee Feedback</h4>
                                <div class="stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php echo $i <= $session['rating'] ? '★' : '☆'; ?>
                                    <?php endfor; ?>
                                </div>
                                <p style="color: #cbd5e1;"><?php echo htmlspecialchars($session['feedback_comment']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
