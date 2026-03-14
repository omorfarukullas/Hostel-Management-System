<?php
/**
 * Helper Functions
 * Hostel Management System
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Sanitize input: trim and escape for MySQLi
 * @param mysqli $conn
 * @param string $data
 * @return string
 */
function sanitize(mysqli $conn, string $data): string {
    return $conn->real_escape_string(trim($data));
}

/**
 * Redirect to a URL and stop execution
 * @param string $url
 */
function redirect(string $url): void {
    header("Location: $url");
    exit();
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if user has a specific role
 * @param string $role
 * @return bool
 */
function hasRole(string $role): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Check if current user is admin
 * @return bool
 */
function isAdmin(): bool {
    return hasRole('admin');
}

/**
 * Check if current user is warden
 * @return bool
 */
function isWarden(): bool {
    return hasRole('warden');
}

/**
 * Require user to be logged in; redirect to login.php if not
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        redirect(BASE_URL . 'login.php');
    }
}

/**
 * Require user to be admin; redirect to dashboard.php if not
 */
function requireAdmin(): void {
    if (!isAdmin()) {
        redirect(BASE_URL . 'dashboard.php');
    }
}

/**
 * Store a flash message in session
 * @param string $type  e.g. 'success', 'error', 'warning', 'info'
 * @param string $message
 */
function flashMessage(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message,
    ];
}

/**
 * Retrieve and clear the flash message from session
 * @return array|null
 */
function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Generate a unique student code like STU-XXXXXX
 * @param mysqli $conn
 * @return string
 */
function generateStudentCode(mysqli $conn): string {
    do {
        $code = 'STU-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 6));
        $result = $conn->query("SELECT student_id FROM students WHERE student_code = '$code' LIMIT 1");
    } while ($result && $result->num_rows > 0);
    return $code;
}

/**
 * Format a date string as "15 Jan 2025" or return "N/A"
 * @param string|null $date
 * @return string
 */
function formatDate(?string $date): string {
    if (empty($date) || $date === '0000-00-00' || $date === null) {
        return 'N/A';
    }
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return 'N/A';
    }
    return date('d M Y', $timestamp);
}

/**
 * Format a number as currency with Taka symbol
 * @param float|string $amount
 * @return string
 */
function formatCurrency($amount): string {
    return '৳ ' . number_format((float)$amount, 2);
}

/**
 * Get current page filename (for active nav link detection)
 * @return string
 */
function currentPage(): string {
    return basename($_SERVER['PHP_SELF']);
}

/**
 * Escape output for HTML context (XSS prevention)
 * @param string|null $str
 * @return string
 */
function e(?string $str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
