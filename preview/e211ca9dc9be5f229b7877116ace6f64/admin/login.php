<?php
require_once __DIR__ . '/auth.php';

$authData = getAuthData();
$storedUser = (string)($authData['username'] ?? '');
$storedHash = (string)($authData['hash'] ?? '');

// Already logged in?
if (!empty($_SESSION['admin_auth'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_login_allowed()) {
        $error = 'Too many attempts. Please wait 5 minutes and try again.';
    } else {
        $u = trim($_POST['username'] ?? '');
        $p = (string)($_POST['password'] ?? '');

        $ok = false;
        if ($storedUser !== '' && $storedHash !== '' && hash_equals(strtolower($storedUser), strtolower($u))) {
            $ok = password_verify($p, $storedHash);
        }
        if ($ok) {
            session_regenerate_id(true);
            $_SESSION['admin_auth'] = true;
            $_SESSION['admin_username'] = $storedUser;
            $_SESSION['admin_email'] = $authData['email'] ?? '';
            header('Location: index.php');
            exit;
        }
        admin_login_failed();
        // Generic error: never reveal whether the username or password was wrong.
        $error = 'Invalid username or password. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login &mdash; global travel</title>
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
  <p class="sub">global travel &bull; Content Management System</p>

  <?php if ($error): ?>
    <div class="err-box">⚠️ <?= htmlspecialchars($error) ?></div>
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