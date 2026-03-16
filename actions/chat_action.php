<?php
/**
 * actions/chat_action.php — Handle Chat Messages
 * Hostel Management System
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin(); // Students and Supervisors use this

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL);
}

$action = $_POST['action'] ?? '';

if ($action === 'send') {
    $student_id    = (int)($_POST['student_id'] ?? 0);
    $supervisor_id = (int)($_POST['supervisor_id'] ?? 0);
    $sender_role   = sanitize($conn, $_POST['sender_role'] ?? '');
    $message       = sanitize($conn, $_POST['message'] ?? '');
    $redirect_to   = sanitize($conn, $_POST['redirect_to'] ?? '');

    if (empty($message) || $student_id <= 0 || $supervisor_id <= 0 || !in_array($sender_role, ['student','supervisor'])) {
        flashMessage('error', 'Invalid message data.');
        redirect(BASE_URL . $redirect_to);
    }
    
    // Security check: Ensure the sender is actually who they claim to be
    $userRole = $_SESSION['role'];
    if ($sender_role !== $userRole) {
        // Technically admin shouldn't be here, but let's be strict
        flashMessage('error', 'Role mismatch.');
        redirect(BASE_URL . $redirect_to);
    }
    
    // Check sender ID matches session
    if ($userRole === 'student') {
        $stmt = $conn->prepare("SELECT student_id FROM students WHERE user_id = ?");
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $st = $stmt->get_result()->fetch_assoc();
        if (!$st || $st['student_id'] != $student_id) {
            flashMessage('error', 'Unauthorized sender.');
            redirect(BASE_URL . $redirect_to);
        }
    } elseif ($userRole === 'supervisor') {
        $stmt = $conn->prepare("SELECT supervisor_id FROM supervisors WHERE user_id = ?");
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $sp = $stmt->get_result()->fetch_assoc();
        if (!$sp || $sp['supervisor_id'] != $supervisor_id) {
            flashMessage('error', 'Unauthorized sender.');
            redirect(BASE_URL . $redirect_to);
        }
    }

    $stmt = $conn->prepare("
        INSERT INTO chat_messages (student_id, supervisor_id, sender_role, message, is_read)
        VALUES (?, ?, ?, ?, 0)
    ");
    $stmt->bind_param('iiss', $student_id, $supervisor_id, $sender_role, $message);
    $stmt->execute();
    
    redirect(BASE_URL . $redirect_to . '#bottom');
}

flashMessage('error', 'Unknown action.');
redirect(BASE_URL);
