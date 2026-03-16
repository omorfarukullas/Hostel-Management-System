<?php
/**
 * admin/tasks.php — Task Assignment for Supervisors
 */
$pageTitle = 'Supervisor Tasks';
require_once __DIR__ . '/../includes/header.php';

requireAdmin();

// ── Filters ──
$statusFilter = sanitize($conn, $_GET['status'] ?? '');
$supervisorFilter = (int)($_GET['supervisor'] ?? 0);

$where = ['1=1'];
$params = [];
$types = '';

if ($statusFilter) {
    $where[] = "t.status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}
if ($supervisorFilter) {
    $where[] = "t.assigned_to = ?";
    $params[] = $supervisorFilter;
    $types .= 'i';
}

$sql = "SELECT t.*, u.name AS supervisor_name, u2.name AS admin_name
        FROM tasks t
        JOIN users u ON t.assigned_to = u.user_id
        JOIN users u2 ON t.assigned_by = u2.user_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY t.created_at DESC";

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$tasks = $stmt->get_result();

// ── Supervisors for dropdown ──
$supervisors = $conn->query("SELECT user_id, name FROM users WHERE role = 'supervisor' AND status = 'active' ORDER BY name ASC");

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
    <h1>✅ Supervisor Tasks</h1>
    <button class="btn btn-primary" onclick="openModal('addTaskModal')">➕ Assign Task</button>
</div>

<!-- Filter Bar -->
<div class="table-card" style="margin-bottom:1rem;">
    <form method="GET" class="filter-bar">
        <select name="status" class="form-control" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <?php foreach(['pending', 'in_progress', 'done', 'cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="supervisor" class="form-control" onchange="this.form.submit()">
            <option value="">All Supervisors</option>
            <?php 
            $supervisors->data_seek(0);
            while($sv = $supervisors->fetch_assoc()): ?>
                <option value="<?= $sv['user_id'] ?>" <?= $supervisorFilter === (int)$sv['user_id'] ? 'selected' : '' ?>><?= e($sv['name']) ?></option>
            <?php endwhile; ?>
        </select>
        <a href="tasks.php" class="btn btn-secondary">✕ Clear</a>
    </form>
</div>

<div class="table-card">
    <div class="table-header">
        <span class="table-title">All Tasks
            <span class="badge badge-info" style="margin-left:6px;"><?= $tasks->num_rows ?></span>
        </span>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Task Details</th>
                    <th>Assigned To</th>
                    <th>Priority</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($tasks->num_rows > 0): ?>
                <?php while ($t = $tasks->fetch_assoc()): ?>
                <tr>
                    <td>
                        <strong><?= e($t['title']) ?></strong><br>
                        <small class="text-muted"><?= e(mb_strimwidth($t['description'], 0, 80, '…')) ?></small>
                    </td>
                    <td><strong><?= e($t['supervisor_name']) ?></strong></td>
                    <td><?= priorityTag($t['priority']) ?></td>
                    <td><?= formatDate($t['due_date']) ?></td>
                    <td><?= taskStatusBadge($t['status']) ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-primary" onclick='openEditModal(<?= json_encode($t, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>✏️</button>
                            <form method="POST" action="<?= BASE_URL ?>actions/task_action.php" onsubmit="return confirm('Delete this task?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="task_id" value="<?= (int)$t['task_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6" class="text-center" style="padding:40px;">No tasks found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ══════════ ADD TASK MODAL ══════════ -->
<div class="modal-overlay" id="addTaskModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">➕ Assign New Task</h3>
            <button class="modal-close" onclick="closeModal('addTaskModal')">✕</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>actions/task_action.php">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label>Task Title <span style="color:red">*</span></label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. Inspect Floor 2 Bathrooms" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Assign To <span style="color:red">*</span></label>
                        <select name="assigned_to" class="form-control" required>
                            <option value="">— Select —</option>
                            <?php 
                            $supervisors->data_seek(0);
                            while($sv = $supervisors->fetch_assoc()): ?>
                                <option value="<?= $sv['user_id'] ?>"><?= e($sv['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Priority</label>
                        <select name="priority" class="form-control">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Due Date</label>
                        <input type="date" name="due_date" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addTaskModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Assign Task</button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════ EDIT TASK MODAL ══════════ -->
<div class="modal-overlay" id="editTaskModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">✏️ Edit Task</h3>
            <button class="modal-close" onclick="closeModal('editTaskModal')">✕</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>actions/task_action.php">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="task_id" id="editTaskId">
            <div class="modal-body">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" id="editTitle" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="editDescription" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="editStatus" class="form-control">
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="done">Done</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Priority</label>
                        <select name="priority" id="editPriority" class="form-control">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Due Date</label>
                        <input type="date" name="due_date" id="editDueDate" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editTaskModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Task</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(t) {
    document.getElementById('editTaskId').value = t.task_id;
    document.getElementById('editTitle').value = t.title;
    document.getElementById('editDescription').value = t.description || '';
    document.getElementById('editStatus').value = t.status;
    document.getElementById('editPriority').value = t.priority;
    document.getElementById('editDueDate').value = t.due_date || '';
    openModal('editTaskModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
