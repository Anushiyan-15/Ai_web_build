<?php
require_once __DIR__ . '/../includes/PublishedAdminKit.php';

$slugs = ['163c827578b6dd8da8e701f5d890ef83', 'b2f77c107af4843583b67783fcedbf4c', 'd2ecd5c885191b468a23ed8f71380727'];

foreach ($slugs as $slug) {
    $adminDir = __DIR__ . '/../preview/' . $slug . '/admin';
    if (!is_dir($adminDir)) continue;
    
    // Read existing manifest if any to preserve other entities
    $mf = $adminDir . '/manifest.json';
    $existing = [];
    if (is_file($mf)) {
        $m = json_decode(@file_get_contents($mf), true);
        $existing = (array)($m['entities'] ?? []);
    }
    if (empty($existing)) {
        $existing = [
            ['id'=>'trips','name'=>'Trips','icon'=>'🚌'],
            ['id'=>'bookings','name'=>'Bookings','icon'=>'🎫'],
            ['id'=>'payments','name'=>'Payments','icon'=>'🧾'],
        ];
    }
    // Add customers if not present
    $hasCust = false;
    foreach ($existing as $e) {
        if (($e['id'] ?? '') === 'customers') { $hasCust = true; break; }
    }
    if (!$hasCust) {
        $existing[] = ['id'=>'customers','name'=>'Customers','icon'=>'👥'];
    }

    $order = [
        'slug' => $slug,
        'site_name' => 'City Express Travels',
        'admin_entities' => $existing
    ];
    $res = pak_install($adminDir, $order);
    echo "Installed for {$slug}: " . count($res['written'] ?? []) . " files written\n";
}
