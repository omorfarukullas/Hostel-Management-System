<?php
/**
 * reports.php — Admin Reports
 * Hostel Management System
 */
$pageTitle = 'Reports';
require_once __DIR__ . '/includes/header.php';

// Admin-only page
requireAdmin();

// ── Section 1: Monthly Fee Collection (last 12 months) ──
$monthlyFees = $conn->query("
    SELECT fee_month, fee_year,
           SUM(amount)  AS total_collected,
           COUNT(*)     AS transactions
    FROM fees
    WHERE status = 'paid'
    GROUP BY fee_year, fee_month
    ORDER BY fee_year DESC,
             FIELD(fee_month,
                'December','November','October','September','August','July',
                'June','May','April','March','February','January')
    LIMIT 12
");

// ── Section 2: Room Utilization ──
$roomUtil = $conn->query("
    SELECT room_id, room_number, floor, type, capacity, occupied, monthly_fee, status
    FROM rooms
    ORDER BY floor ASC, room_number ASC
");

// ── Section 3: Unpaid Fee Records ──
$unpaidFees = $conn->query("
    SELECT f.fee_id, f.amount, f.fee_month, f.fee_year,
           s.name AS student_name, s.student_code,
           COALESCE(r.room_number, '—') AS room_number
    FROM fees f
    JOIN students s ON f.student_id = s.student_id
    LEFT JOIN rooms r ON f.room_id = r.room_id
    WHERE f.status = 'unpaid'
    ORDER BY s.name ASC, f.fee_year DESC,
             FIELD(f.fee_month,
                'December','November','October','September','August','July',
                'June','May','April','March','February','January')
");

// Totals for summary
$r         = $conn->query("SELECT COALESCE(SUM(amount),0) AS t FROM fees WHERE status='paid'");
$grandPaid = (float)$r->fetch_assoc()['t'];
$r         = $conn->query("SELECT COALESCE(SUM(amount),0) AS t FROM fees WHERE status='unpaid'");
$grandUnpaid = (float)$r->fetch_assoc()['t'];
?>

<!-- Summary Cards -->
<div class="page-header">
    <h1>📊 Reports</h1>
    <small class="text-muted">Generated: <?= date('d M Y, H:i') ?></small>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:2rem;">
    <div class="stat-card stat-card-green">
        <div class="stat-icon-box stat-icon-green">✅</div>
        <div class="stat-info">
            <div class="stat-value" style="font-size:1.2rem;"><?= formatCurrency($grandPaid) ?></div>
            <div class="stat-label">Total Collected</div>
        </div>
    </div>
    <div class="stat-card stat-card-red">
        <div class="stat-icon-box stat-icon-red">❌</div>
        <div class="stat-info">
            <div class="stat-value" style="font-size:1.2rem;"><?= formatCurrency($grandUnpaid) ?></div>
            <div class="stat-label">Total Outstanding</div>
        </div>
    </div>
    <div class="stat-card stat-card-blue">
        <div class="stat-icon-box stat-icon-blue">📈</div>
        <div class="stat-info">
            <?php
            $rate = ($grandPaid + $grandUnpaid) > 0
                  ? round($grandPaid / ($grandPaid + $grandUnpaid) * 100, 1)
                  : 0;
            ?>
            <div class="stat-value"><?= $rate ?>%</div>
            <div class="stat-label">Collection Rate</div>
        </div>
    </div>
</div>


<!-- ══════════ SECTION 1: Monthly Fee Collection ══════════ -->
<div class="table-card" style="margin-bottom:2rem;">
    <div class="table-header">
        <span class="table-title">📅 Monthly Fee Collection <small class="text-muted">(Last 12 months — paid only)</small></span>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Month</th>
                    <th>Year</th>
                    <th>Total Collected</th>
                    <th>Transactions</th>
                    <th>Progress</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($monthlyFees && $monthlyFees->num_rows > 0):
                // Find max for relative bar sizing
                $rows = $monthlyFees->fetch_all(MYSQLI_ASSOC);
                $maxAmt = max(array_column($rows, 'total_collected')) ?: 1;
                $i = 1;
                foreach ($rows as $mf):
                    $pct = round(($mf['total_collected'] / $maxAmt) * 100);
            ?>
                <tr>
                    <td class="text-muted"><?= $i++ ?></td>
                    <td><strong><?= e($mf['fee_month']) ?></strong></td>
                    <td><?= e($mf['fee_year']) ?></td>
                    <td><strong style="color:#15803d;"><?= formatCurrency($mf['total_collected']) ?></strong></td>
                    <td><span class="badge badge-info"><?= (int)$mf['transactions'] ?> records</span></td>
                    <td style="min-width:120px;">
                        <div class="occupancy-bar">
                            <div class="occupancy-fill" style="width:<?= $pct ?>%;background:linear-gradient(90deg,#22c55e,#16a34a);"></div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            <?php if (!$monthlyFees || $monthlyFees->num_rows === 0): ?>
                <tr><td colspan="6">
                    <div class="empty-state">
                        <span class="empty-state-icon">📅</span>
                        <div class="empty-state-title">No paid fee records yet</div>
                    </div>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<!-- ══════════ SECTION 2: Room Utilization ══════════ -->
<div class="table-card" style="margin-bottom:2rem;">
    <div class="table-header">
        <span class="table-title">🛏️ Room Utilization</span>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Room No.</th>
                    <th>Floor</th>
                    <th>Type</th>
                    <th>Occupancy</th>
                    <th>Fill %</th>
                    <th>Monthly Fee</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($roomUtil && $roomUtil->num_rows > 0):
                while ($rm = $roomUtil->fetch_assoc()):
                    $pct      = $rm['capacity'] > 0 ? round(($rm['occupied'] / $rm['capacity']) * 100) : 0;
                    $barClass = $pct >= 100 ? 'full' : ($pct >= 75 ? 'high' : '');
                    $statusMap= ['available'=>'success','full'=>'danger','maintenance'=>'warning'];
                    $sCls     = $statusMap[$rm['status']] ?? 'secondary';
            ?>
                <tr>
                    <td><strong><?= e($rm['room_number']) ?></strong></td>
                    <td><span class="badge badge-secondary">F<?= (int)$rm['floor'] ?></span></td>
                    <td><span class="badge badge-info"><?= e(ucfirst($rm['type'])) ?></span></td>
                    <td><?= (int)$rm['occupied'] ?> / <?= (int)$rm['capacity'] ?></td>
                    <td style="min-width:140px;">
                        <div class="occupancy-label" style="margin-bottom:3px;">
                            <span style="font-size:.78rem;"><?= $pct ?>%</span>
                        </div>
                        <div class="occupancy-bar">
                            <div class="occupancy-fill <?= $barClass ?>" style="width:<?= $pct ?>%"></div>
                        </div>
                    </td>
                    <td><?= formatCurrency($rm['monthly_fee']) ?></td>
                    <td><span class="badge badge-<?= $sCls ?>"><?= ucfirst($rm['status']) ?></span></td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="7">
                    <div class="empty-state">
                        <span class="empty-state-icon">🛏️</span>
                        <div class="empty-state-title">No rooms to display</div>
                    </div>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<!-- ══════════ SECTION 3: Unpaid Fee Records ══════════ -->
<div class="table-card">
    <div class="table-header">
        <span class="table-title">⚠️ Unpaid Fee Records
            <?php if ($unpaidFees): ?>
            <span class="badge badge-danger" style="margin-left:6px;"><?= $unpaidFees->num_rows ?></span>
            <?php endif; ?>
        </span>
        <?php if ($grandUnpaid > 0): ?>
        <span style="font-size:.85rem;font-weight:600;color:#dc2626;">
            Total Outstanding: <?= formatCurrency($grandUnpaid) ?>
        </span>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Code</th>
                    <th>Room</th>
                    <th>Period</th>
                    <th>Amount Due</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($unpaidFees && $unpaidFees->num_rows > 0):
                while ($uf = $unpaidFees->fetch_assoc()):
            ?>
                <tr>
                    <td><strong><?= e($uf['student_name']) ?></strong></td>
                    <td><code style="color:#3b82f6;font-size:.78rem;"><?= e($uf['student_code']) ?></code></td>
                    <td><?= e($uf['room_number']) ?></td>
                    <td><?= e($uf['fee_month']) ?> <?= e($uf['fee_year']) ?></td>
                    <td>
                        <strong style="color:#dc2626;font-size:1rem;"><?= formatCurrency($uf['amount']) ?></strong>
                    </td>
                    <td>
                        <form method="POST" action="<?= BASE_URL ?>actions/fee_action.php" style="display:inline;">
                            <input type="hidden" name="action" value="mark_paid">
                            <input type="hidden" name="fee_id" value="<?= (int)$uf['fee_id'] ?>">
                            <input type="hidden" name="redirect_student" value="0">
                            <button type="submit" class="btn btn-sm btn-success">✅ Mark Paid</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="6">
                    <div class="empty-state">
                        <span class="empty-state-icon">🎉</span>
                        <div class="empty-state-title">All fees are paid!</div>
                        <div class="empty-state-msg">No outstanding records found.</div>
                    </div>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
