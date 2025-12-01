<?php
// logout.php - Logout Handler with Animation
session_start();
session_unset();
session_destroy();

// Clear any cookies
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging Out - MentorBridge</title>
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
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, var(--color-1) 0%, var(--color-2) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logout-container {
            background: white;
            padding: 3rem;
            border-radius: 24px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(57, 88, 134, 0.2);
            animation: fadeIn 0.5s ease;
            max-width: 450px;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .logout-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--color-5), var(--color-6));
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            animation: wave 1.5s ease infinite;
        }

        .logout-icon svg {
            width: 40px;
            height: 40px;
        }

        @keyframes wave {
            0%, 100% { transform: rotate(0deg) scale(1); }
            50% { transform: rotate(-10deg) scale(1.05); }
        }

        h1 {
            color: var(--color-6);
            margin-bottom: 1rem;
            font-size: 2rem;
            font-weight: 700;
        }

        p {
            color: #4a5568;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid var(--color-2);
            border-top: 4px solid var(--color-5);
            border-radius: 50%;
            margin: 0 auto 1.5rem;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="logout-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
        </div>
        <h1>Logging Out...</h1>
        <div class="spinner"></div>
        <p>You have been successfully logged out.<br>Redirecting to home page...</p>
    </div>

    <script>
        setTimeout(function() {
            window.location.href = 'index.php';
        }, 2000);
    </script>
</body>
</html>
