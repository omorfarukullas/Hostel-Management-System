<?php
/**
 * actions/register_action.php — Handle Student Registration
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . 'register.php');
}

$name             = sanitize($conn, $_POST['name'] ?? '');
$email            = sanitize($conn, $_POST['email'] ?? '');
$phone            = sanitize($conn, $_POST['phone'] ?? '');
$dob              = sanitize($conn, $_POST['dob'] ?? '');
$gender           = sanitize($conn, $_POST['gender'] ?? '');
$password         = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$guardian_name    = sanitize($conn, $_POST['guardian_name'] ?? '');
$guardian_phone   = sanitize($conn, $_POST['guardian_phone'] ?? '');
$address          = sanitize($conn, $_POST['address'] ?? '');
$room_preference  = sanitize($conn, $_POST['room_preference'] ?? '');

// Validation
if (empty($name) || empty($email) || empty($password) || empty($phone)) {
    flashMessage('error', 'Please fill in all required fields.');
    redirect(BASE_URL . 'register.php');
}

if ($password !== $confirm_password) {
    flashMessage('error', 'Passwords do not match.');
    redirect(BASE_URL . 'register.php');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flashMessage('error', 'Invalid email format.');
    redirect(BASE_URL . 'register.php');
}

// Check if email already exists in users table or pending admission requests
$stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    flashMessage('error', 'This email is already registered.');
    redirect(BASE_URL . 'register.php');
}

$stmt = $conn->prepare("SELECT request_id FROM admission_requests WHERE email = ? AND status = 'pending'");
$stmt->bind_param('s', $email);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    flashMessage('error', 'An admission request with this email is already pending.');
    redirect(BASE_URL . 'register.php');
}

// Ensure password is long enough
if (strlen($password) < 6) {
    session_start();
    flashMessage('error', 'Password must be at least 6 characters.');
    redirect(BASE_URL . 'register.php');
}

$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Insert into admission_requests
$stmt = $conn->prepare("
    INSERT INTO admission_requests 
    (name, email, password_hash, phone, dob, gender, guardian_name, guardian_phone, address, room_preference, status) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
");
$stmt->bind_param(
    'ssssssssss',
    $name, $email, $password_hash, $phone, $dob, $gender, 
    $guardian_name, $guardian_phone, $address, $room_preference
);

if ($stmt->execute()) {
    flashMessage('success', 'Your application has been submitted successfully! Check your email or log in later for status updates.');
    redirect(BASE_URL . 'login.php');
} else {
    flashMessage('error', 'Database error: ' . $conn->error);
    redirect(BASE_URL . 'register.php');
}
