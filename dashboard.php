<?php
/**
 * dashboard.php — Admin Dashboard
 * Hostel Management System
 */
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

// ── Stats Queries ──
// 1. Total active students
$r = $conn->query("SELECT COUNT(*) AS cnt FROM students WHERE status = 'active'");
$totalStudents = (int)$r->fetch_assoc()['cnt'];

// 2. Available rooms / total rooms
$r = $conn->query("SELECT COUNT(*) AS total, SUM(status = 'available') AS avail FROM rooms");
$roomRow = $r->fetch_assoc();
$totalRooms = (int)$roomRow['total'];
$availRooms = (int)$roomRow['avail'];

// 3. Total fees collected (paid)
$r = $conn->query("SELECT COALESCE(SUM(amount), 0) AS total FROM fees WHERE status = 'paid'");
$totalFees = (float)$r->fetch_assoc()['total'];

// 4. Open complaints
$r = $conn->query("SELECT COUNT(*) AS cnt FROM complaints WHERE status = 'open'");
$openComplaints = (int)$r->fetch_assoc()['cnt'];

// ── Recent Students (latest 5) ──
$recentStudents = $conn->query("
    SELECT s.student_code, s.name, s.status, s.created_at,
           COALESCE(r.room_number, '—') AS room_number
    FROM students s
    LEFT JOIN rooms r ON s.room_id = r.room_id
    ORDER BY s.created_at DESC
    LIMIT 5
");

// ── Room Occupancy ──
$roomOccupancy = $conn->query("
    SELECT room_number, floor, type, capacity, occupied, status
    FROM rooms
    ORDER BY floor ASC, room_number ASC
");

// ── Status badge helper ──
function statusBadge(string $status): string {
    $map = [
        'active'      => 'success',
        'available'   => 'success',
        'checked_out' => 'secondary',
        'suspended'   => 'danger',
        'full'        => 'danger',
        'maintenance' => 'warning',
    ];
    $cls = $map[$status] ?? 'secondary';
    return '<span class="badge badge-' . $cls . '">' . ucfirst(str_replace('_', ' ', $status)) . '</span>';
}
?>

<!-- Welcome Banner -->
<div class="welcome-card">
    <h2>Welcome back, <?= e($_SESSION['name']) ?>! 👋</h2>
    <p>Here's what's happening at HostelMS today — <?= date('l, d F Y') ?></p>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card stat-card-blue">
        <div class="stat-icon-box stat-icon-blue">👨‍🎓</div>
        <div class="stat-info">
            <div class="stat-value"><?= $totalStudents ?></div>
            <div class="stat-label">Active Students</div>
        </div>
    </div>
    <div class="stat-card stat-card-green">
        <div class="stat-icon-box stat-icon-green">🛏️</div>
        <div class="stat-info">
            <div class="stat-value"><?= $availRooms ?> <span style="font-size:1rem;color:#64748b;">/ <?= $totalRooms ?></span></div>
            <div class="stat-label">Available Rooms</div>
        </div>
    </div>
    <div class="stat-card stat-card-amber">
        <div class="stat-icon-box stat-icon-amber">💰</div>
        <div class="stat-info">
            <div class="stat-value" style="font-size:1.35rem;"><?= formatCurrency($totalFees) ?></div>
            <div class="stat-label">Total Fees Collected</div>
        </div>
    </div>
    <div class="stat-card stat-card-red">
        <div class="stat-icon-box stat-icon-red">📋</div>
        <div class="stat-info">
            <div class="stat-value"><?= $openComplaints ?></div>
            <div class="stat-label">Open Complaints</div>
        </div>
    </div>
</div>

<!-- Bottom 2-column section -->
<div class="dashboard-grid">

    <!-- Left: Recent Students -->
    <div class="table-card">
        <div class="table-header">
            <span class="table-title">👨‍🎓 Recent Students</span>
            <a href="<?= BASE_URL ?>students.php" class="btn btn-sm btn-primary">View All</a>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Room</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($recentStudents && $recentStudents->num_rows > 0): ?>
                    <?php while ($s = $recentStudents->fetch_assoc()): ?>
                    <tr>
                        <td><code style="font-size:0.78rem;color:#3b82f6;"><?= e($s['student_code']) ?></code></td>
                        <td><strong><?= e($s['name']) ?></strong></td>
                        <td><?= e($s['room_number']) ?></td>
                        <td><?= statusBadge($s['status']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4">
                            <div class="empty-state">
                                <span class="empty-state-icon">🎓</span>
                                <div class="empty-state-title">No students yet</div>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right: Room Occupancy -->
    <div class="table-card">
        <div class="table-header">
            <span class="table-title">🛏️ Room Occupancy</span>
            <a href="<?= BASE_URL ?>rooms.php" class="btn btn-sm btn-primary">Manage</a>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Room</th>
                        <th>Type</th>
                        <th>Occupancy</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($roomOccupancy && $roomOccupancy->num_rows > 0): ?>
                    <?php while ($rm = $roomOccupancy->fetch_assoc()):
                        $pct = $rm['capacity'] > 0 ? round(($rm['occupied'] / $rm['capacity']) * 100) : 0;
                        $barClass = $pct >= 100 ? 'full' : ($pct >= 80 ? 'high' : '');
                    ?>
                    <tr>
                        <td><strong><?= e($rm['room_number']) ?></strong> <small class="text-muted">F<?= e($rm['floor']) ?></small></td>
                        <td><span class="badge badge-info"><?= e(ucfirst($rm['type'])) ?></span></td>
                        <td>
                            <div class="occupancy-label" style="margin-bottom:3px;">
                                <span><?= e($rm['occupied']) ?>/<?= e($rm['capacity']) ?></span>
                                <span><?= $pct ?>%</span>
                            </div>
                            <div class="occupancy-bar">
                                <div class="occupancy-fill <?= $barClass ?>" style="width:<?= $pct ?>%"></div>
                            </div>
                        </td>
                        <td><?= statusBadge($rm['status']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4">
                            <div class="empty-state">
                                <span class="empty-state-icon">🛏️</span>
                                <div class="empty-state-title">No rooms yet</div>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
