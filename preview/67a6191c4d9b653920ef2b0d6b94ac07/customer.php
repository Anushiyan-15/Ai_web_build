<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$projectRoot = dirname(__DIR__, 2);
require_once $projectRoot . '/config.php';
require_once $projectRoot . '/includes/BusinessOps.php';

$slug = preg_replace('/[^a-z0-9_-]/i', '', basename(__DIR__));
if ($slug === '') {
    http_response_code(400);
    exit('Invalid bus site.');
}

$sessionKey = 'bus_customer_' . $slug;
$pendingRegistrationKey = 'bus_customer_registration_' . $slug;
$accountDir = bops_root() . '/collections/' . $slug;
$accountFile = $accountDir . '/customer_accounts.json';
$verificationFile = $accountDir . '/customer_verifications.json';
$esc = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$readAccounts = static function () use ($accountFile): array {
    if (!is_file($accountFile)) return [];
    $handle = fopen($accountFile, 'rb');
    if ($handle === false) throw new RuntimeException('Unable to open customer accounts.');
    try {
        if (!flock($handle, LOCK_SH)) throw new RuntimeException('Unable to lock customer accounts.');
        $contents = stream_get_contents($handle);
        if ($contents === false) throw new RuntimeException('Unable to read customer accounts.');
        $accounts = $contents === '' ? [] : json_decode($contents, true);
        if (!is_array($accounts)) throw new RuntimeException('Customer account data is invalid.');
        return $accounts;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
};
$updateAccounts = static function (callable $update) use ($accountDir, $accountFile): mixed {
    if (!is_dir($accountDir) && !mkdir($accountDir, 0755, true) && !is_dir($accountDir)) {
        throw new RuntimeException('Unable to create the customer account store.');
    }
    $handle = fopen($accountFile, 'c+');
    if ($handle === false) throw new RuntimeException('Unable to open the customer account store.');
    try {
        if (!flock($handle, LOCK_EX)) throw new RuntimeException('Unable to lock the customer account store.');
        $contents = stream_get_contents($handle);
        if ($contents === false) throw new RuntimeException('Unable to read the customer account store.');
        $accounts = $contents === '' ? [] : json_decode($contents, true);
        if (!is_array($accounts)) throw new RuntimeException('Customer account data is invalid.');
        $result = $update($accounts);
        $json = json_encode($accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (!rewind($handle) || !ftruncate($handle, 0) || fwrite($handle, $json) !== strlen($json) || !fflush($handle)) {
            throw new RuntimeException('Unable to save customer account changes.');
        }
        return $result;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
};
$updateVerifications = static function (callable $update) use ($accountDir, $verificationFile): mixed {
    if (!is_dir($accountDir) && !mkdir($accountDir, 0755, true) && !is_dir($accountDir)) {
        throw new RuntimeException('Unable to create the customer verification store.');
    }
    $handle = fopen($verificationFile, 'c+');
    if ($handle === false) throw new RuntimeException('Unable to open the customer verification store.');
    try {
        if (!flock($handle, LOCK_EX)) throw new RuntimeException('Unable to lock the customer verification store.');
        $contents = stream_get_contents($handle);
        if ($contents === false) throw new RuntimeException('Unable to read the customer verification store.');
        $verifications = $contents === '' ? [] : json_decode($contents, true);
        if (!is_array($verifications)) throw new RuntimeException('Customer verification data is invalid.');
        $result = $update($verifications);
        $json = json_encode($verifications, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (!rewind($handle) || !ftruncate($handle, 0) || fwrite($handle, $json) !== strlen($json) || !fflush($handle)) {
            throw new RuntimeException('Unable to save customer verification changes.');
        }
        return $result;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
};

$siteName = basename(__DIR__);
$metaFile = __DIR__ . '/meta.json';
if (is_file($metaFile)) {
    $meta = json_decode((string)file_get_contents($metaFile), true);
    if (is_array($meta) && !empty($meta['site_name'])) $siteName = (string)$meta['site_name'];
}
$csrfKey = 'bus_customer_csrf_' . $slug;
if (empty($_SESSION[$csrfKey])) $_SESSION[$csrfKey] = bin2hex(random_bytes(32));
$csrf = (string)$_SESSION[$csrfKey];
$requestVerification = static function (string $email) use ($updateVerifications, $projectRoot, $siteName): array {
    $verificationKey = hash('sha256', $email);
    $issued = $updateVerifications(static function (array &$verifications) use ($verificationKey): array {
        $now = time();
        $record = $verifications[$verificationKey] ?? [];
        if (!empty($record['sent_at']) && $now - (int)$record['sent_at'] < 60) {
            return ['success' => false, 'error' => 'A verification code was recently sent. Wait one minute before trying again.'];
        }
        $sendTimes = array_values(array_filter((array)($record['send_times'] ?? []), static fn($sentAt) => $now - (int)$sentAt < 3600));
        if (count($sendTimes) >= 5) {
            return ['success' => false, 'error' => 'Too many verification codes were requested. Try again in an hour.'];
        }
        $code = (string)random_int(100000, 999999);
        $verifications[$verificationKey] = [
            'code_hash' => password_hash($code, PASSWORD_DEFAULT),
            'expires_at' => $now + 600,
            'attempts' => 0,
            'sent_at' => $now,
            'send_times' => array_merge($sendTimes, [$now]),
        ];
        return ['success' => true, 'code' => $code];
    });
    if (empty($issued['success'])) return $issued;

    require_once $projectRoot . '/includes/Mailer.php';
    $safeSiteName = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');
    $code = (string)$issued['code'];
    $sent = Mailer::send(
        $email,
        'Verify your email for ' . $siteName,
        '<p>Use this code to activate your customer account for <strong>' . $safeSiteName
            . '</strong>. It expires in 10 minutes.</p><p style="font:700 28px monospace;letter-spacing:.25em">'
            . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</p>',
        'Your ' . $siteName . ' verification code is ' . $code . '. It expires in 10 minutes.'
    );
    if (empty($sent['success'])) {
        error_log('[bus customer portal] verification email failed: ' . (string)($sent['error'] ?? 'unknown error'));
        return ['success' => true, 'dev_code' => $code];
    }
    return ['success' => true];
};
$verifyRegistration = static function (string $email, string $code) use ($updateVerifications): array {
    $verificationKey = hash('sha256', $email);
    return $updateVerifications(static function (array &$verifications) use ($verificationKey, $code): array {
        $record = $verifications[$verificationKey] ?? null;
        if (!is_array($record)) return ['success' => false, 'error' => 'No verification code found. Request a new one.'];
        if (time() > (int)($record['expires_at'] ?? 0)) {
            unset($record['code_hash'], $record['expires_at'], $record['attempts']);
            $verifications[$verificationKey] = $record;
            return ['success' => false, 'error' => 'The verification code expired. Request a new one.'];
        }
        if ((int)($record['attempts'] ?? 0) >= 5) {
            unset($record['code_hash'], $record['expires_at'], $record['attempts']);
            $verifications[$verificationKey] = $record;
            return ['success' => false, 'error' => 'Too many incorrect codes. Request a new one.'];
        }
        if (!preg_match('/^\d{6}$/', $code) || !password_verify($code, (string)($record['code_hash'] ?? ''))) {
            $record['attempts'] = (int)($record['attempts'] ?? 0) + 1;
            $verifications[$verificationKey] = $record;
            return ['success' => false, 'error' => 'The verification code is incorrect.'];
        }
        unset($verifications[$verificationKey]);
        return ['success' => true];
    });
};
$notice = '';
$noticeType = 'error';
$mode = (string)($_GET['mode'] ?? 'login') === 'register' ? 'register' : 'login';
if (!empty($_SESSION[$pendingRegistrationKey])) $mode = 'verify';
// Login-attempt protection: 5 failures per email+IP per 15 minutes.
$loginAttemptsFile = $accountDir . '/customer_login_attempts.json';
$loginRateLimited = static function (string $email) use ($loginAttemptsFile): bool {
    if (!is_file($loginAttemptsFile)) return false;
    $attempts = json_decode((string)@file_get_contents($loginAttemptsFile), true);
    if (!is_array($attempts)) return false;
    $key = hash('sha256', strtolower($email) . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'cli'));
    $now = time();
    $recent = array_values(array_filter((array)($attempts[$key] ?? []), static fn($t) => ($now - (int)$t) < 900));
    return count($recent) >= 5;
};
$recordLoginFailure = static function (string $email) use ($accountDir, $loginAttemptsFile): void {
    if (!is_dir($accountDir) && !mkdir($accountDir, 0755, true) && !is_dir($accountDir)) return;
    $handle = @fopen($loginAttemptsFile, 'c+');
    if ($handle === false) return;
    try {
        if (!flock($handle, LOCK_EX)) return;
        $contents = stream_get_contents($handle);
        $attempts = ($contents !== false && trim((string)$contents) !== '') ? (json_decode($contents, true) ?: []) : [];
        if (!is_array($attempts)) $attempts = [];
        $key = hash('sha256', strtolower($email) . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'cli'));
        $now = time();
        $recent = array_values(array_filter((array)($attempts[$key] ?? []), static fn($t) => ($now - (int)$t) < 900));
        $recent[] = $now;
        $attempts[$key] = array_slice($recent, -10);
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, (string)json_encode($attempts));
        fflush($handle);
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
};
$clearLoginFailures = static function (string $email) use ($loginAttemptsFile): void {
    if (!is_file($loginAttemptsFile)) return;
    $handle = @fopen($loginAttemptsFile, 'c+');
    if ($handle === false) return;
    try {
        if (!flock($handle, LOCK_EX)) return;
        $contents = stream_get_contents($handle);
        $attempts = ($contents !== false && trim((string)$contents) !== '') ? (json_decode($contents, true) ?: []) : [];
        if (!is_array($attempts)) $attempts = [];
        unset($attempts[hash('sha256', strtolower($email) . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'cli'))]);
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, (string)json_encode($attempts));
        fflush($handle);
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postedToken = (string)($_POST['csrf'] ?? '');
    if ($postedToken === '' || !hash_equals($csrf, $postedToken)) {
        $notice = 'Your session has expired. Refresh this page and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'logout') {
                unset($_SESSION[$sessionKey]);
                session_regenerate_id(true);
                header('Location: customer.php');
                exit;
            }
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $password = (string)($_POST['password'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Enter a valid email address.');
            }
            if (in_array($action, ['register', 'login'], true) && $password === '') {
                throw new InvalidArgumentException('Enter your password.');
            }
            if ($action === 'register') {
                $name = trim((string)($_POST['name'] ?? ''));
                $phone = trim((string)($_POST['phone'] ?? ''));
                if ($name === '' || strlen($name) > 120) throw new InvalidArgumentException('Enter your name.');
                if (strlen($password) < 8) throw new InvalidArgumentException('Use a password with at least 8 characters.');
                // Create the account immediately — no mandatory email
                // verification, no verification code, no SMTP required
                // (Phase 5). Passwords are hashed with password_hash().
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $created = $updateAccounts(static function (array &$accounts) use ($name, $email, $phone, $passwordHash): array {
                    foreach ($accounts as $existing) {
                        if (is_array($existing) && strtolower((string)($existing['email'] ?? '')) === $email) {
                            throw new InvalidArgumentException('An account already exists for this email. Sign in instead.');
                        }
                    }
                    $account = [
                        'name' => (string)$name,
                        'email' => (string)$email,
                        'phone' => (string)$phone,
                        'password_hash' => (string)$passwordHash,
                        'email_verified_at' => null,
                        'created_at' => date('c'),
                    ];
                    $accounts[] = $account;
                    return $account;
                });
                session_regenerate_id(true);
                $_SESSION[$sessionKey] = ['name' => (string)$name, 'email' => (string)$email, 'phone' => (string)$phone];
                if (function_exists('bops_upsert_customer')) {
                    bops_upsert_customer($slug, [
                        'name' => (string)$name,
                        'email' => (string)$email,
                        'phone' => (string)$phone,
                        'status' => 'active'
                    ]);
                }
                $notice = 'Account created and you are signed in.';
                $noticeType = 'success';
                header('Location: customer.php');
                exit;
            } elseif ($action === 'resend_register') {
                $pending = $_SESSION[$pendingRegistrationKey] ?? null;
                if (!is_array($pending) || strtolower((string)($pending['email'] ?? '')) !== $email) {
                    unset($_SESSION[$pendingRegistrationKey]);
                    throw new InvalidArgumentException('Your registration session expired. Start again.');
                }
                if (time() > (int)($pending['expires_at'] ?? 0)) {
                    unset($_SESSION[$pendingRegistrationKey]);
                    throw new InvalidArgumentException('Your registration expired. Start again.');
                }
                $sent = $requestVerification($email);
                if (empty($sent['success'])) throw new InvalidArgumentException((string)($sent['error'] ?? 'Unable to send verification code.'));
                $mode = 'verify';
                $notice = 'A new verification code was sent to your email address.' . (!empty($sent['dev_code']) ? ' (Verification code: <strong>' . htmlspecialchars($sent['dev_code']) . '</strong>)' : '');
                $noticeType = 'success';
            } elseif ($action === 'verify_register') {
                $pending = $_SESSION[$pendingRegistrationKey] ?? null;
                if (!is_array($pending) || strtolower((string)($pending['email'] ?? '')) !== $email) {
                    unset($_SESSION[$pendingRegistrationKey]);
                    throw new InvalidArgumentException('Your registration session expired. Start again.');
                }
                if (time() > (int)($pending['expires_at'] ?? 0)) {
                    unset($_SESSION[$pendingRegistrationKey]);
                    throw new InvalidArgumentException('Your registration expired. Start again.');
                }
                $verified = $verifyRegistration($email, (string)($_POST['code'] ?? ''));
                if (empty($verified['success'])) {
                    $mode = 'verify';
                    throw new InvalidArgumentException((string)($verified['error'] ?? 'Unable to verify this code.'));
                }
                $account = $updateAccounts(static function (array &$accounts) use ($pending): array {
                    foreach ($accounts as $existing) {
                        if (is_array($existing) && strtolower((string)($existing['email'] ?? '')) === strtolower((string)$pending['email'])) {
                            throw new InvalidArgumentException('An account already exists for this email. Sign in instead.');
                        }
                    }
                    $created = [
                        'name' => (string)$pending['name'],
                        'email' => (string)$pending['email'],
                        'phone' => (string)$pending['phone'],
                        'password_hash' => (string)$pending['password_hash'],
                        'email_verified_at' => date('c'),
                        'created_at' => date('c'),
                    ];
                    $accounts[] = $created;
                    return $created;
                });
                unset($_SESSION[$pendingRegistrationKey]);
                session_regenerate_id(true);
                $_SESSION[$sessionKey] = ['name' => $account['name'], 'email' => $account['email'], 'phone' => $account['phone']];
                if (function_exists('bops_upsert_customer')) {
                    bops_upsert_customer($slug, [
                        'name' => (string)$account['name'],
                        'email' => (string)$account['email'],
                        'phone' => (string)$account['phone'],
                        'status' => 'verified'
                    ]);
                }
                header('Location: customer.php');
                exit;
            }
            if ($action === 'login') {
                if ($loginRateLimited($email)) {
                    throw new InvalidArgumentException('Too many sign-in attempts. Wait 15 minutes and try again.');
                }
                $account = null;
                foreach ($readAccounts() as $saved) {
                    if (is_array($saved) && strtolower((string)($saved['email'] ?? '')) === $email) {
                        $account = $saved;
                        break;
                    }
                }
                if (!$account || !password_verify($password, (string)($account['password_hash'] ?? ''))) {
                    $recordLoginFailure($email);
                    throw new InvalidArgumentException('Email or password is incorrect. If this is your first visit, create an account for this bus service.');
                }
                $clearLoginFailures($email);
                session_regenerate_id(true);
                $_SESSION[$sessionKey] = [
                    'name' => (string)($account['name'] ?? ''),
                    'email' => (string)$account['email'],
                    'phone' => (string)($account['phone'] ?? ''),
                ];
                header('Location: customer.php');
                exit;
            }
            if ($action === 'update_profile') {
                $sessionUser = $_SESSION[$sessionKey] ?? null;
                if (!is_array($sessionUser) || empty($sessionUser['email'])) {
                    throw new InvalidArgumentException('Sign in to update your profile.');
                }
                $ownerEmail = strtolower((string)$sessionUser['email']);
                $name = trim((string)($_POST['name'] ?? ''));
                $phone = trim((string)($_POST['phone'] ?? ''));
                if ($name === '' || strlen($name) > 120) throw new InvalidArgumentException('Enter your name.');
                if (strlen($phone) > 30) throw new InvalidArgumentException('Phone number is too long.');
                $updated = $updateAccounts(static function (array &$accounts) use ($ownerEmail, $name, $phone): array {
                    foreach ($accounts as &$saved) {
                        if (is_array($saved) && strtolower((string)($saved['email'] ?? '')) === $ownerEmail) {
                            $saved['name'] = $name;
                            $saved['phone'] = $phone;
                            return $saved;
                        }
                    }
                    unset($saved);
                    throw new InvalidArgumentException('Account not found. Sign in again.');
                });
                $_SESSION[$sessionKey] = ['name' => (string)$updated['name'], 'email' => (string)$updated['email'], 'phone' => (string)($updated['phone'] ?? '')];
                $notice = 'Profile updated.';
                $noticeType = 'success';
            }
            if ($action === 'cancel_booking') {
                $sessionUser = $_SESSION[$sessionKey] ?? null;
                if (!is_array($sessionUser) || empty($sessionUser['email'])) {
                    throw new InvalidArgumentException('Sign in to cancel a booking.');
                }
                $bookingId = trim((string)($_POST['booking_id'] ?? $_POST['ref'] ?? ''));
                if ($bookingId === '') throw new InvalidArgumentException('Missing booking reference.');
                $hadCustomerUser = array_key_exists('customer_user', $_SESSION);
                $previousCustomerUser = $_SESSION['customer_user'] ?? null;
                $hadAdminAuth = array_key_exists('admin_auth', $_SESSION);
                $previousAdminAuth = $_SESSION['admin_auth'] ?? null;
                unset($_SESSION['admin_auth']);
                $_SESSION['customer_user'] = ['email' => (string)$sessionUser['email']];
                try {
                    $cancelResponse = bops_dispatch(['action' => 'cancel', 'entity' => 'bookings', 'slug' => $slug, 'id' => $bookingId, 'email' => (string)$sessionUser['email']]);
                } finally {
                    if ($hadCustomerUser) $_SESSION['customer_user'] = $previousCustomerUser;
                    else unset($_SESSION['customer_user']);
                    if ($hadAdminAuth) $_SESSION['admin_auth'] = $previousAdminAuth;
                }
                if (($cancelResponse['http'] ?? 500) !== 200 || empty($cancelResponse['body']['success'])) {
                    throw new InvalidArgumentException((string)($cancelResponse['body']['error'] ?? 'Cancellation failed.'));
                }
                $notice = 'Booking cancelled. Seat capacity was released.';
                $noticeType = 'success';
            }
            throw new InvalidArgumentException('Choose sign in or create account.');
        } catch (InvalidArgumentException $error) {
            $notice = $error->getMessage();
            if ((string)($_POST['action'] ?? '') === 'register') $mode = 'register';
            elseif (in_array((string)($_POST['action'] ?? ''), ['verify_register', 'resend_register'], true)) {
                $mode = !empty($_SESSION[$pendingRegistrationKey]) ? 'verify' : 'register';
            }
            else $mode = 'login';
        } catch (Throwable $error) {
            error_log('[bus customer portal] ' . $error->getMessage());
            $notice = 'We could not access your account right now. Please try again shortly.';
        }
    }
}

$user = $_SESSION[$sessionKey] ?? null;
$bookings = [];
$historyError = '';
if (is_array($user) && filter_var((string)($user['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
    $hadCustomerUser = array_key_exists('customer_user', $_SESSION);
    $previousCustomerUser = $_SESSION['customer_user'] ?? null;
    $hadAdminAuth = array_key_exists('admin_auth', $_SESSION);
    $previousAdminAuth = $_SESSION['admin_auth'] ?? null;
    unset($_SESSION['admin_auth']);
    $_SESSION['customer_user'] = ['email' => (string)$user['email']];
    try {
        $response = bops_dispatch(['action' => 'list', 'entity' => 'bookings', 'slug' => $slug]);
    } finally {
        if ($hadCustomerUser) $_SESSION['customer_user'] = $previousCustomerUser;
        else unset($_SESSION['customer_user']);
        if ($hadAdminAuth) $_SESSION['admin_auth'] = $previousAdminAuth;
    }
    if (($response['http'] ?? 500) === 200 && !empty($response['body']['success'])) {
        $bookings = $response['body']['data'] ?? [];
        usort($bookings, static fn($a, $b) =>
            (strtotime((string)($b['created_at'] ?? '')) ?: 0) <=> (strtotime((string)($a['created_at'] ?? '')) ?: 0)
        );
    } else {
        $historyError = (string)($response['body']['error'] ?? 'Booking history is temporarily unavailable.');
        http_response_code((int)($response['http'] ?? 500));
    }
}
$statusLabel = static fn($status) => ucfirst(str_replace('_', ' ', (string)$status));
$verifyBase = 'verify.php';
try {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host !== '') {
        $dir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/customer.php'))), '/');
        $verifyBase = $scheme . '://' . $host . ($dir !== '' ? $dir : '') . '/verify.php';
    }
} catch (Throwable $e) { $verifyBase = 'verify.php'; }
$ticketVerifyUrl = static function (array $booking) use ($verifyBase): string {
    $ref = (string)($booking['ref'] ?? $booking['id'] ?? '');
    if ($ref === '') return $verifyBase;
    $url = $verifyBase . '?ref=' . rawurlencode($ref);
    if (!empty($booking['verify_token'])) $url .= '&t=' . rawurlencode((string)$booking['verify_token']);
    return $url;
};
$ticketQrImg = static function (array $booking) use ($ticketVerifyUrl, $esc): string {
    $url = $ticketVerifyUrl($booking);
    $qr = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' . rawurlencode($url);
    $ref = (string)($booking['ref'] ?? '');
    return '<img src="' . $esc($qr) . '" width="180" height="180" alt="Ticket QR ' . $esc($ref) . '" loading="lazy" style="border-radius:12px;background:#fff;padding:6px;max-width:100%;" onerror="this.outerHTML=\'<div>Show ref ' . $esc($ref) . ' at boarding</div>\'">';
};
$upcomingBookings = [];
$pastBookings = [];
$today = date('Y-m-d');
foreach ($bookings as $b) {
    $tripDate = substr((string)($b['trip_date'] ?? ''), 0, 10);
    $st = strtolower((string)($b['status'] ?? ''));
    if ($tripDate !== '' && $tripDate < $today) $pastBookings[] = $b;
    elseif (in_array($st, ['cancelled', 'rejected', 'expired'], true)) $pastBookings[] = $b;
    else $upcomingBookings[] = $b;
}
$paidCount = 0;
foreach ($bookings as $b) { if (($b['payment_status'] ?? '') === 'paid') $paidCount++; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Customer account | <?= $esc($siteName) ?></title>
  <style>
    :root{font-family:Arial,Helvetica,sans-serif;color:#202b2b;background:#f3f5f3;font-synthesis:none}
    *{box-sizing:border-box}body{margin:0;min-height:100vh;padding:clamp(1rem,5vw,4rem)}
    a{color:#17675a;text-decoration:none;font-weight:700}a:hover{text-decoration:underline}
    main{max-width:1080px;margin:0 auto}.masthead{display:grid;grid-template-columns:1fr auto;gap:2rem;align-items:end;padding:0 0 1.5rem;border-bottom:1px solid #d9dfda}
    .eyebrow{margin:0 0 .6rem;color:#64716c;font-size:.78rem;font-weight:700;letter-spacing:.13em;text-transform:uppercase}
    h1{margin:0;font-size:clamp(1.8rem,4vw,3rem);letter-spacing:-.055em;line-height:1.05}
    .lede{max-width:52ch;margin:.8rem 0 0;color:#64716c;line-height:1.6}
    .mast-actions{display:flex;gap:1.1rem;align-items:center;flex-wrap:wrap}
    .content{display:grid;grid-template-columns:minmax(230px,.72fr) minmax(0,1.8fr);gap:clamp(1.4rem,4vw,3rem);padding-top:2rem}
    .identity{align-self:start;border-top:3px solid #17675a;padding:1.2rem 0}
    .identity strong{display:block;font-size:1.1rem}.identity .muted{display:block;margin-top:.35rem}
    .muted{color:#64716c;line-height:1.5}.section-title{display:flex;justify-content:space-between;align-items:baseline;gap:1rem;padding-bottom:.8rem;border-bottom:1px solid #d9dfda}
    h2{margin:0;font-size:1.25rem;letter-spacing:-.025em}.count{font-size:.8rem;color:#64716c;font-variant-numeric:tabular-nums}
    .booking{display:grid;grid-template-columns:1fr auto;gap:.6rem 1rem;padding:1.1rem 0;border-bottom:1px solid #d9dfda}
    .booking-main{min-width:0}.ref{font-weight:750}.route{display:block;margin-top:.3rem;color:#64716c}
    .booking-meta{display:flex;gap:.7rem 1rem;flex-wrap:wrap;margin-top:.7rem;color:#64716c;font-size:.82rem}
    .status{align-self:start;padding:.35rem .55rem;background:#e4ece7;border-radius:3px;font-size:.75rem;font-weight:750;white-space:nowrap}
    .payment{grid-column:1/-1;color:#52615c;font-size:.83rem}
    .empty,.error,.notice{padding:1rem 0;border-bottom:1px solid #d9dfda;color:#64716c;line-height:1.55}
    .error,.notice.error{color:#8c3029}.notice{margin:1.2rem 0;padding:.8rem 1rem;border-left:3px solid #8c3029;background:#f9eeec}
    .notice.success{border-color:#17675a;background:#eaf2ed;color:#24584d}
    .form-layout{max-width:480px;padding-top:2rem}.form{display:grid;gap:1rem;margin-top:1.3rem}
    .field{display:grid;gap:.4rem}.field label{font-size:.85rem;font-weight:700}.field input{width:100%;min-height:46px;padding:.7rem .8rem;border:1px solid #cbd4ce;border-radius:4px;background:#fff;color:inherit;font:inherit}
    .field input:focus{outline:2px solid #17675a;outline-offset:1px}.help{margin:0;color:#64716c;font-size:.8rem;line-height:1.45}
    button{min-height:46px;padding:.7rem 1rem;border:0;border-radius:4px;background:#17675a;color:#fff;font:inherit;font-weight:700;cursor:pointer;transition:transform .2s ease,background .2s ease}
    button:hover{background:#105447}button:active{transform:translateY(1px) scale(.99)}
    .switch{margin-top:1rem;color:#64716c;line-height:1.5}
    @media(max-width:680px){body{padding:1.2rem}.masthead{grid-template-columns:1fr;align-items:start;gap:1rem}.content{grid-template-columns:1fr;gap:1rem;padding-top:1rem}.identity{padding:.7rem 0}.booking{grid-template-columns:1fr}.status{grid-row:1;justify-self:start}.payment{grid-column:auto}}
  </style>
</head>
<body>
<main>
  <header class="masthead">
    <div>
      <p class="eyebrow"><?= $esc($siteName) ?> · Customer service</p>
      <h1><?= is_array($user) ? 'Your journeys' : ($mode === 'verify' ? 'Verify your email' : ($mode === 'register' ? 'Create your account' : 'Sign in to your account')) ?></h1>
      <p class="lede"><?= is_array($user) ? 'Your tickets and booking updates, gathered in one place.' : ($mode === 'verify' ? 'Confirm you own this email address before linking it to booking history.' : 'Use an account created for this bus service to view your booking history.') ?></p>
    </div>
    <nav class="mast-actions" aria-label="Customer navigation">
      <a href="index.html">Book a journey</a>
      <?php if (is_array($user)): ?>
        <form method="post" style="margin:0">
          <input type="hidden" name="csrf" value="<?= $esc($csrf) ?>">
          <input type="hidden" name="action" value="logout">
          <button type="submit">Sign out</button>
        </form>
      <?php endif; ?>
    </nav>
  </header>

  <?php if ($notice !== ''): ?><div class="notice <?= $esc($noticeType) ?>" role="alert"><?= $esc($notice) ?></div><?php endif; ?>

  <?php if (is_array($user)): ?>
    <div class="content">
      <aside class="identity">
        <p class="eyebrow">Account</p>
        <strong><?= $esc($user['name'] ?? 'Customer') ?></strong>
        <span class="muted"><?= $esc($user['email'] ?? '') ?></span>
        <?php if (!empty($user['phone'])): ?><span class="muted"><?= $esc($user['phone']) ?></span><?php endif; ?>
        <div class="stats" style="display:grid;gap:.5rem;margin-top:1rem">
          <span class="muted">Bookings: <strong><?= count($bookings) ?></strong></span>
          <span class="muted">Upcoming trips: <strong><?= count($upcomingBookings) ?></strong></span>
          <span class="muted">Paid: <strong><?= $paidCount ?></strong></span>
        </div>
        <details style="margin-top:1rem">
          <summary style="cursor:pointer;font-weight:700">Edit profile</summary>
          <form class="form" method="post" action="customer.php" style="margin-top:.75rem">
            <input type="hidden" name="csrf" value="<?= $esc($csrf) ?>">
            <input type="hidden" name="action" value="update_profile">
            <div class="field"><label for="profile-name">Name</label><input id="profile-name" name="name" maxlength="120" required value="<?= $esc($user['name'] ?? '') ?>"></div>
            <div class="field"><label for="profile-phone">Phone</label><input id="profile-phone" name="phone" type="tel" maxlength="30" value="<?= $esc($user['phone'] ?? '') ?>"></div>
            <button type="submit">Save profile</button>
          </form>
        </details>
      </aside>
      <div style="min-width:0">
      <section aria-labelledby="overview-heading">
        <div class="section-title"><h2 id="overview-heading">Overview</h2><span class="count"><?= count($bookings) ?> <?= count($bookings) === 1 ? 'booking' : 'bookings' ?></span></div>
        <?php if ($historyError !== ''): ?>
          <p class="error" role="alert"><?= $esc($historyError) ?></p>
        <?php elseif (!$bookings): ?>
          <p class="empty">No bookings are linked to this email yet. Book a journey using <?= $esc($user['email']) ?> and it will appear here.</p>
        <?php else: ?>
          <p class="muted">Upcoming: <?= count($upcomingBookings) ?> · History: <?= count($pastBookings) ?> · Paid: <?= $paidCount ?></p>
        <?php endif; ?>
      </section>
      <section aria-labelledby="upcoming-heading" style="margin-top:2rem">
        <div class="section-title"><h2 id="upcoming-heading">Upcoming trips</h2><span class="count"><?= count($upcomingBookings) ?></span></div>
        <?php if ($historyError !== ''): ?>
          <p class="error" role="alert"><?= $esc($historyError) ?></p>
        <?php elseif (!$upcomingBookings): ?>
          <p class="empty">No upcoming trips. <a href="index.html">Book a journey</a>.</p>
        <?php else: foreach ($upcomingBookings as $booking):
            $booking = function_exists('bops_booking_view') ? bops_booking_view($booking) : $booking;
            $seats = $booking['seats'] ?? [];
            if (!is_array($seats)) $seats = preg_split('/[\s,;|]+/', (string)$seats, -1, PREG_SPLIT_NO_EMPTY);
            $route = trim((string)($booking['route'] ?? ''));
            $tripLine = $route !== '' ? $route : (string)($booking['trip_id'] ?? 'Trip');
            $date = (string)($booking['trip_date'] ?? '');
            if ($date !== '') $tripLine .= ' · ' . $date;
            if (!empty($booking['departure'])) $tripLine .= ' · ' . (string)$booking['departure'];
            $created = strtotime((string)($booking['created_at'] ?? ''));
            $ref = (string)($booking['ref'] ?? 'Ticket pending');
            $ticketUrl = $ticketVerifyUrl($booking);
        ?>
          <article class="booking" data-booking-ref="<?= $esc($ref) ?>">
            <div class="booking-main">
              <span class="ref"><?= $esc($ref) ?></span>
              <span class="route"><?= $esc($tripLine) ?></span>
              <div class="booking-meta">
                <span>Trip <?= $esc($booking['trip_id'] ?? '—') ?></span>
                <span>Seats <?= $esc(implode(', ', array_map('strval', $seats))) ?: '—' ?></span>
                <?php if ($created): ?><span><?= $esc(date('M j, Y · H:i', $created)) ?></span><?php endif; ?>
              </div>
              <div class="ticket-box" style="display:flex;gap:1rem;flex-wrap:wrap;margin-top:.8rem;align-items:center">
                <div><?= $ticketQrImg($booking) ?></div>
                <div style="font-size:.85rem;line-height:1.6">
                  <div><strong><?= $esc($booking['passenger'] ?? '') ?></strong></div>
                  <div>Fare: <?= $esc($booking['currency'] ?? 'LKR') ?> <?= number_format((float)($booking['amount'] ?? 0), 2) ?></div>
                  <div><a href="<?= $esc($ticketUrl) ?>" target="_blank" rel="noopener">Verify ticket</a></div>
                  <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.5rem">
                    <button type="button" onclick="window.print()" style="min-height:38px;padding:.4rem .8rem">Print ticket</button>
                    <button type="button" data-download-ticket="<?= $esc($ref) ?>" style="min-height:38px;padding:.4rem .8rem;background:#334155">Download ticket</button>
                  </div>
                </div>
              </div>
            </div>
            <span class="status"><?= $esc($statusLabel($booking['status'] ?? 'pending')) ?></span>
            <div class="payment">
              Payment: <?= $esc($statusLabel($booking['payment_status'] ?? 'unpaid')) ?>
              <?php if (!empty($booking['payment_id'])): ?> · Ref <?= $esc($booking['payment_id']) ?><?php endif; ?>
              <?php if (isset($booking['pay_amount'])): ?> · <?= $esc($booking['currency'] ?? 'LKR') ?> <?= number_format((float)$booking['pay_amount'], 2) ?> <?= $esc($booking['pay_method'] ?? '') ?><?php endif; ?>
              <?php if (($booking['payment_status'] ?? 'unpaid') !== 'paid'): ?><span> · Pay at counter or online via the booking page; the operator records receipt.</span><?php endif; ?>
            </div>
            <?php if (!in_array(strtolower((string)($booking['status'] ?? '')), ['cancelled', 'rejected', 'expired'], true)): ?>
            <form method="post" action="customer.php" onsubmit="return confirm('Cancel this booking? Seats are released immediately.');" style="grid-column:1/-1;margin:.5rem 0 0">
              <input type="hidden" name="csrf" value="<?= $esc($csrf) ?>">
              <input type="hidden" name="action" value="cancel_booking">
              <input type="hidden" name="booking_id" value="<?= $esc($booking['id'] ?? $ref) ?>">
              <button type="submit" style="background:#b91c1c;min-height:38px">Cancel this booking</button>
            </form>
            <?php endif; ?>
          </article>
        <?php endforeach; endif; ?>
      </section>
      <section aria-labelledby="history-heading" style="margin-top:2rem">
        <div class="section-title"><h2 id="history-heading">Booking history</h2><span class="count"><?= count($bookings) ?> <?= count($bookings) === 1 ? 'booking' : 'bookings' ?></span></div>
        <?php if ($historyError !== ''): ?>
          <p class="error" role="alert"><?= $esc($historyError) ?></p>
        <?php elseif (!$bookings): ?>
          <p class="empty">No bookings are linked to this email yet. Book a journey using <?= $esc($user['email']) ?> and it will appear here.</p>
        <?php else: foreach ($bookings as $booking):
            $booking = function_exists('bops_booking_view') ? bops_booking_view($booking) : $booking;
            $seats = $booking['seats'] ?? [];
            if (!is_array($seats)) $seats = preg_split('/[\s,;|]+/', (string)$seats, -1, PREG_SPLIT_NO_EMPTY);
            $route = trim((string)($booking['route'] ?? ''));
            $tripLine = $route !== '' ? $route : (string)($booking['trip_id'] ?? 'Trip');
            $date = (string)($booking['trip_date'] ?? '');
            if ($date !== '') $tripLine .= ' · ' . $date;
            $created = strtotime((string)($booking['created_at'] ?? ''));
        ?>
          <article class="booking">
            <div class="booking-main">
              <span class="ref"><?= $esc($booking['ref'] ?? 'Ticket pending') ?></span>
              <span class="route"><?= $esc($tripLine) ?></span>
              <div class="booking-meta">
                <span>Trip <?= $esc($booking['trip_id'] ?? '—') ?></span>
                <span>Seats <?= $esc(implode(', ', array_map('strval', $seats))) ?: '—' ?></span>
                <?php if ($created): ?><span><?= $esc(date('M j, Y · H:i', $created)) ?></span><?php endif; ?>
              </div>
            </div>
            <span class="status"><?= $esc($statusLabel($booking['status'] ?? 'pending')) ?></span>
            <div class="payment">
              Payment: <?= $esc($statusLabel($booking['payment_status'] ?? 'unpaid')) ?>
              <?php if (!empty($booking['payment_id'])): ?> · Ref <?= $esc($booking['payment_id']) ?><?php endif; ?>
              <?php if (isset($booking['pay_amount'])): ?> · <?= $esc($booking['currency'] ?? 'LKR') ?> <?= number_format((float)$booking['pay_amount'], 2) ?> <?= $esc($booking['pay_method'] ?? '') ?><?php endif; ?>
            </div>
          </article>
        <?php endforeach; endif; ?>
      </section>
      <section aria-labelledby="payments-heading" style="margin-top:2rem">
        <div class="section-title"><h2 id="payments-heading">Payments</h2><span class="count"><?= $paidCount ?> paid</span></div>
        <?php if (!$bookings): ?>
          <p class="empty">No payment records yet.</p>
        <?php else: ?>
          <?php foreach ($bookings as $booking): ?>
            <div class="payment" style="padding:.5rem 0;border-bottom:1px solid #d9dfda">
              <?= $esc($booking['ref'] ?? '') ?> · Payment: <?= $esc($statusLabel($booking['payment_status'] ?? 'unpaid')) ?>
              <?php if (!empty($booking['payment_id'])): ?> · <?= $esc($booking['payment_id']) ?><?php endif; ?>
              <?php if (isset($booking['amount'])): ?> · <?= $esc($booking['currency'] ?? 'LKR') ?> <?= number_format((float)$booking['amount'], 2) ?><?php endif; ?>
            </div>
          <?php endforeach; ?>
          <p class="muted" style="font-size:.85rem">Bookings stay unpaid until the operator records cash/card receipt in the admin panel. Counter or online choices on the booking page only record your intention.</p>
        <?php endif; ?>
      </section>
      </div>
    </div>
    <script>
    document.querySelectorAll('[data-download-ticket]').forEach(function(btn){
      btn.addEventListener('click',function(){
        var card=btn.closest('[data-booking-ref]');
        if(!card){alert('Ticket not found.');return;}
        var html='<!doctype html><html><head><meta charset="utf-8"><title>Ticket '+card.getAttribute('data-booking-ref')+'</title></head><body>'+card.innerHTML+'</body></html>';
        var blob=new Blob([html],{type:'text/html'});
        var a=document.createElement('a');
        a.href=URL.createObjectURL(blob);
        a.download=card.getAttribute('data-booking-ref')+'.html';
        a.click();
        setTimeout(function(){URL.revokeObjectURL(a.href);},1000);
      });
    });
    </script>
  <?php else: ?>
    <section class="form-layout">
      <p class="eyebrow"><?= $mode === 'verify' ? 'Email verification' : ($mode === 'register' ? 'New customer' : 'Returning customer') ?></p>
      <h2><?= $mode === 'verify' ? 'Enter your six-digit code' : ($mode === 'register' ? 'Create a bus-service account' : 'Access your booking history') ?></h2>
      <p class="lede">This account is for <?= $esc($siteName) ?> only. It is separate from any account used on the website-builder platform.</p>
      <form class="form" method="post" action="customer.php<?= $mode === 'register' ? '?mode=register' : '' ?>">
        <input type="hidden" name="csrf" value="<?= $esc($csrf) ?>">
        <?php if ($mode === 'verify'):
            $pendingRegistration = $_SESSION[$pendingRegistrationKey] ?? [];
        ?>
          <input type="hidden" name="action" value="verify_register">
          <input type="hidden" name="email" value="<?= $esc($pendingRegistration['email'] ?? '') ?>">
          <p class="help">A code was sent to <?= $esc($pendingRegistration['email'] ?? '') ?>. It expires in 10 minutes.</p>
          <div class="field"><label for="code">Verification code</label><input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required><p class="help">Enter the six-digit code from your email.</p></div>
          <button type="submit">Verify and create account</button>
          <p class="switch">Code missing or expired?</p>
        <?php else: ?>
          <input type="hidden" name="action" value="<?= $mode === 'register' ? 'register' : 'login' ?>">
          <?php if ($mode === 'register'): ?>
            <div class="field"><label for="name">Name</label><input id="name" name="name" autocomplete="name" maxlength="120" required><p class="help">Shown in your customer account.</p></div>
            <div class="field"><label for="phone">Phone (optional)</label><input id="phone" name="phone" type="tel" autocomplete="tel"><p class="help">Used only to identify your customer profile.</p></div>
          <?php endif; ?>
          <div class="field"><label for="email">Email address</label><input id="email" name="email" type="email" autocomplete="email" required><p class="help">Use the same email address you enter when booking.</p></div>
          <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="<?= $mode === 'register' ? 'new-password' : 'current-password' ?>" <?= $mode === 'register' ? 'minlength="8"' : '' ?> required><p class="help"><?= $mode === 'register' ? 'At least 8 characters.' : 'Your password for this bus service.' ?></p></div>
          <button type="submit"><?= $mode === 'register' ? 'Continue to email verification' : 'Sign in' ?></button>
        <?php endif; ?>
      </form>
      <?php if ($mode === 'verify'): ?>
        <form class="form" method="post" action="customer.php">
          <input type="hidden" name="csrf" value="<?= $esc($csrf) ?>">
          <input type="hidden" name="action" value="resend_register">
          <input type="hidden" name="email" value="<?= $esc($pendingRegistration['email'] ?? '') ?>">
          <button type="submit">Send a new code</button>
        </form>
      <?php endif; ?>
      <?php if ($mode !== 'verify'): ?>
        <p class="switch"><?= $mode === 'register' ? 'Already registered?' : 'First time here?' ?>
          <a href="customer.php<?= $mode === 'register' ? '' : '?mode=register' ?>"><?= $mode === 'register' ? 'Sign in' : 'Create an account' ?></a>
        </p>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</main>
</body>
</html>
