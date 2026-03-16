<?php
/**
 * supervisor/notices.php — Supervisor Notices
 * Hostel Management System
 */
$pageTitle = 'Notices';
require_once __DIR__ . '/../includes/header.php';

requireSupervisor();

// Fetch notices
// Supervisors can see 'all', 'supervisor', and ones they posted. 
// Also let's just show them all notices targeting 'all', 'supervisor', 'student'.
$stmt = $conn->prepare("
    SELECT n.*, u.name as posted_by_name, u.role as posted_by_role
    FROM notices n
    JOIN users u ON n.posted_by = u.user_id
    ORDER BY n.is_pinned DESC, n.created_at DESC
");
$stmt->execute();
$notices = $stmt->get_result();
?>

<div class="page-header" style="display:flex; justify-content:space-between; align-items:center;">
    <h1>📢 Notices & Announcements</h1>
    <button class="btn btn-primary" onclick="openModal('addNoticeModal')">➕ Post Notice</button>
</div>

<div class="notices-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:20px; margin-top:20px;">
    <?php if ($notices && $notices->num_rows > 0): ?>
        <?php while ($n = $notices->fetch_assoc()): ?>
            <div class="notice-card <?= $n['is_pinned'] ? 'pinned-notice' : '' ?>" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; position:relative; <?= $n['is_pinned'] ? 'border-left:4px solid #3b82f6;' : '' ?>">
                
                <?php if ($n['is_pinned']): ?>
                    <div style="position:absolute; top:20px; right:20px; color:#3b82f6;" title="Pinned">📌</div>
                <?php endif; ?>
                
                <h3 style="margin:0 0 10px 0; font-size:1.1rem; padding-right:20px;"><?= e($n['title']) ?></h3>
                
                <div style="font-size:0.85rem; color:#64748b; margin-bottom:15px; display:flex; gap:10px; flex-wrap:wrap;">
                    <span>📅 <?= date('d M Y', strtotime($n['created_at'])) ?></span>
                    <span>👤 <?= e($n['posted_by_name']) ?> <small>(<?= ucfirst($n['posted_by_role']) ?>)</small></span>
                    <?php if ($n['target_role'] !== 'all'): ?>
                        <span class="badge badge-secondary" style="font-size:0.7rem;">Target: <?= ucfirst($n['target_role']) ?>s</span>
                    <?php endif; ?>
                </div>
                
                <div style="font-size:0.95rem; line-height:1.5; color:#334155; margin-bottom:20px;">
                    <?= nl2br(e($n['content'])) ?>
                </div>
                
                <div style="border-top:1px solid #e2e8f0; padding-top:10px; display:flex; justify-content:space-between; align-items:center;">
                    <?php if ($n['posted_by'] == $_SESSION['user_id']): ?>
                        <form method="POST" action="<?= BASE_URL ?>actions/notice_action.php" onsubmit="return confirm('Delete this notice?');" style="margin:0;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="notice_id" value="<?= $n['notice_id'] ?>">
                            <input type="hidden" name="redirect_to" value="supervisor/notices.php">
                            <button type="submit" class="btn btn-sm btn-danger">🗑️ Delete</button>
                        </form>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-state" style="grid-column: 1 / -1;">
            <span class="empty-state-icon">📢</span>
            <div class="empty-state-title">No notices yet</div>
            <div class="empty-state-msg">You haven't posted any announcements.</div>
        </div>
    <?php endif; ?>
</div>

<!-- ══════════ ADD NOTICE MODAL ══════════ -->
<div class="modal-overlay" id="addNoticeModal">
    <div class="modal-card" style="max-width:500px;">
        <div class="modal-header">
            <h3 class="modal-title">➕ Post New Notice</h3>
            <button class="modal-close" onclick="closeModal('addNoticeModal')">✕</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>actions/notice_action.php">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="redirect_to" value="supervisor/notices.php">
            <div class="modal-body">
                <div class="form-group">
                    <label>Title <span style="color:red">*</span></label>
                    <input type="text" name="title" class="form-control" required placeholder="e.g., Water Supply Interruption">
                </div>
                
                <div class="form-group mt-2">
                    <label>Target Audience</label>
                    <select name="target_role" class="form-control">
                        <option value="all">Everyone</option>
                        <option value="student">Students Only</option>
                    </select>
                </div>
                
                <div class="form-group mt-2">
                    <label>Content <span style="color:red">*</span></label>
                    <textarea name="content" class="form-control" rows="5" required placeholder="Write your announcement here..."></textarea>
                </div>
                
                <div class="form-group mt-2" style="display:flex; align-items:center; gap:10px;">
                    <input type="checkbox" name="is_pinned" id="is_pinned" value="1">
                    <label for="is_pinned" style="margin:0; font-weight:normal;">Pin to top</label>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addNoticeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Post Notice</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
