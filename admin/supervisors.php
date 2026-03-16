<?php
/**
 * admin/supervisors.php
 * Manage supervisor accounts and their hostel block assignments.
 */
$pageTitle = 'Supervisors';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

/* ── Data ─────────────────────────────────────────── */
$supervisors = $conn->query("
    SELECT u.user_id, u.name, u.email, u.phone, u.status, u.created_at,
           s.supervisor_id, s.block_assigned, s.department, s.joined_date
    FROM   users u
    LEFT JOIN supervisors s ON s.user_id = u.user_id
    WHERE  u.role = 'supervisor'
    ORDER  BY u.name ASC
");
?>

<!-- Page header -->
<div class="page-header">
    <h1>👔 Supervisor Management</h1>
    <button class="btn btn-primary" onclick="openModal('modalAddSup')">➕ Add Supervisor</button>
</div>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:1.5rem;">
    <?php
    $total  = $conn->query("SELECT COUNT(*) AS n FROM users WHERE role='supervisor'")->fetch_assoc()['n'];
    $active = $conn->query("SELECT COUNT(*) AS n FROM users WHERE role='supervisor' AND status='active'")->fetch_assoc()['n'];
    ?>
    <div class="stat-card">
        <div class="stat-icon-box stat-icon-blue">👔</div>
        <div class="stat-info"><div class="stat-value"><?= $total ?></div><div class="stat-label">Total Supervisors</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon-box stat-icon-green">✅</div>
        <div class="stat-info"><div class="stat-value"><?= $active ?></div><div class="stat-label">Active</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon-box stat-icon-amber">🏗️</div>
        <div class="stat-info"><div class="stat-value"><?= $total - $active ?></div><div class="stat-label">Inactive</div></div>
    </div>
</div>

<!-- Table -->
<div class="table-card">
    <div class="table-header">
        <span class="table-title">All Supervisors <span class="badge badge-info" style="margin-left:6px"><?= $supervisors->num_rows ?></span></span>
    </div>
    <div class="table-responsive">
        <table id="tblSupervisors">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Assigned Block</th>
                    <th>Department</th>
                    <th>Joined</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($supervisors->num_rows > 0):
                $i = 1;
                while ($sv = $supervisors->fetch_assoc()): ?>
                <tr>
                    <td class="text-muted"><?= $i++ ?></td>
                    <td><strong><?= e($sv['name']) ?></strong></td>
                    <td><?= e($sv['email']) ?></td>
                    <td><?= e($sv['phone'] ?: '—') ?></td>
                    <td>
                        <?php if ($sv['block_assigned']): ?>
                            <span class="badge badge-info"><?= e($sv['block_assigned']) ?></span>
                        <?php else: ?>
                            <span class="text-muted">Unassigned</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($sv['department'] ?: '—') ?></td>
                    <td><?= formatDate($sv['joined_date']) ?></td>
                    <td>
                        <span class="badge badge-<?= $sv['status'] === 'active' ? 'success' : 'secondary' ?>">
                            <?= ucfirst($sv['status']) ?>
                        </span>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-primary"
                                onclick='openEditSup(<?= json_encode([
                                    "user_id"       => $sv["user_id"],
                                    "name"          => $sv["name"],
                                    "phone"         => $sv["phone"],
                                    "block_assigned"=> $sv["block_assigned"],
                                    "department"    => $sv["department"],
                                    "status"        => $sv["status"],
                                ]) ?>)'>✏️ Edit</button>
                            <form method="POST" action="<?= BASE_URL ?>actions/supervisor_action.php"
                                  onsubmit="return confirm('Remove this supervisor from the system?')">
                                <input type="hidden" name="action"  value="delete">
                                <input type="hidden" name="user_id" value="<?= (int)$sv['user_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr>
                    <td colspan="9">
                        <div class="empty-state">
                            <span class="empty-state-icon">👔</span>
                            <div class="empty-state-title">No supervisors yet</div>
                            <div class="empty-state-msg">Add a supervisor to manage hostel blocks.</div>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ══ ADD SUPERVISOR MODAL ══ -->
<div class="modal-overlay" id="modalAddSup">
    <div class="modal-card" style="max-width:580px">
        <div class="modal-header">
            <h3 class="modal-title">➕ Add New Supervisor</h3>
            <button class="modal-close" onclick="closeModal('modalAddSup')">✕</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>actions/supervisor_action.php">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Full Name <span style="color:red">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Rahman Ali">
                    </div>
                    <div class="form-group">
                        <label>Email <span style="color:red">*</span></label>
                        <input type="email" name="email" class="form-control" required placeholder="supervisor@hostel.com">
                    </div>
                    <div class="form-group">
                        <label>Password <span style="color:red">*</span></label>
                        <input type="password" name="password" class="form-control" required autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control" placeholder="01xxxxxxxxx">
                    </div>
                    <div class="form-group">
                        <label>Assigned Block</label>
                        <input type="text" name="block_assigned" class="form-control" placeholder="e.g. Block A, Floor 2">
                    </div>
                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" name="department" class="form-control" placeholder="e.g. Maintenance">
                    </div>
                    <div class="form-group">
                        <label>Joined Date</label>
                        <input type="date" name="joined_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalAddSup')">Cancel</button>
                <button type="submit" class="btn btn-primary">✅ Save Supervisor</button>
            </div>
        </form>
    </div>
</div>

<!-- ══ EDIT SUPERVISOR MODAL ══ -->
<div class="modal-overlay" id="modalEditSup">
    <div class="modal-card" style="max-width:520px">
        <div class="modal-header">
            <h3 class="modal-title">✏️ Edit Supervisor</h3>
            <button class="modal-close" onclick="closeModal('modalEditSup')">✕</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>actions/supervisor_action.php">
            <input type="hidden" name="action"  value="edit">
            <input type="hidden" name="user_id" id="esUserId">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Full Name <span style="color:red">*</span></label>
                        <input type="text" name="name" id="esName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" id="esPhone" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Assigned Block</label>
                        <input type="text" name="block_assigned" id="esBlock" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" name="department" id="esDept" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="esStatus" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>New Password <span class="text-muted">(leave blank to keep)</span></label>
                        <input type="password" name="password" class="form-control" autocomplete="new-password">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalEditSup')">Cancel</button>
                <button type="submit" class="btn btn-primary">💾 Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditSup(sv) {
    document.getElementById('esUserId').value = sv.user_id;
    document.getElementById('esName').value   = sv.name          || '';
    document.getElementById('esPhone').value  = sv.phone         || '';
    document.getElementById('esBlock').value  = sv.block_assigned|| '';
    document.getElementById('esDept').value   = sv.department    || '';
    document.getElementById('esStatus').value = sv.status        || 'active';
    openModal('modalEditSup');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
