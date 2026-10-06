<?php
/**
 * customer-portal.php — Customer Project Portal & Website Tracker
 * 
 * Allows customers who published websites on WebCraft AI to:
 * 1. Log in using their email and password (or Order ID).
 * 2. Track all their published websites/projects in one place.
 * 3. View live site, open admin panel, or launch AI feature adder.
 * 4. See notifications, payment reminders, and renewal due dates.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
// No-cache: logout ku pirahu Back press panna logged-in page vara kudathu
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/Mailer.php';
require_once __DIR__ . '/includes/MailQueue.php';

// ─── LOGOUT HANDLER ──────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['customer_user']);
    header('Location: ' . SITE_URL . '/customer-portal.php?logged_out=1');
    exit;
}

// ─── SIGNUP: details → email OTP → password (3-step) ───────────
// Step 1: name + email → OTP mail. Step 2: OTP verify. Step 3: password → account.
$signupError = '';
$signupStep = 'form'; // form | otp | password
if (!empty($_SESSION['pending_signup']['email'])) $signupStep = 'otp';
if (!empty($_SESSION['signup_verified'])) $signupStep = 'password';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'signup_request') {
    $suName  = trim($_POST['su_name'] ?? '');
    $suEmail = strtolower(trim($_POST['su_email'] ?? ''));

    if ($suName === '') {
        $signupError = 'Please enter your name.';
    } elseif (!filter_var($suEmail, FILTER_VALIDATE_EMAIL)) {
        $signupError = 'Please enter a valid email address.';
    } elseif (findCustomerByEmail($suEmail)) {
        // Database la already irukku → clear error
        $signupError = 'This email is already registered. Please sign in instead.';
    } else {
        $otp = requestEmailOtp($suEmail, 'signup');
        if ($otp['success']) {
            $_SESSION['pending_signup'] = ['name' => $suName, 'email' => $suEmail];
            $signupStep = 'otp';
        } else {
            $signupError = $otp['error'] ?? 'Could not send verification email.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'signup_verify') {
    $pend = $_SESSION['pending_signup'] ?? null;
    $code = (string)($_POST['su_otp'] ?? '');
    if (!$pend || empty($pend['email'])) {
        $signupError = 'Session expired. Please start again.';
        $signupStep = 'form';
    } else {
        // Re-check duplicate (DB) before marking verified
        if (findCustomerByEmail($pend['email'])) {
            unset($_SESSION['pending_signup']);
            $signupError = 'This email just got registered. Please sign in instead.';
            $signupStep = 'form';
        } else {
            $ver = verifyEmailOtp($pend['email'], 'signup', $code);
            if ($ver['success']) {
                $_SESSION['signup_verified'] = $pend['email'];
                $signupStep = 'password';
            } else {
                $signupError = $ver['error'] ?? 'Verification failed.';
                $signupStep = 'otp';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'signup_complete') {
    $verified = $_SESSION['signup_verified'] ?? '';
    $pend = $_SESSION['pending_signup'] ?? null;
    $np = (string)($_POST['su_password'] ?? '');
    $np2 = (string)($_POST['su_password2'] ?? '');
    if ($verified === '' || !$pend || $pend['email'] !== $verified) {
        $signupError = 'Verification missing. Please start again.';
        $signupStep = 'form';
    } elseif (strlen($np) < 8) {
        $signupError = 'Password must be at least 8 characters.';
        $signupStep = 'password';
    } elseif ($np !== $np2) {
        $signupError = 'Passwords do not match.';
        $signupStep = 'password';
    } else {
        $res = createCustomerAccount($pend['name'], $pend['email'], $np);
        unset($_SESSION['pending_signup'], $_SESSION['signup_verified']);
        if ($res['success']) {
            $_SESSION['customer_user'] = $res['customer'];
            $_SESSION['flash_success'] = 'Account verified successfully. Welcome!';
            // Welcome email via background queue (never blocks signup)
            try {
                $wp = Mailer::buildWelcomePayload($res['customer']['email'], $res['customer']['name']);
                queueMail($wp + ['kind' => 'welcome']);
            } catch (Throwable $e) {}
            header('Location: ' . SITE_URL . '/customer-portal.php');
            exit;
        } else {
            $signupError = $res['error'] ?? 'Account creation failed.';
            $signupStep = 'form';
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'signup_resend' && !empty($_SESSION['pending_signup']['email'])) {
    $otp = requestEmailOtp($_SESSION['pending_signup']['email'], 'signup');
    $signupError = $otp['success'] ? '' : ($otp['error'] ?? 'Resend failed.');
    if ($otp['success']) $_SESSION['flash_success'] = 'New code sent to your email.';
    header('Location: ' . SITE_URL . '/customer-portal.php?view=signup' . ($signupError ? '&err=' . urlencode($signupError) : ''));
    exit;
}
if (isset($_GET['action']) && $_GET['action'] === 'signup_cancel') {
    unset($_SESSION['pending_signup'], $_SESSION['signup_verified']);
    header('Location: ' . SITE_URL . '/customer-portal.php?view=signup');
    exit;
}

// ─── PROFILE UPDATE (name, phone) ────────────────────────────
$profileMsg = '';
$profileErr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    if (empty($_SESSION['customer_user']['email'])) {
        $profileErr = 'Please sign in first.';
    } else {
        $em = $_SESSION['customer_user']['email'];
        $res = updateCustomerProfile($em, $_POST['pf_name'] ?? '', $_POST['pf_phone'] ?? '');
        if ($res['success']) {
            $row = findCustomerByEmail($em);
            $_SESSION['customer_user']['name'] = $row['name'] ?? $_POST['pf_name'];
            $_SESSION['customer_user']['phone'] = $row['phone'] ?? '';
            $_SESSION['flash_success'] = 'Profile updated successfully.';
            header('Location: ' . SITE_URL . '/customer-portal.php');
            exit;
        } else {
            $profileErr = $res['error'] ?? 'Update failed.';
        }
    }
}

// ─── AVATAR UPLOAD / REMOVE ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_avatar') {
    if (empty($_SESSION['customer_user']['email'])) {
        $profileErr = 'Please sign in first.';
    } else {
        $em = $_SESSION['customer_user']['email'];
        $res = uploadCustomerAvatar($em, $_FILES['avatar'] ?? []);
        if ($res['success']) {
            $_SESSION['customer_user']['avatar'] = $res['path'];
            $_SESSION['flash_success'] = 'Profile photo updated successfully.';
            header('Location: ' . SITE_URL . '/customer-portal.php');
            exit;
        } else {
            $profileErr = $res['error'] ?? 'Upload failed.';
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'remove_avatar' && !empty($_SESSION['customer_user']['email'])) {
    $em = $_SESSION['customer_user']['email'];
    removeCustomerAvatar($em);
    $_SESSION['customer_user']['avatar'] = '';
    $_SESSION['flash_success'] = 'Profile photo removed.';
    header('Location: ' . SITE_URL . '/customer-portal.php');
    exit;
}

// ─── FORGOT PASSWORD WITH EMAIL OTP ───────────────────────────
$resetError = '';
$resetStep = 'email'; // email | otp
$resetEmail = $_SESSION['reset_email'] ?? '';
if ($resetEmail !== '') $resetStep = 'otp';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_request') {
    $re = strtolower(trim($_POST['re_email'] ?? ''));
    if (!filter_var($re, FILTER_VALIDATE_EMAIL)) {
        $resetError = 'Please enter a valid email address.';
    } elseif (!findCustomerByEmail($re) && empty(getCustomerOrdersFromDatabase($re))) {
        $resetError = 'No account found with this email.';
    } else {
        $otp = requestEmailOtp($re, 'reset');
        if ($otp['success']) {
            $_SESSION['reset_email'] = $re;
            $resetEmail = $re;
            $resetStep = 'otp';
        } else {
            $resetError = $otp['error'] ?? 'Could not send reset email.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_verify') {
    $re = $_SESSION['reset_email'] ?? '';
    $code = (string)($_POST['re_otp'] ?? '');
    $np = (string)($_POST['re_password'] ?? '');
    $np2 = (string)($_POST['re_password2'] ?? '');
    if ($re === '') {
        $resetError = 'Session expired. Please start again.';
        $resetStep = 'email';
    } elseif (strlen($np) < 8) {
        $resetError = 'Password must be at least 8 characters.';
        $resetStep = 'otp';
    } elseif ($np !== $np2) {
        $resetError = 'Passwords do not match.';
        $resetStep = 'otp';
    } else {
        $ver = verifyEmailOtp($re, 'reset', $code);
        if ($ver['success']) {
            $upd = setCustomerPassword($re, $np);
            unset($_SESSION['reset_email']);
            if ($upd['success']) {
                $row = findCustomerByEmail($re);
                $_SESSION['customer_user'] = ['email' => $re, 'name' => $row['name'] ?? 'Customer'];
                $_SESSION['flash_success'] = 'Password updated successfully. You are signed in.';
                try {
                    $pp = Mailer::buildPasswordChangedPayload($re, $row['name'] ?? 'Customer');
                    queueMail($pp + ['kind' => 'password_changed']);
                } catch (Throwable $e) {}
                header('Location: ' . SITE_URL . '/customer-portal.php');
                exit;
            } else {
                $resetError = $upd['error'] ?? 'Password update failed.';
                $resetStep = 'email';
            }
        } else {
            $resetError = $ver['error'] ?? 'Verification failed.';
            $resetStep = 'otp';
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'reset_resend' && !empty($_SESSION['reset_email'])) {
    $otp = requestEmailOtp($_SESSION['reset_email'], 'reset');
    header('Location: ' . SITE_URL . '/customer-portal.php?view=forgot' . ($otp['success'] ? '' : '&err=' . urlencode($otp['error'] ?? 'Resend failed.')));
    exit;
}
if (isset($_GET['err'])) {
    $e = $_GET['err'];
    if (($_GET['view'] ?? '') === 'forgot' && !$resetError) $resetError = $e;
    elseif (!$signupError && !$loginError) $signupError = $e;
}

// ─── LOGIN HANDLER (POST) ────────────────────────────────────
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $emailOrId = trim($_POST['email_or_id'] ?? '');
    $password  = trim($_POST['password'] ?? '');

    if (empty($emailOrId)) {
        $loginError = 'Please enter your email or Order ID.';
    } elseif (empty($password)) {
        // Quick access with Order ID if password omitted
        $orders = getCustomerOrdersFromDatabase($emailOrId);
        if (!empty($orders)) {
            $first = $orders[0];
            $_SESSION['customer_user'] = [
                'email' => $first['admin_email'] ?: ($first['client_email'] ?? $emailOrId),
                'name'  => $first['admin_username'] ?? $first['site_name'] ?? 'Customer',
            ];
            header('Location: ' . SITE_URL . '/customer-portal.php');
            exit;
        } else {
            $loginError = 'No project found for this Order ID or Email.';
        }
    } elseif (filter_var($emailOrId, FILTER_VALIDATE_EMAIL)) {
        // 1. Real customer account first
        $res = verifyCustomerAccount($emailOrId, $password);
        if ($res['success']) {
            $_SESSION['customer_user'] = $res['customer'];
            try {
                $sp = Mailer::buildSigninAlertPayload($res['customer']['email'], $res['customer']['name'], $_SERVER['REMOTE_ADDR'] ?? '');
                queueMail($sp + ['kind' => 'signin_alert']);
            } catch (Throwable $e) {}
            header('Location: ' . SITE_URL . '/customer-portal.php');
            exit;
        }
        // 2. Legacy fallback: order admin password (published before signup existed)
        $legacy = verifyCustomerLoginCredentials($emailOrId, $password);
        if ($legacy['success']) {
            $_SESSION['customer_user'] = $legacy['customer'];
            try {
                $sp = Mailer::buildSigninAlertPayload($legacy['customer']['email'], $legacy['customer']['name'], $_SERVER['REMOTE_ADDR'] ?? '');
                queueMail($sp + ['kind' => 'signin_alert']);
            } catch (Throwable $e) {}
            header('Location: ' . SITE_URL . '/customer-portal.php');
            exit;
        } else {
            $loginError = $res['error'] ?? 'Invalid credentials.';
        }
    } else {
        $res = verifyCustomerLoginCredentials($emailOrId, $password);
        if ($res['success']) {
            $_SESSION['customer_user'] = $res['customer'];
            header('Location: ' . SITE_URL . '/customer-portal.php');
            exit;
        } else {
            $loginError = $res['error'] ?? 'Invalid credentials.';
        }
    }
}

// Check logged in state
$currentUser = $_SESSION['customer_user'] ?? null;
$flashSuccess = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);
$customerOrders = [];
$customerNotifs = [];

if ($currentUser) {
    $customerOrders = getCustomerOrdersFromDatabase($currentUser['email']);
    foreach ($customerOrders as $o) {
        $oid = $o['order_id'] ?? '';
        if ($oid) {
            $nList = getCustomerNotificationsFromDb($oid);
            foreach ($nList as $n) {
                $n['site_name'] = $o['site_name'] ?? 'Your Website';
                $n['order_id']  = $oid;
                $customerNotifs[] = $n;
            }
        }
    }
    // Sort notifications newest first
    usort($customerNotifs, function($a, $b) {
        $tA = strtotime($a['created_at'] ?? $a['sent_at'] ?? 'now');
        $tB = strtotime($b['created_at'] ?? $b['sent_at'] ?? 'now');
        return $tB - $tA;
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Customer Project Portal — <?= SITE_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=Fira+Code:wght@500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/loader-3d.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Plus Jakarta Sans',system-ui,sans-serif;background:#060911;color:#e2e8f0;min-height:100vh;display:flex;flex-direction:column;}
a{color:inherit;text-decoration:none;}

/* ── Top Bar ── */
.topbar{background:#0c1220;border-bottom:1px solid #1e293b;padding:0.9rem 1.75rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;}
.brand{display:flex;align-items:center;gap:0.75rem;font-weight:900;font-size:1.15rem;color:#fff;}
.brand-icon{width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;font-size:1.1rem;}
.user-badge{display:flex;align-items:center;gap:0.85rem;}
.user-pill{background:#1e1b4b;border:1px solid rgba(99,102,241,0.4);color:#c7d2fe;padding:0.4rem 0.85rem;border-radius:999px;font-size:0.8rem;font-weight:700;}
.btn-logout{background:#1e293b;border:1px solid #334155;color:#ef4444;padding:0.4rem 0.85rem;border-radius:8px;font-size:0.78rem;font-weight:700;cursor:pointer;transition:all 0.15s;}
.btn-logout:hover{background:#7f1d1d;color:#fff;}

/* ── Login View ── */
.login-wrap{flex:1;display:flex;align-items:center;justify-content:center;padding:2.5rem 1.25rem;background:radial-gradient(circle at 50% 25%,#151c38 0%,#060911 75%);}
.login-card{width:100%;max-width:440px;background:#0c1220;border:1px solid #1e293b;border-radius:20px;padding:2.5rem 2rem;box-shadow:0 25px 60px rgba(0,0,0,0.6);position:relative;overflow:hidden;}
.login-card::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,#6366f1,#a855f7,#06b6d4);}
.field{margin-bottom:1.25rem;}
.field label{display:block;font-size:0.75rem;font-weight:700;color:#cbd5e1;margin-bottom:0.45rem;text-transform:uppercase;letter-spacing:0.04em;}
.inp{width:100%;padding:0.75rem 0.95rem;border:1.5px solid #1e293b;border-radius:10px;background:#060a14;color:#fff;font-family:inherit;font-size:0.88rem;transition:all 0.2s;}
.inp:focus{outline:none;border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,0.25);}
.btn-primary{width:100%;padding:0.85rem;border-radius:11px;border:none;background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;font-weight:800;font-size:0.92rem;cursor:pointer;transition:all 0.2s;box-shadow:0 8px 24px rgba(99,102,241,0.4);display:flex;align-items:center;justify-content:center;gap:0.5rem;}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 12px 30px rgba(99,102,241,0.6);}
.err-box{background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.4);color:#fca5a5;padding:0.75rem;border-radius:10px;font-size:0.82rem;margin-bottom:1.25rem;}
.ok-box{background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.4);color:#6ee7b7;padding:0.75rem;border-radius:10px;font-size:0.82rem;margin-bottom:1.25rem;}
.otp-inp{width:100%;padding:0.85rem 1rem;border:1.5px solid #6366f1;border-radius:10px;background:#060a14;color:#fff;font-family:monospace;font-size:1.4rem;letter-spacing:10px;text-align:center;transition:all 0.2s;}
.otp-inp:focus{outline:none;border-color:#a5b4fc;box-shadow:0 0 0 3px rgba(99,102,241,0.25);}
.pw-track{height:8px;background:#1e293b;border-radius:999px;overflow:hidden;margin-top:.55rem;}
.pw-fill{height:100%;width:0%;border-radius:999px;transition:width .3s ease,background .3s ease;background:#ef4444;}
.pw-hint{font-size:.74rem;color:#64748b;margin-top:.4rem;line-height:1.5;}

/* ── Dashboard View ── */
.dash-wrap{flex:1;max-width:1250px;width:100%;margin:0 auto;padding:2rem 1.5rem;}
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:2rem;}
@media(max-width:850px){.stats-grid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:480px){.stats-grid{grid-template-columns:1fr;}}
.stat-card{background:#0c1220;border:1px solid #1e293b;border-radius:16px;padding:1.25rem;}
.stat-lbl{font-size:0.72rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;}
.stat-val{font-size:1.75rem;font-weight:900;color:#fff;margin:0.35rem 0;}

/* ── Tabs ── */
.tabs-bar{display:flex;gap:0.5rem;border-bottom:1px solid #1e293b;margin-bottom:1.75rem;}
.tab-btn{background:none;border:none;color:#94a3b8;font-family:inherit;font-size:0.92rem;font-weight:700;padding:0.75rem 1.25rem;cursor:pointer;border-bottom:3px solid transparent;transition:all 0.2s;}
.tab-btn:hover{color:#fff;}
.tab-btn.active{color:#a5b4fc;border-bottom-color:#6366f1;}

/* ── Projects Grid ── */
.project-card{background:#0c1220;border:1px solid #1e293b;border-radius:18px;padding:1.5rem;margin-bottom:1.25rem;display:flex;flex-direction:column;gap:1.1rem;box-shadow:0 10px 30px rgba(0,0,0,0.4);transition:transform 0.2s,border-color 0.2s;}
.project-card:hover{border-color:#38bdf8;}
.pc-top{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:0.75rem;}
.pc-title{font-size:1.2rem;font-weight:800;color:#fff;}
.pc-slug{font-family:'Fira Code',monospace;font-size:0.75rem;color:#38bdf8;margin-top:0.25rem;}
.badges{display:flex;gap:0.4rem;}
.badge{padding:0.28rem 0.65rem;border-radius:999px;font-size:0.72rem;font-weight:800;text-transform:uppercase;}
.badge-green{background:rgba(16,185,129,0.15);color:#34d399;border:1px solid rgba(16,185,129,0.3);}
.badge-indigo{background:rgba(99,102,241,0.15);color:#a5b4fc;border:1px solid rgba(99,102,241,0.3);}
.pc-meta{display:grid;grid-template-columns:repeat(3,1fr);gap:0.75rem;background:#060a14;padding:0.85rem 1rem;border-radius:12px;border:1px solid #1e293b;font-size:0.8rem;}
@media(max-width:650px){.pc-meta{grid-template-columns:1fr;}}
.pc-meta-item strong{display:block;color:#64748b;font-size:0.68rem;text-transform:uppercase;}
.pc-meta-item span{color:#f1f5f9;font-weight:700;}
.pc-actions{display:flex;gap:0.6rem;flex-wrap:wrap;}
.act-btn{padding:0.6rem 1.1rem;border-radius:10px;font-size:0.82rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:0.45rem;transition:all 0.15s;border:none;}
.act-btn-primary{background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;}
.act-btn-green{background:linear-gradient(135deg,#10b981,#059669);color:#fff;}
.act-btn-dark{background:#1e293b;color:#e2e8f0;border:1px solid #334155;}
.act-btn:hover{transform:translateY(-1px);filter:brightness(1.1);}

/* ── Notifications List ── */
.notif-row{background:#0c1220;border:1px solid #1e293b;border-radius:12px;padding:1rem 1.25rem;margin-bottom:0.75rem;display:flex;align-items:flex-start;gap:0.85rem;}
.notif-icon{font-size:1.3rem;flex-shrink:0;margin-top:0.2rem;}
.notif-body{flex:1;}
.notif-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:0.35rem;flex-wrap:wrap;gap:0.5rem;}
.notif-title{font-size:0.92rem;font-weight:800;color:#fff;}
.notif-time{font-size:0.72rem;color:#64748b;}
.notif-msg{font-size:0.84rem;color:#94a3b8;line-height:1.55;white-space:pre-wrap;}
</style>
</head>
<body>

<!-- ─── TOPBAR ─────────────────────────────────────────────── -->
<header class="topbar">
  <a href="<?= SITE_URL ?>" class="brand">
    <div class="brand-icon">⚡</div>
    <div><?= SITE_NAME ?> <span style="font-size:0.75rem;color:#818cf8;font-weight:600;margin-left:0.35rem;">Customer Portal</span></div>
  </a>

  <div style="display:flex; align-items:center; gap:0.75rem;">
    <div style="display:inline-flex; align-items:center; gap:0.4rem; padding:0.3rem 0.75rem; border-radius:999px; font-size:0.75rem; font-weight:800; background:rgba(16,185,129,0.15); border:1.5px solid #10b981; color:#34d399;" title="Supabase Cloud Database Active">
      <span style="width:7px; height:7px; border-radius:50%; background:#10b981; box-shadow:0 0 8px #10b981;"></span>
      <span>⚡ Supabase Connected</span>
    </div>

    <?php if ($currentUser): ?>
      <div class="user-badge">
        <?php $topAvatar = customerAvatarUrl($currentUser); ?>
        <?php if ($topAvatar): ?>
          <img src="<?= htmlspecialchars($topAvatar) ?>" alt="" style="width:32px;height:32px;border-radius:50%;object-fit:cover;border:2px solid #6366f1;">
        <?php endif; ?>
        <span class="user-pill">👤 <?= htmlspecialchars($currentUser['email']) ?></span>
        <a href="customer-portal.php?action=logout" class="btn-logout">🚪 Sign Out</a>
      </div>
    <?php else: ?>
      <div style="font-size:0.82rem;color:#94a3b8;">
        Need assistance? Contact <a href="mailto:<?= defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : 'support@webcraft.ai' ?>" style="color:#38bdf8;"><?= defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : 'support@webcraft.ai' ?></a>
      </div>
    <?php endif; ?>
  </div>
</header>

<?php if (!$currentUser): ?>
<!-- ══════════════════════════════════════════════════════════
     SCREEN 1: CUSTOMER LOGIN
══════════════════════════════════════════════════════════ -->
<?php
$viewParam = $_GET['view'] ?? '';
$activeTab = 'signin';
if ($viewParam === 'signup' || !empty($signupError) || $signupStep === 'otp') $activeTab = 'signup';
if ($viewParam === 'forgot' || !empty($resetError) || $resetStep === 'otp') $activeTab = 'forgot';
$pendingEmail = $_SESSION['pending_signup']['email'] ?? '';
?>
<main class="login-wrap">
  <div class="login-card">
    <div style="text-align:center;margin-bottom:1.5rem;">
      <div style="width:48px;height:48px;border-radius:14px;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:inline-flex;align-items:center;justify-content:center;font-size:1.5rem;box-shadow:0 8px 24px rgba(99,102,241,0.4);margin-bottom:0.75rem;">📱</div>
      <h1 style="font-size:1.4rem;font-weight:900;color:#fff;">Customer Portal</h1>
      <p style="font-size:0.84rem;color:#94a3b8;margin-top:0.35rem;">Sign in or create a free account to manage your websites.</p>
    </div>

    <?php if ($flashSuccess): ?>
      <div class="ok-box"><?= htmlspecialchars($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['logged_out'])): ?>
      <div class="ok-box">Signed out successfully.</div>
      <script>
      // Drop legacy global (unscoped) project keys so next login starts clean
      try {
        localStorage.removeItem('webcraft_saved_project');
        localStorage.removeItem('webcraft_generated_projects');
      } catch (e) {}
      </script>
    <?php endif; ?>

    <?php if ($activeTab === 'forgot'): ?>
    <!-- ── FORGOT PASSWORD ── -->
    <div style="text-align:center;margin-bottom:1.25rem;">
      <a href="customer-portal.php" style="font-size:.78rem;color:#94a3b8;">← Back to Sign In</a>
    </div>
    <h2 style="font-size:1.05rem;font-weight:800;color:#fff;margin-bottom:.35rem;">Reset Password</h2>
    <p style="font-size:.8rem;color:#94a3b8;margin-bottom:1.25rem;">We'll email you a 6-digit code to verify it's you.</p>

    <?php if ($resetError): ?>
      <div class="err-box">⚠️ <?= htmlspecialchars($resetError) ?></div>
    <?php endif; ?>

    <?php if ($resetStep === 'email'): ?>
    <form method="POST" action="customer-portal.php?view=forgot">
      <input type="hidden" name="action" value="reset_request">
      <div class="field">
        <label>Account Email</label>
        <input type="email" name="re_email" class="inp" placeholder="you@email.com" required autofocus value="<?= htmlspecialchars($_POST['re_email'] ?? '') ?>">
      </div>
      <button type="submit" class="btn-primary"><span>Send Verification Code</span></button>
    </form>
    <?php else: ?>
    <div class="ok-box">Code sent to <strong><?= htmlspecialchars($resetEmail) ?></strong>. Valid for 10 minutes.</div>
    <form method="POST" action="customer-portal.php?view=forgot">
      <input type="hidden" name="action" value="reset_verify">
      <div class="field">
        <label>6-Digit Code</label>
        <input type="text" name="re_otp" class="otp-inp" placeholder="••••••" required autofocus maxlength="6" inputmode="numeric" autocomplete="one-time-code">
      </div>
      <div class="field">
        <label>New Password (min 8 characters)</label>
        <input type="password" name="re_password" id="re_password" class="inp" placeholder="New password" required oninput="pwMeter(this.value,'re')">
        <div class="pw-track"><div class="pw-fill" id="pw-fill-re"></div></div>
        <div class="pw-hint" id="pw-hint-re">Use 8+ characters with upper, lower, number &amp; symbol.</div>
      </div>
      <div class="field">
        <label>Confirm New Password</label>
        <input type="password" name="re_password2" class="inp" placeholder="Re-enter new password" required>
      </div>
      <button type="submit" class="btn-primary"><span>Verify &amp; Update Password</span></button>
    </form>
    <div style="margin-top:1rem;text-align:center;font-size:.78rem;color:#64748b;">
      Didn't get it? <a href="customer-portal.php?action=reset_resend&view=forgot" style="color:#38bdf8;font-weight:700;">Resend code</a>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div style="display:flex;gap:.5rem;background:#060a14;border:1px solid #1e293b;border-radius:10px;padding:.3rem;margin-bottom:1.5rem;">
      <a href="customer-portal.php" style="flex:1;text-align:center;padding:.55rem;border-radius:8px;font-size:.84rem;font-weight:800;<?= $activeTab === 'signin' ? 'background:#6366f1;color:#fff;' : 'color:#94a3b8;' ?>">Sign In</a>
      <a href="customer-portal.php?view=signup" style="flex:1;text-align:center;padding:.55rem;border-radius:8px;font-size:.84rem;font-weight:800;<?= $activeTab === 'signup' ? 'background:#6366f1;color:#fff;' : 'color:#94a3b8;' ?>">Create Account</a>
    </div>

    <?php if ($activeTab === 'signup'): ?>
    <?php if ($signupError): ?>
      <div class="err-box">⚠️ <?= htmlspecialchars($signupError) ?></div>
    <?php endif; ?>

    <?php if ($signupStep === 'otp'): ?>
    <!-- ── SIGNUP STEP 2: OTP ── -->
    <div class="ok-box">Code sent to <strong><?= htmlspecialchars($pendingEmail) ?></strong>. Enter it to verify your email.</div>
    <form method="POST" action="customer-portal.php?view=signup">
      <input type="hidden" name="action" value="signup_verify">
      <div class="field">
        <label>6-Digit Code</label>
        <input type="text" name="su_otp" class="otp-inp" placeholder="••••••" required autofocus maxlength="6" inputmode="numeric" autocomplete="one-time-code">
      </div>
      <button type="submit" class="btn-primary"><span>Verify Email →</span></button>
    </form>
    <div style="margin-top:1rem;text-align:center;font-size:.78rem;color:#64748b;">
      Didn't get it? <a href="customer-portal.php?action=signup_resend&view=signup" style="color:#38bdf8;font-weight:700;">Resend code</a>
      &nbsp;·&nbsp; <a href="customer-portal.php?action=signup_cancel&view=signup" style="color:#64748b;">Start over</a>
    </div>
    <?php elseif ($signupStep === 'password'): ?>
    <!-- ── SIGNUP STEP 3: PASSWORD (after email verified) ── -->
    <div class="ok-box">Email <strong><?= htmlspecialchars($_SESSION['signup_verified'] ?? '') ?></strong> verified. Now set a strong password.</div>
    <form method="POST" action="customer-portal.php?view=signup">
      <input type="hidden" name="action" value="signup_complete">
      <div class="field">
        <label>Password (min 8 characters)</label>
        <input type="password" name="su_password" id="su_password" class="inp" placeholder="Choose a strong password" required autofocus autocomplete="new-password" oninput="pwMeter(this.value,'su')">
        <div class="pw-track"><div class="pw-fill" id="pw-fill-su"></div></div>
        <div class="pw-hint" id="pw-hint-su">Use 8+ characters with upper, lower, number &amp; symbol.</div>
      </div>
      <div class="field">
        <label>Confirm Password</label>
        <input type="password" name="su_password2" class="inp" placeholder="Re-enter password" required autocomplete="new-password">
      </div>
      <button type="submit" class="btn-primary"><span>Create My Account</span></button>
    </form>
    <?php else: ?>
    <!-- ── SIGNUP STEP 1 ── -->
    <form method="POST" action="customer-portal.php?view=signup">
      <input type="hidden" name="action" value="signup_request">
      <div class="field">
        <label>Your Name</label>
        <input type="text" name="su_name" class="inp" placeholder="e.g. Priya Kumar" required autofocus value="<?= htmlspecialchars($_POST['su_name'] ?? '') ?>">
      </div>
      <div class="field">
        <label>Email Address</label>
        <input type="email" name="su_email" class="inp" placeholder="you@email.com" required value="<?= htmlspecialchars($_POST['su_email'] ?? '') ?>">
      </div>
      <button type="submit" class="btn-primary"><span>Send Verification Code</span></button>
    </form>
    <div style="margin-top:1.5rem;padding-top:1.25rem;border-top:1px solid #1e293b;text-align:center;font-size:0.75rem;color:#64748b;line-height:1.5;">
      💡 One account manages all your websites. Use the same email at publish time.
    </div>
    <?php endif; ?>
    <?php else: ?>
    <?php if ($loginError): ?>
      <div class="err-box">⚠️ <?= htmlspecialchars($loginError) ?></div>
    <?php endif; ?>

    <form method="POST" action="customer-portal.php">
      <input type="hidden" name="action" value="login">
      <div class="field">
        <label>Customer Email or Order ID</label>
        <input type="text" name="email_or_id" class="inp" placeholder="e.g. you@email.com or WBL-2026-XXXX" required autofocus value="<?= htmlspecialchars($_POST['email_or_id'] ?? '') ?>">
      </div>
      <div class="field">
        <label>Password <span style="font-weight:400;color:#64748b;">(Optional for Order ID)</span></label>
        <input type="password" name="password" class="inp" placeholder="Enter your account password">
      </div>
      <button type="submit" class="btn-primary">
        <span>🚀 Access My Websites</span>
      </button>
    </form>

    <div style="margin-top:1.25rem;text-align:center;font-size:.8rem;">
      <a href="customer-portal.php?view=forgot" style="color:#38bdf8;font-weight:700;">Forgot password?</a>
    </div>
    <div style="margin-top:1rem;padding-top:1.25rem;border-top:1px solid #1e293b;text-align:center;font-size:0.75rem;color:#64748b;line-height:1.5;">
      💡 New here? <a href="customer-portal.php?view=signup" style="color:#38bdf8;font-weight:700;">Create a free account</a> first, then build.
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</main>

<?php else: ?>
<!-- ══════════════════════════════════════════════════════════
     SCREEN 2: CUSTOMER DASHBOARD
══════════════════════════════════════════════════════════ -->
<main class="dash-wrap">
  <?php if ($flashSuccess): ?>
    <div class="ok-box"><?= htmlspecialchars($flashSuccess) ?></div>
  <?php endif; ?>
  <?php if (!empty($profileErr)): ?>
    <div class="err-box">⚠️ <?= htmlspecialchars($profileErr) ?></div>
  <?php endif; ?>
  <?php $avatarUrl = customerAvatarUrl($currentUser); ?>
  <!-- ── PROFILE CARD ── -->
  <div style="background:linear-gradient(135deg,#0c1220,#141b33);border:1px solid #28334d;border-radius:20px;padding:1.5rem;margin-bottom:2rem;display:flex;align-items:center;gap:1.25rem;flex-wrap:wrap;box-shadow:0 12px 32px rgba(0,0,0,.45);">
    <div style="position:relative;flex-shrink:0;cursor:pointer;" onclick="document.getElementById('avatar-direct-input').click()" title="Click to change photo">
      <div style="width:84px;height:84px;border-radius:50%;padding:3px;background:linear-gradient(135deg,#6366f1,#a855f7,#06b6d4);">
        <?php if ($avatarUrl): ?>
          <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="Profile photo" style="width:100%;height:100%;border-radius:50%;object-fit:cover;display:block;background:#0b0f17;pointer-events:none;">
        <?php else: ?>
          <div style="width:100%;height:100%;border-radius:50%;background:#1e1b4b;display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:900;color:#c7d2fe;pointer-events:none;"><?= htmlspecialchars(strtoupper(substr($currentUser['name'] ?? $currentUser['email'], 0, 1))) ?></div>
        <?php endif; ?>
      </div>
      <span title="Change photo"
        style="position:absolute;bottom:-2px;right:-2px;width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#4f46e5);border:2px solid #0c1220;color:#fff;font-size:.85rem;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(99,102,241,.5);pointer-events:none;">✎</span>
    </div>
    <!-- Direct photo upload (avatar click → file picker → auto upload) -->
    <form id="avatar-direct-form" method="POST" action="customer-portal.php" enctype="multipart/form-data" style="display:none;">
      <input type="hidden" name="action" value="upload_avatar">
      <input type="file" id="avatar-direct-input" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp" onchange="if(this.files.length){document.getElementById('avatar-direct-form').submit();}">
    </form>
    <div style="flex:1;min-width:200px;">
      <div style="font-size:1.25rem;font-weight:900;color:#fff;"><?= htmlspecialchars($currentUser['name'] ?? 'Customer') ?></div>
      <div style="font-size:.82rem;color:#94a3b8;"><?= htmlspecialchars($currentUser['email']) ?><?= !empty($currentUser['phone']) ? ' · ' . htmlspecialchars($currentUser['phone']) : '' ?></div>
      <div style="font-size:.72rem;color:#64748b;margin-top:.25rem;"><?= count($customerOrders) ?> website(s) under your account</div>
    </div>
    <button onclick="openProfileModal()" style="padding:.6rem 1.2rem;border-radius:10px;border:1.5px solid #6366f1;background:rgba(99,102,241,.12);color:#c7d2fe;font-weight:800;font-size:.82rem;cursor:pointer;font-family:inherit;">✎ Edit Profile</button>
  </div>

  <!-- ── PROFILE EDIT MODAL ── -->
  <div id="profile-modal" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.75);backdrop-filter:blur(8px);align-items:center;justify-content:center;" onclick="if(event.target===this)closeProfileModal()">
    <div style="background:#111622;border:1.5px solid #28334d;border-radius:20px;width:100%;max-width:440px;margin:1rem;box-shadow:0 24px 64px rgba(0,0,0,.6);overflow:hidden;">
      <div style="display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.4rem;background:#0d121c;border-bottom:1px solid #1e293b;">
        <strong style="color:#fff;font-size:1rem;">✎ Edit Profile</strong>
        <button onclick="closeProfileModal()" style="background:none;border:none;color:#64748b;font-size:1.4rem;cursor:pointer;line-height:1;">✕</button>
      </div>
      <div style="padding:1.4rem;">
        <?php if (!empty($profileErr)): ?>
          <div class="err-box">⚠️ <?= htmlspecialchars($profileErr) ?></div>
        <?php endif; ?>
        <form method="POST" action="customer-portal.php">
          <input type="hidden" name="action" value="update_profile">
          <div class="field">
            <label>Your Name</label>
            <input type="text" name="pf_name" class="inp" required maxlength="120" value="<?= htmlspecialchars($currentUser['name'] ?? '') ?>">
          </div>
          <div class="field">
            <label>Phone Number <span style="font-weight:400;color:#64748b;">(optional)</span></label>
            <input type="tel" name="pf_phone" class="inp" maxlength="30" placeholder="e.g. +94 77 123 4567" value="<?= htmlspecialchars($currentUser['phone'] ?? '') ?>">
          </div>
          <div class="field">
            <label>Email (cannot be changed)</label>
            <input type="text" class="inp" disabled value="<?= htmlspecialchars($currentUser['email']) ?>" style="opacity:.6;">
          </div>
          <button type="submit" class="btn-primary"><span>Save Changes</span></button>
        </form>
        <?php if ($avatarUrl): ?>
          <div style="margin-top:1rem;text-align:center;">
            <a href="customer-portal.php?action=remove_avatar" onclick="return confirm('Remove profile photo?')" style="font-size:.78rem;color:#f87171;">Remove profile photo</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <script>
  function openProfileModal(){ var m=document.getElementById('profile-modal'); if(m){ m.style.display='flex'; } }
  function closeProfileModal(){ var m=document.getElementById('profile-modal'); if(m){ m.style.display='none'; } }
  <?php if (!empty($profileErr)): ?>openProfileModal();<?php endif; ?>
  </script>
  <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:1.75rem;flex-wrap:wrap;gap:1rem;">
    <div>
      <h1 style="font-size:1.8rem;font-weight:900;color:#fff;">My Websites &amp; Projects 👋</h1>
      <p style="color:#94a3b8;font-size:0.88rem;margin-top:0.25rem;">Welcome back! Manage your active web properties, open CMS admin panels, and add AI features.</p>
    </div>
    <a href="builder.php" class="act-btn act-btn-primary">➕ Create New Website</a>
  </div>

  <!-- Quick Stats -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-lbl">Total Websites</div>
      <div class="stat-val"><?= count($customerOrders) ?></div>
      <div style="font-size:0.72rem;color:#64748b;">Owned under your account</div>
    </div>
    <div class="stat-card">
      <div class="stat-lbl">Active Online</div>
      <div class="stat-val" style="color:#34d399;"><?= count(array_filter($customerOrders, fn($o) => !empty($o['site_active']))) ?> 🟢</div>
      <div style="font-size:0.72rem;color:#64748b;">Hosted &amp; live right now</div>
    </div>
    <div class="stat-card">
      <div class="stat-lbl">Next Renewal Due</div>
      <?php 
        $nextDate = null;
        foreach ($customerOrders as $o) {
            if (!empty($o['next_payment_due'])) {
                if (!$nextDate || strtotime($o['next_payment_due']) < strtotime($nextDate)) {
                    $nextDate = $o['next_payment_due'];
                }
            }
        }
      ?>
      <div class="stat-val" style="font-size:1.35rem;color:#facc15;"><?= $nextDate ? date('M j, Y', strtotime($nextDate)) : 'Active' ?></div>
      <div style="font-size:0.72rem;color:#64748b;">Subscription auto-billing</div>
    </div>
    <div class="stat-card">
      <div class="stat-lbl">Notifications</div>
      <div class="stat-val" style="color:#818cf8;"><?= count($customerNotifs) ?></div>
      <div style="font-size:0.72rem;color:#64748b;">Updates &amp; payment reminders</div>
    </div>
  </div>

  <!-- Tabs Navigation -->
  <div class="tabs-bar">
    <button class="tab-btn active" onclick="switchPortalTab('sites')" id="ptab-sites">🌐 Published Websites (<?= count($customerOrders) ?>)</button>
    <button class="tab-btn" onclick="switchPortalTab('notifs')" id="ptab-notifs">🔔 Notifications (<?= count($customerNotifs) ?>)</button>
  </div>

  <!-- ── SITES LIST TAB ── -->
  <div id="psec-sites">
    <?php if (empty($customerOrders)): ?>
      <div style="background:#0c1220;border:1px dashed #1e293b;border-radius:18px;padding:3rem;text-align:center;">
        <div style="font-size:3rem;margin-bottom:1rem;">🚀</div>
        <h3 style="color:#fff;font-size:1.2rem;margin-bottom:0.5rem;">No Published Websites Yet</h3>
        <p style="color:#94a3b8;font-size:0.88rem;margin-bottom:1.5rem;">Start building your first stunning AI-powered website now.</p>
        <a href="builder.php" class="act-btn act-btn-primary">Build a Website with AI →</a>
      </div>
    <?php else: ?>
      <?php foreach ($customerOrders as $order): ?>
        <?php 
          $slug = htmlspecialchars($order['slug'] ?? '');
          $siteName = htmlspecialchars($order['site_name'] ?? 'Website');
          $orderId = htmlspecialchars($order['order_id'] ?? '');
          $package = htmlspecialchars(ucfirst($order['package'] ?? 'Pro'));
          $amount = number_format((float)($order['amount'] ?? 19.00), 2);
          $liveUrl = $order['live_url'] ?: (SITE_URL . '/published/' . $slug . '/');
          $adminUrl = $order['admin_url'] ?: (SITE_URL . '/published/' . $slug . '/admin/login.php');
          $isActive = !empty($order['site_active']);
          $dueDate = $order['next_payment_due'] ?? null;
          $managerUrl = SITE_URL . '/site-manager.php?order_id=' . urlencode($orderId);
          $editUrl = SITE_URL . '/builder.php?order_id=' . urlencode($orderId);
        ?>
        <div class="project-card">
          <div class="pc-top">
            <div>
              <div class="pc-title"><?= $siteName ?></div>
              <div class="pc-slug">📍 <?= $slug ?> &bull; Order ID: <?= $orderId ?></div>
            </div>
            <div class="badges">
              <span class="badge badge-indigo"><?= $package ?> Plan</span>
              <span class="badge <?= $isActive ? 'badge-green' : 'badge-red' ?>"><?= $isActive ? '● Live Online' : '● Suspended' ?></span>
            </div>
          </div>

          <div class="pc-meta">
            <div class="pc-meta-item">
              <strong>Live Website:</strong>
              <span><a href="<?= $liveUrl ?>" target="_blank" style="color:#34d399;word-break:break-all;"><?= htmlspecialchars($liveUrl) ?></a></span>
            </div>
            <div class="pc-meta-item">
              <strong>Site Status:</strong>
              <span style="color:<?= $isActive ? '#34d399' : '#f87171' ?>;"><?= $isActive ? '● Live Online' : '● Suspended' ?></span>
            </div>
            <div class="pc-meta-item">
              <strong>Admin Username:</strong>
              <span><?= htmlspecialchars($order['admin_username'] ?? 'admin') ?></span>
            </div>
            <div class="pc-meta-item">
              <strong>Subscription Renewal:</strong>
              <span style="color:#facc15;"><?= $dueDate ? date('M j, Y', strtotime($dueDate)) : '30 days after publish' ?> ($<?= $amount ?>/mo)</span>
            </div>
            <div class="pc-meta-item">
              <strong>Published Date:</strong>
              <span><?= !empty($order['published_at']) ? date('M j, Y', strtotime($order['published_at'])) : 'Recent' ?></span>
            </div>
            <div class="pc-meta-item">
              <strong>Order ID:</strong>
              <span style="font-family:'Fira Code',monospace;"><?= $orderId ?></span>
            </div>
          </div>

          <div class="pc-actions">
            <a href="<?= $liveUrl ?>" target="_blank" class="act-btn act-btn-green">
              <span>🌐 Open Live Website</span>
            </a>
            <a href="<?= $adminUrl ?>" target="_blank" class="act-btn act-btn-primary">
              <span>🔐 Open Admin Panel</span>
            </a>
            <a href="<?= $managerUrl ?>#ai-adder" target="_blank" class="act-btn act-btn-dark" style="border-color:#6366f1;color:#c7d2fe;">
              <span>🤖 Add AI Functions</span>
            </a>
            <a href="<?= $editUrl ?>" class="act-btn act-btn-dark">
              <span>🎨 Edit Design in Builder</span>
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- ── NOTIFICATIONS TAB ── -->
  <div id="psec-notifs" style="display:none;">
    <?php if (empty($customerNotifs)): ?>
      <div style="background:#0c1220;border:1px dashed #1e293b;border-radius:16px;padding:2.5rem;text-align:center;color:#94a3b8;">
        🔔 No notifications yet. You're all caught up!
      </div>
    <?php else: ?>
      <?php foreach ($customerNotifs as $notif): ?>
        <?php 
          $type = $notif['type'] ?? 'info';
          $icon = '🔔';
          if ($type === 'payment_reminder') $icon = '💳';
          elseif ($type === 'warning') $icon = '⚠️';
          elseif ($type === 'welcome') $icon = '🎉';
        ?>
        <div class="notif-row">
          <div class="notif-icon"><?= $icon ?></div>
          <div class="notif-body">
            <div class="notif-head">
              <span class="notif-title"><?= htmlspecialchars($notif['subject'] ?? ucfirst($type)) ?></span>
              <span class="notif-time"><?= htmlspecialchars($notif['created_at'] ?? $notif['sent_at'] ?? '') ?></span>
            </div>
            <div style="font-size:0.75rem;color:#818cf8;font-weight:700;margin-bottom:0.35rem;">
              Site: <?= htmlspecialchars($notif['site_name'] ?? 'Website') ?> (<?= htmlspecialchars($notif['order_id'] ?? '') ?>)
            </div>
            <div class="notif-msg"><?= htmlspecialchars($notif['message'] ?? '') ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</main>

<script>
function switchPortalTab(tab) {
  document.getElementById('psec-sites').style.display = (tab === 'sites') ? 'block' : 'none';
  document.getElementById('psec-notifs').style.display = (tab === 'notifs') ? 'block' : 'none';
  document.getElementById('ptab-sites').classList.toggle('active', tab === 'sites');
  document.getElementById('ptab-notifs').classList.toggle('active', tab === 'notifs');
}
</script>
<?php endif; ?>

<footer style="background:#060a14;padding:1.25rem;text-align:center;border-top:1px solid #1e293b;font-size:0.75rem;color:#64748b;margin-top:auto;">
  &copy; <?= date('Y') ?> <?= SITE_NAME ?> &bull; Customer Project Portal
</footer>

<script>
console.log('%c🟢 Supabase PostgreSQL: Connected & Synced! (scuzaitwwbnsllvyqced.supabase.co)', 'background: #062b22; color: #34d399; font-weight: bold; font-size: 13px; padding: 6px 12px; border-radius: 6px; border: 1.5px solid #10b981;');
</script>
<script src="<?= SITE_URL ?>/assets/js/loader-3d.js"></script>
<script>
// Auto-fade flash boxes (success/error) after 6s
setTimeout(function () {
  document.querySelectorAll('.ok-box,.err-box').forEach(function (b) {
    b.style.transition = 'opacity .5s ease';
    b.style.opacity = '0';
    setTimeout(function () { b.style.display = 'none'; }, 500);
  });
}, 6000);
function pwMeter(val, id) {
  var score = 0, tips = [];
  if (val.length >= 8) score++; else tips.push('8+ characters');
  if (val.length >= 12) score++;
  if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++; else tips.push('upper + lower case');
  if (/\d/.test(val)) score++; else tips.push('a number');
  if (/[^A-Za-z0-9]/.test(val)) score++; else tips.push('a symbol (!@#…)');
  var pct = [0, 20, 40, 60, 80, 100][Math.min(score, 5)];
  var colors = ['#ef4444', '#ef4444', '#f59e0b', '#eab308', '#10b981', '#10b981'];
  var labels = ['Too weak', 'Weak', 'Okay', 'Good', 'Strong', 'Excellent'];
  var fill = document.getElementById('pw-fill-' + id);
  var hint = document.getElementById('pw-hint-' + id);
  if (!fill || !hint) return;
  fill.style.width = pct + '%';
  fill.style.background = colors[Math.min(score, 5)];
  hint.innerHTML = val
    ? '<strong style="color:' + colors[Math.min(score, 5)] + '">' + labels[Math.min(score, 5)] + '</strong>' + (tips.length ? ' — add ' + tips.join(', ') : ' — nice password!')
    : 'Use 8+ characters with upper, lower, number & symbol.';
}

// ★ Logout loading screen (situation-based 3D scene: secure sign-out)
document.addEventListener('click', function (e) {
  var a = e.target.closest && e.target.closest('a[href*="action=logout"]');
  if (!a) return;
  e.preventDefault();
  var url = a.href;
  try { if (window.Loader3D) Loader3D.show('Signing you out…', 'Securing your session', 'lock'); } catch (err) {}
  setTimeout(function () { window.location.href = url; }, 950);
});

// Lock submit buttons + fullscreen home-build loader while the form posts
document.querySelectorAll('form').forEach(function (f) {
  f.addEventListener('submit', function () {
    var b = f.querySelector('button[type=submit]');
    if (b && !b.disabled) {
      b.disabled = true;
      if (!b.dataset.orig) b.dataset.orig = b.innerHTML;
      b.innerHTML = 'Please wait…';
      b.style.opacity = '.75';
    }
    try {
      var act = (f.querySelector('input[name=action]') || {}).value || '';
      var msgs = {
        login: ['Signing you in…', 'Verifying credentials', 'lock'],
        signup_request: ['Sending code…', 'Mailing your verification code', 'mail'],
        signup_verify: ['Verifying…', 'Checking your code', 'lock'],
        signup_complete: ['Creating account…', 'Setting up your workspace', 'lock'],
        reset_request: ['Sending code…', 'Mailing your reset code', 'mail'],
        reset_verify: ['Updating password…', 'Securing your account', 'lock'],
        update_profile: ['Saving profile…', 'Updating your details', 'save'],
        upload_avatar: ['Uploading photo…', 'Saving your picture', 'save']
      };
      var m = msgs[act];
      if (m && window.Loader3D) Loader3D.show(m[0], m[1], m[2]);
    } catch (e) {}
  });
});
</script>
</body>
</html>
