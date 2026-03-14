<?php
/**
 * actions/student_action.php — Student CRUD Handler
 * Hostel Management System
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . 'students.php');
}

$action = $_POST['action'] ?? '';

// ══════════════════════════════════════════
// ADD STUDENT
// ══════════════════════════════════════════
if ($action === 'add') {
    $name           = sanitize($conn, $_POST['name']           ?? '');
    $email          = sanitize($conn, $_POST['email']          ?? '');
    $phone          = sanitize($conn, $_POST['phone']          ?? '');
    $dob            = sanitize($conn, $_POST['dob']            ?? '');
    $gender         = sanitize($conn, $_POST['gender']         ?? 'male');
    $room_id        = (int)($_POST['room_id']                  ?? 0);
    $check_in_date  = sanitize($conn, $_POST['check_in_date']  ?? '');
    $guardian_name  = sanitize($conn, $_POST['guardian_name']  ?? '');
    $guardian_phone = sanitize($conn, $_POST['guardian_phone'] ?? '');
    $address        = sanitize($conn, $_POST['address']        ?? '');

    // Basic validation
    if (empty($name) || empty($email)) {
        flashMessage('error', 'Name and email are required.');
        redirect(BASE_URL . 'students.php');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flashMessage('error', 'Please enter a valid email address.');
        redirect(BASE_URL . 'students.php');
    }

    // Check duplicate email
    $chk = $conn->prepare("SELECT student_id FROM students WHERE email = ? LIMIT 1");
    $chk->bind_param('s', $email);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        flashMessage('error', 'A student with this email already exists.');
        redirect(BASE_URL . 'students.php');
    }
    $chk->close();

    // Generate unique student code
    $student_code = generateStudentCode($conn);

    // Handle photo upload
    $photo = '';
    if (!empty($_FILES['photo']['name'])) {
        $uploadDir  = __DIR__ . '/../uploads/students/';
        $ext        = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed    = ['jpg','jpeg','png','webp'];
        if (in_array($ext, $allowed) && $_FILES['photo']['size'] <= 2 * 1024 * 1024) {
            $filename = $student_code . '.' . $ext;
            $destPath = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $destPath)) {
                $photo = 'uploads/students/' . $filename;
            }
        }
    }

    // Null-safe room and date handling
    $room_id_val       = $room_id > 0 ? $room_id : null;
    $check_in_date_val = !empty($check_in_date) ? $check_in_date : null;
    $dob_val           = !empty($dob) ? $dob : null;

    // Insert student
    $stmt = $conn->prepare("
        INSERT INTO students
            (student_code, name, email, phone, dob, gender, address,
             guardian_name, guardian_phone, room_id, check_in_date, photo, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
    ");
    $stmt->bind_param(
        'sssssssssiss',
        $student_code, $name, $email, $phone, $dob_val, $gender, $address,
        $guardian_name, $guardian_phone, $room_id_val, $check_in_date_val, $photo
    );

    if (!$stmt->execute()) {
        flashMessage('error', 'Failed to add student: ' . $conn->error);
        redirect(BASE_URL . 'students.php');
    }
    $stmt->close();

    // Update room occupancy
    if ($room_id > 0) {
        $conn->query("UPDATE rooms SET occupied = occupied + 1 WHERE room_id = $room_id");
        $conn->query("
            UPDATE rooms
            SET status = IF(occupied >= capacity, 'full', 'available')
            WHERE room_id = $room_id
        ");
    }

    flashMessage('success', "Student added successfully! Code: <strong>$student_code</strong>");
    redirect(BASE_URL . 'students.php');
}


// ══════════════════════════════════════════
// EDIT STUDENT
// ══════════════════════════════════════════
if ($action === 'edit') {
    $student_id     = (int)($_POST['student_id']     ?? 0);
    $name           = sanitize($conn, $_POST['name']           ?? '');
    $phone          = sanitize($conn, $_POST['phone']          ?? '');
    $status         = sanitize($conn, $_POST['status']         ?? 'active');
    $guardian_name  = sanitize($conn, $_POST['guardian_name']  ?? '');
    $guardian_phone = sanitize($conn, $_POST['guardian_phone'] ?? '');
    $address        = sanitize($conn, $_POST['address']        ?? '');

    if ($student_id <= 0 || empty($name)) {
        flashMessage('error', 'Invalid student or missing name.');
        redirect(BASE_URL . 'students.php');
    }

    $allowed_statuses = ['active','checked_out','suspended'];
    if (!in_array($status, $allowed_statuses)) $status = 'active';

    $stmt = $conn->prepare("
        UPDATE students
        SET name = ?, phone = ?, status = ?,
            guardian_name = ?, guardian_phone = ?, address = ?
        WHERE student_id = ?
    ");
    $stmt->bind_param('ssssssi', $name, $phone, $status, $guardian_name, $guardian_phone, $address, $student_id);

    if ($stmt->execute()) {
        flashMessage('success', 'Student updated successfully.');
    } else {
        flashMessage('error', 'Failed to update student: ' . $conn->error);
    }
    $stmt->close();

    redirect(BASE_URL . 'students.php');
}


// ══════════════════════════════════════════
// DELETE STUDENT
// ══════════════════════════════════════════
if ($action === 'delete') {
    $student_id = (int)($_POST['student_id'] ?? 0);

    if ($student_id <= 0) {
        flashMessage('error', 'Invalid student ID.');
        redirect(BASE_URL . 'students.php');
    }

    // Get current room assignment before deleting
    $r = $conn->query("SELECT room_id FROM students WHERE student_id = $student_id LIMIT 1");
    $row = $r ? $r->fetch_assoc() : null;
    $assigned_room = $row ? (int)$row['room_id'] : 0;

    // Delete student record
    $stmt = $conn->prepare("DELETE FROM students WHERE student_id = ?");
    $stmt->bind_param('i', $student_id);

    if ($stmt->execute()) {
        // Fix room occupancy
        if ($assigned_room > 0) {
            $conn->query("UPDATE rooms SET occupied = GREATEST(occupied - 1, 0) WHERE room_id = $assigned_room");
            $conn->query("
                UPDATE rooms
                SET status = IF(status = 'full' AND occupied < capacity, 'available', status)
                WHERE room_id = $assigned_room
            ");
        }
        flashMessage('success', 'Student deleted successfully.');
    } else {
        flashMessage('error', 'Failed to delete student.');
    }
    $stmt->close();

    redirect(BASE_URL . 'students.php');
}

// Unknown action
flashMessage('error', 'Unknown action.');
redirect(BASE_URL . 'students.php');
