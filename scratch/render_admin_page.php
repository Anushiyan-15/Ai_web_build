<?php
// scratch/render_admin_page.php — CLI harness: render a generated admin page
// with a faked admin session (read-only check, never writes).
// Usage: php scratch/render_admin_page.php <adminDir> <page.php> [editId]
$adminDir = rtrim($argv[1] ?? '', '/');
$page = $argv[2] ?? 'customers.php';
$edit = $argv[3] ?? '';
if (!is_dir($adminDir)) { fwrite(STDERR, "bad admin dir\n"); exit(1); }
if (session_status() === PHP_SESSION_NONE) @session_start();
$_SESSION['admin_auth'] = true;
$_SESSION['admin_username'] = 'city_express_admin';
$_SESSION['pak_csrf'] = 'harness';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/' . $page;
$_GET = $edit !== '' ? ['edit' => $edit] : [];
$file = $adminDir . '/' . $page;
ob_start();
include $file;
$html = (string)ob_get_clean();

$nav = [];
if (preg_match_all('/<a href="([^"]+)" class="nav-item[^"]*">([^<]*)</', $html, $m, PREG_SET_ORDER)) {
    foreach ($m as $x) $nav[] = trim($x[2]) . ' -> ' . $x[1];
}
echo "PAGE {$page} (" . strlen($html) . " bytes)\n";
echo "NAV: " . implode(' | ', $nav) . "\n";
if (preg_match_all('/<t[hd][^>]*>(.*?)<\/t[hd]>/s', $html, $rows, PREG_SET_ORDER)) {
    $cells = [];
    foreach ($rows as $r) $cells[] = trim(preg_replace('/\s+/', ' ', strip_tags($r[1])));
    echo 'TABLE: ' . implode(' | ', array_slice($cells, 0, 40)) . "\n";
}
$cards = [];
if (preg_match_all('/<h2[^>]*>(.*?)<\/h2>/s', $html, $h)) {
    foreach ($h[1] as $x) $cards[] = trim(preg_replace('/\s+/', ' ', strip_tags($x)));
}
echo 'H2: ' . implode(' | ', $cards) . "\n";
if (preg_match('/flash-err[^>]*>(.*?)<\/div>/s', $html, $e)) {
    echo 'FLASH-ERR: ' . trim(strip_tags($e[1])) . "\n";
}
