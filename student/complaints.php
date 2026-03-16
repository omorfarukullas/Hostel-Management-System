<?php
/**
 * student/complaints.php — Submit and Track Complaints (with Photo)
 * Hostel Management System
 */
$pageTitle = 'My Complaints';
require_once __DIR__ . '/../includes/header.php';

requireStudent();

$userId = $_SESSION['user_id'];

// Get student id & block & supervisor
$stmt = $conn->prepare("
    SELECT s.student_id, r.block, sup.user_id as supervisor_user_id
    FROM students s
    LEFT JOIN rooms r ON s.room_id = r.room_id
    LEFT JOIN supervisors sup ON r.block = sup.block_assigned
    WHERE s.user_id = ?
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$stInfo = $stmt->get_result()->fetch_assoc();
$studentId = $stInfo['student_id'] ?? 0;
$supervisorUserId = $stInfo['supervisor_user_id'] ?? null;

// Fetch my complaints
$stmt = $conn->prepare("SELECT * FROM complaints WHERE student_id = ? ORDER BY created_at DESC");
$stmt->bind_param('i', $studentId);
$stmt->execute();
$complaints = $stmt->get_result();

function compStatusBadge(string $s): string {
    $map = ['open' => 'danger', 'in_progress' => 'warning', 'resolved' => 'success', 'closed' => 'secondary'];
    return '<span class="badge badge-' . ($map[$s] ?? 'secondary') . '">' . str_replace('_', ' ', ucfirst($s)) . '</span>';
}
function priorityTag(string $p): string {
    $colors = ['high' => '#ef4444', 'medium' => '#f59e0b', 'low' => '#3b82f6'];
    $color = $colors[$p] ?? '#94a3b8';
    return '<span style="color:' . $color . '; font-weight:700;">● ' . ucfirst($p) . '</span>';
}
?>

<div class="page-header" style="display:flex; justify-content:space-between; align-items:center;">
    <h1>📋 My Complaints</h1>
    <button class="btn btn-primary" onclick="openModal('addComplaintModal')">➕ Report Issue</button>
</div>

<div class="table-card">
    <div class="table-header">
        <span class="table-title">Complaint History
            <span class="badge badge-info" style="margin-left:6px;"><?= $complaints ? $complaints->num_rows : 0 ?></span>
        </span>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Issue Title</th>
                    <th>Category</th>
                    <th>Priority</th>
                    <th>Date Reported</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($complaints && $complaints->num_rows > 0): ?>
                <?php while ($c = $complaints->fetch_assoc()): ?>
                <tr>
                    <td>
                        <strong><?= e($c['title']) ?></strong><br>
                        <small class="text-muted"><?= e(mb_strimwidth($c['description'], 0, 60, '…')) ?></small>
                        <?php if ($c['photo']): ?>
                            <br><a href="<?= BASE_URL . e($c['photo']) ?>" target="_blank" style="font-size:0.8rem; color:#3b82f6;">📎 View Attached Photo</a>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge badge-secondary"><?= ucfirst($c['category']) ?></span></td>
                    <td><?= priorityTag($c['priority']) ?></td>
                    <td><?= formatDate($c['created_at']) ?></td>
                    <td><?= compStatusBadge($c['status']) ?></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5" class="text-center" style="padding:40px;">
                    <div class="empty-state">
                        <span class="empty-state-icon">🎉</span>
                        <div class="empty-state-title">No issues reporter!</div>
                        <div class="empty-state-msg">You haven't filed any complaints.</div>
                    </div>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ══════════ ADD COMPLAINT MODAL ══════════ -->
<div class="modal-overlay" id="addComplaintModal">
    <div class="modal-card" style="max-width:500px;">
        <div class="modal-header">
            <h3 class="modal-title">➕ Report a New Issue</h3>
            <button class="modal-close" onclick="closeModal('addComplaintModal')">✕</button>
        </div>
        <!-- IMPORTANT: enctype for file upload -->
        <form method="POST" action="<?= BASE_URL ?>actions/student_portal_action.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_complaint">
            <input type="hidden" name="student_id" value="<?= $studentId ?>">
            <input type="hidden" name="assigned_to" value="<?= $supervisorUserId ?>">
            
            <div class="modal-body">
                <div class="form-group">
                    <label>Issue Title <span style="color:red">*</span></label>
                    <input type="text" name="title" class="form-control" required placeholder="e.g. Leaking Faucet">
                </div>
                
                <div class="form-grid" style="grid-template-columns:1fr 1fr; gap:15px; margin-top:15px;">
                    <div class="form-group">
                        <label>Category <span style="color:red">*</span></label>
                        <select name="category" class="form-control" required>
                            <option value="maintenance">Maintenance</option>
                            <option value="plumbing">Plumbing</option>
                            <option value="electricity">Electricity</option>
                            <option value="noise">Noise</option>
                            <option value="security">Security</option>
                            <option value="cleanliness">Cleanliness</option>
                            <option value="food">Food</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Priority <span style="color:red">*</span></label>
                        <select name="priority" class="form-control" required>
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                </div>

                <div class="form-group mt-2">
                    <label>Description <span style="color:red">*</span></label>
                    <textarea name="description" class="form-control" rows="3" required placeholder="Provide details..."></textarea>
                </div>
                
                <div class="form-group mt-2">
                    <label>Attach Photo (Optional)</label>
                    <input type="file" name="photo" class="form-control" accept="image/jpeg, image/png, image/jpg">
                    <small style="color:#64748b;">Max size: 2MB. JPG or PNG only.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addComplaintModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Report</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
