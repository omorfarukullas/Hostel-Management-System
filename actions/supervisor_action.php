<?php
/**
 * actions/supervisor_action.php
 * Handles add / edit / delete for supervisor accounts.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . 'admin/supervisors.php');
}

$action = $_POST['action'] ?? '';
$back   = BASE_URL . 'admin/supervisors.php';

/* ══ ADD ══════════════════════════════════════════ */
if ($action === 'add') {
    $name           = sanitize($conn, $_POST['name']           ?? '');
    $email          = sanitize($conn, $_POST['email']          ?? '');
    $password       = $_POST['password'] ?? '';
    $phone          = sanitize($conn, $_POST['phone']          ?? '');
    $block_assigned = sanitize($conn, $_POST['block_assigned'] ?? '');
    $department     = sanitize($conn, $_POST['department']     ?? '');
    $joined_date    = sanitize($conn, $_POST['joined_date']    ?? date('Y-m-d'));

    if (!$name || !$email || !$password) {
        flashMessage('error', 'Name, email and password are required.');
        redirect($back);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flashMessage('error', 'Invalid email address.');
        redirect($back);
    }

    // Check duplicate email
    $chk = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
    $chk->bind_param('s', $email);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        flashMessage('error', 'A user with that email already exists.');
        redirect($back);
    }

    $conn->begin_transaction();
    try {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $s1 = $conn->prepare("INSERT INTO users (name, email, password, role, phone, status) VALUES (?, ?, ?, 'supervisor', ?, 'active')");
        $s1->bind_param('ssss', $name, $email, $hash, $phone);
        $s1->execute();
        $uid = $conn->insert_id;

        $s2 = $conn->prepare("INSERT INTO supervisors (user_id, block_assigned, department, joined_date) VALUES (?, ?, ?, ?)");
        $s2->bind_param('isss', $uid, $block_assigned, $department, $joined_date);
        $s2->execute();

        $conn->commit();
        flashMessage('success', "Supervisor <strong>" . e($name) . "</strong> added successfully.");
    } catch (Throwable $ex) {
        $conn->rollback();
        flashMessage('error', 'Failed to add supervisor: ' . $ex->getMessage());
    }
    redirect($back);
}

/* ══ EDIT ══════════════════════════════════════════ */
if ($action === 'edit') {
    $user_id        = (int)($_POST['user_id']        ?? 0);
    $name           = sanitize($conn, $_POST['name']           ?? '');
    $phone          = sanitize($conn, $_POST['phone']          ?? '');
    $block_assigned = sanitize($conn, $_POST['block_assigned'] ?? '');
    $department     = sanitize($conn, $_POST['department']     ?? '');
    $status         = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';
    $password       = $_POST['password'] ?? '';

    if ($user_id <= 0 || !$name) {
        flashMessage('error', 'Invalid request.');
        redirect($back);
    }

    $conn->begin_transaction();
    try {
        if ($password) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $s = $conn->prepare("UPDATE users SET name=?, phone=?, status=?, password=? WHERE user_id=? AND role='supervisor'");
            $s->bind_param('ssssi', $name, $phone, $status, $hash, $user_id);
        } else {
            $s = $conn->prepare("UPDATE users SET name=?, phone=?, status=? WHERE user_id=? AND role='supervisor'");
            $s->bind_param('sssi', $name, $phone, $status, $user_id);
        }
        $s->execute();

        // Upsert supervisor profile
        $s2 = $conn->prepare("
            INSERT INTO supervisors (user_id, block_assigned, department)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE block_assigned = VALUES(block_assigned), department = VALUES(department)
        ");
        $s2->bind_param('iss', $user_id, $block_assigned, $department);
        $s2->execute();

        $conn->commit();
        flashMessage('success', 'Supervisor updated successfully.');
    } catch (Throwable $ex) {
        $conn->rollback();
        flashMessage('error', 'Update failed: ' . $ex->getMessage());
    }
    redirect($back);
}

/* ══ DELETE ══════════════════════════════════════════ */
if ($action === 'delete') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    if ($user_id <= 0) { flashMessage('error', 'Invalid ID.'); redirect($back); }

    $s = $conn->prepare("DELETE FROM users WHERE user_id = ? AND role = 'supervisor'");
    $s->bind_param('i', $user_id);
    $s->execute() && $s->affected_rows > 0
        ? flashMessage('success', 'Supervisor removed.')
        : flashMessage('error', 'Could not remove supervisor.');
    redirect($back);
}

flashMessage('error', 'Unknown action.');
redirect($back);
