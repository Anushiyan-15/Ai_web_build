<?php
// api/preview-env.php — Pre-publish development environment API.
//
//   POST {action:"provision", draftToken?, html, bizName, slug?, entities?,
//         bizSpec?, adminUser?, adminPass?, contactEmail?, genMode?}
//     → {success, env:{id, siteUrl, adminUrl, adminUser, adminPassOnce?}}
//   POST {action:"add_feature", draftId, requirements?, new_files|files,
//         new_entities|entities} → {success, files_added, entities_added, ...}
//   POST {action:"readiness", draftId} → {success, ready, checks:[...]}
//   POST {action:"reset_creds", draftId} → {success, adminUser, adminPassOnce}
//   GET  ?action=info&draftId=… → {success, env:{...}} (no secrets)
//
// Drafts are keyed by unguessable 32-hex tokens. Provisioning is rate-limited
// and count-capped; preview data never touches production storage.
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') exit;

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/PreviewEnv.php';

function penv_out(int $http, array $body): void {
    http_response_code($http);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

function penv_throttle(string $bucket, int $max, int $window = 3600): bool {
    $f = (defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__) . '/storage') . '/preview_throttle.json';
    $all = is_file($f) ? (json_decode(@file_get_contents($f), true) ?: []) : [];
    if (!is_array($all)) $all = [];
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    $now = time();
    $rows = array_values(array_filter((array)($all[$bucket] ?? []),
        fn($h) => is_array($h) && ($now - ($h['t'] ?? 0)) < $window && ($h['ip'] ?? '') === $ip));
    if (count($rows) >= $max) return false;
    $rows[] = ['ip' => $ip, 't' => $now];
    $all[$bucket] = array_slice($rows, -200);
    @file_put_contents($f, json_encode($all), LOCK_EX);
    return true;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    if (($_GET['action'] ?? '') === 'info') {
        $id = strtolower(trim((string)($_GET['draftId'] ?? '')));
        if (!preview_id_valid($id)) penv_out(400, ['success' => false, 'error' => 'Bad draft id.']);
        $meta = preview_draft_meta($id);
        if (empty($meta)) penv_out(404, ['success' => false, 'error' => 'Preview not found.']);
        $base = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
        penv_out(200, ['success' => true, 'env' => [
            'id' => $id, 'bizName' => $meta['bizName'] ?? '',
            'siteUrl' => $base . '/preview/' . $id . '/',
            'adminUrl' => $base . '/preview/' . $id . '/admin/login.php',
            'entities' => array_column((array)($meta['entities'] ?? []), 'id'),
            'updatedAt' => $meta['updatedAt'] ?? '',
        ]]);
    }
    penv_out(400, ['success' => false, 'error' => 'Unknown action.']);
}

if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 8 * 1024 * 1024) {
    penv_out(413, ['success' => false, 'error' => 'Request too large.']);
}
$raw = file_get_contents('php://input');
$req = json_decode($raw ?: '', true);
if (!is_array($req)) $req = $_POST;
$action = strtolower(trim((string)($req['action'] ?? '')));

try {
    if ($action === 'provision') {
        // Heavy op (full admin install), but legitimate design iteration
        // provisions often — 30/hr/IP with env-count + size caps is the
        // abuse backstop, not this number.
        if (!penv_throttle('provision', 30)) {
            penv_out(429, ['success' => false, 'error' => 'Too many preview builds. Try again later.']);
        }
        $res = preview_provision([
            'draftToken' => (string)($req['draftToken'] ?? $req['draftId'] ?? ''),
            'html' => (string)($req['html'] ?? ''),
            'bizName' => (string)($req['bizName'] ?? $req['biz_name'] ?? 'Preview Website'),
            'slug' => (string)($req['slug'] ?? ''),
            'entities' => $req['entities'] ?? [],
            'bizSpec' => $req['bizSpec'] ?? null,
            'adminUser' => (string)($req['adminUser'] ?? ''),
            'adminPass' => (string)($req['adminPass'] ?? ''),
            'contactEmail' => (string)($req['contactEmail'] ?? $req['contact_email'] ?? ''),
            'genMode' => (string)($req['genMode'] ?? $req['gen_mode'] ?? 'admin'),
        ]);
        if (!$res['ok']) penv_out(500, ['success' => false, 'error' => $res['error']]);
        penv_out(200, ['success' => true, 'env' => $res['env']]);
    }

    if ($action === 'add_feature') {
        if (!penv_throttle('add_feature', 20)) {
            penv_out(429, ['success' => false, 'error' => 'Too many feature requests. Try again later.']);
        }
        $id = strtolower(trim((string)($req['draftId'] ?? $req['draftToken'] ?? '')));
        $files = $req['new_files'] ?? $req['files'] ?? [];
        $ents = $req['new_entities'] ?? $req['entities'] ?? [];
        if (!is_array($files)) $files = [];
        if (!is_array($ents)) $ents = [];
        if ($id === '' || empty($files)) {
            penv_out(400, ['success' => false, 'error' => 'Missing draftId or files.']);
        }
        if (!empty($req['sql'])) error_log('[preview-env add_feature] ignored returned SQL for ' . $id);
        $res = preview_add_feature($id, $files, $ents, (string)($req['requirements'] ?? $req['feature_requirements'] ?? ''));
        if (!$res['ok']) penv_out(400, ['success' => false, 'error' => $res['error']]);
        penv_out(200, ['success' => true, 'files_added' => $res['files_added'],
            'entities_added' => $res['entities_added'], 'errors' => $res['errors'],
            'admin_url' => $res['adminUrl'],
            'note' => 'Feature installed in your PREVIEW admin — test it now, before publishing.']);
    }

    if ($action === 'readiness') {
        $id = strtolower(trim((string)($req['draftId'] ?? $req['draftToken'] ?? '')));
        $res = preview_readiness($id);
        penv_out(200, ['success' => true, 'ready' => $res['ready'], 'checks' => $res['checks']]);
    }

    if ($action === 'share' || $action === 'revoke') {
        $id = strtolower(trim((string)($req['draftId'] ?? $req['draftToken'] ?? '')));
        if ($action === 'revoke') {
            $res = preview_revoke($id);
            if (!$res['ok']) penv_out(404, ['success' => false, 'error' => $res['error']]);
            penv_out(200, ['success' => true, 'revoked' => true,
                'note' => 'Preview link revoked (410). Data is kept — re-share anytime.']);
        }
        $days = max(1, min(90, (int)($req['days'] ?? 14)));
        $res = preview_share($id, $days);
        if (!$res['ok']) penv_out(404, ['success' => false, 'error' => $res['error']]);
        penv_out(200, ['success' => true, 'shareUrl' => $res['shareUrl'],
            'expiresAt' => $res['expiresAt'],
            'note' => 'Anyone with this URL can browse + test the preview site (no builder login, no admin password). Admin still needs its login.']);
    }

    if ($action === 'reset_creds') {        if (!penv_throttle('reset_creds', 10)) {
            penv_out(429, ['success' => false, 'error' => 'Too many resets. Try again later.']);
        }
        $id = strtolower(trim((string)($req['draftId'] ?? $req['draftToken'] ?? '')));
        if (!preview_id_valid($id) || !is_dir(preview_dir($id) . '/admin')) {
            penv_out(404, ['success' => false, 'error' => 'Preview not found.']);
        }
        $authFile = preview_dir($id) . '/admin/.auth.json';
        $auth = is_file($authFile) ? (json_decode(@file_get_contents($authFile), true) ?: []) : [];
        if (!is_array($auth)) $auth = [];
        $pass = substr(bin2hex(random_bytes(9)), 0, 12);
        $auth['username'] = preview_valid_username((string)($auth['username'] ?? 'admin'));
        $auth['hash'] = password_hash($pass, PASSWORD_DEFAULT);
        unset($auth['password_plain'], $auth['password']);
        $auth['updated_at'] = date('Y-m-d H:i:s');
        if (@file_put_contents($authFile, json_encode($auth, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
            penv_out(500, ['success' => false, 'error' => 'Cannot write credentials.']);
        }
        penv_out(200, ['success' => true, 'adminUser' => $auth['username'], 'adminPassOnce' => $pass]);
    }

    penv_out(400, ['success' => false, 'error' => 'Unknown action.']);
} catch (Throwable $e) {
    error_log('[preview-env] ' . $e->getMessage());
    penv_out(500, ['success' => false, 'error' => 'Preview service error.']);
}
