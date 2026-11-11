<?php
require_once 'includes/functions.php';
require_once 'config/db.php';
require_login();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM bills WHERE id = ?");
$stmt->execute([$id]);
$bill = $stmt->fetch();

if (!$bill) {
    die("Bill not found.");
}

$stmt2 = $pdo->prepare("SELECT * FROM bill_items WHERE bill_id = ? ORDER BY id ASC");
$stmt2->execute([$id]);
$lineItems = $stmt2->fetchAll();

$page_title = 'Bill ' . $bill['bill_number'];
$active = 'bills';
require 'includes/layout_top.php';
?>

<div class="print-actions">
    <a href="bills.php" class="btn btn-outline">&larr; Back to Bills</a>
    <a href="bill_history.php?id=<?= (int)$bill['id'] ?>" class="btn btn-outline">Edit History</a>
    <a href="edit_bill.php?id=<?= (int)$bill['id'] ?>" class="btn btn-outline">Modify Bill</a>
    <a href="print_bill_plain.php?id=<?= (int)$bill['id'] ?>" class="btn btn-outline">Try Version Without Column Lines</a>
    <button class="btn btn-primary" onclick="window.print()">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/></svg>
        Print Bill
    </button>
</div>

<div class="print-bill">
    <div class="pb-header">
        <div>
            <div class="pb-title">Quotation</div>
            <div style="font-size:12.5px;color:#64748b;margin-top:4px;">Customer Bill Receipt</div>
        </div>
        <div class="pb-meta">
            <div><b>Qoute No: <?= e($bill['bill_number']) ?></b></div>
            <div>Date: <?= e(date('d M Y', strtotime($bill['bill_date']))) ?></div>
        </div>
    </div>

    <div class="pb-customer">
        <div>
            <div><b>Customer:</b> <b><?= e($bill['customer_name'] ?: 'Walk-in Customer') ?></b></div>
            <div><b>Mobile:</b> <?= e($bill['mobile_number'] ?: '—') ?></div>
        </div>
    </div>

    <table class="pb-items-table with-col-lines">
        <thead>
            <tr>
                <th class="col-sr">Sr</th>
                <th class="col-item">Item</th>
                <th class="col-num">Qty</th>
                <th class="col-num">Price</th>
                <th class="col-num">Discount</th>
                <th class="col-num">Net Rate</th>
                <th class="col-num">Total</th>
            </tr>
        </thead>
        <tbody>
        <?php $sr = 1; foreach ($lineItems as $li):
            $netRate = $li['price'] * (1 - ($li['discount'] / 100));
        ?>
            <tr>
                <td class="col-sr"><?= $sr++ ?></td>
                <td class="col-item"><?= e($li['item_name']) ?></td>
                <td class="col-num"><?= rtrim(rtrim(number_format($li['quantity'], 2), '0'), '.') ?></td>
                <td class="col-num"><?= money($li['price']) ?></td>
                <td class="col-num"><?= money($li['discount']) ?>%</td>
                <td class="col-num"><?= money($netRate) ?></td>
                <td class="col-num"><?= money($li['total']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="pb-totals">
        <div class="totals-box" style="width:100%;">
            <div class="row"><span>Subtotal</span><span><?= money($bill['subtotal']) ?></span></div>
            <div class="row"><span>Total Discount</span><span><?= money($bill['total_discount']) ?></span></div>
            <div class="row grand"><span>Grand Total</span><span><?= money($bill['grand_total']) ?></span></div>
        </div>
    </div>

    <?php if ($bill['notes']): ?>
        <div class="pb-notes"><?= nl2br(e($bill['notes'])) ?></div>
    <?php endif; ?>
</div>

<?php require 'includes/layout_bottom.php'; ?>
