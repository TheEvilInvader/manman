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
            color: #e0e7ff;
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

        body::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, #8b5cf6 0%, #6366f1 100%);
        }

        body::before {
            content: '';
            position: fixed;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.15), transparent 70%);
            border-radius: 50%;
            top: -300px;
            right: -300px;
            animation: float 25s ease-in-out infinite;
            z-index: 0;
        }

        body::after {
            content: '';
            position: fixed;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.15), transparent 70%);
            border-radius: 50%;
            bottom: -250px;
            left: -250px;
            animation: float 20s ease-in-out infinite reverse;
            z-index: 0;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(50px, -50px) rotate(120deg); }
            66% { transform: translate(-30px, 30px) rotate(240deg); }
        }

        .nav-bar {
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(20px);
            padding: 1.5rem 2.5rem;
            border-radius: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            box-shadow: 0 0 40px rgba(139, 92, 246, 0.2);
            border: 1px solid rgba(139, 92, 246, 0.2);
            position: relative;
            z-index: 10;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, #8b5cf6, #6366f1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.5px;
            filter: drop-shadow(0 0 10px rgba(139, 92, 246, 0.5));
        }

        .nav-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .admin-badge {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            box-shadow: 0 0 20px rgba(139, 92, 246, 0.4);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            padding: 2rem;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 0 40px rgba(139, 92, 246, 0.2);
            transition: all 0.3s ease;
            border: 1px solid rgba(139, 92, 246, 0.2);
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
            background: linear-gradient(90deg, #6366f1, #8b5cf6);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 50px rgba(139, 92, 246, 0.4);
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
            background: linear-gradient(135deg, #8b5cf6, #6366f1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
            line-height: 1;
            letter-spacing: -1px;
        }

        .stat-label {
            color: #94a3b8;
            font-size: 0.9rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .section {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            padding: 2.5rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            box-shadow: 0 0 40px rgba(139, 92, 246, 0.2);
            border: 1px solid rgba(139, 92, 246, 0.2);
        }

        .section h2 {
            background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 2rem;
            font-size: 1.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            letter-spacing: -0.5px;
        }

        .mentor-item {
            border: 2px solid rgba(139, 92, 246, 0.3);
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            background: rgba(30, 27, 75, 0.6);
        }

        .mentor-item:hover {
            border-color: rgba(139, 92, 246, 0.6);
            box-shadow: 0 8px 30px rgba(139, 92, 246, 0.3);
            transform: translateY(-2px);
        }

        .mentor-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .mentor-info h3 {
            color: #c4b5fd;
            margin-bottom: 0.5rem;
            font-size: 1.3rem;
            font-weight: 700;
        }

        .mentor-email {
            color: #94a3b8;
            font-size: 0.95rem;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .mentor-meta {
            color: #94a3b8;
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
            background: rgba(139, 92, 246, 0.2);
            color: #c4b5fd;
            font-weight: 600;
            border: 2px solid rgba(139, 92, 246, 0.3);
        }

        .btn-secondary:hover {
            background: rgba(139, 92, 246, 0.3);
            border-color: rgba(139, 92, 246, 0.6);
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
            background: rgba(30, 27, 75, 0.4);
            border: 1px solid rgba(139, 92, 246, 0.2);
            padding: 1.5rem;
            border-radius: 12px;
            margin-top: 1rem;
        }

        .detail-row {
            margin-bottom: 1rem;
            color: #94a3b8;
            line-height: 1.6;
        }

        .detail-row:last-child {
            margin-bottom: 0;
        }

        .detail-row strong {
            color: #c7d2fe;
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
            background: rgba(139, 92, 246, 0.2);
            color: #c4b5fd;
            border: 1px solid rgba(139, 92, 246, 0.3);
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
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            box-shadow: 0 0 15px rgba(139, 92, 246, 0.3);
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
            background: rgba(34, 197, 94, 0.15);
            color: #86efac;
            border: 2px solid rgba(34, 197, 94, 0.3);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: rgba(30, 27, 75, 0.6);
            padding: 1rem;
            text-align: left;
            color: #c7d2fe;
            font-weight: 600;
            border-bottom: 2px solid rgba(139, 92, 246, 0.3);
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid rgba(139, 92, 246, 0.2);
            color: #94a3b8;
        }

        tr:hover {
            background: rgba(139, 92, 246, 0.1);
        }

        .status-badge {
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }

        .status-active {
            background: rgba(34, 197, 94, 0.2);
            color: #86efac;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .status-pending {
            background: rgba(234, 179, 8, 0.2);
            color: #fde047;
            border: 1px solid rgba(234, 179, 8, 0.3);
        }

        .status-suspended {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
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
        <svg width="0" height="0" style="position: absolute;">
            <defs>
                <linearGradient id="iconGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" style="stop-color:#6366f1;stop-opacity:1" />
                    <stop offset="100%" style="stop-color:#8b5cf6;stop-opacity:1" />
                </linearGradient>
            </defs>
        </svg>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M17 20C17 18.3431 14.7614 17 12 17C9.23858 17 7 18.3431 7 20M21 17C21 15.7635 19.7014 14.7307 18 14.2628M3 17C3 15.7635 4.29859 14.7307 6 14.2628M18 10.2628C19.1652 9.82849 20 8.75849 20 7.5C20 6.24151 19.1652 5.17151 18 4.73717M6 10.2628C4.83481 9.82849 4 8.75849 4 7.5C4 6.24151 4.83481 5.17151 6 4.73717M15 7.5C15 9.433 13.433 11 11.5 11C9.567 11 8 9.433 8 7.5C8 5.567 9.567 4 11.5 4C13.433 4 15 5.567 15 7.5Z" stroke="url(#iconGradient)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="url(#iconGradient)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M2 17L12 22L22 17" stroke="url(#iconGradient)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M2 12L12 17L22 12" stroke="url(#iconGradient)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_mentors']); ?></div>
                <div class="stat-label">Active Mentors</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 14C14.2091 14 16 12.2091 16 10C16 7.79086 14.2091 6 12 6C9.79086 6 8 7.79086 8 10C8 12.2091 9.79086 14 12 14Z" stroke="url(#iconGradient)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M4 18C4 16.3431 7.58172 15 12 15C16.4183 15 20 16.3431 20 18M19 4L21 6L19 8" stroke="url(#iconGradient)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_mentees']); ?></div>
                <div class="stat-label">Mentees</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M8 2V5M16 2V5M3.5 9.09H20.5M21 8.5V17C21 20 19.5 22 16 22H8C4.5 22 3 20 3 17V8.5C3 5.5 4.5 3.5 8 3.5H16C19.5 3.5 21 5.5 21 8.5Z" stroke="url(#iconGradient)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M11.9955 13.7H12.0045M8.29431 13.7H8.30329M8.29431 16.7H8.30329" stroke="url(#iconGradient)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_sessions']); ?></div>
                <div class="stat-label">Total Sessions</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 22C17.5 22 22 17.5 22 12C22 6.5 17.5 2 12 2C6.5 2 2 6.5 2 12C2 17.5 6.5 22 12 22Z" stroke="url(#iconGradient)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M7.75 12L10.58 14.83L16.25 9.17" stroke="url(#iconGradient)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="stat-value"><?php echo number_format($stats['completed_sessions']); ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="url(#iconGradient)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M12 6V12L16 14" stroke="url(#iconGradient)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="stat-value"><?php echo number_format($stats['pending_mentors']); ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2V22M17 5H9.5C8.57174 5 7.6815 5.36875 7.02513 6.02513C6.36875 6.6815 6 7.57174 6 8.5C6 9.42826 6.36875 10.3185 7.02513 10.9749C7.6815 11.6313 8.57174 12 9.5 12H14.5C15.4283 12 16.3185 12.3687 16.9749 13.0251C17.6313 13.6815 18 14.5717 18 15.5C18 16.4283 17.6313 17.3185 16.9749 17.9749C16.3185 18.6313 15.4283 19 14.5 19H6" stroke="url(#iconGradient)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="stat-value">$<?php echo number_format($stats['total_revenue'], 1); ?></div>
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
                <h2>New Mentor Applications</h2>
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
                <h2>Mentor Profile Update Requests</h2>
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
