<?php
// includes/PublishedAdminKit.php — Generates the REQUIRED published-site
// admin architecture on top of the existing builder:
//
//   published/<slug>/admin/includes/auth.php    (csrf_token, require_login…)
//   published/<slug>/admin/includes/db.php      (db_read/write/insert/…)
//   published/<slug>/admin/includes/layout.php  (sidebar/header/footer)
//   published/<slug>/admin/index.php            (real-data dashboard — kept
//                                               business-specific when present)
//   published/<slug>/admin/<entity>.php         (one CRUD page per entity)
//   published/<slug>/admin/inbox.php            (contact-form inbox, BC with
//                                               messages.php + data_inquiries)
//   published/<slug>/admin/settings.php         (hash-only password + site.json)
//   published/<slug>/admin/api.php              (authenticated AJAX CRUD API)
//   published/<slug>/admin/install.php          (safe one-time installer)
//   published/<slug>/submit.php                 (universal PUBLIC form handler:
//                                               no admin login required)
//
// Design rules:
// - ADDITIVE: never overwrites customer records, .auth.json (when valid),
//   site.json, content.json, or manifest.json data. Only adds/fixes files.
// - Every public form submission → server validation → flock-guarded JSON
//   → visible in the matching admin page on next load.
// - No external dependencies. PHP 8.0+, vanilla output, htmlspecialchars
//   everywhere, CSRF on all state-changing admin requests.

require_once __DIR__ . '/HtmlUtil.php';

if (!function_exists('pak_normalize_entities')) {

function pak_slug(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-') ?: 'site';
}

function pak_entity_id(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '_', $s);
    return trim($s, '_');
}

/**
 * Normalize ANY entity list into install-safe module shape.
 * Reuses AdminPanelGenerator when available so both installers agree.
 */
function pak_normalize_entities(array $list): array {
    if (class_exists('AdminPanelGenerator') && method_exists('AdminPanelGenerator', 'normalizeEntities')) {
        return AdminPanelGenerator::normalizeEntities($list);
    }
    if (!function_exists('business_registry_canonical_entity')) {
        @require_once __DIR__ . '/BusinessRegistry.php';
    }
    $out = [];
    foreach (array_slice($list, 0, 8) as $e) {
        if (is_string($e)) $e = ['name' => $e];
        if (!is_array($e)) continue;
        $id = pak_entity_id((string)($e['id'] ?? $e['key'] ?? $e['name'] ?? ''));
        if ($id === '') continue;
        // ★ Same canonicalization as the generator path: singular AI ids
        // fold into the registry plural so admin + frontend share one store.
        if (function_exists('business_registry_canonical_entity')) {
            $id = business_registry_canonical_entity($id);
        }
        $label = trim((string)($e['label'] ?? $e['name'] ?? ucwords(str_replace('_', ' ', $id))));
        $fields = [];
        foreach (array_slice((array)($e['fields'] ?? []), 0, 15) as $f) {
            if (is_string($f)) {
                $fn = pak_entity_id(explode(':', $f)[0]);
                if ($fn === '' || $fn === 'id') continue;
                $fields[] = ['name' => $fn, 'label' => ucwords(str_replace('_', ' ', $fn)), 'type' => 'text', 'required' => false];
            } elseif (is_array($f) && !empty($f['name'])) {
                $fn = pak_entity_id((string)$f['name']);
                if ($fn === '' || $fn === 'id') continue;
                $t = strtolower((string)($f['type'] ?? 'text'));
                if (!in_array($t, ['text','textarea','number','email','url','date','time','datetime','select','checkbox','tel'], true)) $t = 'text';
                $fields[] = ['name' => $fn, 'label' => (string)($f['label'] ?? ucwords(str_replace('_', ' ', $fn))),
                    'type' => $t, 'required' => !empty($f['required']),
                    'options' => ($t === 'select' && !empty($f['options']) && is_array($f['options'])) ? array_values($f['options']) : []];
            }
        }
        if (empty($fields)) {
            $fields = [
                ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true],
                ['name' => 'details', 'label' => 'Details', 'type' => 'textarea', 'required' => false],
            ];
        }
        $singular = $label;
        if (substr($singular, -1) === 's' && strlen($singular) > 3) $singular = substr($singular, 0, -1);
        $out[] = ['id' => $id, 'name' => $label, 'singular' => $singular,
            'icon' => (string)($e['icon'] ?? '📁'), 'fields' => $fields];
    }
    $seen = []; $ded = [];
    foreach ($out as $o) {
        if (!isset($seen[$o['id']])) { $seen[$o['id']] = true; $ded[] = $o; continue; }
        foreach ($ded as &$d) {
            if ($d['id'] !== $o['id']) continue;
            $have = [];
            foreach (($d['fields'] ?? []) as $f) $have[strtolower((string)($f['name'] ?? ''))] = true;
            foreach (($o['fields'] ?? []) as $f) {
                if (!isset($have[strtolower((string)($f['name'] ?? ''))])) $d['fields'][] = $f;
            }
            break;
        }
        unset($d);
    }
    return $ded;
}

function pak_default_entities(): array {
    return pak_normalize_entities([
        ['id' => 'services', 'name' => 'Services', 'icon' => '💼'],
        ['id' => 'inquiries', 'name' => 'Inquiries', 'icon' => '✉️',
         'fields' => [['name'=>'name','label'=>'Name','type'=>'text','required'=>true],
                       ['name'=>'email','label'=>'Email','type'=>'email','required'=>true],
                       ['name'=>'phone','label'=>'Phone','type'=>'text','required'=>false],
                       ['name'=>'message','label'=>'Message','type'=>'textarea','required'=>false]]],
    ]);
}

/**
 * Customers module for the admin panel (customer management).
 * Always installed: without customers.php the "Customers" nav entry (built
 * from the on-disk data_customers.json) links to a 404.
 */
function pak_customers_entity(): array {
    return [
        'id' => 'customers',
        'name' => 'Customers',
        'singular' => 'Customer',
        'icon' => "\u{1F465}",
        'fields' => [
            ['name' => 'name', 'label' => 'Full Name', 'type' => 'text', 'required' => true, 'options' => []],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => false, 'options' => []],
            ['name' => 'phone', 'label' => 'Phone', 'type' => 'tel', 'required' => false, 'options' => []],
            ['name' => 'total_bookings', 'label' => 'Bookings', 'type' => 'number', 'required' => false, 'options' => []],
            ['name' => 'total_spent', 'label' => 'Total Spent', 'type' => 'number', 'required' => false, 'options' => []],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => false,
             'options' => ['active', 'inactive', 'blocked']],
            ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'required' => false, 'options' => []],
        ],
    ];
}

/** Entities for an order: manifest first, safe default only when empty. */
function pak_entities_for_order(array $order): array {
    $ents = pak_normalize_entities((array)($order['admin_entities'] ?? []));
    if (empty($ents)) $ents = pak_default_entities();
    return $ents;
}

/**
 * Frontend form purpose → destination entity, derived from the ACTUAL
 * manifest (keyword match on entity id/label). Never routes everything
 * into one unrelated file.
 */
function pak_form_entity_map(array $entities): array {
    $ids = [];
    foreach ($entities as $e) $ids[] = (string)($e['id'] ?? '');
    $pick = function (array $keywords, string $fallback) use ($entities, $ids) {
        foreach ($keywords as $kw) {
            foreach ($entities as $e) {
                $hay = strtolower(($e['id'] ?? '') . ' ' . ($e['name'] ?? ''));
                if (strpos($hay, $kw) !== false) return (string)$e['id'];
            }
        }
        return in_array($fallback, $ids, true) ? $fallback : ($ids[0] ?? 'inquiries');
    };
    return [
        'contact'      => $pick(['inquir', 'message', 'contact', 'lead'], 'inquiries'),
        'booking'      => $pick(['book', 'reserv', 'appoint', 'ticket'], in_array('bookings', $ids, true) ? 'bookings' : 'inquiries'),
        'order'        => $pick(['order', 'sale', 'checkout'], in_array('orders', $ids, true) ? 'orders' : 'inquiries'),
        'registration' => $pick(['customer', 'client', 'member', 'user', 'subscriber'], in_array('customers', $ids, true) ? 'customers' : 'inquiries'),
        'review'       => $pick(['review', 'rating', 'testimonial', 'feedback'], in_array('reviews', $ids, true) ? 'reviews' : 'inquiries'),
        'quote'        => $pick(['quote', 'estim'], 'inquiries'),
        'newsletter'   => $pick(['subscriber', 'newsletter', 'subscriber'], in_array('subscribers', $ids, true) ? 'subscribers' : 'inquiries'),
        'appointment'  => $pick(['appoint', 'consult', 'visit', 'schedule'], in_array('appointments', $ids, true) ? 'appointments' : 'inquiries'),
    ];
}

function pak_write(string $file, string $content): bool {
    $dir = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    if (!is_dir($dir)) return false;
    $tmp = $file . '.tmp-' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, $content, LOCK_EX) === false) return false;
    if (@rename($tmp, $file)) return true;
    // Transient Windows lock (AV/indexer): brief retry, then copy fallback.
    usleep(50000);
    if (@rename($tmp, $file)) return true;
    if (@copy($tmp, $file)) { @unlink($tmp); return true; }
    @unlink($tmp);
    return false;
}

/** Valid published-admin relative path (no traversal, no absolute). */
function pak_safe_relpath(string $p): ?string {
    $p = str_replace('\\', '/', trim($p));
    $p = ltrim($p, '/');
    if ($p === '' || strpos($p, '..') !== false || strpos($p, "\0") !== false) return null;
    if (preg_match('#(^|/)\.#', $p) && !in_array(basename($p), ['.auth.json', '.installed'], true)) {
        // Allow listed dotfiles only at admin root; block the rest.
        if (basename($p) !== $p) return null;
    }
    $blocked = ['.auth.json', '.installed', '.login_attempts.json'];
    if (in_array(basename($p), $blocked, true)) return null;
    if (!preg_match('#^[A-Za-z0-9_\-./]{1,120}$#', $p)) return null;
    return $p;
}

// ─── Generated file: admin/includes/auth.php ─────────────────────────────
function pak_auth_php(): string {
    return <<<'PHP'
<?php
// includes/auth.php — Shared admin authentication (generated by WebCraft AI).
// Compatible with the root auth.php / .auth.json contract:
// $_SESSION['admin_auth'] + bcrypt hash in ../.auth.json.
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true,
            'samesite' => 'Lax', 'secure' => $secure]);
    }
    session_start();
}

function pak_auth_file(): string { return dirname(__DIR__) . '/.auth.json'; }

function pak_auth_data(): array {
    $f = pak_auth_file();
    if (!is_file($f)) return [];
    $j = json_decode(@file_get_contents($f), true);
    return is_array($j) ? $j : [];
}

/** Verify a username+password against the bcrypt hash. Never leaks which failed. */
function verify_login(string $username, string $password): bool {
    $a = pak_auth_data();
    $u = (string)($a['username'] ?? '');
    $h = (string)($a['hash'] ?? '');
    if ($u === '' || $h === '') return false;
    if (!hash_equals(strtolower($u), strtolower(trim($username)))) return false;
    return password_verify($password, $h);
}

function admin_current_user(): string {
    return (string)($_SESSION['admin_username'] ?? pak_auth_data()['username'] ?? 'Admin');
}

function require_login(): void {
    if (empty($_SESSION['admin_auth'])) {
        $to = 'login.php';
        // Safe redirect: fixed relative target only — no open redirect.
        header('Location: ' . $to);
        exit;
    }
}

function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** CSRF helpers for every state-changing admin request. */
function csrf_token(): string {
    if (empty($_SESSION['pak_csrf'])) $_SESSION['pak_csrf'] = bin2hex(random_bytes(32));
    return (string)$_SESSION['pak_csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_validate(): void {
    $t = (string)($_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $s = (string)($_SESSION['pak_csrf'] ?? $_SESSION['admin_csrf'] ?? '');
    if ($t === '' || $s === '' || !hash_equals($s, $t)) {
        http_response_code(403);
        exit('Invalid session token. Please reload the page and try again.');
    }
}

/** Login throttle: 5 attempts / 5 min / IP. */
function pak_login_allowed(): bool {
    $f = dirname(__DIR__) . '/.login_attempts.json';
    $now = time();
    $rows = is_file($f) ? (json_decode(@file_get_contents($f), true) ?: []) : [];
    if (!is_array($rows)) $rows = [];
    $rows = array_values(array_filter($rows, fn($r) => is_array($r) && ($r['at'] ?? 0) > $now - 300));
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    $n = 0;
    foreach ($rows as $r) { if (($r['ip'] ?? '') === $ip) $n++; }
    @file_put_contents($f, json_encode($rows, JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $n < 5;
}

function pak_login_failed(): void {
    $f = dirname(__DIR__) . '/.login_attempts.json';
    $rows = is_file($f) ? (json_decode(@file_get_contents($f), true) ?: []) : [];
    if (!is_array($rows)) $rows = [];
    $rows[] = ['ip' => $_SERVER['REMOTE_ADDR'] ?? 'cli', 'at' => time()];
    @file_put_contents($f, json_encode(array_slice($rows, -50), JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// ── Backward-compat aliases for the root auth.php contract ──
if (!function_exists('requireAdmin')) {
    function requireAdmin(): void { require_login(); }
}
if (!function_exists('getAuthData')) {
    function getAuthData(): array { return pak_auth_data(); }
}
if (!function_exists('admin_csrf_token')) {
    function admin_csrf_token(): string {
        if (empty($_SESSION['admin_csrf'])) $_SESSION['admin_csrf'] = csrf_token();
        return (string)$_SESSION['admin_csrf'];
    }
}
if (!function_exists('admin_csrf_field')) {
    function admin_csrf_field(): string { return csrf_field(); }
}
if (!function_exists('admin_require_csrf')) {
    function admin_require_csrf(): void { csrf_validate(); }
}
if (!function_exists('canShowRequirementBuilder')) {
    function canShowRequirementBuilder(string $role, string $mode, $recordId): bool {
        return ($role === 'admin' && $mode === 'edit' && !empty($recordId));
    }
}
PHP;
}

// ─── Generated file: admin/includes/db.php ───────────────────────────────
function pak_db_php(): string {
    return <<<'PHP'
<?php
// includes/db.php — Shared JSON database helper (generated by WebCraft AI).
// Storage: published/<slug>/admin/data_<entity>.json
// flock-guarded reads/writes, atomic replace, unique IDs, created_at /
// updated_at, manifest-validated entity names, safe malformed-JSON recovery.
if (!function_exists('db_entities')) {

function db_dir(): string { return dirname(__DIR__); }

/** Approved entity ids from admin/manifest.json (+ always-allowed public forms). */
function db_entities(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $ids = [];
    $mf = db_dir() . '/manifest.json';
    if (is_file($mf)) {
        $m = json_decode(@file_get_contents($mf), true);
        foreach ((array)($m['entities'] ?? []) as $e) {
            if (!is_array($e)) continue;
            $id = strtolower(preg_replace('/[^a-z0-9]+/i', '_', (string)($e['id'] ?? '')));
            $id = trim($id, '_');
            if ($id !== '' && strlen($id) <= 40) $ids[$id] = true;
        }
        foreach ((array)($m['admin_modules'] ?? []) as $mod) {
            if (!is_array($mod) || empty($mod['entity'])) continue;
            $id = strtolower(preg_replace('/[^a-z0-9]+/i', '_', (string)$mod['entity']));
            $id = trim($id, '_');
            if ($id !== '' && strlen($id) <= 40) $ids[$id] = true;
        }
    }
    // Public-form entities are always writable by the site's own handlers.
    foreach (['inquiries', 'subscribers'] as $always) $ids[$always] = true;
    // Anything already on disk stays manageable (republish-safe).
    foreach (glob(db_dir() . '/data_*.json') ?: [] as $df) {
        $did = preg_replace('/^data_|\.json$/', '', basename($df));
        if (preg_match('/^[a-z0-9_]{1,40}$/', $did)) $ids[$did] = true;
    }
    $cache = array_keys($ids);
    return $cache;
}

/** Href for an entity module: its own page when it exists, otherwise
 *  the generic entity manager (a missing page must never be a 404). */
function db_entity_href(string $entity): string {
    $id = strtolower(preg_replace('/[^a-z0-9_]/i', '', (string)$entity));
    if ($id === '') return 'manage.php';
    return is_file(db_dir() . '/' . $id . '.php')
        ? $id . '.php'
        : 'manage.php?entity=' . rawurlencode($id);
}
function db_entity_allowed(string $entity): bool {
    if (!preg_match('/^[a-z0-9_]{1,40}$/', $entity)) return false;
    return in_array($entity, db_entities(), true);
}

function db_file(string $entity): ?string {
    // Entity name is validated BEFORE any path is built — no traversal.
    if (!db_entity_allowed($entity)) return null;
    return db_dir() . '/data_' . $entity . '.json';
}

/** Fields that untrusted input may never set directly. */
function db_protected_fields(): array {
    return ['id' => 1, 'created_at' => 1, 'role' => 1, 'is_admin' => 1,
        'admin' => 1, 'paid' => 1, 'approved' => 1, 'permissions' => 1,
        'password' => 1, 'password_hash' => 1, 'hash' => 1, 'secret' => 1];
}

function db_strip_protected(array $row): array {
    foreach (db_protected_fields() as $k => $_) unset($row[$k]);
    return $row;
}

function db_new_id(): string { return 'r_' . bin2hex(random_bytes(8)); }

/** Locked read. Malformed JSON is quarantined (never silently destroyed). */
function db_read(string $entity): array {
    $f = db_file($entity);
    if ($f === null) return [];
    if (!is_file($f)) return [];
    $h = @fopen($f, 'r');
    if (!$h) return [];
    try {
        if (!flock($h, LOCK_SH)) return [];
        $raw = stream_get_contents($h);
        flock($h, LOCK_UN);
    } finally { fclose($h); }
    if ($raw === false || trim($raw) === '') return [];
    $rows = json_decode($raw, true);
    if (!is_array($rows)) {
        @rename($f, $f . '.corrupt-' . date('Ymd-His'));
        error_log('[pak db] quarantined malformed JSON: ' . basename($f));
        return [];
    }
    return array_values($rows);
}

/** Atomic locked write (temp file + rename). Preserves nothing but $rows. */
function db_write(string $entity, array $rows): bool {
    $f = db_file($entity);
    if ($f === null) return false;
    $tmp = $f . '.tmp-' . bin2hex(random_bytes(4));
    $ok = @file_put_contents($tmp, json_encode(array_values($rows),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    if ($ok === false) { @unlink($tmp); return false; }
    if (!@rename($tmp, $f)) { @unlink($tmp); return false; }
    return true;
}

function db_find(string $entity, string $id): ?array {
    foreach (db_read($entity) as $r) {
        if (is_array($r) && (string)($r['id'] ?? '') === $id) return $r;
    }
    return null;
}

/** Insert under an exclusive lock — concurrent submits never lose rows. */
function db_insert(string $entity, array $row): ?array {
    $f = db_file($entity);
    if ($f === null) return null;
    $row = db_strip_protected($row);
    $h = @fopen($f, 'c+');
    if (!$h) return null;
    try {
        if (!flock($h, LOCK_EX)) return null;
        $raw = stream_get_contents($h);
        $rows = ($raw !== false && trim((string)$raw) !== '') ? (json_decode($raw, true) ?: []) : [];
        if (!is_array($rows)) $rows = [];
        $rows = array_values($rows);
        $now = date('c');
        $row['id'] = $entity === 'trips'
            ? 'TRIP-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)))
            : ($entity === 'bookings'
                ? 'BK-' . strtoupper(bin2hex(random_bytes(4)))
                : db_new_id());
        if ($entity === 'bookings' && empty($row['ref'])) {
            $row['ref'] = $row['id'];
        }
        $row['created_at'] = $now;
        $row['updated_at'] = $now;
        array_unshift($rows, $row);
        if (count($rows) > 2000) $rows = array_slice($rows, 0, 2000);
        ftruncate($h, 0);
        rewind($h);
        fwrite($h, json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fflush($h);
        db_mirror($entity, (string)$row['id'], $row);
        return $row;
    } finally {
        flock($h, LOCK_UN);
        fclose($h);
    }
}

/** Update one record by id; all other records are preserved untouched. */
function db_update(string $entity, string $id, array $patch): ?array {
    $f = db_file($entity);
    if ($f === null || $id === '') return null;
    $patch = db_strip_protected($patch);
    $h = @fopen($f, 'c+');
    if (!$h) return null;
    try {
        if (!flock($h, LOCK_EX)) return null;
        $raw = stream_get_contents($h);
        $rows = ($raw !== false && trim((string)$raw) !== '') ? (json_decode($raw, true) ?: []) : [];
        if (!is_array($rows)) $rows = [];
        $rows = array_values($rows);
        $found = null;
        foreach ($rows as &$r) {
            if (is_array($r) && (string)($r['id'] ?? '') === $id) {
                $r = array_merge($r, $patch, ['updated_at' => date('c')]);
                $found = $r;
                break;
            }
        }
        if ($found === null) return null;
        ftruncate($h, 0);
        rewind($h);
        fwrite($h, json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fflush($h);
        db_mirror($entity, $id, $found);
        return $found;
    } finally {
        flock($h, LOCK_UN);
        fclose($h);
    }
}

function db_delete(string $entity, string $id): bool {
    $f = db_file($entity);
    if ($f === null || $id === '') return false;
    $h = @fopen($f, 'c+');
    if (!$h) return false;
    try {
        if (!flock($h, LOCK_EX)) return false;
        $raw = stream_get_contents($h);
        $rows = ($raw !== false && trim((string)$raw) !== '') ? (json_decode($raw, true) ?: []) : [];
        if (!is_array($rows)) $rows = [];
        $rows = array_values($rows);
        $before = count($rows);
        $rows = array_values(array_filter($rows,
            fn($r) => (string)(is_array($r) ? ($r['id'] ?? '') : '') !== $id));
        if (count($rows) === $before) return false;
        ftruncate($h, 0);
        rewind($h);
        fwrite($h, json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fflush($h);
        db_mirror($entity, $id, null);
        return true;
    } finally {
        flock($h, LOCK_UN);
        fclose($h);
    }
}

/** Project root (walk up to the dir holding storage/ + includes/). The walk
 *  requires the includes marker — a stray nested storage/ dir (e.g. from an
 *  old mis-pathed writer) must never hijack the root. */
function db_project_root(): string {
    $d = db_dir();
    for ($i = 0; $i < 6; $i++) {
        if (is_dir($d . '/storage/collections') && is_file($d . '/includes/BusinessOps.php')) return $d;
        $p = dirname($d);
        if ($p === $d) break;
        $d = $p;
    }
    return dirname(db_dir(), 3);
}

/** Site slug shared with both storefront backends (same sanitize as bops/Node). */
function db_site_slug(): string {
    $s = strtolower((string)basename(dirname(db_dir())));
    $s = preg_replace('/[^a-z0-9_-]/i', '', $s);
    return substr($s !== '' ? $s : 'site', 0, 60);
}

/**
 * Mirror an admin-side write into storage/collections/<slug>/<entity>.json
 * so the storefront backends (Node read-union + PHP list) see admin-created
 * and admin-edited rows. Upsert by id; $row=null deletes (prevents ghost
 * resurrection via read-repair). The sites/ twin (Node writer) is purged on
 * delete when present so union reads stay clean. Never fatal — returns bool.
 */
function db_mirror(string $entity, string $id, ?array $row): bool {
    try {
        if (!preg_match('/^[a-z0-9_]{1,40}$/', $entity) || $id === '') return false;
        $dir = db_project_root() . '/storage/collections/' . db_site_slug();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return false;
        $f = $dir . '/' . $entity . '.json';
        $h = @fopen($f, 'c+');
        if (!$h) return false;
        try {
            if (!flock($h, LOCK_EX)) return false;
            $raw = stream_get_contents($h);
            $rows = ($raw !== false && trim((string)$raw) !== '') ? (json_decode($raw, true) ?: []) : [];
            if (!is_array($rows)) $rows = [];
            $rows = array_values($rows);
            if ($row === null) {
                $rows = array_values(array_filter($rows,
                    fn($r) => (string)(is_array($r) ? ($r['id'] ?? '') : '') !== $id));
            } else {                $done = false;
                foreach ($rows as &$r) {
                    if (is_array($r) && (string)($r['id'] ?? '') === $id) { $r = $row; $done = true; break; }
                }
                unset($r);
                if (!$done) array_unshift($rows, $row);
                if (count($rows) > 2000) $rows = array_slice($rows, 0, 2000);
            }
            ftruncate($h, 0);
            rewind($h);
            fwrite($h, json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            fflush($h);
            if ($row === null) db_mirror_purge_sites($entity, $id);
            return true;
        } finally {
            flock($h, LOCK_UN);
            fclose($h);
        }
    } catch (Throwable $e) { return false; }
}

/** Purge one id from the sites/ twin (Node writer) when it exists. */
function db_mirror_purge_sites(string $entity, string $id): void {
    try {
        $f = db_project_root() . '/storage/sites/' . db_site_slug() . '/' . $entity . '.json';
        if ($id === '' || !is_file($f)) return;
        $h = @fopen($f, 'c+');
        if (!$h) return;
        try {
            if (!flock($h, LOCK_EX)) return;
            $raw = stream_get_contents($h);
            $rows = ($raw !== false && trim((string)$raw) !== '') ? (json_decode($raw, true) ?: []) : [];
            if (!is_array($rows)) $rows = [];
            $before = count($rows);
            $rows = array_values(array_filter($rows,
                fn($r) => (string)(is_array($r) ? ($r['id'] ?? '') : '') !== $id));
            if (count($rows) !== $before) {
                ftruncate($h, 0);
                rewind($h);
                fwrite($h, json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                fflush($h);
            }
        } finally {
            flock($h, LOCK_UN);
            fclose($h);
        }
    } catch (Throwable $e) { /* best-effort */ }
}

/** Field definitions for an entity (manifest first, record-derived fallback). */
function db_field_defs(string $entity): array {
    $mf = db_dir() . '/manifest.json';
    if (is_file($mf)) {
        $m = json_decode(@file_get_contents($mf), true);
        foreach ((array)($m['entities'] ?? []) as $e) {
            if (!is_array($e)) continue;
            $id = strtolower(preg_replace('/[^a-z0-9]+/i', '_', (string)($e['id'] ?? '')));
            if (trim($id, '_') !== $entity) continue;
            $fields = [];
            foreach (array_slice((array)($e['fields'] ?? []), 0, 15) as $fl) {
                if (!is_array($fl) || empty($fl['name'])) continue;
                $fn = strtolower(preg_replace('/[^a-z0-9]+/i', '_', (string)$fl['name']));
                if ($fn === '' || $fn === 'id') continue;
                $t = strtolower((string)($fl['type'] ?? 'text'));
                if (!in_array($t, ['text','textarea','number','email','url','date','time','datetime','select','checkbox','tel'], true)) $t = 'text';
                $fields[] = ['name' => $fn, 'label' => (string)($fl['label'] ?? ucwords(str_replace('_', ' ', $fn))),
                    'type' => $t, 'required' => !empty($fl['required']),
                    'options' => ($t === 'select' && !empty($fl['options']) && is_array($fl['options'])) ? array_values(array_slice($fl['options'], 0, 12)) : []];
            }
            if (!empty($fields)) return $fields;
        }
    }
    // Fallback: derive visible columns from stored records (never hidden).
    $sample = null;
    foreach (db_read($entity) as $r) { if (is_array($r)) { $sample = $r; break; } }
    $fields = [];
    if (is_array($sample)) {
        foreach ($sample as $k => $v) {
            if (in_array($k, ['id', 'created_at', 'updated_at', 'idempotency_key'], true)) continue;
            if (count($fields) >= 8) break;
            $fields[] = ['name' => (string)$k, 'label' => ucwords(str_replace('_', ' ', (string)$k)),
                'type' => 'text', 'required' => false, 'options' => []];
        }
    }
    if (empty($fields)) {
        $fields = [['name' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true, 'options' => []],
                   ['name' => 'details', 'label' => 'Details', 'type' => 'textarea', 'required' => false, 'options' => []]];
    }
    return $fields;
}

} // end function_exists guard
PHP;
}

// ─── Generated file: admin/includes/layout.php ─────────────────────────────
function pak_layout_php(): string {
    return <<<'PHP'
<?php
// includes/layout.php — Shared admin chrome (generated by WebCraft AI).
// Sidebar is built DYNAMICALLY from manifest.json + on-disk data files,
// so entities added later appear without republishing the layout.
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_login();

function pak_nav_entities(): array {
    $ents = [];
    $mf = dirname(__DIR__) . '/manifest.json';
    if (is_file($mf)) {
        $m = json_decode(@file_get_contents($mf), true);
        foreach (array_slice((array)($m['entities'] ?? []), 0, 8) as $e) {
            if (!is_array($e) || empty($e['id'])) continue;
            $id = strtolower(preg_replace('/[^a-z0-9]+/i', '_', (string)$e['id']));
            if ($id === '') continue;
            $ents[$id] = ['id' => $id, 'name' => (string)($e['name'] ?? $id),
                'icon' => (string)($e['icon'] ?? '📁')];
        }
    }
    foreach (glob(dirname(__DIR__) . '/data_*.json') ?: [] as $df) {
        $did = preg_replace('/^data_|\.json$/', '', basename($df));
        if ($did === '' || $did === 'inquiries' || isset($ents[$did])) continue;
        if (!preg_match('/^[a-z0-9_]{1,40}$/', $did)) continue;
        $ents[$did] = ['id' => $did, 'name' => ucwords(str_replace('_', ' ', $did)), 'icon' => '📁'];
    }
    return array_values($ents);
}

function pak_unread_inbox(): int {
    $f = dirname(__DIR__) . '/data_inquiries.json';
    if (!is_file($f)) return 0;
    $rows = json_decode(@file_get_contents($f), true);
    if (!is_array($rows)) return 0;
    $n = 0;
    foreach ($rows as $r) { if (is_array($r) && empty($r['read'])) $n++; }
    return $n;
}

function pak_site_name(): string {
    $sf = dirname(__DIR__) . '/site.json';
    if (is_file($sf)) {
        $s = json_decode(@file_get_contents($sf), true);
        if (is_array($s) && !empty($s['site_name'])) return (string)$s['site_name'];
    }
    $cf = dirname(__DIR__) . '/content.json';
    if (is_file($cf)) {
        $c = json_decode(@file_get_contents($cf), true);
        if (is_array($c) && !empty($c['business_name'])) return (string)$c['business_name'];
    }
    return 'Website Admin';
}

function sidebar(string $active = 'dashboard'): void {
    $ents = pak_nav_entities();
    $unread = pak_unread_inbox();
    $user = htmlspecialchars(admin_current_user(), ENT_QUOTES, 'UTF-8');
    $site = htmlspecialchars(pak_site_name(), ENT_QUOTES, 'UTF-8');
    $nav = function (string $id, string $href, string $label) use ($active) {
        $cls = $active === $id ? 'nav-item active' : 'nav-item';
        return '<a href="' . $href . '" class="' . $cls . '">' . $label . '</a>';
    };
    echo '<aside class="sidebar">';
    echo '<div class="brand"><div class="logo">⚡</div><div style="min-width:0">'
        . '<div class="brand-name">' . $site . '</div>'
        . '<div style="font-size:.72rem;color:#64748b">Admin Panel</div></div></div>';
    echo '<nav style="flex:1">';
    echo $nav('dashboard', 'index.php', '📊 Dashboard');
    echo $nav('inbox', 'inbox.php', '✉️ Inbox' . ($unread > 0 ? ' <span class="badge badge-green" style="margin-left:auto">' . $unread . '</span>' : ''));
    foreach ($ents as $e) {
        echo $nav('ent_' . $e['id'], htmlspecialchars(db_entity_href($e['id'])),
            htmlspecialchars($e['icon'] . ' ' . $e['name']));
    }
    echo $nav('settings', 'settings.php', '⚙️ Settings');
    echo $nav('server', 'server.php', '🖥️ Server');
    echo '</nav>';
    echo '<div style="padding-top:1.5rem;border-top:1px solid #1e293b">'
        . '<div style="font-size:.75rem;color:#64748b;margin-bottom:.5rem;padding:0 .5rem">Logged in as: <strong style="color:#cbd5e1">' . $user . '</strong></div>'
        . '<a href="../index.html" target="_blank" class="nav-item" style="color:#10b981">🌐 View Live Site →</a>'
        . '<a href="logout.php" class="nav-item" style="color:#ef4444">🚪 Sign Out</a></div>';
    echo '</aside>';
}

function header_html(string $active = 'dashboard', string $title = ''): void {
    $site = htmlspecialchars(pak_site_name(), ENT_QUOTES, 'UTF-8');
    $t = $title !== '' ? htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . ' — ' : '';
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>' . $t . $site . ' Admin</title>'
        . '<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">'
        . '<style>'
        . '*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}'
        . 'body{font-family:\'Plus Jakarta Sans\',system-ui,sans-serif;background:#0a0d14;color:#e2e8f0;min-height:100vh;display:flex}'
        . 'a:focus-visible,button:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible{outline:2px solid #a855f7;outline-offset:2px}'
        . '.sidebar{width:260px;background:linear-gradient(180deg,#0d121c,#111126);border-right:1px solid #1e293b;padding:1.5rem 1rem;flex-shrink:0;min-height:100vh;display:flex;flex-direction:column}'
        . '.main{flex:1;padding:2rem 2.5rem;overflow-y:auto;max-width:1400px;background:radial-gradient(ellipse at top,rgba(99,102,241,.06),transparent 55%)}'
        . '.brand{display:flex;align-items:center;gap:.75rem;padding:0 .5rem 1.5rem;border-bottom:1px solid #1e293b;margin-bottom:1.25rem}'
        . '.brand .logo{width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#6366f1,#a855f7);display:flex;align-items:center;justify-content:center;font-size:1.1rem;box-shadow:0 6px 18px rgba(99,102,241,.4)}'
        . '.brand-name{font-weight:800;font-size:.95rem;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}'
        . '.nav-item{display:flex;align-items:center;gap:.75rem;padding:.7rem 1rem;border-radius:10px;color:#94a3b8;text-decoration:none;font-size:.88rem;font-weight:600;margin-bottom:.35rem;transition:.2s}'
        . '.nav-item:hover{background:#111622;color:#fff}'
        . '.nav-item.active{background:linear-gradient(135deg,rgba(99,102,241,.35),rgba(168,85,247,.25));color:#e0e7ff;font-weight:700;border:1px solid rgba(99,102,241,.4)}'
        . '.top-actions{display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;flex-wrap:wrap;gap:1rem}'
        . '.page-title{font-size:1.75rem;font-weight:900;color:#fff;letter-spacing:-.02em}'
        . '.btn{display:inline-flex;align-items:center;gap:.5rem;padding:.7rem 1.3rem;border-radius:10px;font-weight:700;font-size:.86rem;cursor:pointer;border:none;transition:.2s;text-decoration:none}'
        . '.btn-primary{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff}'
        . '.btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(99,102,241,.4)}'
        . '.btn-ghost{background:#1e293b;color:#94a3b8;border:1.5px solid #334155}'
        . '.btn-ghost:hover{border-color:#6366f1;color:#fff}'
        . '.btn-danger{background:rgba(239,68,68,.15);border:1px solid #ef4444;color:#fca5a5}'
        . '.btn-danger:hover{background:#ef4444;color:#fff}'
        . '.card{background:#111622;border:1px solid #1e293b;border-radius:18px;padding:1.75rem;margin-bottom:1.5rem;box-shadow:0 12px 32px rgba(0,0,0,.35)}'
        . '.grid4{display:grid;grid-template-columns:repeat(4,1fr);gap:1.25rem;margin-bottom:2rem}'
        . '.grid2{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem}'
        . '@media(max-width:1050px){.grid4{grid-template-columns:repeat(2,1fr)}}'
        . '@media(max-width:768px){body{flex-direction:column}.sidebar{width:100%;min-height:auto}.grid4,.grid2{grid-template-columns:1fr}.main{padding:1.25rem}}'
        . '.stat-box{background:#0b0f17;border:1px solid #1e293b;border-radius:14px;padding:1.25rem}'
        . '.stat-lbl{font-size:.76rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em}'
        . '.stat-val{font-size:1.75rem;font-weight:900;color:#fff;margin:.35rem 0}'
        . '.field{margin-bottom:1.25rem}.lbl{display:block;font-size:.78rem;font-weight:700;color:#cbd5e1;margin-bottom:.4rem;text-transform:uppercase;letter-spacing:.03em}'
        . '.inp,.select,textarea.inp{width:100%;padding:.75rem 1rem;border:1.5px solid #283347;border-radius:11px;background:#0b0f17;color:#fff;font-family:inherit;font-size:.9rem;transition:.2s}'
        . '.inp:focus,.select:focus,textarea.inp:focus{outline:none;border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.2)}'
        . 'table{width:100%;border-collapse:collapse;margin-top:1rem}th{text-align:left;padding:.75rem 1rem;font-size:.75rem;font-weight:700;color:#64748b;text-transform:uppercase;border-bottom:1px solid #1e293b}'
        . 'td{padding:.9rem 1rem;border-bottom:1px solid #1e293b;font-size:.85rem;color:#cbd5e1}tr:hover td{background:#0b0f17}'
        . '.badge{display:inline-block;padding:.2rem .65rem;border-radius:999px;font-size:.72rem;font-weight:800}'
        . '.badge-green{background:#064e3b;color:#34d399}.badge-blue{background:#1e1b4b;color:#a5b4fc}'
        . '.flash{background:rgba(16,185,129,.12);border:1px solid #10b981;color:#34d399;padding:.85rem 1.25rem;border-radius:12px;font-weight:700;margin-bottom:1.5rem}'
        . '.flash-err{background:rgba(239,68,68,.12);border-color:#ef4444;color:#fca5a5}'
        . '.pager{display:flex;gap:.5rem;align-items:center;margin-top:1rem;font-size:.82rem;color:#94a3b8}'
        . '.pager a{color:#a5b4fc;text-decoration:none;font-weight:700}'
        . '</style></head><body>';
    sidebar($active);
    echo '<main class="main">';
}

function footer_html(): void {
    echo '</main></body></html>';
}
PHP;
}

// ─── Generated per-entity CRUD page ────────────────────────────────────────
function pak_entity_php(array $entity): string {
    $id = (string)($entity['id'] ?? 'items');
    $label = addslashes((string)($entity['name'] ?? $id));
    $singular = addslashes((string)($entity['singular'] ?? $label));
    $code = <<<'PHP'
<?php
// __ENT__.php — CRUD management for __LABEL__ (generated by WebCraft AI).
// List (search + 20/page) · Create · Edit (real saved values) · Delete
// (POST + CSRF + confirm). All data via includes/db.php JSON storage.
ini_set('display_errors', '0');
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/layout.php';

$ENTITY = '__ENT__';
$LABEL = '__LABEL__';
$SINGULAR = '__SINGULAR__';
if (!db_entity_allowed($ENTITY)) { http_response_code(404); exit('Unknown section.'); }

$fields = db_field_defs($ENTITY);
$fieldKeys = array_map(static fn($field) => (string)($field['name'] ?? ''), $fields);
$isBusBookingPage = $ENTITY === 'bookings'
    && in_array('trip_id', $fieldKeys, true) && in_array('seats', $fieldKeys, true);
$isBusPaymentsPage = $ENTITY === 'payments'
    && in_array('payment_id', $fieldKeys, true) && in_array('booking_ref', $fieldKeys, true);
$isBusLifecyclePage = false; // Lifecycle locks removed: admin has full CRUD capability
$editableFields = $fields;
$flash = '';
$flashErr = '';
if (isset($_GET['flash'])) {
    $flash = (string)$_GET['flash'];
}

// ── Field validation + sanitization (server-side, always) ──
function ent_clean_value(array $f, $raw) {
    $type = $f['type'] ?? 'text';
    if ($type === 'checkbox') return !empty($raw) ? '1' : '0';
    $s = is_scalar($raw) ? trim((string)$raw) : '';
    $s = strip_tags($s);
    if (strlen($s) > 5000) $s = substr($s, 0, 5000);
    return $s;
}
function ent_validate(array $fields, array $input, &$clean, &$err): bool {
    $clean = []; $err = '';
    foreach ($fields as $f) {
        $name = $f['name'];
        $v = ent_clean_value($f, $input[$name] ?? '');
        $type = $f['type'] ?? 'text';
        if (!empty($f['required']) && $v === '') { $err = ($f['label'] ?? $name) . ' is required.'; return false; }
        if ($v !== '') {
            if ($type === 'email' && !filter_var($v, FILTER_VALIDATE_EMAIL)) { $err = 'Invalid email in ' . ($f['label'] ?? $name) . '.'; return false; }
            if ($type === 'url' && !filter_var($v, FILTER_VALIDATE_URL)) { $err = 'Invalid URL in ' . ($f['label'] ?? $name) . '.'; return false; }
            if ($type === 'number' && !is_numeric($v)) { $err = ($f['label'] ?? $name) . ' must be a number.'; return false; }
            if ($type === 'date' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) { $err = ($f['label'] ?? $name) . ' must be YYYY-MM-DD.'; return false; }
            if ($type === 'select' && !empty($f['options']) && !in_array($v, $f['options'], true)) { $err = 'Invalid choice for ' . ($f['label'] ?? $name) . '.'; return false; }
        }
        $clean[$name] = $v;
    }
    return true;
}

// ── Mutations (POST only, authenticated via layout, CSRF enforced) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'save') {
        $id = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)($_POST['id'] ?? ''));
        if (ent_validate($editableFields, $_POST, $clean, $err)) {
            if ($id !== '') {
                $ok = db_update($ENTITY, $id, $clean);
                $dest = $ENTITY . '.php?flash=' . urlencode($ok ? 'Record updated.' : 'Record not found.');
            } else {
                if ($isBusBookingPage) {
                    $clean['status'] = 'pending';
                    $clean['payment_status'] = 'unpaid';
                }
                $row = db_insert($ENTITY, $clean);
                $dest = $ENTITY . '.php?flash=' . urlencode($row ? 'New record added.' : 'Storage failed. Nothing was saved.');
            }
            header('Location: ' . $dest); // Post/Redirect/Get
            exit;
        }
        $flashErr = $err;
    } elseif ($action === 'delete') {
        $id = (string)($_POST['id'] ?? '');
        $ok = db_delete($ENTITY, $id);
        header('Location: ' . $ENTITY . '.php?flash=' . urlencode($ok ? 'Record deleted.' : 'Record not found.'));
        exit;
    } else {
        http_response_code(400);
        exit('Unknown action.');
    }
}

// ── List: search + pagination (20 per page) ──
$q = strtolower(trim((string)($_GET['q'] ?? '')));
$page = max(1, (int)($_GET['page'] ?? 1));
// Backward-compatible booking view: legacy rows stored under camelCase or
// alternate keys (tripId, seat_numbers, booking_ref, …) resolve to the
// canonical trip_id/seats/ref so the table never shows "—" for data that is
// actually present. Truly missing values still render as "—".
if (!function_exists('bus_booking_cell')) {
    function bus_booking_cell(array $row, string $field): string {
        if ($field === 'trip_id') {
            foreach (['trip_id','tripId','tripID','trip','booking_trip','trip_ref'] as $k) {
                if (isset($row[$k]) && trim((string)$row[$k]) !== '') return trim((string)$row[$k]);
            }
            return '—';
        }
        if ($field === 'seats') {
            // seats_display = the label the customer actually clicked
            // (e.g. "6C"); seats = canonical fleet seat (e.g. "F3").
            foreach (['seats_display','seats','seat_numbers','seatNumbers','seat','seats_string','selected_seats'] as $k) {
                if (!array_key_exists($k, $row) || $row[$k] === '' || $row[$k] === null) continue;
                $v = $row[$k];
                if (is_array($v)) { $s = implode(', ', array_slice(array_map('strval', $v), 0, 8)); if ($s !== '') return $s; }
                else { $s = trim((string)$v); if ($s !== '') return $s; }
            }
            return '—';
        }
        $v = $row[$field] ?? '—';
        if (is_array($v)) $v = implode(', ', array_slice(array_map('strval', $v), 0, 4));
        $s = (string)$v; if (strlen($s) > 60) $s = substr($s, 0, 60) . '…';
        return $s === '' ? '—' : $s;
    }
}
$all = db_read($ENTITY);
if ($ENTITY === 'bookings') {
    foreach ($all as &$normalizeRow) {
        if (!is_array($normalizeRow)) continue;
        $viewTrip = bus_booking_cell($normalizeRow, 'trip_id');
        $viewSeats = bus_booking_cell($normalizeRow, 'seats');
        if ($viewTrip !== '—' && empty($normalizeRow['trip_id'])) $normalizeRow['trip_id'] = $viewTrip;
        if ($viewSeats !== '—' && (empty($normalizeRow['seats']) || $normalizeRow['seats'] === '')) $normalizeRow['seats'] = $viewSeats;
    }
    unset($normalizeRow);
}
if ($q !== '') {
    $all = array_values(array_filter($all, function ($r) use ($q) {
        if (!is_array($r)) return false;
        foreach ($r as $v) {
            if (is_scalar($v) && stripos((string)$v, $q) !== false) return true;
        }
        return false;
    }));
}
$total = count($all);
$pages = max(1, (int)ceil($total / 20));
if ($page > $pages) $page = $pages;
$rows = array_slice($all, ($page - 1) * 20, 20);
$reportRows = $all; // respect the active ?q= filter (export + embedded data)
$tableFields = $isBusBookingPage
    ? array_values(array_filter($fields, static fn($field) =>
        in_array((string)($field['name'] ?? ''), ['trip_id', 'seats', 'passenger', 'phone', 'email', 'status'], true)))
    : ($isBusPaymentsPage
        ? array_values(array_filter($fields, static fn($field) =>
            in_array((string)($field['name'] ?? ''), ['payment_id', 'booking_ref', 'method', 'amount', 'status', 'received_at'], true)))
        : array_slice($fields, 0, 5));

// ── Edit target: load ACTUAL saved values ──
$editId = isset($_GET['edit']) ? (string)$_GET['edit'] : '';
$editRow = $editId !== '' ? db_find($ENTITY, $editId) : null;
$showForm = !$isBusLifecyclePage && (isset($_GET['new']) || $editRow !== null);
if ($editId !== '' && $editRow === null && $_SERVER['REQUEST_METHOD'] !== 'POST') $flashErr = 'Record not found.';

function ent_input(array $f, $val): string {
    $n = htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8');
    $v = htmlspecialchars((string)($val ?? ''), ENT_QUOTES, 'UTF-8');
    $req = !empty($f['required']) ? ' required' : '';
    switch ($f['type'] ?? 'text') {
        case 'textarea': return '<textarea class="inp" name="' . $n . '" rows="3"' . $req . '>' . $v . '</textarea>';
        case 'number': return '<input class="inp" type="number" step="any" name="' . $n . '" value="' . $v . '"' . $req . '>';
        case 'email': return '<input class="inp" type="email" name="' . $n . '" value="' . $v . '"' . $req . '>';
        case 'url': return '<input class="inp" type="url" name="' . $n . '" value="' . $v . '" placeholder="https://…"' . $req . '>';
        case 'date': return '<input class="inp" type="date" name="' . $n . '" value="' . $v . '"' . $req . '>';
        case 'time': return '<input class="inp" type="time" name="' . $n . '" value="' . $v . '"' . $req . '>';
        case 'checkbox': return '<input type="checkbox" name="' . $n . '" value="1"' . ($v === '1' ? ' checked' : '') . ' style="width:18px;height:18px;accent-color:#6366f1">';
        case 'select':
            $h = '<select class="inp" name="' . $n . '"' . $req . '><option value="">— Select —</option>';
            foreach (($f['options'] ?? []) as $o) {
                $o = (string)$o;
                $h .= '<option value="' . htmlspecialchars($o, ENT_QUOTES, 'UTF-8') . '"' . ($v === $o ? ' selected' : '') . '>' . htmlspecialchars($o) . '</option>';
            }
            return $h . '</select>';
        default: return '<input class="inp" type="text" name="' . $n . '" value="' . $v . '"' . $req . ' maxlength="5000">';
    }
}

header_html('ent_' . $ENTITY, $LABEL);
?>
<div class="top-actions">
  <div>
    <h1 class="page-title"><?= htmlspecialchars($LABEL) ?></h1>
    <p style="color:#64748b;font-size:.88rem;margin-top:.3rem"><?= (int)$total ?> record(s) · stored in JSON · updates appear immediately</p>
  </div>
  <?php if (!$isBusLifecyclePage): ?><a class="btn btn-primary" href="<?= $ENTITY ?>.php?new=1">➕ Add <?= htmlspecialchars($SINGULAR) ?></a><?php endif; ?>
</div>

<?php if ($flash): ?><div class="flash">✅ <?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="flash flash-err">⚠️ <?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

<?php if ($showForm): ?>
<div class="card">
  <h2 style="font-size:1.1rem;font-weight:800;color:#fff;margin-bottom:1.25rem">
    <?= $editRow ? '✏️ Edit ' . htmlspecialchars($SINGULAR) : '➕ Add ' . htmlspecialchars($SINGULAR) ?>
  </h2>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= htmlspecialchars($editRow['id'] ?? '') ?>">
    <?php foreach ($editableFields as $f): ?>
      <div class="field">
        <label class="lbl"><?= htmlspecialchars($f['label'] ?? $f['name']) ?><?= !empty($f['required']) ? ' *' : '' ?></label>
        <?= ent_input($f, $editRow[$f['name']] ?? '') ?>
      </div>
    <?php endforeach; ?>
    <div style="display:flex;gap:.75rem">
      <button type="submit" class="btn btn-primary">💾 Save</button>
      <a class="btn btn-ghost" href="<?= $ENTITY ?>.php">Cancel</a>
    </div>
  </form>
</div>
<?php endif; ?>

<?php if ($ENTITY === 'customers' && $editRow !== null): ?>
<?php
  $custEmail = strtolower(trim((string)($editRow['email'] ?? '')));
  $custPhone = trim((string)($editRow['phone'] ?? ''));
  $custBookings = [];
  $custSpent = 0.0;
  foreach (db_read('bookings') as $b) {
    if (!is_array($b)) continue;
    $bEmail = strtolower(trim((string)($b['email'] ?? '')));
    $bPhone = trim((string)($b['phone'] ?? ''));
    if (($custEmail !== '' && $bEmail === $custEmail) || ($custPhone !== '' && $bPhone === $custPhone)) {
      $custBookings[] = $b;
      $custSpent += (float)($b['amount'] ?? 0);
    }
  }
?>
<div class="card">
  <h2 style="font-size:1.1rem;font-weight:800;color:#fff;margin-bottom:1rem">👤 Customer details</h2>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:.75rem;margin-bottom:1.25rem">
    <div style="border:1px solid #263244;border-radius:10px;padding:.8rem">
      <div style="color:#64748b;font-size:.72rem;font-weight:700">BOOKINGS</div>
      <div style="color:#fff;font-size:1.4rem;font-weight:800"><?= count($custBookings) ?></div>
    </div>
    <div style="border:1px solid #263244;border-radius:10px;padding:.8rem">
      <div style="color:#64748b;font-size:.72rem;font-weight:700">TOTAL SPENT</div>
      <div style="color:#fff;font-size:1.4rem;font-weight:800"><?= htmlspecialchars(number_format($custSpent, 2)) ?></div>
    </div>
    <div style="border:1px solid #263244;border-radius:10px;padding:.8rem">
      <div style="color:#64748b;font-size:.72rem;font-weight:700">MEMBER SINCE</div>
      <div style="color:#fff;font-size:.95rem;font-weight:800"><?= htmlspecialchars(substr((string)($editRow['created_at'] ?? ''), 0, 10)) ?: '—' ?></div>
    </div>
    <div style="border:1px solid #263244;border-radius:10px;padding:.8rem">
      <div style="color:#64748b;font-size:.72rem;font-weight:700">STATUS</div>
      <div style="color:#fff;font-size:.95rem;font-weight:800"><?= htmlspecialchars((string)($editRow['status'] ?? 'active')) ?></div>
    </div>
  </div>
  <?php if (!$custBookings): ?>
    <p style="color:#64748b;font-size:.85rem">No bookings recorded for this customer yet.</p>
  <?php else: ?>
  <div style="overflow-x:auto"><table>
    <thead><tr><th>Ref</th><th>Trip</th><th>Route</th><th>Date</th><th>Seats</th><th>Amount</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($custBookings as $b):
      $bSeats = $b['seats_display'] ?? ($b['seats'] ?? []);
      if (!is_array($bSeats)) $bSeats = preg_split('/[\s,;|]+/', (string)$bSeats, -1, PREG_SPLIT_NO_EMPTY);
    ?>
      <tr>
        <td><?= htmlspecialchars((string)($b['ref'] ?? $b['id'] ?? '')) ?></td>
        <td><?= htmlspecialchars((string)($b['trip_id'] ?? '')) ?></td>
        <td><?= htmlspecialchars((string)($b['route'] ?? '')) ?></td>
        <td><?= htmlspecialchars((string)($b['trip_date'] ?? '')) ?></td>
        <td><?= htmlspecialchars(implode(', ', array_map('strval', $bSeats))) ?></td>
        <td><?= htmlspecialchars((string)($b['amount'] ?? '')) ?></td>
        <td><?= htmlspecialchars((string)($b['status'] ?? '')) ?></td>
        <td><a class="btn btn-ghost" style="padding:.3rem .6rem;font-size:.75rem" href="bookings.php?edit=<?= urlencode((string)($b['id'] ?? '')) ?>">Open</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
  <form method="GET" style="display:flex;gap:.6rem;flex-wrap:wrap">
    <input class="inp" type="search" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" placeholder="Search <?= htmlspecialchars($LABEL) ?>…" style="max-width:300px">
    <button class="btn btn-ghost" type="submit">🔍 Search</button>
    <button class="btn btn-ghost" type="button" onclick="downloadEntityReport()">Download report</button>
    <?php if ($q !== ''): ?><a class="btn btn-ghost" href="<?= $ENTITY ?>.php">Clear</a><?php endif; ?>
  </form>
  <?php if ($isBusBookingPage): ?>
    <section style="border:1px solid #263244;border-radius:12px;padding:1rem;margin:1rem 0;background:#0b111d">
      <h2 style="margin:0 0 .35rem;color:#fff;font-size:1rem">Record a received payment</h2>
      <p style="margin:0 0 .85rem;color:#94a3b8;font-size:.82rem">Load the saved fare by ticket reference. Select Card or Cash only after the operator receives payment.</p>
      <form id="bus-payment-form" style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end">
        <label style="display:grid;gap:.35rem;min-width:210px;flex:1;color:#cbd5e1;font-size:.78rem;font-weight:700">
          Ticket reference
          <input class="inp" id="bus-payment-ref" required maxlength="50" placeholder="BK-XXXXXXXX">
        </label>
        <button class="btn btn-ghost" id="bus-payment-lookup" type="button">Load fare</button>
        <label style="display:grid;gap:.35rem;min-width:150px;color:#cbd5e1;font-size:.78rem;font-weight:700">
          Method
          <select class="inp" id="bus-payment-method" required>
            <option value="">Select method</option><option value="card">Card received</option><option value="cash">Cash received</option>
          </select>
        </label>
        <button class="btn btn-primary" id="bus-payment-submit" type="submit" disabled>Save payment</button>
      </form>
      <p id="bus-payment-status" aria-live="polite" style="margin:.75rem 0 0;color:#94a3b8;font-size:.85rem">No ticket loaded.</p>
    </section>
  <?php endif; ?>
  <?php if (empty($rows)): ?>
    <div style="text-align:center;padding:3rem 1rem;color:#64748b">
      <div style="font-size:2rem;margin-bottom:.5rem">📂</div>
      <p><?= $total === 0 ? 'No records yet — customer submissions will appear here automatically.' : 'No matches for this search.' ?></p>
    </div>
  <?php else: ?>
    <div style="overflow-x:auto"><table>
      <thead><tr>
        <?php foreach ($tableFields as $f): ?><th><?= htmlspecialchars($f['label'] ?? $f['name']) ?></th><?php endforeach; ?>
        <th>Updated</th><th style="text-align:right">Actions</th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <?php foreach ($tableFields as $f):
            $v = ($isBusBookingPage && in_array($f['name'], ['trip_id','seats'], true) && function_exists('bus_booking_cell'))
              ? bus_booking_cell(is_array($r) ? $r : [], $f['name'])
              : ($r[$f['name']] ?? '—');
            if (is_array($v)) $v = implode(', ', array_slice(array_map('strval', $v), 0, 4));
            $s = (string)$v; if (strlen($s) > 60) $s = substr($s, 0, 60) . '…';
          ?>
            <td><?= htmlspecialchars($s === '' ? '—' : $s) ?></td>
          <?php endforeach; ?>
          <td style="color:#64748b;font-size:.78rem"><?= htmlspecialchars(substr((string)($r['updated_at'] ?? $r['created_at'] ?? ''), 0, 16)) ?></td>
          <td style="text-align:right;white-space:nowrap">
            <?php if ($isBusBookingPage && ($r['status'] ?? 'pending') === 'pending'): ?>
              <button class="btn btn-primary" style="padding:.35rem .6rem;font-size:.76rem" type="button"
                onclick='setBusBookingStatus(<?= json_encode((string)($r['id'] ?? '')) ?>,"approved")'>Approve</button>
              <button class="btn btn-danger" style="padding:.35rem .6rem;font-size:.76rem" type="button"
                onclick='setBusBookingStatus(<?= json_encode((string)($r['id'] ?? '')) ?>,"rejected")'>Reject</button>
            <?php endif; ?>
            <?php if (!$isBusLifecyclePage): ?>
              <a class="btn btn-ghost" style="padding:.35rem .75rem;font-size:.78rem" href="<?= $ENTITY ?>.php?edit=<?= urlencode((string)($r['id'] ?? '')) ?>">✏️ Edit</a>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete this record permanently?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= htmlspecialchars((string)($r['id'] ?? '')) ?>">
                <button class="btn btn-danger" style="padding:.35rem .75rem;font-size:.78rem" type="submit">🗑️</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <div class="pager">
      <span>Page <?= $page ?> of <?= $pages ?> (<?= (int)$total ?> total)</span>
      <?php if ($page > 1): ?><a href="<?= $ENTITY ?>.php?q=<?= urlencode($q) ?>&amp;page=<?= $page - 1 ?>">← Prev</a><?php endif; ?>
      <?php if ($page < $pages): ?><a href="<?= $ENTITY ?>.php?q=<?= urlencode($q) ?>&amp;page=<?= $page + 1 ?>">Next →</a><?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php if ($isBusBookingPage): ?>
<script>
const BUS_ADMIN_CSRF = <?= json_encode(csrf_token()) ?>;
const BUS_ADMIN_SLUG = <?= json_encode(db_site_slug()) ?>;
let busPaymentQuote = null;
function busOps(payload) {
  return fetch('../../../api/business-ops.php', {
    method:'POST', credentials:'same-origin',
    headers:{'Content-Type':'application/json','X-Admin-CSRF':BUS_ADMIN_CSRF},
    body:JSON.stringify(Object.assign({slug:BUS_ADMIN_SLUG},payload))
  }).then(async function(response) {
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.error || 'Request failed.');
    return result;
  });
}
function setBusBookingStatus(id,status) {
  if (!confirm('Set this booking to ' + status + '?')) return;
  busOps({action:'update',entity:'bookings',id:id,patch:{status:status}})
    .then(function(result) {
      const note = result.notification || {};
      alert('Booking ' + status + '. ' + (note.status === 'queued'
        ? 'Customer email queued.'
        : (note.error || 'No valid email address was saved for this booking.')));
      location.reload();
    })
    .catch(function(error) { alert('Booking status was not updated: ' + error.message); });
}
function downloadEntityReport() {
  const records = <?= json_encode($reportRows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  if (!records.length) { alert('There are no records to export.'); return; }
  const columns = Array.from(new Set(records.reduce(function(keys,row) {
    return keys.concat(Object.keys(row || {}));
  },[])));
  const csvCell = function(value) {
    if (Array.isArray(value) || (value && typeof value === 'object')) value = JSON.stringify(value);
    return '"' + String(value == null ? '' : value).replace(/"/g,'""') + '"';
  };
  const csv = [columns.map(csvCell).join(','), ...records.map(function(row) {
    return columns.map(function(key) { return csvCell(row[key]); }).join(',');
  })].join('\r\n');
  const url = URL.createObjectURL(new Blob(['\uFEFF' + csv],{type:'text/csv;charset=utf-8'}));
  const link = document.createElement('a');
  link.href = url;
  link.download = BUS_ADMIN_SLUG + '-<?= htmlspecialchars($ENTITY, ENT_QUOTES, 'UTF-8') ?>-' + new Date().toISOString().slice(0,10) + '.csv';
  link.click();
  setTimeout(function(){URL.revokeObjectURL(url);},1000);
}
document.getElementById('bus-payment-lookup').addEventListener('click',function() {
  const out = document.getElementById('bus-payment-status');
  const ref = document.getElementById('bus-payment-ref').value.trim().toUpperCase();
  const submit = document.getElementById('bus-payment-submit');
  busPaymentQuote = null; submit.disabled = true;
  if (!ref) { out.textContent = 'Enter a ticket reference first.'; return; }
  out.textContent = 'Loading saved fare…';
  busOps({action:'payment_quote',entity:'bookings',ref:ref})
    .then(function(result) {
      busPaymentQuote = result.data;
      out.textContent = result.data.ref + ' · ' + (result.data.passenger || 'Customer') + ' · '
        + result.data.currency + ' ' + Number(result.data.amount).toFixed(2)
        + ' · ' + result.data.status;
      submit.disabled = result.data.status !== 'approved';
      if (submit.disabled) out.textContent += ' · Approve booking before recording payment.';
    })
    .catch(function(error) { out.textContent = error.message; });
});
document.getElementById('bus-payment-form').addEventListener('submit',function(event) {
  event.preventDefault();
  if (!busPaymentQuote) { alert('Load a ticket fare first.'); return; }
  const method = document.getElementById('bus-payment-method').value;
  const submit = document.getElementById('bus-payment-submit');
  submit.disabled = true;
  busOps({action:'pay',entity:'bookings',ref:busPaymentQuote.ref,method:method})
    .then(function(result) {
      const payment = result.payment || {};
      document.getElementById('bus-payment-status').textContent = 'Payment saved: '
        + (payment.payment_id || payment.id) + ' · ' + payment.currency + ' '
        + Number(payment.amount).toFixed(2) + ' · ' + method + ' · ' + payment.received_at;
      busPaymentQuote = null;
      document.getElementById('bus-payment-form').reset();
      setTimeout(function(){location.reload();},1200);
    })
    .catch(function(error) {
      document.getElementById('bus-payment-status').textContent = error.message;
      submit.disabled = false;
    });
});
</script>
<?php else: ?>
<script>
function downloadEntityReport() {
  const records = <?= json_encode($reportRows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  if (!records.length) { alert('There are no records to export.'); return; }
  const columns = Array.from(new Set(records.reduce(function(keys,row) {
    return keys.concat(Object.keys(row || {}));
  },[])));
  const csvCell = function(value) {
    if (Array.isArray(value) || (value && typeof value === 'object')) value = JSON.stringify(value);
    return '"' + String(value == null ? '' : value).replace(/"/g,'""') + '"';
  };
  const csv = [columns.map(csvCell).join(','), ...records.map(function(row) {
    return columns.map(function(key) { return csvCell(row[key]); }).join(',');
  })].join('\r\n');
  const url = URL.createObjectURL(new Blob(['\uFEFF' + csv],{type:'text/csv;charset=utf-8'}));
  const link = document.createElement('a');
  link.href = url;
  link.download = '<?= htmlspecialchars($ENTITY, ENT_QUOTES, 'UTF-8') ?>-' + new Date().toISOString().slice(0,10) + '.csv';
  link.click();
  setTimeout(function(){URL.revokeObjectURL(url);},1000);
}
</script>
<?php endif; ?>

<?php footer_html(); ?>
PHP;
    return str_replace(['__ENT__', '__LABEL__', '__SINGULAR__'], [$id, $label, $singular], $code);
}

// ─── Generated file: admin/inbox.php ───────────────────────────────────────
function pak_inbox_php(): string {
    return <<<'PHP'
<?php
// inbox.php — Customer inquiry inbox (generated by WebCraft AI).
// Reads data_inquiries.json (the contact.php + submit.php destination).
// Backward compatible with messages.php records and legacy shapes.
ini_set('display_errors', '0');
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $action = (string)($_POST['action'] ?? '');
    $id = (string)($_POST['id'] ?? '');
    if ($action === 'mark_read' && $id !== '') {
        db_update('inquiries', $id, ['read' => true]);
        header('Location: inbox.php?flash=' . urlencode('Marked as read.'));
        exit;
    }
    if ($action === 'mark_unread' && $id !== '') {
        db_update('inquiries', $id, ['read' => false]);
        header('Location: inbox.php?flash=' . urlencode('Marked as unread.'));
        exit;
    }
    if ($action === 'delete' && $id !== '') {
        db_delete('inquiries', $id);
        header('Location: inbox.php?flash=' . urlencode('Inquiry deleted.'));
        exit;
    }
}

$rows = db_read('inquiries');
$unread = 0;
foreach ($rows as $r) { if (is_array($r) && empty($r['read'])) $unread++; }
$flash = (string)($_GET['flash'] ?? '');

header_html('inbox', 'Inbox');
?>
<div class="top-actions">
  <div>
    <h1 class="page-title">✉️ Inbox</h1>
    <p style="color:#64748b;font-size:.88rem;margin-top:.3rem"><?= (int)count($rows) ?> message(s) · <?= (int)$unread ?> unread · new contact-form submissions appear here automatically</p>
  </div>
</div>

<?php if ($flash): ?><div class="flash">✅ <?= htmlspecialchars($flash) ?></div><?php endif; ?>

<div class="card">
  <?php if (empty($rows)): ?>
    <div style="text-align:center;padding:3rem 1rem;color:#64748b">
      <div style="font-size:2rem;margin-bottom:.5rem">📥</div>
      <p>No inquiries yet. Messages from your website contact form will list here.</p>
    </div>
  <?php else: ?>
    <?php foreach ($rows as $m):
      if (!is_array($m)) continue;
      $isRead = !empty($m['read']);
      $name = (string)($m['name'] ?? 'Visitor');
      $email = (string)($m['email'] ?? '');
      $phone = (string)($m['phone'] ?? '');
      $msg = (string)($m['message'] ?? $m['enquiry'] ?? $m['comments'] ?? '');
      $date = (string)($m['date'] ?? substr((string)($m['created_at'] ?? ''), 0, 16));
    ?>
    <div style="background:#0b0f17;border:1px solid <?= $isRead ? '#1e293b' : '#10b981' ?>;border-radius:12px;padding:1rem 1.2rem;margin-bottom:.85rem">
      <div style="display:flex;justify-content:space-between;gap:.6rem;flex-wrap:wrap;margin-bottom:.4rem">
        <strong style="color:#fff"><?= htmlspecialchars($name) ?></strong>
        <span style="font-size:.74rem;color:#64748b"><?= htmlspecialchars($date) ?>
          <?= $isRead ? '' : '<span class="badge badge-green" style="margin-left:.4rem">NEW</span>' ?></span>
      </div>
      <div style="font-size:.82rem;color:#94a3b8;margin-bottom:.5rem">
        <?php if ($email !== ''): ?>📧 <a href="mailto:<?= htmlspecialchars($email) ?>" style="color:#38bdf8"><?= htmlspecialchars($email) ?></a><?php endif; ?>
        <?php if ($phone !== ''): ?> &nbsp; 📞 <?= htmlspecialchars($phone) ?><?php endif; ?>
      </div>
      <div style="font-size:.88rem;color:#cbd5e1;line-height:1.6;white-space:pre-wrap"><?= htmlspecialchars($msg) ?></div>
      <div style="display:flex;gap:.5rem;margin-top:.75rem;flex-wrap:wrap">
        <?php if ($email !== ''): ?>
          <a class="btn btn-ghost" style="padding:.4rem .9rem;font-size:.78rem"
             href="mailto:<?= htmlspecialchars($email) ?>?subject=<?= rawurlencode('Re: Your enquiry') ?>">↩️ Reply via email</a>
        <?php endif; ?>
        <form method="POST" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= htmlspecialchars((string)($m['id'] ?? '')) ?>">
          <input type="hidden" name="action" value="<?= $isRead ? 'mark_unread' : 'mark_read' ?>">
          <button class="btn btn-ghost" style="padding:.4rem .9rem;font-size:.78rem" type="submit"><?= $isRead ? 'Mark unread' : '✓ Mark as read' ?></button>
        </form>
        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this inquiry?');">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= htmlspecialchars((string)($m['id'] ?? '')) ?>">
          <input type="hidden" name="action" value="delete">
          <button class="btn btn-danger" style="padding:.4rem .9rem;font-size:.78rem" type="submit">🗑️</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php footer_html(); ?>
PHP;
}

// ─── Generated file: admin/settings.php ────────────────────────────────────
function pak_settings_php(): string {
    return <<<'PHP'
<?php
// settings.php — Admin password + site settings (generated by WebCraft AI).
// Password: hash-only (password_hash/verify), flock-guarded, never exposed.
// Site settings: admin/site.json (kept separate from .auth.json credentials).
ini_set('display_errors', '0');
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

$authFile = pak_auth_file();
$siteFile = __DIR__ . '/site.json';
$okMsg = '';
$errMsg = '';

function pak_lock_write_json(string $file, array $data): bool {
    $tmp = $file . '.tmp-' . bin2hex(random_bytes(4));
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false || @file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    if (@rename($tmp, $file)) return true;
    @unlink($tmp);
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'change_password') {
        $curr = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $conf = (string)($_POST['confirm_password'] ?? '');
        $auth = pak_auth_data();
        $hash = (string)($auth['hash'] ?? '');
        if ($hash === '' || !password_verify($curr, $hash)) {
            $errMsg = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $errMsg = 'New password must be at least 8 characters.';
        } elseif ($new !== $conf) {
            $errMsg = 'New passwords do not match.';
        } else {
            // Hash-only: plaintext is never written to disk.
            $auth['hash'] = password_hash($new, PASSWORD_DEFAULT);
            unset($auth['password_plain'], $auth['password']);
            $auth['updated_at'] = date('Y-m-d H:i:s');
            $errMsg = pak_lock_write_json($authFile, $auth) ? '' : 'Could not save — storage unavailable. Password unchanged.';
            if ($errMsg === '') {
                session_regenerate_id(true);
                // Sync the new hash back to the platform order so a later
                // republish preserves the changed password instead of
                // restoring the stale setup credential.
                $oidSync = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)($auth['order_id'] ?? ''));
                if ($oidSync !== '') {
                    $oFile = dirname(__DIR__, 3) . '/storage/orders/' . $oidSync . '.json';
                    if (is_file($oFile)) {
                        $ord = json_decode(@file_get_contents($oFile), true);
                        if (is_array($ord)) {
                            $ord['admin_password_hash'] = $auth['hash'];
                            unset($ord['admin_password_plain']);
                            if (!pak_lock_write_json($oFile, $ord)) {
                                $errMsg = 'Password changed, but saved generation credentials could not be synchronized. Check storage permissions.';
                            }
                        } else $errMsg = 'Password changed, but the saved generation credentials could not be read.';
                    }
                }
                if ($errMsg === '') $okMsg = 'Password changed successfully.';
            }
        }
    } elseif ($action === 'save_site') {
        $site = is_file($siteFile) ? (json_decode(@file_get_contents($siteFile), true) ?: []) : [];
        if (!is_array($site)) $site = [];
        $name = strip_tags(trim((string)($_POST['site_name'] ?? '')));
        $email = trim((string)($_POST['contact_email'] ?? ''));
        $logo = trim((string)($_POST['logo'] ?? ''));
        if ($name === '' || strlen($name) > 120) { $errMsg = 'Site name is required (max 120 chars).'; }
        elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errMsg = 'Invalid contact email.'; }
        elseif ($logo !== '' && !filter_var($logo, FILTER_VALIDATE_URL) && !preg_match('#^[A-Za-z0-9_\-./]{1,120}$#', $logo)) { $errMsg = 'Logo must be a URL or a safe relative asset path.'; }
        else {
            $site['site_name'] = $name;
            $site['contact_email'] = $email;
            $site['logo'] = $logo;
            $site['phone'] = substr(strip_tags(trim((string)($_POST['phone'] ?? ''))), 0, 40);
            $site['address'] = substr(strip_tags(trim((string)($_POST['address'] ?? ''))), 0, 200);
            $site['updated_at'] = date('c');
            if (pak_lock_write_json($siteFile, $site)) $okMsg = 'Site settings saved.';
            else $errMsg = 'Could not save — storage unavailable.';
        }
    } else {
        http_response_code(400);
        $errMsg = 'Unknown action.';
    }
}

$site = is_file($siteFile) ? (json_decode(@file_get_contents($siteFile), true) ?: []) : [];
if (!is_array($site)) $site = [];
$auth = pak_auth_data();

header_html('settings', 'Settings');
?>
<div class="top-actions">
  <div>
    <h1 class="page-title">⚙️ Settings</h1>
    <p style="color:#64748b;font-size:.88rem;margin-top:.3rem">Password (hash-only) and public site settings (stored separately in site.json).</p>
  </div>
</div>

<?php if ($okMsg): ?><div class="flash">✅ <?= htmlspecialchars($okMsg) ?></div><?php endif; ?>
<?php if ($errMsg): ?><div class="flash flash-err">⚠️ <?= htmlspecialchars($errMsg) ?></div><?php endif; ?>

<div class="grid2">
  <div class="card">
    <h2 style="font-size:1.15rem;font-weight:800;color:#fff;margin-bottom:1.25rem">🔑 Administrator password</h2>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="change_password">
      <div class="field"><label class="lbl">Current password</label>
        <input class="inp" type="password" name="current_password" required autocomplete="current-password"></div>
      <div class="field"><label class="lbl">New password (min 8 chars)</label>
        <input class="inp" type="password" name="new_password" required minlength="8" autocomplete="new-password"></div>
      <div class="field"><label class="lbl">Confirm new password</label>
        <input class="inp" type="password" name="confirm_password" required minlength="8" autocomplete="new-password"></div>
      <button class="btn btn-primary" style="width:100%" type="submit">Update password →</button>
    </form>
  </div>
  <div class="card">
    <h2 style="font-size:1.15rem;font-weight:800;color:#fff;margin-bottom:1.25rem">🌐 Site settings</h2>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_site">
      <div class="field"><label class="lbl">Site name</label>
        <input class="inp" type="text" name="site_name" maxlength="120" required
               value="<?= htmlspecialchars((string)($site['site_name'] ?? '')) ?>"></div>
      <div class="field"><label class="lbl">Logo (URL or asset path)</label>
        <input class="inp" type="text" name="logo" maxlength="500"
               value="<?= htmlspecialchars((string)($site['logo'] ?? '')) ?>" placeholder="https://… or assets/logo.png"></div>
      <div class="field"><label class="lbl">Contact email</label>
        <input class="inp" type="email" name="contact_email"
               value="<?= htmlspecialchars((string)($site['contact_email'] ?? $auth['email'] ?? '')) ?>"></div>
      <div class="field"><label class="lbl">Phone</label>
        <input class="inp" type="text" name="phone" maxlength="40"
               value="<?= htmlspecialchars((string)($site['phone'] ?? '')) ?>"></div>
      <div class="field"><label class="lbl">Address</label>
        <input class="inp" type="text" name="address" maxlength="200"
               value="<?= htmlspecialchars((string)($site['address'] ?? '')) ?>"></div>
      <button class="btn btn-ghost" style="width:100%" type="submit">Save site settings</button>
    </form>
  </div>
</div>

<?php footer_html(); ?>
PHP;
}

// ─── Generated file: admin/api.php ─────────────────────────────────────────
function pak_api_php(): string {
    return <<<'PHP'
<?php
// api.php — Authenticated admin AJAX API (generated by WebCraft AI).
//   GET  ?action=list&entity=X[&q=…]
//   GET  ?action=get&entity=X&id=Y
//   POST ?action=save   (entity + id? + fields…)
//   POST ?action=delete (entity + id)
// Separate from the PUBLIC submit.php: this endpoint always requires an
// admin session + CSRF on state-changing requests. Never exposes hashes,
// paths, warnings or stack traces.
ini_set('display_errors', '0');
error_reporting(0);
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

function api_out(int $http, array $body): void {
    http_response_code($http);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}
function api_fail(string $msg, int $http = 400): void {
    api_out($http, ['success' => false, 'error' => $msg]);
}

if (empty($_SESSION['admin_auth'])) api_fail('Login required.', 401);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = strtolower(trim((string)($_GET['action'] ?? $_POST['action'] ?? '')));
$entity = strtolower(preg_replace('/[^a-z0-9_]/i', '', (string)($_GET['entity'] ?? $_POST['entity'] ?? '')));

if ($action === 'list' && $method === 'GET') {
    if (!db_entity_allowed($entity)) api_fail('Unknown entity.');
    $q = strtolower(trim((string)($_GET['q'] ?? '')));
    $rows = db_read($entity);
    if ($q !== '') {
        $rows = array_values(array_filter($rows, function ($r) use ($q) {
            if (!is_array($r)) return false;
            foreach ($r as $v) { if (is_scalar($v) && stripos((string)$v, $q) !== false) return true; }
            return false;
        }));
    }
    api_out(200, ['success' => true, 'entity' => $entity, 'count' => count($rows), 'data' => array_slice(array_values($rows), 0, 200)]);
}

if ($action === 'get' && $method === 'GET') {
    if (!db_entity_allowed($entity)) api_fail('Unknown entity.');
    $id = (string)($_GET['id'] ?? '');
    if ($id === '') api_fail('Missing id.');
    $row = db_find($entity, $id);
    if ($row === null) api_fail('Not found.', 404);
    api_out(200, ['success' => true, 'data' => $row]);
}

if ($action === 'save' && $method === 'POST') {
    csrf_validate();
    if (!db_entity_allowed($entity)) api_fail('Unknown entity.');
    $defs = db_field_defs($entity);
    $input = $_POST;
    if (isset($input['record']) && is_array($input['record'])) $input = $input['record'];
    if (strlen((string)file_get_contents('php://input')) > 262144) api_fail('Request too large.', 413);
    $clean = [];
    foreach ($defs as $f) {
        $name = $f['name'];
        $v = is_scalar($input[$name] ?? null) ? strip_tags(trim((string)$input[$name])) : '';
        if (strlen($v) > 5000) $v = substr($v, 0, 5000);
        $t = $f['type'] ?? 'text';
        if (!empty($f['required']) && $v === '') api_fail(($f['label'] ?? $name) . ' is required.', 422);
        if ($v !== '' && $t === 'email' && !filter_var($v, FILTER_VALIDATE_EMAIL)) api_fail('Invalid email.', 422);
        if ($v !== '' && $t === 'number' && !is_numeric($v)) api_fail(($f['label'] ?? $name) . ' must be a number.', 422);
        if ($v !== '' && $t === 'select' && !empty($f['options']) && !in_array($v, $f['options'], true)) api_fail('Invalid choice.', 422);
        $clean[$name] = $v;
    }
    $id = (string)($_POST['id'] ?? $input['id'] ?? '');
    if ($id !== '') {
        $row = db_update($entity, $id, $clean);
        if ($row === null) api_fail('Not found.', 404);
        api_out(200, ['success' => true, 'data' => $row]);
    }
    $row = db_insert($entity, $clean);
    if ($row === null) api_fail('Storage failed — nothing was saved.', 500);
    api_out(200, ['success' => true, 'data' => $row]);
}

if ($action === 'delete' && $method === 'POST') {
    csrf_validate();
    if (!db_entity_allowed($entity)) api_fail('Unknown entity.');
    $id = (string)($_POST['id'] ?? '');
    if ($id === '') api_fail('Missing id.');
    if (!db_delete($entity, $id)) api_fail('Not found.', 404);
    api_out(200, ['success' => true]);
}

api_fail('Unknown action.');
PHP;
}

// ─── Generated file: admin/server.php ──────────────────────────────────────
function pak_server_php(): string {
    return <<<'PHP'
<?php
// server.php — Backend server control (generated by WebCraft AI).
// Shows which backend is live (Node.js API vs PHP fallback) and lets an
// authenticated admin START / STOP the local Node backend for testing.
// Every action requires an admin session + CSRF token. Start/stop only ever
// touch THIS project's server.js (fixed paths, no user input in commands);
// Stop refuses unless the server was started from here (tracked PID).
ini_set('display_errors', '0');
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

function srv_root(): string { return dirname(__DIR__, 3); }
function srv_port(): int { return 3100; }
function srv_pid_file(): string { return srv_root() . '/storage/node.pid'; }
function srv_log_file(): string { return srv_root() . '/storage/logs/node.log'; }

function srv_exec(string $cmd, array &$out = null): int {
    $out = [];
    if (!function_exists('exec')) return -1;
    $code = 0;
    @exec($cmd . ' 2>&1', $out, $code);
    return $code;
}

function srv_port_open(): bool {
    $h = @fsockopen('127.0.0.1', srv_port(), $no, $str, 2);
    if ($h) { fclose($h); return true; }
    return false;
}

function srv_health(): ?array {
    $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 3, 'ignore_errors' => true]]);
    $body = @file_get_contents('http://127.0.0.1:' . srv_port() . '/node-api/health', false, $ctx);
    if (!is_string($body) || $body === '') return null;
    $j = json_decode($body, true);
    return is_array($j) ? $j : null;
}

function srv_node_bin(): string {
    $out = [];
    if (srv_exec('where node', $out) !== 0) return '';
    foreach ($out as $line) {
        $p = trim((string)$line);
        if ($p !== '' && stripos($p, 'node') !== false && is_file($p)) return $p;
    }
    return '';
}

function srv_wait_port(bool $wantOpen, int $seconds = 12): bool {
    $t0 = time();
    while ((time() - $t0) < $seconds) {
        if (srv_port_open() === $wantOpen) return true;
        usleep(500000);
    }
    return srv_port_open() === $wantOpen;
}

/** Console window marker (cosmetic + manual cleanup aid). Ownership is
 *  proven by the instance token (see below), not by this title. */
function srv_win_title(): string { return 'WebCraftNode'; }

function srv_tasklist(): array {
    $out = [];
    srv_exec('tasklist /V /FO CSV /NH', $out);
    return $out;
}

/** Tracked server record written by the Node process itself on boot
 *  ({pid, token, started_at}). Absent/unparseable = not started from here. */
function srv_recorded(): array {
    $f = srv_pid_file();
    if (!is_file($f)) return [];
    $j = json_decode(@file_get_contents($f), true);
    if (!is_array($j)) return [];
    $pid = (string)($j['pid'] ?? '');
    $token = (string)($j['token'] ?? '');
    if (!preg_match('/^\d+$/', $pid) || !preg_match('/^[a-f0-9]{32}$/', $token)) return [];
    return ['pid' => $pid, 'token' => $token, 'started_at' => (string)($j['started_at'] ?? '')];
}

function srv_recorded_pid(): string {
    $r = srv_recorded();
    return (string)($r['pid'] ?? '');
}

/** Live instance token reported by the running server ('' = unreachable or
 *  a manual run without a token). */
function srv_live_instance(): string {
    $h = srv_health();
    if (!is_array($h)) return '';
    $d = $h['data'] ?? $h;
    $t = is_array($d) ? ($d['instance'] ?? '') : '';
    return is_string($t) ? $t : '';
}

/** True when $pid names a live node.exe process (image check only —
 *  ownership additionally requires the instance-token match). */
function srv_pid_is_node(string $pid): bool {
    if (!preg_match('/^\d+$/', $pid)) return false;
    $out = [];
    srv_exec('tasklist /FI "PID eq ' . $pid . '" /FO CSV /NH', $out);
    foreach ($out as $line) {
        $cols = str_getcsv((string)$line);
        if (count($cols) < 2) continue;
        if (strcasecmp(trim($cols[0]), 'node.exe') !== 0) continue;
        if (trim((string)$cols[1]) === $pid) return true;
    }
    return false;
}

function srv_start(): array {
    if (srv_port_open()) return [true, 'Node backend is already running on port ' . srv_port() . '.'];
    $node = srv_node_bin();
    if ($node === '') {
        return [false, 'Node.js was not found in PATH. Start it manually: open a terminal in the project folder and run: node server/server.js'];
    }
    $root = srv_root();
    $logDir = dirname(srv_log_file());
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    // Per-start instance token: the server writes {pid, token} to the pid
    // file on boot and reports the token on /health, so Stop can prove the
    // live process is the one started here (no window-title scanning).
    // A tiny launcher .bat avoids cmd's nested-quote mangling (node lives
    // under "C:\Program Files\..."). popen returns immediately (no flash).
    $token = bin2hex(random_bytes(16));
    $bat = $logDir . DIRECTORY_SEPARATOR . 'wc-node-start.bat';
    $batBody = "@echo off\r\n"
        . 'cd /d "' . $root . '"' . "\r\n"
        . 'set WB_INSTANCE_TOKEN=' . $token . "\r\n"
        . 'set WB_PID_FILE=' . srv_pid_file() . "\r\n"
        . '"' . $node . '" server' . DIRECTORY_SEPARATOR . 'server.js'
        . ' >> "' . srv_log_file() . '" 2>&1' . "\r\n";
    if (@file_put_contents($bat, $batBody, LOCK_EX) === false) {
        return [false, 'Cannot write the server launcher. Start it manually: node server/server.js'];
    }
    @unlink(srv_pid_file()); // stale record must never validate a new boot
    @pclose(@popen('cd /d "' . $root . '" && start "' . srv_win_title() . '" /MIN "' . $bat . '"', 'r'));
    $t0 = time();
    $live = '';
    while ((time() - $t0) < 20) {
        if (srv_port_open()) {
            $live = srv_live_instance();
            if ($live !== '' && hash_equals($token, $live)) break;
        }
        usleep(500000);
        $live = '';
    }
    if ($live === '' || !hash_equals($token, $live)) {
        if (srv_port_open()) {
            return [false, 'Port ' . srv_port() . ' is busy but the live server is not ours (no token match). Stop it in its own terminal, then Start again.'];
        }
        $tail = srv_log_tail(5);
        return [false, 'Node did not come up on port ' . srv_port() . ' within 20s.' . ($tail !== '' ? ' Log: ' . $tail : ' Check that port 3100 is free.')];
    }
    $rec = srv_recorded();
    $pid = (string)($rec['pid'] ?? '');
    return [true, 'Node backend started (PID ' . ($pid !== '' ? $pid : 'unknown') . ') on port ' . srv_port() . '.'];
}

function srv_stop(): array {
    $rec = srv_recorded();
    $pid = (string)($rec['pid'] ?? '');
    $token = (string)($rec['token'] ?? '');
    if ($pid === '' || $token === '') {
        return [false, 'No server was started from here (no tracked instance). Refusing to kill unknown processes — stop it in its own terminal (Ctrl+C) or Task Manager.'];
    }
    // The live server must report OUR token — otherwise the port belongs to
    // a manual run (or a stale record) and must not be touched.
    $live = srv_live_instance();
    if ($live === '' || !hash_equals($token, $live)) {
        if (!srv_port_open()) @unlink(srv_pid_file());
        return [false, 'Tracked instance is no longer live (token mismatch) — record cleared. Stop any manual server in its own terminal.'];
    }
    if (!srv_pid_is_node($pid)) {
        return [false, 'Tracked PID ' . $pid . ' is not a live node.exe — refusing. Kill it in Task Manager if needed.'];
    }
    srv_exec('taskkill /F /PID ' . $pid);
    if (!srv_wait_port(false)) {
        return [false, 'PID ' . $pid . ' did not stop. Kill it in Task Manager.'];
    }
    @unlink(srv_pid_file());
    return [true, 'Node backend stopped (PID ' . $pid . '). The site now runs on the PHP fallback.'];
}

function srv_log_tail(int $n = 30): string {
    $f = srv_log_file();
    if (!is_file($f)) return '';
    $lines = @file($f, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines)) return '';
    return implode("\n", array_slice($lines, max(0, count($lines) - $n)));
}

$flash = '';
$flashErr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $action = (string)($_POST['op'] ?? '');
    if ($action === 'start') {
        [$ok, $msg] = srv_start();
    } elseif ($action === 'stop') {
        [$ok, $msg] = srv_stop();
    } else {
        $ok = false;
        $msg = 'Unknown operation.';
    }
    if ($ok) { $flash = $msg; }
    else { $flashErr = $msg; }
}

$portOpen = srv_port_open();
$health = $portOpen ? srv_health() : null;
$rec = srv_recorded();
$pid = (string)($rec['pid'] ?? '');
$owned = ($pid !== '' && $portOpen && hash_equals((string)($rec['token'] ?? ''), srv_live_instance()));

header_html('server', 'Backend Server');
?>
<div class="top-actions">
  <div>
    <h1 class="page-title">🖥️ Backend Server</h1>
    <p style="color:#64748b;font-size:.88rem;margin-top:.3rem">Node.js API + automatic PHP fallback — toggle to test both paths.</p>
  </div>
</div>

<?php if ($flash): ?><div class="flash">✅ <?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="flash flash-err">⚠️ <?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

<div class="grid2">
  <div class="card">
    <h2 style="font-size:1.1rem;font-weight:800;color:#fff;margin-bottom:1rem">Live status</h2>
    <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem">
      <span style="width:14px;height:14px;border-radius:50%;background:<?= $portOpen ? '#10b981' : '#f59e0b' ?>;box-shadow:0 0 12px <?= $portOpen ? '#10b981' : '#f59e0b' ?>"></span>
      <strong style="font-size:1.05rem;color:#fff"><?= $portOpen ? 'Node.js API — ONLINE' : 'Node.js API — OFFLINE' ?></strong>
    </div>
    <div style="font-size:.86rem;color:#94a3b8;line-height:1.7">
      <div>Storefront backend right now: <strong style="color:#fff"><?= $portOpen ? 'Node.js (live)' : 'PHP fallback (automatic)' ?></strong></div>
      <div>Health endpoint: <code style="color:#a5b4fc">GET /node-api/health</code> — <?= $health !== null ? htmlspecialchars(json_encode($health)) : 'unreachable' ?></div>
      <div>Tracked instance: <?= $pid !== '' ? 'PID ' . htmlspecialchars($pid) . ($owned ? ' (ours — Stop works)' : ' (stale or foreign — Stop refused)') : '— (not started from here)' ?></div>
    </div>
    <div style="display:flex;gap:.6rem;margin-top:1.25rem;flex-wrap:wrap">
      <form method="POST" style="display:inline">
        <?= csrf_field() ?>
        <input type="hidden" name="op" value="start">
        <button class="btn btn-primary" type="submit">▶ Start Node server</button>
      </form>
      <form method="POST" style="display:inline" onsubmit="return confirm('Stop the Node backend? The site keeps working on the PHP fallback.');">
        <?= csrf_field() ?>
        <input type="hidden" name="op" value="stop">
        <button class="btn btn-danger" type="submit">⏹ Stop Node server</button>
      </form>
    </div>
    <p style="font-size:.78rem;color:#64748b;margin-top:1rem;line-height:1.6">
      Start launches <code>node server/server.js</code> hidden (output → <code>storage/logs/node.log</code>).
      Stop only kills the PID started from here — anything else must be stopped in its own terminal.
      Manual alternative: run <code>node server/server.js</code> in the project folder (Ctrl+C stops it).
    </p>
  </div>
  <div class="card">
    <h2 style="font-size:1.1rem;font-weight:800;color:#fff;margin-bottom:1rem">📜 Server log (tail)</h2>
    <?php $tail = srv_log_tail(30); ?>
    <?php if ($tail === ''): ?>
      <p style="color:#64748b;font-size:.85rem">No log output yet. Start the server, then reload.</p>
    <?php else: ?>
      <pre style="background:#0b0f17;border:1px solid #1e293b;border-radius:10px;padding:.9rem;font-size:.72rem;color:#a5b4fc;overflow:auto;max-height:380px;white-space:pre-wrap;"><?= htmlspecialchars($tail) ?></pre>
    <?php endif; ?>
  </div>
</div>

<?php footer_html(); ?>
PHP;
}

// ─── Generated file: admin/install.php ─────────────────────────────────────
function pak_install_php(array $order, array $entities): string {
    $needsCreds = !empty($order['admin_username']);
    $seed = json_encode(array_map(fn($e) => $e['id'], $entities));
    $code = <<<'PHP'
<?php
// install.php — Safe one-time installer (generated by WebCraft AI).
// 1. Refuses repeat installation (.installed present).
// 2. Validates admin/manifest.json.
// 3. Uses the publishing-time bcrypt hash — NEVER a default password.
// 4. Creates missing data files / dirs / site.json (preserves existing).
// 5. Writes .installed ONLY after everything succeeded.
ini_set('display_errors', '0');
header('Content-Type: text/html; charset=UTF-8');

$ADMIN = __DIR__;
if (is_file($ADMIN . '/.installed')) {
    http_response_code(403);
    exit('<h1>Already installed</h1><p>This admin panel is already set up. '
        . 'For security, the installer cannot run twice. Delete .installed only if you know what you are doing.</p>');
}

$errors = [];
// 1. Manifest validation
$mf = $ADMIN . '/manifest.json';
$manifest = is_file($mf) ? json_decode(@file_get_contents($mf), true) : null;
if (!is_array($manifest) || empty($manifest['entities'])) {
    $errors[] = 'admin/manifest.json is missing or invalid — republish the site to regenerate it.';
}
// 2. Credentials: existing .auth.json wins when it carries a valid hash.
$authFile = $ADMIN . '/.auth.json';
$auth = is_file($authFile) ? (json_decode(@file_get_contents($authFile), true) ?: []) : [];
$hash = (string)($auth['hash'] ?? '');
$hashOk = ($hash !== '' && (str_starts_with($hash, '$2y$') || str_starts_with($hash, '$argon2')));
if (!$hashOk && __NEEDS_CREDS__) {
    $errors[] = 'No administrator credentials are configured. Publish the site with an admin username + password (min 8 chars) first.';
}
if (!empty($errors)) {
    http_response_code(500);
    echo '<h1>Installation incomplete</h1><ul>';
    foreach ($errors as $e) echo '<li>' . htmlspecialchars($e) . '</li>';
    echo '</ul><p>Nothing was marked installed. Fix the issue and reload this page.</p>';
    exit;
}
// 3. Data files (create only when missing — existing records are preserved)
foreach (__SEED__ as $eid) {
    $eid = preg_replace('/[^a-z0-9_]/i', '', (string)$eid);
    if ($eid === '') continue;
    $f = $ADMIN . '/data_' . $eid . '.json';
    if (!is_file($f)) {
        if (@file_put_contents($f, '[]', LOCK_EX) === false) $errors[] = 'Cannot write ' . $eid . ' data file (check directory permissions).';
    }
}
foreach ([$ADMIN . '/includes'] as $d) {
    if (!is_dir($d) && !@mkdir($d, 0755, true)) $errors[] = 'Cannot create ' . basename($d) . ' directory.';
}
// 4. site.json defaults (never overwrite existing settings)
$siteFile = $ADMIN . '/site.json';
if (!is_file($siteFile)) {
    @file_put_contents($siteFile, json_encode([
        'site_name' => $auth['site_name'] ?? 'Website',
        'contact_email' => $auth['email'] ?? '',
        'logo' => '', 'phone' => '', 'address' => '',
        'updated_at' => date('c'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}
if (!empty($errors)) {
    http_response_code(500);
    echo '<h1>Installation incomplete</h1><ul>';
    foreach ($errors as $e) echo '<li>' . htmlspecialchars($e) . '</li>';
    echo '</ul><p>.installed was NOT created. Fix the issue and reload.</p>';
    exit;
}
// 5. Success marker — written LAST.
@file_put_contents($ADMIN . '/.installed', json_encode(['installed_at' => date('c')]), LOCK_EX);
header('Location: login.php');
exit;
PHP;
    return str_replace(['__NEEDS_CREDS__', '__SEED__'],
        [$needsCreds ? 'true' : 'false', $seed], $code);
}

// ─── Generated file: published/<slug>/submit.php (PUBLIC handler) ──────────
function pak_submit_php(array $entities, array $formMap): string {
    $entsJson = json_encode($entities, JSON_UNESCAPED_UNICODE);
    $mapJson = json_encode($formMap, JSON_UNESCAPED_UNICODE);
    // Escape for single-quoted PHP embedding (keep JSON double quotes intact).
    $esc = fn($j) => str_replace(['\\', "'"], ['\\\\', "\\'"], $j);
    $code = <<<'PHP'
<?php
// submit.php — Universal PUBLIC form submission handler (WebCraft AI).
// No admin login required. Every submission is server-validated, guarded
// (honeypot + rate limit + size cap + duplicate protection), saved with
// flock to admin/data_<entity>.json, and appears in the matching admin
// page on next load. False success is never returned: storage failure
// yields HTTP 500 + success:false.
ini_set('display_errors', '0');
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$ENTITIES = json_decode('__ENTS__', true) ?: [];
$FORM_MAP = json_decode('__MAP__', true) ?: [];
$PROTECTED = ['id'=>1,'created_at'=>1,'updated_at'=>1,'role'=>1,'is_admin'=>1,'admin'=>1,
    'paid'=>1,'approved'=>1,'permissions'=>1,'password'=>1,'password_hash'=>1,'hash'=>1,
    'secret'=>1,'status'=>0]; // status stripped below for public writes

function submit_out(int $http, array $body): void {
    http_response_code($http);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}
function submit_entity_defs(string $id, array $entities): ?array {
    foreach ($entities as $e) {
        if (($e['id'] ?? '') === $id) return $e;
    }
    return null;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    submit_out(405, ['success' => false, 'error' => 'Method not allowed.']);
}
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 262144) {
    submit_out(413, ['success' => false, 'error' => 'Submission too large.']);
}
// JSON or classic form posts both accepted.
$input = $_POST;
$raw = file_get_contents('php://input');
if (empty($input) && $raw !== false && $raw !== '') {
    $j = json_decode($raw, true);
    if (is_array($j)) $input = $j;
}
// Honeypot: bots fill it, humans don't. Quietly "succeed" to deter retries.
if (!empty($input['company'])) { submit_out(200, ['success' => true]); }
// Resolve entity: explicit `entity` wins; else `form` purpose maps via manifest.
$entity = strtolower(preg_replace('/[^a-z0-9_]/i', '', (string)($input['entity'] ?? '')));
if ($entity === '') {
    $purpose = strtolower(trim((string)($input['form'] ?? 'contact')));
    $entity = (string)($FORM_MAP[$purpose] ?? $FORM_MAP['contact'] ?? 'inquiries');
}
$defs = submit_entity_defs($entity, $ENTITIES);
if ($defs === null) submit_out(400, ['success' => false, 'error' => 'Unknown form destination.']);
// Bus bookings carry a lifecycle (hold → confirm → approve → pay). Direct
// public writes must still name a real trip and seats (canonical or legacy
// aliases); empty ghost rows are rejected instead of stored with "—" cells.
if ($entity === 'bookings') {
    $aliasTrip = '';
    foreach (['trip_id','tripId','tripID','trip','booking_trip','trip_ref'] as $ak) {
        if (isset($input[$ak]) && trim((string)$input[$ak]) !== '') { $aliasTrip = trim((string)$input[$ak]); break; }
    }
    $aliasSeats = '';
    foreach (['seats','seat_numbers','seatNumbers','seat','seats_string','selected_seats'] as $ak) {
        if (isset($input[$ak]) && trim((string)(is_array($input[$ak]) ? implode(',', $input[$ak]) : $input[$ak])) !== '') { $aliasSeats = 'x'; break; }
    }
    if ($aliasTrip === '' || $aliasSeats === '') {
        submit_out(422, ['success' => false, 'error' => 'Bookings need a trip and seats. Search live trips, hold seats, then submit passenger details.']);
    }
}
// Rate limit: 20 submissions/hour per IP+entity.
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$hitsFile = __DIR__ . '/submit_hits.json';
$hits = is_file($hitsFile) ? (json_decode(@file_get_contents($hitsFile), true) ?: []) : [];
if (!is_array($hits)) $hits = [];
$now = time();
$hits = array_values(array_filter($hits, fn($h) => is_array($h) && ($now - ($h['t'] ?? 0)) < 3600 && ($h['ip'] ?? '') === $ip && ($h['e'] ?? '') === $entity));
if (count($hits) >= 20) submit_out(429, ['success' => false, 'error' => 'Too many submissions. Please try again later.']);
// Duplicate protection: identical email+message within 60s is deduped.
$clean = [];
foreach (($defs['fields'] ?? []) as $f) {
    $name = (string)($f['name'] ?? '');
    if ($name === '' || $name === 'id') continue;
    $v = is_scalar($input[$name] ?? null) ? strip_tags(trim((string)$input[$name])) : '';
    if (strlen($v) > 5000) $v = substr($v, 0, 5000);
    $t = strtolower((string)($f['type'] ?? 'text'));
    if (!empty($f['required']) && $v === '') submit_out(422, ['success' => false, 'error' => ($f['label'] ?? $name) . ' is required.']);
    if ($v !== '' && $t === 'email' && !filter_var($v, FILTER_VALIDATE_EMAIL)) submit_out(422, ['success' => false, 'error' => 'Enter a valid email.']);
    if ($v !== '' && $t === 'url' && !filter_var($v, FILTER_VALIDATE_URL)) submit_out(422, ['success' => false, 'error' => 'Enter a valid URL.']);
    if ($v !== '' && $t === 'number' && !is_numeric($v)) submit_out(422, ['success' => false, 'error' => ($f['label'] ?? $name) . ' must be a number.']);
    if ($v !== '' && $t === 'select' && !empty($f['options']) && !in_array($v, array_map('strval', $f['options']), true)) submit_out(422, ['success' => false, 'error' => 'Invalid choice.']);
    // Preserve extra submitted business fields (bounded): map unknown keys
    // under 40 safe chars so frontend additions are never silently dropped.
    $clean[$name] = $v;
}
foreach ($input as $k => $v) {
    if (!is_string($k) || array_key_exists($k, $clean)) continue;
    if (in_array($k, ['entity','form','csrf','action','id','company','idempotency_key'], true)) continue;
    if (isset($PROTECTED[$k]) && $k !== 'status') continue;
    if (!preg_match('/^[a-z][a-z0-9_]{0,39}$/i', $k)) continue;
    if (!is_scalar($v)) continue;
    $s = substr(strip_tags(trim((string)$v)), 0, 2000);
    if ($s !== '') $clean[$k] = $s;
    if (count($clean) >= 30) break;
}
// Client-submitted privilege/status fields are NEVER authoritative.
unset($clean['status'], $clean['payment_status'], $clean['role'], $clean['paid']);
$hasEmail = false;
foreach ($clean as $v) { if (is_string($v) && strpos($v, '@') !== false) { $hasEmail = true; break; } }
// Default processing status per entity kind.
$statusDefault = in_array($entity, ['bookings','appointments','reservations','orders','viewings','rentals','enrollments','tickets'], true)
    ? 'pending' : 'unread';
$clean['status'] = $statusDefault;
if ($entity === 'inquiries') $clean['read'] = false;
// Locked read-modify-write on the site's own admin data file.
$adminDir = __DIR__ . '/admin';
if (!is_dir($adminDir)) @mkdir($adminDir, 0755, true);
$dataFile = $adminDir . '/data_' . $entity . '.json';
$h = @fopen($dataFile, 'c+');
if (!$h) submit_out(500, ['success' => false, 'error' => 'Storage unavailable. Nothing was saved.']);
$record = null;
$dup = false;
try {
    if (!flock($h, LOCK_EX)) submit_out(500, ['success' => false, 'error' => 'Storage busy. Try again.']);
    $rawRows = stream_get_contents($h);
    $rows = ($rawRows !== false && trim((string)$rawRows) !== '') ? (json_decode($rawRows, true) ?: []) : [];
    if (!is_array($rows)) $rows = [];
    $rows = array_values($rows);
    // 60-second near-duplicate guard (same email + same longest text field).
    $probe = '';
    foreach ($clean as $v) { if (is_string($v) && strlen($v) > strlen($probe)) $probe = strtolower($v); }
    foreach ($rows as $r) {
        if (!is_array($r)) continue;
        $ca = strtotime($r['created_at'] ?? '');
        if (!$ca || ($now - $ca) > 60) continue;
        $hay = strtolower(json_encode($r));
        $em = '';
        foreach (['email'] as $ek) { if (!empty($clean[$ek])) $em = strtolower((string)$clean[$ek]); }
        if ($em !== '' && strpos($hay, $em) !== false && $probe !== '' && strpos($hay, substr($probe, 0, 40)) !== false) { $dup = true; break; }
    }
    if (!$dup) {
        $ts = date('c');
        $record = array_merge($clean, ['id' => 'r_' . bin2hex(random_bytes(8)),
            'created_at' => $ts, 'updated_at' => $ts]);
        array_unshift($rows, $record);
        if (count($rows) > 2000) $rows = array_slice($rows, 0, 2000);
        ftruncate($h, 0);
        rewind($h);
        if (fwrite($h, json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
            submit_out(500, ['success' => false, 'error' => 'Storage failed. Nothing was saved.']);
        }
        fflush($h);
    }
} finally {
    flock($h, LOCK_UN);
    fclose($h);
}
if ($dup) submit_out(200, ['success' => true, 'deduped' => true]);
// Record rate-limit hit only on accepted writes.
$hits[] = ['ip' => $ip, 'e' => $entity, 't' => $now];
@file_put_contents($hitsFile, json_encode(array_slice($hits, -500), JSON_UNESCAPED_UNICODE), LOCK_EX);
submit_out(200, ['success' => true, 'entity' => $entity, 'id' => (string)($record['id'] ?? '')]);
PHP;
    return str_replace(['__ENTS__', '__MAP__'], [$esc($entsJson), $esc($mapJson)], $code);
}

// ─── Real-data dashboard (used only when no business dashboard exists) ────
function pak_dashboard_php(): string {
    return <<<'PHP'
<?php
// index.php — Real admin dashboard (generated by WebCraft AI).
// Every number is computed from persisted data_*.json at request time.
// No fake records, no hardcoded statistics.
ini_set('display_errors', '0');
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/layout.php';

$entities = [];
$mf = __DIR__ . '/manifest.json';
if (is_file($mf)) {
    $m = json_decode(@file_get_contents($mf), true);
    foreach (array_slice((array)($m['entities'] ?? []), 0, 8) as $e) {
        if (!is_array($e) || empty($e['id'])) continue;
        $id = strtolower(preg_replace('/[^a-z0-9]+/i', '_', (string)$e['id']));
        if ($id === '') continue;
        $entities[] = ['id' => $id, 'name' => (string)($e['name'] ?? $id),
            'icon' => (string)($e['icon'] ?? '📁')];
    }
}
if (empty($entities)) {
    foreach (glob(__DIR__ . '/data_*.json') ?: [] as $df) {
        $did = preg_replace('/^data_|\.json$/', '', basename($df));
        if ($did === '' || !preg_match('/^[a-z0-9_]{1,40}$/', $did)) continue;
        $entities[] = ['id' => $did, 'name' => ucwords(str_replace('_', ' ', $did)), 'icon' => '📁'];
    }
}
$counts = [];
$total = 0;
foreach ($entities as $e) {
    $n = count(db_read($e['id']));
    $counts[$e['id']] = $n;
    $total += $n;
}
// Inbox counters.
$inbox = db_read('inquiries');
$unread = 0;
foreach ($inbox as $r) { if (is_array($r) && empty($r['read'])) $unread++; }
// Pending/status counters for transactional entities.
$pending = 0;
foreach (['bookings', 'appointments', 'orders', 'reservations'] as $pe) {
    foreach (db_read($pe) as $r) {
        if (!is_array($r)) continue;
        $st = strtolower((string)($r['status'] ?? ''));
        if (in_array($st, ['pending', 'unread', 'held', 'awaiting_payment'], true)) $pending++;
    }
}
// Recent activity: newest 5 across entities by created_at/updated_at.
$recent = [];
foreach ($entities as $e) {
    foreach (db_read($e['id']) as $r) {
        if (!is_array($r)) continue;
        $ts = (string)($r['updated_at'] ?? $r['created_at'] ?? $r['date'] ?? '');
        $recent[] = ['entity' => $e['id'], 'label' => $e['name'], 'ts' => $ts, 'row' => $r];
    }
}
usort($recent, fn($a, $b) => strcmp((string)$b['ts'], (string)$a['ts']));
$recent = array_slice($recent, 0, 5);
$siteLive = is_file(dirname(__DIR__) . '/index.html');

header_html('dashboard', 'Dashboard');
?>
<div class="top-actions">
  <div>
    <h1 class="page-title">Welcome, <?= htmlspecialchars(admin_current_user()) ?> 👋</h1>
    <p style="color:#64748b;font-size:.88rem;margin-top:.3rem">Live overview — every count below is computed from your stored records.</p>
  </div>
  <a href="../index.html" target="_blank" class="btn btn-primary">🌐 View Live Website →</a>
</div>

<div class="grid4">
  <div class="stat-box"><div class="stat-lbl">Total records</div><div class="stat-val"><?= (int)$total ?></div>
    <div style="font-size:.75rem;color:#64748b">Across <?= (int)count($entities) ?> section(s)</div></div>
  <div class="stat-box"><div class="stat-lbl">Unread inquiries</div>
    <div class="stat-val" style="color:<?= $unread > 0 ? '#f59e0b' : '#10b981' ?>"><?= (int)$unread ?></div>
    <div style="font-size:.75rem;color:#64748b"><a href="inbox.php" style="color:#818cf8;text-decoration:none">Open Inbox →</a></div></div>
  <div class="stat-box"><div class="stat-lbl">Pending / awaiting</div>
    <div class="stat-val" style="color:<?= $pending > 0 ? '#f59e0b' : '#10b981' ?>"><?= (int)$pending ?></div>
    <div style="font-size:.75rem;color:#64748b">Bookings · orders · appointments</div></div>
  <div class="stat-box"><div class="stat-lbl">Publishing status</div>
    <div class="stat-val" style="color:<?= $siteLive ? '#10b981' : '#f59e0b' ?>;font-size:1.3rem"><?= $siteLive ? 'Published 🟢' : 'No site file ⚠️' ?></div>
    <div style="font-size:.75rem;color:#64748b">Published directory</div></div>
</div>

<div class="grid4">
  <?php foreach ($entities as $e): ?>
  <div class="stat-box"><div class="stat-lbl"><?= htmlspecialchars($e['icon'] . ' ' . $e['name']) ?></div>
    <div class="stat-val"><?= (int)($counts[$e['id']] ?? 0) ?></div>
    <div style="font-size:.75rem;color:#64748b"><a href="<?= htmlspecialchars(db_entity_href($e['id'])) ?>" style="color:#818cf8;text-decoration:none">Manage →</a></div></div>
  <?php endforeach; ?>
</div>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem">
    <h3 style="font-size:1.05rem;font-weight:800;color:#fff">🕘 Recent activity (latest 5)</h3>
  </div>
  <?php if (empty($recent)): ?>
    <div style="text-align:center;padding:2rem 1rem;color:#64748b;font-size:.86rem">No submissions yet — customer activity will appear here the moment it arrives.</div>
  <?php else: ?>
  <div style="overflow-x:auto"><table>
    <thead><tr><th>Section</th><th>Detail</th><th>Status</th><th>Date</th></tr></thead>
    <tbody>
    <?php foreach ($recent as $item):
      $r = $item['row'];
      $detail = (string)($r['name'] ?? $r['passenger'] ?? $r['customer'] ?? $r['guest'] ?? $r['holder'] ?? $r['title'] ?? $r['email'] ?? $r['id'] ?? '');
      if (strlen($detail) > 60) $detail = substr($detail, 0, 60) . '…';
    ?>
      <tr>
        <td><a href="<?= htmlspecialchars(db_entity_href($item['entity'])) ?>" style="color:#a5b4fc;text-decoration:none"><?= htmlspecialchars($item['label']) ?></a></td>
        <td><?= htmlspecialchars($detail === '' ? '—' : $detail) ?></td>
        <td><span class="badge badge-blue"><?= htmlspecialchars((string)($r['status'] ?? ($item['entity'] === 'inquiries' ? (empty($r['read']) ? 'unread' : 'read') : 'stored'))) ?></span></td>
        <td style="color:#64748b;font-size:.78rem"><?= htmlspecialchars(substr((string)$item['ts'], 0, 16)) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="font-size:1.05rem;font-weight:800;color:#fff;margin-bottom:1rem">⚡ Quick links</h3>
  <div style="display:flex;gap:.6rem;flex-wrap:wrap">
    <a class="btn btn-ghost" href="inbox.php">✉️ Inbox<?= $unread > 0 ? ' (' . $unread . ')' : '' ?></a>
    <?php foreach ($entities as $e): ?>
      <a class="btn btn-ghost" href="<?= htmlspecialchars(db_entity_href($e['id'])) ?>"><?= htmlspecialchars($e['icon'] . ' ' . $e['name']) ?></a>
    <?php endforeach; ?>
    <a class="btn btn-ghost" href="settings.php">⚙️ Settings</a>
    <a class="btn btn-ghost" href="server.php">🖥️ Server</a>
  </div>
</div>

<?php footer_html(); ?>
PHP;
}

// ─── Full install into a published admin dir (additive, data-preserving) ───
function pak_install(string $adminDir, array $order): array {
    $written = [];
    $errors = [];
    if (!is_dir($adminDir) && !@mkdir($adminDir, 0755, true)) {
        return ['written' => [], 'errors' => ['Cannot create admin directory.'], 'entities' => []];
    }
    $entities = pak_entities_for_order($order);
    $formMap = pak_form_entity_map($entities);

    // 1. Shared helpers (always safe to refresh — no user data inside).
    $helpers = [
        'includes/auth.php' => pak_auth_php(),
        'includes/db.php' => pak_db_php(),
        'includes/layout.php' => pak_layout_php(),
    ];
    foreach ($helpers as $rel => $code) {
        if (pak_write($adminDir . '/' . $rel, $code)) $written[] = $rel;
        else $errors[] = 'Cannot write ' . $rel;
    }

    // 2. manifest.json — refresh entity/module contract, preserve nothing else.
    $mf = $adminDir . '/manifest.json';
    $manifest = is_file($mf) ? json_decode(@file_get_contents($mf), true) : null;
    if (!is_array($manifest)) {
        $manifest = [
            'schema' => 'business-manifest/1',
            'slug' => pak_slug((string)($order['slug'] ?? 'site')),
            'order_id' => (string)($order['order_id'] ?? ''),
            'business' => ['name' => (string)($order['site_name'] ?? 'Website'),
                'category' => 'general', 'label' => 'General business'],
            'entities' => array_map(function ($e) {
                return ['id' => $e['id'], 'name' => $e['name'],
                    'singular' => $e['singular'], 'icon' => $e['icon'], 'fields' => $e['fields']];
            }, $entities),
            'admin_modules' => array_map(function ($e) {
                return ['id' => $e['id'], 'label' => $e['name'], 'icon' => $e['icon'], 'entity' => $e['id']];
            }, $entities),
            'frontend_api_map' => [],
            'updated_at' => date('c'),
        ];
        if (pak_write($mf, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) $written[] = 'manifest.json';
    }
    // Customer management module: the sidebar builds a "Customers" entry from
    // the on-disk data_customers.json, so the page itself must always exist
    // (otherwise the entry 404s) and the entity must be in the manifest.
    $custEntity = pak_customers_entity();
    $hasCust = false;
    foreach ((array)($manifest['entities'] ?? []) as $e) {
        if (is_array($e) && strtolower(trim((string)($e['id'] ?? ''))) === 'customers') { $hasCust = true; break; }
    }
    if (!$hasCust) {
        $manifest['entities'][] = ['id' => 'customers', 'name' => 'Customers',
            'singular' => 'Customer', 'icon' => $custEntity['icon'], 'fields' => $custEntity['fields']];
        $manifest['admin_modules'][] = ['id' => 'customers', 'label' => 'Customers',
            'icon' => $custEntity['icon'], 'entity' => 'customers'];
        $manifest['updated_at'] = date('c');
        if (pak_write($mf, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) $written[] = 'manifest.json';
    }

    // 3. form_map.json — explicit validated frontend→entity mapping.
    if (pak_write($adminDir . '/form_map.json', json_encode($formMap, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        if (!in_array('form_map.json', $written, true)) $written[] = 'form_map.json';
    }

    // 4. Per-entity CRUD pages.
    foreach ($entities as $e) {
        if (pak_write($adminDir . '/' . $e['id'] . '.php', pak_entity_php($e))) $written[] = $e['id'] . '.php';
        else $errors[] = 'Cannot write ' . $e['id'] . '.php';
    }

    // 4b. Customers page (admin customer management) — always installed so
    // the sidebar's Customers entry never leads to a 404.
    if (pak_write($adminDir . '/customers.php', pak_entity_php(pak_customers_entity()))) $written[] = 'customers.php';
    else $errors[] = 'Cannot write customers.php';
    if (!is_file($adminDir . '/data_customers.json')) {
        if (pak_write($adminDir . '/data_customers.json', '[]')) $written[] = 'data_customers.json';
        else $errors[] = 'Cannot init data_customers.json';
    }

    // 5. Inbox / settings / API / installer / server control.
    foreach (['inbox.php' => pak_inbox_php(), 'settings.php' => pak_settings_php(),
              'api.php' => pak_api_php(), 'server.php' => pak_server_php(),
              'install.php' => pak_install_php($order, $entities)] as $rel => $code) {
        if ($rel === 'settings.php' && is_file($adminDir . '/' . $rel)) {
            // Refresh settings to the hash-only build (old builds stored
            // plaintext passwords) — no user data lives in the file itself.
        }
        if (pak_write($adminDir . '/' . $rel, $code)) $written[] = $rel;
        else $errors[] = 'Cannot write ' . $rel;
    }

    // 6. Data files — create ONLY when missing (existing records preserved).
    foreach ($entities as $e) {
        $f = $adminDir . '/data_' . $e['id'] . '.json';
        if (!is_file($f)) {
            if (pak_write($f, '[]')) $written[] = 'data_' . $e['id'] . '.json';
            else $errors[] = 'Cannot init data_' . $e['id'] . '.json';
        }
    }
    if (!is_file($adminDir . '/data_inquiries.json')) {
        if (pak_write($adminDir . '/data_inquiries.json', '[]')) $written[] = 'data_inquiries.json';
    }

    // 7. site.json defaults — never overwrite existing configuration.
    if (!is_file($adminDir . '/site.json')) {
        pak_write($adminDir . '/site.json', json_encode([
            'site_name' => (string)($order['site_name'] ?? 'Website'),
            'contact_email' => (string)($order['contact_email'] ?? $order['admin_email'] ?? ''),
            'logo' => '', 'phone' => '', 'address' => '',
            'updated_at' => date('c'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    // 8. Dashboard: keep the business-specific one when it is already real;
    // otherwise install the real-data dashboard.
    $idx = $adminDir . '/index.php';
    $cur = is_file($idx) ? @file_get_contents($idx) : '';
    $isReal = is_string($cur) && (strpos($cur, 'computed from your actual records') !== false
        || strpos($cur, 'computed from persisted') !== false);
    if (!$isReal) {
        if (pak_write($idx, pak_dashboard_php())) $written[] = 'index.php';
        else $errors[] = 'Cannot write index.php';
    }

    return ['written' => $written, 'errors' => $errors, 'entities' => $entities, 'form_map' => $formMap];
}

// ─── Public handlers in the published site root ────────────────────────────
function pak_public_handlers(string $targetDir, array $order, array $entities): array {
    $written = [];
    $errors = [];
    if (empty($entities)) $entities = pak_entities_for_order($order);
    $formMap = pak_form_entity_map($entities);
    if (pak_write($targetDir . '/submit.php', pak_submit_php($entities, $formMap))) $written[] = 'submit.php';
    else $errors[] = 'Cannot write submit.php';
    return ['written' => $written, 'errors' => $errors, 'form_map' => $formMap];
}

/**
 * Wire every generated frontend form to the backend:
 * - contact-looking forms → contact.php (existing wiring, upgraded receiver)
 * - business forms (booking/order/…) → submit.php with data-wc-form purpose
 * Idempotent: never injects twice.
 */
function pak_wire_frontend_forms(string $html, array $formMap): string {
    if (stripos($html, 'data-wc-pak-submit') !== false) return $html;
    $js = <<<'WCJS'
<script data-wc-pak-submit>
/* WebCraft universal form wiring — every data form posts to submit.php
   (no admin login needed) and the record appears in the admin panel. */
(function(){
  function note(msg, ok, form){
    var host = (form && form.parentNode) ? form.parentNode : document.body;
    var d = document.createElement('div');
    d.textContent = msg;
    d.style.cssText = 'margin:12px 0;padding:12px 18px;border-radius:10px;font-weight:700;font-size:14px;box-shadow:0 10px 30px rgba(0,0,0,.25);background:' + (ok ? '#059669' : '#dc2626') + ';color:#fff;';
    if (host === document.body) d.style.cssText += 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);z-index:99999;';
    host.appendChild(d);
    setTimeout(function(){ d.remove(); }, 5000);
  }
  function purpose(f){
    var p = (f.getAttribute('data-wc-form') || f.getAttribute('data-form') || '').toLowerCase();
    if (p) return p;
    var html = ((f.id || '') + ' ' + (f.className || '') + ' ' + (f.innerHTML || '')).toLowerCase();
    // Booking markers win over order markers: a bus passenger form talks
    // about "payment" (pay online/counter) but its record is a booking, and
    // "order" also matches "in order to". Shop checkouts carry cart/buy
    // markers that never collide with booking words.
    if (/book|reserv|appoint|seat|trip|schedule|slot/.test(html)) return 'booking';
    if (/checkout|cart|order|payment|buy|add-to-cart/.test(html)) return 'order';
    if (/subscrib|newsletter/.test(html)) return 'newsletter';
    if (/review|rating|testimonial|feedback/.test(html)) return 'review';
    if (/quote|estimate/.test(html)) return 'quote';
    if (/register|signup|sign-up|join|member/.test(html)) return 'registration';
    return '';
  }
  document.addEventListener('submit', function(e){
    var f = e.target;
    if (!f || f.tagName !== 'FORM' || f.dataset.wcPakWired) return;
    // Search/catalog/lookup forms never create records — the storefront
    // runtime (or the page's own JS) owns them. Hijacking them here would
    // break trip search AND file bogus bookings (stopImmediatePropagation
    // runs before their handlers).
    if (f.hasAttribute('data-wc-search') || f.getAttribute('data-remote') === 'off'
        || f.hasAttribute('data-wc-history') || f.hasAttribute('data-wc-ticket-lookup')
        || f.hasAttribute('data-wc-lookup') || /history|lookup|track|my-booking/i.test(f.id || '')) return;
    // Contact forms keep the contact.php+mailer flow; skip them here.
    var hasEmail = f.querySelector('input[type=email]');
    var hasMsg = f.querySelector('textarea');
    if (hasEmail && hasMsg) return;
    var p = purpose(f);
    if (!p) return; // generic/static form — leave native behaviour alone
    // Hold/slot flows (booking/order/appointment) belong to the storefront
    // runtime when present: it attaches held trip+seats, uses idempotency,
    // and tries Node first. Posting them here instead would drop the hold
    // context and fail validation. On runtime-less pages submit.php serves.
    if ((p === 'booking' || p === 'order' || p === 'appointment')
        && window.WCBiz && typeof window.WCBiz.create === 'function') return;
    e.preventDefault();
    e.stopImmediatePropagation();
    f.dataset.wcPakWired = '1';
    var btn = f.querySelector('[type=submit],button');
    var orig = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = 'Sending…'; }
    var fd = new FormData(f);
    fd.append('form', p);
    fetch('submit.php', { method: 'POST', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(j){
        if (j && j.success) {
          f.reset();
          note(j.deduped ? 'Already received — thank you!' : '✓ Thank you! We received it and will be in touch shortly.', true, f);
        } else {
          note((j && j.error) || 'Could not send. Try again.', false, f);
        }
        if (btn) { btn.disabled = false; btn.innerHTML = orig; }
        delete f.dataset.wcPakWired;
      })
      .catch(function(){
        note('Network error. Try again.', false, f);
        if (btn) { btn.disabled = false; btn.innerHTML = orig; }
        delete f.dataset.wcPakWired;
      });
  }, true);
})();
</script>
WCJS;
    // Last real </body> only — a first-match replace can land inside an
    // injected runtime script (its print template contains "</body>"),
    // splitting the script and reviving the page's mock backend.
    return wc_insert_before_body_end($html, $js);
}

} // end function_exists guard
