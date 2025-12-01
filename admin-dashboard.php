<?php
// admin-dashboard.php - Complete Admin Panel
require_once 'config.php';
requireRole('admin');

$pdo = getDB();

// Handle mentor approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $mentor_id = intval($_POST['mentor_id']);
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE mentor_profiles SET status = 'approved' WHERE id = ?");
        $stmt->execute([$mentor_id]);
        $success = 'Mentor approved successfully!';
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE mentor_profiles SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$mentor_id]);
        $success = 'Mentor rejected.';
    } elseif ($action === 'suspend_user') {
        $user_id = intval($_POST['user_id']);
        $stmt = $pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
        $stmt->execute([$user_id]);
        $success = 'User suspended.';
    } elseif ($action === 'activate_user') {
        $user_id = intval($_POST['user_id']);
        $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $stmt->execute([$user_id]);
        $success = 'User activated.';
    }
}

// Get statistics
$stats = [
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'total_mentors' => $pdo->query("SELECT COUNT(*) FROM mentor_profiles WHERE status = 'approved'")->fetchColumn(),
    'total_mentees' => $pdo->query("SELECT COUNT(*) FROM mentee_profiles")->fetchColumn(),
    'total_sessions' => $pdo->query("SELECT COUNT(*) FROM sessions")->fetchColumn(),
    'completed_sessions' => $pdo->query("SELECT COUNT(*) FROM sessions WHERE status = 'completed'")->fetchColumn(),
    'pending_mentors' => $pdo->query("SELECT COUNT(*) FROM mentor_profiles WHERE status = 'pending'")->fetchColumn(),
    'total_revenue' => $pdo->query("SELECT SUM(amount) FROM sessions WHERE payment_status = 'paid'")->fetchColumn() ?? 0,
];

// Get pending mentors
$pending_mentors = $pdo->query("
    SELECT mp.*, u.email, u.created_at as registration_date
    FROM mentor_profiles mp 
    JOIN users u ON mp.user_id = u.id 
    WHERE mp.status = 'pending' 
    ORDER BY mp.created_at DESC
")->fetchAll();

// Get recent users
$recent_users = $pdo->query("
    SELECT u.*, 
           CASE 
               WHEN u.role = 'mentor' THEN mp.full_name
               WHEN u.role = 'mentee' THEN mep.full_name
               ELSE 'Admin'
           END as full_name
    FROM users u
    LEFT JOIN mentor_profiles mp ON u.id = mp.user_id
    LEFT JOIN mentee_profiles mep ON u.id = mep.user_id
    ORDER BY u.created_at DESC
    LIMIT 10
")->fetchAll();

// Get recent sessions
$recent_sessions = $pdo->query("
    SELECT s.*, 
           m.full_name as mentor_name,
           me.full_name as mentee_name
    FROM sessions s
    JOIN mentor_profiles m ON s.mentor_id = m.id
    JOIN mentee_profiles me ON s.mentee_id = me.id
    ORDER BY s.created_at DESC
    LIMIT 10
")->fetchAll();

// Get top mentors
$top_mentors = $pdo->query("
    SELECT mp.*, 
           COUNT(s.id) as session_count,
           SUM(s.amount) as total_earnings
    FROM mentor_profiles mp
    LEFT JOIN sessions s ON mp.id = s.mentor_id AND s.payment_status = 'paid'
    WHERE mp.status = 'approved'
    GROUP BY mp.id
    ORDER BY total_earnings DESC
    LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MentorBridge</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

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
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #666;
            font-size: 0.95rem;
        }

        .section {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .section h2 {
            color: #667eea;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .mentor-item {
            border: 2px solid #f0f0f0;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .mentor-item:hover {
            border-color: #667eea;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.1);
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
            font-size: 1.2rem;
        }

        .mentor-email {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .mentor-meta {
            color: #999;
            font-size: 0.85rem;
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
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-approve {
            background: #10b981;
            color: white;
        }

        .btn-approve:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .btn-reject {
            background: #ef4444;
            color: white;
        }

        .btn-reject:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .btn-suspend {
            background: #f59e0b;
            color: white;
            font-size: 0.85rem;
            padding: 0.4rem 0.8rem;
        }

        .btn-activate {
            background: #10b981;
            color: white;
            font-size: 0.85rem;
            padding: 0.4rem 0.8rem;
        }

        .mentor-details {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
        }

        .detail-row {
            margin-bottom: 0.5rem;
            color: #666;
        }

        .detail-row strong {
            color: #333;
        }

        .skills-tags {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 0.5rem;
        }

        .skill-tag {
            background: #e0e7ff;
            color: #667eea;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.85rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 2px solid #6ee7b7;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8f9fa;
            padding: 1rem;
            text-align: left;
            color: #667eea;
            font-weight: 600;
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
            color: #666;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-active {
            background: #d1fae5;
            color: #065f46;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-suspended {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-completed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .tab {
            padding: 1rem 2rem;
            cursor: pointer;
            border: none;
            background: none;
            color: #666;
            font-weight: 600;
            position: relative;
            transition: color 0.3s ease;
        }

        .tab.active {
            color: #667eea;
        }

        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .no-data {
            text-align: center;
            padding: 3rem;
            color: #999;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }

            .mentor-header {
                flex-direction: column;
                gap: 1rem;
            }

            .mentor-actions {
                width: 100%;
            }

            .btn {
                flex: 1;
            }

            table {
                font-size: 0.85rem;
            }

            th, td {
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <nav class="nav-bar">
        <div class="logo">🎓 MentorBridge - Admin Panel</div>
        <a href="logout.php" class="btn btn-secondary">Logout</a>
    </nav>

    <div class="container">
        <?php if (isset($success)): ?>
            <div class="alert alert-success">✓ <?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Statistics Dashboard -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👨‍🏫</div>
                <div class="stat-value"><?php echo number_format($stats['total_mentors']); ?></div>
                <div class="stat-label">Active Mentors</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👨‍🎓</div>
                <div class="stat-value"><?php echo number_format($stats['total_mentees']); ?></div>
                <div class="stat-label">Mentees</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-value"><?php echo number_format($stats['total_sessions']); ?></div>
                <div class="stat-label">Total Sessions</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-value"><?php echo number_format($stats['completed_sessions']); ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⏳</div>
                <div class="stat-value"><?php echo number_format($stats['pending_mentors']); ?></div>
                <div class="stat-label">Pending Approvals</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-value">$<?php echo number_format($stats['total_revenue'], 2); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="switchTab('pending')">⏳ Pending Approvals (<?php echo count($pending_mentors); ?>)</button>
            <button class="tab" onclick="switchTab('users')">👥 Users</button>
            <button class="tab" onclick="switchTab('sessions')">📅 Sessions</button>
            <button class="tab" onclick="switchTab('topmentors')">⭐ Top Mentors</button>
        </div>

        <!-- Pending Mentors Tab -->
        <div id="tab-pending" class="tab-content active">
            <div class="section">
                <h2>⏳ Pending Mentor Approvals</h2>
                <?php if (empty($pending_mentors)): ?>
                    <div class="no-data">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">✅</div>
                        <p>No pending approvals! All mentors have been reviewed.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($pending_mentors as $mentor): ?>
                        <div class="mentor-item">
                            <div class="mentor-header">
                                <div class="mentor-info">
                                    <h3><?php echo htmlspecialchars($mentor['full_name']); ?></h3>
                                    <div class="mentor-email">📧 <?php echo htmlspecialchars($mentor['email']); ?></div>
                                    <div class="mentor-meta">
                                        📅 Registered: <?php echo date('M d, Y', strtotime($mentor['registration_date'])); ?> | 
                                        💰 Rate: $<?php echo number_format($mentor['hourly_rate'], 2); ?>/hour
                                    </div>
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
                            <div class="mentor-details">
                                <div class="detail-row">
                                    <strong>Bio:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($mentor['bio'])); ?>
                                </div>
                                <div class="detail-row">
                                    <strong>Experience:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($mentor['experience'])); ?>
                                </div>
                                <div class="detail-row">
                                    <strong>Skills:</strong>
                                    <div class="skills-tags">
                                        <?php 
                                        $skills = explode(',', $mentor['skills']);
                                        foreach ($skills as $skill): 
                                        ?>
                                            <span class="skill-tag"><?php echo trim(htmlspecialchars($skill)); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Users Tab -->
        <div id="tab-users" class="tab-content">
            <div class="section">
                <h2>👥 Recent Users</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <?php 
                                    $role_icons = ['mentor' => '👨‍🏫', 'mentee' => '👨‍🎓', 'admin' => '👨‍💼'];
                                    echo $role_icons[$user['role']] . ' ' . ucfirst($user['role']); 
                                    ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $user['status']; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <?php if ($user['status'] === 'active'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="action" value="suspend_user">
                                            <button type="submit" class="btn btn-suspend">Suspend</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="action" value="activate_user">
                                            <button type="submit" class="btn btn-activate">Activate</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sessions Tab -->
        <div id="tab-sessions" class="tab-content">
            <div class="section">
                <h2>📅 Recent Sessions</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Mentor</th>
                            <th>Mentee</th>
                            <th>Scheduled</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Payment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_sessions as $session): ?>
                            <tr>
                                <td>#<?php echo $session['id']; ?></td>
                                <td><?php echo htmlspecialchars($session['mentor_name']); ?></td>
                                <td><?php echo htmlspecialchars($session['mentee_name']); ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($session['scheduled_at'])); ?></td>
                                <td>$<?php echo number_format($session['amount'], 2); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $session['status']; ?>">
                                        <?php echo ucfirst($session['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $session['payment_status'] === 'paid' ? 'active' : 'pending'; ?>">
                                        <?php echo ucfirst($session['payment_status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top Mentors Tab -->
        <div id="tab-topmentors" class="tab-content">
            <div class="section">
                <h2>⭐ Top Performing Mentors</h2>
                <?php if (empty($top_mentors)): ?>
                    <div class="no-data">No mentors yet</div>
                <?php else: ?>
                    <?php foreach ($top_mentors as $index => $mentor): ?>
                        <div class="mentor-item">
                            <div class="mentor-header">
                                <div class="mentor-info">
                                    <h3>
                                        <?php 
                                        $medals = ['🥇', '🥈', '🥉'];
                                        echo ($medals[$index] ?? '🏅') . ' '; 
                                        echo htmlspecialchars($mentor['full_name']); 
                                        ?>
                                    </h3>
                                    <div class="mentor-meta">
                                        ⭐ Rating: <?php echo number_format($mentor['average_rating'], 1); ?> 
                                        (<?php echo $mentor['total_reviews']; ?> reviews) | 
                                        📅 Sessions: <?php echo $mentor['session_count']; ?> | 
                                        💰 Earned: $<?php echo number_format($mentor['total_earnings'], 2); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="mentor-details">
                                <div class="detail-row">
                                    <strong>Skills:</strong>
                                    <div class="skills-tags">
                                        <?php 
                                        $skills = explode(',', $mentor['skills']);
                                        foreach (array_slice($skills, 0, 5) as $skill): 
                                        ?>
                                            <span class="skill-tag"><?php echo trim(htmlspecialchars($skill)); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById('tab-' + tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }

        // Add fade-in animation for cards
        document.addEventListener('DOMContentLoaded', () => {
            const cards = document.querySelectorAll('.stat-card, .mentor-item');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 50);
            });
        });
    </script>
</body>
</html>