<?php
/**
 * supervisor/students.php — View students in assigned block
 * Hostel Management System
 */
$pageTitle = 'My Students';
require_once __DIR__ . '/../includes/header.php';

requireSupervisor();

$userId = $_SESSION['user_id'];

// Get supervisor block
$stmt = $conn->prepare("SELECT block_assigned FROM supervisors WHERE user_id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$blockAssigned = $stmt->get_result()->fetch_assoc()['block_assigned'] ?? '';

// Fetch students
if ($blockAssigned) {
    $stmt = $conn->prepare("
        SELECT s.student_id, s.student_code, s.name, s.email, s.phone, r.room_number, s.status, s.check_in_date
        FROM students s
        JOIN rooms r ON s.room_id = r.room_id
        WHERE r.block = ?
        ORDER BY r.room_number ASC, s.name ASC
    ");
    $stmt->bind_param('s', $blockAssigned);
    $stmt->execute();
    $students = $stmt->get_result();
} else {
    $students = false;
}
?>

<div class="page-header">
    <h1>👨‍🎓 Students in Block: <?= e($blockAssigned ?: 'None') ?></h1>
</div>

<div class="table-card">
    <div class="table-header">
        <span class="table-title">My Residents</span>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Student Info</th>
                    <th>Code / Email</th>
                    <th>Room</th>
                    <th>Phone</th>
                    <th>Check-in Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($students && $students->num_rows > 0): ?>
                <?php while ($st = $students->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= e($st['name']) ?></strong></td>
                    <td>
                        <span class="badge badge-secondary"><?= e($st['student_code']) ?></span><br>
                        <small class="text-muted"><?= e($st['email']) ?></small>
                    </td>
                    <td><strong><?= e($st['room_number']) ?></strong></td>
                    <td><?= e($st['phone'] ?: 'N/A') ?></td>
                    <td><?= formatDate($st['check_in_date']) ?></td>
                    <td>
                        <span class="badge badge-<?= $st['status'] == 'active' ? 'success' : 'secondary' ?>">
                            <?= ucfirst(str_replace('_', ' ', $st['status'])) ?>
                        </span>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6" class="text-center" style="padding:40px;">
                    <div class="empty-state">
                        <span class="empty-state-icon">👨‍🎓</span>
                        <div class="empty-state-title">No students found</div>
                        <div class="empty-state-msg">
                            <?php if (!$blockAssigned): ?>
                                You have not been assigned a block. Please contact the administrator.
                            <?php else: ?>
                                No active students currently reside in your block.
                            <?php endif; ?>
                        </div>
                    </div>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
