<?php
require_once 'includes/functions.php';
require_once 'config/db.php';
require_login();

$page_title = 'Dashboard';
$active = 'dashboard';

$totalItems = $pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();
$totalBillsToday = $pdo->prepare("SELECT COUNT(*) FROM bills WHERE bill_date = CURDATE()");
$totalBillsToday->execute();
$totalBillsToday = $totalBillsToday->fetchColumn();

$salesToday = $pdo->prepare("SELECT COALESCE(SUM(grand_total),0) FROM bills WHERE bill_date = CURDATE()");
$salesToday->execute();
$salesToday = $salesToday->fetchColumn();

$totalBills = $pdo->query("SELECT COUNT(*) FROM bills")->fetchColumn();
$totalEarnings = $pdo->query("SELECT COALESCE(SUM(grand_total),0) FROM bills")->fetchColumn();
$totalCustomers = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();

$outstanding = $pdo->query("
    SELECT COALESCE(SUM(b.grand_total),0) - COALESCE((SELECT SUM(amount) FROM customer_payments),0)
    FROM bills b
")->fetchColumn();

$recentBills = $pdo->query("SELECT * FROM bills ORDER BY id DESC LIMIT 6")->fetchAll();

require 'includes/layout_top.php';
?>

<div class="grid grid-4" style="margin-bottom:18px;">
    <div class="stat-card">
        <div class="icon blue">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 2h6l1 4H8z"/><path d="M4 6h16l-1.5 13a2 2 0 0 1-2 2H7.5a2 2 0 0 1-2-2z"/></svg>
        </div>
        <div><div class="label">Bills Today</div><div class="value"><?= (int)$totalBillsToday ?></div></div>
    </div>
    <div class="stat-card">
        <div class="icon green">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div style="flex:1;">
            <div class="label" style="display:flex;align-items:center;gap:5px;">
                Sales Today (₹)
                <button class="eye-toggle" onclick="toggleMask(this)" title="Show/hide amount">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </div>
            <div class="value masked" data-real="<?= money($salesToday) ?>">••••••</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="icon orange">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8L12 3 3 8l9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/></svg>
        </div>
        <div><div class="label">Total Items</div><div class="value"><?= (int)$totalItems ?></div></div>
    </div>
    <div class="stat-card">
        <div class="icon purple">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
        </div>
        <div><div class="label">Total Bills</div><div class="value"><?= (int)$totalBills ?></div></div>
    </div>
</div>

<div class="grid grid-4" style="margin-bottom:22px;">
    <div class="stat-card">
        <div class="icon green">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>
        </div>
        <div style="flex:1;">
            <div class="label" style="display:flex;align-items:center;gap:5px;">
                Total Earnings (₹)
                <button class="eye-toggle" onclick="toggleMask(this)" title="Show/hide amount">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </div>
            <div class="value masked" data-real="<?= money($totalEarnings) ?>">••••••</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="icon blue">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        </div>
        <div><div class="label">Total Customers</div><div class="value"><?= (int)$totalCustomers ?></div></div>
    </div>
    <div class="stat-card">
        <div class="icon orange">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 2h6l1 4H8z"/><path d="M4 6h16l-1.5 13a2 2 0 0 1-2 2H7.5a2 2 0 0 1-2-2z"/></svg>
        </div>
        <div style="flex:1;">
            <div class="label" style="display:flex;align-items:center;gap:5px;">
                Customers Owe (₹)
                <button class="eye-toggle" onclick="toggleMask(this)" title="Show/hide amount">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </div>
            <div class="value masked" data-real="<?= money(max(0, $outstanding)) ?>">••••••</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="icon purple">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8L12 3 3 8l9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/></svg>
        </div>
        <div><div class="label">Total Bills (All-time)</div><div class="value"><?= (int)$totalBills ?></div></div>
    </div>
</div>

<div class="card-title" style="margin-bottom:10px;">Quick Actions</div>
<div class="quick-links" style="margin-bottom:24px;">
    <a class="quick-link" href="billing.php">
        <div class="icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg></div>
        <div><div class="title">Create New Bill</div><div class="desc">Make a customer bill</div></div>
    </a>
    <a class="quick-link" href="inventory.php">
        <div class="icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8L12 3 3 8l9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/></svg></div>
        <div><div class="title">Manage Inventory</div><div class="desc">Add / edit items</div></div>
    </a>
    <a class="quick-link" href="discount_groups.php">
        <div class="icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="7" r="4"/><path d="M2 21v-2a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v2"/></svg></div>
        <div><div class="title">Discount Groups</div><div class="desc">Manage group discounts</div></div>
    </a>
    <a class="quick-link" href="customers.php">
        <div class="icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
        <div><div class="title">Customers</div><div class="desc">View ledger &amp; balances</div></div>
    </a>
    <a class="quick-link" href="bills.php">
        <div class="icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg></div>
        <div><div class="title">Search Bills</div><div class="desc">Find past customer bills</div></div>
    </a>
    <a class="quick-link" href="backup.php">
        <div class="icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12"/><path d="M7 10l5 5 5-5"/><path d="M4 21h16"/></svg></div>
        <div><div class="title">Backup Data</div><div class="desc">Save a safe copy of DB</div></div>
    </a>
</div>

<div class="card">
    <div class="card-title">
        Recent Bills
        <a href="bills.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="table-wrap">
    <?php if ($recentBills): ?>
        <table>
            <thead><tr><th>Bill No</th><th>Customer</th><th>Mobile</th><th>Date</th><th>Amount</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($recentBills as $b): ?>
                <tr>
                    <td><b><?= e($b['bill_number']) ?></b></td>
                    <td><?= e($b['customer_name'] ?: '—') ?></td>
                    <td><?= e($b['mobile_number'] ?: '—') ?></td>
                    <td><?= e(date('d M Y', strtotime($b['bill_date']))) ?></td>
                    <td>₹<?= money($b['grand_total']) ?></td>
                    <td>
                        <a href="print_bill.php?id=<?= (int)$b['id'] ?>" class="btn btn-outline btn-sm">View</a>
                        <a href="edit_bill.php?id=<?= (int)$b['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="empty-state">No bills yet. Create your first bill!</div>
    <?php endif; ?>
    </div>
</div>

<script>
function toggleMask(btn) {
    const card = btn.closest('.stat-card');
    const valueEl = card.querySelector('.value');
    if (valueEl.classList.contains('masked')) {
        valueEl.textContent = '₹' + valueEl.dataset.real;
        valueEl.classList.remove('masked');
    } else {
        valueEl.textContent = '••••••';
        valueEl.classList.add('masked');
    }
}
</script>

<?php require 'includes/layout_bottom.php'; ?>
