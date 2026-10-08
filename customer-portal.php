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
require_once __DIR__ . '/includes/reviews.php';
require_once __DIR__ . '/includes/ui-lang.php';
$UI_LANG = ui_resolve_lang();

// ─── UI LANGUAGE SWITCH (persists to account when logged in) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_language') {
    $nl = strtolower(trim($_POST['ui_lang'] ?? 'en'));
    if (!ui_lang_valid($nl)) $nl = 'en';
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['ui_lang'] = $nl;
    if (!empty($_SESSION['customer_user']['email'])) {
        $_SESSION['customer_user']['language'] = $nl;
        try { setCustomerLanguage($_SESSION['customer_user']['email'], $nl); } catch (Throwable $e) {}
    }
    $back = SITE_URL . '/customer-portal.php' . (!empty($_GET['tab']) ? '?tab=' . urlencode($_GET['tab']) : '');
    header('Location: ' . $back);
    exit;
}
require_once __DIR__ . '/includes/Mailer.php';
require_once __DIR__ . '/includes/MailQueue.php';
require_once __DIR__ . '/includes/SocialAuth.php';

// ─── SOCIAL LOGIN (Google / Facebook / Instagram — real OAuth 2.0) ──
// ?action=social&provider=X → 302 to provider. Provider returns to
// ?action=social_callback&provider=X&code=…&state=… → verify →
// find-or-create account → session login (same shape as password login).
$socialError = '';
$socialPending = $_SESSION['social_pending'] ?? null;

if (isset($_GET['action']) && $_GET['action'] === 'social') {
    $sp = strtolower(trim($_GET['provider'] ?? ''));
    if (!social_is_provider($sp)) {
        $socialError = 'Unknown sign-in provider.';
    } else {
        $url = social_login_url($sp);
        if ($url === null) {
            $socialError = ucfirst($sp) . ' sign-in is not set up yet — add your app keys in config/social.php (see social.example.php), then try again.';
        } else {
            header('Location: ' . $url);
            exit;
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'social_callback') {
    $sp = strtolower(trim($_GET['provider'] ?? ''));
    $cb = social_handle_callback($sp, $_GET);
    if (isset($cb['error'])) {
        $socialError = $cb['error'];
    } elseif (empty($cb['email'])) {
        // Instagram shares id+username but no email → one extra step: email.
        $_SESSION['social_pending'] = [
            'provider' => $sp,
            'name' => $cb['name'] ?? ucfirst($sp) . ' user',
            'provider_id' => $cb['provider_id'] ?? '',
        ];
        $socialPending = $_SESSION['social_pending'];
    } else {
        $prov = social_provision_customer($sp, $cb['email'], $cb['name'] ?? '');
        if (empty($prov['success'])) {
            $socialError = $prov['error'] ?? 'Social sign-in failed.';
        } else {
            unset($_SESSION['social_pending']);
            $_SESSION['customer_user'] = $prov['customer'];
            $_SESSION['flash_success'] = ($prov['is_new'] ?? false)
                ? 'Account created with ' . ucfirst($sp) . ' — welcome, ' . $prov['customer']['name'] . '!'
                : 'Welcome back, ' . $prov['customer']['name'] . '!';
            try {
                $spm = Mailer::buildSigninAlertPayload($prov['customer']['email'], $prov['customer']['name'], $_SERVER['REMOTE_ADDR'] ?? '');
                queueMail($spm + ['kind' => 'signin_alert']);
            } catch (Throwable $e) {}
            header('Location: ' . SITE_URL . '/customer-portal.php');
            exit;
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'social_cancel') {
    unset($_SESSION['social_pending']);
    $socialPending = null;
    header('Location: ' . SITE_URL . '/customer-portal.php');
    exit;
}

// Instagram-style providers without email: complete account with email.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'social_email') {
    $pend = $_SESSION['social_pending'] ?? null;
    $seEmail = strtolower(trim($_POST['se_email'] ?? ''));
    if (!$pend || empty($pend['name'])) {
        unset($_SESSION['social_pending']);
        $socialPending = null;
        $socialError = 'Session expired. Please sign in again.';
    } elseif (!filter_var($seEmail, FILTER_VALIDATE_EMAIL)) {
        $socialError = 'Please enter a valid email address.';
    } else {
        $prov = social_provision_customer($pend['provider'] ?? 'social', $seEmail, $pend['name']);
        if (empty($prov['success'])) {
            $socialError = $prov['error'] ?? 'Could not complete sign-in.';
        } else {
            unset($_SESSION['social_pending']);
            $socialPending = null;
            $_SESSION['customer_user'] = $prov['customer'];
            $_SESSION['flash_success'] = 'Signed in with ' . ucfirst($pend['provider'] ?? 'social') . ' — welcome!';
            try {
                $spm = Mailer::buildSigninAlertPayload($prov['customer']['email'], $prov['customer']['name'], $_SERVER['REMOTE_ADDR'] ?? '');
                queueMail($spm + ['kind' => 'signin_alert']);
            } catch (Throwable $e) {}
            header('Location: ' . SITE_URL . '/customer-portal.php');
            exit;
        }
    }
}

/**
 * "or continue with" social button row (Google / Facebook / Instagram).
 * $suffix keeps inline-SVG ids unique when rendered twice on one page.
 */
function socialButtonsHtml(string $suffix): string {
    $base = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
    $mk = function (string $provider, string $label, string $icon) use ($base) {
        return '<a class="social-btn" href="' . $base . '/customer-portal.php?action=social&provider='
            . $provider . '" title="Continue with ' . $label . '">' . $icon
            . '<span>' . $label . '</span></a>';
    };
    $g = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="#fff"/><text x="12" y="17.5" text-anchor="middle" font-size="14" font-weight="900" fill="#4285F4" font-family="Arial,sans-serif">G</text></svg>';
    $f = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="#1877F2"/><text x="12" y="17.5" text-anchor="middle" font-size="14" font-weight="900" fill="#fff" font-family="Arial,sans-serif">f</text></svg>';
    $i = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><defs><linearGradient id="igg' . $suffix . '" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stop-color="#f09433"/><stop offset=".5" stop-color="#dc2743"/><stop offset="1" stop-color="#bc1888"/></linearGradient></defs><rect x="1.5" y="1.5" width="21" height="21" rx="6" fill="url(#igg' . $suffix . ')"/><rect x="6.5" y="6.5" width="11" height="11" rx="3.2" fill="none" stroke="#fff" stroke-width="1.8"/><circle cx="12" cy="12" r="2.8" fill="none" stroke="#fff" stroke-width="1.8"/><circle cx="16" cy="8" r="1.3" fill="#fff"/></svg>';
    return '<div class="social-divider"><span>or continue with</span></div>'
        . '<div class="social-row">'
        . $mk('google', 'Google', $g)
        . $mk('facebook', 'Facebook', $f)
        . $mk('instagram', 'Instagram', $i)
        . '</div>';
}

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

// ─── MY REVIEWS (add / edit / delete — own reviews only) ─────
// Same POST→redirect→flash convention as the rest of the portal.
// Ownership is enforced inside the helpers (email in WHERE clause).
$reviewErr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['review_add', 'review_edit', 'review_delete'], true)) {
    if (empty($_SESSION['customer_user']['email'])) {
        $reviewErr = 'Please sign in first.';
    } else {
        $em = strtolower(trim($_SESSION['customer_user']['email']));
        $nm = $_SESSION['customer_user']['name'] ?? 'Customer';
        $cid = null;
        try {
            $crow = findCustomerByEmail($em);
            if (!empty($crow['id'])) $cid = (string)$crow['id'];
        } catch (Throwable $e) {}
        if ($_POST['action'] === 'review_add') {
            $res = review_create($em, $nm, $_POST['rv_rating'] ?? 0, $_POST['rv_title'] ?? '', $_POST['rv_message'] ?? '', $_POST['rv_role'] ?? '', $cid);
            if ($res['success']) {
                $_SESSION['flash_success'] = 'Thank you! Your review has been submitted and is waiting for approval.';
                header('Location: ' . SITE_URL . '/customer-portal.php?tab=reviews');
                exit;
            }
            $reviewErr = $res['error'] ?? 'Could not submit review.';
        } elseif ($_POST['action'] === 'review_edit') {
            $res = review_update_customer($_POST['rv_id'] ?? 0, $em, $_POST['rv_rating'] ?? 0, $_POST['rv_title'] ?? '', $_POST['rv_message'] ?? '', $_POST['rv_role'] ?? '');
            if ($res['success']) {
                $_SESSION['flash_success'] = 'Review updated. It is waiting for approval again.';
                header('Location: ' . SITE_URL . '/customer-portal.php?tab=reviews');
                exit;
            }
            $reviewErr = $res['error'] ?? 'Could not update review.';
        } else {
            // Password re-verification: only deletes when the account
            // password matches (or no password is set on the account).
            if (!review_verify_delete_password($em, (string)($_POST['rv_password'] ?? ''))) {
                $reviewErr = 'Incorrect password. Review was not deleted.';
            } else {
                $res = review_delete_customer($_POST['rv_id'] ?? 0, $em);
                if ($res['success']) {
                    $_SESSION['flash_success'] = 'Review deleted.';
                    header('Location: ' . SITE_URL . '/customer-portal.php?tab=reviews');
                    exit;
                }
                $reviewErr = $res['error'] ?? 'Could not delete review.';
            }
        }
    }
}

// ─── FORGOT PASSWORD WITH EMAIL OTP (3-step, like signup) ───────────
// Step 1: email → OTP mail. Step 2: code ONLY → verify.
// Step 3 (only after verify): new password → update + auto sign-in.
$resetError = '';
$resetStep = 'email'; // email | otp | password
$resetEmail = $_SESSION['reset_email'] ?? '';
if ($resetEmail !== '') $resetStep = 'otp';
if (!empty($_SESSION['reset_verified'])) $resetStep = 'password';

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
    if ($re === '') {
        $resetError = 'Session expired. Please start again.';
        $resetStep = 'email';
    } elseif (!empty($_SESSION['reset_verified']) && $_SESSION['reset_verified'] === $re) {
        $resetStep = 'password';
    } else {
        $ver = verifyEmailOtp($re, 'reset', $code);
        if ($ver['success']) {
            $_SESSION['reset_verified'] = $re;
            $resetStep = 'password';
        } else {
            $resetError = $ver['error'] ?? 'Verification failed.';
            $resetStep = 'otp';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_complete') {
    $verified = $_SESSION['reset_verified'] ?? '';
    $re = $_SESSION['reset_email'] ?? '';
    $np = (string)($_POST['re_password'] ?? '');
    $np2 = (string)($_POST['re_password2'] ?? '');
    if ($verified === '' || $re === '' || $re !== $verified) {
        $resetError = 'Verification missing. Please start again.';
        $resetStep = 'email';
    } elseif (strlen($np) < 8) {
        $resetError = 'Password must be at least 8 characters.';
        $resetStep = 'password';
    } elseif ($np !== $np2) {
        $resetError = 'Passwords do not match.';
        $resetStep = 'password';
    } else {
        $upd = setCustomerPassword($re, $np);
        if ($upd['success']) {
            unset($_SESSION['reset_email'], $_SESSION['reset_verified']);
            $row = findCustomerByEmail($re);
            // No auto-login: user signs in manually on the login portal.
            $_SESSION['flash_success'] = 'Password updated successfully. Please sign in with your new password.';
            try {
                $pp = Mailer::buildPasswordChangedPayload($re, $row['name'] ?? 'Customer');
                queueMail($pp + ['kind' => 'password_changed']);
            } catch (Throwable $e) {}
            header('Location: ' . SITE_URL . '/customer-portal.php');
            exit;
        } else {
            $resetError = $upd['error'] ?? 'Password update failed.';
            $resetStep = 'password';
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'reset_resend' && !empty($_SESSION['reset_email'])) {
    $otp = requestEmailOtp($_SESSION['reset_email'], 'reset');
    header('Location: ' . SITE_URL . '/customer-portal.php?view=forgot' . ($otp['success'] ? '' : '&err=' . urlencode($otp['error'] ?? 'Resend failed.')));
    exit;
}
if (isset($_GET['action']) && $_GET['action'] === 'reset_cancel') {
    unset($_SESSION['reset_email'], $_SESSION['reset_verified']);
    header('Location: ' . SITE_URL . '/customer-portal.php?view=forgot');
    exit;
}
// Back button from the forgot screen: clear reset state so the login
// (Sign In) tab renders — otherwise the pending session would reopen
// the forgot tab and the back button would look broken.
if (isset($_GET['action']) && $_GET['action'] === 'reset_exit') {
    unset($_SESSION['reset_email'], $_SESSION['reset_verified']);
    header('Location: ' . SITE_URL . '/customer-portal.php');
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
$myReviews = [];
$portalTab = ($_GET['tab'] ?? '') === 'reviews' ? 'reviews' : (($_GET['tab'] ?? '') === 'notifs' ? 'notifs' : 'sites');
$reviewNeedsPw = false;

if ($currentUser) {
    $customerOrders = getCustomerOrdersFromDatabase($currentUser['email']);
    $myReviews = reviews_for_customer($currentUser['email'] ?? '');
    $reviewNeedsPw = review_delete_needs_password($currentUser['email'] ?? '');
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
.back-login-btn{display:inline-flex;align-items:center;gap:.4rem;padding:.5rem 1rem;border-radius:9px;background:#131c30;border:1.5px solid #243049;color:#cbd5e1;font-size:.8rem;font-weight:800;text-decoration:none;transition:all .18s;}
.back-login-btn:hover{border-color:#6366f1;color:#fff;background:#1e1b4b;transform:translateX(-2px);}
.ok-box{background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.4);color:#6ee7b7;padding:0.75rem;border-radius:10px;font-size:0.82rem;margin-bottom:1.25rem;}
.otp-inp{width:100%;padding:0.85rem 1rem;border:1.5px solid #6366f1;border-radius:10px;background:#060a14;color:#fff;font-family:monospace;font-size:1.4rem;letter-spacing:10px;text-align:center;transition:all 0.2s;}
.otp-inp:focus{outline:none;border-color:#a5b4fc;box-shadow:0 0 0 3px rgba(99,102,241,0.25);}
.pw-track{height:8px;background:#1e293b;border-radius:999px;overflow:hidden;margin-top:.55rem;}
.pw-fill{height:100%;width:0%;border-radius:999px;transition:width .3s ease,background .3s ease;background:#ef4444;}
.pw-hint{font-size:.74rem;color:#64748b;margin-top:.4rem;line-height:1.5;}

/* ── 3D animated auth scene (login + create account) ── */
.login-wrap{position:relative;overflow:hidden;perspective:1200px;}
.auth-bg{position:absolute;inset:0;z-index:0;pointer-events:none;overflow:hidden;}
.auth-bg .orb{position:absolute;border-radius:50%;filter:blur(70px);opacity:.5;animation:orbDrift 14s ease-in-out infinite alternate;}
.auth-bg .orb.o1{width:420px;height:420px;left:-120px;top:-100px;background:radial-gradient(circle,#6366f1,transparent 70%);}
.auth-bg .orb.o2{width:380px;height:380px;right:-100px;top:20%;background:radial-gradient(circle,#a855f7,transparent 70%);animation-delay:-5s;animation-duration:17s;}
.auth-bg .orb.o3{width:340px;height:340px;left:30%;bottom:-140px;background:radial-gradient(circle,#06b6d4,transparent 70%);animation-delay:-9s;animation-duration:20s;}
@keyframes orbDrift{from{transform:translate(0,0) scale(1);}to{transform:translate(60px,40px) scale(1.15);}}
.auth-bg .gridfloor{position:absolute;left:-25%;right:-25%;bottom:-12%;height:46%;background-image:linear-gradient(rgba(99,102,241,.22) 1px,transparent 1px),linear-gradient(90deg,rgba(99,102,241,.22) 1px,transparent 1px);background-size:44px 44px;transform:perspective(700px) rotateX(62deg);transform-origin:bottom;-webkit-mask-image:linear-gradient(to top,rgba(0,0,0,.9),transparent 85%);mask-image:linear-gradient(to top,rgba(0,0,0,.9),transparent 85%);animation:gridMove 7s linear infinite;}
@keyframes gridMove{from{background-position:0 0,0 0;}to{background-position:0 44px,0 0;}}
.cube3d{position:absolute;width:110px;height:110px;transform-style:preserve-3d;animation:cubeSpin 16s linear infinite;}
.cube3d.c1{left:9%;top:16%;}
.cube3d.c2{right:8%;bottom:14%;width:76px;height:76px;animation-duration:11s;animation-direction:reverse;}
.cube3d .face{position:absolute;inset:0;border:1.5px solid rgba(129,140,248,.55);background:linear-gradient(135deg,rgba(99,102,241,.22),rgba(168,85,247,.08));box-shadow:0 0 28px rgba(99,102,241,.25) inset;border-radius:10px;}
.cube3d .f1{transform:translateZ(55px);}
.cube3d .f2{transform:rotateY(180deg) translateZ(55px);}
.cube3d .f3{transform:rotateY(90deg) translateZ(55px);}
.cube3d .f4{transform:rotateY(-90deg) translateZ(55px);}
.cube3d .f5{transform:rotateX(90deg) translateZ(55px);}
.cube3d .f6{transform:rotateX(-90deg) translateZ(55px);}
.cube3d.c2 .f1{transform:translateZ(38px);}
.cube3d.c2 .f2{transform:rotateY(180deg) translateZ(38px);}
.cube3d.c2 .f3{transform:rotateY(90deg) translateZ(38px);}
.cube3d.c2 .f4{transform:rotateY(-90deg) translateZ(38px);}
.cube3d.c2 .f5{transform:rotateX(90deg) translateZ(38px);}
.cube3d.c2 .f6{transform:rotateX(-90deg) translateZ(38px);}
@keyframes cubeSpin{from{transform:rotateX(-18deg) rotateY(0deg);}to{transform:rotateX(-18deg) rotateY(360deg);}}
.auth-bg .floatchip{position:absolute;padding:.5rem .9rem;border-radius:12px;background:rgba(17,22,34,.72);border:1px solid rgba(129,140,248,.35);backdrop-filter:blur(8px);font-size:.72rem;font-weight:800;color:#c7d2fe;box-shadow:0 12px 30px rgba(0,0,0,.45);animation:chipFloat 6s ease-in-out infinite alternate;}
.auth-bg .floatchip.fc1{left:12%;bottom:22%;}
.auth-bg .floatchip.fc2{right:11%;top:18%;animation-delay:-3s;}
@keyframes chipFloat{from{transform:translateY(-8px) rotate(-2deg);}to{transform:translateY(10px) rotate(2deg);}}
.login-card{transform-style:preserve-3d;will-change:transform;transition:transform .15s ease-out;z-index:1;}
@media(max-width:1100px){.cube3d.c1,.auth-bg .floatchip.fc1{display:none;}}
@media(max-width:820px){.cube3d.c2,.auth-bg .floatchip.fc2{display:none;}}
@media (prefers-reduced-motion: reduce){
  .auth-bg .orb,.auth-bg .gridfloor,.cube3d,.auth-bg .floatchip{animation:none !important;}
  .login-card{transition:none;}
}

/* ── Social login row ── */
.social-divider{display:flex;align-items:center;gap:.75rem;margin:1.35rem 0 1rem;color:#64748b;font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;}
.social-divider::before,.social-divider::after{content:'';flex:1;height:1px;background:#1e293b;}
.social-row{display:flex;gap:.6rem;}
.social-btn{flex:1;display:flex;align-items:center;justify-content:center;gap:.5rem;padding:.65rem .4rem;border-radius:10px;background:#131c30;border:1.5px solid #243049;color:#e2e8f0;font-weight:800;font-size:.8rem;font-family:inherit;transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease;}
.social-btn:hover{transform:translateY(-2px);border-color:#6366f1;box-shadow:0 10px 26px rgba(99,102,241,.35);}
.social-btn:active{transform:translateY(0);}
@media(max-width:420px){.social-row{flex-direction:column;}}

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

/* ── My Reviews ── */
.rv-stars{display:flex;gap:0.25rem;}
.rv-stars button{background:none;border:none;font-size:2rem;line-height:1;color:#334155;cursor:pointer;padding:0.1rem;transition:transform 0.12s,color 0.12s;font-family:inherit;}
.rv-stars button:hover{transform:scale(1.15);}
.rv-stars button.on{color:#f59e0b;text-shadow:0 0 12px rgba(245,158,11,0.5);}
.rv-stars-static{color:#f59e0b;font-size:1rem;letter-spacing:0.1em;}
.rv-pill{font-size:0.68rem;font-weight:800;text-transform:uppercase;letter-spacing:0.05em;padding:0.2rem 0.6rem;border-radius:999px;border:1px solid;}
.rv-row{background:#0c1220;border:1px solid #1e293b;border-radius:12px;padding:1rem 1.25rem;margin-bottom:0.75rem;display:flex;gap:1rem;align-items:flex-start;flex-wrap:wrap;}
.rv-row-actions{display:flex;gap:0.4rem;flex-shrink:0;}
.rv-mini-btn{background:#1e293b;border:1px solid #334155;color:#cbd5e1;font-family:inherit;font-size:0.75rem;font-weight:700;padding:0.4rem 0.75rem;border-radius:8px;cursor:pointer;transition:all 0.15s;}
.rv-mini-btn:hover{border-color:#6366f1;color:#fff;}
.rv-mini-btn.rv-del{color:#fca5a5;border-color:#7f1d1d;}
.rv-mini-btn.rv-del:hover{background:#7f1d1d;color:#fff;}
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
    <form method="POST" action="customer-portal.php<?= !empty($_GET['tab']) ? '?tab=' . urlencode($_GET['tab']) : '' ?>" style="margin:0;">
      <input type="hidden" name="action" value="set_language">
      <select name="ui_lang" onchange="this.form.submit()" title="Language / மொழி / භාෂාව" style="background:#1e1b4b;border:1.5px solid rgba(99,102,241,0.4);color:#c7d2fe;padding:0.4rem 0.6rem;border-radius:999px;font-size:0.78rem;font-weight:700;font-family:inherit;cursor:pointer;">
        <?php foreach (ui_lang_list() as $lc => $ln): ?>
          <option value="<?= $lc ?>"<?= $UI_LANG === $lc ? ' selected' : '' ?>><?= htmlspecialchars($ln) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
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
        <a href="customer-portal.php?action=logout" class="btn-logout">🚪 <?= ui_t('p_logout') ?></a>
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
if ($viewParam === 'forgot' || !empty($resetError) || $resetStep === 'otp' || $resetStep === 'password') $activeTab = 'forgot';
$pendingEmail = $_SESSION['pending_signup']['email'] ?? '';
?>
<main class="login-wrap">
  <div class="auth-bg" aria-hidden="true">
    <div class="orb o1"></div><div class="orb o2"></div><div class="orb o3"></div>
    <div class="gridfloor"></div>
    <div class="cube3d c1"><div class="face f1"></div><div class="face f2"></div><div class="face f3"></div><div class="face f4"></div><div class="face f5"></div><div class="face f6"></div></div>
    <div class="cube3d c2"><div class="face f1"></div><div class="face f2"></div><div class="face f3"></div><div class="face f4"></div><div class="face f5"></div><div class="face f6"></div></div>
    <div class="floatchip fc1">🔐 Secure sign-in</div>
    <div class="floatchip fc2">⚡ 1-click social login</div>
  </div>
  <div class="login-card" id="auth-card">
    <div style="text-align:center;margin-bottom:1.5rem;">
      <div style="width:48px;height:48px;border-radius:14px;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:inline-flex;align-items:center;justify-content:center;font-size:1.5rem;box-shadow:0 8px 24px rgba(99,102,241,0.4);margin-bottom:0.75rem;">📱</div>
      <h1 style="font-size:1.4rem;font-weight:900;color:#fff;"><?= ui_t('p_login_title') ?></h1>
      <p style="font-size:0.84rem;color:#94a3b8;margin-top:0.35rem;"><?= ui_t('p_login_sub') ?></p>
    </div>

    <?php if ($flashSuccess): ?>
      <div class="ok-box"><?= htmlspecialchars($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($socialError): ?>
      <div class="err-box">⚠️ <?= htmlspecialchars($socialError) ?></div>
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
    <div style="margin-bottom:1.25rem;">
      <a href="customer-portal.php?action=reset_exit" class="back-login-btn" title="Back to the login page">← Back</a>
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
    <?php elseif ($resetStep === 'otp'): ?>
    <div class="ok-box">Code sent to <strong><?= htmlspecialchars($resetEmail) ?></strong>. Valid for 10 minutes.</div>
    <form method="POST" action="customer-portal.php?view=forgot">
      <input type="hidden" name="action" value="reset_verify">
      <div class="field">
        <label>6-Digit Code</label>
        <input type="text" name="re_otp" class="otp-inp" placeholder="••••••" required autofocus maxlength="6" inputmode="numeric" autocomplete="one-time-code">
      </div>
      <button type="submit" class="btn-primary"><span>Verify Code →</span></button>
    </form>
    <div style="margin-top:1rem;text-align:center;font-size:.78rem;color:#64748b;">
      Didn't get it? <a href="customer-portal.php?action=reset_resend&view=forgot" style="color:#38bdf8;font-weight:700;">Resend code</a>
      &nbsp;·&nbsp; <a href="customer-portal.php?action=reset_cancel&view=forgot" style="color:#64748b;">Use a different email</a>
    </div>
    <?php else: ?>
    <div class="ok-box">Email <strong><?= htmlspecialchars($_SESSION['reset_verified'] ?? $resetEmail) ?></strong> verified. Now set your new password.</div>
    <form method="POST" action="customer-portal.php?view=forgot">
      <input type="hidden" name="action" value="reset_complete">
      <div class="field">
        <label>New Password (min 8 characters)</label>
        <input type="password" name="re_password" id="re_password" class="inp" placeholder="New password" required autofocus oninput="pwMeter(this.value,'re')">
        <div class="pw-track"><div class="pw-fill" id="pw-fill-re"></div></div>
        <div class="pw-hint" id="pw-hint-re">Use 8+ characters with upper, lower, number &amp; symbol.</div>
      </div>
      <div class="field">
        <label>Confirm New Password</label>
        <input type="password" name="re_password2" class="inp" placeholder="Re-enter new password" required>
      </div>
      <button type="submit" class="btn-primary"><span>Update Password &amp; Sign In</span></button>
    </form>
    <?php endif; ?>

    <?php else: ?>
    <?php if ($socialPending): ?>
    <!-- ── SOCIAL: provider shared no email (e.g. Instagram) — one last step ── -->
    <div class="ok-box">Signed in with <strong><?= htmlspecialchars(ucfirst($socialPending['provider'] ?? 'social')) ?></strong> as <strong><?= htmlspecialchars($socialPending['name'] ?? '') ?></strong>. One last step — add your email to finish.</div>
    <form method="POST" action="customer-portal.php">
      <input type="hidden" name="action" value="social_email">
      <div class="field">
        <label>Email Address</label>
        <input type="email" name="se_email" class="inp" placeholder="you@email.com" required autofocus>
      </div>
      <button type="submit" class="btn-primary"><span>Complete Sign In →</span></button>
    </form>
    <div style="margin-top:1rem;text-align:center;font-size:.78rem;color:#64748b;">
      Wrong account? <a href="customer-portal.php?action=social_cancel" style="color:#38bdf8;font-weight:700;">Start over</a>
    </div>
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
    <?= socialButtonsHtml('b') ?>
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
    <?= socialButtonsHtml('a') ?>

    <div style="margin-top:1.25rem;text-align:center;font-size:.8rem;">
      <a href="customer-portal.php?view=forgot" style="color:#38bdf8;font-weight:700;">Forgot password?</a>
    </div>
    <div style="margin-top:1rem;padding-top:1.25rem;border-top:1px solid #1e293b;text-align:center;font-size:0.75rem;color:#64748b;line-height:1.5;">
      💡 New here? <a href="customer-portal.php?view=signup" style="color:#38bdf8;font-weight:700;">Create a free account</a> first, then build.
    </div>
    <?php endif; ?>
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
      <div style="font-size:.72rem;color:#64748b;margin-top:.25rem;"><?= count($customerOrders) ?> <?= ui_t('p_sites_under') ?></div>
    </div>
    <button onclick="openProfileModal()" style="padding:.6rem 1.2rem;border-radius:10px;border:1.5px solid #6366f1;background:rgba(99,102,241,.12);color:#c7d2fe;font-weight:800;font-size:.82rem;cursor:pointer;font-family:inherit;"><?= ui_t('p_edit_profile') ?></button>
  </div>

  <!-- ── PROFILE EDIT MODAL ── -->
  <div id="profile-modal" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.75);backdrop-filter:blur(8px);align-items:center;justify-content:center;" onclick="if(event.target===this)closeProfileModal()">
    <div style="background:#111622;border:1.5px solid #28334d;border-radius:20px;width:100%;max-width:440px;margin:1rem;box-shadow:0 24px 64px rgba(0,0,0,.6);overflow:hidden;">
      <div style="display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.4rem;background:#0d121c;border-bottom:1px solid #1e293b;">
        <strong style="color:#fff;font-size:1rem;"><?= ui_t('p_edit_profile') ?></strong>
        <button onclick="closeProfileModal()" style="background:none;border:none;color:#64748b;font-size:1.4rem;cursor:pointer;line-height:1;">✕</button>
      </div>
      <div style="padding:1.4rem;">
        <?php if (!empty($profileErr)): ?>
          <div class="err-box">⚠️ <?= htmlspecialchars($profileErr) ?></div>
        <?php endif; ?>
        <form method="POST" action="customer-portal.php">
          <input type="hidden" name="action" value="update_profile">
          <div class="field">
            <label><?= ui_t('p_your_name') ?></label>
            <input type="text" name="pf_name" class="inp" required maxlength="120" value="<?= htmlspecialchars($currentUser['name'] ?? '') ?>">
          </div>
          <div class="field">
            <label><?= ui_t('p_phone') ?> <span style="font-weight:400;color:#64748b;">(optional)</span></label>
            <input type="tel" name="pf_phone" class="inp" maxlength="30" placeholder="e.g. +94 77 123 4567" value="<?= htmlspecialchars($currentUser['phone'] ?? '') ?>">
          </div>
          <div class="field">
            <label>Email (cannot be changed)</label>
            <input type="text" class="inp" disabled value="<?= htmlspecialchars($currentUser['email']) ?>" style="opacity:.6;">
          </div>
          <button type="submit" class="btn-primary"><span><?= ui_t('p_save') ?></span></button>
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
      <h1 style="font-size:1.8rem;font-weight:900;color:#fff;"><?= ui_t('p_dash_title') ?></h1>
      <p style="color:#94a3b8;font-size:0.88rem;margin-top:0.25rem;"><?= ui_t('p_dash_sub') ?></p>
    </div>
    <a href="builder.php" class="act-btn act-btn-primary"><?= ui_t('p_btn_create') ?></a>
  </div>

  <!-- Quick Stats -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-lbl"><?= ui_t('p_stat_total') ?></div>
      <div class="stat-val"><?= count($customerOrders) ?></div>
      <div style="font-size:0.72rem;color:#64748b;"><?= ui_t('p_stat_total_sub') ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-lbl"><?= ui_t('p_stat_active') ?></div>
      <div class="stat-val" style="color:#34d399;"><?= count(array_filter($customerOrders, fn($o) => !empty($o['site_active']))) ?> 🟢</div>
      <div style="font-size:0.72rem;color:#64748b;"><?= ui_t('p_stat_active_sub') ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-lbl"><?= ui_t('p_stat_renewal') ?></div>
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
      <div style="font-size:0.72rem;color:#64748b;"><?= ui_t('p_stat_renewal_sub') ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-lbl"><?= ui_t('p_stat_notifs') ?></div>
      <div class="stat-val" style="color:#818cf8;"><?= count($customerNotifs) ?></div>
      <div style="font-size:0.72rem;color:#64748b;"><?= ui_t('p_stat_notifs_sub') ?></div>
    </div>
  </div>

  <!-- Tabs Navigation -->
  <div class="tabs-bar">
    <button class="tab-btn<?= $portalTab === 'sites' ? ' active' : '' ?>" onclick="switchPortalTab('sites')" id="ptab-sites"><?= ui_t('p_tab_sites') ?> (<?= count($customerOrders) ?>)</button>
    <button class="tab-btn<?= $portalTab === 'notifs' ? ' active' : '' ?>" onclick="switchPortalTab('notifs')" id="ptab-notifs"><?= ui_t('p_tab_notifs') ?> (<?= count($customerNotifs) ?>)</button>
    <button class="tab-btn<?= $portalTab === 'reviews' ? ' active' : '' ?>" onclick="switchPortalTab('reviews')" id="ptab-reviews"><?= ui_t('p_tab_reviews') ?> (<?= count($myReviews) ?>)</button>
  </div>

  <!-- ── SITES LIST TAB ── -->
  <div id="psec-sites"<?= $portalTab === 'sites' ? '' : ' style="display:none;"' ?>>
    <?php if (empty($customerOrders)): ?>
      <div style="background:#0c1220;border:1px dashed #1e293b;border-radius:18px;padding:3rem;text-align:center;">
        <div style="font-size:3rem;margin-bottom:1rem;">🚀</div>
        <h3 style="color:#fff;font-size:1.2rem;margin-bottom:0.5rem;"><?= ui_t('p_empty_sites_t') ?></h3>
        <p style="color:#94a3b8;font-size:0.88rem;margin-bottom:1.5rem;"><?= ui_t('p_empty_sites_s') ?></p>
        <a href="builder.php" class="act-btn act-btn-primary"><?= ui_t('p_btn_build_ai') ?></a>
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
  <div id="psec-notifs"<?= $portalTab === 'notifs' ? '' : ' style="display:none;"' ?>>
    <?php if (empty($customerNotifs)): ?>
      <div style="background:#0c1220;border:1px dashed #1e293b;border-radius:16px;padding:2.5rem;text-align:center;color:#94a3b8;">
        <?= ui_t('p_no_notifs') ?>
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

  <!-- ── MY REVIEWS TAB ── -->
  <div id="psec-reviews"<?= $portalTab === 'reviews' ? '' : ' style="display:none;"' ?>>
    <?php if ($reviewErr): ?>
      <div class="err-box">⚠️ <?= htmlspecialchars($reviewErr) ?></div>
    <?php endif; ?>

    <!-- Write a review -->
    <div style="background:#0c1220;border:1px solid #1e293b;border-radius:16px;padding:1.5rem;margin-bottom:1.25rem;">
      <h3 style="color:#fff;font-size:1.05rem;margin-bottom:0.25rem;"><?= ui_t('r_write') ?></h3>
      <p style="color:#94a3b8;font-size:0.8rem;margin-bottom:1.1rem;"><?= ui_t('r_posting_as') ?> <strong style="color:#c7d2fe;"><?= htmlspecialchars($currentUser['name'] ?? 'Customer') ?></strong> · <?= ui_t('r_approved_note') ?></p>
      <form method="POST" action="customer-portal.php?tab=reviews" id="rv-add-form">
        <input type="hidden" name="action" value="review_add">
        <div class="field">
          <label><?= ui_t('r_rating') ?></label>
          <div class="rv-stars" id="rv-stars-add" role="radiogroup" aria-label="Star rating">
            <?php for ($s = 1; $s <= 5; $s++): ?>
              <button type="button" data-v="<?= $s ?>" onclick="rvSetRating('add', <?= $s ?>)" aria-label="<?= $s ?> star<?= $s > 1 ? 's' : '' ?>">★</button>
            <?php endfor; ?>
          </div>
          <input type="hidden" name="rv_rating" id="rv-rating-add" value="0">
          <div id="rv-rating-hint-add" style="font-size:0.75rem;color:#64748b;margin-top:0.3rem;"><?= ui_t('r_rate_hint') ?></div>
        </div>
        <div class="field">
          <label><?= ui_t('r_title') ?> <span style="font-weight:400;color:#64748b;"><?= ui_t('r_optional') ?></span></label>
          <input type="text" name="rv_title" class="inp" maxlength="120" placeholder="<?= htmlspecialchars(ui_t('r_title_ph')) ?>">
        </div>
        <div class="field">
          <label><?= ui_t('r_msg') ?></label>
          <textarea name="rv_message" class="inp" rows="4" required minlength="10" maxlength="2000" placeholder="<?= htmlspecialchars(ui_t('r_msg_ph')) ?>"></textarea>
        </div>
        <div class="field">
          <label><?= ui_t('r_role') ?> <span style="font-weight:400;color:#64748b;"><?= ui_t('r_optional') ?></span></label>
          <input type="text" name="rv_role" class="inp" maxlength="120" placeholder="<?= htmlspecialchars(ui_t('r_role_ph')) ?>">
        </div>
        <button type="submit" class="btn-primary"><span><?= ui_t('r_submit') ?></span></button>
      </form>
    </div>

    <!-- Own reviews -->
    <h3 style="color:#fff;font-size:1.05rem;margin:0 0 0.9rem;"><?= ui_t('r_yours') ?> (<?= count($myReviews) ?>)</h3>
    <?php if (empty($myReviews)): ?>
      <div style="background:#0c1220;border:1px dashed #1e293b;border-radius:16px;padding:2.5rem;text-align:center;color:#94a3b8;">
        <?= ui_t('r_empty') ?>
      </div>
    <?php else: ?>
      <?php foreach ($myReviews as $rv): ?>
        <?php
          $rvId = (int)($rv['id'] ?? 0);
          $rvStatus = $rv['status'] ?? 'pending';
          $rvStatusLbl = $rvStatus === 'approved' ? ui_t('r_st_approved') : ($rvStatus === 'rejected' ? ui_t('r_st_rejected') : ui_t('r_st_pending'));
          $rvPill = $rvStatus === 'approved' ? 'background:rgba(16,185,129,.14);color:#6ee7b7;border-color:rgba(16,185,129,.4);'
            : ($rvStatus === 'rejected' ? 'background:rgba(239,68,68,.12);color:#fca5a5;border-color:rgba(239,68,68,.4);'
            : 'background:rgba(245,158,11,.12);color:#fcd34d;border-color:rgba(245,158,11,.4);');
        ?>
        <div class="rv-row">
          <div style="flex:1;min-width:0;">
            <div style="display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap;margin-bottom:0.35rem;">
              <span class="rv-stars-static"><?= str_repeat('★', max(0, min(5, (int)($rv['rating'] ?? 0)))) ?><?= str_repeat('☆', 5 - max(0, min(5, (int)($rv['rating'] ?? 0)))) ?></span>
              <span class="rv-pill" style="<?= $rvPill ?>"><?= htmlspecialchars($rvStatusLbl) ?></span>
              <span style="font-size:0.72rem;color:#64748b;"><?= htmlspecialchars(!empty($rv['created_at']) ? date('M j, Y', strtotime($rv['created_at'])) : '') ?></span>
            </div>
            <?php if (!empty($rv['review_title'])): ?>
              <div style="color:#fff;font-weight:800;font-size:0.9rem;"><?= htmlspecialchars($rv['review_title']) ?></div>
            <?php endif; ?>
            <div style="color:#cbd5e1;font-size:0.84rem;line-height:1.55;margin-top:0.2rem;"><?= nl2br(htmlspecialchars($rv['review_message'] ?? '')) ?></div>
            <?php if (!empty($rv['customer_role'])): ?>
              <div style="font-size:0.72rem;color:#64748b;margin-top:0.25rem;"><?= htmlspecialchars($rv['customer_role']) ?></div>
            <?php endif; ?>
          </div>
          <div class="rv-row-actions">
            <button class="rv-mini-btn" onclick='rvOpenEdit(<?= $rvId ?>, <?= json_encode((int)($rv['rating'] ?? 5)) ?>, <?= json_encode($rv['review_title'] ?? '', JSON_HEX_APOS | JSON_HEX_QUOT) ?>, <?= json_encode($rv['review_message'] ?? '', JSON_HEX_APOS | JSON_HEX_QUOT) ?>, <?= json_encode($rv['customer_role'] ?? '', JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><?= ui_t('r_edit') ?></button>
            <?php if ($reviewNeedsPw): ?>
              <button class="rv-mini-btn rv-del" onclick="rvAskDelete(<?= $rvId ?>)"><?= ui_t('r_delete') ?></button>
            <?php else: ?>
            <form method="POST" action="customer-portal.php?tab=reviews" onsubmit="return confirm('Delete this review? This cannot be undone.')" style="margin:0;">
              <input type="hidden" name="action" value="review_delete">
              <input type="hidden" name="rv_id" value="<?= $rvId ?>">
              <button type="submit" class="rv-mini-btn rv-del"><?= ui_t('r_delete') ?></button>
            </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- ── REVIEW EDIT MODAL ── -->
  <div id="rv-edit-modal" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.75);backdrop-filter:blur(8px);align-items:center;justify-content:center;" onclick="if(event.target===this)rvCloseEdit()">
    <div style="background:#111622;border:1.5px solid #28334d;border-radius:20px;width:100%;max-width:480px;margin:1rem;box-shadow:0 24px 64px rgba(0,0,0,.6);overflow:hidden;max-height:90vh;overflow-y:auto;">
      <div style="display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.4rem;background:#0d121c;border-bottom:1px solid #1e293b;">
        <strong style="color:#fff;font-size:1rem;"><?= ui_t('r_edit_title') ?></strong>
        <button onclick="rvCloseEdit()" style="background:none;border:none;color:#64748b;font-size:1.4rem;cursor:pointer;line-height:1;">✕</button>
      </div>
      <div style="padding:1.4rem;">
        <form method="POST" action="customer-portal.php?tab=reviews" id="rv-edit-form">
          <input type="hidden" name="action" value="review_edit">
          <input type="hidden" name="rv_id" id="rv-edit-id" value="">
          <div class="field">
            <label><?= ui_t('r_rating') ?></label>
            <div class="rv-stars" id="rv-stars-edit" role="radiogroup" aria-label="Star rating">
              <?php for ($s = 1; $s <= 5; $s++): ?>
                <button type="button" data-v="<?= $s ?>" onclick="rvSetRating('edit', <?= $s ?>)" aria-label="<?= $s ?> star<?= $s > 1 ? 's' : '' ?>">★</button>
              <?php endfor; ?>
            </div>
            <input type="hidden" name="rv_rating" id="rv-rating-edit" value="5">
            <div id="rv-rating-hint-edit" style="font-size:0.75rem;color:#64748b;margin-top:0.3rem;">5/5</div>
          </div>
          <div class="field">
            <label><?= ui_t('r_title') ?> <span style="font-weight:400;color:#64748b;"><?= ui_t('r_optional') ?></span></label>
            <input type="text" name="rv_title" id="rv-edit-title" class="inp" maxlength="120">
          </div>
          <div class="field">
            <label><?= ui_t('r_msg') ?></label>
            <textarea name="rv_message" id="rv-edit-message" class="inp" rows="4" required minlength="10" maxlength="2000"></textarea>
          </div>
          <div class="field">
            <label><?= ui_t('r_role') ?> <span style="font-weight:400;color:#64748b;"><?= ui_t('r_optional') ?></span></label>
            <input type="text" name="rv_role" id="rv-edit-role" class="inp" maxlength="120">
          </div>
          <button type="submit" class="btn-primary"><span><?= ui_t('r_save') ?></span></button>
        </form>
      </div>
    </div>
  </div>

  <!-- ── REVIEW DELETE CONFIRM (password re-verification) ── -->
  <div id="rv-del-modal" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.75);backdrop-filter:blur(8px);align-items:center;justify-content:center;" onclick="if(event.target===this)rvCloseDelete()">
    <div style="background:#111622;border:1.5px solid #7f1d1d;border-radius:20px;width:100%;max-width:400px;margin:1rem;box-shadow:0 24px 64px rgba(0,0,0,.6);overflow:hidden;">
      <div style="display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.4rem;background:#0d121c;border-bottom:1px solid #1e293b;">
        <strong style="color:#fca5a5;font-size:1rem;"><?= ui_t('r_del_title') ?></strong>
        <button onclick="rvCloseDelete()" style="background:none;border:none;color:#64748b;font-size:1.4rem;cursor:pointer;line-height:1;">✕</button>
      </div>
      <div style="padding:1.4rem;">
        <p style="color:#cbd5e1;font-size:0.84rem;line-height:1.6;margin-bottom:1rem;"><?= ui_t('r_del_msg') ?></p>
        <form method="POST" action="customer-portal.php?tab=reviews">
          <input type="hidden" name="action" value="review_delete">
          <input type="hidden" name="rv_id" id="rv-del-id" value="">
          <div class="field">
            <label><?= ui_t('r_pw') ?></label>
            <input type="password" name="rv_password" class="inp" required autocomplete="current-password" placeholder="••••••••">
          </div>
          <div style="display:flex;gap:0.6rem;">
            <button type="button" onclick="rvCloseDelete()" class="btn-primary" style="background:#1e293b;box-shadow:none;"><span><?= ui_t('r_cancel') ?></span></button>
            <button type="submit" class="btn-primary" style="background:linear-gradient(135deg,#dc2626,#991b1b);box-shadow:0 8px 24px rgba(220,38,38,.4);"><span><?= ui_t('r_del_go') ?></span></button>
          </div>
        </form>
      </div>
    </div>
  </div>

</main>

<script>
function switchPortalTab(tab) {
  ['sites', 'notifs', 'reviews'].forEach(function (t) {
    var sec = document.getElementById('psec-' + t);
    if (sec) sec.style.display = (tab === t) ? 'block' : 'none';
    var btn = document.getElementById('ptab-' + t);
    if (btn) btn.classList.toggle('active', tab === t);
  });
}
/* Interactive star ratings (add + edit forms share logic) */
function rvSetRating(which, n) {
  var hid = document.getElementById('rv-rating-' + which);
  var hint = document.getElementById('rv-rating-hint-' + which);
  if (hid) hid.value = n;
  var wrap = document.getElementById('rv-stars-' + which);
  if (wrap) {
    wrap.querySelectorAll('button').forEach(function (b) {
      b.classList.toggle('on', parseInt(b.dataset.v, 10) <= n);
    });
  }
  if (hint) hint.textContent = n + '/5' + (n === 5 ? ' — Excellent!' : n === 4 ? ' — Great!' : n === 3 ? ' — Good' : n === 2 ? ' — Fair' : ' — Poor');
}
function rvOpenEdit(id, rating, title, message, role) {
  document.getElementById('rv-edit-id').value = id;
  document.getElementById('rv-edit-title').value = title || '';
  document.getElementById('rv-edit-message').value = message || '';
  document.getElementById('rv-edit-role').value = role || '';
  rvSetRating('edit', rating || 5);
  var m = document.getElementById('rv-edit-modal');
  if (m) m.style.display = 'flex';
}
function rvCloseEdit() {
  var m = document.getElementById('rv-edit-modal');
  if (m) m.style.display = 'none';
}
function rvAskDelete(id) {
  document.getElementById('rv-del-id').value = id;
  var m = document.getElementById('rv-del-modal');
  if (m) m.style.display = 'flex';
}
function rvCloseDelete() {
  var m = document.getElementById('rv-del-modal');
  if (m) m.style.display = 'none';
}
document.getElementById('rv-add-form').addEventListener('submit', function (e) {
  if (parseInt(document.getElementById('rv-rating-add').value, 10) < 1) {
    e.preventDefault();
    alert('Please select a star rating first.');
  }
});
<?php if ($reviewErr): ?>switchPortalTab('reviews');<?php endif; ?>
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
        reset_verify: ['Verifying…', 'Checking your code', 'lock'],
        reset_complete: ['Updating password…', 'Securing your account', 'lock'],
        update_profile: ['Saving profile…', 'Updating your details', 'save'],
        upload_avatar: ['Uploading photo…', 'Saving your picture', 'save'],
        social_email: ['Completing sign-in…', 'Setting up your workspace', 'lock']
      };
      var m = msgs[act];
      if (m && window.Loader3D) Loader3D.show(m[0], m[1], m[2]);
    } catch (e) {}
  });
});

// ★ 3D tilt on the auth card (login + create account) — mouse-driven,
// disabled for touch / reduced-motion users.
(function () {
  try {
    var card = document.getElementById('auth-card');
    var wrap = document.querySelector('.login-wrap');
    if (!card || !wrap) return;
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    if (window.matchMedia && window.matchMedia('(pointer: coarse)').matches) return;
    wrap.addEventListener('mousemove', function (e) {
      var r = wrap.getBoundingClientRect();
      var x = (e.clientX - r.left) / Math.max(r.width, 1) - 0.5;
      var y = (e.clientY - r.top) / Math.max(r.height, 1) - 0.5;
      card.style.transform = 'rotateY(' + (x * 8).toFixed(2) + 'deg) rotateX(' + (-y * 8).toFixed(2) + 'deg)';
    });
    wrap.addEventListener('mouseleave', function () { card.style.transform = ''; });
  } catch (e) {}
})();
</script>
</body>
</html>
