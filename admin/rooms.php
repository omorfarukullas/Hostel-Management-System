<?php
/**
 * rooms.php — Room Management
 * Hostel Management System
 */
$pageTitle = 'Rooms';
require_once __DIR__ . '/../includes/header.php';

// ── Fetch all rooms ──
$rooms = $conn->query("
    SELECT room_id, room_number, floor, block, type, capacity, occupied,
           monthly_fee, amenities, status
    FROM rooms
    ORDER BY block ASC, floor ASC, room_number ASC
");

function roomTypeBadge(string $t): string {
    $map=['single'=>'info','double'=>'success','triple'=>'warning','dormitory'=>'purple'];
    return '<span class="badge badge-'.($map[$t]??'secondary').'">'.ucfirst($t).'</span>';
}
function roomStatusBadge(string $s): string {
    $map=['available'=>'success','full'=>'danger','maintenance'=>'warning'];
    return '<span class="badge badge-'.($map[$s]??'secondary').'">'.ucfirst($s).'</span>';
}
?>

<div class="page-header">
    <h1>🛏️ Room Management</h1>
    <?php if (isAdmin()): ?>
    <button class="btn btn-primary" onclick="openModal('addRoomModal')">➕ Add Room</button>
    <?php endif; ?>
</div>

<!-- Rooms Table -->
<div class="table-card">
    <div class="table-header">
        <span class="table-title">All Rooms
            <span class="badge badge-info" style="margin-left:6px;"><?= $rooms->num_rows ?></span>
        </span>
    </div>
    <div class="table-responsive">
        <table id="roomsTable">
            <thead>
                <tr>
                    <th>Room No.</th>
                    <th>Block</th>
                    <th>Floor</th>
                    <th>Type</th>
                    <th>Occupancy</th>
                    <th>Monthly Fee</th>
                    <th>Amenities</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($rooms->num_rows > 0): ?>
                <?php while ($rm = $rooms->fetch_assoc()):
                    $pct      = $rm['capacity'] > 0 ? round(($rm['occupied'] / $rm['capacity']) * 100) : 0;
                    $barClass = $pct >= 100 ? 'full' : ($pct >= 75 ? 'high' : '');
                ?>
                <tr>
                    <td><strong><?= e($rm['room_number']) ?></strong></td>
                    <td><span class="badge badge-info"><?= e($rm['block'] ?: 'General') ?></span></td>
                    <td><span class="badge badge-secondary">Floor <?= (int)$rm['floor'] ?></span></td>
                    <td><?= roomTypeBadge($rm['type']) ?></td>
                    <td style="min-width:120px;">
                        <div class="occupancy-label" style="margin-bottom:3px;">
                            <span><?= (int)$rm['occupied'] ?>/<?= (int)$rm['capacity'] ?></span>
                            <span><?= $pct ?>%</span>
                        </div>
                        <div class="occupancy-bar">
                            <div class="occupancy-fill <?= $barClass ?>" style="width:<?= $pct ?>%"></div>
                        </div>
                    </td>
                    <td><?= formatCurrency($rm['monthly_fee']) ?></td>
                    <td style="max-width:180px;font-size:.82rem;color:#64748b;"><?= e($rm['amenities'] ?: '—') ?></td>
                    <td><?= roomStatusBadge($rm['status']) ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <?php if (isAdmin()): ?>
                            <button class="btn btn-sm btn-primary"
                                onclick="openEditRoomModal(<?= htmlspecialchars(json_encode($rm), ENT_QUOTES) ?>)">
                                ✏️ Edit
                            </button>
                            <form method="POST" action="<?= BASE_URL ?>actions/room_action.php"
                                  onsubmit="return confirmAction('Delete Room <?= e($rm['room_number']) ?>? This cannot be undone.')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="room_id" value="<?= (int)$rm['room_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="8">
                    <div class="empty-state">
                        <span class="empty-state-icon">🛏️</span>
                        <div class="empty-state-title">No rooms yet</div>
                        <div class="empty-state-msg">Add your first room to get started.</div>
                    </div>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<!-- ══════════ ADD ROOM MODAL ══════════ -->
<div class="modal-overlay" id="addRoomModal">
    <div class="modal-card" style="max-width:560px;">
        <div class="modal-header">
            <h3 class="modal-title">➕ Add New Room</h3>
            <button class="modal-close" onclick="closeModal('addRoomModal')">✕</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>actions/room_action.php">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Room Number <span style="color:red">*</span></label>
                        <input type="text" name="room_number" class="form-control" placeholder="e.g. 101" required>
                    </div>
                    <div class="form-group">
                        <label>Floor <span style="color:red">*</span></label>
                        <input type="number" name="floor" class="form-control" min="1" max="20" value="1" required>
                    </div>
                    <div class="form-group">
                        <label>Block / Area</label>
                        <input type="text" name="block" class="form-control" placeholder="e.g. Block A">
                    </div>
                    <div class="form-group">
                        <label>Room Type <span style="color:red">*</span></label>
                        <select name="type" class="form-control" required onchange="updateCapacity(this)">
                            <option value="single">Single (1 bed)</option>
                            <option value="double">Double (2 beds)</option>
                            <option value="triple">Triple (3 beds)</option>
                            <option value="dormitory">Dormitory (6+ beds)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Capacity <span style="color:red">*</span></label>
                        <input type="number" name="capacity" id="capacityInput" class="form-control" min="1" max="20" value="1" required>
                    </div>
                    <div class="form-group">
                        <label>Monthly Fee (৳) <span style="color:red">*</span></label>
                        <input type="number" name="monthly_fee" class="form-control" step="0.01" min="0" placeholder="e.g. 3000" required>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="available">Available</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                    <div class="form-group form-full">
                        <label>Amenities</label>
                        <input type="text" name="amenities" class="form-control" placeholder="e.g. AC, WiFi, Attached Bathroom">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addRoomModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">✅ Save Room</button>
            </div>
        </form>
    </div>
</div>


<!-- ══════════ EDIT ROOM MODAL ══════════ -->
<div class="modal-overlay" id="editRoomModal">
    <div class="modal-card" style="max-width:480px;">
        <div class="modal-header">
            <h3 class="modal-title">✏️ Edit Room</h3>
            <button class="modal-close" onclick="closeModal('editRoomModal')">✕</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>actions/room_action.php">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="room_id" id="editRoomId">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Room Number</label>
                        <input type="text" name="room_number" id="editRoomNumber" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Block / Area</label>
                        <input type="text" name="block" id="editBlock" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Monthly Fee (৳)</label>
                        <input type="number" name="monthly_fee" id="editMonthlyFee" class="form-control" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="editRoomStatus" class="form-control">
                            <option value="available">Available</option>
                            <option value="full">Full</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                    <div class="form-group form-full">
                        <label>Amenities</label>
                        <input type="text" name="amenities" id="editAmenities" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editRoomModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">💾 Update Room</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditRoomModal(room) {
    document.getElementById('editRoomId').value      = room.room_id;
    document.getElementById('editRoomNumber').value  = room.room_number || '';
    document.getElementById('editBlock').value       = room.block || '';
    document.getElementById('editMonthlyFee').value  = room.monthly_fee || '';
    document.getElementById('editRoomStatus').value  = room.status || 'available';
    document.getElementById('editAmenities').value   = room.amenities || '';
    openModal('editRoomModal');
}
function updateCapacity(sel) {
    const map = { single:1, double:2, triple:3, dormitory:6 };
    document.getElementById('capacityInput').value = map[sel.value] || 1;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
