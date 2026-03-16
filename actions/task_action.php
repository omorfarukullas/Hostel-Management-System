<?php
/**
 * actions/task_action.php — Handle Task CRUD
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin(); // Relaxed for supervisors to update status

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL);
}

$action = $_POST['action'] ?? '';

// ── ADD TASK ──
if ($action === 'add' && isAdmin()) {
    $title       = sanitize($conn, $_POST['title'] ?? '');
    $description = sanitize($conn, $_POST['description'] ?? '');
    $assigned_to = (int)($_POST['assigned_to'] ?? 0);
    $priority    = sanitize($conn, $_POST['priority'] ?? 'medium');
    $due_date    = sanitize($conn, $_POST['due_date'] ?? '');
    $assigned_by = $_SESSION['user_id'];

    if (empty($title) || $assigned_to <= 0) {
        flashMessage('error', 'Title and Assignee are required.');
        redirect(BASE_URL . 'admin/tasks.php');
    }

    $stmt = $conn->prepare("INSERT INTO tasks (title, description, assigned_to, assigned_by, priority, due_date, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
    $due_date_val = !empty($due_date) ? $due_date : null;
    $stmt->bind_param('ssiiss', $title, $description, $assigned_to, $assigned_by, $priority, $due_date_val);

    if ($stmt->execute()) {
        flashMessage('success', 'Task assigned successfully.');
    } else {
        flashMessage('error', 'Failed to assign task.');
    }
    redirect(BASE_URL . 'admin/tasks.php');
}

// ── EDIT TASK ──
if ($action === 'edit' && isAdmin()) {
    $task_id     = (int)($_POST['task_id'] ?? 0);
    $title       = sanitize($conn, $_POST['title'] ?? '');
    $description = sanitize($conn, $_POST['description'] ?? '');
    $priority    = sanitize($conn, $_POST['priority'] ?? 'medium');
    $due_date    = sanitize($conn, $_POST['due_date'] ?? '');
    $status      = sanitize($conn, $_POST['status'] ?? 'pending');

    if ($task_id <= 0 || empty($title)) {
        flashMessage('error', 'Title is required.');
        redirect(BASE_URL . 'admin/tasks.php');
    }

    $stmt = $conn->prepare("UPDATE tasks SET title = ?, description = ?, priority = ?, due_date = ?, status = ? WHERE task_id = ?");
    $due_date_val = !empty($due_date) ? $due_date : null;
    $stmt->bind_param('sssssi', $title, $description, $priority, $due_date_val, $status, $task_id);

    if ($stmt->execute()) {
        flashMessage('success', 'Task updated successfully.');
    } else {
        flashMessage('error', 'Failed to update task.');
    }
    redirect(BASE_URL . 'admin/tasks.php');
}

// ── DELETE TASK ──
if ($action === 'delete' && isAdmin()) {
    $task_id = (int)($_POST['task_id'] ?? 0);
    if ($task_id > 0) {
        $conn->query("DELETE FROM tasks WHERE task_id = $task_id");
        flashMessage('success', 'Task deleted.');
    }
    redirect(BASE_URL . 'admin/tasks.php');
}

// ── UPDATE STATUS (Supervisor) ──
if ($action === 'update_status') {
    $task_id     = (int)($_POST['task_id'] ?? 0);
    $status      = sanitize($conn, $_POST['status'] ?? 'pending');
    $redirect_to = sanitize($conn, $_POST['redirect_to'] ?? 'supervisor/tasks.php');

    $validStatuses = ['pending', 'in_progress', 'done'];
    if (!in_array($status, $validStatuses)) $status = 'pending';

    if ($task_id > 0) {
        $stmt = $conn->prepare("UPDATE tasks SET status = ? WHERE task_id = ?");
        $stmt->bind_param('si', $status, $task_id);
        if ($stmt->execute()) {
            flashMessage('success', "Task status updated to " . ucfirst(str_replace('_', ' ', $status)) . ".");
        } else {
            flashMessage('error', 'Failed to update task status.');
        }
    }
    redirect(BASE_URL . $redirect_to);
}

flashMessage('error', 'Unknown action or unauthorized access.');
redirect(BASE_URL);
