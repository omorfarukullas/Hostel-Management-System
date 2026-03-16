<?php
/**
 * actions/room_change_action.php — Room Change Request Handler
 * Hostel Management System
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin(); // Supervisor or Student might use this, but we restrict approve/reject to supervisor

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL);
}

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];
$isSup = isSupervisor();

// Supervisor approves request
if ($action === 'approve' && $isSup) {
    $request_id = (int)($_POST['request_id'] ?? 0);
    
    // Fetch request details
    $stmt = $conn->prepare("SELECT student_id, requested_room_id FROM room_change_requests WHERE request_id = ? AND status = 'pending'");
    $stmt->bind_param('i', $request_id);
    $stmt->execute();
    $req = $stmt->get_result()->fetch_assoc();
    
    if (!$req) {
        flashMessage('error', 'Invalid or already processed request.');
        redirect(BASE_URL . 'supervisor/room_changes.php');
    }
    
    $student_id = $req['student_id'];
    $new_room_id = $req['requested_room_id'];
    
    $conn->begin_transaction();
    try {
        // Find old room
        $sStmt = $conn->prepare("SELECT room_id FROM students WHERE student_id = ?");
        $sStmt->bind_param('i', $student_id);
        $sStmt->execute();
        $old_room_id = $sStmt->get_result()->fetch_assoc()['room_id'] ?? null;
        
        // Update request status
        $rStmt = $conn->prepare("UPDATE room_change_requests SET status = 'approved', decided_by = ?, decided_at = NOW() WHERE request_id = ?");
        $rStmt->bind_param('ii', $user_id, $request_id);
        $rStmt->execute();
        
        // Update student room
        $uStmt = $conn->prepare("UPDATE students SET room_id = ? WHERE student_id = ?");
        $uStmt->bind_param('ii', $new_room_id, $student_id);
        $uStmt->execute();
        
        // Update old room occupancy (-1)
        if ($old_room_id) {
            $conn->query("UPDATE rooms SET occupied = GREATEST(occupied - 1, 0) WHERE room_id = " . (int)$old_room_id);
            $conn->query("UPDATE rooms SET status = 'available' WHERE room_id = " . (int)$old_room_id . " AND status = 'full'");
        }
        
        // Update new room occupancy (+1)
        $conn->query("UPDATE rooms SET occupied = occupied + 1 WHERE room_id = " . (int)$new_room_id);
        $nR = $conn->query("SELECT capacity, occupied FROM rooms WHERE room_id = " . (int)$new_room_id)->fetch_assoc();
        if ($nR && $nR['occupied'] >= $nR['capacity']) {
            $conn->query("UPDATE rooms SET status = 'full' WHERE room_id = " . (int)$new_room_id);
        }
        
        $conn->commit();
        flashMessage('success', 'Room change approved. Student has been moved.');
    } catch (Exception $e) {
        $conn->rollback();
        flashMessage('error', 'Database error: ' . $e->getMessage());
    }
    redirect(BASE_URL . 'supervisor/room_changes.php');
}

// Supervisor rejects request
if ($action === 'reject' && $isSup) {
    $request_id = (int)($_POST['request_id'] ?? 0);
    $reject_reason = sanitize($conn, $_POST['reject_reason'] ?? '');
    
    if (empty($reject_reason)) {
        flashMessage('error', 'Rejection reason is required.');
        redirect(BASE_URL . 'supervisor/room_changes.php');
    }
    
    $stmt = $conn->prepare("UPDATE room_change_requests SET status = 'rejected', reject_reason = ?, decided_by = ?, decided_at = NOW() WHERE request_id = ? AND status = 'pending'");
    $stmt->bind_param('sii', $reject_reason, $user_id, $request_id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        flashMessage('success', 'Request rejected.');
    } else {
        flashMessage('error', 'Failed to reject or request already processed.');
    }
    $stmt->close();
    redirect(BASE_URL . 'supervisor/room_changes.php');
}

flashMessage('error', 'Unknown action or unauthorized.');
redirect(BASE_URL);
