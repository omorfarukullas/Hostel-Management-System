<?php
/**
 * supervisor/chat.php — Supervisor Chat Interface
 * Hostel Management System
 */
$pageTitle = 'Chat with Students';
require_once __DIR__ . '/../includes/header.php';

requireSupervisor();

$userId = $_SESSION['user_id'];

// Get supervisor info
$stmt = $conn->prepare("SELECT supervisor_id, block_assigned FROM supervisors WHERE user_id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$sup = $stmt->get_result()->fetch_assoc();
$supervisorId = $sup['supervisor_id'] ?? 0;
$blockAssigned = $sup['block_assigned'] ?? '';

// Get all students in this block
$students = [];
if ($blockAssigned) {
    $stmt = $conn->prepare("
        SELECT s.student_id, s.name, s.student_code, r.room_number,
               (SELECT COUNT(*) FROM chat_messages cm WHERE cm.student_id = s.student_id AND cm.supervisor_id = ? AND cm.sender_role = 'student' AND cm.is_read = 0) as unread_count
        FROM students s
        JOIN rooms r ON s.room_id = r.room_id
        WHERE r.block = ? AND s.status = 'active'
        ORDER BY unread_count DESC, s.name ASC
    ");
    $stmt->bind_param('is', $supervisorId, $blockAssigned);
    $stmt->execute();
    $studentsList = $stmt->get_result();
    while ($st = $studentsList->fetch_assoc()) {
        $students[] = $st;
    }
}

$activeStudentId = (int)($_GET['student_id'] ?? 0);
$activeStudent = null;
$messages = null;

if ($activeStudentId > 0 && $supervisorId > 0) {
    // Find active student name
    foreach ($students as $s) {
        if ($s['student_id'] == $activeStudentId) {
            $activeStudent = $s;
            break;
        }
    }
    
    // If student not found in active list, fetch their basic info
    if (!$activeStudent) {
        $stmt = $conn->prepare("SELECT s.student_id, s.name, s.student_code, r.room_number FROM students s LEFT JOIN rooms r ON s.room_id = r.room_id WHERE s.student_id = ?");
        $stmt->bind_param('i', $activeStudentId);
        $stmt->execute();
        $activeStudent = $stmt->get_result()->fetch_assoc();
    }
    
    if ($activeStudent) {
        // Mark as read
        $conn->query("UPDATE chat_messages SET is_read = 1 WHERE student_id = $activeStudentId AND supervisor_id = $supervisorId AND sender_role = 'student' AND is_read = 0");
        
        // Fetch messages
        $stmt = $conn->prepare("
            SELECT * FROM chat_messages 
            WHERE student_id = ? AND supervisor_id = ?
            ORDER BY sent_at ASC
        ");
        $stmt->bind_param('ii', $activeStudentId, $supervisorId);
        $stmt->execute();
        $messages = $stmt->get_result();
    }
}
?>

<div class="page-header">
    <h1>💬 Communication</h1>
</div>

<div class="chat-layout" style="display:flex; height: 75vh; background:#fff; border-radius:12px; border:1px solid #e2e8f0; overflow:hidden;">
    
    <!-- Sidebar: Student List -->
    <div class="chat-sidebar" style="width:300px; border-right:1px solid #e2e8f0; display:flex; flex-direction:column;">
        <div style="padding:15px; border-bottom:1px solid #e2e8f0; background:#f8fafc;">
            <strong style="color:#334155;">My Residents (<?= e($blockAssigned ?: 'None') ?>)</strong>
        </div>
        <div style="flex:1; overflow-y:auto; padding:10px;">
            <?php if (!empty($students)): ?>
                <?php foreach ($students as $st): ?>
                    <a href="?student_id=<?= $st['student_id'] ?>" 
                       style="display:block; padding:10px 15px; border-radius:8px; margin-bottom:5px; text-decoration:none; color:inherit; background: <?= $st['student_id'] == $activeStudentId ? '#eff6ff' : 'transparent' ?>; border:1px solid <?= $st['student_id'] == $activeStudentId ? '#bfdbfe' : 'transparent' ?>">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <strong><?= e(mb_strimwidth($st['name'], 0, 20, '...')) ?></strong>
                            <?php if ($st['unread_count'] > 0 && $st['student_id'] != $activeStudentId): ?>
                                <span class="badge badge-danger" style="border-radius:50%; min-width:20px; text-align:center; padding:3px 6px;"><?= $st['unread_count'] ?></span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:0.8rem; color:#64748b; margin-top:3px;">
                            Room: <?= e($st['room_number'] ?: 'Unassigned') ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="padding:20px; text-align:center; color:#94a3b8; font-size:0.9rem;">No active students in your block.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Chat Area -->
    <div class="chat-main" style="flex:1; display:flex; flex-direction:column; background:#f8fafc;">
        <?php if ($activeStudent): ?>
            <!-- Chat Header -->
            <div style="padding:15px 20px; background:#fff; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <strong style="font-size:1.1rem; color:#1e293b;"><?= e($activeStudent['name']) ?></strong>
                    <span style="color:#64748b; font-size:0.9rem; margin-left:10px;">ID: <?= e($activeStudent['student_code']) ?></span>
                </div>
                <!-- Optional: view profile link -->
            </div>
            
            <!-- Chat Messages -->
            <div class="chat-messages" style="flex:1; overflow-y:auto; padding:20px; display:flex; flex-direction:column; gap:15px;" id="chat-messages-container">
                <?php if ($messages && $messages->num_rows > 0): ?>
                    <?php while ($m = $messages->fetch_assoc()): 
                        $isMe = ($m['sender_role'] === 'supervisor');
                    ?>
                        <div style="display:flex; flex-direction:column; align-items: <?= $isMe ? 'flex-end' : 'flex-start' ?>;">
                            <div style="
                                max-width:70%; 
                                padding:10px 15px; 
                                border-radius:12px; 
                                background: <?= $isMe ? '#2563eb' : '#fff' ?>; 
                                color: <?= $isMe ? '#fff' : '#1e293b' ?>;
                                border: <?= $isMe ? 'none' : '1px solid #e2e8f0' ?>;
                                box-shadow: 0 1px 2px rgba(0,0,0,0.05);
                            ">
                                <?= nl2br(e($m['message'])) ?>
                            </div>
                            <small style="color:#94a3b8; font-size:0.75rem; margin-top:4px;">
                                <?= date('h:i A', strtotime($m['sent_at'])) ?>
                            </small>
                        </div>
                    <?php endwhile; ?>
                    <div id="bottom"></div>
                <?php else: ?>
                    <div style="text-align:center; color:#94a3b8; margin-top:50px;">
                        No messages yet. Send a message to start the conversation.
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Chat Input -->
            <div style="padding:15px; background:#fff; border-top:1px solid #e2e8f0;">
                <form method="POST" action="<?= BASE_URL ?>actions/chat_action.php" style="display:flex; gap:10px; margin:0;">
                    <input type="hidden" name="action" value="send">
                    <input type="hidden" name="student_id" value="<?= $activeStudentId ?>">
                    <input type="hidden" name="supervisor_id" value="<?= $supervisorId ?>">
                    <input type="hidden" name="sender_role" value="supervisor">
                    <input type="hidden" name="redirect_to" value="supervisor/chat.php?student_id=<?= $activeStudentId ?>">
                    
                    <textarea name="message" class="form-control" rows="1" placeholder="Type a message..." required style="flex:1; resize:none; padding:10px 15px; border-radius:20px;"></textarea>
                    <button type="submit" class="btn btn-primary" style="border-radius:20px; padding:0 20px;">Send ↗</button>
                </form>
            </div>
            
            <script>
                // Auto-scroll to bottom of chat
                const container = document.getElementById('chat-messages-container');
                container.scrollTop = container.scrollHeight;
            </script>
            
        <?php else: ?>
            <div style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#94a3b8;">
                <span style="font-size:3rem; margin-bottom:10px;">👈</span>
                <p>Select a student from the sidebar to view or start a conversation.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
