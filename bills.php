<?php
require_once 'includes/functions.php';
require_once 'config/db.php';
require_login();

$page_title = 'Bill History';
$actd
ive = 'bills';

$search = trim($_GET['q'] ?? '');
$dateFrom = trim($_GET['from'] ?? '');
$dateTo = trim($_GET['to'] ?? '');

$sql = "SELECT * FROM bills WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (customer_name LIKE ? OR mobile_number LIKE ? OR bill_number LIKE ?)";
    $like = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($dateFrom !== '') {
    $sql .= " AND bill_date >= ?";
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $sql .= " AND bill_date <= ?";
    $params[] = $dateTo;
}
$sql .= " ORDER BY id DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bills = $stmt->fetchAll();

require 'includes/layout_top.php';
?>

<div class="card">
    <div class="card-title">Search Bills</div>
    <form method="GET" class="search-bar">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search by customer name, mobile or bill number...">
        <input type="date" name="from" value="<?= e($dateFrom) ?>" title="From date">
        <input type="date" name="to" value="<?= e($dateTo) ?>" title="To date">
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search || $dateFrom || $dateTo): ?>
            <a href="bills.php" class="btn btn-outline">Reset</a>
        <?php endif; ?>
    </form>

    <div class="table-wrap">
    <?php if ($bills): ?>
        <table>
            <thead><tr><th>Bill No</th><th>Customer</th><th>Mobile</th><th>Date</th><th>Amount</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($bills as $b): ?>
                <tr>
                    <td><b><?= e($b['bill_number']) ?></b></td>
                    <td><?= e($b['customer_name'] ?: '—') ?></td>
                    <td><?= e($b['mobile_number'] ?: '—') ?></td>
                    <td><?= e(date('d M Y', strtotime($b['bill_date']))) ?></td>
                    <td>₹<?= money($b['grand_total']) ?></td>
                    <td>
                        <a href="print_bill.php?id=<?= (int)$b['id'] ?>" class="btn btn-outline btn-sm">View / Print</a>
                        <a href="edit_bill.php?id=<?= (int)$b['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="empty-state">No bills found<?= ($search || $dateFrom || $dateTo) ? ' for your search.' : '. Create your first bill!' ?></div>
    <?php endif; ?>
    </div>
</div>

<?php require 'includes/layout_bottom.php'; ?>
