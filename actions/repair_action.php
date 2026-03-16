<?php
/**
 * actions/repair_action.php — Repair Expense Handler
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . 'admin/repair_costs.php');
}

$action = $_POST['action'] ?? '';

// ── ADD REPAIR RECORD ──
if ($action === 'add') {
    $title        = sanitize($conn, $_POST['title'] ?? '');
    $complaint_id = !empty($_POST['complaint_id']) ? (int)$_POST['complaint_id'] : null;
    $amount       = (float)($_POST['amount'] ?? 0);
    $repair_date  = sanitize($conn, $_POST['repair_date'] ?? date('Y-m-d'));
    $vendor_name  = sanitize($conn, $_POST['vendor_name'] ?? '');
    $description  = sanitize($conn, $_POST['description'] ?? '');
    $created_by   = $_SESSION['user_id'];
    
    if (empty($title) || $amount <= 0) {
        flashMessage('error', 'Title and amount are required.');
        redirect(BASE_URL . 'admin/repair_costs.php');
    }

    // Handle Receipt Upload
    $receipt_path = '';
    if (isset($_FILES['receipt_photo']) && $_FILES['receipt_photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/receipts/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $ext = pathinfo($_FILES['receipt_photo']['name'], PATHINFO_EXTENSION);
        $filename = 'receipt_' . time() . '_' . mt_rand(100, 999) . '.' . $ext;
        if (move_uploaded_file($_FILES['receipt_photo']['tmp_name'], $upload_dir . $filename)) {
            $receipt_path = 'uploads/receipts/' . $filename;
        }
    }

    $stmt = $conn->prepare("INSERT INTO repair_costs (complaint_id, title, description, amount, repair_date, vendor_name, receipt_photo, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('issdsssi', $complaint_id, $title, $description, $amount, $repair_date, $vendor_name, $receipt_path, $created_by);

    if ($stmt->execute()) {
        flashMessage('success', 'Repair expense recorded successfully.');
    } else {
        flashMessage('error', 'Failed to record expense.');
    }
    redirect(BASE_URL . 'admin/repair_costs.php');
}

// ── DELETE REPAIR RECORD ──
if ($action === 'delete') {
    $cost_id = (int)($_POST['cost_id'] ?? 0);
    
    if ($cost_id > 0) {
        // Fetch path to delete file
        $res = $conn->query("SELECT receipt_photo FROM repair_costs WHERE cost_id = $cost_id");
        if ($row = $res->fetch_assoc()) {
            if (!empty($row['receipt_photo']) && file_exists(__DIR__ . '/../' . $row['receipt_photo'])) {
                unlink(__DIR__ . '/../' . $row['receipt_photo']);
            }
        }
        
        $conn->query("DELETE FROM repair_costs WHERE cost_id = $cost_id");
        flashMessage('success', 'Expense record deleted.');
    }
    redirect(BASE_URL . 'admin/repair_costs.php');
}

flashMessage('error', 'Unknown action.');
redirect(BASE_URL . 'admin/repair_costs.php');
