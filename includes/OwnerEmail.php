<?php
// ═══════════════════════════════════════════════════════════════
//  includes/OwnerEmail.php — Per-owner business email credentials
//  + PHPMailer transport factory.
//
//  Each generated website has its own independent email configuration
//  associated with its project slug and verified owner. Credentials are
//  encrypted at rest (AES-256-CBC, server-side key) and stored outside
//  the public web root in storage/owner-email/<slug>.json.
//
//  The platform's own mail.php / Mailer::getInstance() is NOT touched:
//  WebCraft AI platform emails keep using their existing global sender.
// ═══════════════════════════════════════════════════════════════

if (!defined('OWNER_EMAIL_STORAGE_DIR')) {
    require_once __DIR__ . '/../config/owner-email.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

class OwnerEmail
{
    /* ── Provider / auth method constants ──────────────────────── */
    const PROVIDER_GMAIL    = 'gmail';
    const PROVIDER_OUTLOOK  = 'outlook';
    const PROVIDER_CUSTOM   = 'custom';

    const AUTH_SMTP_APP_PASSWORD = 'smtp_app_password';
    const AUTH_OAUTH2            = 'oauth2';
    const AUTH_SMTP_CREDENTIALS  = 'smtp_credentials';

    /* ── SMTP settings per provider (defaults, overridable) ────── */
    private static function providerSmtp(string $provider): array {
        return [
            self::PROVIDER_GMAIL    => ['host' => 'smtp.gmail.com',      'port' => 587,  'secure' => 'tls'],
            self::PROVIDER_OUTLOOK  => ['host' => 'smtp.office365.com',  'port' => 587,  'secure' => 'tls'],
            self::PROVIDER_CUSTOM   => ['host' => '',                    'port' => 587,  'secure' => 'tls'],
        ][$provider] ?? ['host' => '', 'port' => 587, 'secure' => 'tls'];
    }

    /* ── Storage helpers ───────────────────────────────────────── */
    private static function storageDir(): string {
        $d = OWNER_EMAIL_STORAGE_DIR;
        if (!is_dir($d)) {
            @mkdir($d, 0750, true);
            @file_put_contents($d . '/.htaccess', "Require all denied\n", LOCK_EX);
        }
        return $d;
    }

    private static function slugFile(string $slug): string {
        $slug = preg_replace('/[^a-z0-9_-]/i', '', $slug) ?: 'site';
        return self::storageDir() . '/' . substr($slug, 0, 60) . '.json';
    }

    /* ── Encryption ────────────────────────────────────────────── */
    private static function encKey(): string {
        $key = OWNER_EMAIL_ENC_KEY ?? '';
        if ($key === '') {
            throw new RuntimeException('OWNER_EMAIL_ENC_KEY is not configured. Set it in config/owner-email.php or via the OWNER_EMAIL_ENC_KEY environment variable.');
        }
        // Accept hex or raw; normalize to 32 bytes.
        if (ctype_xdigit($key) && strlen($key) === 64) {
            $key = hex2bin($key);
        }
        if (strlen($key) < 32) {
            $key = substr(hash('sha256', $key, true), 0, 32);
        } else {
            $key = substr($key, 0, 32);
        }
        return $key;
    }

    private static function encrypt(string $plaintext): string {
        if ($plaintext === '') return '';
        $key = self::encKey();
        $iv = random_bytes(16);
        $ct = openssl_encrypt($plaintext, OWNER_EMAIL_ENC_CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        if ($ct === false) {
            throw new RuntimeException('Encryption failed');
        }
        return base64_encode($iv . $ct);
    }

    private static function decrypt(string $ciphertext): string {
        if ($ciphertext === '') return '';
        $data = base64_decode($ciphertext, true);
        if ($data === false) {
            throw new RuntimeException('Invalid credential encoding');
        }
        $iv = substr($data, 0, 16);
        $ct = substr($data, 16);
        $key = self::encKey();
        $pt = openssl_decrypt($ct, OWNER_EMAIL_ENC_CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        if ($pt === false) {
            throw new RuntimeException('Decryption failed — credentials may have been encrypted with a different key');
        }
        return $pt;
    }

    /* ── Load / save / delete ──────────────────────────────────── */
    /**
     * Load the decrypted owner email config for a slug.
     * Returns [] when not configured. Never returns plaintext secrets to
     * the caller unless $includeSecrets is true (internal use only).
     */
    public static function load(string $slug, bool $includeSecrets = false): array {
        $f = self::slugFile($slug);
        if (!is_file($f)) {
            return [];
        }
        $raw = @file_get_contents($f);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [];
        }
        if (!$includeSecrets) {
            unset($data['smtp_pass'], $data['oauth_refresh_token'], $data['oauth_access_token']);
        }
        return $data;
    }

    public static function isConfigured(string $slug): bool {
        $d = self::load($slug, false);
        return !empty($d['email']) && !empty($d['provider']);
    }

    /**
     * Save (encrypt at rest) the owner email config for a slug.
     * $config keys: email, name, provider, auth_method, smtp_host, smtp_port,
     * smtp_secure, smtp_user, smtp_pass, oauth_access_token, oauth_refresh_token,
     * oauth_expires_at, updated_at, updated_by
     */
    public static function save(string $slug, array $config): bool {
        $slug = preg_replace('/[^a-z0-9_-]/i', '', $slug) ?: 'site';
        $slug = substr($slug, 0, 60);

        $toStore = $config;
        $toStore['updated_at'] = date('c');

        // Encrypt sensitive fields before writing.
        $sensitive = ['smtp_pass', 'oauth_refresh_token', 'oauth_access_token'];
        foreach ($sensitive as $field) {
            if (isset($toStore[$field]) && $toStore[$field] !== '') {
                $toStore[$field] = self::encrypt((string)$toStore[$field]);
            }
        }

        $json = json_encode($toStore, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }
        return (bool)@file_put_contents(self::slugFile($slug), $json, LOCK_EX);
    }

    public static function delete(string $slug): bool {
        $f = self::slugFile($slug);
        if (is_file($f)) {
            return (bool)@unlink($f);
        }
        return true;
    }

    /* ── Get decrypted credentials (internal / server-side only) ── */
    /**
     * Return the fully decrypted config for a slug. Credentials are
     * decrypted in memory only and never written to logs.
     */
    public static function credentials(string $slug): array {
        $d = self::load($slug, true);
        $sensitive = ['smtp_pass', 'oauth_refresh_token', 'oauth_access_token'];
        foreach ($sensitive as $field) {
            if (!empty($d[$field] ?? '')) {
                try {
                    $d[$field] = self::decrypt((string)$d[$field]);
                } catch (Throwable $e) {
                    $d[$field] = '';
                    $d['_decrypt_error'] = $field;
                }
            }
        }
        return $d;
    }

    /* ── PHPMailer transport factory ───────────────────────────── */
    /**
     * Build a PHPMailer instance configured with the owner's authorized
     * account for slug. Returns PHPMailer or throws RuntimeException.
     *
     * Usage:
     *   $mail = OwnerEmail::mailer($slug);
     *   $mail->addAddress($to);
     *   $mail->Subject = ...;
     *   $mail->Body = ...;
     *   $mail->send();
     */
    public static function mailer(string $slug): PHPMailer {
        $d = self::credentials($slug);

        if (empty($d['email'])) {
            throw new RuntimeException('Owner email is not configured for this website.');
        }

        $provider = $d['provider'] ?? self::PROVIDER_CUSTOM;
        $authMethod = $d['auth_method'] ?? self::AUTH_SMTP_APP_PASSWORD;

        $smtp = self::providerSmtp($provider);
        $host = !empty($d['smtp_host']) ? $d['smtp_host'] : ($smtp['host'] ?? '');
        $port = !empty($d['smtp_port']) ? (int)$d['smtp_port'] : (int)$smtp['port'];
        $secure = !empty($d['smtp_secure']) ? $d['smtp_secure'] : ($smtp['secure'] ?? 'tls');
        $user = $d['smtp_user'] ?? $d['email'] ?? '';
        $pass = $d['smtp_pass'] ?? '';

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->SMTPAuth = true;
        $mail->Host       = $host;
        $mail->Port       = $port;
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 20;
        $mail->SMTPSecure = ($secure === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;

        if ($authMethod === self::AUTH_OAUTH2 && !empty($d['oauth_access_token'])) {
            // OAuth2 bearer authentication via PHPMailer OAuth.
            $mail->AuthType = 'XOAUTH2';
            $oAuth = new class($user, $d['oauth_access_token']) implements \PHPMailer\PHPMailer\OAuthTokenProvider {
                private $user;
                private $token;
                public function __construct(string $user, string $token) {
                    $this->user = $user;
                    $this->token = $token;
                }
                public function getOauth64(): string {
                    return base64_encode("user=" . $this->user . "\001auth=Bearer " . $this->token . "\001\001");
                }
            };
            $mail->setOAuth($oAuth);
        } else {
            // SMTP password / app password.
            if ($user === '' || $pass === '') {
                throw new RuntimeException('Owner email credentials are incomplete (username or password missing).');
            }
            $mail->Username = $user;
            $mail->Password = $pass;
        }

        $fromName = !empty($d['name']) ? $d['name'] : $d['email'];
        $mail->setFrom($d['email'], $fromName);

        return $mail;
    }

    /* ── Send helper ───────────────────────────────────────────── */
    /**
     * Send a single email using the owner's account for $slug.
     * Returns ['success'=>bool, 'error'=>string].
     */
    public static function send(string $slug, string $to, string $subject, string $htmlBody, string $textBody = '', array $options = []): array {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid recipient email address'];
        }
        try {
            $mail = self::mailer($slug);
            $mail->addAddress($to);
            if (!empty($options['replyTo'])) {
                $mail->addReplyTo($options['replyTo']['email'], $options['replyTo']['name'] ?? '');
            }
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $textBody ?: strip_tags($htmlBody);
            foreach ((array)($options['attachments'] ?? []) as $att) {
                $mail->addAttachment($att['path'], $att['name'] ?? '');
            }
            $mail->send();
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        } catch (Throwable $t) {
            return ['success' => false, 'error' => $t->getMessage()];
        }
    }

    /* ── Test connection ───────────────────────────────────────── */
    /**
     * Send a real test email to verify the owner's configuration.
     * Returns ['success'=>bool, 'error'=>string].
     */
    public static function test(string $slug, string $to, string $senderName = ''): array {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid test recipient email address'];
        }
        $d = self::load($slug, false);
        if (empty($d['email'])) {
            return ['success' => false, 'error' => 'Owner email is not configured. Complete Business Email Setup first.'];
        }

        $fromName = $senderName ?: (!empty($d['name']) ? $d['name'] : $d['email']);
        $subject = '✅ Test email — your website business email is working';
        $html = '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;color:#111827;padding:24px;">'
            . '<h2 style="color:#059669;">Test email delivered</h2>'
            . '<p>This confirms your website can send business emails using your authorized account <strong>'
            . htmlspecialchars($d['email'], ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
            . '<p style="color:#6b7280;font-size:13px;">If you received this, your email setup is correct and your published website will use this account for booking confirmations, order notifications, OTPs, contact form messages and other transactional emails.</p>'
            . '</body></html>';
        $text = "Test email delivered.\n\nThis confirms your website can send business emails using your authorized account ({$d['email']}).\n\nIf you received this, your email setup is correct.";

        return self::send($slug, $to, $subject, $html, $text);
    }

    /* ── Public-safe summary (no secrets) ──────────────────────── */
    /**
     * Return a public-safe summary (masked email, provider, configured flag)
     * suitable for JSON responses to the owner.
     */
    public static function summary(string $slug): array {
        $d = self::load($slug, false);
        $email = $d['email'] ?? '';
        $masked = '';
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $parts = explode('@', $email);
            $local = $parts[0];
            $maskedLocal = strlen($local) > 2 ? substr($local, 0, 2) . str_repeat('*', max(0, strlen($local) - 2)) : $local;
            $masked = $maskedLocal . '@' . ($parts[1] ?? '');
        }
        return [
            'configured' => !empty($email) && !empty($d['provider']),
            'email' => $masked,
            'provider' => $d['provider'] ?? '',
            'auth_method' => $d['auth_method'] ?? '',
            'has_credentials' => !empty($d['smtp_pass']) || !empty($d['oauth_refresh_token']),
            'updated_at' => $d['updated_at'] ?? '',
        ];
    }
}
