<?php
/**
 * actions/notice_action.php — Notice Handler
 * Hostel Management System
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $redirect_to = sanitize($conn, $_POST['redirect_to'] ?? 'notices.php');
    redirect(BASE_URL . $redirect_to);
}

$action = $_POST['action'] ?? '';
$redirect_to = sanitize($conn, $_POST['redirect_to'] ?? 'notices.php');

// ══════════════════════════════════════════════
// ADD NOTICE (admin/supervisor only)
// ══════════════════════════════════════════════
if ($action === 'add') {
    // Only admin or supervisor can post
    if (!isAdmin() && !isSupervisor()) {
        flashMessage('error', 'You do not have permission to post notices.');
        redirect(BASE_URL . $redirect_to);
    }

    $title       = sanitize($conn, $_POST['title']       ?? '');
    $content     = sanitize($conn, $_POST['content']     ?? '');
    $target_role = sanitize($conn, $_POST['target_role'] ?? 'all');
    $expires_at  = sanitize($conn, $_POST['expires_at']  ?? '');
    $is_pinned   = isset($_POST['is_pinned']) ? 1 : 0;
    $posted_by   = (int)$_SESSION['user_id'];

    if (empty($title) || empty($content)) {
        flashMessage('error', 'Title and content are required.');
        redirect(BASE_URL . $redirect_to);
    }

    $validRoles = ['all','student','supervisor'];
    if (!in_array($target_role, $validRoles)) $target_role = 'all';

    $expires_at_val = !empty($expires_at) ? $expires_at : null;

    $stmt = $conn->prepare("
        INSERT INTO notices (title, content, posted_by, target_role, is_pinned, expires_at)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('ssisis', $title, $content, $posted_by, $target_role, $is_pinned, $expires_at_val);

    if ($stmt->execute()) {
        flashMessage('success', 'Notice posted successfully.');
    } else {
        flashMessage('error', 'Failed to post notice: ' . $conn->error);
    }
    $stmt->close();
    redirect(BASE_URL . $redirect_to);
}

// ══════════════════════════════════════════════
// DELETE NOTICE
// ══════════════════════════════════════════════
if ($action === 'delete') {
    if (!isAdmin() && !isSupervisor()) {
        flashMessage('error', 'Permission denied.');
        redirect(BASE_URL . $redirect_to);
    }

    $notice_id = (int)($_POST['notice_id'] ?? 0);

    if ($notice_id <= 0) {
        flashMessage('error', 'Invalid notice.');
        redirect(BASE_URL . $redirect_to);
    }

    $stmt = $conn->prepare("DELETE FROM notices WHERE notice_id = ?");
    $stmt->bind_param('i', $notice_id);

    if ($stmt->execute()) {
        flashMessage('success', 'Notice deleted.');
    } else {
        flashMessage('error', 'Failed to delete notice.');
    }
    $stmt->close();
    redirect(BASE_URL . $redirect_to);
}

flashMessage('error', 'Unknown action.');
redirect(BASE_URL . $redirect_to);
