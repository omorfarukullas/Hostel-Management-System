<?php
/**
 * actions/student_portal_action.php — Handle Student Interactions (Complaints, Room Changes)
 * Hostel Management System
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireStudent();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL);
}

$action    = $_POST['action'] ?? '';
$user_id   = $_SESSION['user_id'];

// Get student ID
$stmt = $conn->prepare("SELECT student_id FROM students WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stRes = $stmt->get_result()->fetch_assoc();
if (!$stRes) {
    flashMessage('error', 'Student profile not found.');
    redirect(BASE_URL);
}
$student_id = $stRes['student_id'];

// ══════════════════════════════════════════════
// ADD COMPLAINT (WITH PHOTO)
// ══════════════════════════════════════════════
if ($action === 'add_complaint') {
    $title       = sanitize($conn, $_POST['title'] ?? '');
    $description = sanitize($conn, $_POST['description'] ?? '');
    $category    = sanitize($conn, $_POST['category'] ?? 'other');
    $priority    = sanitize($conn, $_POST['priority'] ?? 'medium');
    $assigned_to = (int)($_POST['assigned_to'] ?? 0); // null if 0
    
    if (empty($title) || empty($description)) {
        flashMessage('error', 'Title and description are required.');
        redirect(BASE_URL . 'student/complaints.php');
    }
    
    $validCategories = ['maintenance','food','security','cleanliness','plumbing','electricity','noise','other'];
    $validPriorities = ['low','medium','high'];
    if (!in_array($category, $validCategories)) $category = 'other';
    if (!in_array($priority, $validPriorities)) $priority = 'low';
    
    // Handle Photo Upload
    $photoPath = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        
        $fileObj = $_FILES['photo'];
        
        if (!in_array($fileObj['type'], $allowedTypes)) {
            flashMessage('error', 'Invalid photo format. Only JPG and PNG allowed.');
            redirect(BASE_URL . 'student/complaints.php');
        }
        if ($fileObj['size'] > $maxSize) {
            flashMessage('error', 'Photo size exceeds 2MB limit.');
            redirect(BASE_URL . 'student/complaints.php');
        }
        
        $ext = strtolower(pathinfo($fileObj['name'], PATHINFO_EXTENSION));
        $filename = 'cmp_' . time() . '_' . rand(100, 999) . '.' . $ext;
        $destPath = '../uploads/complaints/' . $filename;
        
        if (move_uploaded_file($fileObj['tmp_name'], $destPath)) {
            $photoPath = 'uploads/complaints/' . $filename;
        } else {
            flashMessage('error', 'Failed to save photo.');
            redirect(BASE_URL . 'student/complaints.php');
        }
    }
    
    // Prepare INSERT
    $q = "INSERT INTO complaints (student_id, title, description, category, priority, status";
    $v = "(?, ?, ?, ?, ?, 'open'";
    $types = "issss";
    $params = [$student_id, $title, $description, $category, $priority];
    
    if ($photoPath) {
        $q .= ", photo";
        $v .= ", ?";
        $types .= "s";
        $params[] = $photoPath;
    }
    if ($assigned_to > 0) {
        $q .= ", assigned_to";
        $v .= ", ?";
        $types .= "i";
        $params[] = $assigned_to;
    }
    
    $q .= ") VALUES $v)";
    
    $stmt = $conn->prepare($q);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        flashMessage('success', 'Complaint reported successfully.');
    } else {
        flashMessage('error', 'Database error: ' . $conn->error);
    }
    $stmt->close();
    redirect(BASE_URL . 'student/complaints.php');
}

// ══════════════════════════════════════════════
// REQUEST ROOM CHANGE
// ══════════════════════════════════════════════
if ($action === 'request_room_change') {
    $current_room_id = (int)($_POST['current_room_id'] ?? 0);
    $req_room_id     = (int)($_POST['requested_room_id'] ?? 0);
    $reason          = sanitize($conn, $_POST['reason'] ?? '');
    
    if ($req_room_id <= 0 || empty($reason)) {
        flashMessage('error', 'Requested room and reason are required.');
        redirect(BASE_URL . 'student/room_change.php');
    }
    
    if ($current_room_id == $req_room_id) {
        flashMessage('error', 'You are already in that room.');
        redirect(BASE_URL . 'student/room_change.php');
    }
    
    // Ensure no pending request exists
    $stmt = $conn->prepare("SELECT request_id FROM room_change_requests WHERE student_id = ? AND status = 'pending'");
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        flashMessage('error', 'You already have a pending change request.');
        redirect(BASE_URL . 'student/room_change.php');
    }
    
    $stmt = $conn->prepare("INSERT INTO room_change_requests (student_id, current_room_id, requested_room_id, reason, status) VALUES (?, ?, ?, ?, 'pending')");
    $stmt->bind_param('iiis', $student_id, $current_room_id, $req_room_id, $reason);
    
    if ($stmt->execute()) {
        flashMessage('success', 'Room change request submitted to supervisor for review.');
    } else {
        flashMessage('error', 'Failed to submit request.');
    }
    redirect(BASE_URL . 'student/room_change.php');
}

flashMessage('error', 'Unknown action.');
redirect(BASE_URL);
