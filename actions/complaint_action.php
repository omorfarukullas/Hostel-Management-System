<?php
/**
 * actions/complaint_action.php — Complaint Handler
 * Hostel Management System
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . 'complaints.php');
}

$action = $_POST['action'] ?? '';

// ══════════════════════════════════════════════
// ADD COMPLAINT
// ══════════════════════════════════════════════
if ($action === 'add') {
    $student_id  = (int)($_POST['student_id'] ?? 0);
    $title       = sanitize($conn, $_POST['title']       ?? '');
    $description = sanitize($conn, $_POST['description'] ?? '');
    $category    = sanitize($conn, $_POST['category']    ?? 'other');
    $priority    = sanitize($conn, $_POST['priority']    ?? 'low');

    if ($student_id <= 0 || empty($title) || empty($description)) {
        flashMessage('error', 'Student, title, and description are required.');
        redirect(BASE_URL . 'complaints.php');
    }

    $validCategories = ['maintenance','food','security','cleanliness','other'];
    $validPriorities = ['low','medium','high'];
    if (!in_array($category, $validCategories)) $category = 'other';
    if (!in_array($priority, $validPriorities)) $priority = 'low';

    $stmt = $conn->prepare("
        INSERT INTO complaints (student_id, title, description, category, priority, status)
        VALUES (?, ?, ?, ?, ?, 'open')
    ");
    $stmt->bind_param('issss', $student_id, $title, $description, $category, $priority);

    if ($stmt->execute()) {
        flashMessage('success', 'Complaint submitted successfully.');
    } else {
        flashMessage('error', 'Failed to submit complaint: ' . $conn->error);
    }
    $stmt->close();
    redirect(BASE_URL . 'complaints.php');
}

// ══════════════════════════════════════════════
// RESOLVE COMPLAINT
// ══════════════════════════════════════════════
if ($action === 'resolve') {
    $complaint_id = (int)($_POST['complaint_id'] ?? 0);

    if ($complaint_id <= 0) {
        flashMessage('error', 'Invalid complaint.');
        redirect(BASE_URL . 'complaints.php');
    }

    $stmt = $conn->prepare("
        UPDATE complaints
        SET status = 'resolved', resolved_at = NOW()
        WHERE complaint_id = ?
    ");
    $stmt->bind_param('i', $complaint_id);

    if ($stmt->execute()) {
        flashMessage('success', 'Complaint marked as resolved.');
    } else {
        flashMessage('error', 'Failed to resolve complaint.');
    }
    $stmt->close();
    redirect(BASE_URL . 'complaints.php');
}

// ══════════════════════════════════════════════
// DELETE COMPLAINT
// ══════════════════════════════════════════════
if ($action === 'delete') {
    $complaint_id = (int)($_POST['complaint_id'] ?? 0);

    if ($complaint_id <= 0) {
        flashMessage('error', 'Invalid complaint ID.');
        redirect(BASE_URL . 'complaints.php');
    }

    $stmt = $conn->prepare("DELETE FROM complaints WHERE complaint_id = ?");
    $stmt->bind_param('i', $complaint_id);

    if ($stmt->execute()) {
        flashMessage('success', 'Complaint deleted successfully.');
    } else {
        flashMessage('error', 'Failed to delete complaint.');
    }
    $stmt->close();
    redirect(BASE_URL . 'complaints.php');
}

flashMessage('error', 'Unknown action.');
redirect(BASE_URL . 'complaints.php');
