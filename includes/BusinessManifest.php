<?php
// includes/BusinessManifest.php — Business manifest: the per-website contract
// binding frontend → backend API → JSON storage → admin dashboard.
// Saved OUTSIDE the public web root (storage/sites/<slug>/business.json +
// storage/projects/<orderId>/manifest.json); a read-only copy ships with the
// admin dir (published/<slug>/admin/manifest.json) for dashboard rendering.

require_once __DIR__ . '/BusinessRegistry.php';

if (!function_exists('business_manifest_build')) {

function business_manifest_build(array $analysis, string $slug, string $orderId = ''): array {
    $slug = preg_replace('/[^a-z0-9\-]/i', '', $slug) ?: 'site';
    $apiBase = 'api/business-ops.php';
    $apiMappings = [];
    foreach (($analysis['frontend_mappings'] ?? []) as $fm) {
        $apiMappings[] = [
            'frontend' => $fm['frontend'] ?? '', 'api' => $fm['api'] ?? '',
            'entity' => $fm['entity'] ?? '', 'admin_page' => $fm['admin_page'] ?? '',
            'contract' => ($fm['api'] ?? '') . ' → storage/sites/' . $slug . '/' . ($fm['entity'] ?? '') . '.json → admin/data_' . ($fm['entity'] ?? '') . '.json',
        ];
    }
    $schemas = [];
    foreach (($analysis['entities'] ?? []) as $e) {
        $schemas[$e['id'] ?? 'items'] = [
            'file' => 'storage/sites/' . $slug . '/' . ($e['id'] ?? 'items') . '.json',
            'mirror' => 'published/' . $slug . '/admin/data_' . ($e['id'] ?? 'items') . '.json',
            'fields' => $e['fields'] ?? [], 'sensitive' => !empty($e['sensitive']),
        ];
    }
    return [
        'schema' => 'business-manifest/1',
        'slug' => substr($slug, 0, 60),
        'order_id' => $orderId,
        'business' => ['name' => $analysis['business_name'] ?? 'Business',
            'category' => $analysis['category'] ?? 'generic',
            'label' => $analysis['category_label'] ?? 'General business'],
        'core_workflows' => $analysis['workflows'] ?? [],
        'admin_modules' => $analysis['admin_modules'] ?? [],
        'entities' => $analysis['entities'] ?? [],
        'relationships' => $analysis['relationships'] ?? [],
        'roles' => $analysis['roles'] ?? ['visitor', 'customer', 'admin'],
        'frontend_api_map' => $apiMappings,
        'storage_schemas' => $schemas,
        'business_rules' => $analysis['business_rules'] ?? [],
        'privacy' => $analysis['privacy'] ?? null,
        'sensitive' => !empty($analysis['sensitive']),
        'functions' => $analysis['functions'] ?? [],
        'dashboard_stats' => $analysis['dashboard_stats'] ?? [],
        'assumptions' => $analysis['assumptions'] ?? [],
        'follow_up' => $analysis['follow_up'] ?? [],
        'engine' => $analysis['engine'] ?? 'rule-based',
        'implementation_status' => 'generated',
        'test_status' => 'untested',
        'updated_at' => date('c'),
    ];
}

function _bman_write_atomic(string $file, array $data): bool {
    $dir = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    // Backup previous version (last 10).
    if (is_file($file)) {
        $bdir = $dir . '/backups';
        if (!is_dir($bdir)) @mkdir($bdir, 0755, true);
        @copy($file, $bdir . '/' . basename($file, '.json') . '-' . date('Ymd-His') . '.json');
        foreach (array_slice(glob($bdir . '/*.json') ?: [], 0, max(0, count(glob($bdir . '/*.json') ?: []) - 10)) as $old) @unlink($old);
    }
    $tmp = $file . '.tmp-' . bin2hex(random_bytes(4));
    $ok = @file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    if ($ok === false) return false;
    return @rename($tmp, $file);
}

function business_manifest_save(string $slug, array $manifest, string $orderId = ''): array {
    $slug = preg_replace('/[^a-z0-9\-]/i', '', $slug) ?: 'site';
    $root = defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__) . '/storage';
    $written = [];
    $siteDir = $root . '/sites/' . $slug;
    // Preserve Node-known keys (adminModules/capabilities) for backward compat.
    $legacyFile = $siteDir . '/business.json';
    $legacy = is_file($legacyFile) ? (json_decode(@file_get_contents($legacyFile), true) ?: []) : [];
    $merged = array_merge($legacy, $manifest);
    if (empty($merged['adminModules']) && !empty($manifest['admin_modules'])) {
        $merged['adminModules'] = array_column($manifest['admin_modules'], 'label');
    }
    if (_bman_write_atomic($legacyFile, $merged)) $written[] = $legacyFile;
    if ($orderId !== '') {
        $pf = $root . '/projects/' . preg_replace('/[^A-Za-z0-9\-_]/', '', $orderId) . '/manifest.json';
        if (_bman_write_atomic($pf, $manifest)) $written[] = $pf;
    }
    $adminCopy = dirname(__DIR__) . '/published/' . $slug . '/admin/manifest.json';
    if (is_dir(dirname($adminCopy))) {
        // Admin copy strips nothing sensitive (no secrets live in manifest).
        if (_bman_write_atomic($adminCopy, $manifest)) $written[] = $adminCopy;
    }
    return $written;
}

function business_manifest_load(string $slug): ?array {
    $slug = preg_replace('/[^a-z0-9\-]/i', '', $slug) ?: 'site';
    $root = defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__) . '/storage';
    $f = $root . '/sites/' . $slug . '/business.json';
    if (!is_file($f)) return null;
    $j = json_decode(@file_get_contents($f), true);
    if (!is_array($j) || ($j['schema'] ?? '') !== 'business-manifest/1') return null;
    return $j;
}

function business_manifest_validate(array $m): array {
    $errs = [];
    if (($m['schema'] ?? '') !== 'business-manifest/1') $errs[] = 'schema must be business-manifest/1';
    if (empty($m['slug'])) $errs[] = 'slug required';
    if (empty($m['business']['category'])) $errs[] = 'business.category required';
    if (empty($m['entities']) || !is_array($m['entities'])) $errs[] = 'entities required';
    if (empty($m['admin_modules']) || !is_array($m['admin_modules'])) $errs[] = 'admin_modules required';
    if (empty($m['frontend_api_map'])) $errs[] = 'frontend_api_map required';
    if (empty($m['storage_schemas'])) $errs[] = 'storage_schemas required';
    return [$errs === [], $errs];
}

}
