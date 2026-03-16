<?php
/**
 * public/landing.php — Guest Landing Page
 * Hostel Management System
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    roleDashboard($_SESSION['role'] ?? '');
}

// Fetch some quick stats for the landing page
$stats = [
    'rooms' => 0,
    'students' => 0,
    'supervisors' => 0
];
$r = $conn->query("SELECT COUNT(*) as c FROM rooms");
if ($r) $stats['rooms'] = $r->fetch_assoc()['c'];

$r = $conn->query("SELECT COUNT(*) as c FROM students WHERE status = 'active'");
if ($r) $stats['students'] = $r->fetch_assoc()['c'];

$r = $conn->query("SELECT COUNT(*) as c FROM supervisors");
if ($r) $stats['supervisors'] = $r->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - Hostel Management System</title>
    <!-- Use the system's style.css for basic resets and fonts, but we'll add custom styles for the landing page here -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #f8fafc;
            --text-dark: #0f172a;
            --text-light: #64748b;
        }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            margin: 0;
            padding: 0;
            background: #fff;
            color: var(--text-dark);
        }
        /* Navigation */
        .landing-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 5%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            box-sizing: border-box;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .nav-logo {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .nav-links {
            display: flex;
            gap: 30px;
            align-items: center;
        }
        .nav-links a {
            text-decoration: none;
            color: var(--text-dark);
            font-weight: 500;
            transition: color 0.2s;
        }
        .nav-links a:hover {
            color: var(--primary);
        }
        .btn-nav-login {
            background: var(--primary);
            color: white !important;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-nav-login:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        /* Hero Section */
        .hero {
            padding: 160px 5% 100px;
            background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 50px;
            min-height: 80vh;
            box-sizing: border-box;
        }
        .hero-content {
            flex: 1;
            max-width: 600px;
        }
        .hero-title {
            font-size: 4rem;
            line-height: 1.1;
            font-weight: 800;
            margin-bottom: 24px;
            color: #0f172a;
        }
        .hero-title span {
            color: var(--primary);
        }
        .hero-subtitle {
            font-size: 1.25rem;
            line-height: 1.6;
            color: var(--text-light);
            margin-bottom: 40px;
        }
        .hero-buttons {
            display: flex;
            gap: 20px;
        }
        .btn-hero {
            padding: 16px 32px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.3s;
        }
        .btn-primary-hero {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 14px 0 rgba(37, 99, 235, 0.39);
        }
        .btn-primary-hero:hover {
            background: var(--primary-dark);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.23);
            transform: translateY(-2px);
        }
        .btn-secondary-hero {
            background: white;
            color: var(--text-dark);
            border: 1px solid #e2e8f0;
        }
        .btn-secondary-hero:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
        }
        .hero-image {
            flex: 1;
            display: flex;
            justify-content: center;
            position: relative;
        }
        .hero-image img {
            max-width: 100%;
            height: auto;
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            z-index: 2;
            position: relative;
        }
        .blob {
            position: absolute;
            background: #dbeafe;
            border-radius: 50%;
            filter: blur(50px);
            z-index: 1;
        }

        /* Stats Section */
        .stats {
            padding: 60px 5%;
            background: white;
            display: flex;
            justify-content: space-around;
            border-bottom: 1px solid #e2e8f0;
        }
        .stat-item {
            text-align: center;
        }
        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 5px;
        }
        .stat-label {
            color: var(--text-light);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.9rem;
        }

        /* Features Section */
        .features {
            padding: 100px 5%;
            background: #f8fafc;
        }
        .section-header {
            text-align: center;
            max-width: 600px;
            margin: 0 auto 60px auto;
        }
        .section-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 15px;
        }
        .section-subtitle {
            font-size: 1.1rem;
            color: var(--text-light);
        }
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .feature-card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
            transition: transform 0.3s;
            border: 1px solid #f1f5f9;
        }
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        }
        .feature-icon {
            font-size: 3rem;
            margin-bottom: 20px;
            display: inline-block;
            background: #eff6ff;
            width: 80px;
            height: 80px;
            line-height: 80px;
            text-align: center;
            border-radius: 16px;
        }
        .feature-card h3 {
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        .feature-card p {
            color: var(--text-light);
            line-height: 1.6;
        }

        /* Footer */
        .footer {
            background: #0f172a;
            color: #94a3b8;
            padding: 60px 5% 30px;
        }
        .footer-content {
            display: flex;
            justify-content: space-between;
            max-width: 1200px;
            margin: 0 auto 40px;
            flex-wrap: wrap;
            gap: 40px;
        }
        .footer-brand h2 {
            color: white;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .footer-brand p {
            max-width: 300px;
            line-height: 1.6;
        }
        .footer-links h4 {
            color: white;
            margin-bottom: 20px;
            font-size: 1.1rem;
        }
        .footer-links ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .footer-links li {
            margin-bottom: 10px;
        }
        .footer-links a {
            color: #94a3b8;
            text-decoration: none;
            transition: color 0.2s;
        }
        .footer-links a:hover {
            color: white;
        }
        .footer-bottom {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid #1e293b;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .hero {
                flex-direction: column;
                text-align: center;
                padding-top: 120px;
            }
            .hero-title { font-size: 2.5rem; }
            .hero-buttons { justify-content: center; }
            .nav-links { display: none; } /* Could add a hamburger menu here */
            .stats { flex-direction: column; gap: 30px; }
        }
    </style>
</head>
<body>

    <!-- Navigation -->
    <nav class="landing-nav">
        <a href="<?= BASE_URL ?>" class="nav-logo">
            <span style="font-size: 1.8rem;">🏢</span> HMS
        </a>
        <div class="nav-links">
            <a href="#features">Features</a>
            <a href="#about">About</a>
            <a href="#contact">Contact</a>
            <a href="<?= BASE_URL ?>register.php">Apply Now</a>
            <a href="<?= BASE_URL ?>login.php" class="btn-nav-login">Sign In</a>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1 class="hero-title">Modern living for <span>modern students.</span></h1>
            <p class="hero-subtitle">Experience a seamless, comfortable, and secure hostel environment. Manage your room, submit complaints, and stay updated—all from one platform.</p>
            <div class="hero-buttons">
                <a href="<?= BASE_URL ?>register.php" class="btn-hero btn-primary-hero">Apply for a Room</a>
                <a href="<?= BASE_URL ?>login.php" class="btn-hero btn-secondary-hero">Student Portal</a>
            </div>
        </div>
        <div class="hero-image">
            <div class="blob" style="width: 400px; height: 400px; top: -50px; right: -50px;"></div>
            <div class="blob" style="width: 300px; height: 300px; bottom: -50px; left: -50px; background:#e0e7ff;"></div>
            <!-- Using a high-quality placeholder image for the hero -->
            <img src="https://images.unsplash.com/photo-1555854877-bab0e564b8d5?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Modern Hostel Room" style="object-fit:cover; height:500px; width:100%;">
        </div>
    </section>

    <!-- Quick Stats -->
    <section class="stats">
        <div class="stat-item">
            <div class="stat-number"><?= $stats['students'] ?>+</div>
            <div class="stat-label">Active Residents</div>
        </div>
        <div class="stat-item">
            <div class="stat-number"><?= $stats['rooms'] ?></div>
            <div class="stat-label">Total Rooms</div>
        </div>
        <div class="stat-item">
            <div class="stat-number">24/7</div>
            <div class="stat-label">Support</div>
        </div>
    </section>

    <!-- Features -->
    <section id="features" class="features">
        <div class="section-header">
            <h2 class="section-title">Everything you need</h2>
            <p class="section-subtitle">We provide a comprehensive suite of tools to make your hostel life as smooth as possible.</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">🛏️</div>
                <h3>Room Management</h3>
                <p>Easily view your room details, roommates, and apply for room changes directly through your student dashboard.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🛠️</div>
                <h3>Quick Maintenance</h3>
                <p>Report issues by snapping a photo and submitting a complaint. Track its resolution status in real-time.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📢</div>
                <h3>Instant Updates</h3>
                <p>Never miss an important announcement. Get notified instantly about water shortages, events, and deadlines.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">💬</div>
                <h3>Direct Communication</h3>
                <p>Need help? Chat directly with your block supervisor securely within the platform.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">💳</div>
                <h3>Fee Tracking</h3>
                <p>Keep an eye on your monthly fees, download receipts, and view payment history.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🔒</div>
                <h3>Secure Environment</h3>
                <p>Role-based access ensures your data is safe, and visitor tracking keeps the premises secure.</p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-brand">
                <h2>🏢 HMS</h2>
                <p>Redefining hostel management for educational institutions. Making accommodation living seamless and enjoyable.</p>
            </div>
            <div class="footer-links" id="about">
                <h4>Company</h4>
                <ul>
                    <li><a href="#">About Us</a></li>
                    <li><a href="#">Careers</a></li>
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Terms of Service</a></li>
                </ul>
            </div>
            <div class="footer-links" id="contact">
                <h4>Contact Support</h4>
                <ul>
                    <li><a href="#">Help Center</a></li>
                    <li><a href="#">Email: support@hms.edu</a></li>
                    <li><a href="#">Phone: (123) 456-7890</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            &copy; <?= date('Y') ?> Advanced Hostel Management System. All rights reserved.
        </div>
    </footer>

</body>
</html>
