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

$stmt2 = $pdo->prepare("SELECT * FROM bill_edit_history WHERE bill_id = ? ORDER BY id DESC");
$stmt2->execute([$id]);
$history = $stmt2->fetchAll();

$page_title = 'Edit History - ' . $bill['bill_number'];
$active = 'bills';
require 'includes/layout_top.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <a href="bills.php" class="btn btn-outline btn-sm">&larr; Back to Bill History</a>
    <div style="display:flex;gap:8px;">
        <a href="print_bill.php?id=<?= (int)$bill['id'] ?>" class="btn btn-outline btn-sm">View Current Bill</a>
        <a href="edit_bill.php?id=<?= (int)$bill['id'] ?>" class="btn btn-primary btn-sm">Edit This Bill</a>
    </div>
</div>

<div class="card" style="margin-bottom:18px;">
    <div class="card-title">Bill <?= e($bill['bill_number']) ?> — Current Version</div>
    <div class="form-row" style="margin-bottom:0;">
        <div><b>Customer:</b> <?= e($bill['customer_name'] ?: '—') ?></div>
        <div><b>Mobile:</b> <?= e($bill['mobile_number'] ?: '—') ?></div>
    </div>
    <div class="form-row">
        <div><b>Date:</b> <?= e(date('d M Y', strtotime($bill['bill_date']))) ?></div>
        <div><b>Grand Total:</b> ₹<?= money($bill['grand_total']) ?></div>
    </div>
</div>

<div class="card-title" style="margin-bottom:10px;">
    Edit History (<?= count($history) ?> change<?= count($history) === 1 ? '' : 's' ?>)
</div>

<?php if (!$history): ?>
    <div class="card"><div class="empty-state">This bill has not been edited yet. It is still exactly as it was created.</div></div>
<?php else: ?>
    <?php foreach ($history as $h):
        $snap = json_decode($h['old_snapshot'], true);
        $snapBill = $snap['bill'] ?? [];
        $snapItems = $snap['items'] ?? [];
    ?>
    <div class="card" style="margin-bottom:16px;">
        <div class="card-title">
            <span>Version before edit on <?= e(date('d M Y, h:i A', strtotime($h['edited_at']))) ?></span>
            <span style="font-size:12px;font-weight:500;color:#64748b;">
                Edited by: <?= e($h['edited_by'] ?: 'Unknown') ?>
            </span>
        </div>
        <div class="form-row" style="margin-bottom:8px;">
            <div style="font-size:13px;"><b>Customer:</b> <?= e($snapBill['customer_name'] ?? '—') ?></div>
            <div style="font-size:13px;"><b>Mobile:</b> <?= e($snapBill['mobile_number'] ?? '—') ?></div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Item</th><th>Qty</th><th>Price</th><th>Discount</th><th>Net Rate</th><th>Total</th></tr></thead>
                <tbody>
                <?php foreach ($snapItems as $si): $siNetRate = ($si['price'] ?? 0) * (1 - (($si['discount'] ?? 0) / 100)); ?>
                    <tr>
                        <td><?= e($si['item_name'] ?? '') ?></td>
                        <td><?= e($si['quantity'] ?? '') ?></td>
                        <td>₹<?= money($si['price'] ?? 0) ?></td>
                        <td><?= money($si['discount'] ?? 0) ?>%</td>
                        <td>₹<?= money($siNetRate) ?></td>
                        <td>₹<?= money($si['total'] ?? 0) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="text-align:right;margin-top:10px;font-size:14px;">
            <b>Grand Total (at that time): ₹<?= money($snapBill['grand_total'] ?? 0) ?></b>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require 'includes/layout_bottom.php'; ?>
