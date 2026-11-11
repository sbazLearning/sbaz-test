<?php
require_once 'includes/functions.php';
require_once 'config/db.php';
require_login();

$page_title = 'Inventory';
$active = 'inventory';

$items = $pdo->query("
    SELECT
        i.id, i.item_name, i.short_name, i.price,
        i.discount AS own_discount,
        CASE WHEN i.discount_group_id IS NOT NULL THEN g.discount ELSE i.discount END AS effective_discount,
        i.discount_group_id,
        g.group_name
    FROM items i
    LEFT JOIN discount_groups g ON g.id = i.discount_group_id
    ORDER BY i.item_name ASC
")->fetchAll();

$groups = $pdo->query("SELECT id, group_name, discount FROM discount_groups ORDER BY group_name ASC")->fetchAll();

require 'includes/layout_top.php';
?>

<div class="card">
    <div class="card-title">
        Item List (<?= count($items) ?>)
        <div style="display:flex;gap:8px;">
            <a href="discount_groups.php" class="btn btn-outline btn-sm">Manage Discount Groups</a>
            <button class="btn btn-primary btn-sm" onclick="openItemModal()">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                Add Item
            </button>
        </div>
    </div>

    <div class="search-bar">
        <input type="text" id="searchBox" placeholder="Search by item name or shortcut...">
    </div>

    <div class="table-wrap">
        <table id="itemsTable">
            <thead>
                <tr><th>Item Name</th><th>Shortcut</th><th>Price (₹)</th><th>Discount Group</th><th>Effective Discount (%)</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if ($items): foreach ($items as $it): ?>
                <tr data-id="<?= (int)$it['id'] ?>"
                    data-name="<?= e($it['item_name']) ?>"
                    data-short="<?= e($it['short_name']) ?>"
                    data-price="<?= e($it['price']) ?>"
                    data-discount="<?= e($it['own_discount']) ?>"
                    data-group="<?= (int)($it['discount_group_id'] ?? 0) ?>">
                    <td><?= e($it['item_name']) ?></td>
                    <td><?= $it['short_name'] ? '<span class="badge green">' . e($it['short_name']) . '</span>' : '—' ?></td>
                    <td>₹<?= money($it['price']) ?></td>
                    <td><?= $it['group_name'] ? '<span class="badge orange">' . e($it['group_name']) . '</span>' : '<span style="color:#94a3b8;">Individual</span>' ?></td>
                    <td><b><?= money($it['effective_discount']) ?>%</b></td>
                    <td>
                        <button class="btn btn-outline btn-sm" onclick="editItem(this)">Edit</button>
                        <button class="btn btn-danger btn-sm" onclick="deleteItem(<?= (int)$it['id'] ?>, this)">Delete</button>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="6" class="empty-state">No items yet. Click "Add Item" to add your first item.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add / Edit Item Modal -->
<div class="modal-overlay" id="itemModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="itemModalTitle">Add Item</h3>
            <button class="modal-close" onclick="closeItemModal()">&times;</button>
        </div>
        <form id="itemForm">
            <input type="hidden" name="id" id="item_id">
            <div class="form-group">
                <label>Item Name *</label>
                <input type="text" name="item_name" id="item_name" placeholder="e.g. 1 inch Aqua Elbow" required>
            </div>
            <div class="form-group">
                <label>Shortcut Name (optional)</label>
                <input type="text" name="short_name" id="short_name" placeholder="e.g. 1inaqel">
                <div class="hint">Type this shortcut while making a bill — the full item name will fill in automatically.</div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Price (₹)</label>
                    <input type="number" step="0.01" name="price" id="price" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Discount Group</label>
                    <select name="discount_group_id" id="discount_group_id" onchange="toggleGroupDiscount()">
                        <option value="">— None (use individual discount) —</option>
                        <?php foreach ($groups as $g): ?>
                            <option value="<?= (int)$g['id'] ?>" data-discount="<?= e($g['discount']) ?>">
                                <?= e($g['group_name']) ?> (<?= money($g['discount']) ?>%)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="hint">If set, this item always follows the group's discount instead of its own.</div>
                </div>
            </div>
            <div class="form-group" id="individualDiscountWrap">
                <label>Individual Discount (%)</label>
                <input type="number" step="0.01" min="0" max="100" name="discount" id="discount" placeholder="0.00">
                <div class="hint">Only used when no Discount Group is selected above.</div>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Save Item</button>
        </form>
    </div>
</div>

<script>
const modal = document.getElementById('itemModal');

function toggleGroupDiscount() {
    const sel = document.getElementById('discount_group_id');
    const wrap = document.getElementById('individualDiscountWrap');
    wrap.style.opacity = sel.value ? '0.45' : '1';
    document.getElementById('discount').disabled = !!sel.value;
}

function openItemModal() {
    document.getElementById('itemModalTitle').textContent = 'Add Item';
    document.getElementById('itemForm').reset();
    document.getElementById('item_id').value = '';
    toggleGroupDiscount();
    modal.classList.add('open');
}
function closeItemModal() { modal.classList.remove('open'); }

function editItem(btn) {
    const row = btn.closest('tr');
    document.getElementById('itemModalTitle').textContent = 'Edit Item';
    document.getElementById('item_id').value = row.dataset.id;
    document.getElementById('item_name').value = row.dataset.name;
    document.getElementById('short_name').value = row.dataset.short;
    document.getElementById('price').value = row.dataset.price;
    document.getElementById('discount').value = row.dataset.discount;
    document.getElementById('discount_group_id').value = row.dataset.group || '';
    toggleGroupDiscount();
    modal.classList.add('open');
}

document.getElementById('itemForm').addEventListener('submit', function(e) {
    e.preventDefault();
    document.getElementById('discount').disabled = false; // so its value is still submitted
    const fd = new FormData(this);
    fetch('api/item_actions.php?action=save', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                showToast(res.message, 'success');
                setTimeout(() => location.reload(), 600);
            } else {
                showToast(res.message, 'error');
            }
        })
        .catch(() => showToast('Something went wrong.', 'error'));
});

function deleteItem(id, btn) {
    if (!confirm('Delete this item? This cannot be undone.')) return;
    const fd = new FormData();
    fd.append('id', id);
    fetch('api/item_actions.php?action=delete', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                btn.closest('tr').remove();
                showToast('Item deleted.', 'success');
            } else {
                showToast(res.message, 'error');
            }
        });
}

document.getElementById('searchBox').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#itemsTable tbody tr').forEach(row => {
        if (!row.dataset.name) return;
        const match = row.dataset.name.toLowerCase().includes(q) || (row.dataset.short || '').toLowerCase().includes(q);
        row.style.display = match ? '' : 'none';
    });
});
</script>

<?php require 'includes/layout_bottom.php'; ?>
