<?php
// admin-dashboard.php - Complete Admin Panel
require_once 'config.php';
requireRole('admin');

$mysqli = getDB();

// Handle mentor approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $mentor_id = intval($_POST['mentor_id']);
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        $stmt = $mysqli->prepare("UPDATE mentor_profiles SET status = 'approved' WHERE id = ?");
        $stmt->bind_param("i", $mentor_id);
        $stmt->execute();
        $stmt->close();
        $success = 'Mentor approved successfully!';
    } elseif ($action === 'reject') {
        $stmt = $mysqli->prepare("UPDATE mentor_profiles SET status = 'rejected' WHERE id = ?");
        $stmt->bind_param("i", $mentor_id);
        $stmt->execute();
        $stmt->close();
        $success = 'Mentor rejected.';
    } elseif ($action === 'suspend_user') {
        $user_id = intval($_POST['user_id']);
        $stmt = $mysqli->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        $success = 'User suspended.';
    } elseif ($action === 'activate_user') {
        $user_id = intval($_POST['user_id']);
        $stmt = $mysqli->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        $success = 'User activated.';
    }
}

// Get statistics
$stats = [];

$result = $mysqli->query("SELECT COUNT(*) as count FROM users");
$row = $result->fetch_assoc();
$stats['total_users'] = $row['count'];

$result = $mysqli->query("SELECT COUNT(*) as count FROM mentor_profiles WHERE status = 'approved'");
$row = $result->fetch_assoc();
$stats['total_mentors'] = $row['count'];

$result = $mysqli->query("SELECT COUNT(*) as count FROM mentee_profiles");
$row = $result->fetch_assoc();
$stats['total_mentees'] = $row['count'];

$result = $mysqli->query("SELECT COUNT(*) as count FROM sessions");
$row = $result->fetch_assoc();
$stats['total_sessions'] = $row['count'];

$result = $mysqli->query("SELECT COUNT(*) as count FROM sessions WHERE status = 'completed'");
$row = $result->fetch_assoc();
$stats['completed_sessions'] = $row['count'];

$result = $mysqli->query("SELECT COUNT(*) as count FROM mentor_profiles WHERE status = 'pending'");
$row = $result->fetch_assoc();
$stats['pending_mentors'] = $row['count'];

// Calculate platform revenue (20% of all paid sessions)
$result = $mysqli->query("SELECT SUM(amount) as total FROM sessions WHERE payment_status = 'paid'");
$row = $result->fetch_assoc();
$total_paid = $row['total'] ?? 0;
// Platform earns 20% of the total (which is already included in the amount)
// amount = mentor_rate * 1.20, so platform_fee = amount - (amount / 1.20)
$stats['total_revenue'] = $total_paid - ($total_paid / 1.20);

// Get new mentor applications (never been approved)
$result = $mysqli->query("
    SELECT mp.*, u.email, u.created_at as registration_date,
           GROUP_CONCAT(c.name SEPARATOR ', ') as categories,
           (SELECT COUNT(*) FROM sessions WHERE mentor_id = mp.id) as has_sessions
    FROM mentor_profiles mp 
    JOIN users u ON mp.user_id = u.id 
    LEFT JOIN mentor_categories mc ON mp.id = mc.mentor_id
    LEFT JOIN categories c ON mc.category_id = c.id
    WHERE mp.status = 'pending' 
    GROUP BY mp.id
    HAVING has_sessions = 0
    ORDER BY mp.created_at DESC
");
$new_applications = $result->fetch_all(MYSQLI_ASSOC);

// Get profile update requests (pending from approved mentors with sessions)
$result = $mysqli->query("
    SELECT mp.*, u.email, u.created_at as registration_date,
           GROUP_CONCAT(c.name SEPARATOR ', ') as categories,
           (SELECT COUNT(*) FROM sessions WHERE mentor_id = mp.id) as session_count
    FROM mentor_profiles mp 
    JOIN users u ON mp.user_id = u.id 
    LEFT JOIN mentor_categories mc ON mp.id = mc.mentor_id
    LEFT JOIN categories c ON mc.category_id = c.id
    WHERE mp.status = 'pending' 
    GROUP BY mp.id
    HAVING session_count > 0
    ORDER BY mp.updated_at DESC
");
$profile_updates = $result->fetch_all(MYSQLI_ASSOC);

// Get recent users
$result = $mysqli->query("
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
");
$recent_users = $result->fetch_all(MYSQLI_ASSOC);

// Get recent sessions
$result = $mysqli->query("
    SELECT s.*, 
           m.full_name as mentor_name,
           me.full_name as mentee_name
    FROM sessions s
    JOIN mentor_profiles m ON s.mentor_id = m.id
    JOIN mentee_profiles me ON s.mentee_id = me.id
    ORDER BY s.created_at DESC
    LIMIT 10
");
$recent_sessions = $result->fetch_all(MYSQLI_ASSOC);

// Get top mentors
$result = $mysqli->query("
    SELECT mp.*, 
           COUNT(s.id) as session_count,
           COALESCE(SUM(s.amount), 0) as total_earnings
    FROM mentor_profiles mp
    LEFT JOIN sessions s ON mp.id = s.mentor_id AND s.payment_status = 'paid'
    WHERE mp.status = 'approved'
    GROUP BY mp.id
    ORDER BY total_earnings DESC
    LIMIT 5
");
$top_mentors = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MentorBridge</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-primary: #638ECB;
            --color-primary-dark: #395886;
            --color-primary-light: #8AAEE0;
            --color-accent: #B1C9EF;
            --color-bg-light: #F0F3FA;
            --color-bg-lighter: #D5DEEF;
            --color-success: #10b981;
            --color-danger: #ef4444;
            --color-warning: #f59e0b;
            --color-text: #1e293b;
            --color-text-light: #64748b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, var(--color-bg-light) 0%, var(--color-bg-lighter) 100%);
            min-height: 100vh;
            padding: 20px;
            color: var(--color-text);
        }

        .nav-bar {
            background: white;
            padding: 1.5rem 2.5rem;
            border-radius: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            box-shadow: 0 10px 40px rgba(99, 142, 203, 0.15);
            border: 1px solid rgba(99, 142, 203, 0.1);
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.5px;
        }

        .nav-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .admin-badge {
            background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light));
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
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
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(99, 142, 203, 0.08);
            transition: all 0.3s ease;
            border: 1px solid rgba(99, 142, 203, 0.1);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--color-primary), var(--color-primary-light));
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 50px rgba(99, 142, 203, 0.15);
        }

        .stat-card:hover::before {
            transform: scaleX(1);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            filter: grayscale(0.2);
        }

        .stat-value {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
            line-height: 1;
            letter-spacing: -1px;
        }

        .stat-label {
            color: var(--color-text-light);
            font-size: 0.9rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .section {
            background: white;
            padding: 2.5rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            box-shadow: 0 10px 40px rgba(99, 142, 203, 0.08);
            border: 1px solid rgba(99, 142, 203, 0.1);
        }

        .section h2 {
            color: var(--color-primary-dark);
            margin-bottom: 2rem;
            font-size: 1.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            letter-spacing: -0.5px;
        }

        .mentor-item {
            border: 2px solid var(--color-bg-lighter);
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            background: linear-gradient(135deg, rgba(240, 243, 250, 0.3), rgba(255, 255, 255, 0.9));
        }

        .mentor-item:hover {
            border-color: var(--color-primary-light);
            box-shadow: 0 8px 30px rgba(99, 142, 203, 0.15);
            transform: translateY(-2px);
        }

        .mentor-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .mentor-info h3 {
            color: var(--color-primary-dark);
            margin-bottom: 0.5rem;
            font-size: 1.3rem;
            font-weight: 700;
        }

        .mentor-email {
            color: var(--color-text-light);
            font-size: 0.95rem;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .mentor-meta {
            color: var(--color-text-light);
            font-size: 0.9rem;
            font-weight: 500;
        }

        .mentor-actions {
            display: flex;
            gap: 0.75rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            font-size: 0.95rem;
            font-family: 'Inter', sans-serif;
        }

        .btn-approve {
            background: linear-gradient(135deg, var(--color-success), #059669);
            color: white;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        .btn-approve:hover {
            box-shadow: 0 6px 25px rgba(16, 185, 129, 0.4);
            transform: translateY(-2px);
        }

        .btn-reject {
            background: linear-gradient(135deg, var(--color-danger), #dc2626);
            color: white;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }

        .btn-reject:hover {
            box-shadow: 0 6px 25px rgba(239, 68, 68, 0.4);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: var(--color-bg-light);
            color: var(--color-primary-dark);
            font-weight: 600;
            border: 2px solid transparent;
        }

        .btn-secondary:hover {
            background: white;
            border-color: var(--color-primary-light);
            transform: translateY(-2px);
        }

        .btn-suspend {
            background: var(--color-warning);
            color: white;
            font-size: 0.85rem;
            padding: 0.5rem 1rem;
        }

        .btn-activate {
            background: var(--color-success);
            color: white;
            font-size: 0.85rem;
            padding: 0.5rem 1rem;
        }

        .mentor-details {
            background: var(--color-bg-light);
            padding: 1.5rem;
            border-radius: 12px;
            margin-top: 1rem;
        }

        .detail-row {
            margin-bottom: 1rem;
            color: var(--color-text);
            line-height: 1.6;
        }

        .detail-row:last-child {
            margin-bottom: 0;
        }

        .detail-row strong {
            color: var(--color-primary-dark);
            font-weight: 600;
            display: block;
            margin-bottom: 0.5rem;
        }

        .skills-tags {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 0.5rem;
        }

        .skill-tag {
            background: var(--color-accent);
            color: var(--color-primary-dark);
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .category-tags {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 0.5rem;
        }

        .category-tag {
            background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light));
            color: white;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .alert {
            padding: 1.25rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            animation: slideDown 0.3s ease;
            font-weight: 500;
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
            background: var(--color-bg-light);
            padding: 1rem;
            text-align: left;
            color: var(--color-primary-dark);
            font-weight: 600;
            border-bottom: 2px solid var(--color-bg-lighter);
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid var(--color-bg-light);
            color: var(--color-text);
        }

        tr:hover {
            background: var(--color-bg-light);
        }

        .status-badge {
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
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
            gap: 0.5rem;
            margin-bottom: 2rem;
            background: var(--color-bg-light);
            padding: 0.5rem;
            border-radius: 16px;
        }

        .tab {
            padding: 1rem 2rem;
            cursor: pointer;
            border: none;
            background: none;
            color: var(--color-text-light);
            font-weight: 600;
            position: relative;
            transition: all 0.3s ease;
            border-radius: 12px;
            font-family: 'Inter', sans-serif;
        }

        .tab:hover {
            background: rgba(255, 255, 255, 0.5);
        }

        .tab.active {
            color: white;
            background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light));
            box-shadow: 0 4px 15px rgba(99, 142, 203, 0.3);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .no-data {
            text-align: center;
            padding: 3rem;
            color: var(--color-text-light);
        }

        .no-data-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
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

            .tabs {
                flex-wrap: wrap;
            }

            .tab {
                padding: 0.75rem 1rem;
            }
        }
    </style>
</head>
<body>
    <nav class="nav-bar">
        <div class="logo">MentorBridge</div>
        <div class="nav-actions">
            <span class="admin-badge">Admin Panel</span>
            <a href="logout.php" class="btn btn-secondary">Logout</a>
        </div>
    </nav>

    <div class="container">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
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
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-value">$<?php echo number_format($stats['total_revenue'], 2); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="switchTab('applications')">New Applications (<?php echo count($new_applications); ?>)</button>
            <button class="tab" onclick="switchTab('updates')">Profile Updates (<?php echo count($profile_updates); ?>)</button>
            <button class="tab" onclick="switchTab('users')">Users</button>
            <button class="tab" onclick="switchTab('sessions')">Sessions</button>
            <button class="tab" onclick="switchTab('topmentors')">Top Mentors</button>
        </div>

        <!-- New Applications Tab -->
        <div id="tab-applications" class="tab-content active">
            <div class="section">
                <h2>📝 New Mentor Applications</h2>
                <p style="color: #64748b; margin-bottom: 1.5rem;">First-time mentor applications awaiting approval</p>
                <?php if (empty($new_applications)): ?>
                    <div class="no-data">
                        <div class="no-data-icon">✅</div>
                        <p>No new applications! All mentors have been reviewed.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($new_applications as $mentor): ?>
                        <div class="mentor-item">
                            <div class="mentor-header">
                                <div class="mentor-info">
                                    <h3><?php echo htmlspecialchars($mentor['full_name']); ?></h3>
                                    <div class="mentor-email"><?php echo htmlspecialchars($mentor['email']); ?></div>
                                    <div class="mentor-meta">
                                        Registered: <?php echo date('M d, Y', strtotime($mentor['registration_date'])); ?> | 
                                        Rate: $<?php echo number_format($mentor['hourly_rate'], 2); ?>/hour
                                    </div>
                                    <?php if (!empty($mentor['categories'])): ?>
                                        <div class="category-tags">
                                            <?php 
                                            $categories = explode(', ', $mentor['categories']);
                                            foreach ($categories as $category): 
                                            ?>
                                                <span class="category-tag"><?php echo htmlspecialchars($category); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="mentor-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="mentor_id" value="<?php echo $mentor['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn btn-approve">Approve</button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="mentor_id" value="<?php echo $mentor['id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn btn-reject">Reject</button>
                                    </form>
                                </div>
                            </div>
                            <div class="mentor-details">
                                <div class="detail-row">
                                    <strong>Bio</strong>
                                    <?php echo nl2br(htmlspecialchars($mentor['bio'])); ?>
                                </div>
                                <div class="detail-row">
                                    <strong>Experience</strong>
                                    <?php echo nl2br(htmlspecialchars($mentor['experience'])); ?>
                                </div>
                                <div class="detail-row">
                                    <strong>Skills</strong>
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

        <!-- Profile Updates Tab -->
        <div id="tab-updates" class="tab-content">
            <div class="section">
                <h2>🔄 Mentor Profile Update Requests</h2>
                <p style="color: #64748b; margin-bottom: 1.5rem;">Previously approved mentors requesting profile changes - re-approval required</p>
                <?php if (empty($profile_updates)): ?>
                    <div class="no-data">
                        <div class="no-data-icon">✅</div>
                        <p>No profile update requests!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($profile_updates as $mentor): ?>
                        <div class="mentor-item" style="border-left: 4px solid #f59e0b;">
                            <div class="mentor-header">
                                <div class="mentor-info">
                                    <h3><?php echo htmlspecialchars($mentor['full_name']); ?> <span style="background: #fef3c7; color: #92400e; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.75rem; font-weight: 600; margin-left: 0.5rem;">UPDATE REQUEST</span></h3>
                                    <div class="mentor-email"><?php echo htmlspecialchars($mentor['email']); ?></div>
                                    <div class="mentor-meta">
                                        Last Updated: <?php echo date('M d, Y g:i A', strtotime($mentor['updated_at'])); ?> | 
                                        Rate: $<?php echo number_format($mentor['hourly_rate'], 2); ?>/hour |
                                        Sessions: <?php echo $mentor['session_count']; ?>
                                    </div>
                                    <?php if (!empty($mentor['categories'])): ?>
                                        <div class="category-tags">
                                            <?php 
                                            $categories = explode(', ', $mentor['categories']);
                                            foreach ($categories as $category): 
                                            ?>
                                                <span class="category-tag"><?php echo htmlspecialchars($category); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="mentor-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="mentor_id" value="<?php echo $mentor['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn btn-approve">Re-Approve</button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="mentor_id" value="<?php echo $mentor['id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn btn-reject">Reject Changes</button>
                                    </form>
                                </div>
                            </div>
                            <div class="mentor-details">
                                <div class="detail-row">
                                    <strong>Bio</strong>
                                    <?php echo nl2br(htmlspecialchars($mentor['bio'])); ?>
                                </div>
                                <div class="detail-row">
                                    <strong>Experience</strong>
                                    <?php echo nl2br(htmlspecialchars($mentor['experience'])); ?>
                                </div>
                                <div class="detail-row">
                                    <strong>Skills</strong>
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
                <h2>Recent Users</h2>
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
                <h2>Recent Sessions</h2>
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
                <h2>Top Performing Mentors</h2>
                <?php if (empty($top_mentors)): ?>
                    <div class="no-data">
                        <div class="no-data-icon">🏆</div>
                        <p>No mentor data available yet</p>
                    </div>
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
                                        Rating: <?php echo number_format($mentor['average_rating'], 1); ?> 
                                        (<?php echo $mentor['total_reviews']; ?> reviews) | 
                                        Sessions: <?php echo $mentor['session_count']; ?> | 
                                        Earned: $<?php echo number_format($mentor['total_earnings'], 2); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="mentor-details">
                                <div class="detail-row">
                                    <strong>Skills</strong>
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
