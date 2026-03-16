<?php
/**
 * supervisor/complaints.php — Handle Assigned Complaints
 * Hostel Management System
 */
$pageTitle = 'My Complaints';
require_once __DIR__ . '/../includes/header.php';

requireSupervisor();

$userId = $_SESSION['user_id'];

// Fetch complaints assigned to this supervisor
$stmt = $conn->prepare("
    SELECT c.*, s.name as student_name, s.student_code, r.room_number
    FROM complaints c
    JOIN students s ON c.student_id = s.student_id
    LEFT JOIN rooms r ON s.room_id = r.room_id
    WHERE c.assigned_to = ?
    ORDER BY c.created_at DESC
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$complaints = $stmt->get_result();

function compStatusBadge(string $s): string {
    $map = ['open' => 'danger', 'in_progress' => 'warning', 'resolved' => 'success', 'closed' => 'secondary'];
    return '<span class="badge badge-' . ($map[$s] ?? 'secondary') . '">' . str_replace('_', ' ', ucfirst($s)) . '</span>';
}
function priorityTag(string $p): string {
    $colors = ['high' => '#ef4444', 'medium' => '#f59e0b', 'low' => '#3b82f6'];
    $color = $colors[$p] ?? '#94a3b8';
    return '<span style="color:' . $color . '; font-weight:700;">● ' . ucfirst($p) . '</span>';
}
?>

<div class="page-header">
    <h1>📋 Assigned Complaints</h1>
</div>

<div class="table-card">
    <div class="table-header">
        <span class="table-title">My Complaints
            <span class="badge badge-info" style="margin-left:6px;"><?= $complaints->num_rows ?></span>
        </span>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Complaint Details</th>
                    <th>Student Info</th>
                    <th>Priority</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($complaints && $complaints->num_rows > 0): ?>
                <?php while ($c = $complaints->fetch_assoc()): ?>
                <tr>
                    <td>
                        <strong><?= e($c['title']) ?></strong>
                        <span class="badge badge-secondary" style="margin-left:5px;"><?= ucfirst($c['category']) ?></span><br>
                        <small class="text-muted"><?= e(mb_strimwidth($c['description'], 0, 80, '…')) ?></small>
                        <?php if ($c['photo']): ?>
                            <br><a href="<?= BASE_URL . e($c['photo']) ?>" target="_blank" style="font-size:0.8rem;">📎 View Photo</a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?= e($c['student_name']) ?></strong><br>
                        <small class="text-muted">Room: <?= e($c['room_number'] ?: 'N/A') ?></small>
                    </td>
                    <td><?= priorityTag($c['priority']) ?></td>
                    <td><?= formatDate($c['created_at']) ?></td>
                    <td><?= compStatusBadge($c['status']) ?></td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick='openUpdateModal(<?= json_encode([
                            "id" => $c["complaint_id"],
                            "status" => $c["status"]
                        ]) ?>)'>✏️ Update</button>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6" class="text-center" style="padding:40px;">
                    <div class="empty-state">
                        <span class="empty-state-icon">🎉</span>
                        <div class="empty-state-title">No complaints!</div>
                        <div class="empty-state-msg">You have no complaints assigned to you right now.</div>
                    </div>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ══════════ UPDATE COMPLAINT MODAL ══════════ -->
<div class="modal-overlay" id="updateComplaintModal">
    <div class="modal-card" style="max-width:400px;">
        <div class="modal-header">
            <h3 class="modal-title">✏️ Update Complaint Status</h3>
            <button class="modal-close" onclick="closeModal('updateComplaintModal')">✕</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>actions/complaint_action.php">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="complaint_id" id="ucComplaintId">
            <input type="hidden" name="redirect_to" value="supervisor/complaints.php">
            <div class="modal-body">
                <div class="form-group">
                    <label>Status <span style="color:red">*</span></label>
                    <select name="status" id="ucStatus" class="form-control" required>
                        <option value="open">Open</option>
                        <option value="in_progress">In Progress</option>
                        <option value="resolved">Resolved</option>
                        <!-- 'closed' is typically reserved for admin, but we can allow supervisor if needed. Let's include it. -->
                        <option value="closed">Closed</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('updateComplaintModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openUpdateModal(c) {
    document.getElementById('ucComplaintId').value = c.id;
    document.getElementById('ucStatus').value = c.status;
    openModal('updateComplaintModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
