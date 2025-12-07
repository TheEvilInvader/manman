<?php
// mentor-detail.php - Detailed Mentor Profile & Booking
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
$mentor_id = intval($_GET['id'] ?? 0);

// Get mentor details
$stmt = $mysqli->prepare("
    SELECT mp.*, 
           GROUP_CONCAT(DISTINCT c.name) as category_names,
           GROUP_CONCAT(DISTINCT c.icon) as category_icons
    FROM mentor_profiles mp
    LEFT JOIN mentor_categories mc ON mp.id = mc.mentor_id
    LEFT JOIN categories c ON mc.category_id = c.id
    WHERE mp.id = ? AND mp.status = 'approved'
    GROUP BY mp.id
");
$stmt->bind_param("i", $mentor_id);
$stmt->execute();
$result = $stmt->get_result();
$mentor = $result->fetch_assoc();
$stmt->close();

if (!$mentor) {
    redirect('mentee-dashboard.php');
}

// Get feedback/reviews
$stmt = $mysqli->prepare("
    SELECT f.*, mp.full_name as mentee_name, s.scheduled_at
    FROM feedback f
    JOIN sessions s ON f.session_id = s.id
    JOIN mentee_profiles mp ON s.mentee_id = mp.id
    WHERE s.mentor_id = ?
    ORDER BY f.created_at DESC
    LIMIT 10
");
$stmt->bind_param("i", $mentor_id);
$stmt->execute();
$result = $stmt->get_result();
$reviews = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get available time slots from database
$stmt = $mysqli->prepare("
    SELECT day_of_week, TIME_FORMAT(time_slot, '%H:%i') as time_slot
    FROM mentor_availability
    WHERE mentor_id = ? AND is_available = 1
    ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), time_slot
");
$stmt->bind_param("i", $mentor_id);
$stmt->execute();
$result = $stmt->get_result();
$availability = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Organize by day
$available_times = [];
foreach ($availability as $slot) {
    $available_times[$slot['day_of_week']][] = $slot['time_slot'];
}

$mysqli->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($mentor['full_name']); ?> - MentorBridge</title>
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

        .nav-bar {
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(20px);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            border-bottom: 1px solid rgba(139, 92, 246, 0.2);
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            background: linear-gradient(135deg, #8b5cf6, #6366f1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 0 10px rgba(139, 92, 246, 0.5));
        }

        .btn {
            padding: 0.6rem 1.5rem;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .btn-secondary {
            background: rgba(139, 92, 246, 0.2);
            color: #c4b5fd;
            border: 1px solid rgba(139, 92, 246, 0.3);
        }

        .btn-secondary:hover {
            background: rgba(139, 92, 246, 0.3);
        }

        .btn-primary {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(139, 92, 246, 0.6);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .back-link {
            color: #a78bfa;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
            font-weight: 600;
        }

        .back-link:hover {
            color: #c4b5fd;
            text-decoration: underline;
        }

        .profile-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 2rem;
        }

        .profile-main {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(139, 92, 246, 0.2);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 0 40px rgba(139, 92, 246, 0.2);
        }

        .profile-header {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 2px solid rgba(139, 92, 246, 0.2);
        }

        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: white;
            flex-shrink: 0;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .profile-info h1 {
            font-size: 2.5rem;
            background: linear-gradient(135deg, #a78bfa, #c4b5fd);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }

        .rating-large {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .stars-large {
            font-size: 1.8rem;
            color: #fbbf24;
        }

        .rating-text-large {
            font-size: 1.2rem;
            color: #94a3b8;
        }

        .category-badges {
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }

        .category-badge {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.95rem;
        }

        .hourly-rate-large {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }

        .section {
            margin-bottom: 2.5rem;
        }

        .section h2 {
            color: #e0e7ff;
            font-size: 1.8rem;
            margin-bottom: 1rem;
        }

        .section-content {
            color: #94a3b8;
            line-height: 1.8;
            font-size: 1.1rem;
        }

        .skills-list {
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
        }

        .skill-tag {
            background: rgba(139, 92, 246, 0.2);
            color: #c4b5fd;
            border: 1px solid rgba(139, 92, 246, 0.3);
            padding: 0.5rem 1rem;
            border-radius: 20px;
        }

        .reviews-section {
            margin-top: 3rem;
        }

        .reviews-section h2 {
            color: #e0e7ff;
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            font-weight: 800;
        }

        .review-card {
            background: rgba(30, 27, 75, 0.6);
            border: 1px solid rgba(139, 92, 246, 0.2);
            padding: 1.5rem;
            border-radius: 14px;
            margin-bottom: 1rem;
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .reviewer-name {
            font-weight: 600;
            color: #c7d2fe;
        }

        .review-date {
            color: #94a3b8;
            font-size: 0.9rem;
        }

        .review-stars {
            color: #fbbf24;
            margin-bottom: 0.5rem;
        }

        .review-text {
            color: #cbd5e1;
            line-height: 1.6;
        }

        .booking-sidebar {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 0 40px rgba(139, 92, 246, 0.2), inset 0 0 0 1px rgba(139, 92, 246, 0.1);
            border: 1px solid rgba(139, 92, 246, 0.2);
            position: sticky;
            top: 2rem;
            height: fit-content;
        }

        .booking-sidebar h3 {
            background: linear-gradient(135deg, #a78bfa, #c4b5fd);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            font-weight: 800;
        }

        .time-slots {
            margin-bottom: 1.5rem;
        }

        .day-section {
            margin-bottom: 1.5rem;
        }

        .day-name {
            font-weight: 600;
            color: #c7d2fe;
            margin-bottom: 0.8rem;
        }

        .time-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
        }

        .time-btn {
            padding: 0.6rem;
            border: 2px solid rgba(139, 92, 246, 0.3);
            border-radius: 10px;
            background: rgba(30, 27, 75, 0.6);
            color: #e0e7ff;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .time-btn:hover {
            border-color: rgba(139, 92, 246, 0.5);
            background: rgba(30, 27, 75, 0.8);
            box-shadow: 0 0 20px rgba(139, 92, 246, 0.3);
        }

        .time-btn.selected {
            border-color: #8b5cf6;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            box-shadow: 0 0 30px rgba(139, 92, 246, 0.5);
        }

        .booking-summary {
            background: rgba(30, 27, 75, 0.6);
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            border: 1px solid rgba(139, 92, 246, 0.2);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            color: #94a3b8;
        }

        .summary-row.total {
            font-weight: bold;
            background: linear-gradient(135deg, #a78bfa, #c4b5fd);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 1.2rem;
            padding-top: 0.5rem;
            border-top: 2px solid #e0e0e0;
        }

        @media (max-width: 968px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
            
            .booking-sidebar {
                position: static;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <nav class="nav-bar">
        <div class="logo">🎓 MentorBridge</div>
        <div>
            <a href="logout.php" class="btn btn-secondary">Logout</a>
        </div>
    </nav>

    <div class="container">
        <a href="mentee-dashboard.php" class="back-link">← Back to Search</a>

        <div class="profile-container">
            <div class="profile-main">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php if ($mentor['profile_image']): ?>
                            <img src="<?php echo htmlspecialchars($mentor['profile_image']); ?>" alt="<?php echo htmlspecialchars($mentor['full_name']); ?>">
                        <?php else: ?>
                            👤
                        <?php endif; ?>
                    </div>
                    <div class="profile-info">
                        <h1><?php echo htmlspecialchars($mentor['full_name']); ?></h1>
                        <div class="rating-large">
                            <span class="stars-large">
                                <?php 
                                $rating = $mentor['average_rating'];
                                for ($i = 1; $i <= 5; $i++) {
                                    echo $i <= $rating ? '⭐' : '☆';
                                }
                                ?>
                            </span>
                            <span class="rating-text-large">
                                <?php echo number_format($mentor['average_rating'], 1); ?> 
                                (<?php echo $mentor['total_reviews']; ?> reviews)
                            </span>
                        </div>
                        <div class="category-badges">
                            <?php 
                            if (!empty($mentor['category_names'])) {
                                $cat_names = explode(',', $mentor['category_names']);
                                for ($i = 0; $i < count($cat_names); $i++): 
                                    $catName = trim($cat_names[$i]);
                            ?>
                                <span class="category-badge">
                                    <?php echo getCategoryIconSVG($catName) . ' ' . $catName; ?>
                                </span>
                            <?php 
                                endfor;
                            }
                            ?>
                        </div>
                        <div class="hourly-rate-large">
                            $<?php echo number_format($mentor['hourly_rate'] * 1.20, 0); ?>/hour
                        </div>
                    </div>
                </div>

                <div class="section">
                    <h2>📖 About Me</h2>
                    <div class="section-content">
                        <?php echo nl2br(htmlspecialchars($mentor['bio'])); ?>
                    </div>
                </div>

                <div class="section">
                    <h2>💼 Experience</h2>
                    <div class="section-content">
                        <?php echo nl2br(htmlspecialchars($mentor['experience'])); ?>
                    </div>
                </div>

                <div class="section">
                    <h2>🛠️ Skills</h2>
                    <div class="skills-list">
                        <?php 
                        $skills = explode(',', $mentor['skills']);
                        foreach ($skills as $skill): 
                        ?>
                            <span class="skill-tag"><?php echo trim(htmlspecialchars($skill)); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="reviews-section">
                    <h2>⭐ Reviews (<?php echo count($reviews); ?>)</h2>
                    <?php if (empty($reviews)): ?>
                        <p style="color: #94a3b8;">No reviews yet. Be the first to book and review!</p>
                    <?php else: ?>
                        <?php foreach ($reviews as $review): ?>
                            <div class="review-card">
                                <div class="review-header">
                                    <span class="reviewer-name"><?php echo htmlspecialchars($review['mentee_name']); ?></span>
                                    <span class="review-date"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></span>
                                </div>
                                <div class="review-stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php echo $i <= $review['rating'] ? '⭐' : '☆'; ?>
                                    <?php endfor; ?>
                                </div>
                                <div class="review-text">
                                    <?php echo htmlspecialchars($review['comment']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="booking-sidebar">
                <h3>📅 Book a Session</h3>
                
                <form method="POST" action="book-session.php">
                    <input type="hidden" name="mentor_id" value="<?php echo $mentor['id']; ?>">
                    
                    <div class="time-slots">
                        <p style="color: #94a3b8; margin-bottom: 1rem;">Select a date and time:</p>
                        <?php if (empty($available_times)): ?>
                            <div style="text-align: center; padding: 2rem; color: #94a3b8;">
                                <div style="font-size: 3rem; margin-bottom: 1rem;">📅</div>
                                <p>No available time slots at the moment.</p>
                                <p style="font-size: 0.9rem; margin-top: 0.5rem;">Please check back later.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($available_times as $day => $times): ?>
                                <div class="day-section">
                                    <div class="day-name"><?php echo $day; ?></div>
                                    <div class="time-buttons">
                                        <?php foreach ($times as $time): 
                                            $end_hour = intval(substr($time, 0, 2)) + 1;
                                            $end_time = str_pad($end_hour, 2, '0', STR_PAD_LEFT) . substr($time, 2);
                                        ?>
                                            <button type="button" class="time-btn" onclick="selectTime(this, '<?php echo $day; ?>', '<?php echo $time; ?>')">
                                                <?php echo $time . ' - ' . $end_time; ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <input type="hidden" name="selected_day" id="selected_day">
                    <input type="hidden" name="selected_time" id="selected_time">

                    <div class="booking-summary">
                        <div class="summary-row">
                            <span>Duration:</span>
                            <span>1 hour</span>
                        </div>
                        <div class="summary-row">
                            <span>Mentor Rate:</span>
                            <span>$<?php echo number_format($mentor['hourly_rate'], 2); ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Platform Fee (20%):</span>
                            <span>$<?php echo number_format($mentor['hourly_rate'] * 0.20, 2); ?></span>
                        </div>
                        <div class="summary-row total">
                            <span>Total:</span>
                            <span>$<?php echo number_format($mentor['hourly_rate'] * 1.20, 2); ?></span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem; font-size: 1.1rem;">
                        Proceed to Payment →
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        let selectedButton = null;

        function selectTime(btn, day, time) {
            if (selectedButton) {
                selectedButton.classList.remove('selected');
            }
            btn.classList.add('selected');
            selectedButton = btn;
            
            document.getElementById('selected_day').value = day;
            document.getElementById('selected_time').value = time;
        }

        // Validate form before submission
        document.querySelector('form').addEventListener('submit', function(e) {
            if (!document.getElementById('selected_day').value) {
                e.preventDefault();
                alert('Please select a date and time for your session');
            }
        });
    </script>
</body>
</html>