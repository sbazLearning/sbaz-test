<?php
require_once 'includes/functions.php';
require_once 'config/db.php';
require_login();

$page_title = 'Discount Groups';
$active = 'groups';

$groups = $pdo->query("
    SELECT g.*, COUNT(i.id) AS item_count
    FROM discount_groups g
    LEFT JOIN items i ON i.discount_group_id = g.id
    GROUP BY g.id
    ORDER BY g.group_name ASC
")->fetchAll();

require 'includes/layout_top.php';
?>

<div class="card" style="margin-bottom:18px;">
    <b>What are Discount Groups?</b>
    <p style="font-size:13.5px;color:#64748b;margin:6px 0 0;">
        If several items always get the same discount (e.g. all PVC pipes get 10% off), put them in one
        group here instead of setting the discount on each item separately. Change the group's discount
        once, and every item in that group updates automatically — on Inventory and on every new bill.
    </p>
</div>

<div class="card">
    <div class="card-title">
        Groups (<?= count($groups) ?>)
        <button class="btn btn-primary btn-sm" onclick="openGroupModal()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
            Add Group
        </button>
    </div>

    <div class="table-wrap">
        <table>
            <thead><tr><th>Group Name</th><th>Discount (%)</th><th>Items in Group</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if ($groups): foreach ($groups as $g): ?>
                <tr data-id="<?= (int)$g['id'] ?>" data-name="<?= e($g['group_name']) ?>" data-discount="<?= e($g['discount']) ?>">
                    <td><b><?= e($g['group_name']) ?></b></td>
                    <td><span class="badge green"><?= money($g['discount']) ?>%</span></td>
                    <td><?= (int)$g['item_count'] ?> item<?= (int)$g['item_count'] === 1 ? '' : 's' ?></td>
                    <td>
                        <button class="btn btn-outline btn-sm" onclick="editGroup(this)">Edit</button>
                        <button class="btn btn-danger btn-sm" onclick="deleteGroup(<?= (int)$g['id'] ?>, this)">Delete</button>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="4" class="empty-state">No discount groups yet. Click "Add Group" to create your first one.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add / Edit Group Modal -->
<div class="modal-overlay" id="groupModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="groupModalTitle">Add Discount Group</h3>
            <button class="modal-close" onclick="closeGroupModal()">&times;</button>
        </div>
        <form id="groupForm">
            <input type="hidden" name="id" id="group_id">
            <div class="form-group">
                <label>Group Name *</label>
                <input type="text" name="group_name" id="group_name" placeholder="e.g. PVC Pipes" required>
            </div>
            <div class="form-group">
                <label>Discount (%) *</label>
                <input type="number" step="0.01" min="0" max="100" name="discount" id="group_discount" placeholder="0.00" required>
                <div class="hint">This discount will apply to every item you assign to this group.</div>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Save Group</button>
        </form>
    </div>
</div>

<script>
const groupModal = document.getElementById('groupModal');
function openGroupModal() {
    document.getElementById('groupModalTitle').textContent = 'Add Discount Group';
    document.getElementById('groupForm').reset();
    document.getElementById('group_id').value = '';
    groupModal.classList.add('open');
}
function closeGroupModal() { groupModal.classList.remove('open'); }

function editGroup(btn) {
    const row = btn.closest('tr');
    document.getElementById('groupModalTitle').textContent = 'Edit Discount Group';
    document.getElementById('group_id').value = row.dataset.id;
    document.getElementById('group_name').value = row.dataset.name;
    document.getElementById('group_discount').value = row.dataset.discount;
    groupModal.classList.add('open');
}

document.getElementById('groupForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fetch('api/group_actions.php?action=save', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                showToast(res.message, 'success');
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(res.message, 'error');
            }
        })
        .catch(() => showToast('Something went wrong.', 'error'));
});

function deleteGroup(id, btn) {
    if (!confirm('Delete this group? Items in it will fall back to their own individual discount (not lose their discount info, just stop following the group).')) return;
    const fd = new FormData();
    fd.append('id', id);
    fetch('api/group_actions.php?action=delete', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                btn.closest('tr').remove();
                showToast(res.message, 'success');
            } else {
                showToast(res.message, 'error');
            }
        });
}
</script>

<?php require 'includes/layout_bottom.php'; ?>
