<?php
/**
 * complaints.php — Complaint Management
 * Hostel Management System
 */
$pageTitle = 'Complaints';
require_once __DIR__ . '/includes/header.php';

// ── Filters ──
$filterStatus   = trim($_GET['status']   ?? '');
$filterPriority = trim($_GET['priority'] ?? '');

$where  = ['1=1'];
$params = [];
$types  = '';

$validStatuses  = ['open','in_progress','resolved','closed'];
$validPriorities= ['low','medium','high'];

if ($filterStatus !== '' && in_array($filterStatus, $validStatuses)) {
    $where[] = 'c.status = ?'; $params[] = $filterStatus; $types .= 's';
}
if ($filterPriority !== '' && in_array($filterPriority, $validPriorities)) {
    $where[] = 'c.priority = ?'; $params[] = $filterPriority; $types .= 's';
}

$sql = "SELECT c.complaint_id, c.title, c.description, c.category, c.priority,
               c.status, c.created_at, c.resolved_at,
               s.name AS student_name, s.student_code
        FROM complaints c
        JOIN students s ON c.student_id = s.student_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY FIELD(c.priority,'high','medium','low'), c.created_at DESC";

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$complaints = $stmt->get_result();

// ── Active students for dropdown ──
$activeStudents = $conn->query("SELECT student_id, name, student_code FROM students WHERE status='active' ORDER BY name ASC");

function priorityBadge(string $p): string {
    $map = ['high'=>'danger','medium'=>'warning','low'=>'info'];
    $icon= ['high'=>'🔴','medium'=>'🟡','low'=>'🔵'];
    return '<span class="badge badge-'.($map[$p]??'secondary').'">'
          .($icon[$p]??'').' '.ucfirst($p).'</span>';
}
function complaintStatusBadge(string $s): string {
    $map = ['open'=>'danger','in_progress'=>'warning','resolved'=>'success','closed'=>'secondary'];
    return '<span class="badge badge-'.($map[$s]??'secondary').'">'.ucfirst(str_replace('_',' ',$s)).'</span>';
}
?>

<div class="page-header">
    <h1>📋 Complaints</h1>
    <button class="btn btn-primary" onclick="openModal('addComplaintModal')">➕ Add Complaint</button>
</div>

<!-- Filter Bar -->
<form method="GET" action="">
    <div class="filter-bar">
        <select name="status" class="form-control" onchange="this.form.submit()" style="min-width:140px;">
            <option value="">All Status</option>
            <?php foreach (['open'=>'Open','in_progress'=>'In Progress','resolved'=>'Resolved','closed'=>'Closed'] as $v=>$l): ?>
                <option value="<?= $v ?>" <?= $filterStatus===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
        </select>
        <select name="priority" class="form-control" onchange="this.form.submit()" style="min-width:130px;">
            <option value="">All Priority</option>
            <?php foreach (['high'=>'🔴 High','medium'=>'🟡 Medium','low'=>'🔵 Low'] as $v=>$l): ?>
                <option value="<?= $v ?>" <?= $filterPriority===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($filterStatus || $filterPriority): ?>
            <a href="<?= BASE_URL ?>complaints.php" class="btn btn-secondary">✕ Clear</a>
        <?php endif; ?>
    </div>
</form>

<!-- Complaints Table -->
<div class="table-card">
    <div class="table-header">
        <span class="table-title">All Complaints
            <span class="badge badge-info" style="margin-left:6px;"><?= $complaints->num_rows ?></span>
        </span>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Complaint</th>
                    <th>Category</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($complaints->num_rows > 0): ?>
                <?php while ($c = $complaints->fetch_assoc()): ?>
                <tr>
                    <td>
                        <strong><?= e($c['student_name']) ?></strong><br>
                        <small class="text-muted"><?= e($c['student_code']) ?></small>
                    </td>
                    <td style="max-width:240px;">
                        <strong><?= e($c['title']) ?></strong><br>
                        <small class="text-muted"><?= e(mb_strimwidth($c['description'], 0, 80, '…')) ?></small>
                    </td>
                    <td>
                        <span class="badge badge-secondary">
                            <?php
                            $catIcon=['maintenance'=>'🔧','food'=>'🍽️','security'=>'🔒','cleanliness'=>'🧹','other'=>'📌'];
                            echo ($catIcon[$c['category']]??'📌').' '.ucfirst($c['category']);
                            ?>
                        </span>
                    </td>
                    <td><?= priorityBadge($c['priority']) ?></td>
                    <td><?= complaintStatusBadge($c['status']) ?></td>
                    <td style="white-space:nowrap;font-size:.82rem;"><?= formatDate($c['created_at']) ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <?php if (!in_array($c['status'], ['resolved','closed'])): ?>
                            <form method="POST" action="<?= BASE_URL ?>actions/complaint_action.php">
                                <input type="hidden" name="action" value="resolve">
                                <input type="hidden" name="complaint_id" value="<?= (int)$c['complaint_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-success">✅ Resolve</button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" action="<?= BASE_URL ?>actions/complaint_action.php"
                                  onsubmit="return confirmAction('Delete this complaint?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="complaint_id" value="<?= (int)$c['complaint_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="7">
                    <div class="empty-state">
                        <span class="empty-state-icon">📋</span>
                        <div class="empty-state-title">No complaints found</div>
                        <div class="empty-state-msg">All clear! No complaints match your filters.</div>
                    </div>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<!-- ══════════ ADD COMPLAINT MODAL ══════════ -->
<div class="modal-overlay" id="addComplaintModal">
    <div class="modal-card" style="max-width:540px;">
        <div class="modal-header">
            <h3 class="modal-title">➕ Add Complaint</h3>
            <button class="modal-close" onclick="closeModal('addComplaintModal')">✕</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>actions/complaint_action.php">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group form-full">
                        <label>Student <span style="color:red">*</span></label>
                        <select name="student_id" class="form-control" required>
                            <option value="">— Select Student —</option>
                            <?php if ($activeStudents): while ($st = $activeStudents->fetch_assoc()): ?>
                                <option value="<?= (int)$st['student_id'] ?>">
                                    <?= e($st['name']) ?> (<?= e($st['student_code']) ?>)
                                </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Category <span style="color:red">*</span></label>
                        <select name="category" class="form-control" required>
                            <option value="maintenance">🔧 Maintenance</option>
                            <option value="food">🍽️ Food</option>
                            <option value="security">🔒 Security</option>
                            <option value="cleanliness">🧹 Cleanliness</option>
                            <option value="other">📌 Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Priority <span style="color:red">*</span></label>
                        <select name="priority" class="form-control" required>
                            <option value="low">🔵 Low</option>
                            <option value="medium" selected>🟡 Medium</option>
                            <option value="high">🔴 High</option>
                        </select>
                    </div>
                    <div class="form-group form-full">
                        <label>Title <span style="color:red">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="Brief complaint title" required>
                    </div>
                    <div class="form-group form-full">
                        <label>Description <span style="color:red">*</span></label>
                        <textarea name="description" class="form-control" rows="3"
                                  placeholder="Describe the issue in detail…" required></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addComplaintModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">📋 Submit Complaint</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
