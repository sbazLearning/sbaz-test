<?php
// Sbaz AI Workspace API - diagnostic/fixed version
// Put this file in: htdocs/Project/workspace-api.php
// Sbaz AI URL: http://localhost:8080/Project/workspace-api.php
// Token: chatgpt

$TOKEN = 'chatgpt';
$ROOT = realpath(__DIR__);
$MAX_EDIT = 1024 * 1024;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out($data, $status=200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($ROOT === false || !is_dir($ROOT)) {
    out(['ok'=>false,'error'=>'Workspace root does not exist'],500);
}

// Sbaz AI sends X-Token.
$given = isset($_SERVER['HTTP_X_TOKEN']) ? (string)$_SERVER['HTTP_X_TOKEN'] : '';
if ($given === '' || !hash_equals($TOKEN,$given)) {
    out(['ok'=>false,'error'=>'Unauthorized'],401);
}

function clean_rel($path) {
    $path = str_replace('\\','/',(string)$path);
    $path = preg_replace('#/+#','/',$path);
    $path = preg_replace('#^\./#','',$path);
    $path = ltrim($path,'/');
    if ($path==='' || strpos($path,"\0")!==false) return null;
    if (preg_match('#(^|/)\.\.(/|$)#',$path)) return null;
    if (preg_match('#^[A-Za-z]:($|/)#',$path)) return null;
    return $path;
}

function full_path($rel) {
    global $ROOT;
    $rel=clean_rel($rel);
    if ($rel===null) return null;

    $full=$ROOT.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$rel);

    if (file_exists($full)) {
        $real=realpath($full);
        if ($real===false) return null;
        $prefix=rtrim($ROOT,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if ($real!==$ROOT && strpos($real.DIRECTORY_SEPARATOR,$prefix)!==0) return null;
        return $real;
    }

    $parent=realpath(dirname($full));
    if ($parent!==false) {
        $prefix=rtrim($ROOT,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if ($parent!==$ROOT && strpos($parent.DIRECTORY_SEPARATOR,$prefix)!==0) return null;
    }
    return $full;
}

function last_error_text() {
    $e=error_get_last();
    return ($e && isset($e['message'])) ? $e['message'] : 'unknown filesystem error';
}

$action=isset($_GET['action']) ? (string)$_GET['action'] : '';

if ($action==='ping') {
    out([
        'ok'=>true,
        'message'=>'Workspace API is running',
        'root'=>$ROOT,
        'rootWritable'=>is_writable($ROOT),
        'phpVersion'=>PHP_VERSION
    ]);
}

if ($action==='list') {
    $files=[];
    $dirs=[];
    try {
        $it=new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($ROOT,FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach($it as $item) {
            $full=$item->getPathname();
            $rel=str_replace('\\','/',substr($full,strlen($ROOT)+1));

            if($rel==='' || strpos($rel,'.git/')===0 || $rel==='.git' || strpos($rel,'node_modules/')===0 || $rel==='node_modules') continue;

            if($item->isDir()) {
                $dirs[]=$rel;
                continue;
            }
            if(!$item->isFile()) continue;
            if(realpath($full)===realpath(__FILE__)) continue;

            $ext=strtolower(pathinfo($rel,PATHINFO_EXTENSION));
            $binary=['png','jpg','jpeg','gif','webp','ico','pdf','zip','rar','7z','mp3','mp4','mov','avi','woff','woff2','ttf','otf','exe','bin'];

            $files[]=[
                'name'=>$rel,
                'size'=>$item->getSize(),
                'isText'=>!in_array($ext,$binary,true),
                'tooBig'=>!in_array($ext,$binary,true) && $item->getSize()>$MAX_EDIT
            ];
        }
    } catch(Throwable $e) {
        out(['ok'=>false,'error'=>'Could not list workspace: '.$e->getMessage()],500);
    }

    sort($dirs,SORT_NATURAL|SORT_FLAG_CASE);
    usort($files,function($a,$b){return strnatcasecmp($a['name'],$b['name']);});
    out(['ok'=>true,'files'=>$files,'dirs'=>$dirs]);
}

if ($action==='mkdir') {
    $body=json_decode(file_get_contents('php://input'),true);
    if(!is_array($body)) out(['ok'=>false,'error'=>'Invalid JSON'],400);

    $rel=clean_rel(isset($body['path'])?$body['path']:'');
    if($rel===null) out(['ok'=>false,'error'=>'Invalid folder path'],400);

    $full=full_path($rel);
    if($full===null) out(['ok'=>false,'error'=>'Invalid workspace path'],400);

    if(is_dir($full)) out(['ok'=>true,'path'=>$rel,'alreadyExists'=>true]);

    $made=@mkdir($full,0775,true);
    if(!$made && !is_dir($full)) {
        out([
            'ok'=>false,
            'error'=>'Could not create folder: '.last_error_text(),
            'path'=>$rel,
            'parent'=>dirname($full),
            'parentWritable'=>is_dir(dirname($full)) ? is_writable(dirname($full)) : false
        ],500);
    }

    @chmod($full,0775);
    out(['ok'=>true,'path'=>$rel,'alreadyExists'=>false]);
}

if ($action==='read') {
    $rel=clean_rel(isset($_GET['path'])?$_GET['path']:'');
    $full=$rel!==null ? full_path($rel) : null;

    if($full===null || !is_file($full))
        out(['ok'=>false,'error'=>'File not found: '.$rel],404);

    $size=@filesize($full);
    if($size===false)$size=0;

    if($size>$MAX_EDIT)
        out(['ok'=>true,'content'=>null,'tooBig'=>true,'size'=>$size]);

    $content=@file_get_contents($full);
    if($content===false)
        out(['ok'=>false,'error'=>'Could not read file: '.last_error_text()],500);

    out(['ok'=>true,'content'=>$content,'tooBig'=>false,'size'=>$size]);
}

if ($action==='save') {
    $body=json_decode(file_get_contents('php://input'),true);

    if(!is_array($body))
        out(['ok'=>false,'error'=>'Invalid JSON received by PHP'],400);

    $rel=clean_rel(isset($body['path'])?$body['path']:'');
    $content=array_key_exists('content',$body)?(string)$body['content']:null;

    if($rel===null || $content===null)
        out(['ok'=>false,'error'=>'Invalid path or content'],400);

    if(strlen($content)>$MAX_EDIT)
        out(['ok'=>false,'error'=>'File is larger than 1 MB'],413);

    $full=full_path($rel);
    if($full===null)
        out(['ok'=>false,'error'=>'Invalid workspace path: '.$rel],400);

    $parent=dirname($full);

    // Ensure nested folders exist.
    if(!is_dir($parent)) {
        $mkErr='';
        set_error_handler(function($s,$m)use(&$mkErr){$mkErr=$m;return true;});
        $made=mkdir($parent,0775,true);
        restore_error_handler();

        if(!$made && !is_dir($parent)) {
            out([
                'ok'=>false,
                'error'=>'Cannot create folder: '.($mkErr?:last_error_text()),
                'path'=>$rel,
                'folder'=>$parent
            ],500);
        }
    }

    clearstatcache(true,$full);
    clearstatcache(true,$parent);

    // Important for Android/local-server files copied with read-only mode.
    @chmod($parent,0775);
    if(is_file($full)) @chmod($full,0664);

    clearstatcache(true,$full);
    clearstatcache(true,$parent);

    $exists=is_file($full);
    $fw=is_dir($parent) ? is_writable($parent) : false;
    $filew=$exists ? is_writable($full) : null;

    // Try normal write first.
    $writeErr='';
    set_error_handler(function($s,$m)use(&$writeErr){$writeErr=$m;return true;});
    $written=file_put_contents($full,$content);
    restore_error_handler();

    if($written!==false) {
        clearstatcache(true,$full);
        out([
            'ok'=>true,
            'path'=>$rel,
            'size'=>(int)filesize($full)
        ]);
    }

    // Try temp + rename if normal write failed.
    $tmpErr='';
    $tmp=false;

    set_error_handler(function($s,$m)use(&$tmpErr){$tmpErr=$m;return true;});
    $tmp=tempnam($parent,'.sbaz_');
    restore_error_handler();

    if($tmp!==false) {
        $tw=false;

        set_error_handler(function($s,$m)use(&$tmpErr){$tmpErr=$m;return true;});
        $tw=file_put_contents($tmp,$content);
        restore_error_handler();

        if($tw!==false) {
            @chmod($tmp,0664);
            if(@rename($tmp,$full)) {
                clearstatcache(true,$full);
                out([
                    'ok'=>true,
                    'path'=>$rel,
                    'size'=>(int)filesize($full),
                    'method'=>'temp-rename'
                ]);
            }
            @unlink($tmp);
        } else {
            @unlink($tmp);
        }
    }

    clearstatcache(true,$full);
    clearstatcache(true,$parent);

    // Put the REAL reason directly in "error" because the current Sbaz AI
    // frontend displays j.error and hides j.detail.
    $reason=$writeErr ?: $tmpErr ?: last_error_text();

    out([
        'ok'=>false,
        'error'=>'SAVE FAILED: '.$reason,
        'path'=>$rel,
        'fileExists'=>is_file($full),
        'fileWritable'=>is_file($full)?is_writable($full):null,
        'folderWritable'=>is_dir($parent)?is_writable($parent):false,
        'rootWritable'=>is_writable($ROOT),
        'root'=>$ROOT,
        'parent'=>$parent
    ],500);
}

out(['ok'=>false,'error'=>'Unknown action: '.$action],400);
?>
