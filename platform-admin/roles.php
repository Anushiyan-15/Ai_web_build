<?php
/**
 * platform-admin/roles.php
 * Role-Based Access Control (RBAC) & Multi-Admin Engine
 *
 * Supported Roles:
 *  1. Super Admin   (superadmin) : Full platform control
 *  2. Support Admin (support)    : Tenants view, tickets, site previews, notifications
 *  3. Billing Admin (billing)    : Invoices, subscriptions, refunds, plan pricing, overdue auto
 *  4. Developer Admin (developer): Deployments, rollback, AI usage, domains & SSL, audit logs
 */

if (!defined('ROLE_SUPERADMIN')) define('ROLE_SUPERADMIN', 'superadmin');
if (!defined('ROLE_SUPPORT'))    define('ROLE_SUPPORT',    'support');
if (!defined('ROLE_BILLING'))    define('ROLE_BILLING',    'billing');
if (!defined('ROLE_DEVELOPER'))  define('ROLE_DEVELOPER',  'developer');

/**
 * Return all defined roles with labels, colors, and permissions.
 */
function get_role_definitions(): array {
    return [
        ROLE_SUPERADMIN => [
            'label'       => 'Super Admin',
            'description' => 'Full unrestricted system access, team management, and settings.',
            'color'       => '#818cf8',
            'bg'          => 'rgba(99,102,241,0.18)',
            'border'      => 'rgba(99,102,241,0.4)',
            'icon'        => '👑',
            'permissions' => ['*'],
        ],
        ROLE_SUPPORT => [
            'label'       => 'Support Admin',
            'description' => 'User & Tenant viewer, ticket management, client notifications, and site inspect.',
            'color'       => '#34d399',
            'bg'          => 'rgba(16,185,129,0.18)',
            'border'      => 'rgba(16,185,129,0.4)',
            'icon'        => '🎧',
            'permissions' => [
                'dashboard', 'tenants_view', 'customer_view', 'tickets',
                'notifications_send', 'notifications_view', 'sites_preview'
            ],
        ],
        ROLE_BILLING => [
            'label'       => 'Billing Admin',
            'description' => 'Subscriptions, plans, invoices, refunds, and overdue automation.',
            'color'       => '#fbbf24',
            'bg'          => 'rgba(245,158,11,0.18)',
            'border'      => 'rgba(245,158,11,0.4)',
            'icon'        => '💳',
            'permissions' => [
                'dashboard', 'tenants_view', 'customer_view', 'billing',
                'billing_manage', 'invoices', 'refunds', 'plans_edit',
                'notifications_send', 'notifications_view'
            ],
        ],
        ROLE_DEVELOPER => [
            'label'       => 'Developer Admin',
            'description' => 'Deployment logs, instant rollback, domain & SSL tracker, AI tokens & spend.',
            'color'       => '#22d3ee',
            'bg'          => 'rgba(6,182,212,0.18)',
            'border'      => 'rgba(6,182,212,0.4)',
            'icon'        => '🛠️',
            'permissions' => [
                'dashboard', 'tenants_view', 'customer_view', 'deployments',
                'deployments_rollback', 'domains_ssl', 'ai_usage', 'audit_logs',
                'site_toggle', 'sites_preview'
            ],
        ],
    ];
}

/**
 * Path to mutable admin accounts file
 */
function admin_accounts_file(): string {
    $dir = dirname(__DIR__) . '/storage';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir . '/admin_accounts.json';
}

/**
 * Default seeded admin accounts for all 4 roles
 */
function default_admin_accounts(): array {
    return [
        'superadmin' => [
            'username'   => 'superadmin',
            'name'       => 'Master Super Admin',
            'email'      => 'admin@webcraft.ai',
            'role'       => ROLE_SUPERADMIN,
            'password'   => password_hash('WebCraft@2026!', PASSWORD_DEFAULT),
            'raw_pass'   => 'WebCraft@2026!',
            'active'     => true,
            'created_at' => '2026-09-01 00:00:00',
        ],
        'support_admin' => [
            'username'   => 'support_admin',
            'name'       => 'Customer Support Lead',
            'email'      => 'support@webcraft.ai',
            'role'       => ROLE_SUPPORT,
            'password'   => password_hash('Support@2026!', PASSWORD_DEFAULT),
            'raw_pass'   => 'Support@2026!',
            'active'     => true,
            'created_at' => '2026-09-01 00:00:00',
        ],
        'billing_admin' => [
            'username'   => 'billing_admin',
            'name'       => 'Finance & Billing Manager',
            'email'      => 'billing@webcraft.ai',
            'role'       => ROLE_BILLING,
            'password'   => password_hash('Billing@2026!', PASSWORD_DEFAULT),
            'raw_pass'   => 'Billing@2026!',
            'active'     => true,
            'created_at' => '2026-09-01 00:00:00',
        ],
        'dev_admin' => [
            'username'   => 'dev_admin',
            'name'       => 'Lead DevOps & Cloud Engineer',
            'email'      => 'devops@webcraft.ai',
            'role'       => ROLE_DEVELOPER,
            'password'   => password_hash('Developer@2026!', PASSWORD_DEFAULT),
            'raw_pass'   => 'Developer@2026!',
            'active'     => true,
            'created_at' => '2026-09-01 00:00:00',
        ],
    ];
}

/**
 * Load all registered admin accounts (merged with disk)
 */
function load_admin_accounts(): array {
    $file = admin_accounts_file();
    if (!file_exists($file)) {
        $defaults = default_admin_accounts();
        @file_put_contents($file, json_encode($defaults, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $defaults;
    }
    $data = json_decode(@file_get_contents($file), true);
    if (!is_array($data) || empty($data)) {
        return default_admin_accounts();
    }
    return $data;
}

/**
 * Save an admin account
 */
function save_admin_account(array $account): bool {
    $accounts = load_admin_accounts();
    $u = strtolower(trim($account['username'] ?? ''));
    if (!$u) return false;
    $accounts[$u] = $account;
    return (bool) @file_put_contents(admin_accounts_file(), json_encode($accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

/**
 * Find admin account by username
 */
function find_admin_account(string $username): ?array {
    $accounts = load_admin_accounts();
    $u = strtolower(trim($username));
    return $accounts[$u] ?? null;
}

/**
 * Verify credentials for any defined admin account
 */
function authenticate_admin_account(string $username, string $password): ?array {
    $account = find_admin_account($username);
    if (!$account || empty($account['active'])) {
        // Fallback check against default config for legacy superadmin
        if (defined('PLATFORM_ADMIN_USERNAME') && strtolower($username) === strtolower(PLATFORM_ADMIN_USERNAME)) {
            if (defined('PLATFORM_ADMIN_PASSWORD_HASH') && password_verify($password, PLATFORM_ADMIN_PASSWORD_HASH)) {
                return [
                    'username' => PLATFORM_ADMIN_USERNAME,
                    'name'     => 'Super Administrator',
                    'email'    => 'admin@webcraft.ai',
                    'role'     => ROLE_SUPERADMIN,
                ];
            }
            if ($password === 'WebCraft@2026!') {
                return [
                    'username' => PLATFORM_ADMIN_USERNAME,
                    'name'     => 'Super Administrator',
                    'email'    => 'admin@webcraft.ai',
                    'role'     => ROLE_SUPERADMIN,
                ];
            }
        }
        return null;
    }

    $hash = $account['password'] ?? '';
    if (password_verify($password, $hash)) {
        return $account;
    }
    // Check raw fallback if hash verify failed
    if (!empty($account['raw_pass']) && $account['raw_pass'] === $password) {
        return $account;
    }

    return null;
}

/**
 * Get current session user's role
 */
function current_admin_role(): string {
    return $_SESSION['platform_admin_role'] ?? ROLE_SUPERADMIN;
}

/**
 * Get current session user's display name / username
 */
function current_admin_user(): string {
    return $_SESSION['platform_admin_user'] ?? 'superadmin';
}

/**
 * Check if the currently logged-in admin has a specific capability
 */
function has_permission(string $permission): bool {
    $role = current_admin_role();
    $defs = get_role_definitions();
    $perms = $defs[$role]['permissions'] ?? [];
    if (in_array('*', $perms, true)) return true;
    return in_array($permission, $perms, true);
}

/**
 * Block access and show friendly forbidden notice if user lacks permission
 */
function require_permission(string $permission): void {
    if (!has_permission($permission)) {
        $defs = get_role_definitions();
        $roleName = $defs[current_admin_role()]['label'] ?? current_admin_role();
        http_response_code(403);
        require_once __DIR__ . '/helpers.php';
        render_head('Access Denied');
        render_sidebar();
        ?>
        <main class="main">
          <div style="max-width:540px;margin:80px auto;background:#111622;border:1px solid #1e293b;border-radius:20px;padding:48px;text-align:center;">
            <div style="font-size:3.5rem;margin-bottom:16px;">🛑</div>
            <h1 style="font-size:1.6rem;font-weight:800;color:#ef4444;margin-bottom:10px;">Permission Denied</h1>
            <p style="color:#94a3b8;font-size:0.95rem;line-height:1.6;margin-bottom:24px;">
              Your current role (<strong><?= htmlspecialchars($roleName) ?></strong>) does not have permission to access the <code><?= htmlspecialchars($permission) ?></code> module.
            </p>
            <div style="display:flex;gap:10px;justify-content:center;">
              <a href="index.php" class="btn btn-primary">← Return to Dashboard</a>
              <a href="login.php?action=logout" class="btn btn-ghost">Switch Account</a>
            </div>
          </div>
        </main>
        </div></body></html>
        <?php
        exit;
    }
}

/**
 * Render visual badge for any role
 */
function role_badge(string $role): string {
    $defs = get_role_definitions();
    $def  = $defs[$role] ?? $defs[ROLE_SUPERADMIN];
    return sprintf(
        '<span style="display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;font-size:0.75rem;font-weight:700;color:%s;background:%s;border:1px solid %s;">%s %s</span>',
        $def['color'], $def['bg'], $def['border'], $def['icon'], htmlspecialchars($def['label'])
    );
}
