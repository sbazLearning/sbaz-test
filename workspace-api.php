<?php
// Sbaz AI Workspace API - nested folder support + delete
// Put this file in the root of the project you want Sbaz AI to edit.
// Set the same token in $TOKEN and in Sbaz AI -> Workspace -> Local server.

$TOKEN = 'chatgpt';
$ROOT = realpath(__DIR__);
$MAX_EDIT = 1024 * 1024; // 1 MB editable text limit

header('Content-Type: application/json; charset=utf-8');

function out($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

$given = $_SERVER['HTTP_X_TOKEN'] ?? '';
if (!hash_equals($TOKEN, $given)) out(['ok'=>false,'error'=>'Unauthorized'], 401);

function clean_rel($path) {
    $path = str_replace('\\', '/', (string)$path);
    $path = preg_replace('#/+#', '/', $path);
    $path = preg_replace('#^\./#', '', $path);
    $path = ltrim($path, '/');
    if ($path === '' || strpos($path, "\0") !== false) return null;
    if (preg_match('#(^|/)\.\.(/|$)#', $path)) return null;
    if (preg_match('#^[A-Za-z]:($|/)#', $path)) return null;
    return $path;
}

function full_path($rel) {
    global $ROOT;
    $rel = clean_rel($rel);
    if ($rel === null) return null;
    $full = $ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $parent = dirname($full);
    $realParent = realpath($parent);
    if ($realParent !== false) {
        $rootPrefix = rtrim($ROOT, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if ($realParent !== $ROOT && strpos($realParent . DIRECTORY_SEPARATOR, $rootPrefix) !== 0) return null;
    }
    return $full;
}

function is_hidden_or_api($name) {
    return $name === '.' || $name === '..' || $name === basename(__FILE__);
}

$action = $_GET['action'] ?? '';

if ($action === 'list') {
    $files = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($it as $file) {
        if (!$file->isFile()) continue;
        $full = $file->getPathname();
        if ($full === __FILE__) continue;
        $rel = str_replace('\\', '/', substr($full, strlen($ROOT) + 1));
        if (strpos($rel, '.git/') === 0 || strpos($rel, 'node_modules/') === 0) continue;
        $size = $file->getSize();
        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        $binaryExt = ['png','jpg','jpeg','gif','webp','ico','pdf','zip','rar','7z','mp3','mp4','mov','avi','woff','woff2','ttf','otf','exe','bin'];
        $isText = !in_array($ext, $binaryExt, true);
        $files[] = [
            'name' => $rel,
            'size' => $size,
            'isText' => $isText,
            'tooBig' => $isText && $size > $MAX_EDIT
        ];
    }
    usort($files, fn($a,$b) => strnatcasecmp($a['name'],$b['name']));
    out(['ok'=>true,'files'=>$files]);
}

if ($action === 'read') {
    $rel = clean_rel($_GET['path'] ?? '');
    $full = $rel === null ? null : full_path($rel);
    if ($full === null || !is_file($full)) out(['ok'=>false,'error'=>'File not found'], 404);
    $size = filesize($full);
    if ($size > $MAX_EDIT) out(['ok'=>true,'content'=>null,'tooBig'=>true,'size'=>$size]);
    $content = file_get_contents($full);
    if ($content === false) out(['ok'=>false,'error'=>'Could not read file'], 500);
    out(['ok'=>true,'content'=>$content,'tooBig'=>false,'size'=>$size]);
}

if ($action === 'save') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) out(['ok'=>false,'error'=>'Invalid JSON'], 400);
    $rel = clean_rel($body['path'] ?? '');
    $content = isset($body['content']) ? (string)$body['content'] : null;
    if ($rel === null || $content === null) out(['ok'=>false,'error'=>'Invalid path or content'], 400);
    $full = full_path($rel);
    if ($full === null) out(['ok'=>false,'error'=>'Invalid path'], 400);
    $parent = dirname($full);
    if (!is_dir($parent) && !mkdir($parent, 0775, true)) out(['ok'=>false,'error'=>'Could not create folder path'], 500);
    if (strlen($content) > $MAX_EDIT) out(['ok'=>false,'error'=>'File too large'], 413);

    // Some Android/mobile filesystems (FAT32/exFAT SD cards, some scoped-storage
    // backed paths KSWEB can see) don't support flock(), which makes a locked write
    // fail even though a plain write would succeed. Try locked first, then fall back.
    error_clear_last();
    $written = @file_put_contents($full, $content, LOCK_EX);
    if ($written === false) {
        error_clear_last();
        $written = @file_put_contents($full, $content);
    }
    if ($written === false) {
        $err = error_get_last();
        $detail = $err ? $err['message'] : 'unknown reason';
        $writable = is_writable($parent) ? 'yes' : 'no';
        out(['ok'=>false,'error'=>"Could not save file — $detail. Folder writable: $writable. On Android, check KSWEB (or your PHP app) has storage/\"All files access\" permission for this folder, and that it isn't on a read-only or SD-card path without write support."], 500);
    }
    out(['ok'=>true,'path'=>$rel,'size'=>filesize($full)]);
}

/* Delete a single file safely inside the workspace root. */
if ($action === 'delete') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) out(['ok'=>false,'error'=>'Invalid JSON'], 400);
    $rel = clean_rel($body['path'] ?? '');
    if ($rel === null) out(['ok'=>false,'error'=>'Invalid path'], 400);

    // Never allow the workspace API itself to be deleted.
    if ($rel === basename(__FILE__)) {
        out(['ok'=>false,'error'=>'Cannot delete workspace API'], 403);
    }

    $full = full_path($rel);
    if ($full === null) out(['ok'=>false,'error'=>'Invalid path'], 400);
    if (!is_file($full)) out(['ok'=>false,'error'=>'File not found'], 404);

    if (!unlink($full)) {
        $err = error_get_last();
        $detail = $err ? $err['message'] : 'unknown reason';
        out(['ok'=>false,'error'=>"Could not delete file — $detail. Check server filesystem permissions."], 500);
    }

    out(['ok'=>true,'path'=>$rel]);
}

out(['ok'=>false,'error'=>'Unknown action'], 400);
