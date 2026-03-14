<?php
/**
 * index.php — Entry point
 * Redirects authenticated users to dashboard, others to login
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    redirect(BASE_URL . 'dashboard.php');
} else {
    redirect(BASE_URL . 'login.php');
}
