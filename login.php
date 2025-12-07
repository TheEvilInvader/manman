<?php
// login.php - Login Page
require_once 'config.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password';
    } else {
        $mysqli = getDB();
        $stmt = $mysqli->prepare("SELECT id, email, password, role, status FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'suspended') {
                $error = 'Your account has been suspended';
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['email'] = $user['email'];
                
                redirect('dashboard.php');
            }
        } else {
            $error = 'Invalid email or password';
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
    <title>Login - MentorBridge</title>
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
            animation: float 20s infinite ease-in-out;
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
            animation: float 15s infinite ease-in-out reverse;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(30px, -50px) rotate(120deg); }
            66% { transform: translate(-20px, 20px) rotate(240deg); }
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

        @keyframes glow {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        @keyframes btnGlow {
            0%, 100% { box-shadow: 0 0 20px rgba(139, 92, 246, 0.4), 0 0 40px rgba(99, 102, 241, 0.2); }
            50% { box-shadow: 0 0 30px rgba(139, 92, 246, 0.6), 0 0 60px rgba(99, 102, 241, 0.3); }
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

        .login-container {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(139, 92, 246, 0.2);
            border-radius: 28px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4), 0 0 100px rgba(139, 92, 246, 0.1);
            width: 100%;
            max-width: 500px;
            padding: 2.5rem;
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            z-index: 1;
        }

        .login-container::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 28px;
            padding: 1px;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.4), rgba(99, 102, 241, 0.2), transparent);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
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
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }

        .back-home a:hover {
            color: #c4b5fd;
            background: rgba(139, 92, 246, 0.1);
            transform: translateX(-5px);
            box-shadow: 0 0 20px rgba(139, 92, 246, 0.2);
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
            font-size: 0.95rem;
            font-weight: 400;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.7rem;
            color: #c4b5fd;
            font-weight: 600;
            font-size: 0.9rem;
            letter-spacing: 0.3px;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 1rem 1.3rem;
            border: 1px solid rgba(139, 92, 246, 0.3);
            border-radius: 14px;
            font-size: 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: 'Inter', sans-serif;
            background: rgba(30, 27, 75, 0.6);
            color: #e0e7ff;
            font-weight: 500;
        }

        input[type="email"]::placeholder,
        input[type="password"]::placeholder {
            color: #64748b;
        }

        input:focus {
            outline: none;
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1), 0 0 30px rgba(139, 92, 246, 0.3);
            background: rgba(30, 27, 75, 0.8);
            transform: translateY(-1px);
        }

        .forgot-password {
            text-align: right;
            margin-top: 0.5rem;
        }

        .forgot-password a {
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }

        .forgot-password a:hover {
            color: #c4b5fd;
            text-shadow: 0 0 10px rgba(167, 139, 250, 0.5);
        }

        .btn {
            width: 100%;
            padding: 1.2rem;
            border: none;
            border-radius: 14px;
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            transition: all 0.2s ease;
            margin-top: 0.5rem;
            letter-spacing: 0.5px;
            box-shadow: 0 10px 30px rgba(139, 92, 246, 0.4);
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
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
            box-shadow: 0 0 30px rgba(239, 68, 68, 0.2);
        }

        .register-link {
            text-align: center;
            margin-top: 2rem;
            color: #94a3b8;
            font-size: 0.95rem;
        }

        .register-link a {
            color: #a78bfa;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
        }

        .register-link a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, #8b5cf6, #6366f1);
            transition: width 0.3s ease;
        }

        .register-link a:hover {
            color: #c4b5fd;
            text-shadow: 0 0 10px rgba(167, 139, 250, 0.5);
        }

        .register-link a:hover::after {
            width: 100%;
        }

        @media (max-width: 768px) {
            .login-container {
                padding: 2rem;
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
    <div class="login-container">
        <div class="back-home">
            <a href="index.php">← Back to Home</a>
        </div>
        
        <div class="logo">
        </div>
        
        <div style="text-align: center; margin-bottom: 1.5rem;">
            <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" style="width: 64px; height: 64px; display: inline-block;">
                <!-- User icon with arrow for login -->
                <circle cx="28" cy="20" r="10" stroke="#8b5cf6" stroke-width="2" fill="none"/>
                <path d="M14 50 C14 38 20 34 28 34 C36 34 42 38 42 50" stroke="#8b5cf6" stroke-width="2" fill="none" stroke-linecap="round"/>
                <!-- Arrow pointing in -->
                <path d="M52 32 L44 32 M48 28 L52 32 L48 36" stroke="#6366f1" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        
        <h1>Welcome Back</h1>
        <p class="subtitle">Login to your MentorBridge account</p>

        <?php if ($error): ?>
            <div class="alert"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required placeholder="your@email.com">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="Enter your password">
                <div class="forgot-password">
                    <a href="#">Forgot password?</a>
                </div>
            </div>

            <button type="submit" class="btn">Login</button>
        </form>

        <div class="register-link">
            Don't have an account? <a href="register.php">Sign up here</a>
        </div>
    </div>
</body>
</html>