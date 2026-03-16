<?php
/**
 * student/dashboard.php — Student Dashboard
 * Hostel Management System
 */
$pageTitle = 'Student Dashboard';
require_once __DIR__ . '/../includes/header.php';

requireStudent();

$userId = $_SESSION['user_id'];

// Get student info
$stmt = $conn->prepare("
    SELECT s.*, r.room_number, r.block, r.floor, r.type, r.capacity, r.monthly_fee
    FROM students s
    LEFT JOIN rooms r ON s.room_id = r.room_id
    WHERE s.user_id = ?
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Student profile not found!</div></div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$studentId = $student['student_id'];
$roomId = $student['room_id'];

// Get Roommates
$roommates = [];
if ($roomId) {
    $stmt = $conn->prepare("SELECT name, phone, student_code FROM students WHERE room_id = ? AND student_id != ? AND status = 'active'");
    $stmt->bind_param('ii', $roomId, $studentId);
    $stmt->execute();
    $rmRes = $stmt->get_result();
    while ($rm = $rmRes->fetch_assoc()) {
        $roommates[] = $rm;
    }
}

// Get Supervisor for this block
$supervisor = null;
if (!empty($student['block'])) {
    $stmt = $conn->prepare("
        SELECT u.name, u.phone, s.department 
        FROM supervisors s 
        JOIN users u ON s.user_id = u.user_id 
        WHERE s.block_assigned = ?
    ");
    $stmt->bind_param('s', $student['block']);
    $stmt->execute();
    $supervisor = $stmt->get_result()->fetch_assoc();
}

// Recent Notices
// Target role: 'all' or 'student'
$stmt = $conn->prepare("
    SELECT title, created_at, is_pinned 
    FROM notices 
    WHERE target_role IN ('all', 'student') 
    ORDER BY is_pinned DESC, created_at DESC 
    LIMIT 3
");
$stmt->execute();
$recentNotices = $stmt->get_result();

// My Recent Complaints
$stmt = $conn->prepare("
    SELECT title, status, created_at 
    FROM complaints 
    WHERE student_id = ? 
    ORDER BY created_at DESC 
    LIMIT 3
");
$myComplaints = $stmt->get_result();

// My Complaint Count
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM complaints WHERE student_id = ?");
$stmt->bind_param('i', $studentId);
$stmt->execute();
$complaintCount = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;

// Pending Room Change
$stmt = $conn->prepare("SELECT status FROM room_change_requests WHERE student_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param('i', $studentId);
$stmt->execute();
$lastChangeReq = $stmt->get_result()->fetch_assoc();
?>

<div class="page-header">
    <h1>👋 Welcome, <?= e(explode(' ', $student['name'])[0]) ?>!</h1>
    <span class="badge badge-success" style="font-size:1rem; padding:8px 15px;">
        ID: <?= e($student['student_code']) ?>
    </span>
</div>

<div class="dashboard-grid" style="display:grid; grid-template-columns: 2fr 1fr; gap:20px; align-items: start;">
    
    <!-- LEFT COLUMN -->
    <div style="display:flex; flex-direction:column; gap:20px;">
        
        <!-- Room Info Card -->
        <div class="table-card" style="margin-bottom:0;">
            <div class="table-header" style="background: linear-gradient(135deg, #3b82f6, #2563eb); color:white; border-radius:12px 12px 0 0;">
                <span class="table-title" style="color:white;">🏠 My Accommodation</span>
            </div>
            <div style="padding:20px;">
                <?php if ($roomId): ?>
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:15px; margin-bottom:20px;">
                        <div>
                            <small style="color:#64748b; display:block;">Room Number</small>
                            <strong style="font-size:1.5rem; color:#1e293b;"><?= e($student['room_number']) ?></strong>
                        </div>
                        <div>
                            <small style="color:#64748b; display:block;">Block</small>
                            <strong style="font-size:1.2rem; color:#1e293b;"><?= e($student['block']) ?></strong>
                        </div>
                        <div>
                            <small style="color:#64748b; display:block;">Room Type</small>
                            <strong style="font-size:1.2rem; color:#1e293b;"><?= ucfirst($student['type']) ?> (<?= $student['capacity'] ?> Person)</strong>
                        </div>
                        <div>
                            <small style="color:#64748b; display:block;">Floor</small>
                            <strong style="font-size:1.2rem; color:#1e293b;"><?= $student['floor'] ?></strong>
                        </div>
                    </div>
                    
                    <div style="border-top:1px solid #e2e8f0; padding-top:15px; margin-top:10px;">
                        <h4 style="margin:0 0 10px 0; color:#334155;">👥 Roommates</h4>
                        <?php if (!empty($roommates)): ?>
                            <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:8px;">
                                <?php foreach ($roommates as $rm): ?>
                                    <li style="display:flex; align-items:center; gap:10px; background:#f8fafc; padding:10px; border-radius:8px;">
                                        <div style="width:35px; height:35px; border-radius:50%; background:#e0e7ff; color:#4f46e5; display:flex; align-items:center; justify-content:center; font-weight:bold;">
                                            <?= strtoupper(substr($rm['name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <strong style="display:block; color:#1e293b; font-size:0.95rem;"><?= e($rm['name']) ?></strong>
                                            <small style="color:#64748b;"><?= e($rm['phone']) ?></small>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p style="color:#64748b; margin:0; font-size:0.95rem;">You currently have no roommates.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <span class="empty-state-icon">🏠</span>
                        <div class="empty-state-title">No Room Assigned</div>
                        <div class="empty-state-msg">You have not been assigned to a room yet. Please contact the administrator.</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- My Complaints -->
        <div class="table-card" style="margin-bottom:0;">
            <div class="table-header">
                <span class="table-title">Recent Complaints <span class="badge badge-info" style="margin-left:6px;"><?= $complaintCount ?></span></span>
                <a href="complaints.php" class="btn btn-sm btn-secondary">Report Issue</a>
            </div>
            <div class="table-responsive">
                <table>
                    <tbody>
                    <?php if ($myComplaints && $myComplaints->num_rows > 0): ?>
                        <?php while($c = $myComplaints->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong><?= e($c['title']) ?></strong><br>
                                <small class="text-muted"><?= date('d M Y', strtotime($c['created_at'])) ?></small>
                            </td>
                            <td class="text-right">
                                <span class="badge badge-<?= $c['status'] == 'open' ? 'danger' : ($c['status'] == 'resolved' ? 'success' : 'warning') ?>">
                                    <?= ucfirst(str_replace('_', ' ', $c['status'])) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="2" class="text-center text-muted">No recent complaints.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
    
    <!-- RIGHT COLUMN -->
    <div style="display:flex; flex-direction:column; gap:20px;">
        
        <!-- Block Supervisor -->
        <div class="table-card" style="margin-bottom:0;">
            <div class="table-header">
                <span class="table-title">👔 My Supervisor</span>
            </div>
            <div style="padding:15px;">
                <?php if ($supervisor): ?>
                    <strong style="display:block; font-size:1.1rem; color:#1e293b; margin-bottom:5px;"><?= e($supervisor['name']) ?></strong>
                    <div style="color:#64748b; font-size:0.9rem; margin-bottom:5px;">📞 <?= e($supervisor['phone']) ?></div>
                    <div style="color:#64748b; font-size:0.9rem; margin-bottom:15px;">🏢 <?= e($supervisor['department']) ?></div>
                    <a href="chat.php" class="btn btn-primary btn-sm" style="width:100%; text-align:center;">💬 Send Message</a>
                <?php else: ?>
                    <p style="color:#64748b; margin:0; font-size:0.9rem;">No supervisor assigned to your block yet.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="table-card" style="margin-bottom:0;">
            <div class="table-header">
                <span class="table-title">⚡ Quick Actions</span>
            </div>
            <div style="padding:15px; display:flex; flex-direction:column; gap:10px;">
                <?php if ($lastChangeReq && $lastChangeReq['status'] == 'pending'): ?>
                    <button class="btn btn-secondary" disabled style="opacity:0.7;">🔄 Room Change (Pending)</button>
                <?php else: ?>
                    <a href="room_change.php" class="btn btn-secondary text-center" style="display:block;">🔄 Request Room Change</a>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>fees.php" class="btn btn-secondary text-center" style="display:block;">💳 Pay Fees</a>
            </div>
        </div>

        <!-- Recent Notices -->
        <div class="table-card" style="margin-bottom:0;">
            <div class="table-header">
                <span class="table-title">📢 Latest Notices</span>
                <a href="notices.php" class="btn btn-sm btn-secondary">All</a>
            </div>
            <div style="padding:15px; display:flex; flex-direction:column; gap:10px;">
                <?php if ($recentNotices && $recentNotices->num_rows > 0): ?>
                    <?php while($n = $recentNotices->fetch_assoc()): ?>
                        <div style="border-left:3px solid <?= $n['is_pinned'] ? '#3b82f6' : '#e2e8f0' ?>; padding-left:10px;">
                            <strong style="display:block; font-size:0.95rem; color:#1e293b;">
                                <?= $n['is_pinned'] ? '📌 ' : '' ?> <?= e($n['title']) ?>
                            </strong>
                            <small style="color:#94a3b8;"><?= date('d M Y', strtotime($n['created_at'])) ?></small>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="color:#64748b; margin:0; font-size:0.9rem;">No recent announcements.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
