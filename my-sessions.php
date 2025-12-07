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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #1e1b4b 100%);
            min-height: 100vh;
            padding: 20px;
            color: #e0e7ff;
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
            padding: 1.5rem 2.5rem;
            border-radius: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            box-shadow: 0 0 40px rgba(139, 92, 246, 0.2), inset 0 0 0 1px rgba(139, 92, 246, 0.1);
            border: 1px solid rgba(139, 92, 246, 0.2);
            position: relative;
            z-index: 10;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, #a78bfa, #c4b5fd);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 0 20px rgba(139, 92, 246, 0.5));
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

        .btn-primary {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            box-shadow: 0 10px 30px rgba(139, 92, 246, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 40px rgba(139, 92, 246, 0.5);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
            z-index: 10;
        }

        .header {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            padding: 2rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            box-shadow: 0 0 40px rgba(139, 92, 246, 0.2), inset 0 0 0 1px rgba(139, 92, 246, 0.1);
            border: 1px solid rgba(139, 92, 246, 0.2);
        }

        .header h1 {
            color: #e0e7ff;
            margin-bottom: 0.5rem;
            font-size: 2rem;
            font-weight: 800;
        }

        .header p {
            color: #94a3b8;
        }

        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            animation: slideDown 0.3s ease;
            backdrop-filter: blur(10px);
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

        .sessions-grid {
            display: grid;
            gap: 1.5rem;
        }

        .session-card {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 0 40px rgba(139, 92, 246, 0.2), inset 0 0 0 1px rgba(139, 92, 246, 0.1);
            border: 1px solid rgba(139, 92, 246, 0.2);
            border-left: 4px solid #8b5cf6;
            transition: all 0.3s ease;
        }

        .session-card:hover {
            box-shadow: 0 0 60px rgba(139, 92, 246, 0.3);
            border-color: rgba(139, 92, 246, 0.4);
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
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            border: 2px solid rgba(139, 92, 246, 0.3);
            box-shadow: 0 0 20px rgba(139, 92, 246, 0.3);
        }

        .mentor-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .mentor-name {
            color: #c7d2fe;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            border: 1px solid;
        }

        .status-completed {
            background: rgba(34, 197, 94, 0.15);
            color: #4ade80;
            border-color: rgba(34, 197, 94, 0.3);
        }

        .status-confirmed {
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
            border-color: rgba(59, 130, 246, 0.3);
        }

        .status-pending {
            background: rgba(251, 191, 36, 0.15);
            color: #fbbf24;
            border-color: rgba(251, 191, 36, 0.3);
        }

        .session-details {
            color: #cbd5e1;
            margin-bottom: 1.5rem;
        }

        .session-details p {
            margin-bottom: 0.5rem;
        }

        .session-details strong {
            color: #94a3b8;
        }

        .feedback-section {
            background: rgba(30, 27, 75, 0.6);
            padding: 1.5rem;
            border-radius: 12px;
            margin-top: 1rem;
            border: 1px solid rgba(139, 92, 246, 0.2);
        }

        .feedback-section h3 {
            color: #c7d2fe;
            margin-bottom: 1rem;
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
            border: 2px solid rgba(139, 92, 246, 0.3);
            border-radius: 12px;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            min-height: 100px;
            resize: vertical;
            background: rgba(30, 27, 75, 0.6);
            color: #e0e7ff;
        }

        textarea:focus {
            outline: none;
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }

        textarea::placeholder {
            color: #64748b;
        }

        .submitted-feedback {
            background: rgba(30, 27, 75, 0.6);
            padding: 1.5rem;
            border-radius: 12px;
            margin-top: 1rem;
            border: 1px solid rgba(139, 92, 246, 0.2);
        }

        .submitted-feedback h3 {
            color: #c7d2fe;
            margin-bottom: 1rem;
        }

        .submitted-feedback p {
            color: #cbd5e1;
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
