<?php
// tests/admin_harness.php — subprocess worker for test-admin-login-crud.php.
// Usage: php admin_harness.php <adminDir> <action> <jsonArg>
// Fixed session id + save path so sequential calls share one admin login.
// Prints single-line JSON. Actions: login|get|save|delete.
$adminDir = $argv[1] ?? '';
$action = $argv[2] ?? '';
// Args arrive via JSON file (Windows argv mangling breaks inline JSON).
$argFile = $argv[3] ?? '';
$arg = is_file($argFile) ? (json_decode(@file_get_contents($argFile), true) ?: []) : [];

$err = function ($m) { echo json_encode(['ok' => false, 'error' => $m]); exit; };
if (!is_dir($adminDir)) $err('no admin dir');

$sessDir = sys_get_temp_dir() . '/wc-harness-sess';
if (!is_dir($sessDir)) @mkdir($sessDir, 0755, true);
$sidFile = $sessDir . '/wc-sid.json';
session_save_path($sessDir);
// Login regenerates the id — later workers read the fresh id from $sidFile.
$sid = 'wcharness01';
if ($action !== 'login' && is_file($sidFile)) {
    $sj = json_decode(@file_get_contents($sidFile), true);
    if (!empty($sj['sid'])) $sid = preg_replace('/[^a-zA-Z0-9,-]/', '', (string)$sj['sid']);
}
session_id($sid);
session_start();

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
chdir($adminDir);

if ($action === 'login') {
    unset($_SESSION['admin_auth']);
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['username' => $arg['user'] ?? '', 'password' => $arg['pass'] ?? ''];
    $_GET = [];
    // login.php calls exit() on success — report from a shutdown handler
    // so both paths (redirect-exit and form render) produce JSON.
    $GLOBALS['__login_done'] = false;
    register_shutdown_function(function () use ($sidFile) {
        if (!empty($GLOBALS['__login_done'])) return;
        @file_put_contents($sidFile, json_encode(['sid' => session_id()]));
        echo json_encode(['ok' => true, 'auth' => !empty($_SESSION['admin_auth'])]);
    });
    ob_start();
    include $adminDir . '/login.php';
    ob_end_clean();
    $GLOBALS['__login_done'] = true;
    echo json_encode(['ok' => true, 'auth' => !empty($_SESSION['admin_auth'])]);
    exit;
}

if (empty($_SESSION['admin_auth'])) { echo json_encode(['ok' => false, 'error' => 'not logged in']); exit; }

if ($action === 'change_password') {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SESSION['admin_csrf'] = 'harnesstoken';
    $_POST = ['action' => 'change_password', 'csrf' => 'harnesstoken',
        'current_password' => $arg['current'] ?? '', 'new_password' => $arg['new'] ?? '',
        'confirm_password' => $arg['new'] ?? ''];
    ob_start();
    include $adminDir . '/settings.php';
    $html = (string)ob_get_clean();
    @file_put_contents($sidFile, json_encode(['sid' => session_id()]));
    echo json_encode(['ok' => true, 'changed' => strpos($html, 'successfully') !== false]);
    exit;
}

if ($action === 'get') {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET = [];
    if (!empty($arg['entity'])) $_GET['entity'] = $arg['entity'];
    $_POST = [];
    $map = ['index' => 'index.php', 'manage' => 'manage.php', 'messages' => 'messages.php',
        'content' => 'content.php', 'settings' => 'settings.php', 'features' => 'features.php'];
    $file = $map[$arg['page'] ?? ''] ?? '';
    if ($file === '' || !is_file($adminDir . '/' . $file)) $err('bad page');
    ob_start();
    include $adminDir . '/' . $file;
    $html = (string)ob_get_clean();
    $found = [];
    foreach ((array)($arg['needles'] ?? []) as $n) $found[$n] = strpos($html, $n) !== false;
    echo json_encode(['ok' => true, 'len' => strlen($html), 'found' => $found]);
    exit;
}

if ($action === 'save' || $action === 'delete') {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SESSION['admin_csrf'] = 'harnesstoken';
    $_GET = ['entity' => $arg['entity'] ?? ''];
    if ($action === 'save') {
        $_POST = array_merge(['action' => 'save_item', 'csrf' => 'harnesstoken',
            'item_id' => $arg['item_id'] ?? ''], (array)($arg['record'] ?? []));
    } else {
        $_POST = ['action' => 'delete_item', 'csrf' => 'harnesstoken', 'item_id' => $arg['item_id'] ?? ''];
    }
    ob_start();
    include $adminDir . '/manage.php';
    $html = (string)ob_get_clean();
    $ent = preg_replace('/[^a-z0-9_]/i', '', (string)($arg['entity'] ?? ''));
    $rows = json_decode(@file_get_contents($adminDir . '/data_' . $ent . '.json') ?: '[]', true) ?: [];
    echo json_encode(['ok' => true, 'count' => count($rows), 'rows' => array_slice($rows, 0, 3),
        'msg_ok' => (strpos($html, 'successfully') !== false), 'msg_warn' => (strpos($html, '⚠️') !== false)]);
    exit;
}

$err('unknown action');
