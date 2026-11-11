<?php
require_once 'includes/functions.php';
require_once 'config/db.php';
require_login();

$page_title = 'Backup / Restore';
$active = 'backup';

$message = '';
$messageType = '';

// ---------- Handle Backup Download ----------
if (isset($_GET['download']) && $_GET['download'] === '1') {
    $tables = ['users', 'discount_groups', 'items', 'customers', 'bills', 'customer_payments', 'bill_items', 'bill_edit_history', 'settings'];
    $sql = "-- National Cement Sanitary & Hardware - Database Backup\n";
    $sql .= "-- Generated on " . date('Y-m-d H:i:s') . "\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        $sql .= "-- --------------------------------------------------\n";
        $sql .= "-- Table: $table\n";
        $sql .= "-- --------------------------------------------------\n";
        $sql .= "DELETE FROM `$table`;\n";

        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $cols = array_keys($row);
            $vals = array_map(function ($v) use ($pdo) {
                if ($v === null) return 'NULL';
                return $pdo->quote($v);
            }, array_values($row));
            $sql .= "INSERT INTO `$table` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $vals) . ");\n";
        }
        $sql .= "\n";
    }
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

    $filename = 'hardware_shop_backup_' . date('Y-m-d_His') . '.sql';
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($sql));
    echo $sql;
    exit;
}

// ---------- Handle Restore Upload ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['backup_file'])) {
    if ($_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
        $content = file_get_contents($_FILES['backup_file']['tmp_name']);

        // Strip full-line SQL comments first so they don't get bundled
        // together with the real statement that follows them.
        $lines = explode("\n", $content);
        $lines = array_filter($lines, function ($line) {
            return strpos(ltrim($line), '--') !== 0;
        });
        $cleaned = implode("\n", $lines);

        // naive splitting on ";\n" — fine for our own generated backup files
        $statements = preg_split('/;\s*\n/', $cleaned);
        try {
            $pdo->beginTransaction();
            foreach ($statements as $stmt) {
                $stmt = trim($stmt);
                if ($stmt === '') continue;
                $pdo->exec($stmt);
            }
            $pdo->commit();
            $message = 'Database restored successfully from backup file.';
            $messageType = 'success';
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'Restore failed: ' . $e->getMessage();
            $messageType = 'error';
        }
    } else {
        $message = 'File upload failed. Please try again.';
        $messageType = 'error';
    }
}

require 'includes/layout_top.php';
?>

<?php if ($message): ?>
<div class="card" style="margin-bottom:18px; <?= $messageType === 'success' ? 'border-left:4px solid #16a34a' : 'border-left:4px solid #dc2626' ?>">
    <b><?= $messageType === 'success' ? '✅' : '⚠️' ?> <?= e($message) ?></b>
</div>
<?php endif; ?>

<div class="grid grid-2">
    <div class="card">
        <div class="card-title">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2"><path d="M12 3v12"/><path d="M7 10l5 5 5-5"/><path d="M4 21h16"/></svg>
            Backup Database
        </div>
        <p style="font-size:13.5px;color:#64748b;">
            Download a complete copy of your shop data (items, bills, login) as a single .sql file.
            Keep this file safe — on a pendrive or in Google Drive — in case something happens to your computer.
        </p>
        <a href="backup.php?download=1" class="btn btn-primary">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>
            Download Backup Now
        </a>
        <p class="hint" style="margin-top:14px;">Tip: Take a backup every evening after closing the shop.</p>
    </div>

    <div class="card">
        <div class="card-title">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2"><path d="M21 9V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9"/><path d="M12 12v8M8 16l4-4 4 4"/></svg>
            Restore Database
        </div>
        <p style="font-size:13.5px;color:#64748b;">
            Upload a previously downloaded backup (.sql) file to restore your data.
            <b>Warning:</b> this will replace your current data with the data in the backup file.
        </p>
        <form method="POST" enctype="multipart/form-data" onsubmit="return confirm('This will REPLACE your current data with the backup file. Continue?');">
            <div class="form-group">
                <input type="file" name="backup_file" accept=".sql" required>
            </div>
            <button type="submit" class="btn btn-outline" style="border-color:#ea580c;color:#ea580c;">
                Restore From Backup
            </button>
        </form>
    </div>
</div>

<?php require 'includes/layout_bottom.php'; ?>
