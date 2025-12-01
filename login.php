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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --color-1: #F0F3FA;
            --color-2: #D5DEEF;
            --color-3: #B1C9EF;
            --color-4: #8AAEE0;
            --color-5: #638ECB;
            --color-6: #395886;
            --white: #ffffff;
            --text-dark: #1a202c;
            --text-light: #4a5568;
            --shadow: rgba(57, 88, 134, 0.1);
            --shadow-hover: rgba(57, 88, 134, 0.2);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, var(--color-1) 0%, var(--color-2) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Animated Background */
        .animated-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            overflow: hidden;
        }

        .bg-circle {
            position: absolute;
            border-radius: 50%;
            opacity: 0.15;
            animation: float 20s infinite ease-in-out;
        }

        .circle-1 {
            width: 300px;
            height: 300px;
            background: linear-gradient(135deg, var(--color-4), var(--color-5));
            top: -150px;
            right: -150px;
        }

        .circle-2 {
            width: 250px;
            height: 250px;
            background: linear-gradient(135deg, var(--color-3), var(--color-4));
            bottom: -125px;
            left: -125px;
            animation-delay: 5s;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(30px, 30px) scale(1.1); }
        }

        .login-container {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px var(--shadow-hover);
            width: 100%;
            max-width: 450px;
            padding: 3rem;
            animation: slideUp 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            z-index: 1;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo-section {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--color-5), var(--color-6));
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            animation: pulse 2s ease infinite;
        }

        .logo-icon svg {
            width: 35px;
            height: 35px;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        h1 {
            text-align: center;
            color: var(--color-6);
            margin-bottom: 0.5rem;
            font-size: 2rem;
            font-weight: 700;
        }

        .subtitle {
            text-align: center;
            color: var(--text-light);
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.6rem;
            color: var(--text-dark);
            font-weight: 600;
            font-size: 0.9rem;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 0.95rem 1.2rem;
            border: 2px solid var(--color-2);
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
            background: var(--color-1);
        }

        input:focus {
            outline: none;
            border-color: var(--color-5);
            box-shadow: 0 0 0 4px rgba(99, 142, 203, 0.1);
            background: white;
        }

        .btn {
            width: 100%;
            padding: 1.1rem;
            border: none;
            border-radius: 12px;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            background: linear-gradient(135deg, var(--color-5), var(--color-6));
            color: white;
            transition: all 0.3s ease;
            margin-top: 0.5rem;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(57, 88, 134, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }

        .alert {
            padding: 1rem 1.2rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            animation: shake 0.5s ease;
            background: #fee;
            color: #c33;
            border-left: 4px solid #c33;
            font-size: 0.9rem;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-8px); }
            75% { transform: translateX(8px); }
        }

        .links-section {
            margin-top: 1.5rem;
        }

        .register-link {
            text-align: center;
            margin-top: 1.5rem;
            color: var(--text-light);
            font-size: 0.95rem;
        }

        .register-link a {
            color: var(--color-5);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .register-link a:hover {
            color: var(--color-6);
            text-decoration: underline;
        }

        .back-home {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .back-home a {
            color: var(--text-light);
            text-decoration: none;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: all 0.3s ease;
        }

        .back-home a:hover {
            color: var(--color-6);
            transform: translateX(-4px);
        }

        .forgot-password {
            text-align: right;
            margin-top: 0.5rem;
        }

        .forgot-password a {
            color: var(--text-light);
            text-decoration: none;
            font-size: 0.85rem;
            transition: color 0.3s ease;
        }

        .forgot-password a:hover {
            color: var(--color-5);
        }

        @media (max-width: 768px) {
            .login-container {
                padding: 2rem 1.5rem;
            }
            
            h1 {
                font-size: 1.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="animated-bg">
        <div class="bg-circle circle-1"></div>
        <div class="bg-circle circle-2"></div>
    </div>

    <div class="login-container">
        <div class="back-home">
            <a href="index.php">← Back to Home</a>
        </div>
        
        <div class="logo-section">
            <div class="logo-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                    <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                    <path d="M2 17l10 5 10-5"></path>
                    <path d="M2 12l10 5 10-5"></path>
                </svg>
            </div>
            <h1>Welcome Back</h1>
            <p class="subtitle">Login to your MentorBridge account</p>
        </div>

        <?php if ($error): ?>
            <div class="alert"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required placeholder="Enter your email">
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

    <script>
        // Add input animation
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateY(-2px)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>
