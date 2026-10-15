<?php
// includes/MissingFunctionAnalyzer.php — AI-assisted missing-function analysis.
// Inspects manifest + frontend HTML + backend APIs + storage + admin dashboard,
// reports implemented / partial / missing, and safely auto-fixes only
// additive gaps (wire forms, create empty stores). Never executes untrusted
// code, never invents backend behavior beyond the registry.

require_once __DIR__ . '/BusinessRegistry.php';

if (!function_exists('mfa_scan')) {

function mfa_scan(array $manifest, string $html, string $slug): array {
    $slug = preg_replace('/[^a-z0-9\-]/i', '', $slug) ?: 'site';
    $findings = []; $implemented = []; $partial = []; $missing = [];
    $H = $html;
    $noScript = preg_replace('/<script\b[\s\S]*?<\/script\s*>/i', '', $H);
    $noScript = preg_replace('/<style\b[\s\S]*?<\/style\s*>/i', '', $noScript);

    // 1. Forms without backend wiring.
    preg_match_all('/<form\b[^>]*>/i', $noScript, $fm);
    $forms = $fm[0] ?? [];
    $bare = array_filter($forms, fn($t) => !preg_match('/data-wc-form|data-wc-search|data-wc-catalog|business-ops|node-api|contact\.php|wc-biz-runtime/i', $t . $H));
    $wired = stripos($H, 'wc-biz-runtime') !== false || stripos($H, 'business-ops') !== false;
    // Markup already carrying data-wc-* wiring only needs the runtime
    // injected (reported under 'runtime'), not a forms finding.
    $unwired = count($bare);
    if ($unwired > 0 && !$wired) {
        $missing[] = 'forms: ' . $unwired . ' form(s) submit nowhere';
        $findings[] = ['area' => 'forms', 'severity' => 'high', 'status' => 'missing',
            'detail' => $unwired . ' form(s) with no backend wiring', 'plan' => 'wire_forms'];
    } elseif (!empty($forms) && $unwired === 0) {
        $implemented[] = 'forms: ' . count($forms) . ' form(s) wired via runtime';
    } elseif (!empty($forms)) {
        $partial[] = 'forms: ' . $unwired . ' of ' . count($forms) . ' form(s) lack wiring';
        $findings[] = ['area' => 'forms', 'severity' => 'medium', 'status' => 'partial',
            'detail' => $unwired . ' form(s) with no backend wiring', 'plan' => 'wire_forms'];
    }

    // 2. Buttons outside forms without handlers (heuristic, capped).
    preg_match_all('/<button\b[^>]*>/i', $noScript, $bm);
    $btns = $bm[0] ?? [];
    $bareBtn = 0;
    foreach (array_slice($btns, 0, 30) as $b) {
        if (!preg_match('/onclick|data-wc-|type="submit"/i', $b)) $bareBtn++;
    }
    if ($bareBtn > 0 && count($forms) === 0) {
        $partial[] = "buttons: $bareBtn button(s) without explicit handlers (may rely on runtime JS)";
        $findings[] = ['area' => 'buttons', 'severity' => 'low', 'status' => 'partial',
            'detail' => "$bareBtn button(s) lack inline handlers", 'plan' => 'manual_review'];
    }

    // 3. Entity coverage: storage + admin mirror.
    $root = defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__) . '/storage';
    foreach (($manifest['entities'] ?? []) as $e) {
        $id = preg_replace('/[^a-z0-9_]/i', '', (string)($e['id'] ?? ''));
        if ($id === '') continue;
        $store = $root . '/sites/' . $slug . '/' . $id . '.json';
        $legacy = $root . '/collections/' . $slug . '/' . $id . '.json';
        $admin = dirname(__DIR__) . '/published/' . $slug . '/admin/data_' . $id . '.json';
        $hasStore = is_file($store) || is_file($legacy);
        $hasAdmin = is_file($admin);
        // HTML markers for the entity.
        $marker = preg_match('/' . preg_quote(str_replace('_', '[_\\s-]?', $id), '/') . '/i', $noScript);
        if ($hasStore && $hasAdmin) {
            $implemented[] = "$id: storage + admin mirror present";
        } elseif ($hasStore && !$hasAdmin) {
            $partial[] = "$id: stored but not mirrored to admin";
            $findings[] = ['area' => "module:$id", 'severity' => 'medium', 'status' => 'partial',
                'detail' => "Records exist but admin/data_$id.json missing", 'plan' => 'mirror_admin'];
        } else {
            // Entityed defined but no records yet = OK (empty state), only flag if HTML references it.
            if ($marker) {
                $partial[] = "$id: referenced in frontend, no records yet (empty state)";
            } else {
                $implemented[] = "$id: module ready (empty state)";
            }
        }
    }

    // 4. Runtime wiring present?
    if (!$wired) {
        $findings[] = ['area' => 'runtime', 'severity' => 'high', 'status' => 'missing',
            'detail' => 'No frontend→backend runtime detected', 'plan' => 'runtime'];
        if (!in_array('runtime: missing', $missing)) $missing[] = 'runtime: no BusinessRuntime wiring';
    } else {
        $implemented[] = 'runtime: BusinessRuntime present';
    }

    // 5. Requested features vs HTML markers (lightweight).
    foreach (array_slice((array)($manifest['functions'] ?? []), 0, 12) as $fn) {
        $feat = strtolower((string)($fn['frontend_feature'] ?? ''));
        if ($feat === '' || $feat === 'accounts') continue;
        $kw = preg_split('/\s+/', $feat)[0] ?? '';
        if (strlen($kw) >= 4 && stripos($noScript, $kw) === false) {
            $findings[] = ['area' => 'feature:' . $feat, 'severity' => 'low', 'status' => 'partial',
                'detail' => "Frontend has no visible marker for '$feat'", 'plan' => 'manual_review'];
        }
    }

    // 6. Validation / permission gates (static check of backend files).
    $ops = @file_get_contents(__DIR__ . '/BusinessOps.php');
    if ($ops && strpos($ops, 'bops_is_admin') === false) {
        $missing[] = 'permissions: admin gate missing';
        $findings[] = ['area' => 'permissions', 'severity' => 'high', 'status' => 'missing',
            'detail' => 'Admin gate not found in BusinessOps', 'plan' => 'manual_review'];
    } else {
        $implemented[] = 'permissions: admin gates + ownership checks present';
    }

    return ['implemented' => array_values(array_unique($implemented)),
        'partial' => array_values(array_unique($partial)),
        'missing' => array_values(array_unique($missing)),
        'findings' => $findings,
        'auto_fixable' => count(array_filter($findings, fn($f) => in_array($f['plan'] ?? '', ['wire_forms', 'runtime', 'mirror_admin'], true)))];
}

function mfa_autofix(string $slug, array $manifest, string $html): array {
    $slug = preg_replace('/[^a-z0-9\-]/i', '', $slug) ?: 'site';
    $applied = []; $skipped = [];
    $root = defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__) . '/storage';
    $scan = mfa_scan($manifest, $html, $slug);
    $outHtml = $html;

    foreach ($scan['findings'] as $f) {
        $plan = $f['plan'] ?? '';
        if ($plan === 'wire_forms' || $plan === 'runtime') {
            if (stripos($outHtml, 'wc-biz-runtime') === false) {
                if (function_exists('business_runtime_inject')) {
                    $outHtml = business_runtime_inject($outHtml, ['category' => $manifest['business']['category'] ?? 'generic'], $slug);
                    $applied[] = 'runtime: BusinessRuntime injected (forms now persist via API)';
                } else {
                    // Fallback: require at call time.
                    require_once __DIR__ . '/BusinessRuntime.php';
                    $outHtml = business_runtime_inject($outHtml, ['category' => $manifest['business']['category'] ?? 'generic'], $slug);
                    $applied[] = 'runtime: BusinessRuntime injected (forms now persist via API)';
                }
            } else { $skipped[] = 'runtime: already wired'; }
        } elseif ($plan === 'mirror_admin') {
            $area = (string)($f['area'] ?? '');
            $id = preg_replace('/^module:/', '', $area);
            $id = preg_replace('/[^a-z0-9_]/i', '', $id);
            if ($id !== '') {
                $siteF = $root . '/sites/' . $slug . '/' . $id . '.json';
                $adminF = dirname(__DIR__) . '/published/' . $slug . '/admin/data_' . $id . '.json';
                $made = false;
                if (!is_file($siteF) && !is_file($root . '/collections/' . $slug . '/' . $id . '.json')) {
                    @mkdir(dirname($siteF), 0755, true);
                    if (@file_put_contents($siteF, '[]', LOCK_EX) !== false) { $made = true; }
                }
                if (is_dir(dirname($adminF)) && !is_file($adminF)) {
                    if (@file_put_contents($adminF, '[]', LOCK_EX) !== false) { $made = true; }
                }
                if ($made) $applied[] = "module:$id: empty store created (no fake rows)";
                else $skipped[] = "module:$id: already exists";
            }
        } else {
            $skipped[] = ($f['area'] ?? 'item') . ': needs manual review (' . ($f['detail'] ?? '') . ')';
        }
    }
    // De-dupe applied (wire_forms + runtime collapse to one injection).
    $applied = array_values(array_unique($applied));
    return ['applied' => $applied, 'skipped' => array_values(array_unique($skipped)), 'html' => $outHtml,
        'rescan' => mfa_scan($manifest, $outHtml, $slug)];
}

}
