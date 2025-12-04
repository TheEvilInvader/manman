<?php
// mentor-detail.php - Detailed Mentor Profile & Booking
require_once 'config.php';
requireRole('mentee');

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

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .back-link {
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
            font-weight: 600;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .profile-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 2rem;
        }

        .profile-main {
            background: white;
            border-radius: 15px;
            padding: 2.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .profile-header {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 2px solid #f0f0f0;
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
            color: #667eea;
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
            color: #666;
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
            color: #667eea;
            font-size: 1.8rem;
            margin-bottom: 1rem;
        }

        .section-content {
            color: #666;
            line-height: 1.8;
            font-size: 1.1rem;
        }

        .skills-list {
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
        }

        .skill-tag {
            background: #f3f4f6;
            color: #667eea;
            padding: 0.5rem 1rem;
            border-radius: 20px;
        }

        .reviews-section {
            margin-top: 3rem;
        }

        .review-card {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1rem;
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .reviewer-name {
            font-weight: 600;
            color: #333;
        }

        .review-date {
            color: #999;
            font-size: 0.9rem;
        }

        .review-stars {
            color: #fbbf24;
            margin-bottom: 0.5rem;
        }

        .review-text {
            color: #666;
            line-height: 1.6;
        }

        .booking-sidebar {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            position: sticky;
            top: 2rem;
            height: fit-content;
        }

        .booking-sidebar h3 {
            color: #667eea;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }

        .time-slots {
            margin-bottom: 1.5rem;
        }

        .day-section {
            margin-bottom: 1.5rem;
        }

        .day-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.8rem;
        }

        .time-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
        }

        .time-btn {
            padding: 0.6rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .time-btn:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }

        .time-btn.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .booking-summary {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            color: #666;
        }

        .summary-row.total {
            font-weight: bold;
            color: #333;
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
                                $cat_icons = !empty($mentor['category_icons']) ? explode(',', $mentor['category_icons']) : [];
                                for ($i = 0; $i < count($cat_names); $i++): 
                                    $icon = $cat_icons[$i] ?? '📚';
                            ?>
                                <span class="category-badge">
                                    <?php echo $icon . ' ' . trim($cat_names[$i]); ?>
                                </span>
                            <?php 
                                endfor;
                            }
                            ?>
                        </div>
                        <div class="hourly-rate-large">
                            $<?php echo number_format($mentor['hourly_rate'], 0); ?>/hour
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
                        <p style="color: #666;">No reviews yet. Be the first to book and review!</p>
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
                        <p style="color: #666; margin-bottom: 1rem;">Select a date and time:</p>
                        <?php if (empty($available_times)): ?>
                            <div style="text-align: center; padding: 2rem; color: #999;">
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
                            <span>Rate:</span>
                            <span>$<?php echo number_format($mentor['hourly_rate'], 2); ?></span>
                        </div>
                        <div class="summary-row total">
                            <span>Total:</span>
                            <span>$<?php echo number_format($mentor['hourly_rate'], 2); ?></span>
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