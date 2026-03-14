<?php
/**
 * fees.php — Fee Management
 * Hostel Management System
 */
$pageTitle = 'Fees';
require_once __DIR__ . '/includes/header.php';

// ── URL filter: specific student ──
$filterStudentId = (int)($_GET['student_id'] ?? 0);
$filterStatus    = trim($_GET['status'] ?? '');

// ── Stat Cards ──
$r = $conn->query("SELECT COALESCE(SUM(amount),0) AS total FROM fees WHERE status='paid'");
$totalPaid = (float)$r->fetch_assoc()['total'];

$r = $conn->query("SELECT COALESCE(SUM(amount),0) AS total FROM fees WHERE status='unpaid'");
$totalUnpaid = (float)$r->fetch_assoc()['total'];

$r = $conn->query("SELECT COALESCE(SUM(amount),0) AS total FROM fees WHERE status='partial'");
$totalPartial = (float)$r->fetch_assoc()['total'];

// ── Build query ──
$where  = ['1=1'];
$params = [];
$types  = '';

if ($filterStudentId > 0) {
    $where[] = 'f.student_id = ?';
    $params[] = $filterStudentId;
    $types .= 'i';
}
$allowedFeeStatuses = ['paid','unpaid','partial'];
if ($filterStatus !== '' && in_array($filterStatus, $allowedFeeStatuses)) {
    $where[] = 'f.status = ?';
    $params[] = $filterStatus;
    $types .= 's';
}

$sql = "SELECT f.fee_id, f.amount, f.fee_month, f.fee_year, f.payment_date,
               f.payment_method, f.status, f.remarks,
               s.name AS student_name, s.student_code,
               COALESCE(r.room_number, '—') AS room_number
        FROM fees f
        JOIN students s ON f.student_id = s.student_id
        LEFT JOIN rooms r ON f.room_id = r.room_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY f.created_at DESC";

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$fees = $stmt->get_result();

// ── Student name for filter title ──
$filterStudentName = '';
if ($filterStudentId > 0) {
    $sn = $conn->query("SELECT name FROM students WHERE student_id = $filterStudentId LIMIT 1");
    if ($sn && $sn->num_rows > 0) $filterStudentName = $sn->fetch_assoc()['name'];
}

// ── Active students for dropdown ──
$activeStudents = $conn->query("
    SELECT s.student_id, s.name, s.student_code,
           COALESCE(r.monthly_fee, 0) AS monthly_fee
    FROM students s
    LEFT JOIN rooms r ON s.room_id = r.room_id
    WHERE s.status = 'active'
    ORDER BY s.name ASC
");

// Months
$months = [
    '01'=>'January','02'=>'February','03'=>'March','04'=>'April',
    '05'=>'May','06'=>'June','07'=>'July','08'=>'August',
    '09'=>'September','10'=>'October','11'=>'November','12'=>'December'
];

function feeStatusBadge(string $s): string {
    $map = ['paid'=>'success','unpaid'=>'danger','partial'=>'warning'];
    return '<span class="badge badge-'.($map[$s]??'secondary').'">'.ucfirst($s).'</span>';
}
?>

<div class="page-header">
    <h1>💰 Fee Management <?= $filterStudentName ? '— '.e($filterStudentName) : '' ?></h1>
    <div class="d-flex gap-1">
        <?php if ($filterStudentId): ?>
            <a href="<?= BASE_URL ?>fees.php" class="btn btn-secondary">✕ Clear Filter</a>
        <?php endif; ?>
        <button class="btn btn-primary" onclick="openModal('addFeeModal')">➕ Add Fee Record</button>
    </div>
</div>

<!-- Stat Cards -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:1.5rem;">
    <div class="stat-card stat-card-green">
        <div class="stat-icon-box stat-icon-green">✅</div>
        <div class="stat-info">
            <div class="stat-value" style="font-size:1.3rem;"><?= formatCurrency($totalPaid) ?></div>
            <div class="stat-label">Total Paid</div>
        </div>
    </div>
    <div class="stat-card stat-card-red">
        <div class="stat-icon-box stat-icon-red">❌</div>
        <div class="stat-info">
            <div class="stat-value" style="font-size:1.3rem;"><?= formatCurrency($totalUnpaid) ?></div>
            <div class="stat-label">Total Unpaid</div>
        </div>
    </div>
    <div class="stat-card stat-card-amber">
        <div class="stat-icon-box stat-icon-amber">⏳</div>
        <div class="stat-info">
            <div class="stat-value" style="font-size:1.3rem;"><?= formatCurrency($totalPartial) ?></div>
            <div class="stat-label">Partial Payments</div>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<form method="GET" action="">
    <?php if ($filterStudentId): ?>
        <input type="hidden" name="student_id" value="<?= $filterStudentId ?>">
    <?php endif; ?>
    <div class="filter-bar">
        <select name="status" class="form-control" onchange="this.form.submit()" style="min-width:150px;">
            <option value="">All Status</option>
            <?php foreach (['paid'=>'Paid','unpaid'=>'Unpaid','partial'=>'Partial'] as $v=>$l): ?>
                <option value="<?= $v ?>" <?= $filterStatus===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($filterStatus): ?>
            <a href="<?= BASE_URL ?>fees.php<?= $filterStudentId?"?student_id=$filterStudentId":'' ?>" class="btn btn-secondary">✕ Clear</a>
        <?php endif; ?>
    </div>
</form>

<!-- Fees Table -->
<div class="table-card">
    <div class="table-header">
        <span class="table-title">Fee Records
            <span class="badge badge-info" style="margin-left:6px;"><?= $fees->num_rows ?></span>
        </span>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Room</th>
                    <th>Period</th>
                    <th>Amount</th>
                    <th>Payment Date</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($fees->num_rows > 0): ?>
                <?php while ($f = $fees->fetch_assoc()): ?>
                <tr>
                    <td>
                        <strong><?= e($f['student_name']) ?></strong><br>
                        <small class="text-muted"><?= e($f['student_code']) ?></small>
                    </td>
                    <td><?= e($f['room_number']) ?></td>
                    <td><?= e($f['fee_month']) ?> <?= e($f['fee_year']) ?></td>
                    <td><strong><?= formatCurrency($f['amount']) ?></strong></td>
                    <td><?= $f['payment_date'] ? formatDate($f['payment_date']) : '<span class="text-muted">—</span>' ?></td>
                    <td>
                        <?php
                        $mBadge = ['cash'=>'secondary','bank_transfer'=>'info','online'=>'purple'];
                        $mLabel = ['cash'=>'💵 Cash','bank_transfer'=>'🏦 Bank','online'=>'💳 Online'];
                        $m = $f['payment_method'];
                        echo '<span class="badge badge-'.($mBadge[$m]??'secondary').'">'.($mLabel[$m]??ucfirst($m)).'</span>';
                        ?>
                    </td>
                    <td><?= feeStatusBadge($f['status']) ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <?php if ($f['status'] !== 'paid'): ?>
                            <form method="POST" action="<?= BASE_URL ?>actions/fee_action.php">
                                <input type="hidden" name="action" value="mark_paid">
                                <input type="hidden" name="fee_id" value="<?= (int)$f['fee_id'] ?>">
                                <input type="hidden" name="redirect_student" value="<?= $filterStudentId ?>">
                                <button type="submit" class="btn btn-sm btn-success">✅ Mark Paid</button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" action="<?= BASE_URL ?>actions/fee_action.php"
                                  onsubmit="return confirmAction('Delete this fee record?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="fee_id" value="<?= (int)$f['fee_id'] ?>">
                                <input type="hidden" name="redirect_student" value="<?= $filterStudentId ?>">
                                <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="8">
                    <div class="empty-state">
                        <span class="empty-state-icon">💰</span>
                        <div class="empty-state-title">No fee records found</div>
                        <div class="empty-state-msg">Add a fee record to get started.</div>
                    </div>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<!-- ══════════ ADD FEE MODAL ══════════ -->
<div class="modal-overlay" id="addFeeModal">
    <div class="modal-card" style="max-width:580px;">
        <div class="modal-header">
            <h3 class="modal-title">➕ Add Fee Record</h3>
            <button class="modal-close" onclick="closeModal('addFeeModal')">✕</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>actions/fee_action.php">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="redirect_student" value="<?= $filterStudentId ?>">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group form-full">
                        <label>Student <span style="color:red">*</span></label>
                        <select name="student_id" class="form-control" required id="feeStudentSel">
                            <option value="">— Select Student —</option>
                            <?php if ($activeStudents): while ($st = $activeStudents->fetch_assoc()): ?>
                                <option value="<?= (int)$st['student_id'] ?>"
                                    data-fee="<?= (float)$st['monthly_fee'] ?>"
                                    <?= $filterStudentId == $st['student_id'] ? 'selected' : '' ?>>
                                    <?= e($st['name']) ?> (<?= e($st['student_code']) ?>)
                                </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Amount (৳) <span style="color:red">*</span></label>
                        <input type="number" name="amount" id="feeAmount" class="form-control" step="0.01" min="0" placeholder="e.g. 3000" required>
                    </div>
                    <div class="form-group">
                        <label>Payment Method</label>
                        <select name="payment_method" class="form-control">
                            <option value="cash">💵 Cash</option>
                            <option value="bank_transfer">🏦 Bank Transfer</option>
                            <option value="online">💳 Online</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Month <span style="color:red">*</span></label>
                        <select name="fee_month" class="form-control" required>
                            <?php foreach ($months as $num => $name): ?>
                                <option value="<?= $name ?>" <?= (int)date('m')==(int)$num?'selected':'' ?>><?= $name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Year <span style="color:red">*</span></label>
                        <input type="number" name="fee_year" class="form-control" value="<?= date('Y') ?>" min="2020" max="2099" required>
                    </div>
                    <div class="form-group">
                        <label>Payment Date</label>
                        <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="paid">Paid</option>
                            <option value="unpaid">Unpaid</option>
                            <option value="partial">Partial</option>
                        </select>
                    </div>
                    <div class="form-group form-full">
                        <label>Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2" placeholder="Optional notes…"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addFeeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">✅ Save Record</button>
            </div>
        </form>
    </div>
</div>

<script>
// Auto-fill amount from student's room monthly fee
document.getElementById('feeStudentSel').addEventListener('change', function() {
    var fee = this.options[this.selectedIndex].getAttribute('data-fee');
    if (fee && parseFloat(fee) > 0) {
        document.getElementById('feeAmount').value = parseFloat(fee).toFixed(2);
    }
});
// Trigger on load if student pre-selected
(function(){
    var sel = document.getElementById('feeStudentSel');
    if (sel && sel.value) sel.dispatchEvent(new Event('change'));
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
