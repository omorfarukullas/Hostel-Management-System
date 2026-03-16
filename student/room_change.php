<?php
/**
 * student/room_change.php — Request Room Change
 * Hostel Management System
 */
$pageTitle = 'Room Change';
require_once __DIR__ . '/../includes/header.php';

requireStudent();

$userId = $_SESSION['user_id'];

// Get student info
$stmt = $conn->prepare("
    SELECT s.student_id, s.room_id, r.room_number, r.block 
    FROM students s
    LEFT JOIN rooms r ON s.room_id = r.room_id
    WHERE s.user_id = ?
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$stInfo = $stmt->get_result()->fetch_assoc();
$studentId = $stInfo['student_id'] ?? 0;
$currentRoomId = $stInfo['room_id'] ?? null;
$currentRoomDesc = $currentRoomId ? ($stInfo['room_number'] . ' (Block ' . ($stInfo['block'] ?: 'General') . ')') : 'None';

// Get available rooms for dropdown
$rooms = $conn->query("
    SELECT room_id, room_number, block, type, capacity, occupied, monthly_fee 
    FROM rooms 
    WHERE status != 'full' AND status != 'maintenance'
    ORDER BY block ASC, room_number ASC
");

// Fetch my history of requests
$stmt = $conn->prepare("
    SELECT rc.*, r_curr.room_number as curr_room, r_req.room_number as req_room 
    FROM room_change_requests rc
    LEFT JOIN rooms r_curr ON rc.current_room_id = r_curr.room_id
    LEFT JOIN rooms r_req ON rc.requested_room_id = r_req.room_id
    WHERE rc.student_id = ? 
    ORDER BY rc.created_at DESC
");
$stmt->bind_param('i', $studentId);
$stmt->execute();
$history = $stmt->get_result();

// Check if there is an active pending request
$hasPending = false;
$historyArr = [];
if ($history && $history->num_rows > 0) {
    while ($r = $history->fetch_assoc()) {
        if ($r['status'] === 'pending') $hasPending = true;
        $historyArr[] = $r;
    }
}

function reqStatusBadge(string $s): string {
    $map = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'];
    return '<span class="badge badge-' . ($map[$s] ?? 'secondary') . '">' . ucfirst($s) . '</span>';
}
?>

<div class="page-header">
    <h1>🔄 Room Change Request</h1>
</div>

<div class="dashboard-grid" style="display:grid; grid-template-columns: 1fr 2fr; gap:20px; align-items: start;">
    
    <!-- Request Form -->
    <div class="table-card" style="margin-bottom:0;">
        <div class="table-header">
            <span class="table-title">New Request</span>
        </div>
        <div style="padding:20px;">
            <?php if (!$currentRoomId): ?>
                <div class="alert alert-warning">You must be assigned to a room first before requesting a change.</div>
            <?php elseif ($hasPending): ?>
                <div class="alert alert-info">You already have a pending room change request. Please wait for the supervisor to process it.</div>
            <?php else: ?>
                <form method="POST" action="<?= BASE_URL ?>actions/student_portal_action.php">
                    <input type="hidden" name="action" value="request_room_change">
                    <input type="hidden" name="student_id" value="<?= $studentId ?>">
                    <input type="hidden" name="current_room_id" value="<?= $currentRoomId ?>">
                    
                    <div class="form-group mb-3">
                        <label>Current Room</label>
                        <input type="text" class="form-control" value="<?= e($currentRoomDesc) ?>" disabled style="background:#f8fafc; color:#64748b;">
                    </div>
                    
                    <div class="form-group mb-3">
                        <label>Select New Room <span style="color:red">*</span></label>
                        <select name="requested_room_id" class="form-control" required>
                            <option value="">-- Select Available Room --</option>
                            <?php if ($rooms && $rooms->num_rows > 0): ?>
                                <?php while ($r = $rooms->fetch_assoc()): ?>
                                    <?php if ($r['room_id'] != $currentRoomId): ?>
                                        <option value="<?= $r['room_id'] ?>">
                                            <?= e($r['room_number']) ?> (Block <?= e($r['block'] ?: 'Gen') ?>) - <?= ucfirst($r['type']) ?> - <?= formatCurrency($r['monthly_fee']) ?>/mo
                                        </option>
                                    <?php endif; ?>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="form-group mb-4">
                        <label>Reason for Change <span style="color:red">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" required placeholder="Why do you want to move?"></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width:100%;">Submit Request</button>
                    <p style="margin-top:10px; font-size:0.85rem; color:#64748b; text-align:center;">
                        Note: Requests are subject to supervisor approval. Submitting a request does not guarantee a room change.
                    </p>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Request History -->
    <div class="table-card" style="margin-bottom:0;">
        <div class="table-header">
            <span class="table-title">Request History</span>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Wanted Room</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Decision Note</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($historyArr)): ?>
                    <?php foreach ($historyArr as $req): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($req['created_at'])) ?></td>
                        <td>
                            <span class="badge badge-info"><?= e($req['req_room']) ?></span>
                            <br><small class="text-muted">from <?= e($req['curr_room']) ?></small>
                        </td>
                        <td><small><?= e($req['reason']) ?></small></td>
                        <td><?= reqStatusBadge($req['status']) ?></td>
                        <td>
                            <?php if ($req['status'] === 'rejected' && $req['reject_reason']): ?>
                                <small class="text-danger"><?= e($req['reject_reason']) ?></small>
                            <?php elseif ($req['status'] === 'approved'): ?>
                                <small class="text-success">Approved</small>
                            <?php else: ?>
                                <small class="text-muted">-</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center text-muted" style="padding:40px;">No room change requests found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
