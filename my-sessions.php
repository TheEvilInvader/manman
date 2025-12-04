<?php
// my-sessions.php - Mentee's Session History and Feedback
require_once 'config.php';
requireRole('mentee');

$mysqli = getDB();
$user_id = getUserId();

// Get mentee profile
$stmt = $mysqli->prepare("SELECT * FROM mentee_profiles WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$mentee = $result->fetch_assoc();
$stmt->close();

if (!$mentee) {
    redirect('mentee-dashboard.php');
}

$success = '';
$error = '';

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $session_id = intval($_POST['session_id']);
    $rating = intval($_POST['rating']);
    $comment = sanitize($_POST['comment']);
    
    if ($rating >= 1 && $rating <= 5) {
        // Check if feedback already exists
        $stmt = $mysqli->prepare("SELECT id FROM feedback WHERE session_id = ?");
        $stmt->bind_param("i", $session_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        if ($result->num_rows === 0) {
            $stmt = $mysqli->prepare("INSERT INTO feedback (session_id, rating, comment) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $session_id, $rating, $comment);
            if ($stmt->execute()) {
                $success = 'Thank you for your feedback!';
            } else {
                $error = 'Failed to submit feedback.';
            }
            $stmt->close();
        } else {
            $error = 'You have already submitted feedback for this session.';
        }
    }
}

// Get all sessions with feedback status
$stmt = $mysqli->prepare("
    SELECT s.id, s.mentor_id, s.mentee_id, s.scheduled_at, s.duration, s.status, 
           s.payment_status, s.notes, s.created_at,
           COALESCE(NULLIF(s.amount, 0), NULLIF(mp.hourly_rate * 1.20, 0), 60.00) as amount,
           COALESCE(NULLIF(mp.full_name, ''), 'John Doe') as mentor_name, 
           mp.profile_image,
           COALESCE(NULLIF(mp.hourly_rate, 0), 50.00) as hourly_rate,
           f.id as feedback_id, f.rating, f.comment as feedback_comment
    FROM sessions s
    JOIN mentor_profiles mp ON s.mentor_id = mp.id
    JOIN users u ON mp.user_id = u.id
    LEFT JOIN feedback f ON s.id = f.session_id
    WHERE s.mentee_id = ?
    ORDER BY s.scheduled_at DESC
");
$stmt->bind_param("i", $mentee['id']);
$stmt->execute();
$result = $stmt->get_result();
$sessions = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$mysqli->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Sessions - MentorBridge</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-primary: #638ECB;
            --color-primary-dark: #395886;
            --color-primary-light: #8AAEE0;
            --color-bg-light: #F0F3FA;
            --color-bg-lighter: #D5DEEF;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--color-bg-light) 0%, var(--color-bg-lighter) 100%);
            min-height: 100vh;
            padding: 20px;
            color: #1e293b;
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
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
            text-decoration: none;
            display: inline-block;
        }

        .btn-secondary {
            background: var(--color-bg-light);
            color: var(--color-primary-dark);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
            color: white;
            box-shadow: 0 4px 15px rgba(99, 142, 203, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(99, 142, 203, 0.4);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            box-shadow: 0 10px 40px rgba(99, 142, 203, 0.08);
        }

        .header h1 {
            color: var(--color-primary-dark);
            margin-bottom: 0.5rem;
            font-size: 2rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            animation: slideDown 0.3s ease;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 2px solid #6ee7b7;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 2px solid #fca5a5;
        }

        .sessions-grid {
            display: grid;
            gap: 1.5rem;
        }

        .session-card {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(99, 142, 203, 0.08);
            border-left: 4px solid var(--color-primary);
        }

        .session-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1.5rem;
        }

        .mentor-info {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .mentor-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--color-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .mentor-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-completed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-confirmed {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .session-details {
            color: #64748b;
            margin-bottom: 1.5rem;
        }

        .session-details p {
            margin-bottom: 0.5rem;
        }

        .feedback-section {
            background: var(--color-bg-light);
            padding: 1.5rem;
            border-radius: 12px;
            margin-top: 1rem;
        }

        .rating-stars {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .star {
            font-size: 2rem;
            cursor: pointer;
            transition: all 0.2s ease;
            color: #d1d5db;
        }

        .star:hover, .star.active {
            color: #fbbf24;
            transform: scale(1.1);
        }

        textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--color-bg-lighter);
            border-radius: 12px;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            min-height: 100px;
            resize: vertical;
        }

        textarea:focus {
            outline: none;
            border-color: var(--color-primary);
        }

        .submitted-feedback {
            background: var(--color-bg-light);
            padding: 1.5rem;
            border-radius: 12px;
            margin-top: 1rem;
        }

        .submitted-stars {
            color: #fbbf24;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        @media (max-width: 768px) {
            .session-header {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <nav class="nav-bar">
        <div class="logo">MentorBridge</div>
        <a href="mentee-dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </nav>

    <div class="container">
        <div class="header">
            <h1>📚 My Sessions</h1>
            <p style="color: #64748b;">View your session history and provide feedback</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="sessions-grid">
            <?php if (empty($sessions)): ?>
                <div class="session-card" style="text-align: center; padding: 3rem;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">📅</div>
                    <h3>No sessions yet</h3>
                    <p style="color: #64748b;">Book your first mentorship session to get started!</p>
                    <a href="mentee-dashboard.php" class="btn btn-primary" style="margin-top: 1rem;">Find Mentors</a>
                </div>
            <?php else: ?>
                <?php foreach ($sessions as $session): ?>
                    <div class="session-card">
                        <div class="session-header">
                            <div class="mentor-info">
                                <div class="mentor-avatar">
                                    <?php if ($session['profile_image']): ?>
                                        <img src="<?php echo htmlspecialchars($session['profile_image']); ?>" alt="Mentor">
                                    <?php else: ?>
                                        👤
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h3 style="color: var(--color-primary-dark); margin-bottom: 0.25rem;">
                                        <?php echo htmlspecialchars($session['mentor_name']); ?>
                                    </h3>
                                    <p style="color: #64748b; font-size: 0.9rem;">
                                        $<?php echo number_format($session['hourly_rate'], 2); ?>/hour
                                    </p>
                                </div>
                            </div>
                            <span class="status-badge status-<?php echo $session['status']; ?>">
                                <?php echo ucfirst($session['status']); ?>
                            </span>
                        </div>

                        <div class="session-details">
                            <p><strong>📅 Date & Time:</strong> 
                                <?php 
                                $start = strtotime($session['scheduled_at']);
                                $end = $start + 3600;
                                echo date('l, F j, Y', $start) . ' at ' . 
                                     date('g:i A', $start) . ' - ' . date('g:i A', $end);
                                ?>
                            </p>
                            <p><strong>💰 Amount:</strong> $<?php echo number_format($session['amount'], 2); ?></p>
                            <p><strong>💳 Payment:</strong> <?php echo ucfirst($session['payment_status']); ?></p>
                            <p><strong>📊 Status:</strong> <?php echo ucfirst($session['status']); ?></p>
                        </div>

                        <?php if ($session['status'] === 'completed'): ?>
                            <?php if (!$session['feedback_id']): ?>
                            <div class="feedback-section">
                                <h4 style="color: var(--color-primary-dark); margin-bottom: 1rem;">⭐ Rate This Session</h4>
                                <form method="POST" id="feedback-form-<?php echo $session['id']; ?>">
                                    <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                                    <input type="hidden" name="submit_feedback" value="1">
                                    <input type="hidden" name="rating" id="rating-<?php echo $session['id']; ?>" value="5">
                                    
                                    <div class="rating-stars" id="stars-<?php echo $session['id']; ?>">
                                        <span class="star active" data-rating="1" onclick="setRating(<?php echo $session['id']; ?>, 1)">★</span>
                                        <span class="star active" data-rating="2" onclick="setRating(<?php echo $session['id']; ?>, 2)">★</span>
                                        <span class="star active" data-rating="3" onclick="setRating(<?php echo $session['id']; ?>, 3)">★</span>
                                        <span class="star active" data-rating="4" onclick="setRating(<?php echo $session['id']; ?>, 4)">★</span>
                                        <span class="star active" data-rating="5" onclick="setRating(<?php echo $session['id']; ?>, 5)">★</span>
                                    </div>
                                    
                                    <textarea name="comment" placeholder="Share your experience with this mentor..." required></textarea>
                                    
                                    <button type="submit" class="btn btn-primary" style="margin-top: 1rem; width: 100%;">
                                        Submit Feedback
                                    </button>
                                </form>
                            </div>
                            <?php else: ?>
                            <div class="submitted-feedback">
                                <h4 style="color: var(--color-primary-dark); margin-bottom: 0.5rem;">Your Feedback</h4>
                                <div class="submitted-stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php echo $i <= $session['rating'] ? '★' : '☆'; ?>
                                    <?php endfor; ?>
                                </div>
                                <p style="color: #64748b;"><?php echo htmlspecialchars($session['feedback_comment']); ?></p>
                            </div>
                            <?php endif; ?>
                        <?php elseif ($session['status'] === 'confirmed' || $session['status'] === 'pending'): ?>
                            <div style="background: #fef3c7; padding: 1rem; border-radius: 12px; margin-top: 1rem;">
                                <p style="color: #92400e;">⏳ Waiting for mentor to complete this session before you can provide feedback.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function setRating(sessionId, rating) {
            const stars = document.querySelectorAll(`#stars-${sessionId} .star`);
            const ratingInput = document.getElementById(`rating-${sessionId}`);
            
            ratingInput.value = rating;
            
            stars.forEach((star, index) => {
                if (index < rating) {
                    star.classList.add('active');
                } else {
                    star.classList.remove('active');
                }
            });
        }
    </script>
</body>
</html>
