<?php
/**
 * admin/admission_requests.php — Handle Student Registrations
 */
$pageTitle = 'Admission Requests';
require_once __DIR__ . '/../includes/header.php';

requireAdmin();

// ── Fetch all requests ──
$statusFilter = sanitize($conn, $_GET['status'] ?? 'pending');
$allowedStatuses = ['pending', 'approved', 'rejected'];
if (!in_array($statusFilter, $allowedStatuses)) $statusFilter = 'pending';

$stmt = $conn->prepare("SELECT * FROM admission_requests WHERE status = ? ORDER BY requested_at DESC");
$stmt->bind_param('s', $statusFilter);
$stmt->execute();
$requests = $stmt->get_result();

// ── Rooms for approval dropdown ──
$availRooms = $conn->query("SELECT room_id, room_number, type, monthly_fee FROM rooms WHERE status = 'available' ORDER BY room_number ASC");

function reqStatusBadge(string $s): string {
    $map = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'];
    return '<span class="badge badge-' . ($map[$s] ?? 'secondary') . '">' . ucfirst($s) . '</span>';
}
?>

<div class="page-header">
    <h1>📝 Admission Requests</h1>
</div>

<!-- Filter Tabs -->
<div class="table-card" style="margin-bottom:1rem;">
    <div class="filter-bar" style="padding: 10px 20px;">
        <div class="d-flex gap-2">
            <a href="?status=pending" class="btn btn-sm <?= $statusFilter === 'pending' ? 'btn-primary' : 'btn-secondary' ?>">Pending</a>
            <a href="?status=approved" class="btn btn-sm <?= $statusFilter === 'approved' ? 'btn-primary' : 'btn-secondary' ?>">Approved</a>
            <a href="?status=rejected" class="btn btn-sm <?= $statusFilter === 'rejected' ? 'btn-primary' : 'btn-secondary' ?>">Rejected</a>
        </div>
    </div>
</div>

<div class="table-card">
    <div class="table-header">
        <span class="table-title"><?= ucfirst($statusFilter) ?> Requests
            <span class="badge badge-info" style="margin-left:6px;"><?= $requests->num_rows ?></span>
        </span>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Applicant</th>
                    <th>Contact</th>
                    <th>Gender</th>
                    <th>Preference</th>
                    <th>Requested At</th>
                    <?php if ($statusFilter === 'pending'): ?>
                        <th>Actions</th>
                    <?php else: ?>
                        <th>Status / Reason</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if ($requests->num_rows > 0): ?>
                <?php while ($r = $requests->fetch_assoc()): ?>
                <tr>
                    <td>
                        <strong><?= e($r['name']) ?></strong><br>
                        <small class="text-muted">Guardian: <?= e($r['guardian_name']) ?></small>
                    </td>
                    <td>
                        <?= e($r['email']) ?><br>
                        <small class="text-muted"><?= e($r['phone']) ?></small>
                    </td>
                    <td><?= ucfirst($r['gender']) ?></td>
                    <td><span class="badge badge-secondary"><?= e($r['room_preference'] ?: 'Any') ?></span></td>
                    <td><?= formatDate($r['requested_at']) ?></td>
                    <td>
                        <?php if ($statusFilter === 'pending'): ?>
                            <div class="d-flex gap-1">
                                <button class="btn btn-sm btn-success" onclick="openApproveModal(<?= (int)$r['request_id'] ?>, '<?= e($r['name']) ?>')">✅ Approve</button>
                                <button class="btn btn-sm btn-danger" onclick="openRejectModal(<?= (int)$r['request_id'] ?>, '<?= e($r['name']) ?>')">✕ Reject</button>
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
                <tr><td colspan="6" class="text-center" style="padding:30px;">No <?= $statusFilter ?> requests found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ══════════ APPROVE MODAL ══════════ -->
<div class="modal-overlay" id="approveModal">
    <div class="modal-card" style="max-width:400px;">
        <div class="modal-header">
            <h3 class="modal-title">✅ Approve Admission</h3>
            <button class="modal-close" onclick="closeModal('approveModal')">✕</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>actions/admission_action.php">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="request_id" id="approveRequestId">
            <div class="modal-body">
                <p>Approve admission for <strong id="approveName"></strong>?</p>
                <div class="form-group mt-2">
                    <label>Assign Room <span style="color:red">*</span></label>
                    <select name="room_id" class="form-control" required>
                        <option value="">— Select Room —</option>
                        <?php while ($rm = $availRooms->fetch_assoc()): ?>
                            <option value="<?= (int)$rm['room_id'] ?>">Room <?= e($rm['room_number']) ?> (<?= ucfirst($rm['type']) ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('approveModal')">Cancel</button>
                <button type="submit" class="btn btn-success">Approve & Assign Room</button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════ REJECT MODAL ══════════ -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal-card" style="max-width:400px;">
        <div class="modal-header">
            <h3 class="modal-title">✕ Reject Admission</h3>
            <button class="modal-close" onclick="closeModal('rejectModal')">✕</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>actions/admission_action.php">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="request_id" id="rejectRequestId">
            <div class="modal-body">
                <p>Reject admission for <strong id="rejectName"></strong>?</p>
                <div class="form-group mt-2">
                    <label>Reason for Rejection <span style="color:red">*</span></label>
                    <textarea name="reject_reason" class="form-control" rows="2" required placeholder="e.g. No rooms available, invalid data..."></textarea>
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
function openApproveModal(id, name) {
    document.getElementById('approveRequestId').value = id;
    document.getElementById('approveName').innerText = name;
    openModal('approveModal');
}
function openRejectModal(id, name) {
    document.getElementById('rejectRequestId').value = id;
    document.getElementById('rejectName').innerText = name;
    openModal('rejectModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
