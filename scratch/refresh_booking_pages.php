<?php
// scratch/refresh_booking_pages.php
// Regenerates the generated bookings admin page (same generator as the kit)
// so the SEATS column shows the label the customer picked (seats_display)
// instead of the internal fleet code. Data files are never touched.
$root = dirname(__DIR__);
require_once $root . '/includes/PublishedAdminKit.php';
function jread(string $f): array {
    if (!is_file($f)) return [];
    $j = json_decode((string)@file_get_contents($f), true);
    return is_array($j) ? $j : [];
}
$dirs = [];
foreach (['preview', 'published'] as $group) {
    foreach (glob($root . '/' . $group . '/*/admin', GLOB_ONLYDIR) ?: [] as $d) $dirs[] = $d;
}
$done = 0;
foreach ($dirs as $adminDir) {
    $page = $adminDir . '/bookings.php';
    if (!is_file($page)) continue;
    if (!is_file($adminDir . '/data_bookings.json')) continue;
    $manifest = jread($adminDir . '/manifest.json');
    $entity = null;
    foreach ((array)($manifest['entities'] ?? []) as $e) {
        if (is_array($e) && strtolower(trim((string)($e['id'] ?? ''))) === 'bookings') { $entity = $e; break; }
    }
    if (!$entity) continue;
    if (!isset($entity['singular'])) $entity['singular'] = 'Booking';
    if (!isset($entity['icon'])) $entity['icon'] = "\u{1F3AB}";
    pak_write($page, pak_entity_php($entity));
    echo 'refreshed bookings.php: ' . str_replace($root, '', $adminDir) . "\n";
    $done++;
}
echo "done: {$done} booking page(s) refreshed\n";
