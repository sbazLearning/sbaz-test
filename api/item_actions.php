<?php
require_once '../includes/functions.php';
require_once '../config/db.php';

if (!is_logged_in()) {
    json_out(['success' => false, 'message' => 'Not logged in'], 401);
}

$action = $_REQUEST['action'] ?? '';

/**
 * Base SELECT that computes the EFFECTIVE discount for each item:
 * if the item belongs to a discount group, the group's discount wins;
 * otherwise the item's own discount is used. `discount` in the result
 * is always the effective one (what actually applies on a bill).
 * `own_discount` is the item's individual value, used for editing.
 */
function items_select_sql($where = '') {
    return "
        SELECT
            i.id, i.item_name, i.short_name, i.price,
            i.discount AS own_discount,
            CASE WHEN i.discount_group_id IS NOT NULL THEN g.discount ELSE i.discount END AS discount,
            i.discount_group_id,
            g.group_name,
            i.created_at, i.updated_at
        FROM items i
        LEFT JOIN discount_groups g ON g.id = i.discount_group_id
        $where
    ";
}

switch ($action) {

    case 'save': // add or update
        $id         = (int)($_POST['id'] ?? 0);
        $item_name  = trim($_POST['item_name'] ?? '');
        $short_name = trim($_POST['short_name'] ?? '');
        $price      = is_numeric($_POST['price'] ?? null) ? (float)$_POST['price'] : 0;
        $discount   = is_numeric($_POST['discount'] ?? null) ? (float)$_POST['discount'] : 0;
        $group_id   = (int)($_POST['discount_group_id'] ?? 0);
        $group_id   = $group_id > 0 ? $group_id : null;

        if ($item_name === '') {
            json_out(['success' => false, 'message' => 'Item name is required.']);
        }
        if ($discount < 0 || $discount > 100) {
            json_out(['success' => false, 'message' => 'Discount must be a percentage between 0 and 100.']);
        }

        $short_name = $short_name !== '' ? $short_name : null;

        try {
            if ($short_name !== null) {
                $chk = $pdo->prepare("SELECT id FROM items WHERE short_name = ? AND id != ?");
                $chk->execute([$short_name, $id]);
                if ($chk->fetch()) {
                    json_out(['success' => false, 'message' => 'This shortcut name is already used by another item.']);
                }
            }

            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE items SET item_name=?, short_name=?, price=?, discount=?, discount_group_id=? WHERE id=?");
                $stmt->execute([$item_name, $short_name, $price, $discount, $group_id, $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO items (item_name, short_name, price, discount, discount_group_id) VALUES (?,?,?,?,?)");
                $stmt->execute([$item_name, $short_name, $price, $discount, $group_id]);
                $id = $pdo->lastInsertId();
            }
            json_out(['success' => true, 'message' => 'Item saved.', 'id' => $id]);
        } catch (Exception $e) {
            json_out(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;

    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM items WHERE id=?");
        $stmt->execute([$id]);
        json_out(['success' => true, 'message' => 'Item deleted.']);
        break;

    case 'list':
        $q = trim($_GET['q'] ?? '');
        if ($q !== '') {
            $stmt = $pdo->prepare(items_select_sql("WHERE i.item_name LIKE ? OR i.short_name LIKE ? ORDER BY i.item_name ASC"));
            $like = "%$q%";
            $stmt->execute([$like, $like]);
        } else {
            $stmt = $pdo->query(items_select_sql("ORDER BY i.item_name ASC"));
        }
        json_out(['success' => true, 'items' => $stmt->fetchAll()]);
        break;

    case 'lookup': // used by billing page - lookup by short_name or item name (exact/best match)
        $term = trim($_GET['term'] ?? '');
        if ($term === '') {
            json_out(['success' => false]);
        }
        // Try exact short_name match first
        $stmt = $pdo->prepare(items_select_sql("WHERE i.short_name = ? LIMIT 1"));
        $stmt->execute([$term]);
        $item = $stmt->fetch();

        if (!$item) {
            $stmt = $pdo->prepare(items_select_sql("WHERE i.item_name = ? LIMIT 1"));
            $stmt->execute([$term]);
            $item = $stmt->fetch();
        }
        if ($item) {
            json_out(['success' => true, 'item' => $item]);
        } else {
            json_out(['success' => false]);
        }
        break;

    case 'suggest': // autocomplete while typing - matches shortcode OR item name, anywhere in the text
        $term = trim($_GET['term'] ?? '');
        if ($term === '') json_out(['success' => true, 'items' => []]);
        $startsWith = "$term%";
        $anywhere = "%$term%";
        // Rank "starts with" matches (on either field) above matches found
        // in the middle of the name, so the most relevant items float to top.
        $stmt = $pdo->prepare(items_select_sql("
            WHERE i.short_name LIKE ? OR i.item_name LIKE ?
            ORDER BY
                CASE
                    WHEN i.short_name LIKE ? THEN 0
                    WHEN i.item_name LIKE ? THEN 1
                    ELSE 2
                END,
                i.item_name ASC
            LIMIT 10
        "));
        $stmt->execute([$anywhere, $anywhere, $startsWith, $startsWith]);
        json_out(['success' => true, 'items' => $stmt->fetchAll()]);
        break;

    default:
        json_out(['success' => false, 'message' => 'Unknown action'], 400);
}
