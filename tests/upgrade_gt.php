<?php
// TEMP: upgrade global-travel admin with new kit (code only — data/auth kept).
require __DIR__ . '/../includes/PublishedAdminKit.php';
$root = dirname(__DIR__);
$adminDir = $root . '/published/global-travel/admin';
$authBefore = md5_file($adminDir . '/.auth.json');
$dataBefore = [];
foreach (glob($adminDir . '/data_*.json') ?: [] as $f) $dataBefore[basename($f)] = md5_file($f);
$mf = json_decode(file_get_contents($adminDir . '/manifest.json'), true);
$order = ['order_id' => 'WBL-2026-CB5EDD', 'site_name' => 'global travel',
    'slug' => 'global-travel', 'admin_username' => 'x',
    'admin_email' => '', 'contact_email' => '',
    'admin_password_hash' => 'x', 'admin_entities' => $mf['entities'] ?? []];
$res = pak_install($adminDir, $order);
echo 'errors: ' . json_encode($res['errors']) . "\n";
echo 'server.php: ' . (is_file($adminDir . '/server.php') ? 'yes' : 'NO') . "\n";
echo 'auth same: ' . (md5_file($adminDir . '/.auth.json') === $authBefore ? 'yes' : 'CHANGED!') . "\n";
$kept = true;
foreach ($dataBefore as $b => $h) {
    if (!is_file($adminDir . '/' . $b) || md5_file($adminDir . '/' . $b) !== $h) { $kept = false; echo "DATA CHANGED: $b\n"; }
}
echo 'data kept: ' . ($kept ? 'yes' : 'NO') . "\n";
// Mirror existing rows to the REAL collections tree (Node + PHP lists).
require $adminDir . '/includes/db.php';
foreach (['trips', 'buses', 'routes', 'payments', 'bookings', 'booking', 'bus', 'ticket', 'inquiries'] as $ent) {
    $n = 0;
    foreach (db_read($ent) as $t) {
        if (is_array($t) && isset($t['id']) && db_mirror($ent, (string)$t['id'], $t)) $n++;
    }
    if ($n > 0) echo "mirrored $ent: $n\n";
}
