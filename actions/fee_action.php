<?php
/**
 * actions/fee_action.php — Fee Records Handler
 * Hostel Management System
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . 'fees.php');
}

$action          = $_POST['action']          ?? '';
$redirectStudent = (int)($_POST['redirect_student'] ?? 0);
$backUrl         = BASE_URL . 'fees.php' . ($redirectStudent > 0 ? "?student_id=$redirectStudent" : '');

// ══════════════════════════════════════════════
// ADD FEE RECORD
// ══════════════════════════════════════════════
if ($action === 'add') {
    $student_id     = (int)($_POST['student_id']     ?? 0);
    $amount         = max(0.0, (float)($_POST['amount'] ?? 0));
    $fee_month      = sanitize($conn, $_POST['fee_month']      ?? '');
    $fee_year       = (int)($_POST['fee_year']       ?? date('Y'));
    $payment_date   = sanitize($conn, $_POST['payment_date']   ?? '');
    $payment_method = sanitize($conn, $_POST['payment_method'] ?? 'cash');
    $fee_status     = sanitize($conn, $_POST['status']         ?? 'unpaid');
    $remarks        = sanitize($conn, $_POST['remarks']        ?? '');

    if ($student_id <= 0 || empty($fee_month)) {
        flashMessage('error', 'Student and month are required.');
        redirect($backUrl);
    }

    $allowed_methods  = ['cash','bank_transfer','online'];
    $allowed_statuses = ['paid','unpaid','partial'];
    if (!in_array($payment_method, $allowed_methods))  $payment_method = 'cash';
    if (!in_array($fee_status, $allowed_statuses))     $fee_status     = 'unpaid';
    if ($fee_year < 2020 || $fee_year > 2099)          $fee_year       = (int)date('Y');

    // Get student's current room_id
    $sr = $conn->query("SELECT room_id FROM students WHERE student_id = $student_id LIMIT 1");
    $room_id = ($sr && $sr->num_rows > 0) ? (int)$sr->fetch_assoc()['room_id'] : 0;

    $payment_date_val = !empty($payment_date) ? $payment_date : null;

    $stmt = $conn->prepare("
        INSERT INTO fees
            (student_id, room_id, amount, fee_month, fee_year,
             payment_date, payment_method, status, remarks)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    // s=student_id→i, room_id→i (nullable), amount→d, fee_month→s, fee_year→i, payment_date→s, payment_method→s, status→s, remarks→s
    $stmt->bind_param('iidssisss',
        $student_id, $room_id, $amount, $fee_month, $fee_year,
        $payment_date_val, $payment_method, $fee_status, $remarks
    );

    if ($stmt->execute()) {
        flashMessage('success', 'Fee record added successfully.');
    } else {
        flashMessage('error', 'Failed to add fee record: ' . $conn->error);
    }
    $stmt->close();
    redirect($backUrl);
}


// ══════════════════════════════════════════════
// MARK AS PAID
// ══════════════════════════════════════════════
if ($action === 'mark_paid') {
    $fee_id = (int)($_POST['fee_id'] ?? 0);
    $today  = date('Y-m-d');

    if ($fee_id <= 0) {
        flashMessage('error', 'Invalid fee record.');
        redirect($backUrl);
    }

    $stmt = $conn->prepare("
        UPDATE fees SET status = 'paid', payment_date = ?
        WHERE fee_id = ?
    ");
    $stmt->bind_param('si', $today, $fee_id);

    if ($stmt->execute()) {
        flashMessage('success', 'Fee marked as paid successfully.');
    } else {
        flashMessage('error', 'Failed to mark fee as paid.');
    }
    $stmt->close();
    redirect($backUrl);
}


// ══════════════════════════════════════════════
// DELETE FEE RECORD
// ══════════════════════════════════════════════
if ($action === 'delete') {
    $fee_id = (int)($_POST['fee_id'] ?? 0);

    if ($fee_id <= 0) {
        flashMessage('error', 'Invalid fee record.');
        redirect($backUrl);
    }

    $stmt = $conn->prepare("DELETE FROM fees WHERE fee_id = ?");
    $stmt->bind_param('i', $fee_id);

    if ($stmt->execute()) {
        flashMessage('success', 'Fee record deleted successfully.');
    } else {
        flashMessage('error', 'Failed to delete fee record.');
    }
    $stmt->close();
    redirect($backUrl);
}

flashMessage('error', 'Unknown action.');
redirect($backUrl);
