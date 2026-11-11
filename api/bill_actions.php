<?php
require_once '../includes/functions.php';
require_once '../config/db.php';

if (!is_logged_in()) {
    json_out(['success' => false, 'message' => 'Not logged in'], 401);
}

$action = $_REQUEST['action'] ?? '';

if ($action === 'save') {
    $customer_name  = trim($_POST['customer_name'] ?? '');
    $mobile_number  = trim($_POST['mobile_number'] ?? '');
    $bill_date      = $_POST['bill_date'] ?? date('Y-m-d');
    $notes          = trim($_POST['notes'] ?? '');
    $itemsJson      = $_POST['items'] ?? '[]';
    $items          = json_decode($itemsJson, true);

    if (!is_array($items) || count($items) === 0) {
        json_out(['success' => false, 'message' => 'Please add at least one item to the bill.']);
    }

    try {
        $pdo->beginTransaction();

        $subtotal = 0;
        $totalDiscount = 0;
        $grandTotal = 0;
        $cleanItems = [];

        foreach ($items as $it) {
            $item_name  = trim($it['item_name'] ?? '');
            $short_name = trim($it['short_name'] ?? '');
            $qty        = isset($it['quantity']) && is_numeric($it['quantity']) ? (float)$it['quantity'] : 0;
            $price      = isset($it['price']) && is_numeric($it['price']) ? (float)$it['price'] : 0;
            $discountPct = isset($it['discount']) && is_numeric($it['discount']) ? (float)$it['discount'] : 0;
            $discountPct = max(0, min(100, $discountPct)); // clamp 0-100
            $item_id    = isset($it['item_id']) && $it['item_id'] ? (int)$it['item_id'] : null;

            if ($item_name === '' || $qty <= 0) continue;

            $lineSubtotal = $qty * $price;
            $lineDiscount = $lineSubtotal * ($discountPct / 100);
            $lineTotal = $lineSubtotal - $lineDiscount;

            $subtotal += $lineSubtotal;
            $totalDiscount += $lineDiscount;
            $grandTotal += $lineTotal;

            $cleanItems[] = [
                'item_id' => $item_id, 'item_name' => $item_name, 'short_name' => $short_name,
                'quantity' => $qty, 'price' => $price, 'discount' => $discountPct, 'total' => $lineTotal
            ];
        }

        if (count($cleanItems) === 0) {
            $pdo->rollBack();
            json_out(['success' => false, 'message' => 'Please add at least one valid item (with quantity) to the bill.']);
        }

        $billNumber = next_bill_number($pdo);
        $customerId = find_customer_by_mobile($pdo, $mobile_number); // only link if they were explicitly added under Customers

        $stmt = $pdo->prepare("INSERT INTO bills (bill_number, customer_id, customer_name, mobile_number, bill_date, subtotal, total_discount, grand_total, notes)
                                VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$billNumber, $customerId, $customer_name, $mobile_number, $bill_date, $subtotal, $totalDiscount, $grandTotal, $notes]);
        $billId = $pdo->lastInsertId();

        $stmt2 = $pdo->prepare("INSERT INTO bill_items (bill_id, item_id, item_name, short_name, quantity, price, discount, total)
                                 VALUES (?,?,?,?,?,?,?,?)");
        foreach ($cleanItems as $ci) {
            $stmt2->execute([$billId, $ci['item_id'], $ci['item_name'], $ci['short_name'], $ci['quantity'], $ci['price'], $ci['discount'], $ci['total']]);
        }

        $pdo->commit();
        json_out(['success' => true, 'message' => 'Bill saved successfully.', 'bill_id' => $billId, 'bill_number' => $billNumber]);

    } catch (Exception $e) {
        $pdo->rollBack();
        json_out(['success' => false, 'message' => 'Error saving bill: ' . $e->getMessage()]);
    }
}

if ($action === 'update') {
    $bill_id        = (int)($_POST['bill_id'] ?? 0);
    $customer_name  = trim($_POST['customer_name'] ?? '');
    $mobile_number  = trim($_POST['mobile_number'] ?? '');
    $bill_date      = $_POST['bill_date'] ?? date('Y-m-d');
    $notes          = trim($_POST['notes'] ?? '');
    $itemsJson      = $_POST['items'] ?? '[]';
    $items          = json_decode($itemsJson, true);

    if ($bill_id <= 0) {
        json_out(['success' => false, 'message' => 'Invalid bill.']);
    }
    if (!is_array($items) || count($items) === 0) {
        json_out(['success' => false, 'message' => 'Please keep at least one item on the bill.']);
    }

    try {
        $pdo->beginTransaction();

        // Fetch the bill and its current items so we can keep a full
        // snapshot of "before" this edit, in the history table.
        $stmt = $pdo->prepare("SELECT * FROM bills WHERE id = ? FOR UPDATE");
        $stmt->execute([$bill_id]);
        $existingBill = $stmt->fetch();

        if (!$existingBill) {
            $pdo->rollBack();
            json_out(['success' => false, 'message' => 'Bill not found.']);
        }

        $stmt2 = $pdo->prepare("SELECT * FROM bill_items WHERE bill_id = ?");
        $stmt2->execute([$bill_id]);
        $existingItems = $stmt2->fetchAll();

        $snapshot = json_encode([
            'bill' => $existingBill,
            'items' => $existingItems,
        ]);

        $editedBy = $_SESSION['username'] ?? null;
        $histStmt = $pdo->prepare("INSERT INTO bill_edit_history (bill_id, edited_by, old_snapshot) VALUES (?,?,?)");
        $histStmt->execute([$bill_id, $editedBy, $snapshot]);

        // Recalculate totals from the new item list
        $subtotal = 0;
        $totalDiscount = 0;
        $grandTotal = 0;
        $cleanItems = [];

        foreach ($items as $it) {
            $item_name  = trim($it['item_name'] ?? '');
            $short_name = trim($it['short_name'] ?? '');
            $qty        = isset($it['quantity']) && is_numeric($it['quantity']) ? (float)$it['quantity'] : 0;
            $price      = isset($it['price']) && is_numeric($it['price']) ? (float)$it['price'] : 0;
            $discountPct = isset($it['discount']) && is_numeric($it['discount']) ? (float)$it['discount'] : 0;
            $discountPct = max(0, min(100, $discountPct));
            $item_id    = isset($it['item_id']) && $it['item_id'] ? (int)$it['item_id'] : null;

            if ($item_name === '' || $qty <= 0) continue;

            $lineSubtotal = $qty * $price;
            $lineDiscount = $lineSubtotal * ($discountPct / 100);
            $lineTotal = $lineSubtotal - $lineDiscount;

            $subtotal += $lineSubtotal;
            $totalDiscount += $lineDiscount;
            $grandTotal += $lineTotal;

            $cleanItems[] = [
                'item_id' => $item_id, 'item_name' => $item_name, 'short_name' => $short_name,
                'quantity' => $qty, 'price' => $price, 'discount' => $discountPct, 'total' => $lineTotal
            ];
        }

        if (count($cleanItems) === 0) {
            $pdo->rollBack();
            json_out(['success' => false, 'message' => 'Please keep at least one valid item (with quantity) on the bill.']);
        }

        $customerId = find_customer_by_mobile($pdo, $mobile_number); // only link if they were explicitly added under Customers

        $upd = $pdo->prepare("UPDATE bills SET customer_id=?, customer_name=?, mobile_number=?, bill_date=?, subtotal=?, total_discount=?, grand_total=?, notes=? WHERE id=?");
        $upd->execute([$customerId, $customer_name, $mobile_number, $bill_date, $subtotal, $totalDiscount, $grandTotal, $notes, $bill_id]);

        $del = $pdo->prepare("DELETE FROM bill_items WHERE bill_id = ?");
        $del->execute([$bill_id]);

        $ins = $pdo->prepare("INSERT INTO bill_items (bill_id, item_id, item_name, short_name, quantity, price, discount, total)
                               VALUES (?,?,?,?,?,?,?,?)");
        foreach ($cleanItems as $ci) {
            $ins->execute([$bill_id, $ci['item_id'], $ci['item_name'], $ci['short_name'], $ci['quantity'], $ci['price'], $ci['discount'], $ci['total']]);
        }

        $pdo->commit();
        json_out(['success' => true, 'message' => 'Bill updated successfully.', 'bill_id' => $bill_id, 'bill_number' => $existingBill['bill_number']]);

    } catch (Exception $e) {
        $pdo->rollBack();
        json_out(['success' => false, 'message' => 'Error updating bill: ' . $e->getMessage()]);
    }
}

json_out(['success' => false, 'message' => 'Unknown action'], 400);
