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
    $bio = sanitize($_POST['bio'] ?? '');
    $skills = sanitize($_POST['skills'] ?? '');
    $experience = sanitize($_POST['experience'] ?? '');
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

        .nav-buttons {
            display: flex;
            gap: 1rem;
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

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .status-banner {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .status-pending {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: white;
        }

        .status-approved {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .status-rejected {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
            font-size: 3rem;
            margin-bottom: 1rem;
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
            font-size: 1rem;
        }

        .profile-card {
            background: white;
            padding: 2.5rem;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        h2 {
            color: #667eea;
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }

        input[type="text"],
        input[type="number"],
        input[type="file"],
        textarea,
        select {
            width: 100%;
            padding: 0.9rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
        }

        .category-checkbox {
            display: flex;
            align-items: center;
            padding: 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .category-checkbox:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }

        .category-checkbox input[type="checkbox"]:checked + label {
            color: #667eea;
            font-weight: 600;
        }

        .category-checkbox input[type="checkbox"] {
            margin-right: 0.5rem;
            cursor: pointer;
        }

        .profile-image-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 10px;
            margin-top: 1rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
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

        @media (max-width: 768px) {
            .nav-bar {
                flex-direction: column;
                gap: 1rem;
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
                    💾 Save Profile
                </button>
            </form>
        </div>
    </div>
</body>
</html>