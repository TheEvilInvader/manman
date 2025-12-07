<?php
// mentor-profile.php - Mentor Profile & Dashboard
require_once 'config.php';
requireRole('mentor');

$mysqli = getDB();
$user_id = getUserId();

// Get mentor profile
$stmt = $mysqli->prepare("
    SELECT mp.*, u.email 
    FROM mentor_profiles mp 
    JOIN users u ON mp.user_id = u.id 
    WHERE mp.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc();
$result->free();
$stmt->close();

// Get categories
$result = $mysqli->query("SELECT * FROM categories ORDER BY name");
$categories = $result->fetch_all(MYSQLI_ASSOC);
$result->free();

// Get mentor's selected categories
$stmt = $mysqli->prepare("SELECT category_id FROM mentor_categories WHERE mentor_id = ?");
$stmt->bind_param("i", $profile['id']);
$stmt->execute();
$result = $stmt->get_result();
$selected_categories = [];
while ($row = $result->fetch_assoc()) {
    $selected_categories[] = $row['category_id'];
}
$result->free();
$stmt->close();

$success = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['complete_session'])) {
    $full_name = sanitize($_POST['full_name'] ?? '');
    $bio = $_POST['bio'] ?? '';
    $skills = sanitize($_POST['skills'] ?? '');
    $experience = $_POST['experience'] ?? '';
    $hourly_rate = floatval($_POST['hourly_rate'] ?? 0);
    $selected_cats = $_POST['categories'] ?? [];
    
    $mysqli->begin_transaction();
    
    try {
        // Handle profile image upload
        $profile_image = $profile['profile_image'];
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['profile_image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $new_filename = 'mentor_' . $user_id . '_' . time() . '.' . $ext;
                $upload_path = 'uploads/' . $new_filename;
                
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                    $profile_image = $upload_path;
                }
            }
        }
        
        // If mentor is approved and making changes, set status back to pending for re-approval
        $new_status = $profile['status'];
        if ($profile['status'] === 'approved') {
            $new_status = 'pending';
            $_SESSION['info'] = 'Profile changes submitted for admin re-approval. You can continue managing sessions.';
        }
        
        // Update mentor profile
        $stmt = $mysqli->prepare("
            UPDATE mentor_profiles 
            SET full_name = ?, bio = ?, skills = ?, experience = ?, 
                hourly_rate = ?, profile_image = ?, status = ?
            WHERE user_id = ?
        ");
        $stmt->bind_param("ssssdssi", $full_name, $bio, $skills, $experience, $hourly_rate, $profile_image, $new_status, $user_id);
        $stmt->execute();
        $stmt->close();
        
        // Update categories
        $stmt = $mysqli->prepare("DELETE FROM mentor_categories WHERE mentor_id = ?");
        $stmt->bind_param("i", $profile['id']);
        $stmt->execute();
        $stmt->close();
        
        if (!empty($selected_cats)) {
            $stmt = $mysqli->prepare("INSERT INTO mentor_categories (mentor_id, category_id) VALUES (?, ?)");
            foreach ($selected_cats as $cat_id) {
                $stmt->bind_param("ii", $profile['id'], $cat_id);
                $stmt->execute();
            }
            $stmt->close();
        }
        
        $mysqli->commit();
        $success = 'Profile updated successfully!';
        
        // Refresh profile
        $stmt = $mysqli->prepare("SELECT * FROM mentor_profiles WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $profile = $result->fetch_assoc();
        $result->free();
        $stmt->close();
        
        $stmt = $mysqli->prepare("SELECT category_id FROM mentor_categories WHERE mentor_id = ?");
        $stmt->bind_param("i", $profile['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $selected_categories = [];
        while ($row = $result->fetch_assoc()) {
            $selected_categories[] = $row['category_id'];
        }
        $result->free();
        $stmt->close();
        
    } catch(Exception $e) {
        $mysqli->rollback();
        $error = 'Update failed. Please try again.';
    }
}

// Get mentor statistics
$stmt = $mysqli->prepare("SELECT COUNT(*) as total FROM sessions WHERE mentor_id = ? AND status = 'completed'");
$stmt->bind_param("i", $profile['id']);
$stmt->execute();
$result = $stmt->get_result();
$stats = $result->fetch_assoc();
$total_sessions = $stats['total'];
$result->free();
$stmt->close();

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
    SELECT s.*, mp.full_name as mentee_name, mp.interests
    FROM sessions s
    JOIN mentee_profiles mp ON s.mentee_id = mp.id
    WHERE s.mentor_id = ? AND s.status IN ('pending', 'confirmed')
    ORDER BY s.scheduled_at ASC
");
$stmt->bind_param("i", $profile['id']);
$stmt->execute();
$result = $stmt->get_result();
$pending_sessions = $result->fetch_all(MYSQLI_ASSOC);
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
            padding: 20px;
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
            border-radius: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
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
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
            z-index: 10;
        }

        .status-banner {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            padding: 1.5rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            text-align: center;
            box-shadow: 0 0 40px rgba(139, 92, 246, 0.2), inset 0 0 0 1px rgba(139, 92, 246, 0.1);
            border: 1px solid rgba(139, 92, 246, 0.2);
        }

        .status-pending {
            background: linear-gradient(135deg, rgba(251, 191, 36, 0.2), rgba(245, 158, 11, 0.2));
            color: #fbbf24;
            border-color: rgba(251, 191, 36, 0.3);
        }

        .status-approved {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.2), rgba(5, 150, 105, 0.2));
            color: #4ade80;
            border-color: rgba(34, 197, 94, 0.3);
        }

        .status-rejected {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(220, 38, 38, 0.2));
            color: #fca5a5;
            border-color: rgba(239, 68, 68, 0.3);
        }

        .dashboard-grid {
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
            text-align: center;
            box-shadow: 0 0 40px rgba(139, 92, 246, 0.2), inset 0 0 0 1px rgba(139, 92, 246, 0.1);
            transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            border: 1px solid rgba(139, 92, 246, 0.2);
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 0 60px rgba(139, 92, 246, 0.4), 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .stat-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            filter: drop-shadow(0 0 10px rgba(139, 92, 246, 0.5));
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 900;
            background: linear-gradient(135deg, #a78bfa, #c4b5fd);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #94a3b8;
            font-size: 1rem;
        }

        .profile-card {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            padding: 2.5rem;
            border-radius: 24px;
            box-shadow: 0 0 40px rgba(139, 92, 246, 0.2), inset 0 0 0 1px rgba(139, 92, 246, 0.1);
            border: 1px solid rgba(139, 92, 246, 0.2);
        }

        h2 {
            color: #e0e7ff;
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
            font-weight: 800;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.75rem;
            color: #c7d2fe;
            font-weight: 600;
            font-size: 0.9rem;
        }

        input[type="text"],
        input[type="number"],
        textarea,
        select {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 2px solid rgba(139, 92, 246, 0.2);
            border-radius: 14px;
            font-size: 1rem;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            font-family: 'Inter', sans-serif;
            background: rgba(15, 23, 42, 0.5);
            color: #e0e7ff;
            backdrop-filter: blur(10px);
        }

        input[type="file"] {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 2px solid rgba(139, 92, 246, 0.2);
            border-radius: 14px;
            font-size: 1rem;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            font-family: 'Inter', sans-serif;
            background: rgba(15, 23, 42, 0.5);
            color: #e0e7ff;
            backdrop-filter: blur(10px);
            cursor: pointer;
        }

        input[type="file"]::file-selector-button {
            padding: 0.5rem 1rem;
            border: 1px solid rgba(139, 92, 246, 0.3);
            border-radius: 8px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            cursor: pointer;
            font-weight: 600;
            margin-right: 1rem;
            transition: all 0.2s ease;
        }

        input[type="file"]::file-selector-button:hover {
            background: linear-gradient(135deg, #7c3aed, #a78bfa);
            box-shadow: 0 0 15px rgba(139, 92, 246, 0.4);
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: #8b5cf6;
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.15), 0 0 30px rgba(139, 92, 246, 0.3);
            background: rgba(15, 23, 42, 0.7);
        }

        input::placeholder,
        textarea::placeholder {
            color: #64748b;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
        }

        .category-checkbox {
            display: flex;
            align-items: center;
            padding: 0;
            border: 2px solid rgba(139, 92, 246, 0.2);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(15, 23, 42, 0.5);
            color: #cbd5e1;
            position: relative;
        }

        .category-checkbox:hover {
            border-color: rgba(139, 92, 246, 0.5);
            background: rgba(139, 92, 246, 0.1);
            box-shadow: 0 0 20px rgba(139, 92, 246, 0.2);
        }

        .category-checkbox:has(input[type="checkbox"]:checked) {
            background: rgba(139, 92, 246, 0.15);
            border-color: rgba(139, 92, 246, 0.6);
        }

        .category-checkbox input[type="checkbox"]:checked + label {
            color: #c4b5fd;
            font-weight: 700;
        }

        .category-checkbox input[type="checkbox"] {
            margin: 0;
            padding: 0;
            width: 20px;
            height: 20px;
            margin-left: 1rem;
            cursor: pointer;
            accent-color: #8b5cf6;
        }

        .category-checkbox label {
            flex: 1;
            padding: 1rem;
            padding-left: 0.5rem;
            cursor: pointer;
            margin: 0;
        }

        .profile-image-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 16px;
            margin-top: 1rem;
            border: 2px solid rgba(139, 92, 246, 0.3);
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

        @media (max-width: 768px) {
            .nav-bar {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem 1.5rem;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="nav-bar">
        <div class="logo">🎓 MentorBridge</div>
        <div class="nav-buttons">
            <span style="color: #666;">👤 <?php echo htmlspecialchars(!empty($profile['full_name']) ? $profile['full_name'] : 'John Doe'); ?></span>
            <?php if ($profile['status'] === 'approved'): ?>
                <a href="mentor-dashboard.php" class="btn btn-primary">Back to Dashboard</a>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-secondary">Logout</a>
        </div>
    </nav>

    <div class="container">
        <?php if (isset($_SESSION['info'])): ?>
            <div class="status-banner" style="background: linear-gradient(135deg, #3b82f6, #2563eb);">
                ℹ️ <?php echo $_SESSION['info']; unset($_SESSION['info']); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($profile['status'] === 'pending' && empty($profile['full_name'])): ?>
            <div class="status-banner status-pending">
                👋 Welcome! Please complete your mentor profile to get started.
            </div>
        <?php elseif ($profile['status'] === 'pending'): ?>
            <div class="status-banner status-pending">
                ⏳ Your profile is pending admin approval. You'll be notified once approved.
            </div>
        <?php elseif ($profile['status'] === 'approved'): ?>
            <div class="status-banner status-approved">
                ✅ Edit your profile here - changes will require admin re-approval but won't interrupt your sessions.
            </div>
        <?php elseif ($profile['status'] === 'rejected'): ?>
            <div class="status-banner status-rejected">
                ❌ Your profile was not approved. Please contact support for more information.
            </div>
        <?php endif; ?>

        <div class="dashboard-grid">
            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-value"><?php echo $total_sessions; ?></div>
                <div class="stat-label">Completed Sessions</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">⭐</div>
                <div class="stat-value"><?php echo number_format($profile['average_rating'], 1); ?></div>
                <div class="stat-label">Average Rating</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">💬</div>
                <div class="stat-value"><?php echo $profile['total_reviews']; ?></div>
                <div class="stat-label">Total Reviews</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-value">$<?php echo number_format($profile['hourly_rate'], 0); ?></div>
                <div class="stat-label">Hourly Rate</div>
            </div>
        </div>

        <div class="profile-card">
            <h2>📝 Your Profile</h2>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($profile['full_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Profile Image</label>
                    <input type="file" name="profile_image" accept="image/*">
                    <?php if ($profile['profile_image']): ?>
                        <img src="<?php echo htmlspecialchars($profile['profile_image']); ?>" alt="Profile" class="profile-image-preview">
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Bio (Tell students about yourself)</label>
                    <textarea name="bio" required><?php echo htmlspecialchars($profile['bio']); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Skills (Comma-separated)</label>
                    <input type="text" name="skills" value="<?php echo htmlspecialchars($profile['skills']); ?>" placeholder="e.g., Python, Machine Learning, Web Development" required>
                </div>

                <div class="form-group">
                    <label>Experience</label>
                    <textarea name="experience" placeholder="Describe your professional experience"><?php echo htmlspecialchars($profile['experience']); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Hourly Rate ($)</label>
                    <input type="number" name="hourly_rate" value="<?php echo $profile['hourly_rate']; ?>" min="0" step="0.01" required>
                </div>

                <div class="form-group">
                    <label>Categories (Select all that apply)</label>
                    <div class="categories-grid">
                        <?php foreach ($categories as $cat): ?>
                            <div class="category-checkbox">
                                <input type="checkbox" 
                                       name="categories[]" 
                                       value="<?php echo $cat['id']; ?>" 
                                       id="cat_<?php echo $cat['id']; ?>"
                                       <?php echo in_array($cat['id'], $selected_categories) ? 'checked' : ''; ?>>
                                <label for="cat_<?php echo $cat['id']; ?>">
                                    <?php echo $cat['icon'] . ' ' . htmlspecialchars($cat['name']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem; font-size: 1.1rem;">
                    Save Profile
                </button>
            </form>
        </div>
    </div>
</body>
</html>