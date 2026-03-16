<?php
/**
 * student/chat.php — Student Chat Interface
 * Hostel Management System
 */
$pageTitle = 'Chat with Supervisor';
require_once __DIR__ . '/../includes/header.php';

requireStudent();

$userId = $_SESSION['user_id'];

// Get student info + Assigned Supervisor
$stmt = $conn->prepare("
    SELECT s.student_id, r.block, sup.supervisor_id, u.name as supervisor_name
    FROM students s
    LEFT JOIN rooms r ON s.room_id = r.room_id
    LEFT JOIN supervisors sup ON r.block = sup.block_assigned
    LEFT JOIN users u ON sup.user_id = u.user_id
    WHERE s.user_id = ?
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$info = $stmt->get_result()->fetch_assoc();

$studentId = $info['student_id'] ?? 0;
$supervisorId = $info['supervisor_id'] ?? 0;
$supervisorName = $info['supervisor_name'] ?? 'Not Assigned';

$messages = null;
if ($studentId > 0 && $supervisorId > 0) {
    // Mark as read (messages from supervisor to student)
    $conn->query("UPDATE chat_messages SET is_read = 1 WHERE student_id = $studentId AND supervisor_id = $supervisorId AND sender_role = 'supervisor' AND is_read = 0");
    
    // Fetch messages
    $stmt = $conn->prepare("
        SELECT * FROM chat_messages 
        WHERE student_id = ? AND supervisor_id = ?
        ORDER BY sent_at ASC
    ");
    $stmt->bind_param('ii', $studentId, $supervisorId);
    $stmt->execute();
    $messages = $stmt->get_result();
}
?>

<div class="page-header">
    <h1>💬 Communication</h1>
</div>

<div class="chat-layout" style="display:flex; height: 75vh; background:#fff; border-radius:12px; border:1px solid #e2e8f0; overflow:hidden;">
    
    <?php if ($supervisorId > 0): ?>
        <!-- Main Chat Area -->
        <div class="chat-main" style="flex:1; display:flex; flex-direction:column; background:#f8fafc;">
            
            <!-- Chat Header -->
            <div style="padding:15px 20px; background:#fff; border-bottom:1px solid #e2e8f0; display:flex; align-items:center; gap:15px;">
                <div style="width:40px; height:40px; background:#e0e7ff; color:#4f46e5; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:1.2rem;">
                    <?= strtoupper(substr($supervisorName, 0, 1)) ?>
                </div>
                <div>
                    <strong style="font-size:1.1rem; color:#1e293b;"><?= e($supervisorName) ?></strong>
                    <span style="color:#64748b; font-size:0.85rem; display:block;">Block <?= e($info['block']) ?> Supervisor</span>
                </div>
            </div>
            
            <!-- Chat Messages -->
            <div class="chat-messages" style="flex:1; overflow-y:auto; padding:20px; display:flex; flex-direction:column; gap:15px;" id="chat-messages-container">
                <?php if ($messages && $messages->num_rows > 0): ?>
                    <?php while ($m = $messages->fetch_assoc()): 
                        $isMe = ($m['sender_role'] === 'student');
                    ?>
                        <div style="display:flex; flex-direction:column; align-items: <?= $isMe ? 'flex-end' : 'flex-start' ?>;">
                            <div style="
                                max-width:80%; 
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
                    <div style="text-align:center; color:#94a3b8; margin-top:50px; flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                        <span style="font-size:3rem; margin-bottom:15px;">👋</span>
                        <p>Say hello to your supervisor! Use this to ask questions or get help.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Chat Input -->
            <div style="padding:15px; background:#fff; border-top:1px solid #e2e8f0;">
                <form method="POST" action="<?= BASE_URL ?>actions/chat_action.php" style="display:flex; gap:10px; margin:0;">
                    <input type="hidden" name="action" value="send">
                    <input type="hidden" name="student_id" value="<?= $studentId ?>">
                    <input type="hidden" name="supervisor_id" value="<?= $supervisorId ?>">
                    <input type="hidden" name="sender_role" value="student">
                    <input type="hidden" name="redirect_to" value="student/chat.php">
                    
                    <textarea name="message" class="form-control" rows="1" placeholder="Type your message..." required style="flex:1; resize:none; padding:10px 15px; border-radius:20px;"></textarea>
                    <button type="submit" class="btn btn-primary" style="border-radius:20px; padding:0 25px;">Send ↗</button>
                </form>
            </div>
            
            <script>
                // Auto-scroll to bottom of chat
                const container = document.getElementById('chat-messages-container');
                container.scrollTop = container.scrollHeight;
            </script>
            
        </div>
    <?php else: ?>
        <div style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#94a3b8; background:#f8fafc; padding:40px; text-align:center;">
            <span style="font-size:4rem; margin-bottom:20px;">🏢</span>
            <h2>No Supervisor Assigned</h2>
            <p style="font-size:1.1rem; max-width:400px;">
                You are currently not assigned to a block, or your block does not have a supervisor yet. 
                You can contact the administration if you need assistance.
            </p>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
