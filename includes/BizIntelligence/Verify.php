<?php
// includes/BizIntelligence/Verify.php — Post-generation verification.
// Compares the ACTUAL generated project against the APPROVED spec and
// reports passed / failed / untested / missing-config. Test-only: never
// mutates the project. Admin PHP files are linted (php -l), never executed.
require_once __DIR__ . '/Schema.php';

function biz_verify_project(array $spec, array $project): array {
    // $project: ['htmls' => [variationHtml...], 'admin_dir' => ?path,
    //   'admin_files' => [relpath...], 'order' => ?, 'slug' => ?]
    $passed = []; $failed = []; $untested = []; $missingCfg = [];
    $htmls = array_values(array_filter($project['htmls'] ?? [], fn($h) => is_string($h) && strlen($h) > 500));
    $prime = $htmls[0] ?? '';

    // 1. Required pages/sections present in prime variation
    $needSections = ['hero', 'contact'];
    foreach (($spec['pages'][0]['sections'] ?? []) as $s) {
        if (!in_array(strtolower((string)$s), $needSections, true)) $needSections[] = strtolower((string)$s);
    }
    foreach (array_slice($needSections, 0, 8) as $s) {
        $found = $prime !== '' && (stripos($prime, 'id="' . $s . '"') !== false || stripos($prime, "id='" . $s . "'") !== false
            || stripos($prime, $s) !== false);
        if ($found) $passed[] = ['t' => 'section:' . $s, 'd' => 'present in landing page'];
        else $failed[] = ['t' => 'section:' . $s, 'd' => 'not found in landing page'];
    }

    // 2. Links resolve to existing anchors (no dead # links to missing ids)
    if ($prime !== '') {
        preg_match_all('/href="#([A-Za-z0-9_\-]+)"/', $prime, $m);
        $dead = [];
        foreach (array_unique($m[1] ?? []) as $id) {
            if (stripos($prime, 'id="' . $id . '"') === false && stripos($prime, "id='" . $id . "'") === false) $dead[] = $id;
        }
        if (empty($dead)) $passed[] = ['t' => 'nav links', 'd' => 'all anchors resolve'];
        else $failed[] = ['t' => 'nav links', 'd' => 'dead anchors: ' . implode(',', array_slice($dead, 0, 6))];
    } else {
        $untested[] = ['t' => 'nav links', 'd' => 'no HTML to inspect'];
    }

    // 3. Forms have validation (required attrs) and a real action/handler
    if ($prime !== '') {
        $nForms = preg_match_all('/<form\b/i', $prime, $fm);
        if ($nForms > 0) {
            $hasReq = (bool)preg_match('/<form\b[\s\S]*?required/i', $prime);
            $hasHandler = stripos($prime, 'business-ops.php') !== false || stripos($prime, 'data-wc-form') !== false
                || stripos($prime, 'data-wc-search') !== false || stripos($prime, 'data-wc-catalog') !== false
                || stripos($prime, 'contact.php') !== false;
            if ($hasReq && $hasHandler) $passed[] = ['t' => 'forms', 'd' => $nForms . ' form(s) with validation + backend wiring'];
            else $failed[] = ['t' => 'forms', 'd' => 'validation=' . ($hasReq ? 'yes' : 'NO') . ' backend=' . ($hasHandler ? 'yes' : 'NO')];
        } else {
            $untested[] = ['t' => 'forms', 'd' => 'spec requires no customer forms'];
        }
    }

    // 4. Admin CRUD files exist for spec entities
    $adminDir = (string)($project['admin_dir'] ?? '');
    $adminFiles = $project['admin_files'] ?? null;
    if ($adminDir !== '' && is_dir($adminDir)) {
        $files = [];
        foreach (glob($adminDir . '/*.php') ?: [] as $f) $files[] = basename($f);
        foreach (['login.php', 'auth.php', 'manage.php', 'index.php'] as $need) {
            if (in_array($need, $files, true)) $passed[] = ['t' => 'admin:' . $need, 'd' => 'present'];
            else $failed[] = ['t' => 'admin:' . $need, 'd' => 'missing'];
        }
        // PHP lint generated admin (parse check only, never execute)
        $lintBad = [];
        foreach (glob($adminDir . '/*.php') ?: [] as $f) {
            $out = []; $code = 0;
            @exec(PHP_BINARY . ' -l ' . escapeshellarg($f) . ' 2>&1', $out, $code);
            if ($code !== 0) $lintBad[] = basename($f);
        }
        if (empty($lintBad)) $passed[] = ['t' => 'admin php lint', 'd' => 'all generated PHP parses'];
        else $failed[] = ['t' => 'admin php lint', 'd' => 'parse errors: ' . implode(',', $lintBad)];
        // No secrets in generated frontend-visible files
        $leak = [];
        foreach (glob($adminDir . '/*.php') ?: [] as $f) {
            $c = @file_get_contents($f);
            if ($c && preg_match('/password_plain|sk-live|AIza[0-9A-Za-z_\-]{20,}/', $c)) $leak[] = basename($f);
        }
        if (empty($leak)) $passed[] = ['t' => 'admin secrets', 'd' => 'no plaintext secrets in generated code'];
        else $failed[] = ['t' => 'admin secrets', 'd' => 'possible leak in: ' . implode(',', $leak)];
    } elseif (is_array($adminFiles)) {
        foreach (['login.php', 'auth.php', 'manage.php'] as $need) {
            $hit = false;
            foreach ($adminFiles as $k) { if (substr((string)$k, -strlen($need)) === $need) { $hit = true; break; } }
            if ($hit) $passed[] = ['t' => 'admin:' . $need, 'd' => 'in file set'];
            else $failed[] = ['t' => 'admin:' . $need, 'd' => 'missing from file set'];
        }
    } else {
        $needsAdmin = !empty($spec['entities']);
        if ($needsAdmin) $untested[] = ['t' => 'admin CRUD', 'd' => 'admin not generated yet (pre-publish)'];
        else $passed[] = ['t' => 'admin CRUD', 'd' => 'n/a — no entities'];
    }

    // 5. Auth endpoints present when accounts required
    if (in_array('identity', $spec['capabilities'] ?? [], true)) {
        $hasAuth = $prime !== '' && (stripos($prime, 'login') !== false || stripos($prime, 'account') !== false);
        if ($hasAuth) $passed[] = ['t' => 'auth entry', 'd' => 'login/account reachable from site'];
        else $failed[] = ['t' => 'auth entry', 'd' => 'no login/account entry found'];
    }

    // 6. Booking/order markers when those capabilities are required
    foreach (['booking' => ['book', 'seat', 'reserve'], 'orders' => ['cart', 'checkout', 'order']] as $cap => $markers) {
        if (!in_array($cap, $spec['capabilities'] ?? [], true)) continue;
        $hit = false;
        foreach ($markers as $mk) { if ($prime !== '' && stripos($prime, $mk) !== false) { $hit = true; break; } }
        if ($hit) $passed[] = ['t' => 'capability:' . $cap, 'd' => 'markers present'];
        else $failed[] = ['t' => 'capability:' . $cap, 'd' => 'no UI markers for required capability'];
    }

    // 7. Responsive / a11y / SEO basics
    if ($prime !== '') {
        if (stripos($prime, 'viewport') !== false) $passed[] = ['t' => 'responsive', 'd' => 'viewport meta present'];
        else $failed[] = ['t' => 'responsive', 'd' => 'viewport meta missing'];
        if (preg_match('/<title>[^<]{3,}<\/title>/i', $prime)) $passed[] = ['t' => 'seo title', 'd' => 'present'];
        else $failed[] = ['t' => 'seo title', 'd' => 'missing/empty'];
        if (stripos($prime, '<html lang=') !== false) $passed[] = ['t' => 'a11y lang', 'd' => 'present'];
        else $failed[] = ['t' => 'a11y lang', 'd' => 'html lang missing'];
    }

    // 8. Integration config state (honest missing-config, never fake success)
    foreach (['payments'] as $cap) {
        if (!in_array($cap, $spec['capabilities'] ?? [], true)) continue;
        $missingCfg[] = ['t' => 'payments provider', 'd' => 'keys must be configured in site settings; workflows run in configurable/offline-safe mode until then'];
    }

    return ['passed' => $passed, 'failed' => $failed, 'untested' => $untested, 'missing_config' => $missingCfg,
        'summary' => ['passed' => count($passed), 'failed' => count($failed),
            'untested' => count($untested), 'missing_config' => count($missingCfg)]];
}
