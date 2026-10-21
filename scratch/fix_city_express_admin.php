<?php
// scratch/fix_city_express_admin.php
// One-off repair for the City Express Travels preview admin:
//   1. re-installs the admin kit (adds customers.php + refreshes entity pages)
//   2. merges the duplicated customer records (c_/cust_ id schemes)
//   3. stores the customer-facing seat label ("6C") next to the canonical
//      fleet seat ("F3") so the admin shows the seat the customer picked
// Run: php scratch/fix_city_express_admin.php [previewId]
$slug = $argv[1] ?? '0c5fb868c5a77b3f74205462029907d6';
$root = dirname(__DIR__);
$adminDir = $root . '/preview/' . $slug . '/admin';
$collDir = $root . '/storage/collections/' . $slug;
if (!is_dir($adminDir)) { fwrite(STDERR, "No admin dir for {$slug}\n"); exit(1); }
require_once $root . '/includes/PublishedAdminKit.php';
require_once $root . '/includes/BusinessOps.php';

function jread(string $f): array {
    if (!is_file($f)) return [];
    $j = json_decode((string)@file_get_contents($f), true);
    return is_array($j) ? $j : [];
}
function jwrite(string $f, array $rows): void {
    file_put_contents($f, json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// ── 1. Re-install the admin kit (entities come from the existing manifest) ──
$mf = $adminDir . '/manifest.json';
$manifest = jread($mf);
$entities = (array)($manifest['entities'] ?? []);
if (!$entities) {
    $entities = [
        ['id' => 'trips', 'name' => 'Trips', 'icon' => "\u{1F68C}"],
        ['id' => 'bookings', 'name' => 'Bookings', 'icon' => "\u{1F3AB}"],
        ['id' => 'payments', 'name' => 'Payments', 'icon' => "\u{1F9FE}"],
    ];
}
$res = pak_install($adminDir, [
    'slug' => $slug,
    'site_name' => (string)($manifest['business']['name'] ?? 'City Express Travels'),
    'order_id' => (string)($manifest['order_id'] ?? ''),
    'admin_entities' => $entities,
]);
echo "admin kit: " . count($res['written'] ?? []) . " files written\n";
foreach ((array)($res['errors'] ?? []) as $e) echo "  ERROR {$e}\n";

// ── 2. Merge duplicated customer records into the canonical store ──
$custFile = $collDir . '/customers.json';
$legacyFile = $collDir . '/data_customers.json';
$rows = jread($custFile);
foreach (jread($legacyFile) as $legacy) {
    $email = strtolower(trim((string)($legacy['email'] ?? '')));
    $phone = trim((string)($legacy['phone'] ?? ''));
    $merged = false;
    foreach ($rows as &$r) {
        $rEmail = strtolower(trim((string)($r['email'] ?? '')));
        $rPhone = trim((string)($r['phone'] ?? ''));
        if (($email !== '' && $rEmail === $email) || ($phone !== '' && $rPhone === $phone)) {
            // Keep the richer record: counts + earliest creation date.
            $r['total_bookings'] = max((int)($r['total_bookings'] ?? 0), (int)($legacy['total_bookings'] ?? 0));
            $r['total_spent'] = max((float)($r['total_spent'] ?? 0), (float)($legacy['total_spent'] ?? 0));
            foreach (['name', 'email', 'phone', 'status'] as $k) {
                if (empty($r[$k]) && !empty($legacy[$k])) $r[$k] = $legacy[$k];
            }
            if (strtotime((string)($legacy['created_at'] ?? '')) < strtotime((string)($r['created_at'] ?? ''))) {
                $r['created_at'] = $legacy['created_at'];
            }
            $r['id'] = bops_customer_record_id((string)($r['email'] ?? $email), (string)($r['phone'] ?? $phone));
            $merged = true;
            break;
        }
    }
    unset($r);
    if (!$merged) $rows[] = $legacy;
}
jwrite($custFile, $rows);
if (is_file($legacyFile)) @unlink($legacyFile);
echo 'customers merged: ' . count($rows) . " record(s)\n";

// Mirror the merged customer list into the admin panel.
$adminCust = $adminDir . '/data_customers.json';
$out = [];
foreach ($rows as $r) {
    $r['updated_at'] = date('c');
    $out[] = $r;
}
jwrite($adminCust, $out);

// ── 3. Seat label the customer actually picked (canonical F3 -> display 6C) ──
$toDisplay = static function (string $seat): string {
    $s = strtoupper(trim($seat));
    if (preg_match('/^([A-Z])([1-9])$/', $s, $m)) {
        $row = ord($m[1]) - ord('A') + 1;
        $col = (int)$m[2];
        return $row . chr(ord('A') + $col - 1);
    }
    return $s;
};
foreach ([[$collDir . '/bookings.json', $adminDir . '/data_bookings.json']] as $pair) {
    [$storeFile, $mirrorFile] = $pair;
    $bookings = jread($storeFile);
    $changed = false;
    foreach ($bookings as &$b) {
        if (!is_array($b)) continue;
        $seats = $b['seats'] ?? [];
        if (!is_array($seats)) $seats = preg_split('/[\s,;|]+/', (string)$seats, -1, PREG_SPLIT_NO_EMPTY);
        $display = array_values(array_filter(array_map($toDisplay, array_map('strval', (array)$seats))));
        if ($display && $display !== (array)$seats) { $b['seats_display'] = $display; $changed = true; }
    }
    unset($b);
    if ($changed) {
        jwrite($storeFile, $bookings);
        jwrite($mirrorFile, $bookings);
        echo 'seat labels updated: ' . basename($storeFile) . "\n";
    }
}
echo "done for {$slug}\n";
