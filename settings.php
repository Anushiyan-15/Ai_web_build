<?php
// settings.php — App Settings (customer-friendly)
// The platform appearance is fixed to the official green brand theme.
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/ui-lang.php';
$UI_LANG = ui_resolve_lang();
$page_title = 'Settings';
$page_desc = 'Account options, language and website shortcuts for your //WEBbuilders.lk workspace.';
require_once __DIR__ . '/includes/nav.php';
$customerUser = $_SESSION['customer_user'] ?? null;
?>
<div class="set-wrap">
  <!-- ── Hero: what is on this page ── -->
  <div class="set-hero">
    <div>
      <span class="badge">⚙️ Settings</span>
      <h1 class="heading-lg">Your workspace settings</h1>
      <p class="lead" style="margin-top:.5rem">The //WEBbuilders.lk appearance is fixed to our official green theme, so every page stays consistent. Your websites are never affected.</p>
    </div>
    <div class="set-status" id="set-status">
      <span class="set-status-dot" id="set-status-dot"></span>
      <span>Using now: <strong id="set-current-name">Green (fixed)</strong></span>
    </div>
  </div>
  <nav class="set-toc" aria-label="What's on this page">
    <a href="#set-appearance"><span>🎨</span><span><strong>1 · Appearance</strong><small>Official green theme</small></span></a>
    <a href="#set-account"><span>👤</span><span><strong>2 · Account &amp; more</strong><small>Login, language, websites</small></span></a>
  </nav>

  <!-- ── 01 Appearance (fixed) ── -->
  <section class="set-card set-section" id="set-appearance">
    <div class="set-sec-head">
      <span class="set-num">01</span>
      <div><h2>Appearance</h2><p class="sub">//WEBbuilders.lk uses one fixed premium green design system across the dashboard, builder, studio and admin pages.</p></div>
    </div>
    <div class="fixed-theme-card">
      <span class="ft-swatch" aria-hidden="true"></span>
      <div>
        <strong>Green · Fixed brand theme</strong>
        <small>Dark, Light and Custom appearance options have been retired so the brand stays consistent everywhere.</small>
      </div>
    </div>
    <p class="set-note">💡 Tip: this changes only how <strong>//WEBbuilders.lk itself</strong> looks. The websites you build keep their own independent designs and color palettes.</p>
  </section>

  <!-- ── 02 Account & more ── -->
  <section class="set-card set-section" id="set-account">
    <div class="set-sec-head">
      <span class="set-num">02</span>
      <div><h2>Account &amp; more</h2><p class="sub">Everything else lives where you already use it — one tap away.</p></div>
    </div>
    <?php if ($customerUser): ?>
    <div class="set-me">👤 Signed in as <strong><?= htmlspecialchars($customerUser['email']) ?></strong></div>
    <?php else: ?>
    <div class="set-me">👋 You are browsing as a <strong>guest</strong> — <a href="<?= SITE_URL ?>/customer-portal.php" style="color:var(--p);font-weight:700">log in</a> to sync your websites on any device.</div>
    <?php endif; ?>
    <div class="set-links">
      <a class="btn btn-ghost" href="<?= SITE_URL ?>/customer-portal.php">📁 My websites <small>open, manage &amp; track</small></a>
      <a class="btn btn-ghost" href="<?= SITE_URL ?>/builder.php">✦ AI Builder <small>create a new site</small></a>
      <a class="btn btn-ghost" href="<?= SITE_URL ?>/customer-portal.php?tab=language">🌐 Language <small>change app language</small></a>
      <a class="btn btn-ghost" href="<?= SITE_URL ?>/site-manager.php">🗂️ Website manager <small>published site controls</small></a>
    </div>
  </section>
</div>
</body></html>
