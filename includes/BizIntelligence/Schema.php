<?php
// includes/BizIntelligence/Schema.php — Canonical Business Requirements Specification
// Machine-readable JSON schema + programmatic validation + repair.
// Layers: profile / requirements / entities / actors / functional /
// workflows / pages / backend / nonfunctional / tests / uncertainty.

function biz_canonical_schema_version(): string { return 'biz-spec/1'; }

function biz_requirement_id(string $prefix, int $n): string {
    return strtoupper($prefix) . '-AC-' . str_pad((string)$n, 3, '0', STR_PAD_LEFT);
}

// Build a blank canonical spec skeleton
function biz_blank_spec(array $profile = []): array {
    return [
        'schema' => biz_canonical_schema_version(),
        'profile' => array_merge([
            'business_name' => '', 'description' => '', 'domain' => 'general',
            'business_model' => 'services', 'objectives' => [],
            'target_users' => [], 'context' => [],
        ], $profile),
        'requirements' => [],   // {id, description, category, priority, source, confidence, reason, dependencies[], validation, unresolved?}
        'entities' => [],       // {key,name,label,purpose,fields[],relations[],ownership,crud,lifecycle[],validation[],effects[]}
        'actors' => [],         // {role, description, actions[], resources[], restrictions[]}
        'capabilities' => [],   // composed capability ids
        'custom' => [],         // domain-specific components not covered by capabilities
        'workflows' => [],      // {key,name,trigger,steps[],transitions{from[]to},rejected[],failures[],idempotency,notifications[],audit}
        'pages' => [],          // {route,title,sections[],forms[],components[]}
        'backend' => ['apis' => [], 'tables' => [], 'transactions' => [], 'authz' => []],
        'nonfunctional' => ['performance' => [], 'accessibility' => [], 'security' => [], 'privacy' => []],
        'tests' => [],          // {id, scenario, steps[], expect}
        'uncertainty' => ['confirmed' => [], 'inferred' => [], 'recommended' => [], 'unresolved' => [], 'assumptions' => []],
        'meta' => ['engine' => 'biz-intel/1', 'created_at' => date('c')],
    ];
}

function biz_add_requirement(array &$spec, string $prefix, string $desc, string $cat = 'functional',
    string $priority = 'must', string $source = 'inferred', float $conf = 0.7,
    string $reason = '', array $deps = [], string $validation = '', ?string $unresolved = null,
    string $class = '', string $approval = 'pending', ?int $order = null): string {
    static $counters = [];
    $counters[$prefix] = ($counters[$prefix] ?? 0) + 1;
    $id = biz_requirement_id($prefix, $counters[$prefix]);
    // Classification: confirmed | recommended | inferred | needs_confirmation | future.
    // Never treat a recommendation as confirmed: default derives from source.
    if ($class === '') {
        $class = $source === 'explicit' ? 'confirmed' : ($source === 'recommended' ? 'recommended' : 'inferred');
    }
    if ($approval === 'pending' && $class === 'confirmed') $approval = 'accepted';
    $r = ['id' => $id, 'description' => $desc, 'category' => $cat, 'priority' => $priority,
        'source' => $source, 'confidence' => $conf, 'reason' => $reason,
        'dependencies' => array_values($deps), 'validation' => $validation,
        'class' => $class, 'approval' => $approval,
        'order' => $order ?? count($spec['requirements'] ?? [])];
    if ($unresolved) { $r['unresolved'] = $unresolved; if ($class !== 'confirmed') { $r['class'] = 'needs_confirmation'; } }
    $spec['requirements'][] = $r;
    return $id;
}

// Customer verdicts on proposed requirements (accept/edit/reject/reorder).
// Only touches classification/approval/text/order — never deletes silently:
// rejections are recorded, keeping an audit trail.
function biz_requirement_verdict(array &$spec, string $id, string $verdict, ?string $editText = null, ?int $newOrder = null): bool {
    if (!in_array($verdict, ['accepted', 'edited', 'rejected'], true)) return false;
    foreach ($spec['requirements'] as &$r) {
        if (($r['id'] ?? '') !== $id) continue;
        $r['approval'] = $verdict;
        if ($verdict === 'edited' && $editText !== null && trim($editText) !== '') {
            $r['description'] = substr(trim($editText), 0, 500);
            $r['class'] = 'confirmed';
        }
        if ($verdict === 'accepted' && ($r['class'] ?? '') !== 'future') {
            if (($r['class'] ?? '') === 'needs_confirmation') $r['class'] = 'confirmed';
        }
        if ($newOrder !== null) $r['order'] = max(0, $newOrder);
        return true;
    }
    return false;
}

// Validate canonical spec. Returns [ok, errors[]]
function biz_validate_spec(array $spec): array {
    $errs = [];
    if (($spec['schema'] ?? '') !== biz_canonical_schema_version()) $errs[] = 'schema mismatch';
    if (trim((string)($spec['profile']['business_name'] ?? '')) === '' && trim((string)($spec['profile']['description'] ?? '')) === '') $errs[] = 'profile empty';
    $ids = [];
    foreach (($spec['requirements'] ?? []) as $r) {
        if (empty($r['id']) || empty($r['description'])) { $errs[] = 'requirement missing id/description'; break; }
        if (isset($ids[$r['id']])) $errs[] = 'duplicate requirement ' . $r['id'];
        $ids[$r['id']] = true;
        if (!in_array($r['source'] ?? '', ['explicit','inferred','recommended'], true)) $errs[] = 'bad source ' . ($r['id'] ?? '?');
        $c = (float)($r['confidence'] ?? -1);
        if ($c < 0 || $c > 1) $errs[] = 'bad confidence ' . ($r['id'] ?? '?');
        if (isset($r['class']) && !in_array($r['class'], ['confirmed','recommended','inferred','needs_confirmation','future'], true)) $errs[] = 'bad class ' . ($r['id'] ?? '?');
        if (isset($r['approval']) && !in_array($r['approval'], ['pending','accepted','edited','rejected'], true)) $errs[] = 'bad approval ' . ($r['id'] ?? '?');
        // A recommendation must never pose as confirmed.
        if (($r['source'] ?? '') === 'recommended' && ($r['class'] ?? '') === 'confirmed' && ($r['approval'] ?? '') !== 'accepted') $errs[] = 'recommendation posed as confirmed ' . ($r['id'] ?? '?');
    }
    foreach (($spec['entities'] ?? []) as $e) {
        if (empty($e['key']) || empty($e['name'])) { $errs[] = 'entity missing key/name'; break; }
        if (empty($e['fields']) || !is_array($e['fields'])) $errs[] = 'entity ' . ($e['key'] ?? '?') . ' has no fields';
    }
    foreach (($spec['workflows'] ?? []) as $w) {
        if (empty($w['key']) || empty($w['steps'])) { $errs[] = 'workflow ' . ($w['key'] ?? '?') . ' has no steps'; break; }
    }
    // Every must-requirement needs validation criteria
    foreach (($spec['requirements'] ?? []) as $r) {
        if (($r['priority'] ?? '') === 'must' && trim((string)($r['validation'] ?? '')) === '') $errs[] = 'must-requirement ' . $r['id'] . ' lacks validation';
    }
    return [empty($errs), $errs];
}

// Repair common malformations from model JSON (never exec as code)
function biz_repair_spec(array $s): array {
    if (!isset($s['schema'])) $s['schema'] = biz_canonical_schema_version();
    foreach (['requirements','entities','actors','capabilities','custom','workflows','pages','tests'] as $k) {
        if (!isset($s[$k]) || !is_array($s[$k])) $s[$k] = [];
    }
    if (!isset($s['backend']) || !is_array($s['backend'])) $s['backend'] = ['apis'=>[],'tables'=>[],'transactions'=>[],'authz'=>[]];
    if (!isset($s['nonfunctional']) || !is_array($s['nonfunctional'])) $s['nonfunctional'] = ['performance'=>[],'accessibility'=>[],'security'=>[],'privacy'=>[]];
    if (!isset($s['uncertainty']) || !is_array($s['uncertainty'])) $s['uncertainty'] = ['confirmed'=>[],'inferred'=>[],'recommended'=>[],'unresolved'=>[],'assumptions'=>[]];
    if (!isset($s['profile']) || !is_array($s['profile'])) $s['profile'] = [];
    return $s;
}
