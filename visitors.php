<?php
/**
 * visitors.php — Visitor Log
 * Hostel Management System
 */
$pageTitle = 'Visitors';
require_once __DIR__ . '/includes/header.php';

// ── Filter ──
$filterStatus = trim($_GET['status'] ?? '');
$where  = ['1=1'];
$params = [];
$types  = '';
$validStatuses = ['pending','approved','denied','checked_out'];
if ($filterStatus !== '' && in_array($filterStatus, $validStatuses)) {
    $where[] = 'v.status = ?'; $params[] = $filterStatus; $types .= 's';
}

$sql = "SELECT v.visitor_id, v.visitor_name, v.visitor_phone, v.relation,
               v.purpose, v.check_in, v.check_out, v.status,
               s.name AS student_name, s.student_code,
               COALESCE(u.name, '—') AS approved_by_name
        FROM visitors v
        JOIN students s ON v.student_id = s.student_id
        LEFT JOIN users u ON v.approved_by = u.user_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY v.check_in DESC";

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$visitors = $stmt->get_result();

// ── Students for dropdown ──
$allStudents = $conn->query("SELECT student_id, name, student_code FROM students WHERE status='active' ORDER BY name ASC");

function visitorStatusBadge(string $s): string {
    $map = ['pending'=>'warning','approved'=>'success','denied'=>'danger','checked_out'=>'secondary'];
    $icon= ['pending'=>'⏳','approved'=>'✅','denied'=>'❌','checked_out'=>'🚶'];
    return '<span class="badge badge-'.($map[$s]??'secondary').'">'.($icon[$s]??'').' '.ucfirst(str_replace('_',' ',$s)).'</span>';
}
?>

<div class="page-header">
    <h1>👥 Visitor Log</h1>
    <button class="btn btn-primary" onclick="openModal('logVisitorModal')">➕ Log Visitor</button>
</div>

<!-- Filter Bar -->
<form method="GET" action="">
    <div class="filter-bar">
        <select name="status" class="form-control" onchange="this.form.submit()" style="min-width:150px;">
            <option value="">All Status</option>
            <?php foreach (['pending'=>'⏳ Pending','approved'=>'✅ Approved','denied'=>'❌ Denied','checked_out'=>'🚶 Checked Out'] as $v=>$l): ?>
                <option value="<?= $v ?>" <?= $filterStatus===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($filterStatus): ?>
            <a href="<?= BASE_URL ?>visitors.php" class="btn btn-secondary">✕ Clear</a>
        <?php endif; ?>
    </div>
</form>

<!-- Visitors Table -->
<div class="table-card">
    <div class="table-header">
        <span class="table-title">Visitor Records
            <span class="badge badge-info" style="margin-left:6px;"><?= $visitors->num_rows ?></span>
        </span>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Visitor</th>
                    <th>Visiting Student</th>
                    <th>Relation</th>
                    <th>Purpose</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($visitors->num_rows > 0): ?>
                <?php while ($v = $visitors->fetch_assoc()): ?>
                <tr>
                    <td>
                        <strong><?= e($v['visitor_name']) ?></strong><br>
                        <small class="text-muted">📞 <?= e($v['visitor_phone'] ?: '—') ?></small>
                    </td>
                    <td>
                        <?= e($v['student_name']) ?><br>
                        <small class="text-muted"><?= e($v['student_code']) ?></small>
                    </td>
                    <td><?= e($v['relation'] ?: '—') ?></td>
                    <td style="max-width:160px;font-size:.82rem;">
                        <?= e(mb_strimwidth($v['purpose'] ?? '', 0, 60, '…')) ?>
                    </td>
                    <td style="white-space:nowrap;font-size:.82rem;">
                        <?= $v['check_in'] ? e(date('d M Y H:i', strtotime($v['check_in']))) : '—' ?>
                    </td>
                    <td style="white-space:nowrap;font-size:.82rem;">
                        <?= $v['check_out'] ? e(date('d M Y H:i', strtotime($v['check_out']))) : '<span class="text-muted">—</span>' ?>
                    </td>
                    <td><?= visitorStatusBadge($v['status']) ?></td>
                    <td>
                        <div class="d-flex gap-1" style="flex-wrap:wrap;">
                            <?php if ($v['status'] === 'pending'): ?>
                            <form method="POST" action="<?= BASE_URL ?>actions/visitor_action.php">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="visitor_id" value="<?= (int)$v['visitor_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-success">✅ Approve</button>
                            </form>
                            <form method="POST" action="<?= BASE_URL ?>actions/visitor_action.php">
                                <input type="hidden" name="action" value="deny">
                                <input type="hidden" name="visitor_id" value="<?= (int)$v['visitor_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">❌ Deny</button>
                            </form>
                            <?php endif; ?>
                            <?php if ($v['status'] === 'approved' && empty($v['check_out'])): ?>
                            <form method="POST" action="<?= BASE_URL ?>actions/visitor_action.php">
                                <input type="hidden" name="action" value="checkout">
                                <input type="hidden" name="visitor_id" value="<?= (int)$v['visitor_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-warning">🚶 Check Out</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="8">
                    <div class="empty-state">
                        <span class="empty-state-icon">👥</span>
                        <div class="empty-state-title">No visitor records found</div>
                        <div class="empty-state-msg">Log a visitor entry to get started.</div>
                    </div>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<!-- ══════════ LOG VISITOR MODAL ══════════ -->
<div class="modal-overlay" id="logVisitorModal">
    <div class="modal-card" style="max-width:520px;">
        <div class="modal-header">
            <h3 class="modal-title">➕ Log New Visitor</h3>
            <button class="modal-close" onclick="closeModal('logVisitorModal')">✕</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>actions/visitor_action.php">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group form-full">
                        <label>Visiting Student <span style="color:red">*</span></label>
                        <select name="student_id" class="form-control" required>
                            <option value="">— Select Student —</option>
                            <?php if ($allStudents): while ($st = $allStudents->fetch_assoc()): ?>
                                <option value="<?= (int)$st['student_id'] ?>">
                                    <?= e($st['name']) ?> (<?= e($st['student_code']) ?>)
                                </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Visitor Name <span style="color:red">*</span></label>
                        <input type="text" name="visitor_name" class="form-control" placeholder="Full name" required>
                    </div>
                    <div class="form-group">
                        <label>Visitor Phone</label>
                        <input type="text" name="visitor_phone" class="form-control" placeholder="01XXXXXXXXX">
                    </div>
                    <div class="form-group">
                        <label>Relation</label>
                        <input type="text" name="relation" class="form-control" placeholder="e.g. Parent, Friend, Sibling">
                    </div>
                    <div class="form-group form-full">
                        <label>Purpose of Visit</label>
                        <textarea name="purpose" class="form-control" rows="2"
                                  placeholder="Reason for the visit…"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('logVisitorModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">🏷️ Log Visitor</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
