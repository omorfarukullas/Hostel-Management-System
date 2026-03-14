<?php
/**
 * notices.php — Notice Board
 * Hostel Management System
 */
$pageTitle = 'Notices';
require_once __DIR__ . '/includes/header.php';

// ── Fetch notices: pinned first, then newest ──
$notices = $conn->query("
    SELECT n.notice_id, n.title, n.content, n.target_role,
           n.is_pinned, n.created_at, n.expires_at,
           COALESCE(u.name, 'System') AS posted_by_name
    FROM notices n
    LEFT JOIN users u ON n.posted_by = u.user_id
    ORDER BY n.is_pinned DESC, n.created_at DESC
");

function targetBadge(string $t): string {
    $map = ['all'=>'secondary','student'=>'info','warden'=>'success'];
    $labels = ['all'=>'👥 All','student'=>'🎓 Students','warden'=>'🏠 Wardens'];
    return '<span class="badge badge-'.($map[$t]??'secondary').'">'
          .($labels[$t]??ucfirst($t)).'</span>';
}
?>

<div class="page-header">
    <h1>📢 Notice Board</h1>
    <?php if (isAdmin() || isWarden()): ?>
    <button class="btn btn-primary" onclick="openModal('addNoticeModal')">📌 Post Notice</button>
    <?php endif; ?>
</div>

<!-- Notice Cards -->
<?php if ($notices && $notices->num_rows > 0): ?>
    <?php while ($n = $notices->fetch_assoc()):
        $isPinned = (bool)$n['is_pinned'];
        $expired  = !empty($n['expires_at']) && strtotime($n['expires_at']) < time();
    ?>
    <div class="notice-card <?= $isPinned ? 'notice-pinned' : '' ?> <?= $expired ? 'notice-expired' : '' ?>">
        <div class="notice-card-header">
            <div class="notice-card-badges">
                <?php if ($isPinned): ?>
                    <span class="badge badge-warning">📌 Pinned</span>
                <?php endif; ?>
                <?php if ($expired): ?>
                    <span class="badge badge-secondary">⏰ Expired</span>
                <?php endif; ?>
                <?= targetBadge($n['target_role']) ?>
            </div>
            <?php if (isAdmin() || isWarden()): ?>
            <form method="POST" action="<?= BASE_URL ?>actions/notice_action.php"
                  onsubmit="return confirmAction('Delete this notice?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="notice_id" value="<?= (int)$n['notice_id'] ?>">
                <button type="submit" class="btn btn-sm btn-danger">🗑️ Delete</button>
            </form>
            <?php endif; ?>
        </div>
        <h3 class="notice-card-title"><?= e($n['title']) ?></h3>
        <p class="notice-card-content"><?= nl2br(e($n['content'])) ?></p>
        <div class="notice-card-footer">
            <span>📝 Posted by <strong><?= e($n['posted_by_name']) ?></strong></span>
            <span>🕐 <?= formatDate($n['created_at']) ?></span>
            <?php if (!empty($n['expires_at'])): ?>
                <span>⏳ Expires: <?= formatDate($n['expires_at']) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <?php endwhile; ?>
<?php else: ?>
    <div class="empty-state" style="padding:4rem;">
        <span class="empty-state-icon">📢</span>
        <div class="empty-state-title">No notices posted yet</div>
        <div class="empty-state-msg">Post a notice to inform students and staff.</div>
    </div>
<?php endif; ?>


<!-- ══════════ POST NOTICE MODAL ══════════ -->
<div class="modal-overlay" id="addNoticeModal">
    <div class="modal-card" style="max-width:560px;">
        <div class="modal-header">
            <h3 class="modal-title">📌 Post New Notice</h3>
            <button class="modal-close" onclick="closeModal('addNoticeModal')">✕</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>actions/notice_action.php">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group form-full">
                        <label>Title <span style="color:red">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="Notice headline" required>
                    </div>
                    <div class="form-group form-full">
                        <label>Content <span style="color:red">*</span></label>
                        <textarea name="content" class="form-control" rows="4"
                                  placeholder="Write the notice content here…" required></textarea>
                    </div>
                    <div class="form-group">
                        <label>Target Audience</label>
                        <select name="target_role" class="form-control">
                            <option value="all">👥 All</option>
                            <option value="student">🎓 Students Only</option>
                            <option value="warden">🏠 Wardens Only</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Expiry Date <span class="text-muted">(optional)</span></label>
                        <input type="date" name="expires_at" class="form-control">
                    </div>
                    <div class="form-group form-full">
                        <label class="checkbox-label" style="display:flex;align-items:center;gap:.6rem;cursor:pointer;">
                            <input type="checkbox" name="is_pinned" value="1"
                                   style="width:18px;height:18px;accent-color:#3b82f6;">
                            <span>📌 Pin this notice to the top</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addNoticeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">📢 Post Notice</button>
            </div>
        </form>
    </div>
</div>

<style>
/* ── Notice Card Styles ── */
.notice-card {
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    border-left: 4px solid #3b82f6;
    box-shadow: 0 1px 3px rgba(0,0,0,.06), 0 4px 12px rgba(0,0,0,.04);
    padding: 1.25rem 1.5rem;
    margin-bottom: 1rem;
    transition: transform .18s, box-shadow .18s;
}
.notice-card:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(0,0,0,.1); }
.notice-card.notice-pinned { border-left-color: #f59e0b; background: #fffdf5; }
.notice-card.notice-expired { opacity: .65; border-left-color: #94a3b8; }
.notice-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: .75rem;
    gap: .5rem;
    flex-wrap: wrap;
}
.notice-card-badges { display: flex; gap: .4rem; flex-wrap: wrap; align-items: center; }
.notice-card-title { font-size: 1.05rem; font-weight: 700; color: #1e293b; margin-bottom: .5rem; }
.notice-card-content { font-size: .9rem; color: #475569; line-height: 1.6; margin-bottom: 1rem; white-space: pre-wrap; }
.notice-card-footer {
    display: flex;
    gap: 1.25rem;
    font-size: .78rem;
    color: #94a3b8;
    border-top: 1px solid #f1f5f9;
    padding-top: .75rem;
    flex-wrap: wrap;
}
.notice-card-footer strong { color: #64748b; }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
