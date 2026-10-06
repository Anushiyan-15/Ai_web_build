<?php
if (session_status() === PHP_SESSION_NONE) session_start();
// No-cache: logout ku pirahu Back press panna stale list vara kudathu
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
require_once dirname(__DIR__) . '/config/app.php';

$customerUser  = $_SESSION['customer_user'] ?? null;
$customerEmail = strtolower(trim($customerUser['email'] ?? ''));

// Map published slug → owner email (local order files + Supabase)
$slugOwners = [];
$ordersDir = dirname(__DIR__) . '/storage/orders';
if (is_dir($ordersDir)) {
    foreach (glob($ordersDir . '/*.json') as $f) {
        $o = json_decode(@file_get_contents($f), true);
        if (!is_array($o) || empty($o['slug'])) continue;
        $em = strtolower(trim($o['admin_email'] ?? ($o['client_email'] ?? '')));
        if ($em) $slugOwners[$o['slug']] = $em;
    }
}
if ($customerEmail && file_exists(dirname(__DIR__) . '/includes/db.php')) {
    try {
        require_once dirname(__DIR__) . '/includes/db.php';
        $db = function_exists('getDb') ? getDb() : null;
        if ($db) {
            foreach ($db->query("SELECT slug, admin_email, client_email FROM orders")->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (empty($row['slug'])) continue;
                $em = strtolower(trim($row['admin_email'] ?? ($row['client_email'] ?? '')));
                if ($em) $slugOwners[$row['slug']] = $em;
            }
        }
    } catch (Throwable $e) {}
}

// Scan published directory for live sites
$sites = [];
$dir = __DIR__;
foreach (scandir($dir) as $item) {
    if ($item === '.' || $item === '..' || !is_dir($dir . '/' . $item)) continue;
    $indexPath = $dir . '/' . $item . '/index.html';
    $metaPath  = $dir . '/' . $item . '/meta.json';

    // Logged-in customers see ONLY their own projects here
    if ($customerEmail) {
        $owner = $slugOwners[$item] ?? null;
        if ($owner === null && file_exists($metaPath)) {
            $metaTmp = json_decode(@file_get_contents($metaPath), true) ?: [];
            $oidTmp = $metaTmp['order_id'] ?? '';
            if ($oidTmp) {
                $ofTmp = $ordersDir . '/' . preg_replace('/[^A-Za-z0-9\-]/', '', $oidTmp) . '.json';
                if (file_exists($ofTmp)) {
                    $oTmp = json_decode(@file_get_contents($ofTmp), true) ?: [];
                    $owner = strtolower(trim($oTmp['admin_email'] ?? ($oTmp['client_email'] ?? ''))) ?: null;
                }
            }
        }
        if ($owner !== $customerEmail) continue;
    }
    
    $title = ucwords(str_replace(['-', '_'], ' ', $item));
    $publishedAt = file_exists($indexPath) ? date('M j, Y g:i A', filemtime($indexPath)) : 'Recently';
    
    if (file_exists($metaPath)) {
        $meta = json_decode(file_get_contents($metaPath), true) ?: [];
        if (!empty($meta['biz_name'])) $title = $meta['biz_name'];
        if (!empty($meta['published_at'])) $publishedAt = date('M j, Y g:i A', strtotime($meta['published_at']));
    }
    
    $sites[] = [
        'slug'         => $item,
        'title'        => $title,
        'url'          => SITE_URL . '/published/' . $item . '/',
        'published_at' => $publishedAt
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Published Websites — <?= SITE_NAME ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin:0; padding:0; }
    body { font-family: 'Inter', system-ui, sans-serif; background: #080c14; color: #f8fafc; min-height: 100vh; display: flex; flex-direction: column; }
    .header { border-bottom: 1px solid #1e293b; padding: 1.25rem 2rem; display: flex; justify-content: space-between; align-items: center; background: #0f172a; }
    .logo { font-size: 1.25rem; font-weight: 800; color: #818cf8; text-decoration: none; display: flex; align-items: center; gap: 0.5rem; }
    .btn { background: #4f46e5; color: #fff; text-decoration: none; padding: 0.6rem 1.2rem; border-radius: 8px; font-size: 0.9rem; font-weight: 600; transition: background 0.2s; }
    .btn:hover { background: #4338ca; }
    .container { max-width: 1100px; margin: 3rem auto; padding: 0 1.5rem; flex: 1; width: 100%; }
    .hero { margin-bottom: 2.5rem; text-align: center; }
    .hero h1 { font-size: 2.2rem; font-weight: 800; margin-bottom: 0.5rem; background: linear-gradient(135deg, #fff 30%, #94a3b8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    .hero p { color: #94a3b8; font-size: 1rem; }
    .site-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem; }
    .site-card { background: #0f172a; border: 1px solid #1e293b; border-radius: 12px; overflow: hidden; transition: transform 0.2s, border-color 0.2s; display: flex; flex-direction: column; }
    .site-card:hover { transform: translateY(-3px); border-color: #6366f1; }
    .site-preview { background:#1e293b; height:180px; position:relative; overflow:hidden; }
    .site-preview .preview-fallback { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:2.5rem; }
    .site-preview iframe { position:absolute; top:0; left:0; width:1200px; height:800px; border:0; background:#fff;
      transform:scale(.3); transform-origin:top left; pointer-events:none; }
    .site-body { padding: 1.25rem; flex: 1; display: flex; flex-direction: column; justify-content: space-between; }
    .site-title { font-size: 1.1rem; font-weight: 700; color: #fff; margin-bottom: 0.35rem; }
    .site-meta { font-size: 0.8rem; color: #64748b; margin-bottom: 1rem; }
    .site-actions { display: flex; gap: 0.5rem; }
    .site-btn { flex: 1; text-align: center; padding: 0.5rem; border-radius: 6px; font-size: 0.85rem; font-weight: 600; text-decoration: none; border: 1px solid #334155; color: #cbd5e1; transition: all 0.15s; }
    .site-btn.primary { background: #4f46e5; border-color: #4f46e5; color: #fff; }
    .site-btn:hover { border-color: #818cf8; color: #fff; }
    .empty-state { text-align: center; padding: 4rem 2rem; background: #0f172a; border: 1px dashed #334155; border-radius: 16px; }
    .empty-icon { font-size: 3rem; margin-bottom: 1rem; }
  </style>
</head>
<body>
  <header class="header">
    <a href="<?= SITE_URL ?>" class="logo">⚡ <?= SITE_NAME ?></a>
    <div style="display:flex;align-items:center;gap:.75rem;">
      <?php if ($customerEmail): ?>
        <span style="font-size:.82rem;color:#c7d2fe;font-weight:700;">👤 <?= htmlspecialchars($customerUser['email']) ?></span>
        <a href="<?= SITE_URL ?>/customer-portal.php" class="btn">My Projects</a>
      <?php else: ?>
        <a href="<?= SITE_URL ?>/customer-portal.php" class="btn">Login</a>
        <a href="<?= SITE_URL ?>/builder.php" class="btn">✦ Open AI Builder</a>
      <?php endif; ?>
    </div>
  </header>

  <main class="container">
    <div class="hero">
      <?php if ($customerEmail): ?>
        <h1>🌐 My Live Sites</h1>
        <p>Websites published under <?= htmlspecialchars($customerUser['email']) ?> — <?= count($sites) ?> live.</p>
      <?php else: ?>
        <h1>🚀 Published Websites</h1>
        <p>Live websites generated with AI and hosted seamlessly on your server. <a href="<?= SITE_URL ?>/customer-portal.php" style="color:#818cf8;font-weight:700;">Login</a> to see your own sites here.</p>
      <?php endif; ?>
    </div>

    <?php if (empty($sites)): ?>
      <div class="empty-state">
        <div class="empty-icon">🌐</div>
        <?php if ($customerEmail): ?>
          <h2 style="font-size:1.3rem; margin-bottom:0.5rem">No live sites under your email yet</h2>
          <p style="color:#94a3b8; margin-bottom:1.5rem">Build and publish your first website — it will appear here.</p>
        <?php else: ?>
          <h2 style="font-size:1.3rem; margin-bottom:0.5rem">No websites published yet</h2>
          <p style="color:#94a3b8; margin-bottom:1.5rem">Use the AI Builder to generate your first website and publish it with PayPal!</p>
        <?php endif; ?>
        <a href="<?= $customerEmail ? SITE_URL . '/builder.php' : SITE_URL . '/customer-portal.php?view=signup' ?>" class="btn">✦ Build &amp; Publish Website</a>
      </div>
    <?php else: ?>
      <div class="site-grid">
        <?php foreach ($sites as $site): ?>
          <div class="site-card">
            <div class="site-preview">
              <div class="preview-fallback">🌐</div>
              <iframe src="<?= htmlspecialchars($site['url']) ?>" title="<?= htmlspecialchars($site['title']) ?> preview" loading="lazy" scrolling="no" sandbox="allow-scripts allow-same-origin"></iframe>
            </div>
            <div class="site-body">
              <div>
                <div class="site-title"><?= htmlspecialchars($site['title']) ?></div>
                <div class="site-meta">Published on <?= htmlspecialchars($site['published_at']) ?></div>
              </div>
              <div class="site-actions">
                <a href="<?= htmlspecialchars($site['url']) ?>" target="_blank" rel="noopener" class="site-btn primary">Visit Live Site ↗</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>
</body>
</html>
