<?php
/**
 * supervisor/tasks.php — View Assigned Tasks
 * Hostel Management System
 */
$pageTitle = 'My Tasks';
require_once __DIR__ . '/../includes/header.php';

requireSupervisor();

$userId = $_SESSION['user_id'];

// Fetch tasks assigned to this supervisor
$stmt = $conn->prepare("
    SELECT t.*, u.name as admin_name
    FROM tasks t
    JOIN users u ON t.assigned_by = u.user_id
    WHERE t.assigned_to = ?
    ORDER BY FIELD(t.status, 'pending', 'in_progress', 'done', 'cancelled'), t.due_date ASC
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$tasks = $stmt->get_result();

function taskStatusBadge(string $s): string {
    $map = ['pending' => 'warning', 'in_progress' => 'info', 'done' => 'success', 'cancelled' => 'secondary'];
    return '<span class="badge badge-' . ($map[$s] ?? 'secondary') . '">' . str_replace('_', ' ', ucfirst($s)) . '</span>';
}
function priorityTag(string $p): string {
    $colors = ['high' => '#ef4444', 'medium' => '#f59e0b', 'low' => '#3b82f6'];
    $color = $colors[$p] ?? '#94a3b8';
    return '<span style="color:' . $color . '; font-weight:700;">● ' . ucfirst($p) . '</span>';
}
?>

<div class="page-header">
    <h1>✅ My Tasks</h1>
</div>

<div class="table-card">
    <div class="table-header">
        <span class="table-title">Assigned by Admin
            <span class="badge badge-info" style="margin-left:6px;"><?= $tasks->num_rows ?></span>
        </span>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Task Details</th>
                    <th>Assigned By</th>
                    <th>Priority</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($tasks && $tasks->num_rows > 0): ?>
                <?php while ($t = $tasks->fetch_assoc()): ?>
                <tr>
                    <td>
                        <strong><?= e($t['title']) ?></strong><br>
                        <small class="text-muted"><?= e($t['description'] ?: 'No additional details') ?></small>
                    </td>
                    <td><?= e($t['admin_name']) ?></td>
                    <td><?= priorityTag($t['priority']) ?></td>
                    <td><?= formatDate($t['due_date']) ?></td>
                    <td><?= taskStatusBadge($t['status']) ?></td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick='openUpdateModal(<?= json_encode([
                            "id" => $t["task_id"],
                            "status" => $t["status"]
                        ]) ?>)'>✏️ Update</button>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6" class="text-center" style="padding:40px;">
                    <div class="empty-state">
                        <span class="empty-state-icon">🎉</span>
                        <div class="empty-state-title">No tasks assigned!</div>
                        <div class="empty-state-msg">You have no tasks assigned to you right now.</div>
                    </div>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ══════════ UPDATE TASK MODAL ══════════ -->
<div class="modal-overlay" id="updateTaskModal">
    <div class="modal-card" style="max-width:400px;">
        <div class="modal-header">
            <h3 class="modal-title">✏️ Update Task Status</h3>
            <button class="modal-close" onclick="closeModal('updateTaskModal')">✕</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>actions/task_action.php">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="task_id" id="utTaskId">
            <input type="hidden" name="redirect_to" value="supervisor/tasks.php">
            <div class="modal-body">
                <div class="form-group">
                    <label>Status <span style="color:red">*</span></label>
                    <select name="status" id="utStatus" class="form-control" required>
                        <option value="pending">Pending</option>
                        <option value="in_progress">In Progress</option>
                        <option value="done">Done</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('updateTaskModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openUpdateModal(t) {
    document.getElementById('utTaskId').value = t.id;
    document.getElementById('utStatus').value = t.status;
    openModal('updateTaskModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
