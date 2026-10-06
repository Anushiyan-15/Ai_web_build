<?php
// ═══════════════════════════════════════════════════════════════
//  api/upload.php — Studio/site image uploads → storage/uploads/
//  POST multipart field "image". Returns {success, url} where url is
//  served via /upload.php?u=<hash> (storage/ is blocked by .htaccess,
//  same pattern as avatar.php). Max 8MB, real-image validated.
// ═══════════════════════════════════════════════════════════════

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once dirname(__DIR__) . '/config.php';

$inputUrl = trim($_POST['url'] ?? '');
if (!$inputUrl) {
    $rawInput = @file_get_contents('php://input');
    if ($rawInput) {
        $json = @json_decode($rawInput, true);
        if (!empty($json['url'])) $inputUrl = trim($json['url']);
    }
}

$dir = (defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__) . '/storage') . '/uploads';
if (!is_dir($dir)) @mkdir($dir, 0755, true);

$map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
try {
    $hash = bin2hex(random_bytes(16));
} catch (Throwable $e) {
    $hash = md5(uniqid((string)mt_rand(), true));
}

if ($inputUrl) {
    if (!preg_match('#^https?://#i', $inputUrl)) {
        echo json_encode(['success' => false, 'error' => 'Please provide a valid http or https image URL.']);
        exit;
    }

    $ch = curl_init($inputUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ]);
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300 || empty($data)) {
        echo json_encode(['success' => false, 'error' => 'Failed to download image from URL' . ($curlErr ? ": $curlErr" : " (HTTP $httpCode).")]);
        exit;
    }
    if (strlen($data) > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => 'Downloaded image is larger than 10MB.']);
        exit;
    }

    $info = @getimagesizefromstring($data);
    if (!$info || !isset($map[$info['mime']])) {
        // Fallback check for SVG or WebP
        if (stripos($data, '<svg') !== false) {
            $mime = 'image/svg+xml';
            $ext = 'svg';
        } else {
            echo json_encode(['success' => false, 'error' => 'URL does not point to a valid JPG, PNG, GIF, or WebP image.']);
            exit;
        }
    } else {
        $mime = $info['mime'];
        $ext = $map[$mime];
    }

    $dest = $dir . '/' . $hash . '.' . $ext;
    if (@file_put_contents($dest, $data) === false) {
        echo json_encode(['success' => false, 'error' => 'Could not save downloaded image to storage.']);
        exit;
    }

    $base = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
    $parsedPath = parse_url($inputUrl, PHP_URL_PATH);
    $origName = $parsedPath ? basename($parsedPath) : 'online-image.' . $ext;

    // Prune oldest beyond newest 300
    try {
        $all = glob($dir . '/*.{jpg,jpeg,png,gif,webp,svg}', GLOB_BRACE) ?: [];
        if (count($all) > 300) {
            usort($all, fn($a, $b) => filemtime($a) - filemtime($b));
            foreach (array_slice($all, 0, count($all) - 300) as $old) @unlink($old);
        }
    } catch (Throwable $e) {}

    $scriptDir = dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $relBase = ($scriptDir === '/' || $scriptDir === '\\') ? '' : rtrim($scriptDir, '/\\');
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $fullBase = $host ? ($proto . '://' . $host . $relBase) : (defined('SITE_URL') ? rtrim(SITE_URL, '/') : '');
    $finalUrl = $fullBase ? ($fullBase . '/upload.php?u=' . $hash) : ($relBase . '/upload.php?u=' . $hash);

    echo json_encode([
        'success' => true,
        'url'     => $finalUrl,
        'name'    => preg_replace('/[^a-zA-Z0-9._-]/', '_', $origName),
        'mime'    => $mime,
        'bytes'   => filesize($dest)
    ]);
    exit;
}

$file = $_FILES['image'] ?? null;
if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Please choose an image file or enter an image URL.']);
    exit;
}
if (($file['size'] ?? 0) > 8 * 1024 * 1024) {
    echo json_encode(['success' => false, 'error' => 'Image must be under 8MB.']);
    exit;
}
$info = @getimagesize($file['tmp_name']);
if (!$info || !isset($map[$info['mime']])) {
    echo json_encode(['success' => false, 'error' => 'Only JPG, PNG, GIF or WebP images allowed.']);
    exit;
}

$dest = $dir . '/' . $hash . '.' . $map[$info['mime']];
if (!@move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['success' => false, 'error' => 'Could not save image.']);
    exit;
}

// Prune oldest beyond newest 300 (uploads folder hygiene)
try {
    $all = glob($dir . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE) ?: [];
    if (count($all) > 300) {
        usort($all, fn($a, $b) => filemtime($a) - filemtime($b));
        foreach (array_slice($all, 0, count($all) - 300) as $old) @unlink($old);
    }
} catch (Throwable $e) {}

    $scriptDir = dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $relBase = ($scriptDir === '/' || $scriptDir === '\\') ? '' : rtrim($scriptDir, '/\\');
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $fullBase = $host ? ($proto . '://' . $host . $relBase) : (defined('SITE_URL') ? rtrim(SITE_URL, '/') : '');
    $finalUrl = $fullBase ? ($fullBase . '/upload.php?u=' . $hash) : ($relBase . '/upload.php?u=' . $hash);

    echo json_encode([
        'success' => true,
        'url'     => $finalUrl,
        'name'    => preg_replace('/[^a-zA-Z0-9._-]/', '_', (string)($file['name'] ?? 'image')),
        'mime'    => $info['mime'],
        'bytes'   => filesize($dest)
    ]);
