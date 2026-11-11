<?php
// The "with column lines" version is now the default print_bill.php.
// This file is kept only so any old bookmarks/links don't break.
require_once 'includes/functions.php';
$id = (int)($_GET['id'] ?? 0);
header('Location: print_bill.php?id=' . $id);
exit;
