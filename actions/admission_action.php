<?php
/**
 * actions/admission_action.php — Handle Approval/Rejection
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . 'admin/admission_requests.php');
}

$action = $_POST['action'] ?? '';

// ── APPROVE ──
if ($action === 'approve') {
    $request_id = (int)($_POST['request_id'] ?? 0);
    $room_id    = (int)($_POST['room_id'] ?? 0);

    if ($request_id <= 0 || $room_id <= 0) {
        flashMessage('error', 'Valid Request ID and Room ID are required.');
        redirect(BASE_URL . 'admin/admission_requests.php');
    }

    // 1. Fetch Request Details
    $stmt = $conn->prepare("SELECT * FROM admission_requests WHERE request_id = ? AND status = 'pending' LIMIT 1");
    $stmt->bind_param('i', $request_id);
    $stmt->execute();
    $req = $stmt->get_result()->fetch_assoc();

    if (!$req) {
        flashMessage('error', 'Request not found or already processed.');
        redirect(BASE_URL . 'admin/admission_requests.php');
    }

    $conn->begin_transaction();
    try {
        // 2. Create User Record
        $stmt_user = $conn->prepare("INSERT INTO users (name, email, password, role, phone) VALUES (?, ?, ?, 'student', ?)");
        $stmt_user->bind_param('ssss', $req['name'], $req['email'], $req['password_hash'], $req['phone']);
        $stmt_user->execute();
        $user_id = $conn->insert_id;

        // 3. Create Student Record
        $student_code = generateStudentCode($conn);
        $check_in_date = date('Y-m-d');
        $stmt_stu = $conn->prepare("
            INSERT INTO students (user_id, student_code, name, email, phone, dob, gender, address, guardian_name, guardian_phone, room_id, check_in_date, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt_stu->bind_param(
            'isssssssssis',
            $user_id, $student_code, $req['name'], $req['email'], $req['phone'],
            $req['dob'], $req['gender'], $req['address'], $req['guardian_name'], $req['guardian_phone'],
            $room_id, $check_in_date
        );
        $stmt_stu->execute();

        // 4. Update Room Occupancy
        $conn->query("UPDATE rooms SET occupied = occupied + 1 WHERE room_id = $room_id");
        $conn->query("UPDATE rooms SET status = 'full' WHERE room_id = $room_id AND occupied >= capacity");

        // 5. Update Request Status
        $stmt_upd = $conn->prepare("UPDATE admission_requests SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE request_id = ?");
        $admin_id = $_SESSION['user_id'];
        $stmt_upd->bind_param('ii', $admin_id, $request_id);
        $stmt_upd->execute();

        $conn->commit();
        flashMessage('success', "Admission approved! Student Code: $student_code");
    } catch (Exception $e) {
        $conn->rollback();
        flashMessage('error', 'Approval failed: ' . $e->getMessage());
    }
    redirect(BASE_URL . 'admin/admission_requests.php');
}

// ── REJECT ──
if ($action === 'reject') {
    $request_id    = (int)($_POST['request_id'] ?? 0);
    $reject_reason = sanitize($conn, $_POST['reject_reason'] ?? '');

    if ($request_id <= 0 || empty($reject_reason)) {
        flashMessage('error', 'Request ID and rejection reason are required.');
        redirect(BASE_URL . 'admin/admission_requests.php');
    }

    $stmt = $conn->prepare("UPDATE admission_requests SET status = 'rejected', reject_reason = ?, reviewed_by = ?, reviewed_at = NOW() WHERE request_id = ? AND status = 'pending'");
    $admin_id = $_SESSION['user_id'];
    $stmt->bind_param('sii', $reject_reason, $admin_id, $request_id);

    if ($stmt->execute()) {
        flashMessage('success', 'Admission request rejected.');
    } else {
        flashMessage('error', 'Rejection failed.');
    }
    redirect(BASE_URL . 'admin/admission_requests.php');
}

flashMessage('error', 'Unknown action.');
redirect(BASE_URL . 'admin/admission_requests.php');
