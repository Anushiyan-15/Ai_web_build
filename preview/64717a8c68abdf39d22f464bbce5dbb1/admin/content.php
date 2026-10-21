<?php
require_once __DIR__ . '/layout.php';

$contentFile = __DIR__ . '/content.json';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf();
    $data = [
        'business_name' => trim($_POST['business_name'] ?? ''),
        'tagline'       => trim($_POST['tagline'] ?? ''),
        'phone'         => trim($_POST['phone'] ?? ''),
        'email'         => trim($_POST['email'] ?? ''),
        'address'       => trim($_POST['address'] ?? ''),
        'hours'         => trim($_POST['hours'] ?? ''),
        'hero_title'    => trim($_POST['hero_title'] ?? ''),
        'hero_subtitle' => trim($_POST['hero_subtitle'] ?? ''),
        'about_title'   => trim($_POST['about_title'] ?? ''),
        'about_text'    => trim($_POST['about_text'] ?? ''),
    ];
    file_put_contents($contentFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Also update ../index.html dynamically if placeholder/text exists
    $indexPath = dirname(__DIR__) . '/index.html';
    if (file_exists($indexPath) && !empty($data['business_name'])) {
        $html = file_get_contents($indexPath);
        // Replace title tag
        $html = preg_replace('/<title>(.*?)<\/title>/i', '<title>' . htmlspecialchars($data['business_name']) . '</title>', $html);
        file_put_contents($indexPath, $html);
    }

    $msg = 'Content saved successfully! Changes are live on your website.';
}

$content = file_exists($contentFile) ? json_decode(file_get_contents($contentFile), true) : [];
renderAdminHeader('content', 'Edit Website Content');
?>
<div class="top-actions">
  <div>
    <h1 class="page-title">📝 Website Content Editor</h1>
    <p style="color:#64748b;font-size:.88rem;margin-top:.3rem">Update business information and key headlines appearing on your live website.</p>
  </div>
  <a href="../index.html" target="_blank" class="btn btn-ghost"><span>👁️</span> Preview Website &rarr;</a>
</div>

<?php if ($msg): ?>
  <div style="background:rgba(16,185,129,.15);border:1px solid #10b981;color:#34d399;padding:1rem 1.25rem;border-radius:12px;font-weight:700;margin-bottom:1.5rem">
    ✅ <?= htmlspecialchars($msg) ?>
  </div>
<?php endif; ?>

<form method="POST">
  <?= admin_csrf_field() ?>
  <!-- General Info Card -->
  <div class="card">
    <h2 style="font-size:1.15rem;font-weight:800;color:#fff;margin-bottom:1.25rem">🏢 Business Profile &amp; Contact</h2>
    <div class="grid2">
      <div class="field">
        <label class="lbl">Business Name</label>
        <input type="text" name="business_name" class="inp" value="<?= htmlspecialchars($content['business_name'] ?? 'City Express Travels') ?>" required>
      </div>
      <div class="field">
        <label class="lbl">Tagline / Slogan</label>
        <input type="text" name="tagline" class="inp" value="<?= htmlspecialchars($content['tagline'] ?? '') ?>">
      </div>
      <div class="field">
        <label class="lbl">Phone Number</label>
        <input type="text" name="phone" class="inp" value="<?= htmlspecialchars($content['phone'] ?? '') ?>">
      </div>
      <div class="field">
        <label class="lbl">Public Email</label>
        <input type="email" name="email" class="inp" value="<?= htmlspecialchars($content['email'] ?? '') ?>">
      </div>
    </div>
    <div class="grid2">
      <div class="field">
        <label class="lbl">Physical Address</label>
        <input type="text" name="address" class="inp" value="<?= htmlspecialchars($content['address'] ?? '') ?>">
      </div>
      <div class="field">
        <label class="lbl">Working Hours</label>
        <input type="text" name="hours" class="inp" value="<?= htmlspecialchars($content['hours'] ?? '') ?>">
      </div>
    </div>
  </div>

  <!-- Hero & About Card -->
  <div class="card">
    <h2 style="font-size:1.15rem;font-weight:800;color:#fff;margin-bottom:1.25rem">✨ Hero Banner &amp; About Text</h2>
    <div class="field">
      <label class="lbl">Hero Headline</label>
      <input type="text" name="hero_title" class="inp" value="<?= htmlspecialchars($content['hero_title'] ?? '') ?>">
    </div>
    <div class="field">
      <label class="lbl">Hero Subtitle</label>
      <textarea name="hero_subtitle" rows="3" class="inp"><?= htmlspecialchars($content['hero_subtitle'] ?? '') ?></textarea>
    </div>
    <div class="field">
      <label class="lbl">About Section Title</label>
      <input type="text" name="about_title" class="inp" value="<?= htmlspecialchars($content['about_title'] ?? '') ?>">
    </div>
    <div class="field">
      <label class="lbl">About Section Description</label>
      <textarea name="about_text" rows="4" class="inp"><?= htmlspecialchars($content['about_text'] ?? '') ?></textarea>
    </div>

    <div style="text-align:right;margin-top:1rem">
      <button type="submit" class="btn btn-primary" style="padding:.9rem 2rem;font-size:1rem">
        <span>💾</span> Save All Changes &rarr;
      </button>
    </div>
  </div>
</form>

<?php renderAdminFooter(); ?>