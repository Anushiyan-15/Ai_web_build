<?php
// scratch/install_customers_pages.php
// Installs the admin "Customers" page (+ manifest entry) into every
// generated admin kit that has a data_customers.json (the sidebar builds the
// Customers nav entry from that file, so the page MUST exist) and refreshes
// bookings.php where bookings exist (so the seat label the customer picked is
// shown instead of the internal fleet code).
// Usage: php scratch/install_customers_pages.php
$root = dirname(__DIR__);
require_once $root . '/includes/PublishedAdminKit.php';

function jread(string $f): array {
    if (!is_file($f)) return [];
    $j = json_decode((string)@file_get_contents($f), true);
    return is_array($j) ? $j : [];
}
function jwrite(string $f, array $data): void {
    file_put_contents($f, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$dirs = [];
foreach (['preview', 'published'] as $group) {
    foreach (glob($root . '/' . $group . '/*/admin', GLOB_ONLYDIR) ?: [] as $d) $dirs[] = $d;
}
$done = 0;
foreach ($dirs as $adminDir) {
    $custFile = $adminDir . '/data_customers.json';
    $bookFile = $adminDir . '/data_bookings.json';
    if (!is_file($custFile) && !is_file($bookFile)) continue;
    $mf = $adminDir . '/manifest.json';
    $manifest = jread($mf);
    $entities = (array)($manifest['entities'] ?? []);
    $changed = false;
    $hasCust = false;
    foreach ($entities as $e) {
        if (is_array($e) && strtolower(trim((string)($e['id'] ?? ''))) === 'customers') { $hasCust = true; break; }
    }
    if ($hasCust) continue; // already installed
    $cust = pak_customers_entity();
    $entities[] = ['id' => 'customers', 'name' => 'Customers', 'singular' => 'Customer',
        'icon' => $cust['icon'], 'fields' => $cust['fields']];
    $manifest['entities'] = $entities;
    $modules = (array)($manifest['admin_modules'] ?? []);
    $modules[] = ['id' => 'customers', 'label' => 'Customers', 'icon' => $cust['icon'], 'entity' => 'customers'];
    $manifest['admin_modules'] = $modules;
    $manifest['updated_at'] = date('c');
    jwrite($mf, $manifest);
    pak_write($adminDir . '/customers.php', pak_entity_php($cust));
    if (!is_file($custFile)) pak_write($custFile, '[]');
    if (is_file($bookFile) && is_file($mf)) {
        $ents = [];
        foreach ($entities as $e) {
            if (!is_array($e) || empty($e['id'])) continue;
            if ((string)$e['id'] === 'customers') continue;
            $ents[] = $e;
        }
        foreach ($ents as $e) {
            $page = $adminDir . '/' . $e['id'] . '.php';
            if (!is_file($page)) pak_write($page, pak_entity_php($e));
        }
    }
    echo "installed customers page: " . str_replace($root, '', $adminDir) . "\n";
    $done++;
}
echo "done: {$done} admin kit(s) updated\n";
