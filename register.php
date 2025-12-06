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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-primary: #638ECB;
            --color-primary-dark: #395886;
            --color-primary-light: #8AAEE0;
            --color-accent: #B1C9EF;
            --color-bg-light: #F0F3FA;
            --color-bg-lighter: #D5DEEF;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, var(--color-bg-light) 0%, var(--color-bg-lighter) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .register-container {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(99, 142, 203, 0.15);
            width: 100%;
            max-width: 520px;
            padding: 3rem;
            animation: slideUp 0.5s ease;
            border: 1px solid rgba(99, 142, 203, 0.1);
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
            font-size: 3rem;
            margin-bottom: 0.5rem;
            filter: drop-shadow(0 2px 4px rgba(99, 142, 203, 0.2));
        }

        h1 {
            text-align: center;
            background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .subtitle {
            text-align: center;
            color: #64748b;
            margin-bottom: 2rem;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .role-selection {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .role-card {
            border: 2px solid var(--color-bg-lighter);
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            background: white;
        }

        .role-card input[type="radio"] {
            display: none;
        }

        .role-card:hover {
            border-color: var(--color-primary-light);
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(99, 142, 203, 0.15);
        }

        .role-card input[type="radio"]:checked + label {
            border-color: var(--color-primary);
        }

        .role-card.selected {
            border-color: var(--color-primary);
            background: linear-gradient(135deg, rgba(99, 142, 203, 0.05), rgba(177, 201, 239, 0.08));
            box-shadow: 0 8px 25px rgba(99, 142, 203, 0.15);
        }

        .role-card label {
            cursor: pointer;
            display: block;
        }

        .role-icon {
            font-size: 3rem;
            margin-bottom: 0.5rem;
            filter: grayscale(0.3);
            transition: filter 0.3s ease;
        }

        .role-card.selected .role-icon {
            filter: grayscale(0);
        }

        .role-title {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--color-primary-dark);
            margin-bottom: 0.3rem;
        }

        .role-desc {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--color-primary-dark);
            font-weight: 600;
            font-size: 0.9rem;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 0.9rem 1rem;
            border: 2px solid var(--color-bg-lighter);
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
            font-weight: 500;
        }

        input:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 4px rgba(99, 142, 203, 0.1);
        }

        input::placeholder {
            color: #94a3b8;
            font-weight: 400;
        }

        .btn {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 12px;
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
            background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
            color: white;
            transition: all 0.3s ease;
            margin-top: 1rem;
            box-shadow: 0 4px 15px rgba(99, 142, 203, 0.3);
            letter-spacing: 0.3px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99, 142, 203, 0.4);
        }

        .btn:active {
            transform: translateY(0);
        }

        .alert {
            padding: 1rem 1.25rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            animation: slideDown 0.3s ease;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 2px solid #fca5a5;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 2px solid #6ee7b7;
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

        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            color: #64748b;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .login-link a {
            color: var(--color-primary);
            text-decoration: none;
            font-weight: 700;
            transition: color 0.3s ease;
        }

        .login-link a:hover {
            color: var(--color-primary-dark);
            text-decoration: underline;
        }

        .back-home {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .back-home a {
            color: var(--color-primary);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }

        .back-home a:hover {
            background: rgba(99, 142, 203, 0.1);
            color: var(--color-primary-dark);
        }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 1.5rem 0;
            color: #94a3b8;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid var(--color-bg-lighter);
        }

        .divider span {
            padding: 0 1rem;
        }

        @media (max-width: 768px) {
            .register-container {
                padding: 2rem;
            }
            
            .role-selection {
                grid-template-columns: 1fr;
            }
            
            h1 {
                font-size: 1.75rem;
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
                    c.classList.remove('selected');
                });
                
                // Add selected class to clicked card
                this.classList.add('selected');
            });
        });
        
        // Auto-select role if passed in URL
        const urlParams = new URLSearchParams(window.location.search);
        const selectedRole = urlParams.get('role');
        if (selectedRole) {
            const roleRadio = document.getElementById(selectedRole);
            if (roleRadio) {
                roleRadio.checked = true;
                roleRadio.closest('.role-card').classList.add('selected');
            }
        }
        
        // Check if any role is already selected on page load
        document.querySelectorAll('input[name="role"]').forEach(radio => {
            if (radio.checked) {
                radio.closest('.role-card').classList.add('selected');
            }
        });
    </script>
</body>
</html>