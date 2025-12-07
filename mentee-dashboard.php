<?php
// mentee-dashboard.php - Mentee Dashboard with Category Selection & Mentor Search
require_once 'config.php';
requireRole('mentee');

// Function to get SVG icon for category
function getCategoryIconSVG($categoryName) {
    $icons = [
        'Programming' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="width:20px;height:20px;vertical-align:middle;"><path d="M8 6L4 10L8 14M16 6L20 10L16 14M12 4L10 16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'School' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="width:20px;height:20px;vertical-align:middle;"><path d="M6 7L12 4L18 7L12 10L6 7Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M6 7V14L12 17L18 14V7" stroke="currentColor" stroke-width="2"/></svg>',
        'University' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="width:20px;height:20px;vertical-align:middle;"><path d="M4 10L12 6L20 10M12 6V18M9 13H15V18H9V13ZM4 10V16M20 10V16M3 16H21" stroke="currentColor" stroke-width="2"/></svg>',
        'Biology' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="width:20px;height:20px;vertical-align:middle;"><circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2"/><circle cx="8" cy="14" r="3" stroke="currentColor" stroke-width="2"/><circle cx="16" cy="14" r="3" stroke="currentColor" stroke-width="2"/></svg>',
        'Mathematics' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="width:20px;height:20px;vertical-align:middle;"><circle cx="12" cy="12" r="6" stroke="currentColor" stroke-width="2"/><path d="M9 12H15M12 9V15" stroke="currentColor" stroke-width="2"/></svg>',
        'Business' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="width:20px;height:20px;vertical-align:middle;"><rect x="6" y="8" width="12" height="12" rx="1" stroke="currentColor" stroke-width="2"/><path d="M10 8V6C10 5 11 4 12 4C13 4 14 5 14 6V8" stroke="currentColor" stroke-width="2"/></svg>',
        'Languages' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="width:20px;height:20px;vertical-align:middle;"><circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="2"/><path d="M4 12C4 12 7 7 12 7C17 7 20 12 20 12C20 12 17 17 12 17C7 17 4 12 4 12" stroke="currentColor" stroke-width="2"/></svg>',
        'Arts' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="width:20px;height:20px;vertical-align:middle;"><circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="2"/><circle cx="10" cy="6" r="2" fill="currentColor"/><circle cx="14" cy="9" r="1.5" fill="currentColor"/></svg>',
        'Science' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="width:20px;height:20px;vertical-align:middle;"><path d="M9 4V10L6 16C5 18 6 20 8 20H16C18 20 19 18 18 16L15 10V4" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="15" r="1.5" fill="currentColor"/></svg>',
        'Engineering' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="width:20px;height:20px;vertical-align:middle;"><circle cx="12" cy="12" r="7" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="2" stroke="currentColor" stroke-width="2"/><path d="M12 5V10M12 14V19M5 12H10M14 12H19" stroke="currentColor" stroke-width="2"/></svg>'
    ];
    return $icons[$categoryName] ?? '📚';
}

$mysqli = getDB();
$user_id = getUserId();

// Get mentee profile
$stmt = $mysqli->prepare("SELECT * FROM mentee_profiles WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$mentee_profile = $result->fetch_assoc();
$stmt->close();

// Get all categories
$result = $mysqli->query("SELECT * FROM categories ORDER BY name");
$categories = $result->fetch_all(MYSQLI_ASSOC);

// Get selected category
$selected_category = $_GET['category'] ?? null;
$search_query = $_GET['search'] ?? '';

// Get mentors based on filters
$mentors = [];
if ($selected_category || $search_query) {
    $sql = "
        SELECT DISTINCT mp.*, 
               GROUP_CONCAT(c.name) as category_names,
               GROUP_CONCAT(c.icon) as category_icons
        FROM mentor_profiles mp
        LEFT JOIN mentor_categories mc ON mp.id = mc.mentor_id
        LEFT JOIN categories c ON mc.category_id = c.id
        WHERE mp.status = 'approved'
    ";
    
    $types = "";
    $params = [];
    
    if ($selected_category) {
        $sql .= " AND mc.category_id = ?";
        $types .= "i";
        $params[] = $selected_category;
    }
    
    if ($search_query) {
        $sql .= " AND (mp.full_name LIKE ? OR mp.skills LIKE ? OR mp.bio LIKE ?)";
        $search_param = '%' . $search_query . '%';
        $types .= "sss";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $sql .= " GROUP BY mp.id ORDER BY mp.average_rating DESC, mp.total_reviews DESC";
    
    $stmt = $mysqli->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $mentors = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$mysqli->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find a Mentor - MentorBridge</title>
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

        .nav-bar {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            padding: 1.25rem 2.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
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

        .nav-buttons {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn {
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            font-family: 'Inter', sans-serif;
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
            margin: 0 auto;
            padding: 2rem;
            position: relative;
            z-index: 10;
        }

        .hero-section {
            text-align: center;
            color: white;
            padding: 3rem 0;
        }

        .hero-section h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #a78bfa, #c4b5fd);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 900;
        }

        .hero-section p {
            font-size: 1.3rem;
            color: #a5b4fc;
            margin-bottom: 2rem;
        }

        .search-bar {
            max-width: 600px;
            margin: 0 auto 3rem;
            position: relative;
        }

        .search-bar input {
            width: 100%;
            padding: 1.2rem 1.5rem;
            border: 2px solid rgba(139, 92, 246, 0.3);
            border-radius: 16px;
            font-size: 1.1rem;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(10px);
            color: #e0e7ff;
            font-family: 'Inter', sans-serif;
            transition: all 0.4s ease;
        }

        .search-bar input:focus {
            outline: none;
            border-color: #8b5cf6;
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.15), 0 0 40px rgba(139, 92, 246, 0.3);
            background: rgba(15, 23, 42, 0.8);
        }

        .search-bar input::placeholder {
            color: #64748b;
        }

        .categories-section {
            margin-bottom: 3rem;
        }

        .section-title {
            text-align: center;
            background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 2rem;
            margin-bottom: 2rem;
            font-weight: 800;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .category-card {
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(10px);
            padding: 2rem;
            border-radius: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            text-decoration: none;
            color: inherit;
            border: 2px solid rgba(139, 92, 246, 0.2);
        }

        .category-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 50px rgba(139, 92, 246, 0.3), 0 0 60px rgba(99, 102, 241, 0.2);
            border-color: rgba(139, 92, 246, 0.5);
        }

        .category-card.active {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.3), rgba(99, 102, 241, 0.3));
            border-color: #8b5cf6;
            box-shadow: 0 0 40px rgba(139, 92, 246, 0.4);
        }

        .category-icon {
            margin-bottom: 1rem;
            filter: drop-shadow(0 0 10px rgba(139, 92, 246, 0.5));
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .category-icon svg {
            width: 48px;
            height: 48px;
        }

        .category-name {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #e0e7ff;
        }

        .category-desc {
            font-size: 0.9rem;
            color: #94a3b8;
        }

        .mentors-section {
            margin-top: 3rem;
        }

        .mentors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
        }

        .mentor-card {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 2rem;
            box-shadow: 0 0 40px rgba(139, 92, 246, 0.2), inset 0 0 0 1px rgba(139, 92, 246, 0.1);
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            cursor: pointer;
            border: 1px solid rgba(139, 92, 246, 0.2);
        }

        .mentor-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 0 60px rgba(139, 92, 246, 0.4), 0 20px 40px rgba(0, 0, 0, 0.3);
            border-color: rgba(139, 92, 246, 0.5);
        }

        .mentor-header {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .mentor-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #8b5cf6, #6366f1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            flex-shrink: 0;
            box-shadow: 0 0 30px rgba(139, 92, 246, 0.5);
        }

        .mentor-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .mentor-info {
            flex: 1;
        }

        .mentor-name {
            font-size: 1.3rem;
            font-weight: 700;
            background: linear-gradient(135deg, #a78bfa, #c4b5fd);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .mentor-rating {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .stars {
            color: #fbbf24;
            font-size: 1.2rem;
            filter: drop-shadow(0 0 5px rgba(251, 191, 36, 0.5));
        }

        .rating-text {
            color: #94a3b8;
            font-size: 0.9rem;
        }

        .mentor-categories {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        .category-badge {
            background: rgba(139, 92, 246, 0.2);
            padding: 0.4rem 0.9rem;
            border-radius: 8px;
            font-size: 0.85rem;
            color: #c4b5fd;
            border: 1px solid rgba(139, 92, 246, 0.3);
        }

        .mentor-bio {
            color: #cbd5e1;
            line-height: 1.6;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .mentor-skills {
            color: #94a3b8;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .mentor-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid rgba(139, 92, 246, 0.2);
        }

        .hourly-rate {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #a78bfa, #c4b5fd);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .btn-view {
            background: linear-gradient(135deg, #8b5cf6, #6366f1);
            color: white;
            padding: 0.8rem 1.8rem;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 10px 30px rgba(139, 92, 246, 0.3);
        }

        .btn-view:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 50px rgba(139, 92, 246, 0.5);
        }

        .no-results {
            text-align: center;
            color: #a5b4fc;
            font-size: 1.3rem;
            padding: 3rem;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(139, 92, 246, 0.2);
        }

        .filter-bar {
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(10px);
            padding: 1.2rem 2rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid rgba(139, 92, 246, 0.2);
            box-shadow: 0 0 30px rgba(139, 92, 246, 0.2);
        }

        @media (max-width: 768px) {
            .hero-section h1 {
                font-size: 2rem;
            }
            
            .mentors-grid {
                grid-template-columns: 1fr;
            }
            
            .categories-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .filter-bar {
                flex-direction: column;
                gap: 1rem;
            }

            .nav-bar {
                padding: 1rem 1.5rem;
            }

            .container {
                padding: 1rem;
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
        <div class="nav-buttons">
            <a href="my-sessions.php" class="btn btn-primary" style="background: linear-gradient(135deg, #6366f1, #8b5cf6); padding: 0.75rem 1.5rem; border: 1px solid rgba(139, 92, 246, 0.3); box-shadow: 0 0 20px rgba(139, 92, 246, 0.3);">My Sessions</a>
            <span style="color: #c7d2fe; padding: 0.75rem 1.5rem; background: rgba(30, 27, 75, 0.6); border-radius: 12px; border: 1px solid rgba(139, 92, 246, 0.3); font-weight: 600;"><?php echo htmlspecialchars($mentee_profile['full_name']); ?></span>
            <a href="logout.php" class="btn btn-secondary">Logout</a>
        </div>
    </nav>

    <div class="container">
        <?php if (isset($_SESSION['error'])): ?>
            <div style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 10px; margin-bottom: 1rem;">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <div class="hero-section">
            <h1>Find Your Perfect Mentor</h1>
            <p>Connect with expert mentors in any field</p>
            
            <div class="search-bar">
                <form method="GET" action="">
                    <?php if ($selected_category): ?>
                        <input type="hidden" name="category" value="<?php echo $selected_category; ?>">
                    <?php endif; ?>
                    <input type="text" 
                           name="search" 
                           placeholder="🔍 Search by name, skills, or expertise..." 
                           value="<?php echo htmlspecialchars($search_query); ?>">
                </form>
            </div>
        </div>

        <?php if (!$selected_category): ?>
        <div class="categories-section">
            <h2 class="section-title">Choose a Category</h2>
            <div class="categories-grid">
                <?php 
                // SVG icon mapping for categories
                $categoryIcons = [
                    'Programming' => '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="4" y="8" width="40" height="32" rx="2" stroke="#8b5cf6" stroke-width="2"/><path d="M16 20 L12 24 L16 28 M24 18 L20 30 M32 20 L36 24 L32 28" stroke="#6366f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
                    'School' => '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 14 L24 8 L36 14 L24 20 L12 14Z" stroke="#8b5cf6" stroke-width="2" stroke-linejoin="round"/><path d="M12 14 V28 L24 34 L36 28 V14" stroke="#6366f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M24 20 V34" stroke="#6366f1" stroke-width="2"/></svg>',
                    'University' => '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 20 L24 12 L40 20 M24 12 V36" stroke="#8b5cf6" stroke-width="2"/><rect x="18" y="26" width="12" height="10" stroke="#6366f1" stroke-width="2"/><path d="M8 20 V32 M40 20 V32" stroke="#8b5cf6" stroke-width="2"/><path d="M6 32 L42 32" stroke="#8b5cf6" stroke-width="2.5"/></svg>',
                    'Biology' => '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="24" cy="16" r="8" stroke="#8b5cf6" stroke-width="2"/><circle cx="16" cy="28" r="6" stroke="#6366f1" stroke-width="2"/><circle cx="32" cy="28" r="6" stroke="#6366f1" stroke-width="2"/><path d="M20 20 L18 26 M28 20 L30 26" stroke="#8b5cf6" stroke-width="1.5"/></svg>',
                    'Mathematics' => '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 8 L40 40 M8 40 L40 8" stroke="#8b5cf6" stroke-width="2"/><circle cx="24" cy="24" r="12" stroke="#6366f1" stroke-width="2"/><path d="M18 24 L30 24 M24 18 L24 30" stroke="#6366f1" stroke-width="2"/></svg>',
                    'Business' => '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="12" y="16" width="24" height="24" rx="2" stroke="#8b5cf6" stroke-width="2"/><path d="M20 16 V12 C20 10 21 8 24 8 C27 8 28 10 28 12 V16" stroke="#6366f1" stroke-width="2"/><line x1="12" y1="24" x2="36" y2="24" stroke="#6366f1" stroke-width="2"/></svg>',
                    'Languages' => '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="24" cy="24" r="16" stroke="#8b5cf6" stroke-width="2"/><path d="M8 24 C8 24 14 14 24 14 C34 14 40 24 40 24 C40 24 34 34 24 34 C14 34 8 24 8 24" stroke="#6366f1" stroke-width="2"/><path d="M24 8 V40 M12 16 L36 32 M12 32 L36 16" stroke="#6366f1" stroke-width="1.5"/></svg>',
                    'Arts' => '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M18 38 L12 42 L14 34 L8 28 L16 28 L20 20 L24 28 L32 28 L26 34 L28 42 L22 38" stroke="#8b5cf6" stroke-width="2" stroke-linejoin="round"/><circle cx="20" cy="12" r="4" fill="#6366f1"/><circle cx="28" cy="18" r="3" fill="#8b5cf6"/></svg>',
                    'Science' => '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M18 8 L18 20 L12 32 C10 36 12 40 16 40 L32 40 C36 40 38 36 36 32 L30 20 L30 8" stroke="#8b5cf6" stroke-width="2" stroke-linecap="round"/><line x1="18" y1="8" x2="30" y2="8" stroke="#6366f1" stroke-width="2"/><circle cx="24" cy="30" r="3" fill="#6366f1"/></svg>',
                    'Engineering' => '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="24" cy="24" r="14" stroke="#8b5cf6" stroke-width="2"/><circle cx="24" cy="24" r="4" stroke="#6366f1" stroke-width="2"/><path d="M24 10 V18 M24 30 V38 M10 24 H18 M30 24 H38" stroke="#6366f1" stroke-width="2"/><circle cx="24" cy="10" r="2" fill="#8b5cf6"/><circle cx="24" cy="38" r="2" fill="#8b5cf6"/><circle cx="10" cy="24" r="2" fill="#8b5cf6"/><circle cx="38" cy="24" r="2" fill="#8b5cf6"/></svg>'
                ];
                
                foreach ($categories as $cat): 
                    $svgIcon = $categoryIcons[$cat['name']] ?? $cat['icon'];
                ?>
                    <a href="?category=<?php echo $cat['id']; ?>" class="category-card">
                        <div class="category-icon"><?php echo $svgIcon; ?></div>
                        <div class="category-name"><?php echo htmlspecialchars($cat['name']); ?></div>
                        <div class="category-desc"><?php echo htmlspecialchars($cat['description']); ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($selected_category || $search_query): ?>
        <div class="mentors-section">
            <div class="filter-bar">
                <div style="color: #e0e7ff; font-weight: 600;">
                    Found <span style="color: #a78bfa; font-size: 1.2em;"><?php echo count($mentors); ?></span> mentor(s)
                    <?php if ($selected_category): 
                        $cat = array_values(array_filter($categories, fn($c) => $c['id'] == $selected_category))[0] ?? null;
                        if ($cat):
                    ?>
                        in <span style="color: #8b5cf6; font-weight: 700;"><?php echo getCategoryIconSVG($cat['name']) . ' ' . htmlspecialchars($cat['name']); ?></span>
                    <?php endif; endif; ?>
                </div>
                <a href="mentee-dashboard.php" class="btn btn-secondary">← Back to Categories</a>
            </div>

            <?php if (empty($mentors)): ?>
                <div class="no-results">
                    No mentors found. Try adjusting your filters.
                </div>
            <?php else: ?>
                <div class="mentors-grid">
                    <?php foreach ($mentors as $mentor): ?>
                        <div class="mentor-card" onclick="window.location='metnor-detail.php?id=<?php echo $mentor['id']; ?>'">
                            <div class="mentor-header">
                                <div class="mentor-avatar">
                                    <?php if ($mentor['profile_image']): ?>
                                        <img src="<?php echo htmlspecialchars($mentor['profile_image']); ?>" alt="<?php echo htmlspecialchars($mentor['full_name']); ?>">
                                    <?php else: ?>
                                        👤
                                    <?php endif; ?>
                                </div>
                                <div class="mentor-info">
                                    <div class="mentor-name"><?php echo htmlspecialchars($mentor['full_name']); ?></div>
                                    <div class="mentor-rating">
                                        <span class="stars">
                                            <?php 
                                            $rating = $mentor['average_rating'];
                                            for ($i = 1; $i <= 5; $i++) {
                                                echo $i <= $rating ? '⭐' : '☆';
                                            }
                                            ?>
                                        </span>
                                        <span class="rating-text">
                                            <?php echo number_format($mentor['average_rating'], 1); ?> 
                                            (<?php echo $mentor['total_reviews']; ?> reviews)
                                        </span>
                                    </div>
                                    <div class="mentor-categories">
                                        <?php 
                                        $cat_names = explode(',', $mentor['category_names']);
                                        for ($i = 0; $i < min(3, count($cat_names)); $i++): 
                                            $catName = trim($cat_names[$i]);
                                        ?>
                                            <span class="category-badge">
                                                <?php echo getCategoryIconSVG($catName) . ' ' . $catName; ?>
                                            </span>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mentor-bio">
                                <?php echo htmlspecialchars($mentor['bio']); ?>
                            </div>
                            
                            <div class="mentor-skills">
                                <strong>Skills:</strong> <?php echo htmlspecialchars($mentor['skills']); ?>
                            </div>
                            
                            <div class="mentor-footer">
                                <div class="hourly-rate">
                                    $<?php echo number_format($mentor['hourly_rate'] * 1.20, 0); ?>/hour
                                </div>
                                <a href="metnor-detail.php?id=<?php echo $mentor['id']; ?>" class="btn-view" onclick="event.stopPropagation()">
                                    View Profile →
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Add animation on load
        document.addEventListener('DOMContentLoaded', () => {
            const cards = document.querySelectorAll('.mentor-card, .category-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>