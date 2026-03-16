<?php
/**
 * includes/header.php — Shared layout header
 * Included at the top of every protected page.
 * Requires $pageTitle to be set in the calling page.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

requireLogin();

$flash       = getFlash();
$currentRole = $_SESSION['role'] ?? 'student';
$currentName = $_SESSION['name'] ?? 'User';
$pageTitle   = $pageTitle ?? 'Dashboard';

// ── Role-aware nav links: [label, relative path from BASE_URL, emoji] ──
$navLinks = match($currentRole) {
    'admin' => [
        ['Dashboard',         'dashboard.php',                '🏠'],
        ['Supervisors',       'admin/supervisors.php',        '👔'],
        ['Students',          'students.php',                 '👨‍🎓'],
        ['Admission Requests','admin/admission_requests.php', '📝'],
        ['Rooms',             'admin/rooms.php',              '🛏️'],
        ['Fees',              'fees.php',                     '💰'],
        ['Tasks',             'admin/tasks.php',              '✅'],
        ['Complaints',        'complaints.php',               '📋'],
        ['Repair Costs',      'admin/repair_costs.php',       '🔧'],
        ['Notices',           'notices.php',                  '📢'],
        ['Reports',           'admin/reports.php',            '📊'],
        ['Profile',           'profile.php',                  '👤'],
    ],
    'supervisor' => [
        ['Dashboard',         'supervisor/dashboard.php',     '🏠'],
        ['My Students',       'supervisor/students.php',      '👨‍🎓'],
        ['Room Changes',      'supervisor/room_changes.php',  '🔄'],
        ['Complaints',        'supervisor/complaints.php',    '📋'],
        ['My Tasks',          'supervisor/tasks.php',         '✅'],
        ['Notices',           'supervisor/notices.php',       '📢'],
        ['Chat',              'supervisor/chat.php',          '💬'],
        ['Profile',           'profile.php',                  '👤'],
    ],
    'student' => [
        ['Dashboard',         'student/dashboard.php',        '🏠'],
        ['My Complaint',      'student/complaints.php',       '📋'],
        ['Room Change',       'student/room_change.php',      '🔄'],
        ['Notices',           'student/notices.php',          '📢'],
        ['Chat',              'student/chat.php',             '💬'],
        ['Profile',           'profile.php',                  '👤'],
    ],
    default => [],
};

// Active page detection — match by the end of the URL path
$currentUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — HostelMS</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/dashboard.css">
</head>
<body>

<!-- ===== SIDEBAR ===== -->
<aside class="sidebar" id="sidebar">

    <!-- Logo -->
    <div class="sidebar-logo">
        <span class="sidebar-logo-icon">🏨</span>
        <span class="sidebar-logo-text">HostelMS</span>
    </div>

    <!-- Role badge -->
    <div class="sidebar-role-tag">
        <?php
        $roleLabel = match($currentRole) {
            'admin'      => '⚙️ Admin Panel',
            'supervisor' => '👔 Supervisor Panel',
            'student'    => '🎓 Student Portal',
            default      => ucfirst($currentRole),
        };
        echo e($roleLabel);
        ?>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <?php foreach ($navLinks as [$label, $file, $icon]):
            $isActive = str_ends_with($currentUri, $file) ||
                        str_ends_with($currentUri, '/' . basename($file));
        ?>
            <a href="<?= BASE_URL . $file ?>"
               class="nav-item<?= $isActive ? ' active' : '' ?>"
               title="<?= e($label) ?>">
                <span class="nav-icon"><?= $icon ?></span>
                <span class="nav-label"><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- User Info + Logout -->
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-user-avatar"><?= strtoupper(substr($currentName, 0, 1)) ?></div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?= e($currentName) ?></div>
                <div class="sidebar-user-role"><?= e(ucfirst($currentRole)) ?></div>
            </div>
        </div>
        <a href="<?= BASE_URL ?>logout.php" class="nav-item nav-logout" title="Logout">
            <span class="nav-icon">🚪</span>
            <span class="nav-label">Logout</span>
        </a>
    </div>

    <!-- Collapse Toggle -->
    <button class="sidebar-collapse-btn" id="sidebarCollapseBtn" aria-label="Collapse sidebar">
        <span>◀</span>
    </button>
</aside>

<!-- ===== TOPBAR ===== -->
<div class="main-wrapper" id="mainWrapper">
    <header class="topbar">
        <div class="topbar-left">
            <button class="topbar-toggle" id="topbarToggle" aria-label="Toggle sidebar">
                <span class="hamburger"></span>
                <span class="hamburger"></span>
                <span class="hamburger"></span>
            </button>
            <h2 class="topbar-title"><?= e($pageTitle) ?></h2>
        </div>
        <div class="topbar-right">
            <span class="role-badge role-<?= e($currentRole) ?>"><?= e(ucfirst($currentRole)) ?></span>
        </div>
    </header>

    <!-- ===== FLASH MESSAGE ===== -->
    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>" id="flashAlert">
            <?= e($flash['message']) ?>
            <button class="alert-close" onclick="this.parentElement.remove()">✕</button>
        </div>
    <?php endif; ?>

    <!-- ===== MAIN CONTENT START ===== -->
    <main class="main-content">
