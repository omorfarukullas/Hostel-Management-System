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
 */
function sanitize(mysqli $conn, string $data): string {
    return $conn->real_escape_string(trim($data));
}

/**
 * Redirect to a URL and stop execution
 */
function redirect(string $url): void {
    header("Location: $url");
    exit();
}

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if user has a specific role
 */
function hasRole(string $role): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function isAdmin(): bool      { return hasRole('admin'); }
function isSupervisor(): bool { return hasRole('supervisor'); }
function isStudent(): bool    { return hasRole('student'); }

/**
 * Require login; redirect to login.php if not
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        redirect(BASE_URL . 'login.php');
    }
}

/**
 * Require a specific role; redirect to appropriate dashboard if not
 */
function requireRole(string ...$roles): void {
    requireLogin();
    $currentRole = $_SESSION['role'] ?? '';
    if (!in_array($currentRole, $roles, true)) {
        redirect(BASE_URL . roleDashboard($currentRole));
    }
}

/**
 * Require admin only
 */
function requireAdmin(): void {
    requireRole('admin');
}

/**
 * Require supervisor only
 */
function requireSupervisor(): void {
    requireRole('supervisor');
}

/**
 * Require student only
 */
function requireStudent(): void {
    requireRole('student');
}

/**
 * Return the default dashboard file for a given role
 */
function roleDashboard(string $role): string {
    return match($role) {
        'admin'      => 'dashboard.php',
        'supervisor' => 'supervisor/dashboard.php',
        'student'    => 'student/dashboard.php',
        default      => 'login.php',
    };
}

/**
 * Store a flash message in session
 */
function flashMessage(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message,
    ];
}

/**
 * Retrieve and clear the flash message from session
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
 */
function generateStudentCode(mysqli $conn): string {
    do {
        $code   = 'STU-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 6));
        $result = $conn->query("SELECT student_id FROM students WHERE student_code = '$code' LIMIT 1");
    } while ($result && $result->num_rows > 0);
    return $code;
}

/**
 * Format a date string as "15 Jan 2025" or return "N/A"
 */
function formatDate(?string $date): string {
    if (empty($date) || $date === '0000-00-00' || $date === null) return 'N/A';
    $ts = strtotime($date);
    return $ts !== false ? date('d M Y', $ts) : 'N/A';
}

/**
 * Format a number as currency with Taka symbol
 */
function formatCurrency($amount): string {
    return '৳ ' . number_format((float)$amount, 2);
}

/**
 * Get current page filename (for active nav link detection)
 */
function currentPage(): string {
    return basename($_SERVER['PHP_SELF']);
}

/**
 * Escape output for HTML context (XSS prevention)
 */
function e(?string $str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
