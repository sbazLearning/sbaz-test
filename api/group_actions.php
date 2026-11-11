<?php
require_once '../includes/functions.php';
require_once '../config/db.php';

if (!is_logged_in()) {
    json_out(['success' => false, 'message' => 'Not logged in'], 401);
}

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    case 'save':
        $id = (int)($_POST['id'] ?? 0);
        $group_name = trim($_POST['group_name'] ?? '');
        $discount = is_numeric($_POST['discount'] ?? null) ? (float)$_POST['discount'] : 0;

        if ($group_name === '') {
            json_out(['success' => false, 'message' => 'Group name is required.']);
        }
        if ($discount < 0 || $discount > 100) {
            json_out(['success' => false, 'message' => 'Discount must be a percentage between 0 and 100.']);
        }

        try {
            $chk = $pdo->prepare("SELECT id FROM discount_groups WHERE group_name = ? AND id != ?");
            $chk->execute([$group_name, $id]);
            if ($chk->fetch()) {
                json_out(['success' => false, 'message' => 'A group with this name already exists.']);
            }

            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE discount_groups SET group_name=?, discount=? WHERE id=?");
                $stmt->execute([$group_name, $discount, $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO discount_groups (group_name, discount) VALUES (?,?)");
                $stmt->execute([$group_name, $discount]);
                $id = $pdo->lastInsertId();
            }
            json_out(['success' => true, 'message' => 'Group saved. All items in this group now use this discount.', 'id' => $id]);
        } catch (Exception $e) {
            json_out(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;

    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        // Items in this group simply fall back to their own individual discount (FK is ON DELETE SET NULL)
        $stmt = $pdo->prepare("DELETE FROM discount_groups WHERE id=?");
        $stmt->execute([$id]);
        json_out(['success' => true, 'message' => 'Group deleted. Its items now use their own individual discount instead.']);
        break;

    case 'list':
        $stmt = $pdo->query("
            SELECT g.*, COUNT(i.id) AS item_count
            FROM discount_groups g
            LEFT JOIN items i ON i.discount_group_id = g.id
            GROUP BY g.id
            ORDER BY g.group_name ASC
        ");
        json_out(['success' => true, 'groups' => $stmt->fetchAll()]);
        break;

    default:
        json_out(['success' => false, 'message' => 'Unknown action'], 400);
}
