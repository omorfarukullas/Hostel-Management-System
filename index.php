<?php
/**
 * index.php — Entry point
 * Logged-in users → role dashboard | Guests → public landing page
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    redirect(BASE_URL . roleDashboard($_SESSION['role'] ?? ''));
} else {
    redirect(BASE_URL . 'public/landing.php');
}
