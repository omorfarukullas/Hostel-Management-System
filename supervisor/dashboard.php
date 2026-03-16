<?php
/**
 * supervisor/dashboard.php — Supervisor Dashboard
 * Hostel Management System
 */
$pageTitle = 'Supervisor Dashboard';
require_once __DIR__ . '/../includes/header.php';

requireSupervisor();

$userId = $_SESSION['user_id'];

// Get supervisor details
$stmt = $conn->prepare("SELECT supervisor_id, block_assigned FROM supervisors WHERE user_id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$supInfo = $stmt->get_result()->fetch_assoc();
$blockAssigned = $supInfo['block_assigned'] ?? '';

// ── Dashboard Stats ──
$stats = [
    'my_students' => 0,
    'open_complaints' => 0,
    'pending_tasks' => 0,
    'pending_room_changes' => 0,
    'block_occupied' => 0,
    'block_capacity' => 0
];

if ($blockAssigned) {
    // Block Occupancy
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(occupied), 0) AS occ, COALESCE(SUM(capacity), 0) AS cap
        FROM rooms
        WHERE block = ?
    ");
    $stmt->bind_param('s', $blockAssigned);
    $stmt->execute();
    $occRes = $stmt->get_result()->fetch_assoc();
    $stats['block_occupied'] = $occRes['occ'];
    $stats['block_capacity'] = $occRes['cap'];
}

// Open Complaints assigned to me OR from my block (simplifying to assigned to me)
$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM complaints WHERE status != 'resolved' AND status != 'closed' AND assigned_to = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$stats['open_complaints'] = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;

// Pending Tasks assigned to me
$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM tasks WHERE status != 'done' AND status != 'cancelled' AND assigned_to = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$stats['pending_tasks'] = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;

// Pending Room changes (if we route them to the block supervisor)
// Let's just find pending requests where the requested room is in my block, OR the current room is in my block.
if ($blockAssigned) {
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT rc.request_id) AS cnt
        FROM room_change_requests rc
        LEFT JOIN rooms r_curr ON rc.current_room_id = r_curr.room_id
        LEFT JOIN rooms r_req ON rc.requested_room_id = r_req.room_id
        WHERE rc.status = 'pending' AND (r_curr.block = ? OR r_req.block = ?)
    ");
    $stmt->bind_param('ss', $blockAssigned, $blockAssigned);
    $stmt->execute();
    $stats['pending_room_changes'] = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
}

// ── Recent Activity ──
// Tasks
$stmt = $conn->prepare("SELECT title, priority, due_date FROM tasks WHERE assigned_to = ? AND status != 'done' ORDER BY due_date ASC LIMIT 5");
$stmt->bind_param('i', $userId);
$stmt->execute();
$recentTasks = $stmt->get_result();

// Complaints
$stmt = $conn->prepare("
    SELECT c.title, c.category, c.status, c.created_at, s.name as student_name
    FROM complaints c
    JOIN students s ON c.student_id = s.student_id
    WHERE c.assigned_to = ? AND c.status != 'resolved'
    ORDER BY c.created_at DESC LIMIT 5
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$recentComplaints = $stmt->get_result();
?>

<div class="page-header">
    <h1>👔 Supervisor Dashboard</h1>
    <span class="badge badge-info" style="font-size:1rem; padding:8px 15px;">
        Block Assigned: <?= e($blockAssigned ?: 'None') ?>
    </span>
</div>

<div class="stats-grid" style="margin-bottom:2rem;">
    <div class="stat-card">
        <div class="stat-icon-box stat-icon-blue">🛏️</div>
        <div class="stat-info">
            <div class="stat-value"><?= $stats['block_occupied'] ?> / <?= $stats['block_capacity'] ?></div>
            <div class="stat-label">Block Occupancy</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon-box stat-icon-red">📋</div>
        <div class="stat-info">
            <div class="stat-value"><?= $stats['open_complaints'] ?></div>
            <div class="stat-label">Open Complaints</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon-box stat-icon-amber">✅</div>
        <div class="stat-info">
            <div class="stat-value"><?= $stats['pending_tasks'] ?></div>
            <div class="stat-label">My Pending Tasks</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon-box stat-icon-green">🔄</div>
        <div class="stat-info">
            <div class="stat-value"><?= $stats['pending_room_changes'] ?></div>
            <div class="stat-label">Room Change Req.</div>
        </div>
    </div>
</div>

<div class="dashboard-grid" style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
    <!-- Recent Complaints -->
    <div class="table-card">
        <div class="table-header">
            <span class="table-title">Recent Complaints</span>
            <a href="complaints.php" class="btn btn-sm btn-secondary">View All</a>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr><th>Title & Student</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php if ($recentComplaints && $recentComplaints->num_rows > 0): ?>
                    <?php while($c = $recentComplaints->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <strong><?= e(mb_strimwidth($c['title'],0,30,'...')) ?></strong><br>
                            <small class="text-muted"><?= e($c['student_name']) ?> • <?= ucfirst($c['category']) ?></small>
                        </td>
                        <td>
                            <span class="badge badge-<?= $c['status'] == 'open' ? 'danger' : 'warning' ?>">
                                <?= ucfirst($c['status']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="2" class="text-center text-muted">No pending complaints assigned to you.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- My Tasks -->
    <div class="table-card">
        <div class="table-header">
            <span class="table-title">My Tasks (To-Do)</span>
            <a href="tasks.php" class="btn btn-sm btn-secondary">View All</a>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr><th>Task</th><th>Due Date</th></tr>
                </thead>
                <tbody>
                <?php if ($recentTasks && $recentTasks->num_rows > 0): ?>
                    <?php while($t = $recentTasks->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <strong><?= e($t['title']) ?></strong><br>
                            <small style="color: <?= $t['priority'] == 'high' ? '#dc2626' : ($t['priority'] == 'medium' ? '#f59e0b' : '#3b82f6') ?>">
                                <?= ucfirst($t['priority']) ?> Priority
                            </small>
                        </td>
                        <td><?= formatDate($t['due_date']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="2" class="text-center text-muted">No pending tasks. Great job!</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
