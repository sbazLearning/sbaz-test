<?php
require_once 'includes/functions.php';
require_once 'config/db.php';
require_login();

$page_title = 'Customers';
$active = 'customers';

$search = trim($_GET['q'] ?? '');

$sql = "
    SELECT c.*,
        COALESCE((SELECT SUM(b.grand_total) FROM bills b WHERE b.customer_id = c.id), 0) AS total_billed,
        COALESCE((SELECT SUM(p.amount) FROM customer_payments p WHERE p.customer_id = c.id), 0) AS total_paid,
        (SELECT COUNT(*) FROM bills b WHERE b.customer_id = c.id) AS bill_count
    FROM customers c
";
$params = [];
if ($search !== '') {
    $sql .= " WHERE c.name LIKE ? OR c.mobile_number LIKE ? OR c.customer_code LIKE ?";
    $like = "%$search%";
    $params = [$like, $like, $like];
}
$sql .= " ORDER BY c.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

$totalOutstanding = 0;
foreach ($customers as $c) {
    $totalOutstanding += ((float)$c['total_billed'] - (float)$c['total_paid']);
}

require 'includes/layout_top.php';
?>

<div class="card" style="margin-bottom:18px;">
    <b>How this list works</b>
    <p style="font-size:13.5px;color:#64748b;margin:6px 0 0;">
        This is only for customers you track deliberately — usually people who take items regularly
        (like on credit) and pay you back over time. A normal one-time bill does <b>not</b> add anyone
        here anymore. Click <b>Add Customer</b> below for anyone you want to keep a running balance for.
        If a bill's mobile number matches someone already on this list, it links to their ledger
        automatically — otherwise the bill just stays a simple one-time bill.
    </p>
</div>

<div class="grid grid-4" style="margin-bottom:22px;">
    <div class="stat-card">
        <div class="icon blue">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        </div>
        <div><div class="label">Tracked Customers</div><div class="value"><?= count($customers) ?></div></div>
    </div>
    <div class="stat-card">
        <div class="icon orange">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div><div class="label">Total Outstanding (₹)</div><div class="value"><?= money($totalOutstanding) ?></div></div>
    </div>
</div>

<div class="card">
    <div class="card-title">
        Customer List
        <button class="btn btn-primary btn-sm" onclick="openAddCustomerModal()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
            Add Customer
        </button>
    </div>
    <form method="GET" class="search-bar">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search by name, mobile, or customer ID...">
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search): ?>
            <a href="customers.php" class="btn btn-outline">Reset</a>
        <?php endif; ?>
    </form>

    <div class="table-wrap">
    <?php if ($customers): ?>
        <table>
            <thead><tr><th>Customer ID</th><th>Name</th><th>Mobile</th><th>Bills</th><th>Total Billed</th><th>Total Paid</th><th>Balance</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($customers as $c):
                $balance = (float)$c['total_billed'] - (float)$c['total_paid'];
            ?>
                <tr>
                    <td><b><?= e($c['customer_code']) ?></b></td>
                    <td><?= e($c['name']) ?></td>
                    <td><?= e($c['mobile_number'] ?: '—') ?></td>
                    <td><?= (int)$c['bill_count'] ?></td>
                    <td>₹<?= money($c['total_billed']) ?></td>
                    <td>₹<?= money($c['total_paid']) ?></td>
                    <td>
                        <?php if ($balance > 0.004): ?>
                            <span class="badge orange">₹<?= money($balance) ?> due</span>
                        <?php elseif ($balance < -0.004): ?>
                            <span class="badge green">₹<?= money(abs($balance)) ?> advance</span>
                        <?php else: ?>
                            <span class="badge green">Settled</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="customer_detail.php?id=<?= (int)$c['id'] ?>" class="btn btn-outline btn-sm">View Ledger</a>
                        <button class="btn btn-danger btn-sm" onclick="deleteCustomer(<?= (int)$c['id'] ?>, this)">Remove</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="empty-state">
            No customers added yet.<br>
            <span style="font-size:12.5px;">Click "Add Customer" above for anyone you want to track a running balance for.</span>
        </div>
    <?php endif; ?>
    </div>
</div>

<!-- Add Customer Modal -->
<div class="modal-overlay" id="addCustomerModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Add Customer</h3>
            <button class="modal-close" onclick="closeAddCustomerModal()">&times;</button>
        </div>
        <form id="addCustomerForm">
            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name" required placeholder="e.g. Ramesh Patil">
            </div>
            <div class="form-group">
                <label>Mobile Number</label>
                <input type="text" name="mobile_number" placeholder="10-digit mobile number">
                <div class="hint">If a bill is later made with this same mobile number, it will link to this customer automatically.</div>
            </div>
            <div class="form-group">
                <label>Address (optional)</label>
                <textarea name="address" rows="2"></textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Add Customer</button>
        </form>
    </div>
</div>

<script>
function openAddCustomerModal() { document.getElementById('addCustomerModal').classList.add('open'); }
function closeAddCustomerModal() { document.getElementById('addCustomerModal').classList.remove('open'); }

document.getElementById('addCustomerForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fetch('api/customer_actions.php?action=create', { method: 'POST', body: fd })
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

function deleteCustomer(id, btn) {
    if (!confirm('Remove this customer from tracking? Their past bills stay in Bill History, just no longer linked to a ledger.')) return;
    const fd = new FormData();
    fd.append('id', id);
    fetch('api/customer_actions.php?action=delete', { method: 'POST', body: fd })
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
