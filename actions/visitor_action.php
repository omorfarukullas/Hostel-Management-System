<?php
/**
 * actions/visitor_action.php — Visitor Log Handler
 * Hostel Management System
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . 'visitors.php');
}

$action = $_POST['action'] ?? '';

// ══════════════════════════════════════════════
// ADD VISITOR
// ══════════════════════════════════════════════
if ($action === 'add') {
    $student_id    = (int)($_POST['student_id']    ?? 0);
    $visitor_name  = sanitize($conn, $_POST['visitor_name']  ?? '');
    $visitor_phone = sanitize($conn, $_POST['visitor_phone'] ?? '');
    $relation      = sanitize($conn, $_POST['relation']      ?? '');
    $purpose       = sanitize($conn, $_POST['purpose']       ?? '');

    if ($student_id <= 0 || empty($visitor_name)) {
        flashMessage('error', 'Student and visitor name are required.');
        redirect(BASE_URL . 'visitors.php');
    }

    $stmt = $conn->prepare("
        INSERT INTO visitors (student_id, visitor_name, visitor_phone, relation, purpose, check_in, status)
        VALUES (?, ?, ?, ?, ?, NOW(), 'pending')
    ");
    $stmt->bind_param('issss', $student_id, $visitor_name, $visitor_phone, $relation, $purpose);

    if ($stmt->execute()) {
        flashMessage('success', "Visitor <strong>$visitor_name</strong> logged. Awaiting approval.");
    } else {
        flashMessage('error', 'Failed to log visitor: ' . $conn->error);
    }
    $stmt->close();
    redirect(BASE_URL . 'visitors.php');
}

// ══════════════════════════════════════════════
// APPROVE VISITOR
// ══════════════════════════════════════════════
if ($action === 'approve') {
    $visitor_id  = (int)($_POST['visitor_id'] ?? 0);
    $approved_by = (int)$_SESSION['user_id'];

    if ($visitor_id <= 0) {
        flashMessage('error', 'Invalid visitor record.');
        redirect(BASE_URL . 'visitors.php');
    }

    $stmt = $conn->prepare("
        UPDATE visitors
        SET status = 'approved', approved_by = ?
        WHERE visitor_id = ?
    ");
    $stmt->bind_param('ii', $approved_by, $visitor_id);

    if ($stmt->execute()) {
        flashMessage('success', 'Visitor approved successfully.');
    } else {
        flashMessage('error', 'Failed to approve visitor.');
    }
    $stmt->close();
    redirect(BASE_URL . 'visitors.php');
}

// ══════════════════════════════════════════════
// DENY VISITOR
// ══════════════════════════════════════════════
if ($action === 'deny') {
    $visitor_id  = (int)($_POST['visitor_id'] ?? 0);
    $approved_by = (int)$_SESSION['user_id'];

    if ($visitor_id <= 0) {
        flashMessage('error', 'Invalid visitor record.');
        redirect(BASE_URL . 'visitors.php');
    }

    $stmt = $conn->prepare("
        UPDATE visitors SET status = 'denied', approved_by = ?
        WHERE visitor_id = ?
    ");
    $stmt->bind_param('ii', $approved_by, $visitor_id);

    if ($stmt->execute()) {
        flashMessage('warning', 'Visitor entry denied.');
    } else {
        flashMessage('error', 'Failed to update visitor status.');
    }
    $stmt->close();
    redirect(BASE_URL . 'visitors.php');
}

// ══════════════════════════════════════════════
// CHECK OUT VISITOR
// ══════════════════════════════════════════════
if ($action === 'checkout') {
    $visitor_id = (int)($_POST['visitor_id'] ?? 0);

    if ($visitor_id <= 0) {
        flashMessage('error', 'Invalid visitor record.');
        redirect(BASE_URL . 'visitors.php');
    }

    $stmt = $conn->prepare("
        UPDATE visitors
        SET check_out = NOW(), status = 'checked_out'
        WHERE visitor_id = ?
    ");
    $stmt->bind_param('i', $visitor_id);

    if ($stmt->execute()) {
        flashMessage('success', 'Visitor checked out successfully.');
    } else {
        flashMessage('error', 'Failed to check out visitor.');
    }
    $stmt->close();
    redirect(BASE_URL . 'visitors.php');
}

flashMessage('error', 'Unknown action.');
redirect(BASE_URL . 'visitors.php');
