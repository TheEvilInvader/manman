<?php
// register.php - Registration with Role Selection
require_once 'config.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';
$selectedRole = $_GET['role'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = sanitize($_POST['role']);
    $full_name = sanitize($_POST['full_name']);
    
    if (empty($email) || empty($password) || empty($role) || empty($full_name)) {
        $error = 'All fields are required';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif (!in_array($role, ['mentor', 'mentee'])) {
        $error = 'Invalid role selected';
    } else {
        $mysqli = getDB();
        
        // Check if email exists
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->fetch_assoc()) {
            $error = 'Email already registered';
        } else {
            $mysqli->begin_transaction();
            
            try {
                // Create user
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $mysqli->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $email, $password_hash, $role);
                $stmt->execute();
                $user_id = $mysqli->insert_id;
                
                // Create profile based on role
                if ($role === 'mentor') {
                    $stmt = $mysqli->prepare("INSERT INTO mentor_profiles (user_id, full_name, status) VALUES (?, ?, 'pending')");
                    $stmt->bind_param("is", $user_id, $full_name);
                    $stmt->execute();
                } else {
                    $stmt = $mysqli->prepare("INSERT INTO mentee_profiles (user_id, full_name) VALUES (?, ?)");
                    $stmt->bind_param("is", $user_id, $full_name);
                    $stmt->execute();
                }
                
                $mysqli->commit();
                
                // Auto login
                $_SESSION['user_id'] = $user_id;
                $_SESSION['role'] = $role;
                $_SESSION['email'] = $email;
                
                redirect('dashboard.php');
            } catch(Exception $e) {
                $mysqli->rollback();
                $error = 'Registration failed. Please try again.';
            }
        }
        
        $stmt->close();
        $mysqli->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - MentorBridge</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .register-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 500px;
            padding: 3rem;
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo {
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        h1 {
            text-align: center;
            color: #667eea;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 2rem;
        }

        .role-selection {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .role-card {
            border: 3px solid #e0e0e0;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .role-card input[type="radio"] {
            display: none;
        }

        .role-card:hover {
            border-color: #667eea;
            transform: translateY(-5px);
        }

        .role-card input[type="radio"]:checked + label {
            border-color: #667eea;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
        }

        .role-card label {
            cursor: pointer;
            display: block;
        }

        .role-icon {
            font-size: 3rem;
            margin-bottom: 0.5rem;
        }

        .role-title {
            font-weight: 600;
            font-size: 1.2rem;
            color: #333;
            margin-bottom: 0.3rem;
        }

        .role-desc {
            font-size: 0.9rem;
            color: #666;
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
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 0.9rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            animation: shake 0.5s ease;
        }

        .alert-error {
            background: #fee;
            color: #c33;
            border: 2px solid #fcc;
        }

        .alert-success {
            background: #efe;
            color: #3c3;
            border: 2px solid #cfc;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            color: #666;
        }

        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .back-home {
            text-align: center;
            margin-bottom: 1rem;
        }

        .back-home a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        @media (max-width: 768px) {
            .register-container {
                padding: 2rem;
            }
            
            .role-selection {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="back-home">
            <a href="index.php">← Back to Home</a>
        </div>
        
        <div class="logo">🎓</div>
        <h1>Create Account</h1>
        <p class="subtitle">Join MentorBridge today</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" required placeholder="Enter your full name">
            </div>

            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required placeholder="your@email.com">
            </div>

            <div class="form-group">
                <label>I want to be a:</label>
                <div class="role-selection">
                    <div class="role-card">
                        <input type="radio" name="role" id="mentor" value="mentor" <?php echo $selectedRole === 'mentor' ? 'checked' : ''; ?> required>
                        <label for="mentor">
                            <div class="role-icon">👨‍🏫</div>
                            <div class="role-title">Mentor</div>
                            <div class="role-desc">Share your knowledge</div>
                        </label>
                    </div>
                    <div class="role-card">
                        <input type="radio" name="role" id="mentee" value="mentee" <?php echo $selectedRole === 'mentee' ? 'checked' : ''; ?> required>
                        <label for="mentee">
                            <div class="role-icon">👨‍🎓</div>
                            <div class="role-title">Mentee</div>
                            <div class="role-desc">Learn from experts</div>
                        </label>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="At least 6 characters">
            </div>

            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" required placeholder="Re-enter password">
            </div>

            <button type="submit" class="btn">Create Account</button>
        </form>

        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>

    <script>
        // Add click animation to role cards
        document.querySelectorAll('.role-card').forEach(card => {
            card.addEventListener('click', function() {
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
                
                // Remove selected class from all cards
                document.querySelectorAll('.role-card').forEach(c => {
                    c.style.borderColor = '#e0e0e0';
                    c.style.background = 'white';
                });
                
                // Add selected class to clicked card
                this.style.borderColor = '#667eea';
                this.style.background = 'linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1))';
            });
        });
        
        // Auto-select role if passed in URL
        const selectedRole = new URLSearchParams(window.location.search).get('role');
        if (selectedRole) {
            const roleCard = document.getElementById(selectedRole);
            if (roleCard) {
                roleCard.click();
            }
        }
    </script>
</body>
</html>