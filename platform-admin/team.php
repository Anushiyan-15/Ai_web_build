<?php
/**
 * platform-admin/team.php
 * Admin Roles & Team Staff Management (Super Admin Only)
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require_permission('*'); // Super Admin only

$accounts = load_admin_accounts();
$action   = $_GET['action'] ?? $_POST['action'] ?? '';

// ─── ACTION: Create New Admin ──────────────────────────────
if ($action === 'create_admin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = strtolower(trim($_POST['username'] ?? ''));
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $role     = trim($_POST['role'] ?? ROLE_SUPPORT);
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        header('Location: team.php?msg=Username+and+password+are+required&type=error');
        exit;
    }

    if (!preg_match('/^[a-z0-9_]{3,20}$/', $username)) {
        header('Location: team.php?msg=Invalid+username+format+(3-20+letters,+numbers,+underscore)&type=error');
        exit;
    }

    $account = [
        'username'   => $username,
        'name'       => $name ?: ucfirst($username),
        'email'      => $email,
        'role'       => $role,
        'password'   => password_hash($password, PASSWORD_DEFAULT),
        'raw_pass'   => $password,
        'active'     => true,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    save_admin_account($account);
    log_admin_action('admin_created', null, "Created new admin user '{$username}' with role '{$role}'");
    header('Location: team.php?msg=Admin+account+created+successfully&type=success');
    exit;
}

// ─── ACTION: Toggle Admin Active Status ────────────────────
if ($action === 'toggle_active' && isset($_GET['username'])) {
    $u = strtolower($_GET['username']);
    if ($u === 'superadmin' || $u === current_admin_user()) {
        header('Location: team.php?msg=Cannot+deactivate+primary+superadmin&type=error');
        exit;
    }

    if (isset($accounts[$u])) {
        $accounts[$u]['active'] = !($accounts[$u]['active'] ?? true);
        save_admin_account($accounts[$u]);
        log_admin_action('admin_status_changed', null, "Changed active state of admin '{$u}'");
        header('Location: team.php?msg=Admin+status+updated&type=success');
        exit;
    }
}

$roleDefs = get_role_definitions();

render_head('Admin Team & Roles');
render_sidebar('team');
?>

<main class="main">
  <div class="page-header">
    <div>
      <h1>👥 Admin Team &amp; Roles Management</h1>
      <p>Configure staff access, assign role-based permissions, and audit platform operators.</p>
    </div>
  </div>

  <?php if (!empty($_GET['msg'])): ?>
    <div class="alert alert-<?= ($_GET['type'] ?? '') === 'error' ? 'error' : 'success' ?>">
      <?= htmlspecialchars($_GET['msg']) ?>
    </div>
  <?php endif; ?>

  <!-- Role Permissions Reference Grid -->
  <div class="grid-4" style="margin-bottom:24px;">
    <?php foreach ($roleDefs as $rk => $r): ?>
      <div class="card" style="border-top:4px solid <?= $r['color'] ?>;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
          <span style="font-size:1.3rem;"><?= $r['icon'] ?></span>
          <strong style="color:#fff;font-size:0.95rem;"><?= htmlspecialchars($r['label']) ?></strong>
        </div>
        <p style="font-size:0.75rem;color:var(--muted);line-height:1.5;margin-bottom:12px;">
          <?= htmlspecialchars($r['description']) ?>
        </p>
        <div style="font-size:0.7rem;color:#cbd5e1;line-height:1.6;border-top:1px dashed var(--border);padding-top:8px;">
          <?php if (in_array('*', $r['permissions'], true)): ?>
            <strong style="color:#818cf8;">✓ Full Unrestricted Access</strong>
          <?php else: ?>
            <div>Allowed Modules:</div>
            <div style="color:var(--muted);"><?= implode(', ', $r['permissions']) ?></div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="grid-3" style="margin-bottom:28px;">
    
    <!-- Admin Staff Accounts Table (2 cols) -->
    <div class="card" style="grid-column:span 2;padding:0;overflow:hidden;">
      <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
        <h2 style="font-size:1.05rem;font-weight:800;color:#fff;">🛡️ Registered Platform Administrators</h2>
        <span style="font-size:0.75rem;color:var(--muted);"><?= count($accounts) ?> Operators</span>
      </div>

      <div style="overflow-x:auto;">
        <table>
          <thead>
            <tr>
              <th>Operator / Username</th>
              <th>Assigned Role</th>
              <th>Email</th>
              <th>Status</th>
              <th style="text-align:right;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($accounts as $acc): 
              $u = $acc['username'] ?? '';
              $isActive = ($acc['active'] ?? true);
            ?>
            <tr>
              <td>
                <div style="font-weight:700;color:#fff;"><?= htmlspecialchars($acc['name'] ?? ucfirst($u)) ?></div>
                <div style="font-size:0.75rem;color:#818cf8;font-family:'Fira Code',monospace;">@<?= htmlspecialchars($u) ?></div>
              </td>
              <td>
                <?= role_badge($acc['role'] ?? ROLE_SUPPORT) ?>
              </td>
              <td style="font-size:0.8rem;color:var(--muted);">
                <?= htmlspecialchars($acc['email'] ?? '—') ?>
              </td>
              <td>
                <?php if ($isActive): ?>
                  <span style="font-size:0.72rem;font-weight:700;color:#10b981;background:rgba(16,185,129,0.15);padding:3px 8px;border-radius:999px;">Active</span>
                <?php else: ?>
                  <span style="font-size:0.72rem;font-weight:700;color:#ef4444;background:rgba(239,68,68,0.15);padding:3px 8px;border-radius:999px;">Disabled</span>
                <?php endif; ?>
              </td>
              <td style="text-align:right;">
                <?php if ($u !== 'superadmin' && $u !== current_admin_user()): ?>
                  <a href="?action=toggle_active&username=<?= urlencode($u) ?>" 
                     class="btn btn-ghost btn-sm" style="<?= $isActive ? 'color:#ef4444;' : 'color:#10b981;' ?>">
                    <?= $isActive ? 'Deactivate' : 'Enable' ?>
                  </a>
                <?php else: ?>
                  <span style="font-size:0.72rem;color:var(--muted);">Protected</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Add Admin Form (1 col) -->
    <div class="card">
      <h3 style="font-size:1.05rem;font-weight:800;color:#fff;margin-bottom:14px;">➕ Add Administrator</h3>
      <p style="font-size:0.8rem;color:var(--muted);line-height:1.5;margin-bottom:16px;">
        Provision a new staff operator and bind their capabilities to an explicit role.
      </p>

      <form method="POST" action="team.php">
        <input type="hidden" name="action" value="create_admin">

        <div class="form-group">
          <label>Username</label>
          <input type="text" name="username" placeholder="e.g. jdoe_support" required>
        </div>

        <div class="form-group">
          <label>Full Display Name</label>
          <input type="text" name="name" placeholder="e.g. John Doe">
        </div>

        <div class="form-group">
          <label>Email Address</label>
          <input type="email" name="email" placeholder="staff@webcraft.ai">
        </div>

        <div class="form-group">
          <label>Assigned Role</label>
          <select name="role" required style="padding:9px;">
            <option value="superadmin">👑 Super Admin (Full Control)</option>
            <option value="support" selected>🎧 Support Admin (Tickets &amp; Tenants)</option>
            <option value="billing">💳 Billing Admin (Invoices &amp; Overdue)</option>
            <option value="developer">🛠️ Developer Admin (Rollback &amp; AI)</option>
          </select>
        </div>

        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" placeholder="Min 8 characters" required>
        </div>

        <div style="margin-top:20px;">
          <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">
            Add Staff Member
          </button>
        </div>
      </form>
    </div>

  </div>
</main>
</div></body></html>
