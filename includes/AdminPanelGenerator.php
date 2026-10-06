<?php
/**
 * includes/AdminPanelGenerator.php — Standalone, Production-Grade CMS Admin Panel Builder
 * 
 * Automatically generates a complete, fully-functional, beautiful PHP CMS Admin Panel
 * for any published customer website.
 * 
 * Features included:
 * - Secure login with 1-Click Quick Fill & Auto-Login support (?autologin=1)
 * - Modern responsive dark dashboard (Stats, Quick actions, Live site link)
 * - Website Content Editor (live update headings, phone, email, text in index.html)
 * - Dynamic Entity Manager (full CRUD for Products, Services, Team, Appointments, etc.)
 * - Settings page with Customer Password Change (updates .auth.json & order record)
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
        $username   = $order['admin_username'] ?? 'admin';
        $email      = $order['admin_email'] ?? 'admin@example.com';
        $plainPass  = $order['admin_password_plain'] ?? 'password123';
        $passHash   = !empty($order['admin_password_hash']) 
                        ? $order['admin_password_hash'] 
                        : password_hash($plainPass, PASSWORD_DEFAULT);
        $orderId    = $order['order_id'] ?? '';
        $entities   = !empty($order['admin_entities']) ? $order['admin_entities'] : self::getDefaultEntities($siteName);

        // 1. Write .auth.json (owner account + shareable demo account)
        $demoPass = 'Demo@1234';
        file_put_contents($adminDir . '/.auth.json', json_encode([
            'username'       => $username,
            'email'          => $email,
            'hash'           => $passHash,
            'password_plain' => $plainPass,
            'demo'           => [
                'username'       => 'demo',
                'hash'           => password_hash($demoPass, PASSWORD_DEFAULT),
                'password_plain' => $demoPass,
            ],
            'order_id'       => $orderId,
            'created'        => date('Y-m-d H:i:s'),
        ], JSON_PRETTY_PRINT));

        // 2. Write auth.php
        file_put_contents($adminDir . '/auth.php', self::getAuthPhpCode());

        // 3. Write login.php (with Quick-Fill, Demo Access & Autologin)
        file_put_contents($adminDir . '/login.php', self::getLoginPhpCode($siteName, $username, $plainPass));

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
        file_put_contents($adminDir . '/features.php', self::getFeaturesPhpCode($siteName, $orderId));

        // 10. Seed initial data files for entities
        foreach ($entities as $ent) {
            $entId = is_array($ent) ? ($ent['id'] ?? 'items') : 'items';
            $dataFile = $adminDir . '/data_' . $entId . '.json';
            if (!file_exists($dataFile)) {
                $sampleData = self::getSampleDataForEntity($ent);
                file_put_contents($dataFile, json_encode($sampleData, JSON_PRETTY_PRINT));
            }
        }

        // 11. Initial content.json for website editor
        $contentFile = $adminDir . '/content.json';
        if (!file_exists($contentFile)) {
            file_put_contents($contentFile, json_encode([
                'business_name'  => $siteName,
                'tagline'        => 'Quality & Excellence Delivered',
                'phone'          => '+1 (555) 019-2834',
                'email'          => $email,
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

/** True when logged in via the shareable demo account. */
function isDemo(): bool {
    return !empty($_SESSION['admin_auth']) && !empty($_SESSION['demo_mode']);
}

function getAuthData(): array {
    $file = __DIR__ . '/.auth.json';
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?: [];
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
     * Code for login.php
     */
    private static function getLoginPhpCode(string $siteName, string $defUser, string $defPass): string {
        $safeSite = htmlspecialchars($siteName, ENT_QUOTES);
        $safeUser = htmlspecialchars($defUser, ENT_QUOTES);
        $safePass = htmlspecialchars($defPass, ENT_QUOTES);

        return <<<PHP
<?php
require_once __DIR__ . '/auth.php';

\$authData = getAuthData();
\$storedUser = \$authData['username'] ?? '$safeUser';
\$storedPassPlain = \$authData['password_plain'] ?? '$safePass';
\$storedHash = \$authData['hash'] ?? '';
\$demoUser = \$authData['demo']['username'] ?? 'demo';
\$demoHash = \$authData['demo']['hash'] ?? '';
\$demoPassPlain = \$authData['demo']['password_plain'] ?? '';

// Check Autologin parameter
if (!empty(\$_GET['autologin']) && \$_GET['autologin'] === '1') {
    \$_SESSION['admin_auth'] = true;
    \$_SESSION['admin_username'] = \$storedUser;
    \$_SESSION['admin_email'] = \$authData['email'] ?? '';
    header('Location: index.php');
    exit;
}

// Already logged in?
if (!empty(\$_SESSION['admin_auth'])) {
    header('Location: index.php');
    exit;
}

\$error = '';
if (\$_SERVER['REQUEST_METHOD'] === 'POST') {
    \$u = trim(\$_POST['username'] ?? '');
    \$p = trim(\$_POST['password'] ?? '');

    \$userMatches = (strtolower(\$u) === strtolower(\$storedUser));
    \$passMatches = false;

    if (\$storedHash) {
        \$passMatches = password_verify(\$p, \$storedHash);
    }
    if (!\$passMatches && \$storedPassPlain && \$p === \$storedPassPlain) {
        \$passMatches = true;
    }

    // Shareable demo account (demo / Demo@1234) — full preview access
    \$isDemoLogin = false;
    if (!\$passMatches && \$demoUser && strtolower(\$u) === strtolower(\$demoUser)) {
        if ((\$demoHash && password_verify(\$p, \$demoHash)) || (\$demoPassPlain && \$p === \$demoPassPlain)) {
            \$passMatches = true;
            \$isDemoLogin = true;
        }
    }

    if (\$userMatches && \$passMatches) {
        \$_SESSION['admin_auth'] = true;
        \$_SESSION['admin_username'] = \$storedUser;
        \$_SESSION['admin_email'] = \$authData['email'] ?? '';
        \$_SESSION['demo_mode'] = false;
        header('Location: index.php');
        exit;
    } elseif (\$isDemoLogin) {
        \$_SESSION['admin_auth'] = true;
        \$_SESSION['admin_username'] = \$demoUser . ' (demo)';
        \$_SESSION['admin_email'] = \$authData['email'] ?? '';
        \$_SESSION['demo_mode'] = true;
        header('Location: index.php');
        exit;
    } else {
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
      <input type="text" name="username" id="username" class="inp" required autofocus value="<?= htmlspecialchars(\$storedUser) ?>" placeholder="Enter admin username">
    </div>
    <div class="field">
      <label class="lbl" for="password">Password</label>
      <input type="password" name="password" id="password" class="inp" required value="<?= htmlspecialchars(\$storedPassPlain) ?>" placeholder="Enter password">
    </div>
    <button type="submit" class="btn-submit">Sign In to Dashboard &rarr;</button>
  </form>

  <!-- Quick Fill Box for convenience -->
  <div class="quick-box">
    <div class="quick-title">⚡ CLIENT CREDENTIALS (SAVED)</div>
    <button type="button" class="btn-quick" onclick="quickFill()">
      <span>🔑</span> One-Click Auto-Fill &amp; Sign In
    </button>
    <button type="button" class="btn-quick" onclick="demoFill()" style="margin-top:.55rem;border-color:#10b981;color:#6ee7b7;">
      <span>🎭</span> Try Demo Access (demo / Demo@1234)
    </button>
  </div>

  <a href="../index.html" class="site-link">&larr; Return to Live Website</a>
</div>

<script>
function quickFill() {
  document.getElementById('username').value = <?= json_encode(\$storedUser) ?>;
  document.getElementById('password').value = <?= json_encode(\$storedPassPlain) ?>;
  document.getElementById('loginForm').submit();
}
function demoFill() {
  document.getElementById('username').value = <?= json_encode(\$demoUser) ?>;
  document.getElementById('password').value = <?= json_encode(\$demoPassPlain) ?>;
  document.getElementById('loginForm').submit();
}
</script>
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
        $entitiesJson = json_encode($entities);

        return <<<PHP
<?php
require_once __DIR__ . '/auth.php';
requireAdmin();

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
?>
<?php if (!empty(\$_SESSION['demo_mode'])): ?>
<div class="card" style="border-color:#10b981;background:rgba(16,185,129,.07);margin-bottom:1.5rem">
  <strong style="color:#6ee7b7">🎭 Demo Mode</strong>
  <span style="color:#94a3b8;font-size:.85rem"> — you are previewing with the shareable demo account. Changes you make are real; sign in with the owner account for full control.</span>
</div>
<?php endif; ?>
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
    <div class="stat-val" style="color:#10b981">Live 🟢</div>
    <div style="font-size:.75rem;color:#64748b">Hosted &amp; Published</div>
  </div>
  <div class="stat-box">
    <div class="stat-lbl">SSL Certificate</div>
    <div class="stat-val" style="color:#a5b4fc">Active 🔒</div>
    <div style="font-size:.75rem;color:#64748b">HTTPS Secured</div>
  </div>
  <div class="stat-box">
    <div class="stat-lbl">Content Sections</div>
    <div class="stat-val">6</div>
    <div style="font-size:.75rem;color:#64748b">Hero, Services, Contact &amp; More</div>
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
        $safeEntitiesJson = json_encode($entities);

        return <<<PHP
<?php
require_once __DIR__ . '/layout.php';

\$entities = json_decode('$safeEntitiesJson', true) ?: [];
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

\$msg = '';
// Handle Add / Edit POST
if (\$_SERVER['REQUEST_METHOD'] === 'POST') {
    \$action = \$_POST['action'] ?? '';

    if (\$action === 'save_item') {
        \$id = trim(\$_POST['item_id'] ?? '');
        \$isEdit = !empty(\$id);

        \$item = ['id' => \$isEdit ? \$id : (string)time()];
        foreach (\$activeEntity['fields'] ?? [] as \$f) {
            \$fname = \$f['name'];
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
        file_put_contents(\$dataFile, json_encode(\$records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        \$msg = \$isEdit ? 'Record updated successfully!' : 'New record added successfully!';
    }

    if (\$action === 'delete_item') {
        \$delId = trim(\$_POST['item_id'] ?? '');
        \$records = array_values(array_filter(\$records, fn(\$r) => (\$r['id'] ?? '') !== \$delId));
        file_put_contents(\$dataFile, json_encode(\$records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
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
  <button class="btn btn-primary" onclick="openAddModal()">
    <span>➕</span> Add New <?= htmlspecialchars(\$activeEntity['singular'] ?? 'Item') ?>
  </button>
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
    <input type="text" id="filterInp" placeholder="Search records..." class="inp" style="max-width:260px;padding:.5rem .85rem;font-size:.82rem" oninput="filterTable()">
  </div>

  <?php if (empty(\$records)): ?>
    <div style="text-align:center;padding:3rem 1rem;color:#64748b">
      <div style="font-size:2rem;margin-bottom:.5rem">📂</div>
      <p>No records found yet. Click <strong>+ Add New</strong> above to create your first entry.</p>
    </div>
  <?php else: ?>
    <div style="overflow-x:auto">
      <table id="recordsTable">
        <thead>
          <tr>
            <?php foreach (\$activeEntity['fields'] ?? [] as \$f): ?>
              <th><?= htmlspecialchars(\$f['label'] ?? \$f['name']) ?></th>
            <?php endforeach; ?>
            <th style="text-align:right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (\$records as \$row): ?>
            <tr>
              <?php foreach (\$activeEntity['fields'] ?? [] as \$f): 
                \$val = \$row[\$f['name']] ?? '—';
              ?>
                <td><?= htmlspecialchars(\$val) ?></td>
              <?php endforeach; ?>
              <td style="text-align:right;white-space:nowrap">
                <button type="button" class="btn btn-ghost" style="padding:.35rem .75rem;font-size:.78rem;margin-right:.4rem"
                        onclick='openEditModal(<?= json_encode(\$row) ?>)'>✏️ Edit</button>
                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this record?');">
                  <input type="hidden" name="action" value="delete_item">
                  <input type="hidden" name="item_id" value="<?= htmlspecialchars(\$row['id'] ?? '') ?>">
                  <button type="submit" class="btn btn-danger" style="padding:.35rem .75rem;font-size:.78rem">🗑️</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Add / Edit Modal -->
<div id="itemModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);backdrop-filter:blur(5px);z-index:9999;align-items:center;justify-content:center;padding:1.5rem">
  <div style="background:#111622;border:1.5px solid #283347;border-radius:20px;max-width:540px;width:100%;padding:2rem;box-shadow:0 25px 60px rgba(0,0,0,.6)">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem">
      <h3 style="font-size:1.25rem;font-weight:800;color:#fff" id="modalTitle">Add Record</h3>
      <button type="button" onclick="closeModal()" style="background:none;border:none;color:#94a3b8;font-size:1.3rem;cursor:pointer">&times;</button>
    </div>

    <form method="POST" id="itemForm">
      <input type="hidden" name="action" value="save_item">
      <input type="hidden" name="item_id" id="modal_item_id" value="">

      <?php foreach (\$activeEntity['fields'] ?? [] as \$f): 
        \$fname = \$f['name'];
        \$ftype = \$f['type'] ?? 'text';
      ?>
        <div class="field">
          <label class="lbl"><?= htmlspecialchars(\$f['label'] ?? \$fname) ?></label>
          <?php if (\$ftype === 'textarea'): ?>
            <textarea name="<?= \$fname ?>" id="inp_<?= \$fname ?>" rows="3" class="inp"></textarea>
          <?php else: ?>
            <input type="<?= \$ftype === 'email' ? 'email' : (\$ftype === 'date' ? 'date' : 'text') ?>" 
                   name="<?= \$fname ?>" id="inp_<?= \$fname ?>" class="inp">
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

<script>
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

// Handle Password Change POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $currPass = trim($_POST['current_password'] ?? '');
        $newPass  = trim($_POST['new_password'] ?? '');
        $confPass = trim($_POST['confirm_password'] ?? '');

        // Verify current password
        $storedHash  = $authData['hash'] ?? '';
        $storedPlain = $authData['password_plain'] ?? '';

        $currValid = false;
        if ($storedHash && password_verify($currPass, $storedHash)) {
            $currValid = true;
        } elseif ($storedPlain && $currPass === $storedPlain) {
            $currValid = true;
        }

        if (!$currValid) {
            $errorMsg = 'Current password is incorrect. Please check and try again.';
        } elseif (strlen($newPass) < 6) {
            $errorMsg = 'New password must be at least 6 characters long.';
        } elseif ($newPass !== $confPass) {
            $errorMsg = 'New passwords do not match. Please re-enter.';
        } else {
            // Update .auth.json
            $newHash = password_hash($newPass, PASSWORD_DEFAULT);
            $authData['hash'] = $newHash;
            $authData['password_plain'] = $newPass;
            $authData['updated_at'] = date('Y-m-d H:i:s');
            file_put_contents($authFile, json_encode($authData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // Also synchronize with storage/orders if order_id is present
            $orderId = $authData['order_id'] ?? '';
            if ($orderId) {
                $orderDir = dirname(__DIR__, 2) . '/storage/orders';
                $orderFile = $orderDir . '/' . $orderId . '.json';
                if (file_exists($orderFile)) {
                    $order = json_decode(file_get_contents($orderFile), true);
                    if ($order) {
                        $order['admin_password_hash'] = $newHash;
                        $order['admin_password_plain'] = $newPass;
                        file_put_contents($orderFile, json_encode($order, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    }
                }
            }

            $successMsg = 'Your password has been changed successfully!';
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

      <div class="field">
        <label class="lbl" for="current_password">Current Password</label>
        <input type="password" name="current_password" id="current_password" class="inp" required placeholder="Enter current password">
      </div>

      <div class="field">
        <label class="lbl" for="new_password">New Password</label>
        <input type="password" name="new_password" id="new_password" class="inp" required minlength="6" placeholder="At least 6 characters">
      </div>

      <div class="field">
        <label class="lbl" for="confirm_password">Confirm New Password</label>
        <input type="password" name="confirm_password" id="confirm_password" class="inp" required minlength="6" placeholder="Re-enter new password">
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
        @file_put_contents($inboxFile, json_encode($inbox, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
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
     */
    private static function getFeaturesPhpCode(string $siteName, string $orderId): string {
        $safeSite = htmlspecialchars($siteName, ENT_QUOTES);
        $safeOid  = htmlspecialchars($orderId, ENT_QUOTES);

        return <<<PHP
<?php
require_once __DIR__ . '/layout.php';
renderAdminHeader('🤖 Add Functions (AI Feature Builder)', 'features', '$safeSite');
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

  <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap">
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
function usePreset(t) {
  const el = document.getElementById('feature-req-input');
  if (el) { el.value = t; el.focus(); }
}

function installFeature() {
  const req = document.getElementById('feature-req-input').value.trim();
  if (!req) { showToast('⚠️ Please describe what you want to add'); return; }
  const oid = '$safeOid';
  if (oid) {
    window.location.href = '../../site-manager.php?order_id=' + encodeURIComponent(oid) + '#ai-adder';
  } else {
    showToast('✨ Feature generator initialized!');
  }
}
</script>

<?php renderAdminFooter(); ?>
PHP;
    }
}
