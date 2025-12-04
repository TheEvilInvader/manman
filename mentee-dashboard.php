<?php
// mentee-dashboard.php - Mentee Dashboard with Category Selection & Mentor Search
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
        }

        .nav-bar {
            background: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-buttons {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn {
            padding: 0.6rem 1.5rem;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .hero-section {
            text-align: center;
            color: white;
            padding: 3rem 0;
        }

        .hero-section h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .hero-section p {
            font-size: 1.3rem;
            opacity: 0.9;
            margin-bottom: 2rem;
        }

        .search-bar {
            max-width: 600px;
            margin: 0 auto 3rem;
            position: relative;
        }

        .search-bar input {
            width: 100%;
            padding: 1rem 1.5rem;
            border: none;
            border-radius: 50px;
            font-size: 1.1rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        .search-bar input:focus {
            outline: none;
            box-shadow: 0 10px 50px rgba(0,0,0,0.3);
        }

        .categories-section {
            margin-bottom: 3rem;
        }

        .section-title {
            text-align: center;
            color: white;
            font-size: 2rem;
            margin-bottom: 2rem;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .category-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .category-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
        }

        .category-card.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .category-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .category-name {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .category-desc {
            font-size: 0.9rem;
            opacity: 0.7;
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
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .mentor-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
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
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            flex-shrink: 0;
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
            font-weight: 600;
            color: #667eea;
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
        }

        .rating-text {
            color: #666;
            font-size: 0.9rem;
        }

        .mentor-categories {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        .category-badge {
            background: #f3f4f6;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            color: #667eea;
        }

        .mentor-bio {
            color: #666;
            line-height: 1.6;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .mentor-skills {
            color: #999;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .mentor-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid #f0f0f0;
        }

        .hourly-rate {
            font-size: 1.5rem;
            font-weight: 600;
            color: #667eea;
        }

        .btn-view {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 0.7rem 1.5rem;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .no-results {
            text-align: center;
            color: white;
            font-size: 1.3rem;
            padding: 3rem;
        }

        .filter-bar {
            background: white;
            padding: 1rem 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
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
        }
    </style>
</head>
<body>
    <nav class="nav-bar">
        <div class="logo">🎓 MentorBridge</div>
        <div class="nav-buttons">
            <span style="color: #666;">👤 <?php echo htmlspecialchars($mentee_profile['full_name']); ?></span>
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
                <?php foreach ($categories as $cat): ?>
                    <a href="?category=<?php echo $cat['id']; ?>" class="category-card">
                        <div class="category-icon"><?php echo $cat['icon']; ?></div>
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
                <div>
                    <strong>Found <?php echo count($mentors); ?> mentor(s)</strong>
                    <?php if ($selected_category): 
                        $cat = array_values(array_filter($categories, fn($c) => $c['id'] == $selected_category))[0] ?? null;
                        if ($cat):
                    ?>
                        in <span style="color: #667eea;"><?php echo $cat['icon'] . ' ' . htmlspecialchars($cat['name']); ?></span>
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
                                        $cat_icons = explode(',', $mentor['category_icons']);
                                        for ($i = 0; $i < min(3, count($cat_names)); $i++): 
                                        ?>
                                            <span class="category-badge">
                                                <?php echo $cat_icons[$i] . ' ' . $cat_names[$i]; ?>
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
                                    $<?php echo number_format($mentor['hourly_rate'], 0); ?>/hour
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