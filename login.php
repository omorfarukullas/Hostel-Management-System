<?php
/**
 * login.php — Authentication Page
 * Hostel Management System
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// Already logged in? Route to role dashboard
if (isLoggedIn()) {
    redirect(BASE_URL . roleDashboard($_SESSION['role'] ?? ''));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Prepared statement — safe from SQL injection
        $stmt = $conn->prepare(
            "SELECT user_id, name, email, password, role, status FROM users WHERE email = ? LIMIT 1"
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $error = 'No account found with that email address.';
        } elseif ($user['status'] !== 'active') {
            $error = 'Your account is inactive. Please contact the administrator.';
        } elseif (!password_verify($password, $user['password'])) {
            $error = 'Incorrect password. Please try again.';
        } else {
            // ✅ Valid login — set session
            session_regenerate_id(true); // prevent session fixation
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = $user['role'];

            flashMessage('success', 'Welcome back, ' . $user['name'] . '!');
            redirect(BASE_URL . roleDashboard($user['role']));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — HostelMS</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>css/login.css">
</head>
<body>

<div class="login-wrapper">
    <div class="login-card">

        <!-- Logo -->
        <div class="login-logo">
            <div class="login-logo-icon">🏨</div>
            <h1 class="login-title">HostelMS</h1>
            <p class="login-subtitle">Hostel Management System</p>
        </div>

        <!-- Error Alert -->
        <?php if ($error): ?>
            <div class="login-alert login-alert-error">
                <span>⚠️</span> <?= e($error) ?>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form id="loginForm" method="POST" action="" novalidate>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control"
                    placeholder="Enter your email"
                    value="<?= e($_POST['email'] ?? '') ?>"
                    autocomplete="email"
                    required
                >
                <span class="field-error" id="emailError"></span>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-wrapper">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >
                    <button type="button" class="toggle-password" id="togglePassword" aria-label="Toggle password visibility">
                        <span id="toggleIcon">👁️</span>
                    </button>
                </div>
                <span class="field-error" id="passwordError"></span>
            </div>

            <button type="submit" class="btn-login">
                Sign In to HostelMS
            </button>

        </form>

        <!-- Hint -->
        <div class="login-hint">
            <p>🔑 Default: <strong>admin@hostel.com</strong> / <strong>Admin@123</strong></p>
        </div>

    </div>
</div>

<script src="<?= BASE_URL ?>js/validation.js"></script>
</body>
</html>
