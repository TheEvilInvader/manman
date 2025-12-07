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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-primary: #6366f1;
            --color-primary-dark: #4f46e5;
            --color-secondary: #8b5cf6;
            --color-accent: #a78bfa;
            --color-neon: #c084fc;
            --color-dark: #0f172a;
            --color-darker: #020617;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-glow: linear-gradient(135deg, rgba(139, 92, 246, 0.3), rgba(99, 102, 241, 0.3));
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #1e1b4b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
            overflow-y: auto;
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

        body::before {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.15), transparent 70%);
            border-radius: 50%;
            top: -250px;
            right: -250px;
            animation: float 20s ease-in-out infinite;
        }

        body::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.15), transparent 70%);
            border-radius: 50%;
            bottom: -200px;
            left: -200px;
            animation: float 15s ease-in-out infinite reverse;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(50px, 50px); }
        }

        .register-container {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            border-radius: 32px;
            box-shadow: 
                0 0 80px rgba(139, 92, 246, 0.2),
                0 20px 60px rgba(0, 0, 0, 0.5),
                inset 0 0 0 1px rgba(167, 139, 250, 0.2);
            width: 100%;
            max-width: 550px;
            padding: 2.5rem;
            animation: slideUp 0.8s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            z-index: 10;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes glow {
            0%, 100% {
                box-shadow: 0 0 20px rgba(139, 92, 246, 0.4), 0 0 40px rgba(99, 102, 241, 0.2);
            }
            50% {
                box-shadow: 0 0 30px rgba(139, 92, 246, 0.6), 0 0 60px rgba(99, 102, 241, 0.4);
            }
        }

        .logo {
            text-align: center;
            font-size: 3rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #8b5cf6, #6366f1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 0 20px rgba(139, 92, 246, 0.5));
            animation: glow 3s ease-in-out infinite;
        }

        h1 {
            text-align: center;
            color: #e0e7ff;
            margin-bottom: 0.5rem;
            font-size: 2.5rem;
            font-weight: 900;
            letter-spacing: -0.5px;
        }

        .subtitle {
            text-align: center;
            color: #a5b4fc;
            margin-bottom: 2.5rem;
            font-size: 1rem;
            font-weight: 400;
            letter-spacing: 0.5px;
        }

        .role-selection {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .role-card {
            border: 2px solid rgba(139, 92, 246, 0.3);
            border-radius: 20px;
            padding: 2rem 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            background: rgba(15, 23, 42, 0.5);
            overflow: hidden;
        }

        .role-card::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 20px;
            padding: 2px;
            background: linear-gradient(135deg, #8b5cf6, #6366f1);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        .role-card input[type="radio"] {
            display: none;
        }

        .role-card:hover {
            transform: translateY(-8px) scale(1.02);
            border-color: rgba(139, 92, 246, 0.6);
            box-shadow: 
                0 20px 40px rgba(139, 92, 246, 0.3),
                0 0 60px rgba(99, 102, 241, 0.2),
                inset 0 0 20px rgba(139, 92, 246, 0.1);
        }

        .role-card.selected {
            border-color: transparent;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(99, 102, 241, 0.2));
            box-shadow: 
                0 0 50px rgba(139, 92, 246, 0.5),
                0 20px 40px rgba(139, 92, 246, 0.3),
                inset 0 0 30px rgba(139, 92, 246, 0.2);
            transform: scale(1.05);
        }

        .role-card.selected::before {
            opacity: 1;
        }

        .role-card label {
            cursor: pointer;
            display: block;
        }

        .role-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 1rem;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(99, 102, 241, 0.2));
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            transition: all 0.4s ease;
        }

        .role-icon::before {
            content: '';
            position: absolute;
            inset: -4px;
            border-radius: 50%;
            background: linear-gradient(135deg, #8b5cf6, #6366f1);
            opacity: 0;
            z-index: -1;
            transition: opacity 0.4s ease;
        }

        .role-card.selected .role-icon::before {
            opacity: 1;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                opacity: 0.7;
            }
            50% {
                transform: scale(1.1);
                opacity: 1;
            }
        }

        .role-icon svg {
            width: 32px;
            height: 32px;
            stroke: #a78bfa;
            transition: stroke 0.4s ease;
        }

        .role-card.selected .role-icon svg {
            stroke: #fff;
            filter: drop-shadow(0 0 8px rgba(167, 139, 250, 0.8));
        }

        .role-title {
            font-weight: 700;
            font-size: 1.2rem;
            background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
            transition: all 0.4s ease;
        }

        .role-card.selected .role-title {
            background: linear-gradient(135deg, #a78bfa, #c4b5fd);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .role-desc {
            font-size: 0.85rem;
            color: #94a3b8;
            font-weight: 400;
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
            letter-spacing: 0.3px;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 2px solid rgba(139, 92, 246, 0.2);
            border-radius: 14px;
            font-size: 1rem;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            font-family: 'Inter', sans-serif;
            font-weight: 500;
            background: rgba(15, 23, 42, 0.5);
            color: #e0e7ff;
            backdrop-filter: blur(10px);
        }

        input:focus {
            outline: none;
            border-color: #8b5cf6;
            box-shadow: 
                0 0 0 4px rgba(139, 92, 246, 0.15),
                0 0 30px rgba(139, 92, 246, 0.3),
                inset 0 0 20px rgba(139, 92, 246, 0.1);
            background: rgba(15, 23, 42, 0.7);
            transform: translateY(-2px);
        }

        input::placeholder {
            color: #64748b;
            font-weight: 400;
        }

        .btn {
            width: 100%;
            padding: 1.1rem;
            border: none;
            border-radius: 14px;
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
            background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);
            color: white;
            transition: all 0.2s ease;
            margin-top: 1rem;
            box-shadow: 0 10px 30px rgba(139, 92, 246, 0.4);
            letter-spacing: 0.5px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 40px rgba(139, 92, 246, 0.5);
        }

        .btn:active {
            transform: translateY(0);
        }

        .alert {
            padding: 1.1rem 1.5rem;
            border-radius: 14px;
            margin-bottom: 1.5rem;
            animation: slideDown 0.5s cubic-bezier(0.16, 1, 0.3, 1);
            font-weight: 500;
            font-size: 0.95rem;
            backdrop-filter: blur(10px);
            border: 1px solid;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            border-color: rgba(239, 68, 68, 0.3);
            box-shadow: 0 0 30px rgba(239, 68, 68, 0.2);
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.15);
            color: #86efac;
            border-color: rgba(34, 197, 94, 0.3);
            box-shadow: 0 0 30px rgba(34, 197, 94, 0.2);
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-link {
            text-align: center;
            margin-top: 2rem;
            color: #94a3b8;
            font-size: 0.95rem;
            font-weight: 400;
        }

        .login-link a {
            color: #a78bfa;
            text-decoration: none;
            font-weight: 700;
            transition: all 0.3s ease;
            position: relative;
        }

        .login-link a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, #8b5cf6, #6366f1);
            transition: width 0.3s ease;
        }

        .login-link a:hover::after {
            width: 100%;
        }

        .login-link a:hover {
            color: #c4b5fd;
            text-shadow: 0 0 10px rgba(167, 139, 250, 0.5);
        }

        .back-home {
            text-align: center;
            margin-bottom: 2rem;
        }

        .back-home a {
            color: #94a3b8;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            padding: 0.6rem 1.2rem;
            border-radius: 10px;
            border: 1px solid rgba(139, 92, 246, 0.2);
        }

        .back-home a:hover {
            background: rgba(139, 92, 246, 0.1);
            color: #a78bfa;
            border-color: rgba(139, 92, 246, 0.4);
            transform: translateX(-5px);
            box-shadow: 0 0 20px rgba(139, 92, 246, 0.2);
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

            .logo {
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="back-home">
            <a href="index.php">← Back to Home</a>
        </div>
        
        <div class="logo">
        </div>
        
        <div style="text-align: center; margin-bottom: 1.5rem;">
            <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" style="width: 64px; height: 64px; display: inline-block;">
                <!-- User icon with plus sign for registration -->
                <circle cx="32" cy="20" r="10" stroke="#8b5cf6" stroke-width="2" fill="none"/>
                <path d="M18 50 C18 38 24 34 32 34 C40 34 46 38 46 50" stroke="#8b5cf6" stroke-width="2" fill="none" stroke-linecap="round"/>
                <!-- Plus sign -->
                <circle cx="48" cy="16" r="10" fill="#6366f1"/>
                <path d="M48 12 L48 20 M44 16 L52 16" stroke="white" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        
        <h1>Create Account</h1>
        <p class="subtitle">Join MentorBridge and start your journey</p>

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
                            <div class="role-icon">
                                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M2 17L12 22L22 17" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M2 12L12 17L22 12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <div class="role-title">Mentor</div>
                            <div class="role-desc">Share your knowledge</div>
                        </label>
                    </div>
                    <div class="role-card">
                        <input type="radio" name="role" id="mentee" value="mentee" <?php echo $selectedRole === 'mentee' ? 'checked' : ''; ?> required>
                        <label for="mentee">
                            <div class="role-icon">
                                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2Z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M12 6V12L16 14" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
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