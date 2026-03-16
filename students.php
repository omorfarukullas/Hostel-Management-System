<?php
/**
 * students.php — Student Management
 * Hostel Management System
 */
$pageTitle = 'Students';
require_once __DIR__ . '/includes/header.php';

// ── Filters ──
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

// ── Build query ──
$where = ['1=1'];
$params = [];
$types  = '';

if ($search !== '') {
    $where[] = "(s.name LIKE ? OR s.student_code LIKE ? OR s.email LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= 'sss';
}
$allowedStatus = ['active','checked_out','suspended'];
if ($status !== '' && in_array($status, $allowedStatus)) {
    $where[] = "s.status = ?";
    $params[] = $status;
    $types .= 's';
}

$sql = "SELECT s.student_id, s.student_code, s.name, s.email, s.phone,
               s.check_in_date, s.status, s.guardian_name, s.guardian_phone,
               s.address, s.dob, s.gender,
               COALESCE(r.room_number, '') AS room_number,
               COALESCE(r.room_id, 0) AS room_id
        FROM students s
        LEFT JOIN rooms r ON s.room_id = r.room_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY s.created_at DESC";

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$students = $stmt->get_result();

// ── Available rooms for dropdown ──
$availRoomsQ = $conn->query("
    SELECT room_id, room_number, type, monthly_fee
    FROM rooms
    WHERE status != 'maintenance'
    ORDER BY room_number ASC
");

// ── Status badge ──
function sBadge(string $s): string {
    $map = ['active'=>'success','checked_out'=>'secondary','suspended'=>'danger'];
    return '<span class="badge badge-'.($map[$s]??'secondary').'">'.ucfirst(str_replace('_',' ',$s)).'</span>';
}
?>

<div class="page-header">
    <h1>👨‍🎓 Student Management</h1>
    <button class="btn btn-primary" onclick="openModal('addStudentModal')">➕ Add Student</button>
</div>

<!-- Filter Bar -->
<form method="GET" action="" id="filterForm">
    <div class="filter-bar">
        <div class="filter-search">
            <input type="text" name="search" class="form-control"
                   placeholder="Search by name, code or email…"
                   value="<?= e($search) ?>"
                   data-search-table="studentsTable"
                   oninput="document.getElementById('filterForm').submit()">
        </div>
        <select name="status" class="form-control" onchange="this.form.submit()">
            <option value="">All Status</option>
            <?php foreach (['active'=>'Active','checked_out'=>'Checked Out','suspended'=>'Suspended'] as $val=>$lbl): ?>
                <option value="<?= $val ?>" <?= $status===$val?'selected':'' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($search || $status): ?>
            <a href="<?= BASE_URL ?>students.php" class="btn btn-secondary">✕ Clear</a>
        <?php endif; ?>
    </div>
</form>

<!-- Students Table -->
<div class="table-card">
    <div class="table-header">
        <span class="table-title">All Students
            <span class="badge badge-info" style="margin-left:6px;"><?= $students->num_rows ?></span>
        </span>
    </div>
    <div class="table-responsive">
        <table id="studentsTable">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name / Email</th>
                    <th>Room</th>
                    <th>Phone</th>
                    <th>Check-in</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($students->num_rows > 0): ?>
                <?php while ($s = $students->fetch_assoc()): ?>
                <tr>
                    <td><code style="color:#3b82f6;font-size:.78rem;"><?= e($s['student_code']) ?></code></td>
                    <td>
                        <strong><?= e($s['name']) ?></strong><br>
                        <small class="text-muted"><?= e($s['email']) ?></small>
                    </td>
                    <td><?= $s['room_number'] ? e($s['room_number']) : '<span class="text-muted">—</span>' ?></td>
                    <td><?= e($s['phone'] ?: '—') ?></td>
                    <td><?= formatDate($s['check_in_date']) ?></td>
                    <td><?= sBadge($s['status']) ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-primary"
                                onclick="openEditModal(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)">
                                ✏️ Edit
                            </button>
                            <a href="<?= BASE_URL ?>fees.php?student_id=<?= (int)$s['student_id'] ?>"
                               class="btn btn-sm btn-success">💰 Fees</a>
                            <form method="POST" action="<?= BASE_URL ?>actions/student_action.php"
                                  onsubmit="return confirmAction('Delete this student? This cannot be undone.')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="student_id" value="<?= (int)$s['student_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <span class="empty-state-icon">🎓</span>
                            <div class="empty-state-title">No students found</div>
                            <div class="empty-state-msg">Try adjusting your search or add a new student.</div>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<!-- ══════════ ADD STUDENT MODAL ══════════ -->
<div class="modal-overlay" id="addStudentModal">
    <div class="modal-card" style="max-width:680px;">
        <div class="modal-header">
            <h3 class="modal-title">➕ Add New Student</h3>
            <button class="modal-close" onclick="closeModal('addStudentModal')">✕</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>actions/student_action.php" enctype="multipart/form-data" class="modal-form">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Full Name <span style="color:red">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Faruk Ahmed" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address <span style="color:red">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="student@email.com" required>
                    </div>
                    <div class="form-group">
                        <label>Login Password</label>
                        <input type="text" name="password" class="form-control" placeholder="Leave blank for Student@123">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control" placeholder="01XXXXXXXXX">
                    </div>
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="dob" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Gender <span style="color:red">*</span></label>
                        <select name="gender" class="form-control" required>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Assign Room</label>
                        <select name="room_id" class="form-control">
                            <option value="">— No Room —</option>
                            <?php while ($rm = $availRoomsQ->fetch_assoc()): ?>
                                <option value="<?= (int)$rm['room_id'] ?>">
                                    Room <?= e($rm['room_number']) ?> — <?= ucfirst($rm['type']) ?> — <?= formatCurrency($rm['monthly_fee']) ?>/mo
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Check-in Date</label>
                        <input type="date" name="check_in_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label>Profile Photo</label>
                        <input type="file" name="photo" class="form-control" accept="image/jpeg,image/png,image/webp">
                    </div>
                    <div class="form-group">
                        <label>Guardian Name</label>
                        <input type="text" name="guardian_name" class="form-control" placeholder="Parent / Guardian">
                    </div>
                    <div class="form-group">
                        <label>Guardian Phone</label>
                        <input type="text" name="guardian_phone" class="form-control" placeholder="01XXXXXXXXX">
                    </div>
                    <div class="form-group form-full">
                        <label>Address</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="Home address…"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addStudentModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">✅ Save Student</button>
            </div>
        </form>
    </div>
</div>


<!-- ══════════ EDIT STUDENT MODAL ══════════ -->
<div class="modal-overlay" id="editStudentModal">
    <div class="modal-card" style="max-width:580px;">
        <div class="modal-header">
            <h3 class="modal-title">✏️ Edit Student</h3>
            <button class="modal-close" onclick="closeModal('editStudentModal')">✕</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>actions/student_action.php" class="modal-form">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="student_id" id="editStudentId">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="name" id="editName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" id="editPhone" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="editStatus" class="form-control">
                            <option value="active">Active</option>
                            <option value="checked_out">Checked Out</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Guardian Name</label>
                        <input type="text" name="guardian_name" id="editGuardianName" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Guardian Phone</label>
                        <input type="text" name="guardian_phone" id="editGuardianPhone" class="form-control">
                    </div>
                    <div class="form-group form-full">
                        <label>Address</label>
                        <textarea name="address" id="editAddress" class="form-control" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editStudentModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">💾 Update Student</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(student) {
    document.getElementById('editStudentId').value     = student.student_id;
    document.getElementById('editName').value          = student.name || '';
    document.getElementById('editPhone').value         = student.phone || '';
    document.getElementById('editStatus').value        = student.status || 'active';
    document.getElementById('editGuardianName').value  = student.guardian_name || '';
    document.getElementById('editGuardianPhone').value = student.guardian_phone || '';
    document.getElementById('editAddress').value       = student.address || '';
    openModal('editStudentModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
