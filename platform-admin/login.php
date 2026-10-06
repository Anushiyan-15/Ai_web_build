<?php
/**
 * Platform Admin - Login Page with Role-Based Authentication
 */
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/platform-admin.php';
require_once __DIR__ . '/roles.php';
require_once __DIR__ . '/auth.php';

// Already logged in → go to dashboard
if (!empty($_SESSION['platform_admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

$error   = '';
$success = '';

if (!empty($_GET['logged_out'])) {
    $success = 'You have been logged out successfully.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        $auth = authenticate_admin_account($username, $password);
        if ($auth) {
            $_SESSION['platform_admin_logged_in'] = true;
            $_SESSION['platform_admin_user']       = $auth['username'];
            $_SESSION['platform_admin_name']       = $auth['name'] ?? ucfirst($auth['username']);
            $_SESSION['platform_admin_role']       = $auth['role'] ?? ROLE_SUPERADMIN;
            $_SESSION['platform_admin_email']      = $auth['email'] ?? '';
            $_SESSION['platform_admin_login_time'] = time();
            session_regenerate_id(true);

            // Log to audit trail
            require_once __DIR__ . '/helpers.php';
            log_admin_action('admin_login', null, "Admin '{$auth['username']}' logged in with role '{$auth['role']}'");

            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid credentials. Please verify your username and password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Platform Admin Login — WebCraft AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/loader-3d.css">
<script src="../assets/js/loader-3d.js"></script>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg:      #0a0d14;
    --card:    #111622;
    --border:  #1e293b;
    --primary: #6366f1;
    --primary-hover: #4f46e5;
    --success: #10b981;
    --warning: #f59e0b;
    --danger:  #ef4444;
    --text:    #e2e8f0;
    --muted:   #64748b;
    --input-bg:#0d1117;
  }

  body {
    font-family: 'Plus Jakarta Sans', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    padding: 24px;
  }

  body::before, body::after {
    content: '';
    position: fixed;
    border-radius: 50%;
    filter: blur(140px);
    pointer-events: none;
    z-index: 0;
  }
  body::before {
    width: 600px; height: 600px;
    background: rgba(99,102,241,.14);
    top: -200px; left: -200px;
  }
  body::after {
    width: 500px; height: 500px;
    background: rgba(16,185,129,.1);
    bottom: -150px; right: -150px;
  }

  .login-wrap {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 480px;
  }

  .brand {
    text-align: center;
    margin-bottom: 28px;
  }
  .brand-icon {
    width: 56px; height: 56px;
    background: linear-gradient(135deg, var(--primary), #818cf8);
    border-radius: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 14px;
    box-shadow: 0 0 40px rgba(99,102,241,.35);
  }
  .brand-icon svg { width: 28px; height: 28px; fill: #fff; }
  .brand h1 { font-size: 1.5rem; font-weight: 800; color: #fff; letter-spacing: -.02em; }
  .brand p  { color: var(--muted); font-size: .875rem; margin-top: 4px; }

  .card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 32px;
    box-shadow: 0 25px 60px rgba(0,0,0,.5);
  }

  .card h2 {
    font-size: 1.25rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 6px;
  }
  .card .subtitle {
    color: var(--muted);
    font-size: .85rem;
    margin-bottom: 24px;
  }

  .alert {
    padding: 12px 16px;
    border-radius: 10px;
    font-size: .875rem;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .alert-error   { background: rgba(239,68,68,.12);  border: 1px solid rgba(239,68,68,.3);  color: #fca5a5; }
  .alert-success { background: rgba(16,185,129,.12); border: 1px solid rgba(16,185,129,.3); color: #6ee7b7; }

  .form-group { margin-bottom: 18px; }
  .form-group label {
    display: block;
    font-size: .8rem;
    font-weight: 600;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .06em;
    margin-bottom: 8px;
  }
  .input-wrap { position: relative; }
  .input-wrap svg {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    width: 18px; height: 18px;
    stroke: var(--muted);
    fill: none;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
    pointer-events: none;
  }
  .input-wrap input {
    width: 100%;
    background: var(--input-bg);
    border: 1px solid var(--border);
    border-radius: 10px;
    color: var(--text);
    font-family: inherit;
    font-size: .925rem;
    padding: 12px 14px 12px 42px;
    outline: none;
    transition: border-color .2s, box-shadow .2s;
  }
  .input-wrap input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99,102,241,.2);
  }

  .toggle-pw {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    padding: 2px;
  }
  .toggle-pw svg {
    position: static;
    transform: none;
    stroke: var(--muted);
    transition: stroke .2s;
  }
  .toggle-pw:hover svg { stroke: var(--text); }

  .btn-primary {
    width: 100%;
    padding: 13px;
    background: var(--primary);
    color: #fff;
    border: none;
    border-radius: 10px;
    font-family: inherit;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: background .2s, transform .1s, box-shadow .2s;
    margin-top: 6px;
    box-shadow: 0 4px 20px rgba(99,102,241,.35);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
  }
  .btn-primary:hover  { background: var(--primary-hover); box-shadow: 0 6px 28px rgba(99,102,241,.45); }
  .btn-primary:active { transform: scale(.98); }

  /* Quick Role Switcher Chips */
  .role-chips {
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px dashed var(--border);
  }
  .role-chips-label {
    font-size: 0.74rem;
    font-weight: 700;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-bottom: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .chip-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
  }
  .role-chip-btn {
    background: #0d1117;
    border: 1px solid var(--border);
    border-radius: 9px;
    padding: 8px 10px;
    text-align: left;
    cursor: pointer;
    transition: all .15s;
    font-family: inherit;
  }
  .role-chip-btn:hover {
    border-color: var(--primary);
    transform: translateY(-1px);
    background: #141b2a;
  }
  .role-chip-title {
    font-size: 0.78rem;
    font-weight: 700;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 5px;
  }
  .role-chip-sub {
    font-size: 0.68rem;
    color: var(--muted);
    margin-top: 2px;
  }

  .footer-note {
    text-align: center;
    color: var(--muted);
    font-size: .78rem;
    margin-top: 20px;
  }
  .footer-note span { color: var(--primary); }
</style>
</head>
<body>
<div class="login-wrap">

  <div class="brand">
    <div class="brand-icon">
      <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
      </svg>
    </div>
    <h1>WebCraft AI Builder</h1>
    <p>Enterprise Platform Admin &amp; RBAC Portal</p>
  </div>

  <div class="card">
    <h2>Sign In to Console</h2>
    <p class="subtitle">Access controls &amp; tenant administration</p>

    <?php if ($error): ?>
    <div class="alert alert-error">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
      <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off" id="loginForm">
      <div class="form-group">
        <label>Admin Username</label>
        <div class="input-wrap">
          <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <input type="text" name="username" id="usernameInput"
                 value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                 placeholder="superadmin" required autofocus>
        </div>
      </div>

      <div class="form-group">
        <label>Password</label>
        <div class="input-wrap">
          <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          <input type="password" name="password" id="pwInput" placeholder="••••••••••••" required>
          <button type="button" class="toggle-pw" onclick="togglePw()" title="Show/hide password">
            <svg id="eyeIcon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-primary">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
        Authenticate &amp; Continue
      </button>

      <!-- Quick Role Fill Bar -->
      <div class="role-chips">
        <div class="role-chips-label">
          <span>⚡ Quick Fill by Role</span>
          <span style="color:#6366f1;font-size:0.65rem;">Click to pre-fill</span>
        </div>
        <div class="chip-grid">
          <button type="button" class="role-chip-btn" onclick="fillRole('superadmin', 'WebCraft@2026!')">
            <div class="role-chip-title" style="color:#a5b4fc">👑 Super Admin</div>
            <div class="role-chip-sub">Full access</div>
          </button>
          <button type="button" class="role-chip-btn" onclick="fillRole('support_admin', 'Support@2026!')">
            <div class="role-chip-title" style="color:#6ee7b7">🎧 Support Admin</div>
            <div class="role-chip-sub">Tickets &amp; tenants</div>
          </button>
          <button type="button" class="role-chip-btn" onclick="fillRole('billing_admin', 'Billing@2026!')">
            <div class="role-chip-title" style="color:#fcd34d">💳 Billing Admin</div>
            <div class="role-chip-sub">Invoices &amp; overdue</div>
          </button>
          <button type="button" class="role-chip-btn" onclick="fillRole('dev_admin', 'Developer@2026!')">
            <div class="role-chip-title" style="color:#67e8f9">🛠️ Developer Admin</div>
            <div class="role-chip-sub">Rollback, SSL &amp; AI</div>
          </button>
        </div>
      </div>
    </form>
  </div>

  <p class="footer-note">WebCraft Enterprise Platform &nbsp;·&nbsp; <span>RBAC Engine v2.0</span></p>
</div>

<script>
function togglePw() {
  const inp  = document.getElementById('pwInput');
  const icon = document.getElementById('eyeIcon');
  if (inp.type === 'password') {
    inp.type = 'text';
    icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
  } else {
    inp.type = 'password';
    icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
  }
}

function fillRole(u, p) {
  document.getElementById('usernameInput').value = u;
  document.getElementById('pwInput').value = p;
  document.getElementById('usernameInput').focus();
}

// ★ Secure sign-in loader (lock scene — page reloads after POST)
document.getElementById('loginForm').addEventListener('submit', function () {
  try { if (window.Loader3D) Loader3D.show('Authenticating…', 'Verifying admin credentials', 'lock'); } catch (e) {}
});
</script>
</body>
</html>
