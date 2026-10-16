<?php
/**
 * includes/AdminPanelGenerator.php — Standalone, Production-Grade CMS Admin Panel Builder
 * 
 * Automatically generates a complete, fully-functional, beautiful PHP CMS Admin Panel
 * for any published customer website. Admin modules are driven by the
 * business spec entities ($order['admin_entities']) — every business gets
 * its own modules, never a fixed one-size-fits-all panel.
 *
 * Features included:
 * - Secure login (bcrypt hash only, rate-limited, generic errors, no defaults)
 * - Modern responsive dark dashboard (Stats, Quick actions, Live site link)
 * - Website Content Editor (live update headings, phone, email, text in index.html)
 * - Dynamic Entity Manager (full CRUD for the business's own entities)
 * - Settings page with secure Password Change (hash only, never plaintext)
 * - Section 18.5 compliance: canShowRequirementBuilder() guard
 */

class AdminPanelGenerator {

    /**
     * Builds and installs the complete admin panel into the target directory
     */
    public static function install(string $adminDir, array $order): bool {
        if (!is_dir($adminDir)) {
            @mkdir($adminDir, 0755, true);
        }

        $siteName   = $order['site_name'] ?? 'My Website';
        $slug       = $order['slug'] ?? 'site';
        $username   = trim((string)($order['admin_username'] ?? ''));
        $email      = trim((string)($order['admin_email'] ?? ''));
        if ($username === '' || !preg_match('/^[a-z][a-z0-9_]{3,19}$/', $username)) {
            throw new InvalidArgumentException('Admin username is required (4-20 chars, start with a letter).');
        }
        // Hash-only auth: the order must carry a bcrypt hash. Plaintext is
        // NEVER written to disk. Legacy orders with only a plaintext password
        // are hashed once here and the plaintext is discarded (not stored).
        $passHash = (string)($order['admin_password_hash'] ?? '');
        if ($passHash === '' || (!str_starts_with($passHash, '$2y$') && !str_starts_with($passHash, '$argon2'))) {
            $legacyPlain = (string)($order['admin_password_plain'] ?? '');
            if (strlen($legacyPlain) < 8) {
                throw new InvalidArgumentException('Admin password hash is required (min 8-char password at setup).');
            }
            $passHash = password_hash($legacyPlain, PASSWORD_DEFAULT);
        }
        $orderId    = $order['order_id'] ?? '';
        // Canonical-spec entities first (per-business modules); any other
        // list is normalized (unique ids, icons, safe fields); generic
        // defaults ONLY when nothing was specified at all.
        $entities   = self::normalizeEntities(!empty($order['admin_entities']) ? $order['admin_entities'] : []);
        if (empty($entities)) $entities = self::getDefaultEntities($siteName);
        // Canonical installs seed EMPTY tables (no fake sample records in a
        // real business admin); template/default installs keep samples.
        $seedEmpty  = !empty($order['admin_seed_empty']);

        // 1. Write .auth.json (owner account, hash only — no plaintext, no demo)
        file_put_contents($adminDir . '/.auth.json', json_encode([
            'username'       => $username,
            'email'          => $email,
            'hash'           => $passHash,
            'order_id'       => $orderId,
            'created'        => date('Y-m-d H:i:s'),
        ], JSON_PRETTY_PRINT));

        // 2. Write auth.php
        file_put_contents($adminDir . '/auth.php', self::getAuthPhpCode());

        // 3. Write login.php (hash-only, rate-limited, generic errors)
        file_put_contents($adminDir . '/login.php', self::getLoginPhpCode($siteName));

        // 4. Write logout.php
        file_put_contents($adminDir . '/logout.php', self::getLogoutPhpCode());

        // 5. Write header & nav helper: layout.php
        file_put_contents($adminDir . '/layout.php', self::getLayoutPhpCode($siteName, $entities));

        // 6. Write index.php (Dashboard)
        file_put_contents($adminDir . '/index.php', self::getDashboardPhpCode($siteName, $entities));

        // 7. Write content.php (Website Content Editor)
        file_put_contents($adminDir . '/content.php', self::getContentEditorPhpCode($siteName));

        // 8. Write manage.php (Entities CRUD Manager)
        file_put_contents($adminDir . '/manage.php', self::getManagePhpCode($siteName, $entities));

        // 9. Write settings.php (with Change Password feature)
        file_put_contents($adminDir . '/settings.php', self::getSettingsPhpCode($siteName));

        // 9b. Write messages.php (Customer Inbox — reads data_inquiries.json)
        file_put_contents($adminDir . '/messages.php', self::getMessagesPhpCode($siteName));

        // 9b. Write features.php (AI Feature & Requirement Builder)
        $nodeApi = defined('NODE_API_URL') ? NODE_API_URL : (getenv('NODE_API_URL') ?: 'http://localhost:3100');
        file_put_contents($adminDir . '/features.php', self::getFeaturesPhpCode($siteName, $orderId, ($order['slug'] ?? 'site'), $nodeApi));

        // 10. Seed initial data files for entities
        foreach ($entities as $ent) {
            $entId = is_array($ent) ? ($ent['id'] ?? 'items') : 'items';
            $dataFile = $adminDir . '/data_' . $entId . '.json';
            if (!file_exists($dataFile)) {
                $sampleData = $seedEmpty ? [] : self::getSampleDataForEntity($ent);
                file_put_contents($dataFile, json_encode($sampleData, JSON_PRETTY_PRINT));
            }
        }

        // 11. Initial content.json for website editor (real owner contacts
        // from the order — never foreign placeholder identities).
        $ownerEmail = trim((string)($order['contact_email'] ?? $email));
        if ($ownerEmail === '' || !filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) $ownerEmail = $email;
        $contentFile = $adminDir . '/content.json';
        if (!file_exists($contentFile)) {
            file_put_contents($contentFile, json_encode([
                'business_name'  => $siteName,
                'tagline'        => 'Quality & Excellence Delivered',
                'phone'          => trim((string)($order['contact_phone'] ?? '')) !== '' ? trim((string)$order['contact_phone']) : '',
                'email'          => $ownerEmail,
                'address'        => '123 Business Avenue, Suite 100',
                'hours'          => 'Mon - Fri: 9:00 AM - 6:00 PM',
                'hero_title'     => 'Welcome to ' . $siteName,
                'hero_subtitle'  => 'We provide exceptional services tailored to your needs. Explore what we offer and connect with us today.',
                'about_title'    => 'About Our Company',
                'about_text'     => $siteName . ' is committed to delivering the highest quality solutions to our clients worldwide.',
            ], JSON_PRETTY_PRINT));
        }

        return true;
    }

    /**
     * Embed JSON inside a single-quoted PHP string in generated code.
     * addslashes() also escapes double quotes, which corrupts JSON
     * (json_decode('{\"a\":1}') fails at runtime → empty nav/tabs).
     * Single-quoted PHP only needs backslash + single-quote escaped.
     */
    private static function jsEmbed(string $json): string {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $json);
    }

    /**
     * Parse one spec field in ANY supported shape:
     *  - "name:type*" string (canonical: "email:email", "price:number*",
     *    "status:select:pending|confirmed" — trailing * = required)
     *  - "name:Label:type*" string (label-preserving: "price:Base Fare:number*")
     *  - ["name"=>..,"label"=>..,"type"=>..,"required"=>..] array
     * Returns null for unusable entries. Never invents field names.
     */
    private static function parseField($f): ?array {
        $known = ['text', 'email', 'tel', 'number', 'date', 'time', 'datetime', 'textarea', 'select', 'url'];
        if (is_string($f)) {
            $t = trim($f);
            if ($t === '') return null;
            // Required star may sit mid-string ("...:select*:opt|opt") since
            // options are appended after it — any star means required.
            $req = str_contains($t, '*');
            $core = str_replace('*', '', $t);
            $segs = explode(':', $core);
            $fn = strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim($segs[0] ?? '')));
            $fn = trim($fn, '_');
            if ($fn === '' || $fn === 'id') return null;
            $rest = array_values(array_filter(array_map('trim', array_slice($segs, 1)), fn($s) => $s !== ''));
            $label = '';
            $tt = 'text';
            $optStr = '';
            if (!empty($rest)) {
                $first = strtolower(trim(explode('|', $rest[0])[0]));
                if ($first === 'phone') $first = 'tel';
                if (in_array($first, $known, true)) {
                    // "name:type[:options]" — classic shape.
                    $tt = $first;
                    $optStr = implode(':', array_slice($rest, 1));
                } elseif (count($rest) >= 2) {
                    // "name:Label:type[:options]" — label-preserving shape.
                    $label = array_shift($rest);
                    $t2 = strtolower(trim(explode('|', $rest[0] ?? 'text')[0]));
                    if ($t2 === 'phone') $t2 = 'tel';
                    $tt = in_array($t2, $known, true) ? $t2 : 'text';
                    $optStr = implode(':', array_slice($rest, 1));
                } else {
                    // Unknown lone type word (json/bool/…) — plain text, auto label.
                    $tt = 'text';
                }
            }
            $opts = [];
            if ($tt === 'select') {
                foreach (explode('|', $optStr) as $o) {
                    $o = trim((string)$o);
                    if ($o !== '' && !in_array($o, $opts, true)) $opts[] = $o;
                    if (count($opts) >= 12) break;
                }
            }
            if ($label === '') $label = ucwords(str_replace('_', ' ', $fn));
            return ['name' => $fn, 'label' => $label, 'type' => $tt, 'required' => $req, 'options' => $opts];
        }
        if (!is_array($f)) return null;
        $fn = strtolower(preg_replace('/[^a-z0-9]+/i', '_', (string)($f['name'] ?? $f['key'] ?? '')));
        $fn = trim($fn, '_');
        if ($fn === '' || $fn === 'id') return null;
        $fl = trim((string)($f['label'] ?? ucwords(str_replace('_', ' ', $fn))));
        $ft = strtolower((string)($f['type'] ?? 'text'));
        $type = in_array($ft, ['text', 'email', 'phone', 'tel', 'number', 'date', 'time', 'datetime', 'textarea', 'select', 'url'], true)
            ? ($ft === 'phone' ? 'tel' : $ft) : 'text';
        $opts = [];
        if ($type === 'select' && !empty($f['options']) && is_array($f['options'])) {
            foreach ($f['options'] as $o) {
                $o = trim((string)(is_array($o) ? ($o['value'] ?? $o['label'] ?? '') : $o));
                if ($o !== '' && !in_array($o, $opts, true)) $opts[] = $o;
                if (count($opts) >= 12) break;
            }
        }
        return ['name' => $fn, 'label' => $fl !== '' ? $fl : ucwords(str_replace('_', ' ', $fn)),
            'type' => $type, 'required' => !empty($f['required']), 'options' => $opts];
    }

    /**
     * Canonical-spec entity map (biz-spec/1) → admin module shape.
     * Every business gets its own modules (ids, labels, icons, fields);
     * generic defaults are used ONLY when the spec carries no entities.
     */
    public static function entitiesFromCanonical(array $spec): array {
        $list = $spec['entities'] ?? [];
        if (!is_array($list) || empty($list)) return [];
        $out = [];
        foreach (array_slice($list, 0, 8) as $e) {
            if (!is_array($e)) continue;
            $key = strtolower(preg_replace('/[^a-z0-9]+/i', '_', (string)($e['key'] ?? $e['name'] ?? '')));
            $key = trim($key, '_');
            if ($key === '') continue;
            $label = trim((string)($e['label'] ?? $e['name'] ?? ucwords(str_replace('_', ' ', $key))));
            if ($label === '') $label = ucwords(str_replace('_', ' ', $key));
            $fields = [];
            foreach (array_slice((array)($e['fields'] ?? []), 0, 15) as $f) {
                $p = self::parseField($f);
                if ($p !== null) $fields[] = $p;
            }
            $singular = $label;
            if (substr($singular, -1) === 's' && strlen($singular) > 3) $singular = substr($singular, 0, -1);
            $out[] = ['id' => $key, 'name' => $label, 'singular' => $singular,
                'icon' => self::iconForEntity($key, $label), 'fields' => $fields];
        }
        // De-dupe by id, keep first.
        $seen = []; $ded = [];
        foreach ($out as $o) { if (!isset($seen[$o['id']])) { $seen[$o['id']] = true; $ded[] = $o; } }
        return $ded;
    }

    private static function iconForEntity(string $key, string $label): string {
        $t = strtolower($key . ' ' . $label);
        $map = [
            '🚌' => ['trip', 'bus', 'route', 'schedule', 'seat'],
            '🎫' => ['book', 'ticket', 'reservation', 'appointment'],
            '🛍️' => ['product', 'shop', 'item', 'catalog', 'inventor', 'stock'],
            '🧾' => ['order', 'sale', 'payment', 'invoice', 'transaction'],
            '✉️' => ['inquir', 'message', 'contact', 'lead', 'subscriber', 'newsletter'],
            '⭐' => ['review', 'rating', 'testimonial', 'feedback'],
            '👥' => ['staff', 'team', 'employee', 'member', 'customer', 'client', 'user'],
            '📅' => ['event', 'slot', 'calendar', 'availab'],
            '🍽️' => ['menu', 'dish', 'food', 'table', 'restaurant'],
            '🏠' => ['room', 'property', 'listing'],
            '🎓' => ['student', 'teacher', 'class', 'course', 'school'],
        ];
        foreach ($map as $icon => $words) {
            foreach ($words as $w) { if (strpos($t, $w) !== false) return $icon; }
        }
        return '📁';
    }

    /**
     * Normalize ANY entity list into install-safe module shape
     * (unique ids, labels, icons, field arrays). Unknown shapes degrade to
     * a Title/Details pair — never to another business's modules.
     */
    public static function normalizeEntities(array $list): array {
        if (!function_exists('business_registry_canonical_entity')) {
            @require_once __DIR__ . '/BusinessRegistry.php';
        }
        $out = [];
        foreach (array_slice($list, 0, 8) as $e) {
            if (is_string($e)) $e = ['name' => $e];
            if (!is_array($e)) continue;
            $id = strtolower(preg_replace('/[^a-z0-9]+/i', '_', (string)($e['id'] ?? $e['key'] ?? $e['name'] ?? '')));
            $id = trim($id, '_');
            if ($id === '') continue;
            // ★ Canonicalize singular AI ids (bus/trip/booking/…) to the
            // registry plural (buses/trips/bookings/…) so admin writes and
            // frontend reads converge on ONE store — never split files.
            if (function_exists('business_registry_canonical_entity')) {
                $id = business_registry_canonical_entity($id);
            }
            $label = trim((string)($e['label'] ?? $e['name'] ?? ucwords(str_replace('_', ' ', $id))));
            $fields = [];
            foreach (array_slice((array)($e['fields'] ?? []), 0, 15) as $f) {
                $p = self::parseField($f);
                if ($p !== null) $fields[] = $p;
            }
            $fieldNames = array_map(static fn($field) => strtolower((string)($field['name'] ?? '')), $fields);
            $isBusBookings = $id === 'bookings'
                && in_array('trip_id', $fieldNames, true)
                && in_array('seats', $fieldNames, true);
            if ($isBusBookings) {
                $busFields = [
                    ['name' => 'passenger', 'label' => 'Passenger', 'type' => 'text', 'required' => true],
                    ['name' => 'phone', 'label' => 'Phone', 'type' => 'tel', 'required' => true],
                    ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                    ['name' => 'status', 'label' => 'Approval status', 'type' => 'select', 'required' => false,
                        'options' => ['pending', 'approved', 'rejected', 'cancelled']],
                    ['name' => 'payment_status', 'label' => 'Payment status', 'type' => 'text', 'required' => false],
                    ['name' => 'amount', 'label' => 'Fare total', 'type' => 'number', 'required' => false],
                ];
                foreach ($busFields as $busField) {
                    $existing = array_search($busField['name'], $fieldNames, true);
                    if ($existing === false) {
                        $fields[] = $busField;
                        $fieldNames[] = $busField['name'];
                    } elseif ($busField['name'] === 'status') {
                        $fields[$existing] = $busField;
                    }
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
                'icon' => (string)($e['icon'] ?? self::iconForEntity($id, $label)), 'fields' => $fields];
        }
        // De-dupe by id, keep first — merging fields so a canonical entry
        // plus its former singular twin lose nothing.
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

    /**
     * Default entities when none specified
     */
    private static function getDefaultEntities(string $siteName): array {
        return [
            [
                'id'       => 'services',
                'name'     => 'Services / Products',
                'singular' => 'Service',
                'icon'     => '💼',
                'fields'   => [
                    ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true],
                    ['name' => 'category', 'label' => 'Category', 'type' => 'text', 'required' => false],
                    ['name' => 'price', 'label' => 'Price ($)', 'type' => 'text', 'required' => false],
                    ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'required' => false],
                ]
            ],
            [
                'id'       => 'inquiries',
                'name'     => 'Customer Inquiries',
                'singular' => 'Inquiry',
                'icon'     => '✉️',
                'fields'   => [
                    ['name' => 'name', 'label' => 'Customer Name', 'type' => 'text', 'required' => true],
                    ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                    ['name' => 'phone', 'label' => 'Phone', 'type' => 'text', 'required' => false],
                    ['name' => 'message', 'label' => 'Message / Notes', 'type' => 'textarea', 'required' => false],
                    ['name' => 'date', 'label' => 'Date Received', 'type' => 'date', 'required' => false],
                ]
            ],
        ];
    }

    private static function getSampleDataForEntity(array $ent): array {
        $id = $ent['id'] ?? 'item';
        if ($id === 'services' || $id === 'products') {
            return [
                ['id' => '1', 'title' => 'Premium Consultation', 'category' => 'Consulting', 'price' => '150.00', 'description' => 'One-on-one expert session tailored to your goals.'],
                ['id' => '2', 'title' => 'Standard Package', 'category' => 'Services', 'price' => '99.00', 'description' => 'Comprehensive package covering all essentials.'],
                ['id' => '3', 'title' => 'Starter Pack', 'category' => 'Basic', 'price' => '49.00', 'description' => 'Quick setup and support to get you up and running.'],
            ];
        }
        if ($id === 'inquiries' || $id === 'messages') {
            return [
                ['id' => '1', 'name' => 'Sarah Johnson', 'email' => 'sarah@example.com', 'phone' => '+1 555-0142', 'message' => 'Interested in booking a consultation next week.', 'date' => date('Y-m-d')],
                ['id' => '2', 'name' => 'Michael Chang', 'email' => 'michael.c@example.com', 'phone' => '+1 555-0199', 'message' => 'Please send your service catalogue and pricing details.', 'date' => date('Y-m-d', strtotime('-1 day'))],
            ];
        }
        return [
            ['id' => '1', 'title' => 'Sample Record 1', 'description' => 'Sample entry for ' . ($ent['name'] ?? 'Section')],
            ['id' => '2', 'title' => 'Sample Record 2', 'description' => 'Another record for testing.'],
        ];
    }

    /**
     * Code for auth.php
     */
    private static function getAuthPhpCode(): string {
        return <<<'PHP'
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireAdmin(): void {
    if (empty($_SESSION['admin_auth'])) {
        $redirect = 'login.php';
        header("Location: $redirect");
        exit;
    }
}

/** Login rate limiter: max 5 attempts per 5 minutes per IP. */
function admin_login_allowed(): bool {
    $file = __DIR__ . '/.login_attempts.json';
    $now = time();
    $rows = file_exists($file) ? (json_decode(@file_get_contents($file), true) ?: []) : [];
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    $rows = array_values(array_filter($rows, fn($r) => ($r['at'] ?? 0) > $now - 300));
    $n = 0;
    foreach ($rows as $r) { if (($r['ip'] ?? '') === $ip) $n++; }
    @file_put_contents($file, json_encode($rows, JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $n < 5;
}

function admin_login_failed(): void {
    $file = __DIR__ . '/.login_attempts.json';
    $rows = file_exists($file) ? (json_decode(@file_get_contents($file), true) ?: []) : [];
    $rows[] = ['ip' => $_SERVER['REMOTE_ADDR'] ?? 'cli', 'at' => time()];
    @file_put_contents($file, json_encode(array_slice($rows, -50), JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function getAuthData(): array {
    $file = __DIR__ . '/.auth.json';
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?: [];
}

/** CSRF protection for session-authenticated admin POST forms. */
function admin_csrf_token(): string {
    if (empty($_SESSION['admin_csrf'])) $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    return (string)$_SESSION['admin_csrf'];
}

function admin_csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(admin_csrf_token(), ENT_QUOTES) . '">';
}

function admin_require_csrf(): void {
    $t = (string)($_POST['csrf'] ?? '');
    $s = (string)($_SESSION['admin_csrf'] ?? '');
    if ($t === '' || $s === '' || !hash_equals($s, $t)) {
        http_response_code(403);
        exit('Invalid session token. Please reload the page and try again.');
    }
}

/**
 * SECTION 18.5 — VISIBILITY & DISPLAY RULE GUARD
 * Evaluates whether Requirement Builder features may be rendered.
 */
function canShowRequirementBuilder(string $role, string $mode, $recordId): bool {
    return ($role === 'admin' && $mode === 'edit' && !empty($recordId));
}
PHP;
    }

    /**
     * Code for login.php — hash-only, rate-limited, generic errors.
     * No embedded credentials, no autologin, no demo account.
     */
    private static function getLoginPhpCode(string $siteName): string {
        $safeSite = htmlspecialchars($siteName, ENT_QUOTES);

        return <<<PHP
<?php
require_once __DIR__ . '/auth.php';

\$authData = getAuthData();
\$storedUser = (string)(\$authData['username'] ?? '');
\$storedHash = (string)(\$authData['hash'] ?? '');

// Already logged in?
if (!empty(\$_SESSION['admin_auth'])) {
    header('Location: index.php');
    exit;
}

\$error = '';
if (\$_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_login_allowed()) {
        \$error = 'Too many attempts. Please wait 5 minutes and try again.';
    } else {
        \$u = trim(\$_POST['username'] ?? '');
        \$p = (string)(\$_POST['password'] ?? '');

        \$ok = false;
        if (\$storedUser !== '' && \$storedHash !== '' && hash_equals(strtolower(\$storedUser), strtolower(\$u))) {
            \$ok = password_verify(\$p, \$storedHash);
        }
        if (\$ok) {
            session_regenerate_id(true);
            \$_SESSION['admin_auth'] = true;
            \$_SESSION['admin_username'] = \$storedUser;
            \$_SESSION['admin_email'] = \$authData['email'] ?? '';
            header('Location: index.php');
            exit;
        }
        admin_login_failed();
        // Generic error: never reveal whether the username or password was wrong.
        \$error = 'Invalid username or password. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login &mdash; {$safeSite}</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Fira+Code:wght@500&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',system-ui,sans-serif;background:radial-gradient(ellipse at top,#1e1b4b 0%,#0a0d14 60%);color:#e2e8f0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem}
.card{background:#111622;border:1px solid #1e293b;border-radius:24px;width:100%;max-width:440px;padding:2.5rem;box-shadow:0 25px 70px rgba(0,0,0,.6)}
.logo-icon{width:56px;height:56px;border-radius:16px;background:linear-gradient(135deg,#6366f1,#a855f7);display:flex;align-items:center;justify-content:center;font-size:1.8rem;margin:0 auto 1.25rem;box-shadow:0 10px 30px rgba(99,102,241,.35)}
h1{font-size:1.45rem;font-weight:800;color:#fff;text-align:center;margin-bottom:.35rem}
.sub{color:#94a3b8;font-size:.86rem;text-align:center;margin-bottom:2rem}
.field{margin-bottom:1.25rem}
.lbl{display:block;font-size:.8rem;font-weight:700;color:#cbd5e1;margin-bottom:.4rem;text-transform:uppercase;letter-spacing:.03em}
.inp{width:100%;padding:.85rem 1.1rem;border:1.5px solid #283347;border-radius:12px;background:#0b0f17;color:#fff;font-family:inherit;font-size:.95rem;transition:.2s}
.inp:focus{outline:none;border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.25)}
.btn-submit{width:100%;padding:.9rem;border-radius:12px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;font-weight:800;font-size:.95rem;border:none;cursor:pointer;transition:.2s;margin-top:.5rem}
.btn-submit:hover{transform:translateY(-2px);box-shadow:0 10px 25px rgba(99,102,241,.4)}
.err-box{background:rgba(239,68,68,.12);border:1px solid #ef4444;color:#fca5a5;padding:.75rem 1rem;border-radius:10px;font-size:.84rem;margin-bottom:1.25rem;font-weight:600}
.quick-box{background:#0b0f17;border:1px solid #1e293b;border-radius:14px;padding:1.1rem;margin-top:1.75rem}
.quick-title{font-size:.78rem;font-weight:700;color:#a5b4fc;margin-bottom:.65rem;display:flex;align-items:center;gap:.4rem}
.btn-quick{width:100%;background:#1e1b4b;border:1.5px solid #6366f1;color:#c7d2fe;padding:.65rem;border-radius:9px;font-size:.82rem;font-weight:700;cursor:pointer;transition:.2s;display:flex;align-items:center;justify-content:center;gap:.5rem}
.btn-quick:hover{background:#312e81;color:#fff}
.site-link{display:block;text-align:center;margin-top:1.5rem;color:#64748b;font-size:.82rem;text-decoration:none;transition:.2s}
.site-link:hover{color:#94a3b8}
</style>
</head>
<body>
<div class="card">
  <div class="logo-icon">🔐</div>
  <h1>Admin Panel</h1>
  <p class="sub">{$safeSite} &bull; Content Management System</p>

  <?php if (\$error): ?>
    <div class="err-box">⚠️ <?= htmlspecialchars(\$error) ?></div>
  <?php endif; ?>

  <form method="POST" id="loginForm">
    <div class="field">
      <label class="lbl" for="username">Username</label>
      <input type="text" name="username" id="username" class="inp" required autofocus autocomplete="username" placeholder="Enter admin username">
    </div>
    <div class="field">
      <label class="lbl" for="password">Password</label>
      <input type="password" name="password" id="password" class="inp" required autocomplete="current-password" placeholder="Enter password">
    </div>
    <button type="submit" class="btn-submit">Sign In to Dashboard &rarr;</button>
  </form>

  <a href="../index.html" class="site-link">&larr; Return to Live Website</a>
</div>

</body>
</html>
PHP;
    }

    /**
     * Code for logout.php
     */
    private static function getLogoutPhpCode(): string {
        return <<<'PHP'
<?php
session_start();
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();
header('Location: login.php?logged_out=1');
exit;
PHP;
    }

    /**
     * Code for layout.php (common sidebar, header, styling)
     */
    private static function getLayoutPhpCode(string $siteName, array $entities): string {
        $safeSite = htmlspecialchars($siteName, ENT_QUOTES);
        $entitiesJson = self::jsEmbed(json_encode($entities));

        return <<<PHP
<?php
require_once __DIR__ . '/auth.php';
requireAdmin();

// Business-specific modules (from the validated business spec — every
// business gets its own nav, never a fixed one-size-fits-all set).
\$navEntities = json_decode('$entitiesJson', true) ?: [];

function renderAdminHeader(string \$activeTab = 'dashboard', string \$pageTitle = ''): void {
    \$auth = getAuthData();
    \$siteName = '$safeSite';
    \$user = htmlspecialchars(\$_SESSION['admin_username'] ?? \$auth['username'] ?? 'Admin');
    // Unread inbox count (contact form writes admin/data_inquiries.json)
    \$unreadInbox = 0;
    \$inboxFile = __DIR__ . '/data_inquiries.json';
    if (file_exists(\$inboxFile)) {
        \$rows = json_decode(@file_get_contents(\$inboxFile), true);
        if (is_array(\$rows)) foreach (\$rows as \$r) {
            if (empty(\$r['read'])) \$unreadInbox++;
        }
    }
    \$inboxBadge = \$unreadInbox > 0 ? ' <span class="badge badge-green" style="margin-left:auto">' . \$unreadInbox . '</span>' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= \$pageTitle ? htmlspecialchars(\$pageTitle) . ' &mdash; ' : '' ?><?= \$siteName ?> Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',system-ui,sans-serif;background:#0a0d14;color:#e2e8f0;min-height:100vh;display:flex}
.sidebar{width:260px;background:#0d121c;border-right:1px solid #1e293b;padding:1.5rem 1rem;flex-shrink:0;min-height:100vh;display:flex;flex-direction:column}
.main{flex:1;padding:2rem 2.5rem;overflow-y:auto;max-width:1400px}
.brand{display:flex;align-items:center;gap:.75rem;padding:0 .5rem 1.5rem;border-bottom:1px solid #1e293b;margin-bottom:1.25rem}
.brand .logo{width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#6366f1,#a855f7);display:flex;align-items:center;justify-content:center;font-size:1.1rem}
.brand-name{font-weight:800;font-size:.95rem;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.nav-item{display:flex;align-items:center;gap:.75rem;padding:.7rem 1rem;border-radius:10px;color:#94a3b8;text-decoration:none;font-size:.88rem;font-weight:600;margin-bottom:.35rem;transition:.2s}
.nav-item:hover{background:#111622;color:#fff}
.nav-item.active{background:#1e1b4b;color:#a5b4fc;font-weight:700}
.top-actions{display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;flex-wrap:wrap;gap:1rem}
.page-title{font-size:1.75rem;font-weight:900;color:#fff;letter-spacing:-.02em}
.btn{display:inline-flex;align-items:center;gap:.5rem;padding:.7rem 1.3rem;border-radius:10px;font-weight:700;font-size:.86rem;cursor:pointer;border:none;transition:.2s;text-decoration:none}
.btn-primary{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(99,102,241,.4)}
.btn-success{background:linear-gradient(135deg,#10b981,#059669);color:#fff}
.btn-success:hover{transform:translateY(-2px)}
.btn-ghost{background:#1e293b;color:#94a3b8;border:1.5px solid #334155}
.btn-ghost:hover{border-color:#6366f1;color:#fff}
.btn-danger{background:rgba(239,68,68,.15);border:1px solid #ef4444;color:#fca5a5}
.btn-danger:hover{background:#ef4444;color:#fff}
.card{background:#111622;border:1px solid #1e293b;border-radius:18px;padding:1.75rem;margin-bottom:1.5rem}
.grid4{display:grid;grid-template-columns:repeat(4,1fr);gap:1.25rem;margin-bottom:2rem}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem}
@media(max-width:1050px){.grid4{grid-template-columns:repeat(2,1fr)}}
@media(max-width:768px){body{flex-direction:column}.sidebar{width:100%;min-height:auto}.grid4,.grid2{grid-template-columns:1fr}}
.stat-box{background:#0b0f17;border:1px solid #1e293b;border-radius:14px;padding:1.25rem;position:relative}
.stat-lbl{font-size:.76rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em}
.stat-val{font-size:1.75rem;font-weight:900;color:#fff;margin:.35rem 0}
.field{margin-bottom:1.25rem}
.lbl{display:block;font-size:.78rem;font-weight:700;color:#cbd5e1;margin-bottom:.4rem;text-transform:uppercase;letter-spacing:.03em}
.inp,.select,textarea{width:100%;padding:.75rem 1rem;border:1.5px solid #283347;border-radius:11px;background:#0b0f17;color:#fff;font-family:inherit;font-size:.9rem;transition:.2s}
.inp:focus,.select:focus,textarea:focus{outline:none;border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.2)}
table{width:100%;border-collapse:collapse;margin-top:1rem}
th{text-align:left;padding:.75rem 1rem;font-size:.75rem;font-weight:700;color:#64748b;text-transform:uppercase;border-bottom:1px solid #1e293b}
td{padding:.9rem 1rem;border-bottom:1px solid #1e293b;font-size:.85rem;color:#cbd5e1}
tr:hover td{background:#0b0f17}
.badge{display:inline-block;padding:.2rem .65rem;border-radius:999px;font-size:.72rem;font-weight:800}
.badge-green{background:#064e3b;color:#34d399}
.badge-blue{background:#1e1b4b;color:#a5b4fc}
.toast{position:fixed;bottom:2rem;right:2rem;background:#10b981;color:#fff;padding:.85rem 1.4rem;border-radius:12px;font-weight:700;box-shadow:0 15px 40px rgba(0,0,0,.5);z-index:99999;display:none}
.toast.show{display:block}
</style>
</head>
<body>

<aside class="sidebar">
  <div class="brand">
    <div class="logo">⚡</div>
    <div style="min-width:0">
      <div class="brand-name"><?= \$siteName ?></div>
      <div style="font-size:.72rem;color:#64748b">Customer CMS Admin</div>
    </div>
  </div>

  <nav style="flex:1">
    <a href="index.php" class="nav-item <?= \$activeTab === 'dashboard' ? 'active' : '' ?>">📊 Dashboard</a>
    <a href="messages.php" class="nav-item <?= \$activeTab === 'messages' ? 'active' : '' ?>">✉️ Inbox<?= \$inboxBadge ?></a>
    <a href="content.php" class="nav-item <?= \$activeTab === 'content' ? 'active' : '' ?>">📝 Edit Content</a>
    <a href="manage.php" class="nav-item <?= \$activeTab === 'manage' ? 'active' : '' ?>">📋 Manage Data</a>
    <?php foreach (\$navEntities as \$ne): ?>
      <a href="manage.php?entity=<?= urlencode(\$ne['id'] ?? '') ?>" class="nav-item" style="padding-left:2.5rem;font-size:.8rem"><?= htmlspecialchars((\$ne['icon'] ?? '📁') . ' ' . (\$ne['name'] ?? \$ne['id'] ?? '')) ?></a>
    <?php endforeach; ?>
    <a href="features.php" class="nav-item <?= \$activeTab === 'features' ? 'active' : '' ?>" style="color:#c7d2fe;background:rgba(99,102,241,0.12);border:1px dashed rgba(99,102,241,0.4)">🤖 Add Functions (AI)</a>
    <a href="settings.php" class="nav-item <?= \$activeTab === 'settings' ? 'active' : '' ?>">⚙️ Settings &amp; Password</a>
  </nav>

  <div style="padding-top:1.5rem;border-top:1px solid #1e293b">
    <div style="font-size:.75rem;color:#64748b;margin-bottom:.5rem;padding:0 .5rem">Logged in as: <strong style="color:#cbd5e1"><?= \$user ?></strong></div>
    <a href="../index.html" target="_blank" class="nav-item" style="color:#10b981">🌐 View Live Website &rarr;</a>
    <a href="logout.php" class="nav-item" style="color:#ef4444">🚪 Sign Out</a>
  </div>
</aside>

<main class="main">
<?php
}

function renderAdminFooter(): void {
?>
</main>
<div id="toast" class="toast"></div>
<script>
function showToast(msg) {
  const t = document.getElementById('toast');
  if (!t) return;
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 3500);
}
</script>
</body>
</html>
<?php
}
PHP;
    }

    /**
     * Code for index.php (Dashboard)
     */
    private static function getDashboardPhpCode(string $siteName, array $entities): string {
        $safeSite = htmlspecialchars($siteName, ENT_QUOTES);

        return <<<PHP
<?php
require_once __DIR__ . '/layout.php';
renderAdminHeader('dashboard', 'Dashboard');

\$contentData = file_exists(__DIR__ . '/content.json') ? json_decode(file_get_contents(__DIR__ . '/content.json'), true) : [];
\$authData = getAuthData();
// Inbox stats (contact-form inquiries land in data_inquiries.json)
\$inboxRows = file_exists(__DIR__ . '/data_inquiries.json') ? (json_decode(@file_get_contents(__DIR__ . '/data_inquiries.json'), true) ?: []) : [];
if (!is_array(\$inboxRows)) \$inboxRows = [];
\$unreadCount = 0;
foreach (\$inboxRows as \$ir) { if (empty(\$ir['read'])) \$unreadCount++; }
\$latestInquiry = \$inboxRows[0] ?? null;
// Real counts from persisted records — never fabricated.
\$__secCount = 0;
\$__idxHtml = @file_get_contents(__DIR__ . '/../index.html');
if (is_string(\$__idxHtml)) \$__secCount = preg_match_all('/<section\\b/i', \$__idxHtml);
\$__recTotal = 0;
foreach (glob(__DIR__ . '/data_*.json') ?: [] as \$__df) {
    if (basename(\$__df) === 'data_inquiries.json') continue;
    \$__jr = json_decode(@file_get_contents(\$__df), true);
    if (is_array(\$__jr)) \$__recTotal += count(\$__jr);
}
// Honest status: HTTPS reflects the live request scheme; the site file
// check reflects this published directory. No unverified claims.
\$__isHttps = (!empty(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] !== 'off') || ((int)(\$_SERVER['SERVER_PORT'] ?? 80) === 443);
\$__siteLive = is_file(__DIR__ . '/../index.html');
?>
<div class="top-actions">
  <div>
    <h1 class="page-title">Welcome, <?= htmlspecialchars(\$_SESSION['admin_username'] ?? 'Admin') ?> 👋</h1>
    <p style="color:#64748b;font-size:.88rem;margin-top:.3rem">Manage your website content, customer records, and settings here.</p>
  </div>
  <a href="../index.html" target="_blank" class="btn btn-primary">
    <span>🌐</span> View Live Website &rarr;
  </a>
</div>

<!-- Stat Cards -->
<div class="grid4">
  <div class="stat-box">
    <div class="stat-lbl">Website Status</div>
    <div class="stat-val" style="color:<?= \$__siteLive ? '#10b981' : '#f59e0b' ?>"><?= \$__siteLive ? 'Live 🟢' : 'No site file ⚠️' ?></div>
    <div style="font-size:.75rem;color:#64748b">Published directory</div>
  </div>
  <div class="stat-box">
    <div class="stat-lbl">Connection</div>
    <div class="stat-val" style="color:<?= \$__isHttps ? '#a5b4fc' : '#f59e0b' ?>"><?= \$__isHttps ? 'HTTPS 🔒' : 'HTTP — enable HTTPS' ?></div>
    <div style="font-size:.75rem;color:#64748b">Live request scheme</div>
  </div>
  <div class="stat-box">
    <div class="stat-lbl">Content Sections</div>
    <div class="stat-val"><?= (int)\$__secCount ?></div>
    <div style="font-size:.75rem;color:#64748b">Live site sections</div>
  </div>
  <div class="stat-box">
    <div class="stat-lbl">Stored Records</div>
    <div class="stat-val"><?= (int)\$__recTotal ?></div>
    <div style="font-size:.75rem;color:#64748b"><a href="manage.php" style="color:#818cf8;text-decoration:none">Manage Data &rarr;</a></div>
  </div>
  <div class="stat-box">
    <div class="stat-lbl">New Inquiries</div>
    <div class="stat-val" style="color:<?= \$unreadCount > 0 ? '#f59e0b' : '#10b981' ?>"><?= (int)\$unreadCount ?></div>
    <div style="font-size:.75rem;color:#64748b"><a href="messages.php" style="color:#818cf8;text-decoration:none">Open Inbox &rarr;</a></div>
  </div>
  <div class="stat-box">
    <div class="stat-lbl">Account</div>
    <div class="stat-val" style="font-size:1.25rem"><?= htmlspecialchars(\$_SESSION['admin_username'] ?? 'admin') ?></div>
    <div style="font-size:.75rem;color:#64748b"><a href="settings.php" style="color:#818cf8;text-decoration:none">Change Password &rarr;</a></div>
  </div>
</div>

<!-- Quick Actions -->
<h2 style="font-size:1.15rem;font-weight:800;color:#fff;margin-bottom:1rem">⚡ Quick Actions</h2>
<div class="grid2" style="margin-bottom:2.5rem">
  <div class="card" style="margin-bottom:0">
    <div style="font-size:1.8rem;margin-bottom:.75rem">📝</div>
    <h3 style="font-size:1.1rem;font-weight:800;color:#fff;margin-bottom:.35rem">Edit Website Content</h3>
    <p style="color:#94a3b8;font-size:.85rem;line-height:1.55;margin-bottom:1.25rem">
      Update your business name, phone number, email, address, hero banner, and about text directly on your live website.
    </p>
    <a href="content.php" class="btn btn-primary">Open Content Editor &rarr;</a>
  </div>

  <div class="card" style="margin-bottom:0">
    <div style="font-size:1.8rem;margin-bottom:.75rem">📋</div>
    <h3 style="font-size:1.1rem;font-weight:800;color:#fff;margin-bottom:.35rem">Manage Business Data</h3>
    <p style="color:#94a3b8;font-size:.85rem;line-height:1.55;margin-bottom:1.25rem">
      Add, edit, or delete items such as services, products, customer inquiries, and appointments with simple forms.
    </p>
    <a href="manage.php" class="btn btn-ghost">Manage Records &rarr;</a>
  </div>
</div>

<!-- Live Info Card -->
<div class="card">
  <h3 style="font-size:1.05rem;font-weight:800;color:#fff;margin-bottom:1rem">ℹ️ Your Website Details</h3>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;font-size:.85rem">
    <div><strong style="color:#64748b">Business Name:</strong> <span style="color:#fff"><?= htmlspecialchars(\$contentData['business_name'] ?? '$safeSite') ?></span></div>
    <div><strong style="color:#64748b">Recovery Email:</strong> <span style="color:#fff"><?= htmlspecialchars(\$authData['email'] ?? 'Not set') ?></span></div>
    <div><strong style="color:#64748b">Primary Phone:</strong> <span style="color:#fff"><?= htmlspecialchars(\$contentData['phone'] ?? 'Not set') ?></span></div>
    <div><strong style="color:#64748b">Admin URL:</strong> <code style="color:#34d399;font-size:.8rem"><?= htmlspecialchars(\$_SERVER['REQUEST_URI'] ?? '') ?></code></div>
  </div>
</div>

<?php renderAdminFooter(); ?>
PHP;
    }

    /**
     * Code for content.php (Live Content Editor)
     */
    private static function getContentEditorPhpCode(string $siteName): string {
        $safeSite = htmlspecialchars($siteName, ENT_QUOTES);

        return <<<PHP
<?php
require_once __DIR__ . '/layout.php';

\$contentFile = __DIR__ . '/content.json';
\$msg = '';

if (\$_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf();
    \$data = [
        'business_name' => trim(\$_POST['business_name'] ?? ''),
        'tagline'       => trim(\$_POST['tagline'] ?? ''),
        'phone'         => trim(\$_POST['phone'] ?? ''),
        'email'         => trim(\$_POST['email'] ?? ''),
        'address'       => trim(\$_POST['address'] ?? ''),
        'hours'         => trim(\$_POST['hours'] ?? ''),
        'hero_title'    => trim(\$_POST['hero_title'] ?? ''),
        'hero_subtitle' => trim(\$_POST['hero_subtitle'] ?? ''),
        'about_title'   => trim(\$_POST['about_title'] ?? ''),
        'about_text'    => trim(\$_POST['about_text'] ?? ''),
    ];
    file_put_contents(\$contentFile, json_encode(\$data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Also update ../index.html dynamically if placeholder/text exists
    \$indexPath = dirname(__DIR__) . '/index.html';
    if (file_exists(\$indexPath) && !empty(\$data['business_name'])) {
        \$html = file_get_contents(\$indexPath);
        // Replace title tag
        \$html = preg_replace('/<title>(.*?)<\\/title>/i', '<title>' . htmlspecialchars(\$data['business_name']) . '</title>', \$html);
        file_put_contents(\$indexPath, \$html);
    }

    \$msg = 'Content saved successfully! Changes are live on your website.';
}

\$content = file_exists(\$contentFile) ? json_decode(file_get_contents(\$contentFile), true) : [];
renderAdminHeader('content', 'Edit Website Content');
?>
<div class="top-actions">
  <div>
    <h1 class="page-title">📝 Website Content Editor</h1>
    <p style="color:#64748b;font-size:.88rem;margin-top:.3rem">Update business information and key headlines appearing on your live website.</p>
  </div>
  <a href="../index.html" target="_blank" class="btn btn-ghost"><span>👁️</span> Preview Website &rarr;</a>
</div>

<?php if (\$msg): ?>
  <div style="background:rgba(16,185,129,.15);border:1px solid #10b981;color:#34d399;padding:1rem 1.25rem;border-radius:12px;font-weight:700;margin-bottom:1.5rem">
    ✅ <?= htmlspecialchars(\$msg) ?>
  </div>
<?php endif; ?>

<form method="POST">
  <?= admin_csrf_field() ?>
  <!-- General Info Card -->
  <div class="card">
    <h2 style="font-size:1.15rem;font-weight:800;color:#fff;margin-bottom:1.25rem">🏢 Business Profile &amp; Contact</h2>
    <div class="grid2">
      <div class="field">
        <label class="lbl">Business Name</label>
        <input type="text" name="business_name" class="inp" value="<?= htmlspecialchars(\$content['business_name'] ?? '$safeSite') ?>" required>
      </div>
      <div class="field">
        <label class="lbl">Tagline / Slogan</label>
        <input type="text" name="tagline" class="inp" value="<?= htmlspecialchars(\$content['tagline'] ?? '') ?>">
      </div>
      <div class="field">
        <label class="lbl">Phone Number</label>
        <input type="text" name="phone" class="inp" value="<?= htmlspecialchars(\$content['phone'] ?? '') ?>">
      </div>
      <div class="field">
        <label class="lbl">Public Email</label>
        <input type="email" name="email" class="inp" value="<?= htmlspecialchars(\$content['email'] ?? '') ?>">
      </div>
    </div>
    <div class="grid2">
      <div class="field">
        <label class="lbl">Physical Address</label>
        <input type="text" name="address" class="inp" value="<?= htmlspecialchars(\$content['address'] ?? '') ?>">
      </div>
      <div class="field">
        <label class="lbl">Working Hours</label>
        <input type="text" name="hours" class="inp" value="<?= htmlspecialchars(\$content['hours'] ?? '') ?>">
      </div>
    </div>
  </div>

  <!-- Hero & About Card -->
  <div class="card">
    <h2 style="font-size:1.15rem;font-weight:800;color:#fff;margin-bottom:1.25rem">✨ Hero Banner &amp; About Text</h2>
    <div class="field">
      <label class="lbl">Hero Headline</label>
      <input type="text" name="hero_title" class="inp" value="<?= htmlspecialchars(\$content['hero_title'] ?? '') ?>">
    </div>
    <div class="field">
      <label class="lbl">Hero Subtitle</label>
      <textarea name="hero_subtitle" rows="3" class="inp"><?= htmlspecialchars(\$content['hero_subtitle'] ?? '') ?></textarea>
    </div>
    <div class="field">
      <label class="lbl">About Section Title</label>
      <input type="text" name="about_title" class="inp" value="<?= htmlspecialchars(\$content['about_title'] ?? '') ?>">
    </div>
    <div class="field">
      <label class="lbl">About Section Description</label>
      <textarea name="about_text" rows="4" class="inp"><?= htmlspecialchars(\$content['about_text'] ?? '') ?></textarea>
    </div>

    <div style="text-align:right;margin-top:1rem">
      <button type="submit" class="btn btn-primary" style="padding:.9rem 2rem;font-size:1rem">
        <span>💾</span> Save All Changes &rarr;
      </button>
    </div>
  </div>
</form>

<?php renderAdminFooter(); ?>
PHP;
    }

    /**
     * Code for manage.php (Entities Manager CRUD)
     */
    private static function getManagePhpCode(string $siteName, array $entities): string {
        $safeEntitiesJson = self::jsEmbed(json_encode($entities));

        return <<<PHP
<?php
require_once __DIR__ . '/layout.php';

\$entities = json_decode('$safeEntitiesJson', true) ?: [];
// Live data files created since publish (e.g. storefront-submitted records
// mirrored from the API) appear as extra modules. Columns derive
// from their own records — nothing hidden, nothing hardcoded.
\$__knownIds = [];
foreach (\$entities as \$ent) { \$__knownIds[(string)(\$ent['id'] ?? '')] = true; }
foreach (glob(__DIR__ . '/data_*.json') ?: [] as \$df) {
    \$did = preg_replace('/^data_|\.json$/', '', basename(\$df));
    if (\$did === '' || isset(\$__knownIds[\$did]) || \$did === 'inquiries') continue;
    \$__sample = [];
    \$__jr = json_decode(@file_get_contents(\$df), true);
    if (is_array(\$__jr)) { foreach (\$__jr as \$__rr) { if (is_array(\$__rr)) { \$__sample = \$__rr; break; } } }
    \$__dfields = [];
    foreach (array_keys(\$__sample) as \$__k) {
        if (\$__k === 'id') continue;
        \$__dfields[] = ['name' => \$__k, 'label' => ucwords(str_replace('_', ' ', \$__k)), 'type' => 'text', 'required' => false];
        if (count(\$__dfields) >= 8) break;
    }
    \$entities[] = ['id' => \$did, 'name' => ucwords(str_replace('_', ' ', \$did)),
        'singular' => ucwords(str_replace('_', ' ', \$did)), 'icon' => '📁', 'fields' => \$__dfields];
    \$__knownIds[\$did] = true;
}
\$activeEntityId = \$_GET['entity'] ?? (\$entities[0]['id'] ?? 'services');

\$activeEntity = null;
foreach (\$entities as \$ent) {
    if (\$ent['id'] === \$activeEntityId) {
        \$activeEntity = \$ent;
        break;
    }
}
if (!\$activeEntity && !empty(\$entities)) {
    \$activeEntity = \$entities[0];
    \$activeEntityId = \$activeEntity['id'];
}

\$dataFile = __DIR__ . '/data_' . \$activeEntityId . '.json';
\$records = file_exists(\$dataFile) ? json_decode(file_get_contents(\$dataFile), true) : [];
if (!is_array(\$records)) \$records = [];
// Read-repair: union customer-submitted rows from the JSON stores
// (storage/collections + storage/sites) so frontend activity is NEVER
// invisible here — e.g. submitted before this admin dir existed, via the
// Node API (sites/), or for entities added after publish. Upsert by id.
\$__entSafe = preg_replace('/[^a-z0-9_]/i', '', \$activeEntityId);
\$__byId = [];
foreach (\$records as \$__rr) { if (is_array(\$__rr) && isset(\$__rr['id'])) \$__byId[(string)\$__rr['id']] = \$__rr; }
\$__extra = 0;
foreach ([dirname(__FILE__, 3) . '/storage/collections/' . basename(dirname(__DIR__)) . '/' . \$__entSafe . '.json',
          dirname(__FILE__, 3) . '/storage/sites/' . basename(dirname(__DIR__)) . '/' . \$__entSafe . '.json'] as \$__sf) {
    if (!is_file(\$__sf)) continue;
    \$__sj = json_decode(@file_get_contents(\$__sf), true);
    if (!is_array(\$__sj)) continue;
    foreach (\$__sj as \$__sr) {
        if (is_array(\$__sr) && isset(\$__sr['id']) && !isset(\$__byId[(string)\$__sr['id']])) {
            \$__byId[(string)\$__sr['id']] = \$__sr; \$__extra++;
        }
    }
}
if (\$__extra > 0) {
    \$records = array_values(\$__byId);
    if (count(\$records) > 2000) \$records = array_slice(\$records, -2000);
    @file_put_contents(\$dataFile, json_encode(array_values(\$records), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}
// Extra columns: submitted keys beyond the spec fields (ref, status,
// payment, seats, ...) render too — the admin sees ALL customer data.
// System keys stay hidden.
\$__sysKeys = ['id' => true, 'created_at' => true, 'updated_at' => true, 'idempotency_key' => true];
\$__fieldNames = [];
foreach (\$activeEntity['fields'] ?? [] as \$__ff) { \$__fieldNames[(string)(\$__ff['name'] ?? '')] = true; }
\$__isBusBookingManager = \$activeEntityId === 'bookings'
    && isset(\$__fieldNames['trip_id']) && isset(\$__fieldNames['seats']);
\$__isBusPaymentLedger = \$activeEntityId === 'payments'
    && isset(\$__fieldNames['payment_id']) && isset(\$__fieldNames['booking_ref']);
\$__isBusLifecycleEntity = \$__isBusBookingManager || \$__isBusPaymentLedger;
\$__extraCols = [];
foreach (\$records as \$__rr) {
    if (!is_array(\$__rr)) continue;
    foreach (\$__rr as \$__k => \$__v) {
        if (isset(\$__sysKeys[\$__k]) || isset(\$__fieldNames[\$__k])) continue;
        if (!in_array(\$__k, \$__extraCols, true)) \$__extraCols[] = (string)\$__k;
        if (count(\$__extraCols) >= 6) break 2;
    }
}
\$__cell = function (\$v) {
    if (is_array(\$v)) \$v = count(\$v) > 0 && array_keys(\$v) === range(0, count(\$v) - 1)
        ? implode(', ', array_map('strval', array_slice(\$v, 0, 6)))
        : json_encode(\$v, JSON_UNESCAPED_UNICODE);
    \$s = (string)(\$v ?? '—');
    if (\$s === '') \$s = '—';
    if (strlen(\$s) > 120) \$s = substr(\$s, 0, 120) . '…';
    return htmlspecialchars(\$s);
};

// Sync helper: admin edits propagate to the platform collections store so
// customer-facing history (business-ops list) reflects permitted changes.
// Convergent by id — the same upsert rule the API mirror uses.
\$__slug = basename(dirname(dirname(__FILE__)));
\$__collFile = dirname(__FILE__, 3) . '/storage/collections/' . preg_replace('/[^a-z0-9\-]/i', '', \$__slug) . '/' . preg_replace('/[^a-z0-9_]/i', '', \$activeEntityId) . '.json';
\$__syncCollections = function (\$rows) use (\$__collFile) {
    \$d = dirname(\$__collFile);
    if (!is_dir(\$d)) @mkdir(\$d, 0755, true);
    \$h = @fopen(\$__collFile, 'c+');
    if (!\$h) return;
    if (!flock(\$h, LOCK_EX)) { fclose(\$h); return; }
    \$raw = stream_get_contents(\$h);
    \$coll = (\$raw !== false && \$raw !== '') ? (json_decode(\$raw, true) ?: []) : [];
    if (!is_array(\$coll)) \$coll = [];
    \$byId = [];
    foreach (\$coll as \$c) { if (is_array(\$c) && isset(\$c['id'])) \$byId[(string)\$c['id']] = \$c; }
    foreach ((array)\$rows as \$r) { if (is_array(\$r) && isset(\$r['id'])) \$byId[(string)\$r['id']] = \$r; }
    \$ids = [];
    foreach ((array)\$rows as \$r) { if (is_array(\$r) && isset(\$r['id'])) \$ids[(string)\$r['id']] = true; }
    // Deletions propagate: drop collection rows absent from admin data.
    foreach (array_keys(\$byId) as \$k) { if (!isset(\$ids[\$k])) unset(\$byId[\$k]); }
    ftruncate(\$h, 0); rewind(\$h);
    fwrite(\$h, json_encode(array_values(\$byId), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fflush(\$h); flock(\$h, LOCK_UN); fclose(\$h);
};

\$msg = '';
// Handle Add / Edit POST
if (\$_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf();
    \$action = \$_POST['action'] ?? '';

    if (\$__isBusLifecycleEntity) {
        http_response_code(403);
        exit('Bus booking and payment records can only be changed through their lifecycle actions.');
    } elseif (\$action === 'save_item') {
        \$id = trim(\$_POST['item_id'] ?? '');
        \$isEdit = !empty(\$id);
        // Server-side validation mirrors the form: required fields + select
        // options. Admin Add/Edit is a real function, never a silent write.
        \$__errs = [];
        foreach (\$activeEntity['fields'] ?? [] as \$__vf) {
            \$__vn = (string)(\$__vf['name'] ?? '');
            if (\$__vn === '') continue;
            \$__vv = trim((string)(\$_POST[\$__vn] ?? ''));
            if (!empty(\$__vf['required']) && \$__vv === '') {
                \$__errs[] = '“' . (\$__vf['label'] ?? \$__vn) . '” is required.';
                continue;
            }
            if (\$__vv !== '' && (\$__vf['type'] ?? '') === 'select'
                && !empty(\$__vf['options']) && is_array(\$__vf['options'])
                && !in_array(\$__vv, array_map('strval', \$__vf['options']), true)) {
                \$__errs[] = '“' . (\$__vf['label'] ?? \$__vn) . '” has an invalid option.';
            }
            if (\$__vv !== '' && (\$__vf['type'] ?? '') === 'email'
                && !filter_var(\$__vv, FILTER_VALIDATE_EMAIL)) {
                \$__errs[] = '“' . (\$__vf['label'] ?? \$__vn) . '” must be a valid email.';
            }
        }
        if (!empty(\$__errs)) {
            \$msg = '⚠️ ' . implode(' ', array_slice(\$__errs, 0, 3));
        } else {

        // Edit preserves keys the form does not render (ref, payment, seats,
        // ...); only known fields are overwritten. Nothing is ever dropped.
        \$newId = (\$activeEntityId === 'trips')
            ? 'TRIP-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)))
            : (string)time();
        \$base = ['id' => \$isEdit ? \$id : \$newId];
        if (\$isEdit) {
            foreach (\$records as \$__er) {
                if (is_array(\$__er) && (string)(\$__er['id'] ?? '') === \$id) { \$base = \$__er; break; }
            }
            \$base['id'] = \$id;
        }
        \$item = \$base;
        foreach (\$activeEntity['fields'] ?? [] as \$f) {
            \$fname = \$f['name'];
            if (\$__isBusBookingManager && in_array(\$fname, ['status', 'payment_status', 'payment_id', 'amount'], true)) {
                \$item[\$fname] = \$base[\$fname] ?? (\$fname === 'status' ? 'pending' : '');
                continue;
            }
            \$item[\$fname] = trim(\$_POST[\$fname] ?? '');
        }

        if (\$isEdit) {
            foreach (\$records as &\$r) {
                if ((\$r['id'] ?? '') === \$id) {
                    \$r = \$item;
                    break;
                }
            }
        } else {
            array_unshift(\$records, \$item);
        }
        file_put_contents(\$dataFile, json_encode(\$records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        \$__syncCollections(\$records);
        \$msg = \$isEdit ? 'Record updated successfully!' : 'New record added successfully!';
        }
    }

    elseif (\$action === 'delete_item') {
        \$delId = trim(\$_POST['item_id'] ?? '');
        \$records = array_values(array_filter(\$records, fn(\$r) => (\$r['id'] ?? '') !== \$delId));
        file_put_contents(\$dataFile, json_encode(\$records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        \$__syncCollections(\$records);
        \$msg = 'Record deleted.';
    }
}

renderAdminHeader('manage', 'Manage Data');
?>
<div class="top-actions">
  <div>
    <h1 class="page-title">📋 Data &amp; Record Manager</h1>
    <p style="color:#64748b;font-size:.88rem;margin-top:.3rem">Add, edit, or remove live data items for your website.</p>
  </div>
  <?php if (!\$__isBusLifecycleEntity): ?><button class="btn btn-primary" onclick="openAddModal()">
    <span>➕</span> Add New <?= htmlspecialchars(\$activeEntity['singular'] ?? 'Item') ?>
  </button><?php endif; ?>
</div>

<?php if (\$msg): ?>
  <div style="background:rgba(16,185,129,.15);border:1px solid #10b981;color:#34d399;padding:.85rem 1.25rem;border-radius:12px;font-weight:700;margin-bottom:1.5rem">
    ✅ <?= htmlspecialchars(\$msg) ?>
  </div>
<?php endif; ?>

<!-- Tabs for entities -->
<div style="display:flex;gap:.5rem;margin-bottom:1.5rem;flex-wrap:wrap">
  <?php foreach (\$entities as \$ent): ?>
    <a href="?entity=<?= urlencode(\$ent['id']) ?>" 
       class="btn <?= \$ent['id'] === \$activeEntityId ? 'btn-primary' : 'btn-ghost' ?>">
       <span><?= htmlspecialchars(\$ent['icon'] ?? '📁') ?></span>
       <span><?= htmlspecialchars(\$ent['name'] ?? \$ent['id']) ?></span>
    </a>
  <?php endforeach; ?>
</div>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
    <h2 style="font-size:1.1rem;font-weight:800;color:#fff"><?= htmlspecialchars(\$activeEntity['name'] ?? 'Records') ?> (<?= count(\$records) ?>)</h2>
    <div style="display:flex;gap:.6rem;flex-wrap:wrap;justify-content:flex-end">
      <input type="text" id="filterInp" placeholder="Search records..." class="inp" style="max-width:260px;padding:.5rem .85rem;font-size:.82rem" oninput="filterTable()">
      <button type="button" class="btn btn-ghost" onclick="downloadDataReport()">Download report</button>
    </div>
  </div>

  <?php if (\$__isBusBookingManager): ?>
    <section style="border:1px solid #263244;border-radius:12px;padding:1rem;margin:0 0 1.25rem;background:#0b111d">
      <h3 style="margin:0 0 .35rem;color:#fff;font-size:1rem">Record a received payment</h3>
      <p style="margin:0 0 .85rem;color:#94a3b8;font-size:.82rem">Enter the ticket reference to load its fare. Choose the method only after receiving the payment.</p>
      <form id="wc-payment-form" style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end">
        <label style="display:grid;gap:.35rem;min-width:210px;flex:1;color:#cbd5e1;font-size:.78rem;font-weight:700">
          Ticket reference
          <input class="inp" id="wc-payment-ref" required maxlength="50" placeholder="BK-XXXXXXXX">
        </label>
        <button class="btn btn-ghost" id="wc-payment-lookup" type="button">Load fare</button>
        <label style="display:grid;gap:.35rem;min-width:150px;color:#cbd5e1;font-size:.78rem;font-weight:700">
          Method
          <select class="inp" id="wc-payment-method" required>
            <option value="">Select method</option><option value="card">Card received</option><option value="cash">Cash received</option>
          </select>
        </label>
        <button class="btn btn-primary" id="wc-payment-submit" type="submit" disabled>Save payment</button>
      </form>
      <p id="wc-payment-quote" aria-live="polite" style="margin:.75rem 0 0;color:#94a3b8;font-size:.85rem">No ticket loaded.</p>
    </section>
  <?php endif; ?>

  <?php if (empty(\$records)): ?>
    <div style="text-align:center;padding:3rem 1rem;color:#64748b">
      <div style="font-size:2rem;margin-bottom:.5rem">📂</div>
      <p><?= \$__isBusPaymentLedger ? 'No received payments have been recorded yet.' : (\$__isBusBookingManager ? 'Customer bookings will appear here after they submit a ticket request.' : 'No records found yet. Click <strong>+ Add New</strong> above to create your first entry.') ?></p>
    </div>
  <?php else: ?>
    <div style="overflow-x:auto">
      <table id="recordsTable">
        <thead>
          <tr>
            <?php foreach (\$activeEntity['fields'] ?? [] as \$f): ?>
              <th><?= htmlspecialchars(\$f['label'] ?? \$f['name']) ?></th>
            <?php endforeach; ?>
            <?php foreach (\$__extraCols as \$__ec): ?>
              <th><?= htmlspecialchars(ucwords(str_replace('_', ' ', \$__ec))) ?></th>
            <?php endforeach; ?>
            <th style="text-align:right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (\$records as \$row): ?>
            <tr>
              <?php foreach (\$activeEntity['fields'] ?? [] as \$f): 
              ?>
                <td><?= \$__cell(\$row[\$f['name']] ?? null) ?></td>
              <?php endforeach; ?>
              <?php foreach (\$__extraCols as \$__ec): ?>
                <td><?= \$__cell(\$row[\$__ec] ?? null) ?></td>
              <?php endforeach; ?>
              <td style="text-align:right;white-space:nowrap">
                <?php if (\$__isBusBookingManager && in_array((string)(\$row['status'] ?? 'pending'), ['pending', 'approved'], true)): ?>
                  <?php if ((\$row['status'] ?? 'pending') === 'pending'): ?>
                    <button type="button" class="btn btn-primary" style="padding:.35rem .6rem;font-size:.76rem;margin-right:.25rem" onclick='wcBookingStatus(<?= json_encode((string)(\$row['id'] ?? '')) ?>,"approved")'>Approve</button>
                    <button type="button" class="btn btn-danger" style="padding:.35rem .6rem;font-size:.76rem;margin-right:.25rem" onclick='wcBookingStatus(<?= json_encode((string)(\$row['id'] ?? '')) ?>,"rejected")'>Reject</button>
                  <?php endif; ?>
                <?php endif; ?>
                <?php if (!\$__isBusLifecycleEntity): ?><button type="button" class="btn btn-ghost" style="padding:.35rem .75rem;font-size:.78rem;margin-right:.4rem"
                        onclick='openEditModal(<?= json_encode(\$row) ?>)'>✏️ Edit</button>
                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this record?');">
                  <?= admin_csrf_field() ?>
                  <input type="hidden" name="action" value="delete_item">
                  <input type="hidden" name="item_id" value="<?= htmlspecialchars(\$row['id'] ?? '') ?>">
                  <button type="submit" class="btn btn-danger" style="padding:.35rem .75rem;font-size:.78rem">🗑️</button>
                </form><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if (!\$__isBusLifecycleEntity): ?>
<!-- Add / Edit Modal -->
<div id="itemModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);backdrop-filter:blur(5px);z-index:9999;align-items:center;justify-content:center;padding:1.5rem">
  <div style="background:#111622;border:1.5px solid #283347;border-radius:20px;max-width:540px;width:100%;padding:2rem;box-shadow:0 25px 60px rgba(0,0,0,.6)">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem">
      <h3 style="font-size:1.25rem;font-weight:800;color:#fff" id="modalTitle">Add Record</h3>
      <button type="button" onclick="closeModal()" style="background:none;border:none;color:#94a3b8;font-size:1.3rem;cursor:pointer">&times;</button>
    </div>

    <form method="POST" id="itemForm">
      <?= admin_csrf_field() ?>
      <input type="hidden" name="action" value="save_item">
      <input type="hidden" name="item_id" id="modal_item_id" value="">

      <?php foreach (\$activeEntity['fields'] ?? [] as \$f):
        if (\$__isBusBookingManager && in_array((string)(\$f['name'] ?? ''), ['status', 'payment_status', 'payment_id', 'amount'], true)) continue;
        \$fname = \$f['name'];
        \$ftype = \$f['type'] ?? 'text';
        \$freq = !empty(\$f['required']);
        \$fopts = (isset(\$f['options']) && is_array(\$f['options'])) ? array_values(array_filter(array_map('strval', \$f['options']))) : [];
      ?>
        <div class="field">
          <label class="lbl"><?= htmlspecialchars(\$f['label'] ?? \$fname) ?><?= \$freq ? ' *' : '' ?></label>
          <?php if (\$ftype === 'textarea'): ?>
            <textarea name="<?= \$fname ?>" id="inp_<?= \$fname ?>" rows="3" class="inp"<?= \$freq ? ' required' : '' ?>></textarea>
          <?php elseif (\$ftype === 'select'): ?>
            <select name="<?= \$fname ?>" id="inp_<?= \$fname ?>" class="inp"<?= \$freq ? ' required' : '' ?>>
              <option value="">— Select —</option>
              <?php foreach (\$fopts as \$__o): ?>
                <option value="<?= htmlspecialchars(\$__o) ?>"><?= htmlspecialchars(\$__o) ?></option>
              <?php endforeach; ?>
            </select>
          <?php elseif (\$ftype === 'number'): ?>
            <input type="number" step="any" name="<?= \$fname ?>" id="inp_<?= \$fname ?>" class="inp"<?= \$freq ? ' required' : '' ?>>
          <?php else: ?>
            <input type="<?= \$ftype === 'email' ? 'email' : (\$ftype === 'date' ? 'date' : ((\$ftype === 'tel' || \$ftype === 'url') ? \$ftype : 'text')) ?>"
                   name="<?= \$fname ?>" id="inp_<?= \$fname ?>" class="inp"<?= \$freq ? ' required' : '' ?>>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <!-- Section 18.5 Requirement Builder hook (only inside edit context) -->
      <div id="req-builder-hook" style="display:none;background:#1e1b4b;border:1px solid #4338ca;padding:.75rem;border-radius:10px;margin-bottom:1.25rem;font-size:.8rem;color:#c7d2fe">
        ✨ <strong>Process Builder:</strong> Edit context verified. Requirement builder features enabled.
      </div>

      <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:1.5rem">
        <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn btn-primary" id="modalSubmitBtn">Save Record &rarr;</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
const WC_ADMIN_CSRF = <?= json_encode(admin_csrf_token()) ?>;
const WC_ADMIN_SLUG = <?= json_encode(\$__slug) ?>;
const WC_ADMIN_RECORDS = <?= json_encode(\$records, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
function wcOps(payload) {
  return fetch('../../../api/business-ops.php', {
    method: 'POST',
    credentials: 'same-origin',
    headers: {'Content-Type': 'application/json', 'X-Admin-CSRF': WC_ADMIN_CSRF},
    body: JSON.stringify(Object.assign({slug: WC_ADMIN_SLUG}, payload))
  }).then(async function(response) {
    const data = await response.json();
    if (!response.ok || !data.success) throw new Error(data.error || 'Request failed.');
    return data;
  });
}
function wcBookingStatus(id, status) {
  if (!confirm('Set this booking to ' + status + '?')) return;
  wcOps({action:'update', entity:'bookings', id:id, patch:{status:status}})
    .then(function(data) {
      const notice = data.notification || {};
      const mail = notice.status === 'queued' ? 'Customer status notification queued.'
        : (notice.error || 'No valid customer email; status was saved without an email.');
      alert('Booking ' + status + '. ' + mail);
      location.reload();
    })
    .catch(function(error) { alert('Could not update booking: ' + error.message); });
}
function downloadDataReport() {
  const rows = Array.isArray(WC_ADMIN_RECORDS) ? WC_ADMIN_RECORDS : [];
  if (!rows.length) { alert('There are no records to export.'); return; }
  const columns = Array.from(new Set(rows.reduce(function(keys, row) {
    return keys.concat(Object.keys(row || {}));
  }, [])));
  const cell = function(value) {
    if (Array.isArray(value) || (value && typeof value === 'object')) value = JSON.stringify(value);
    return '"' + String(value == null ? '' : value).replace(/"/g, '""') + '"';
  };
  const csv = [columns.map(cell).join(','), ...rows.map(function(row) {
    return columns.map(function(key) { return cell(row[key]); }).join(',');
  })].join('\r\n');
  const url = URL.createObjectURL(new Blob(['\uFEFF' + csv], {type:'text/csv;charset=utf-8'}));
  const link = document.createElement('a');
  link.href = url;
  link.download = WC_ADMIN_SLUG + '-<?= htmlspecialchars(\$activeEntityId, ENT_QUOTES) ?>-' + new Date().toISOString().slice(0,10) + '.csv';
  link.click();
  setTimeout(function() { URL.revokeObjectURL(url); }, 1000);
}
<?php if (\$__isBusBookingManager): ?>
let wcPaymentQuote = null;
document.getElementById('wc-payment-lookup').addEventListener('click', function() {
  const ref = document.getElementById('wc-payment-ref').value.trim().toUpperCase();
  const out = document.getElementById('wc-payment-quote');
  wcPaymentQuote = null;
  document.getElementById('wc-payment-submit').disabled = true;
  if (!ref) { out.textContent = 'Enter a ticket reference first.'; return; }
  out.textContent = 'Loading fare…';
  wcOps({action:'payment_quote', entity:'bookings', ref:ref})
    .then(function(result) {
      wcPaymentQuote = result.data;
      out.textContent = result.data.ref + ' · ' + (result.data.passenger || 'Customer') + ' · '
        + result.data.currency + ' ' + Number(result.data.amount).toFixed(2)
        + ' · booking ' + result.data.status;
      document.getElementById('wc-payment-submit').disabled = result.data.status !== 'approved';
      if (result.data.status !== 'approved') out.textContent += ' · Approve the booking before recording payment.';
    })
    .catch(function(error) { out.textContent = error.message; });
});
document.getElementById('wc-payment-form').addEventListener('submit', function(event) {
  event.preventDefault();
  if (!wcPaymentQuote) { alert('Load the ticket fare before saving payment.'); return; }
  const method = document.getElementById('wc-payment-method').value;
  const submit = document.getElementById('wc-payment-submit');
  submit.disabled = true;
  wcOps({action:'pay', entity:'bookings', ref:wcPaymentQuote.ref, method:method})
    .then(function(result) {
      const payment = result.payment || {};
      document.getElementById('wc-payment-quote').textContent = 'Payment saved: '
        + (payment.payment_id || payment.id) + ' · ' + payment.currency + ' '
        + Number(payment.amount).toFixed(2) + ' · ' + method + ' · ' + payment.received_at;
      wcPaymentQuote = null;
      document.getElementById('wc-payment-form').reset();
      setTimeout(function() { location.reload(); }, 1200);
    })
    .catch(function(error) {
      document.getElementById('wc-payment-quote').textContent = error.message;
      submit.disabled = false;
    });
});
<?php endif; ?>

function openAddModal() {
  document.getElementById('modalTitle').textContent = '➕ Add New ' + <?= json_encode(\$activeEntity['singular'] ?? 'Item') ?>;
  document.getElementById('modal_item_id').value = '';
  document.getElementById('itemForm').reset();
  document.getElementById('req-builder-hook').style.display = 'none';
  document.getElementById('itemModal').style.display = 'flex';
}

function openEditModal(row) {
  document.getElementById('modalTitle').textContent = '✏️ Edit ' + <?= json_encode(\$activeEntity['singular'] ?? 'Item') ?>;
  document.getElementById('modal_item_id').value = row.id || '';
  
  <?php foreach (\$activeEntity['fields'] ?? [] as \$f): ?>
    if (document.getElementById('inp_' + <?= json_encode(\$f['name']) ?>)) {
      document.getElementById('inp_' + <?= json_encode(\$f['name']) ?>).value = row[<?= json_encode(\$f['name']) ?>] || '';
    }
  <?php endforeach; ?>

  // Section 18.5: render builder hook ONLY in edit mode
  document.getElementById('req-builder-hook').style.display = 'block';
  document.getElementById('itemModal').style.display = 'flex';
}

function closeModal() {
  document.getElementById('itemModal').style.display = 'none';
}

function filterTable() {
  const query = document.getElementById('filterInp').value.toLowerCase();
  const rows = document.querySelectorAll('#recordsTable tbody tr');
  rows.forEach(r => {
    r.style.display = r.textContent.toLowerCase().includes(query) ? '' : 'none';
  });
}
</script>

<?php renderAdminFooter(); ?>
PHP;
    }

    /**
     * Code for settings.php (with Customer Password Change!)
     */
    private static function getSettingsPhpCode(string $siteName): string {
        return <<<'PHP'
<?php
require_once __DIR__ . '/layout.php';

$authFile = __DIR__ . '/.auth.json';
$authData = file_exists($authFile) ? json_decode(file_get_contents($authFile), true) : [];

$successMsg = '';
$errorMsg = '';

function apg_save_json_atomic(string $file, array $data): bool {
    $tmp = $file . '.tmp-' . bin2hex(random_bytes(4));
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false || @file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    if (@rename($tmp, $file)) return true;
    @unlink($tmp);
    return false;
}

// Handle Password Change POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'backup') {
        // One-click backup: content + all entity data as a JSON download
        $bundle = [
            'site'        => $authData['username'] ?? 'website',
            'exported_at' => date('Y-m-d H:i:s'),
            'content'     => file_exists(__DIR__ . '/content.json') ? json_decode(@file_get_contents(__DIR__ . '/content.json'), true) : [],
            'data'        => [],
        ];
        foreach (glob(__DIR__ . '/data_*.json') ?: [] as $f) {
            $bundle['data'][basename($f)] = json_decode(@file_get_contents($f), true);
        }
        $fn = 'backup-' . date('Ymd-His') . '.json';
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $fn . '"');
        echo json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'change_password') {
        $currPass = (string)($_POST['current_password'] ?? '');
        $newPass  = (string)($_POST['new_password'] ?? '');
        $confPass = (string)($_POST['confirm_password'] ?? '');

        // Verify current password against the bcrypt hash only.
        $storedHash  = (string)($authData['hash'] ?? '');

        $currValid = ($storedHash !== '' && password_verify($currPass, $storedHash));

        if (!$currValid) {
            $errorMsg = 'Current password is incorrect. Please check and try again.';
        } elseif (strlen($newPass) < 8) {
            $errorMsg = 'New password must be at least 8 characters long.';
        } elseif ($newPass !== $confPass) {
            $errorMsg = 'New passwords do not match. Please re-enter.';
        } else {
            // Update .auth.json (hash only — plaintext is never stored)
            $newHash = password_hash($newPass, PASSWORD_DEFAULT);
            $authData['hash'] = $newHash;
            unset($authData['password_plain']);
            $authData['updated_at'] = date('Y-m-d H:i:s');
            if (!apg_save_json_atomic($authFile, $authData)) {
                $errorMsg = 'Could not save the new password. Your current password is unchanged.';
            } else {
                // Keep the platform order in sync so republishing preserves it.
                $orderId = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)($authData['order_id'] ?? ''));
                if ($orderId) {
                    $orderFile = dirname(__DIR__, 3) . '/storage/orders/' . $orderId . '.json';
                    if (is_file($orderFile)) {
                        $order = json_decode((string)@file_get_contents($orderFile), true);
                        if (is_array($order)) {
                            $order['admin_password_hash'] = $newHash;
                            unset($order['admin_password_plain']);
                            if (!apg_save_json_atomic($orderFile, $order)) {
                                $errorMsg = 'Password changed, but saved generation credentials could not be synchronized. Check storage permissions.';
                            }
                        } else {
                            $errorMsg = 'Password changed, but saved generation credentials could not be read.';
                        }
                    }
                }
                if ($errorMsg === '') $successMsg = 'Your password has been changed successfully!';
            }
        }
    }

    if ($action === 'update_profile') {
        $newEmail = trim($_POST['email'] ?? '');
        if (filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $authData['email'] = $newEmail;
            file_put_contents($authFile, json_encode($authData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $successMsg = 'Profile email updated successfully!';
        } else {
            $errorMsg = 'Please enter a valid email address.';
        }
    }
}

renderAdminHeader('settings', 'Settings & Password');
?>
<div class="top-actions">
  <div>
    <h1 class="page-title">⚙️ Settings &amp; Security</h1>
    <p style="color:#64748b;font-size:.88rem;margin-top:.3rem">Change your password and manage login credentials for this website.</p>
  </div>
</div>

<?php if ($successMsg): ?>
  <div style="background:rgba(16,185,129,.15);border:1px solid #10b981;color:#34d399;padding:1rem 1.25rem;border-radius:12px;font-weight:700;margin-bottom:1.5rem">
    ✅ <?= htmlspecialchars($successMsg) ?>
  </div>
<?php endif; ?>

<?php if ($errorMsg): ?>
  <div style="background:rgba(239,68,68,.15);border:1px solid #ef4444;color:#fca5a5;padding:1rem 1.25rem;border-radius:12px;font-weight:700;margin-bottom:1.5rem">
    ⚠️ <?= htmlspecialchars($errorMsg) ?>
  </div>
<?php endif; ?>

<div class="grid2">
  <!-- CHANGE PASSWORD CARD -->
  <div class="card">
    <h2 style="font-size:1.15rem;font-weight:800;color:#fff;margin-bottom:1.25rem;display:flex;align-items:center;gap:.5rem">
      <span>🔑</span> Change Password
    </h2>
    <form method="POST">
      <input type="hidden" name="action" value="change_password">
      <?= admin_csrf_field() ?>

      <div class="field">
        <label class="lbl" for="current_password">Current Password</label>
        <input type="password" name="current_password" id="current_password" class="inp" required placeholder="Enter current password">
      </div>

      <div class="field">
        <label class="lbl" for="new_password">New Password</label>
        <input type="password" name="new_password" id="new_password" class="inp" required minlength="8" placeholder="At least 8 characters">
      </div>

      <div class="field">
        <label class="lbl" for="confirm_password">Confirm New Password</label>
        <input type="password" name="confirm_password" id="confirm_password" class="inp" required minlength="8" placeholder="Re-enter new password">
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;margin-top:.5rem">
        Update Password Now &rarr;
      </button>
    </form>
  </div>

  <!-- PROFILE / ACCOUNT CARD -->
  <div class="card">
    <h2 style="font-size:1.15rem;font-weight:800;color:#fff;margin-bottom:1.25rem;display:flex;align-items:center;gap:.5rem">
      <span>👤</span> Account Details
    </h2>
    <form method="POST">
      <input type="hidden" name="action" value="update_profile">
      <?= admin_csrf_field() ?>

      <div class="field">
        <label class="lbl">Admin Username</label>
        <input type="text" class="inp" value="<?= htmlspecialchars($authData['username'] ?? 'admin') ?>" disabled style="opacity:.6;cursor:not-allowed">
        <div style="font-size:.75rem;color:#64748b;margin-top:.3rem">Username is fixed to your site installation.</div>
      </div>

      <div class="field">
        <label class="lbl" for="email">Recovery Email</label>
        <input type="email" name="email" id="email" class="inp" value="<?= htmlspecialchars($authData['email'] ?? '') ?>" required placeholder="you@example.com">
        <div style="font-size:.75rem;color:#64748b;margin-top:.3rem">Used for password recovery and notifications.</div>
      </div>

      <button type="submit" class="btn btn-ghost" style="width:100%;margin-top:.5rem">
        Save Profile Email
      </button>
    </form>
  </div>
</div>

<!-- BACKUP CARD -->
<div class="card">
  <h2 style="font-size:1.15rem;font-weight:800;color:#fff;margin-bottom:.5rem;display:flex;align-items:center;gap:.5rem">
    <span>💾</span> Backup Your Data
  </h2>
  <p style="color:#94a3b8;font-size:.85rem;line-height:1.55;margin-bottom:1.25rem">
    Download everything (website content + all records: services, inquiries, appointments…) as one JSON file. Keep it safe — restore anytime by re-uploading.
  </p>
  <form method="POST">
    <input type="hidden" name="action" value="backup">
    <?= admin_csrf_field() ?>
    <button type="submit" class="btn btn-success" style="width:100%">
      ⬇️ Download Full Backup
    </button>
  </form>
</div>

<?php renderAdminFooter(); ?>
PHP;
    }

    /**
     * Code for messages.php (Customer Inbox — contact-form inquiries)
     */
    private static function getMessagesPhpCode(string $siteName): string {
        return <<<'PHP'
<?php
require_once __DIR__ . '/layout.php';

$inboxFile = __DIR__ . '/data_inquiries.json';
$inbox = file_exists($inboxFile) ? (json_decode(@file_get_contents($inboxFile), true) ?: []) : [];
if (!is_array($inbox)) $inbox = [];

$notice = '';
// Actions: mark read / delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf();
    $act = $_POST['op'] ?? '';
    $id  = (string)($_POST['id'] ?? '');
    if ($act === 'read' || $act === 'unread' || $act === 'delete') {
        foreach ($inbox as $k => $row) {
            if ((string)($row['id'] ?? '') === $id) {
                if ($act === 'delete') unset($inbox[$k]);
                else $inbox[$k]['read'] = ($act === 'read');
                break;
            }
        }
        $inbox = array_values($inbox);
        @file_put_contents($inboxFile, json_encode($inbox, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        $notice = $act === 'delete' ? 'Message deleted.' : 'Message updated.';
    } elseif ($act === 'mark_all_read') {
        foreach ($inbox as $k => $row) $inbox[$k]['read'] = true;
        @file_put_contents($inboxFile, json_encode($inbox, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $notice = 'All messages marked as read.';
    }
}

$unread = 0;
foreach ($inbox as $r) { if (empty($r['read'])) $unread++; }
renderAdminHeader('messages', 'Customer Inbox');
?>
<div class="top-actions">
  <div>
    <h1 class="page-title">✉️ Customer Inbox <?= $unread > 0 ? '<span class="badge badge-green">' . $unread . ' new</span>' : '' ?></h1>
    <p style="color:#64748b;font-size:.88rem;margin-top:.3rem">Every contact-form message from your live website lands here. Reply straight from your email.</p>
  </div>
  <?php if (!empty($inbox)): ?>
  <form method="POST"><input type="hidden" name="op" value="mark_all_read">
    <?= admin_csrf_field() ?>
    <button type="submit" class="btn btn-ghost">✓ Mark all read</button>
  </form>
  <?php endif; ?>
</div>

<?php if ($notice): ?>
  <div style="background:rgba(16,185,129,.15);border:1px solid #10b981;color:#34d399;padding:.9rem 1.2rem;border-radius:12px;font-weight:700;margin-bottom:1.5rem">✅ <?= htmlspecialchars($notice) ?></div>
<?php endif; ?>

<?php if (empty($inbox)): ?>
  <div class="card" style="text-align:center;padding:3rem 1.5rem">
    <div style="font-size:2.5rem;margin-bottom:.75rem">📭</div>
    <h3 style="color:#fff;font-weight:800;margin-bottom:.4rem">No messages yet</h3>
    <p style="color:#94a3b8;font-size:.88rem">When visitors submit your website contact form, their messages appear here instantly.</p>
  </div>
<?php else: ?>
  <?php foreach ($inbox as $row):
    $rid = htmlspecialchars((string)($row['id'] ?? ''));
    $rname = htmlspecialchars($row['name'] ?? 'Visitor');
    $remail = htmlspecialchars($row['email'] ?? '');
    $rphone = htmlspecialchars($row['phone'] ?? '—');
    $rmsg = nl2br(htmlspecialchars($row['message'] ?? ''));
    $rdate = htmlspecialchars($row['date'] ?? '');
    $isNew = empty($row['read']);
  ?>
  <div class="card" style="<?= $isNew ? 'border-color:#f59e0b;background:rgba(245,158,11,.05)' : '' ?>">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem;margin-bottom:.75rem">
      <div style="display:flex;align-items:center;gap:.7rem">
        <div style="width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#a855f7);display:flex;align-items:center;justify-content:center;font-weight:800;color:#fff"><?= htmlspecialchars(mb_strtoupper(mb_substr($rname, 0, 1))) ?></div>
        <div>
          <strong style="color:#fff"><?= $rname ?></strong>
          <?= $isNew ? ' <span class="badge" style="background:#f59e0b;color:#000">NEW</span>' : '' ?>
          <div style="font-size:.78rem;color:#64748b"><?= $remail ?> · <?= $rphone ?> · <?= $rdate ?></div>
        </div>
      </div>
      <div style="display:flex;gap:.4rem;flex-wrap:wrap">
        <a class="btn btn-success" style="padding:.45rem .9rem;font-size:.78rem" href="mailto:<?= $remail ?>?subject=<?= rawurlencode('Re: Your enquiry') ?>">↩ Reply</a>
        <form method="POST" style="display:inline">
          <?= admin_csrf_field() ?>
          <input type="hidden" name="id" value="<?= $rid ?>">
          <?php if ($isNew): ?>
            <button type="submit" name="op" value="read" class="btn btn-ghost" style="padding:.45rem .9rem;font-size:.78rem">✓ Read</button>
          <?php else: ?>
            <button type="submit" name="op" value="unread" class="btn btn-ghost" style="padding:.45rem .9rem;font-size:.78rem">Unread</button>
          <?php endif; ?>
          <button type="submit" name="op" value="delete" class="btn btn-danger" style="padding:.45rem .9rem;font-size:.78rem" onclick="return confirm('Delete this message?')">Delete</button>
        </form>
      </div>
    </div>
    <div style="background:#0b0f17;border:1px solid #1e293b;border-radius:10px;padding:1rem;font-size:.88rem;color:#cbd5e1;line-height:1.6;white-space:pre-wrap;"><?= $rmsg ?></div>
  </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php renderAdminFooter(); ?>
PHP;
    }

    /**
     * Code for features.php (AI Feature Adder & Requirement Builder)
     * Real workflow: Analyze (Node) → review findings → Install module.
     * No fake success: every state (analyzing/empty/applied/offline) is shown.
     */
    private static function getFeaturesPhpCode(string $siteName, string $orderId, string $slug = '', string $nodeApi = ''): string {
        $safeSite = htmlspecialchars($siteName, ENT_QUOTES);
        $safeOid  = htmlspecialchars($orderId, ENT_QUOTES);
        $safeSlug = htmlspecialchars($slug !== '' ? $slug : 'site', ENT_QUOTES);
        $safeNode = htmlspecialchars($nodeApi !== '' ? $nodeApi : 'http://localhost:3100', ENT_QUOTES);

        return <<<PHP
<?php
require_once __DIR__ . '/layout.php';
renderAdminHeader('🤖 Add Functions (AI Feature Builder)', 'features', '\$safeSite');
// SSO bridge: this page already verified the admin session (requireAdmin).
// Mint a 5-minute HMAC handoff so the JS can trade it for a Node Bearer
// token — no password handling, no secrets in markup beyond the window.
\$__bridgeTs = (string)time();
\$__bridgeSig = '';
\$__bridgeSlug = basename(dirname(__FILE__));
try {
    \$__secFile = dirname(__FILE__, 3) . '/storage/sites/' . preg_replace('/[^a-z0-9\-]/i', '', \$__bridgeSlug) . '/node_secret';
    if (is_file(\$__secFile)) {
        \$__secret = trim((string)@file_get_contents(\$__secFile));
        if (strlen(\$__secret) >= 32) \$__bridgeSig = hash_hmac('sha256', \$__bridgeTs . ':' . \$__bridgeSlug, \$__secret);
    }
} catch (Throwable \$__e) {}
?>

<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem">
  <div>
    <div style="display:inline-block;padding:0.25rem 0.75rem;background:rgba(99,102,241,0.15);border:1px solid rgba(99,102,241,0.4);border-radius:999px;font-size:0.72rem;font-weight:800;color:#c7d2fe;margin-bottom:0.4rem;">
      ✦ AI-FLOWCRAFT 28-SKILL FULLSTACK ENGINE &amp; TASTESKILL UI
    </div>
    <h1 class="page-title">Add Functions &amp; Full-Stack Module Builder 🤖</h1>
    <p style="color:#94a3b8;font-size:.88rem">Powered by AI-FlowCraft (Skills 1-28: Data Model, MySQL DDL, REST API &amp; TDD) and TasteSkill UI Design for $safeSite.</p>
  </div>
  <?php if ('$safeOid'): ?>
  <a href="../../site-manager.php?order_id=$safeOid#ai-adder" target="_blank" class="btn btn-primary">
    <span>✨ Open Advanced AI Studio &rarr;</span>
  </a>
  <?php endif; ?>
</div>

<div class="card" style="border-color:rgba(99,102,241,0.35);background:linear-gradient(135deg,rgba(99,102,241,0.08),rgba(15,23,42,0.8));">
  <div style="font-size:.82rem;font-weight:700;color:#cbd5e1;margin-bottom:.5rem">Quick Presets (Click to Load):</div>
  <div style="margin-bottom:1rem">
    <button type="button" class="btn btn-ghost" style="padding:.4rem .8rem;font-size:.78rem;margin:0 .3rem .3rem 0" onclick="usePreset('Add an Appointment Booking system with Customer Name, Phone, Email, Service Type, Booking Date, Time Slot, and Status.')">📅 Appointment Booking</button>
    <button type="button" class="btn btn-ghost" style="padding:.4rem .8rem;font-size:.78rem;margin:0 .3rem .3rem 0" onclick="usePreset('Add an Employee / Team Directory with Full Name, Role / Position, Phone, Bio, and Photo.')">👥 Staff / Team Management</button>
    <button type="button" class="btn btn-ghost" style="padding:.4rem .8rem;font-size:.78rem;margin:0 .3rem .3rem 0" onclick="usePreset('Add a Customer Reviews &amp; Testimonials module with Client Name, Company, Rating (1-5 stars), Comment, and Status.')">⭐ Reviews &amp; Ratings</button>
    <button type="button" class="btn btn-ghost" style="padding:.4rem .8rem;font-size:.78rem;margin:0 .3rem .3rem 0" onclick="usePreset('Add a Blog &amp; News Publishing section with Article Title, Featured Image, Category, Content Body, and Publish Date.')">📰 Blog / News Articles</button>
  </div>

  <div class="field">
    <label class="lbl">Describe The Function / Business Process to Add</label>
    <textarea id="feature-req-input" rows="4" class="inp" placeholder="e.g. Add an Appointment Booking system with Customer Name, Phone, Email, Date, Time, and Status..."></textarea>
  </div>

  <div class="field">
    <label class="lbl">Record fields (comma-separated, optional — e.g. holder, tier, expires)</label>
    <input id="feature-fields-input" class="inp" placeholder="title, details">
  </div>

  <div id="feat-result" style="display:none;border:1.5px solid #334155;border-radius:10px;padding:.8rem 1rem;margin-bottom:1rem;font-size:.84rem;color:#e2e8f0"></div>

  <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap">
    <button type="button" class="btn btn-ghost" id="feat-analyze-btn" onclick="analyzeFeatures()">
      <span>🔍 Analyze Missing Functions</span>
    </button>
    <button type="button" class="btn btn-success" onclick="installFeature()">
      <span>✨ Generate &amp; Install Module</span>
    </button>
    <?php if ('$safeOid'): ?>
    <a href="../../site-manager.php?order_id=$safeOid#ai-adder" target="_blank" style="color:#a5b4fc;font-size:.82rem;text-decoration:none">
      Or use your Website Manager AI Feature Adder &rarr;
    </a>
    <?php endif; ?>
  </div>
</div>

<div id="feat-findings" style="margin-top:1rem"></div>

<div class="card">
  <h3 style="color:#fff;font-size:1.05rem;margin-bottom:1rem">📦 Installed Business Modules</h3>
  <table>
    <thead><tr><th>Module Name</th><th>Type</th><th>Storage</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <tr><td><strong>Content Management</strong></td><td>Core CMS</td><td>JSON Storage</td><td><span class="badge badge-green">Active</span></td><td><a href="content.php" style="color:#818cf8;font-weight:700;text-decoration:none">Edit</a></td></tr>
      <tr><td><strong>Dynamic Records Manager</strong></td><td>Entities CRUD</td><td>JSON Storage</td><td><span class="badge badge-green">Active</span></td><td><a href="manage.php" style="color:#818cf8;font-weight:700;text-decoration:none">Edit</a></td></tr>
      <tr><td><strong>Admin Authentication</strong></td><td>Security &amp; Passwords</td><td>Bcrypt Hash</td><td><span class="badge badge-green">Active</span></td><td><a href="settings.php" style="color:#818cf8;font-weight:700;text-decoration:none">Edit</a></td></tr>
    </tbody>
  </table>
</div>

<script>
var WC_NODE_API = '$safeNode';
var WC_SLUG = '$safeSlug';
var WC_BRIDGE = { ts: '<?= \$__bridgeTs ?>', sig: '<?= \$__bridgeSig ?>' };
// Trade the PHP-minted HMAC handoff for a Node Bearer token (5-min window).
// Falls back to manual Node login only if the bridge is unavailable.
async function wcNodeToken() {
  let t = null;
  try { t = sessionStorage.getItem('wc_node_token'); } catch (e) {}
  if (t) return t;
  try {
    const r = await fetch(WC_NODE_API + '/node-api/' + encodeURIComponent(WC_SLUG) + '/auth/bridge', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ts: WC_BRIDGE.ts, sig: WC_BRIDGE.sig })
    });
    const j = await r.json();
    if (j && j.success && j.data && j.data.token) {
      try { sessionStorage.setItem('wc_node_token', j.data.token); } catch (e) {}
      return j.data.token;
    }
  } catch (e) {}
  return null;
}
function usePreset(t) {
  const el = document.getElementById('feature-req-input');
  if (el) { el.value = t; el.focus(); }
}
function featMsg(html, ok) {
  let box = document.getElementById('feat-result');
  if (!box) return;
  box.style.display = 'block';
  box.style.borderColor = ok ? '#10b981' : '#ef4444';
  box.innerHTML = html;
}
function escH(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

// Step 1: Analyze — the Node engine inspects THIS business (manifest +
// live site HTML) and reports genuinely missing functions with safe plans.
async function analyzeFeatures() {
  const out = document.getElementById('feat-findings');
  const btn = document.getElementById('feat-analyze-btn');
  out.innerHTML = '<p style="color:#94a3b8">🔍 Analyzing your website vs business requirements…</p>';
  if (btn) btn.disabled = true;
  try {
    let siteHtml = '';
    try {
      const r = await fetch('../index.html');
      if (r.ok) siteHtml = (await r.text()).slice(0, 120000);
    } catch (e) {}
    const res = await fetch(WC_NODE_API + '/node-api/' + encodeURIComponent(WC_SLUG) + '/analyze', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ html: siteHtml, business: '$safeSite' })
    });
    const j = await res.json();
    if (!j || !j.success) throw new Error((j && j.error) || 'analyze failed');
    const f = j.data.findings || [];
    if (!f.length) {
      out.innerHTML = '<div class="card" style="border-color:#10b981">✅ No missing functions found — frontend, backend and admin are in sync.</div>';
    } else {
      out.innerHTML = '<div class="card"><h3 style="color:#fff;margin-bottom:.75rem">🔍 Missing / incomplete functions (' + f.length + ')</h3>'
        + f.map(function (x, i) {
          return '<div style="padding:.6rem 0;border-bottom:1px solid #1e293b;font-size:.84rem">'
            + '<strong style="color:#fff">' + escH(x.area) + '</strong> '
            + '<span class="badge" style="background:' + (x.severity === 'high' ? '#7f1d1d' : '#1e1b4b') + ';color:' + (x.severity === 'high' ? '#fca5a5' : '#a5b4fc') + '">' + escH(x.severity) + '</span><br>'
            + '<span style="color:#94a3b8">' + escH(x.detail) + '</span>'
            + (x.plan && x.plan !== 'manual'
              ? '<br><button type="button" class="btn btn-ghost" style="margin-top:.4rem;padding:.35rem .8rem;font-size:.76rem" onclick="applyPlan(' + i + ')">⚙️ Auto-fix: ' + escH(x.plan) + '</button>'
              : '<br><span style="color:#64748b;font-size:.76rem">Needs content decisions — describe it below and install as a module.</span>')
            + '</div>';
        }).join('') + '</div>';
      window.__featFindings = f;
    }
    featMsg('Analysis complete: ' + f.length + ' finding(s).', true);
  } catch (e) {
    out.innerHTML = '<div class="card" style="border-color:#ef4444">⚠️ Analyzer unreachable — is the Node backend running? '
      + '(<code>node server/server.js</code>). Your existing admin functions keep working; nothing was faked.</div>';
    featMsg('Node backend offline — start it to use AI analysis.', false);
  } finally {
    if (btn) btn.disabled = false;
  }
}

// Step 2: Install a real module (entity + data file + audit + backup).
// Modules appear in Manage Data immediately via live-module discovery.
async function installFeature() {
  const req = document.getElementById('feature-req-input').value.trim();
  const fieldsRaw = (document.getElementById('feature-fields-input') || {}).value || '';
  if (!req) { featMsg('⚠️ Describe what you want to add first (or run Analyze).', false); return; }
  if (!confirm('Install "' + req.slice(0, 60) + '" as a new admin module? A backup is taken first.')) return;
  featMsg('⏳ Installing module… (backup first, then files)', true);
  try {
    const token = await wcNodeToken();
    const res = await fetch(WC_NODE_API + '/node-api/' + encodeURIComponent(WC_SLUG) + '/features/apply', {
      method: 'POST', headers: { 'Content-Type': 'application/json', ...(token ? { Authorization: 'Bearer ' + token } : {}) },
      body: JSON.stringify({ module: { name: req.slice(0, 60),
        fields: fieldsRaw.split(',').map(s => s.trim()).filter(Boolean).slice(0, 12) } })
    });
    const j = await res.json();
    if (!j || !j.success) throw new Error((j && j.error) || 'install failed');
    featMsg('✅ Installed <strong>' + escH(j.data.name) + '</strong> — open <a href="manage.php?entity=' + encodeURIComponent(j.data.id) + '" style="color:#818cf8">Manage Data</a> to use it.', true);
    loadInstalledFeatures();
  } catch (e) {
    featMsg('❌ Install failed: ' + escH(e.message) + (String(e.message).includes('Failed to fetch') ? ' — is the Node backend running?' : ''), false);
  }
}

async function loadInstalledFeatures() {
  try {
    const token = await wcNodeToken();
    const res = await fetch(WC_NODE_API + '/node-api/' + encodeURIComponent(WC_SLUG) + '/features', {
      headers: token ? { Authorization: 'Bearer ' + token } : {}
    });
    const j = await res.json();
    const tb = document.querySelector('#features-table tbody');
    if (tb && j && j.success) {
      (j.data || []).forEach(function (m) {
        if (document.getElementById('featmod-' + m.id)) return;
        const tr = document.createElement('tr');
        tr.id = 'featmod-' + m.id;
        tr.innerHTML = '<td><strong>' + escH(m.name) + '</strong></td><td>AI Module</td><td>JSON Storage</td>'
          + '<td><span class="badge badge-green">Active</span></td>'
          + '<td><a href="manage.php?entity=' + encodeURIComponent(m.id) + '" style="color:#818cf8;font-weight:700;text-decoration:none">Open</a></td>';
        tb.appendChild(tr);
      });
    }
  } catch (e) {}
}
loadInstalledFeatures();
</script>

<?php renderAdminFooter(); ?>
PHP;
    }
}
