<?php
/**
 * admin/repair_costs.php — Track Maintenance Expenses
 */
$pageTitle = 'Repair Costs';
require_once __DIR__ . '/../includes/header.php';

requireAdmin();

// ── Statistics ──
$stats = $conn->query("SELECT SUM(amount) as total, COUNT(*) as count FROM repair_costs")->fetch_assoc();
$monthStats = $conn->query("SELECT SUM(amount) as total FROM repair_costs WHERE MONTH(repair_date) = MONTH(CURRENT_DATE) AND YEAR(repair_date) = YEAR(CURRENT_DATE)")->fetch_assoc();

// ── Fetch Repairs ──
$repairs = $conn->query("
    SELECT r.*, c.category, c.description as complaint_desc
    FROM repair_costs r
    LEFT JOIN complaints c ON r.complaint_id = c.complaint_id
    ORDER BY r.repair_date DESC
");

// ── Pending Complaints for selection ──
$pendingComplaints = $conn->query("SELECT complaint_id, category, SUBSTRING(description, 1, 50) as snippet FROM complaints WHERE status != 'resolved' ORDER BY created_at DESC");
?>

<div class="page-header">
    <h1>🔧 Repair Cost Tracking</h1>
    <button class="btn btn-primary" onclick="openModal('addRepairModal')">➕ Record Expense</button>
</div>

<!-- Stats Bar -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon-box stat-icon-blue">💰</div>
        <div class="stat-info">
            <div class="stat-value"><?= formatCurrency($stats['total'] ?? 0) ?></div>
            <div class="stat-label">Total Repair Cost</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon-box stat-icon-green">📅</div>
        <div class="stat-info">
            <div class="stat-value"><?= formatCurrency($monthStats['total'] ?? 0) ?></div>
            <div class="stat-label">Cost This Month</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon-box stat-icon-amber">🛠️</div>
        <div class="stat-info">
            <div class="stat-value"><?= (int)$stats['count'] ?></div>
            <div class="stat-label">Total Records</div>
        </div>
    </div>
</div>

<div class="table-card">
    <div class="table-header">
        <span class="table-title">Repair History</span>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Item / Description</th>
                    <th>Linked Complaint</th>
                    <th>Repair Date</th>
                    <th>Vendor</th>
                    <th>Cost</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($repairs->num_rows > 0): ?>
                <?php while ($r = $repairs->fetch_assoc()): ?>
                <tr>
                    <td>
                        <strong><?= e($r['title']) ?></strong><br>
                        <small class="text-muted"><?= e($r['description'] ?: 'No details') ?></small>
                    </td>
                    <td>
                        <?php if ($r['complaint_id']): ?>
                            <span class="badge badge-info"><?= ucfirst($r['category']) ?></span><br>
                            <small class="text-muted">#<?= (int)$r['complaint_id'] ?></small>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= formatDate($r['repair_date']) ?></td>
                    <td><?= e($r['vendor_name'] ?: '—') ?></td>
                    <td><strong class="text-danger"><?= formatCurrency($r['amount']) ?></strong></td>
                    <td>
                        <div class="d-flex gap-1">
                            <?php if ($r['receipt_photo']): ?>
                                <a href="<?= BASE_URL . e($r['receipt_photo']) ?>" target="_blank" class="btn btn-sm btn-secondary" title="View Receipt">📄</a>
                            <?php endif; ?>
                            <form method="POST" action="<?= BASE_URL ?>actions/repair_action.php" onsubmit="return confirm('Delete this record?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="cost_id" value="<?= (int)$r['cost_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6" class="text-center" style="padding:40px;">No repair records found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ══════════ ADD REPAIR MODAL ══════════ -->
<div class="modal-overlay" id="addRepairModal">
    <div class="modal-card" style="max-width:560px;">
        <div class="modal-header">
            <h3 class="modal-title">➕ Record Repair Expense</h3>
            <button class="modal-close" onclick="closeModal('addRepairModal')">✕</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>actions/repair_action.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group form-full">
                        <label>Item / Service Name <span style="color:red">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Broken Pipe Repair" required>
                    </div>
                    <div class="form-group form-full">
                        <label>Select Linked Complaint <small class="text-muted">(Optional)</small></label>
                        <select name="complaint_id" class="form-control">
                            <option value="">— Not linked —</option>
                            <?php while($c = $pendingComplaints->fetch_assoc()): ?>
                                <option value="<?= $c['complaint_id'] ?>">[<?= ucfirst($c['category']) ?>] #<?= $c['complaint_id'] ?> - <?= e($c['snippet']) ?>...</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Cost (৳) <span style="color:red">*</span></label>
                        <input type="number" name="amount" class="form-control" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label>Repair Date <span style="color:red">*</span></label>
                        <input type="date" name="repair_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group form-full">
                        <label>Vendor / Technician Details</label>
                        <input type="text" name="vendor_name" class="form-control" placeholder="e.g. John's Plumbing (Phone: ...)">
                    </div>
                    <div class="form-group form-full">
                        <label>Receipt Photo <small class="text-muted">(JPG/PNG)</small></label>
                        <input type="file" name="receipt_photo" class="form-control" accept="image/*">
                    </div>
                    <div class="form-group form-full">
                        <label>Additional Notes</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addRepairModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Expense</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
