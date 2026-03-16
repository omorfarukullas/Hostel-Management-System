<?php
/**
 * student/notices.php — Student Notices
 * Hostel Management System
 */
$pageTitle = 'Notices';
require_once __DIR__ . '/../includes/header.php';

requireStudent();

$stmt = $conn->prepare("
    SELECT n.*, u.name as posted_by_name, u.role as posted_by_role
    FROM notices n
    JOIN users u ON n.posted_by = u.user_id
    WHERE n.target_role IN ('all', 'student')
    ORDER BY n.is_pinned DESC, n.created_at DESC
");
$stmt->execute();
$notices = $stmt->get_result();
?>

<div class="page-header">
    <h1>📢 Notices & Announcements</h1>
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
                </div>
                
                <div style="font-size:0.95rem; line-height:1.5; color:#334155;">
                    <?= nl2br(e($n['content'])) ?>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-state" style="grid-column: 1 / -1;">
            <span class="empty-state-icon">📢</span>
            <div class="empty-state-title">No notices</div>
            <div class="empty-state-msg">There are no announcements for students right now.</div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
