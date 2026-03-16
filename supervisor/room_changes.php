<?php
/**
 * supervisor/room_changes.php — Manage Room Change Requests
 * Hostel Management System
 */
$pageTitle = 'Room Changes';
require_once __DIR__ . '/../includes/header.php';

requireSupervisor();

$userId = $_SESSION['user_id'];

// Get supervisor block
$stmt = $conn->prepare("SELECT block_assigned FROM supervisors WHERE user_id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$blockAssigned = $stmt->get_result()->fetch_assoc()['block_assigned'] ?? '';

// Fetch requests where either current room or requested room is in supervisor's block
$requests = null;
if ($blockAssigned) {
    $stmt = $conn->prepare("
        SELECT rc.*, s.name as student_name, s.student_code, 
               r_curr.room_number as curr_room, r_curr.block as curr_block,
               r_req.room_number as req_room, r_req.block as req_block
        FROM room_change_requests rc
        JOIN students s ON rc.student_id = s.student_id
        LEFT JOIN rooms r_curr ON rc.current_room_id = r_curr.room_id
        LEFT JOIN rooms r_req ON rc.requested_room_id = r_req.room_id
        WHERE (r_curr.block = ? OR r_req.block = ?)
        ORDER BY rc.created_at DESC
    ");
    $stmt->bind_param('ss', $blockAssigned, $blockAssigned);
    $stmt->execute();
    $requests = $stmt->get_result();
}

function reqStatusBadge(string $s): string {
    $map = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'];
    return '<span class="badge badge-' . ($map[$s] ?? 'secondary') . '">' . ucfirst($s) . '</span>';
}
?>

<div class="page-header">
    <h1>🔄 Room Change Requests</h1>
</div>

<div class="table-card">
    <div class="table-header">
        <span class="table-title">Requests for Block: <?= e($blockAssigned ?: 'None') ?></span>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Current Room</th>
                    <th>Requested Room</th>
                    <th>Reason</th>
                    <th>Date</th>
                    <th>Status / Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($requests && $requests->num_rows > 0): ?>
                <?php while ($r = $requests->fetch_assoc()): ?>
                <tr>
                    <td>
                        <strong><?= e($r['student_name']) ?></strong><br>
                        <small class="text-muted"><?= e($r['student_code']) ?></small>
                    </td>
                    <td>
                        <span class="badge badge-secondary"><?= e($r['curr_room']) ?></span><br>
                        <small class="text-muted"><?= e($r['curr_block']) ?></small>
                    </td>
                    <td>
                        <span class="badge badge-info"><?= e($r['req_room']) ?></span><br>
                        <small class="text-muted"><?= e($r['req_block']) ?></small>
                    </td>
                    <td><?= e($r['reason']) ?></td>
                    <td><?= formatDate($r['created_at']) ?></td>
                    <td>
                        <?php if ($r['status'] === 'pending'): ?>
                            <div class="d-flex gap-1">
                                <form method="POST" action="<?= BASE_URL ?>actions/room_change_action.php" style="display:inline;">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="request_id" value="<?= (int)$r['request_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Approve this change? The student will be moved immediately.')">✅ Approve</button>
                                </form>
                                <button type="button" class="btn btn-sm btn-danger" onclick="openRejectModal(<?= (int)$r['request_id'] ?>, '<?= htmlspecialchars(e($r['student_name']), ENT_QUOTES) ?>')">✕ Reject</button>
                            </div>
                        <?php else: ?>
                            <?= reqStatusBadge($r['status']) ?>
                            <?php if ($r['status'] === 'rejected' && $r['reject_reason']): ?>
                                <br><small class="text-danger">Reason: <?= e($r['reject_reason']) ?></small>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6" class="text-center" style="padding:40px;">
                    <div class="empty-state">
                        <span class="empty-state-icon">🔄</span>
                        <div class="empty-state-title">No requests found</div>
                        <div class="empty-state-msg">There are no pending room change requests for your block.</div>
                    </div>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ══════════ REJECT MODAL ══════════ -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal-card" style="max-width:400px;">
        <div class="modal-header">
            <h3 class="modal-title">✕ Reject Request</h3>
            <button class="modal-close" onclick="closeModal('rejectModal')">✕</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>actions/room_change_action.php">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="request_id" id="rejectRequestId">
            <div class="modal-body">
                <p>Reject room change for <strong id="rejectName"></strong>?</p>
                <div class="form-group mt-2">
                    <label>Reason for Rejection <span style="color:red">*</span></label>
                    <textarea name="reject_reason" class="form-control" rows="2" required placeholder="e.g. Requested room is fully occupied"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('rejectModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">Confirm Rejection</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRejectModal(id, name) {
    document.getElementById('rejectRequestId').value = id;
    document.getElementById('rejectName').innerText = name;
    openModal('rejectModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
