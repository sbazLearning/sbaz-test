<?php
session_start();

function require_login() {
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function is_logged_in() {
    return !empty($_SESSION['user_id']);
}

function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function money($num) {
    return number_format((float)$num, 2);
}

/**
 * Generates the next incremental bill number, e.g. NCSH0001, NCSH0002 ...
 * Stored/updated in the settings table so it always increments even
 * across different days / sessions.
 */
function next_bill_number($pdo) {
    // NOTE: this must always be called from inside an already-open
    // transaction (see api/bill_actions.php) so the increment is safe
    // even if two bills are being saved at the same time.
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'last_bill_number' FOR UPDATE");
    $stmt->execute();
    $last = (int)$stmt->fetchColumn();
    $next = $last + 1;

    $upd = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'last_bill_number'");
    $upd->execute([$next]);

    $prefixStmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'bill_prefix'");
    $prefix = $prefixStmt->fetchColumn() ?: 'NCSH';

    return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
}

function get_setting($pdo, $key, $default = '') {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $val = $stmt->fetchColumn();
    return $val !== false ? $val : $default;
}

/**
 * Generates the next incremental customer code, e.g. CUST00001, CUST00002 ...
 * Must be called from inside an already-open transaction, same pattern
 * as next_bill_number().
 */
function next_customer_code($pdo) {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'last_customer_number' FOR UPDATE");
    $stmt->execute();
    $last = (int)$stmt->fetchColumn();
    $next = $last + 1;

    $upd = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'last_customer_number'");
    $upd->execute([$next]);

    $prefixStmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'customer_prefix'");
    $prefix = $prefixStmt->fetchColumn() ?: 'CUST';

    return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
}

/**
 * Finds an existing customer by mobile number ONLY - never creates one.
 * Used when saving a regular one-time bill: if the mobile number matches
 * someone already in the Customers list (added there on purpose, e.g. for
 * credit/ledger tracking), the bill links to them automatically. Otherwise
 * the bill just keeps its own customer_name/mobile_number text and no new
 * customer record is created.
 */
function find_customer_by_mobile($pdo, $mobile) {
    $mobile = trim($mobile);
    if ($mobile === '') return null;
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE mobile_number = ?");
    $stmt->execute([$mobile]);
    $id = $stmt->fetchColumn();
    return $id ? (int)$id : null;
}

/**
 * Finds an existing customer by mobile number (preferred, since names can
 * be spelled differently) or creates a new one. Returns the customer id.
 * Must be called from inside an already-open transaction.
 * Used only by the explicit "Add Customer" action - regular bills do NOT
 * call this anymore (see find_customer_by_mobile above).
 */
function find_or_create_customer($pdo, $name, $mobile) {
    $name = trim($name);
    $mobile = trim($mobile);

    if ($mobile === '' && $name === '') {
        return null; // walk-in / anonymous customer, nothing to link
    }

    if ($mobile !== '') {
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE mobile_number = ?");
        $stmt->execute([$mobile]);
        $existing = $stmt->fetchColumn();
        if ($existing) {
            // Keep the name reasonably up to date if it changed
            if ($name !== '') {
                $upd = $pdo->prepare("UPDATE customers SET name = ? WHERE id = ?");
                $upd->execute([$name, $existing]);
            }
            return (int)$existing;
        }
    }

    $code = next_customer_code($pdo);
    $ins = $pdo->prepare("INSERT INTO customers (customer_code, name, mobile_number) VALUES (?,?,?)");
    $ins->execute([$code, $name !== '' ? $name : 'Walk-in Customer', $mobile !== '' ? $mobile : null]);
    return (int)$pdo->lastInsertId();
}

function json_out($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
