<?php
/**
 * Database Configuration
 * Hostel Management System
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hostel_db');
define('DB_CHARSET', 'utf8mb4');

// Base URL for redirects (adjust if hosted in a subfolder)
define('BASE_URL', '/Hostel-Management-System/');

// Create MySQLi connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    header('Content-Type: application/json');
    die(json_encode([
        'success' => false,
        'error'   => 'Database connection failed: ' . $conn->connect_error
    ]));
}

// Set charset
if (!$conn->set_charset(DB_CHARSET)) {
    http_response_code(500);
    header('Content-Type: application/json');
    die(json_encode([
        'success' => false,
        'error'   => 'Failed to set charset: ' . $conn->error
    ]));
}
