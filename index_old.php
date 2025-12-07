<?php
// index.php - Landing Page
require_once 'config.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect('dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MentorBridge - Connect. Learn. Grow.</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
            color: var(--text-dark);
            overflow-x: hidden;
            line-height: 1.6;
        }

        /* Animated Background */
        .animated-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }

        .bg-circle {
            position: absolute;
            border-radius: 50%;
            opacity: 0.15;
            animation: float 25s infinite ease-in-out;
        }

        .circle-1 {
            width: 400px;
            height: 400px;
            background: linear-gradient(135deg, var(--color-4), var(--color-5));
            top: -200px;
            left: -200px;
            animation-delay: 0s;
        }

        .circle-2 {
            width: 300px;
            height: 300px;
            background: linear-gradient(135deg, var(--color-3), var(--color-4));
            top: 40%;
            right: -150px;
            animation-delay: 8s;
        }

        .circle-3 {
            width: 350px;
            height: 350px;
            background: linear-gradient(135deg, var(--color-5), var(--color-6));
            bottom: -175px;
            left: 25%;
            animation-delay: 15s;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(60px, 60px) scale(1.1); }
            66% { transform: translate(-60px, 80px) scale(0.9); }
        }

        /* Navigation */
        nav {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 1.2rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 20px var(--shadow);
            animation: slideDown 0.5s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-100%);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .nav-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 2rem;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--color-5), var(--color-6));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            cursor: pointer;
            transition: all 0.3s ease;
            letter-spacing: -0.5px;
        }

        .logo:hover {
            transform: scale(1.05);
        }

        .nav-buttons {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .theme-toggle {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .theme-toggle:hover {
            transform: rotate(180deg);
        }

        .btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
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
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s ease;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--color-5), var(--color-6));
            color: white;
            box-shadow: 0 4px 15px rgba(57, 88, 134, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(57, 88, 134, 0.4);
        }

        .btn-secondary {
            background: transparent;
            color: var(--color-6);
            border: 2px solid var(--color-5);
        }

        .btn-secondary:hover {
            background: var(--color-1);
            transform: translateY(-2px);
        }

        /* Hero Section */
        .hero {
            max-width: 1200px;
            margin: 0 auto;
            padding: 8rem 2rem 6rem;
            text-align: center;
        }

        .hero-content {
            animation: fadeInUp 1s ease;
        }

        .hero h1 {
            font-size: 4rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, var(--color-6), var(--color-5), var(--color-4));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.2;
            letter-spacing: -2px;
        }

        .hero p {
            font-size: 1.4rem;
            color: var(--text-light);
            margin-bottom: 3rem;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            font-weight: 400;
        }

        .hero-buttons {
            display: flex;
            gap: 1.5rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Features Section */
        .features {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            padding: 6rem 2rem;
        }

        .features-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .section-title {
            font-size: 3rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 1rem;
            color: var(--color-6);
        }

        .section-subtitle {
            text-align: center;
            font-size: 1.2rem;
            color: var(--text-light);
            margin-bottom: 4rem;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 2.5rem;
        }

        .feature-card {
            background: white;
            padding: 2.5rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px var(--shadow);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--color-5), var(--color-4));
            transform: scaleX(0);
            transition: transform 0.4s ease;
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 50px var(--shadow-hover);
        }

        .feature-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--color-3), var(--color-4));
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1.5rem;
            transition: all 0.4s ease;
        }

        .feature-card:hover .feature-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .feature-card h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--color-6);
        }

        .feature-card p {
            color: var(--text-light);
            line-height: 1.7;
            font-size: 1rem;
        }

        /* Stats Section */
        .stats {
            padding: 6rem 2rem;
            background: linear-gradient(135deg, var(--color-5), var(--color-6));
        }

        .stats-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 3rem;
            text-align: center;
        }

        .stat-item {
            animation: countUp 2s ease;
        }

        @keyframes countUp {
            from {
                opacity: 0;
                transform: scale(0.5);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .stat-item h2 {
            font-size: 3.5rem;
            font-weight: 800;
            color: white;
            margin-bottom: 0.5rem;
        }

        .stat-item p {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }

        /* CTA Section */
        .cta {
            padding: 6rem 2rem;
            text-align: center;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
        }

        .cta h2 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--color-6);
        }

        .cta p {
            font-size: 1.3rem;
            color: var(--text-light);
            margin-bottom: 2.5rem;
        }

        /* Footer */
        footer {
            background: var(--color-6);
            color: white;
            padding: 3rem 2rem;
            text-align: center;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .footer-links a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .footer-links a:hover {
            color: white;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .hero h1 { font-size: 2.5rem; }
            .hero p { font-size: 1.1rem; }
            .section-title { font-size: 2rem; }
            .hero-buttons { flex-direction: column; }
            nav { padding: 1rem 5%; }
            .logo { font-size: 1.4rem; }
        }

        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }

        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <div class="animated-bg">
        <div class="bg-circle circle-1"></div>
        <div class="bg-circle circle-2"></div>
        <div class="bg-circle circle-3"></div>
    </div>

    <nav>
        <div class="nav-content">
            <div class="logo">MentorBridge</div>
            <div class="nav-buttons">
                <a href="login.php" class="btn btn-secondary">Login</a>
                <a href="register.php" class="btn btn-primary">Get Started</a>
            </div>
        </div>
    </nav>

    <section class="hero">
        <div class="hero-content">
            <h1>Connect. Learn. Grow.</h1>
            <p>Bridge the gap between students and mentors. Find your perfect guide to success in any field.</p>
            <div class="hero-buttons">
                <a href="register.php?role=mentee" class="btn btn-primary">Find a Mentor</a>
                <a href="register.php?role=mentor" class="btn btn-secondary">Become a Mentor</a>
            </div>
        </div>
    </section>

    <section class="features">
        <div class="features-container">
            <h2 class="section-title">Why Choose MentorBridge?</h2>
            <p class="section-subtitle">Everything you need to unlock your potential</p>
            
            <div class="features-grid">
                <div class="feature-card fade-in">
                    <div class="feature-icon">
                        <svg width="35" height="35" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </div>
                    <h3>Expert Mentors</h3>
                    <p>Connect with verified professionals and educators who have real-world experience in their fields.</p>
                </div>

                <div class="feature-card fade-in">
                    <div class="feature-icon">
                        <svg width="35" height="35" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                    </div>
                    <h3>Flexible Scheduling</h3>
                    <p>Book sessions at your convenience with our easy-to-use scheduling system.</p>
                </div>

                <div class="feature-card fade-in">
                    <div class="feature-icon">
                        <svg width="35" height="35" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                        </svg>
                    </div>
                    <h3>Track Progress</h3>
                    <p>Monitor your learning journey with detailed analytics and session feedback.</p>
                </div>

                <div class="feature-card fade-in">
                    <div class="feature-icon">
                        <svg width="35" height="35" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                        </svg>
                    </div>
                    <h3>Secure Platform</h3>
                    <p>Your data and payments are protected with industry-standard security measures.</p>
                </div>

                <div class="feature-card fade-in">
                    <div class="feature-icon">
                        <svg width="35" height="35" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                        </svg>
                    </div>
                    <h3>Quality Assurance</h3>
                    <p>All mentors are vetted and reviewed to ensure the highest quality of guidance.</p>
                </div>

                <div class="feature-card fade-in">
                    <div class="feature-icon">
                        <svg width="35" height="35" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                    </div>
                    <h3>Direct Communication</h3>
                    <p>Engage in meaningful one-on-one sessions with your mentor.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="stats">
        <div class="stats-container">
            <div class="stats-grid">
                <div class="stat-item">
                    <h2>10K+</h2>
                    <p>Active Students</p>
                </div>
                <div class="stat-item">
                    <h2>500+</h2>
                    <p>Expert Mentors</p>
                </div>
                <div class="stat-item">
                    <h2>50K+</h2>
                    <p>Sessions Completed</p>
                </div>
                <div class="stat-item">
                    <h2>4.9/5</h2>
                    <p>Average Rating</p>
                </div>
            </div>
        </div>
    </section>

    <section class="cta fade-in">
        <h2>Ready to Start Your Journey?</h2>
        <p>Join thousands of students and mentors already growing together</p>
        <div class="hero-buttons">
            <a href="register.php" class="btn btn-primary">Join Now</a>
        </div>
    </section>

    <footer>
        <div class="footer-content">
            <div class="footer-links">
                <a href="#">About Us</a>
                <a href="#">How It Works</a>
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Service</a>
                <a href="#">Contact</a>
            </div>
            <p>&copy; 2025 MentorBridge. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Intersection Observer for fade-in animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, observerOptions);

        document.querySelectorAll('.fade-in').forEach(el => {
            observer.observe(el);
        });

        // Smooth scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add scroll effect to nav
        let lastScroll = 0;
        window.addEventListener('scroll', () => {
            const nav = document.querySelector('nav');
            const currentScroll = window.pageYOffset;
            
            if (currentScroll > 100) {
                nav.style.boxShadow = '0 4px 30px rgba(57, 88, 134, 0.15)';
            } else {
                nav.style.boxShadow = '0 2px 20px rgba(57, 88, 134, 0.1)';
            }
            
            lastScroll = currentScroll;
        });
    </script>
</body>
</html>