<?php
require_once '../includes/functions.php';
require_once '../config/db.php';

if (!is_logged_in()) {
    json_out(['success' => false, 'message' => 'Not logged in'], 401);
}

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    case 'search': // used for a quick customer picker / autocomplete
        $term = trim($_GET['term'] ?? '');
        if ($term === '') json_out(['success' => true, 'customers' => []]);
        $like = "%$term%";
        $stmt = $pdo->prepare("
            SELECT c.*,
                COALESCE((SELECT SUM(b.grand_total) FROM bills b WHERE b.customer_id = c.id), 0) AS total_billed,
                COALESCE((SELECT SUM(p.amount) FROM customer_payments p WHERE p.customer_id = c.id), 0) AS total_paid
            FROM customers c
            WHERE c.name LIKE ? OR c.mobile_number LIKE ? OR c.customer_code LIKE ?
            ORDER BY c.name ASC LIMIT 10
        ");
        $stmt->execute([$like, $like, $like]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['balance'] = (float)$r['total_billed'] - (float)$r['total_paid'];
        }
        json_out(['success' => true, 'customers' => $rows]);
        break;

    case 'add_payment':
        $customer_id = (int)($_POST['customer_id'] ?? 0);
        $amount = is_numeric($_POST['amount'] ?? null) ? (float)$_POST['amount'] : 0;
        $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
        $notes = trim($_POST['notes'] ?? '');

        if ($customer_id <= 0) {
            json_out(['success' => false, 'message' => 'Invalid customer.']);
        }
        if ($amount <= 0) {
            json_out(['success' => false, 'message' => 'Please enter a valid payment amount.']);
        }

        $chk = $pdo->prepare("SELECT id FROM customers WHERE id = ?");
        $chk->execute([$customer_id]);
        if (!$chk->fetch()) {
            json_out(['success' => false, 'message' => 'Customer not found.']);
        }

        $stmt = $pdo->prepare("INSERT INTO customer_payments (customer_id, amount, payment_date, notes) VALUES (?,?,?,?)");
        $stmt->execute([$customer_id, $amount, $payment_date, $notes]);

        json_out(['success' => true, 'message' => 'Payment recorded.']);
        break;

    case 'delete_payment':
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM customer_payments WHERE id=?");
        $stmt->execute([$id]);
        json_out(['success' => true, 'message' => 'Payment entry removed.']);
        break;

    case 'create': // explicit "Add Customer" - the ONLY way a customer record gets created now
        $name = trim($_POST['name'] ?? '');
        $mobile = trim($_POST['mobile_number'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if ($name === '') {
            json_out(['success' => false, 'message' => 'Customer name is required.']);
        }

        if ($mobile !== '') {
            $chk = $pdo->prepare("SELECT id FROM customers WHERE mobile_number = ?");
            $chk->execute([$mobile]);
            if ($chk->fetch()) {
                json_out(['success' => false, 'message' => 'A customer with this mobile number already exists.']);
            }
        }

        try {
            $pdo->beginTransaction();
            $code = next_customer_code($pdo);
            $stmt = $pdo->prepare("INSERT INTO customers (customer_code, name, mobile_number, address) VALUES (?,?,?,?)");
            $stmt->execute([$code, $name, $mobile !== '' ? $mobile : null, $address !== '' ? $address : null]);
            $id = $pdo->lastInsertId();
            $pdo->commit();
            json_out(['success' => true, 'message' => 'Customer added.', 'id' => $id, 'customer_code' => $code]);
        } catch (Exception $e) {
            $pdo->rollBack();
            json_out(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;

    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        // Bills already linked to this customer simply become unlinked
        // (ON DELETE SET NULL) - their bill history/text is not affected.
        $stmt = $pdo->prepare("DELETE FROM customers WHERE id=?");
        $stmt->execute([$id]);
        json_out(['success' => true, 'message' => 'Customer removed. Their past bills are kept, just no longer linked to a ledger.']);
        break;

    case 'update':
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $mobile = trim($_POST['mobile_number'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if ($id <= 0 || $name === '') {
            json_out(['success' => false, 'message' => 'Customer name is required.']);
        }

        if ($mobile !== '') {
            $chk = $pdo->prepare("SELECT id FROM customers WHERE mobile_number = ? AND id != ?");
            $chk->execute([$mobile, $id]);
            if ($chk->fetch()) {
                json_out(['success' => false, 'message' => 'Another customer already uses this mobile number.']);
            }
        }

        $stmt = $pdo->prepare("UPDATE customers SET name=?, mobile_number=?, address=? WHERE id=?");
        $stmt->execute([$name, $mobile !== '' ? $mobile : null, $address, $id]);
        json_out(['success' => true, 'message' => 'Customer details updated.']);
        break;

    default:
        json_out(['success' => false, 'message' => 'Unknown action'], 400);
}
