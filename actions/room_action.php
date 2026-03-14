<?php
/**
 * actions/room_action.php — Room CRUD Handler
 * Hostel Management System
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . 'rooms.php');
}

$action = $_POST['action'] ?? '';

// ══════════════════════════════════════════════
// ADD ROOM
// ══════════════════════════════════════════════
if ($action === 'add') {
    $room_number = sanitize($conn, $_POST['room_number'] ?? '');
    $floor       = max(1, (int)($_POST['floor'] ?? 1));
    $type        = sanitize($conn, $_POST['type']        ?? 'single');
    $capacity    = max(1, (int)($_POST['capacity']       ?? 1));
    $monthly_fee = max(0.0, (float)($_POST['monthly_fee'] ?? 0));
    $status      = sanitize($conn, $_POST['status']      ?? 'available');
    $amenities   = sanitize($conn, $_POST['amenities']   ?? '');

    if (empty($room_number)) {
        flashMessage('error', 'Room number is required.');
        redirect(BASE_URL . 'rooms.php');
    }

    $allowed_types    = ['single','double','triple','dormitory'];
    $allowed_statuses = ['available','maintenance'];
    if (!in_array($type, $allowed_types))      $type   = 'single';
    if (!in_array($status, $allowed_statuses)) $status = 'available';

    // Check duplicate room number
    $chk = $conn->prepare("SELECT room_id FROM rooms WHERE room_number = ? LIMIT 1");
    $chk->bind_param('s', $room_number);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        flashMessage('error', "Room number <strong>$room_number</strong> already exists.");
        redirect(BASE_URL . 'rooms.php');
    }
    $chk->close();

    $stmt = $conn->prepare("
        INSERT INTO rooms (room_number, floor, type, capacity, occupied, monthly_fee, status, amenities)
        VALUES (?, ?, ?, ?, 0, ?, ?, ?)
    ");
    $stmt->bind_param('siisdss', $room_number, $floor, $type, $capacity, $monthly_fee, $status, $amenities);

    if ($stmt->execute()) {
        flashMessage('success', "Room <strong>$room_number</strong> added successfully.");
    } else {
        flashMessage('error', 'Failed to add room: ' . $conn->error);
    }
    $stmt->close();
    redirect(BASE_URL . 'rooms.php');
}


// ══════════════════════════════════════════════
// EDIT ROOM
// ══════════════════════════════════════════════
if ($action === 'edit') {
    $room_id     = (int)($_POST['room_id']       ?? 0);
    $room_number = sanitize($conn, $_POST['room_number'] ?? '');
    $monthly_fee = max(0.0, (float)($_POST['monthly_fee'] ?? 0));
    $status      = sanitize($conn, $_POST['status']      ?? 'available');
    $amenities   = sanitize($conn, $_POST['amenities']   ?? '');

    if ($room_id <= 0 || empty($room_number)) {
        flashMessage('error', 'Invalid request.');
        redirect(BASE_URL . 'rooms.php');
    }

    $allowed_statuses = ['available','full','maintenance'];
    if (!in_array($status, $allowed_statuses)) $status = 'available';

    $stmt = $conn->prepare("
        UPDATE rooms
        SET room_number = ?, monthly_fee = ?, status = ?, amenities = ?
        WHERE room_id = ?
    ");
    $stmt->bind_param('sdssi', $room_number, $monthly_fee, $status, $amenities, $room_id);

    if ($stmt->execute()) {
        flashMessage('success', 'Room updated successfully.');
    } else {
        flashMessage('error', 'Failed to update room: ' . $conn->error);
    }
    $stmt->close();
    redirect(BASE_URL . 'rooms.php');
}


// ══════════════════════════════════════════════
// DELETE ROOM
// ══════════════════════════════════════════════
if ($action === 'delete') {
    $room_id = (int)($_POST['room_id'] ?? 0);

    if ($room_id <= 0) {
        flashMessage('error', 'Invalid room ID.');
        redirect(BASE_URL . 'rooms.php');
    }

    // Guard: check if active students are assigned
    $chk = $conn->prepare("
        SELECT COUNT(*) AS cnt FROM students
        WHERE room_id = ? AND status = 'active'
    ");
    $chk->bind_param('i', $room_id);
    $chk->execute();
    $cnt = (int)$chk->get_result()->fetch_assoc()['cnt'];
    $chk->close();

    if ($cnt > 0) {
        flashMessage('error', "Cannot delete: <strong>$cnt student(s)</strong> are currently assigned to this room.");
        redirect(BASE_URL . 'rooms.php');
    }

    $stmt = $conn->prepare("DELETE FROM rooms WHERE room_id = ?");
    $stmt->bind_param('i', $room_id);

    if ($stmt->execute()) {
        flashMessage('success', 'Room deleted successfully.');
    } else {
        flashMessage('error', 'Failed to delete room.');
    }
    $stmt->close();
    redirect(BASE_URL . 'rooms.php');
}

flashMessage('error', 'Unknown action.');
redirect(BASE_URL . 'rooms.php');
