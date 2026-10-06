<?php
// ═══════════════════════════════════════════════════════════════
//  includes/SocialAuth.php — Real-world social login (OAuth 2.0)
//  Providers: google, facebook, instagram.
//
//  Flow (standard authorization-code grant, same as production apps):
//    1. customer-portal.php?action=social&provider=X
//       → state token into session → 302 to provider authorize URL.
//    2. Provider authenticates the user, redirects back to
//       customer-portal.php?action=social_callback&provider=X&code=…&state=…
//    3. State verified → code exchanged for access token (server-to-server)
//       → userinfo fetched → local account found-or-created → session login.
//
//  Credentials come from config/social.php (or env, see social.example.php).
//  When a provider is NOT configured, login URL builders return null and
//  the portal shows a setup notice instead of calling the provider.
// ═══════════════════════════════════════════════════════════════

function social_providers(): array {
    return ['google', 'facebook', 'instagram'];
}

function social_is_provider(string $p): bool {
    return in_array(strtolower($p), social_providers(), true);
}

/** Read a credential: constant first, then env, else ''. */
function social_cred(string $constName, string $envName): string {
    if (defined($constName)) {
        $v = trim((string)constant($constName));
        if ($v !== '' && stripos($v, 'YOUR_') !== 0) return $v;
    }
    $e = trim((string)(getenv($envName) ?: ''));
    if ($e !== '' && stripos($e, 'YOUR_') !== 0) return $e;
    return '';
}

/** Exact redirect URI registered in the provider console. Must match byte-for-byte. */
function social_redirect_uri(string $provider): string {
    $base = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
    return $base . '/customer-portal.php?action=social_callback&provider=' . urlencode($provider);
}

function social_is_configured(string $provider): bool {
    $provider = strtolower($provider);
    if ($provider === 'google') {
        return social_cred('SOCIAL_GOOGLE_CLIENT_ID', 'SOCIAL_GOOGLE_CLIENT_ID') !== ''
            && social_cred('SOCIAL_GOOGLE_CLIENT_SECRET', 'SOCIAL_GOOGLE_CLIENT_SECRET') !== '';
    }
    if ($provider === 'facebook') {
        return social_cred('SOCIAL_FACEBOOK_APP_ID', 'SOCIAL_FACEBOOK_APP_ID') !== ''
            && social_cred('SOCIAL_FACEBOOK_APP_SECRET', 'SOCIAL_FACEBOOK_APP_SECRET') !== '';
    }
    if ($provider === 'instagram') {
        return social_cred('SOCIAL_INSTAGRAM_APP_ID', 'SOCIAL_INSTAGRAM_APP_ID') !== ''
            && social_cred('SOCIAL_INSTAGRAM_APP_SECRET', 'SOCIAL_INSTAGRAM_APP_SECRET') !== '';
    }
    return false;
}

/**
 * Build the provider authorize URL (step 1). Returns null when unconfigured.
 * Stores a one-time state token in the session for CSRF protection.
 */
function social_login_url(string $provider): ?string {
    $provider = strtolower($provider);
    if (!social_is_provider($provider) || !social_is_configured($provider)) return null;
    if (session_status() === PHP_SESSION_NONE) session_start();
    $state = bin2hex(random_bytes(16));
    $_SESSION['social_oauth_state'][$provider] = $state;
    $redir = social_redirect_uri($provider);

    if ($provider === 'google') {
        $q = http_build_query([
            'client_id' => social_cred('SOCIAL_GOOGLE_CLIENT_ID', 'SOCIAL_GOOGLE_CLIENT_ID'),
            'redirect_uri' => $redir,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ]);
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . $q;
    }
    if ($provider === 'facebook') {
        $q = http_build_query([
            'client_id' => social_cred('SOCIAL_FACEBOOK_APP_ID', 'SOCIAL_FACEBOOK_APP_ID'),
            'redirect_uri' => $redir,
            'state' => $state,
            'scope' => 'email,public_profile',
            'response_type' => 'code',
        ]);
        return 'https://www.facebook.com/v18.0/dialog/oauth?' . $q;
    }
    // instagram — Instagram Login (instagram.com OAuth). Returns id+username;
    // email (when missing) is collected in one extra step on our card.
    $q = http_build_query([
        'client_id' => social_cred('SOCIAL_INSTAGRAM_APP_ID', 'SOCIAL_INSTAGRAM_APP_ID'),
        'redirect_uri' => $redir,
        'scope' => 'instagram_business_basic,instagram_business_manage_messages',
        'response_type' => 'code',
        'state' => $state,
    ]);
    return 'https://www.instagram.com/oauth/authorize?' . $q;
}

/** Server-to-server POST, form-encoded. Returns decoded array or null. */
function social_http_post(string $url, array $fields): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => false, // local-dev convenience (same as PayPalService)
    ]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err || $raw === false || $code < 200 || $code >= 300) {
        error_log("SocialAuth POST $url failed: HTTP $code ($err) " . substr((string)$raw, 0, 200));
        return null;
    }
    $data = json_decode((string)$raw, true);
    return is_array($data) ? $data : null;
}

/** Server-to-server GET. Returns decoded array or null. */
function social_http_get(string $url, array $headers = []): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => false, // local-dev convenience (same as PayPalService)
    ]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err || $raw === false || $code < 200 || $code >= 300) {
        error_log("SocialAuth GET $url failed: HTTP $code ($err)");
        return null;
    }
    $data = json_decode((string)$raw, true);
    return is_array($data) ? $data : null;
}

/**
 * Handle the provider callback (step 3). On success returns
 * ['email'=>?, 'name'=>?, 'avatar'=>?] (email may be '' for Instagram).
 * On failure returns ['error'=>message].
 */
function social_handle_callback(string $provider, array $get): array {
    $provider = strtolower($provider);
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!social_is_provider($provider)) return ['error' => 'Unknown provider.'];
    if (!empty($get['error'])) {
        return ['error' => 'Sign-in was cancelled at ' . ucfirst($provider) . '. Please try again.'];
    }
    $code = (string)($get['code'] ?? '');
    $state = (string)($get['state'] ?? '');
    $expect = $_SESSION['social_oauth_state'][$provider] ?? '';
    unset($_SESSION['social_oauth_state'][$provider]);
    if ($code === '' || $state === '' || $expect === '' || !hash_equals($expect, $state)) {
        return ['error' => 'Security check failed. Please try signing in again.'];
    }
    $redir = social_redirect_uri($provider);

    if ($provider === 'google') {
        $tok = social_http_post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => social_cred('SOCIAL_GOOGLE_CLIENT_ID', 'SOCIAL_GOOGLE_CLIENT_ID'),
            'client_secret' => social_cred('SOCIAL_GOOGLE_CLIENT_SECRET', 'SOCIAL_GOOGLE_CLIENT_SECRET'),
            'redirect_uri' => $redir,
            'grant_type' => 'authorization_code',
        ]);
        $at = $tok['access_token'] ?? '';
        if (!$at) return ['error' => 'Google did not return an access token.'];
        $me = social_http_get('https://www.googleapis.com/oauth2/v3/userinfo', ['Authorization: Bearer ' . $at]);
        if (empty($me['email'])) return ['error' => 'Google did not share an email address.'];
        return [
            'email' => strtolower(trim($me['email'])),
            'name' => trim($me['name'] ?? '') ?: explode('@', $me['email'])[0],
            'avatar' => $me['picture'] ?? '',
        ];
    }

    if ($provider === 'facebook') {
        $tok = social_http_get('https://graph.facebook.com/v18.0/oauth/access_token?' . http_build_query([
            'client_id' => social_cred('SOCIAL_FACEBOOK_APP_ID', 'SOCIAL_FACEBOOK_APP_ID'),
            'redirect_uri' => $redir,
            'client_secret' => social_cred('SOCIAL_FACEBOOK_APP_SECRET', 'SOCIAL_FACEBOOK_APP_SECRET'),
            'code' => $code,
        ]));
        $at = $tok['access_token'] ?? '';
        if (!$at) return ['error' => 'Facebook did not return an access token.'];
        $me = social_http_get('https://graph.facebook.com/me?' . http_build_query([
            'fields' => 'id,name,email,picture.type(large)',
            'access_token' => $at,
        ]));
        if (empty($me['email'])) return ['error' => 'Facebook did not share an email address.'];
        $avatar = '';
        if (!empty($me['picture']['data']['url'])) $avatar = $me['picture']['data']['url'];
        return [
            'email' => strtolower(trim($me['email'])),
            'name' => trim($me['name'] ?? '') ?: explode('@', $me['email'])[0],
            'avatar' => $avatar,
        ];
    }

    // instagram
    $tok = social_http_post('https://api.instagram.com/oauth/access_token', [
        'client_id' => social_cred('SOCIAL_INSTAGRAM_APP_ID', 'SOCIAL_INSTAGRAM_APP_ID'),
        'client_secret' => social_cred('SOCIAL_INSTAGRAM_APP_SECRET', 'SOCIAL_INSTAGRAM_APP_SECRET'),
        'grant_type' => 'authorization_code',
        'redirect_uri' => $redir,
        'code' => $code,
    ]);
    $at = $tok['access_token'] ?? '';
    if (!$at) return ['error' => 'Instagram did not return an access token.'];
    $me = social_http_get('https://graph.instagram.com/me?fields=id,username&access_token=' . urlencode($at));
    if (empty($me['username']) && empty($me['id'])) return ['error' => 'Instagram did not return a profile.'];
    // Instagram Login shares no email → caller collects it in one extra step.
    return [
        'email' => '',
        'name' => trim($me['username'] ?? ('ig_' . ($me['id'] ?? 'user'))),
        'avatar' => '',
        'provider_id' => 'ig:' . ($me['id'] ?? $me['username']),
    ];
}

/**
 * Find-or-create the local customer and shape the session user —
 * the same session shape as password login, so the dashboard just works.
 */
function social_provision_customer(string $provider, string $email, string $name): array {
    $email = strtolower(trim($email));
    $name = trim(strip_tags($name)) ?: explode('@', $email)[0];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Please provide a valid email address.'];
    }
    $row = function_exists('findCustomerByEmail') ? findCustomerByEmail($email) : null;
    if ($row) {
        return ['success' => true, 'customer' => [
            'email' => $row['email'] ?? $email,
            'name' => $row['name'] ?? $name,
            'phone' => $row['phone'] ?? '',
            'avatar' => $row['avatar'] ?? '',
        ], 'is_new' => false];
    }
    if (!function_exists('createCustomerAccount')) {
        return ['success' => false, 'error' => 'Account system unavailable.'];
    }
    $res = createCustomerAccount($name, $email, bin2hex(random_bytes(16)));
    if (empty($res['success'])) return ['success' => false, 'error' => $res['error'] ?? 'Account creation failed.'];
    $res['is_new'] = true;
    return $res;
}
