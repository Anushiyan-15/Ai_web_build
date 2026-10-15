<?php
// Shared Navbar — included in every page
if (session_status() === PHP_SESSION_NONE) session_start();
// No-cache: back button after logout must refetch (logged-in view never served stale)
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
$customerUser = $_SESSION['customer_user'] ?? null;
$current = basename($_SERVER['PHP_SELF']);
// Logged-in customers get a customer home: logo + Home go to their portal.
// Guests see the public landing + Login.
$homeHref = $customerUser ? SITE_URL . '/customer-portal.php' : SITE_URL . '/index.php';
$nav_links = $customerUser ? [
    'customer-portal.php' => 'Home',
    'builder.php'         => '✦ AI Builder',
    'published/'          => 'Live Sites',
    'client-intake.php'   => 'Get a Quote',
    'contact.php'         => 'Contact',
] : [
    'index.php'         => 'Home',
    'builder.php'       => '✦ AI Builder',
    'published/'        => 'Live Sites',
    'client-intake.php' => 'Get a Quote',
    'contact.php'       => 'Contact',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($page_title) ? htmlspecialchars($page_title).' — '.SITE_NAME : SITE_NAME ?></title>
<meta name="description" content="<?= isset($page_desc) ? htmlspecialchars($page_desc) : SITE_TAGLINE ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/loader-3d.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/wc-theme.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/wc-premium-3d.css">
<script>
/* Early theme bootstrap — prevents flash of wrong theme (dark/light/custom). */
(function(){
  try {
    var mode = localStorage.getItem('wc_theme_mode') || localStorage.getItem('wc_theme') || '';
    if (!mode) mode = (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) ? 'light' : 'dark';
    if (mode !== 'dark' && mode !== 'light' && mode !== 'custom') mode = 'dark';
    var h = document.documentElement;
    h.setAttribute('data-theme', mode === 'custom' ? 'custom' : mode);
    h.setAttribute('data-appearance', mode);
    if (mode === 'custom') {
      try {
        var c = JSON.parse(localStorage.getItem('wc_theme_custom') || '{}');
        var d = { bg:'#0a0e17',bg2:'#0d1322',surface:'#131b2c',accent:'#818cf8',accent2:'#a78bfa',text:'#eef2ff',text2:'#aab8d4',border:'#22304a',radius:12 };
        Object.keys(d).forEach(function(k){ if (typeof c[k]==='string'&&/^#[0-9a-fA-F]{6}$/.test(c[k])) d[k]=c[k]; });
        if (!isNaN(parseInt(c.radius,10))) d.radius=Math.max(0,Math.min(24,parseInt(c.radius,10)));
        h.style.setProperty('--wc-c-bg',d.bg); h.style.setProperty('--wc-c-bg2',d.bg2);
        h.style.setProperty('--wc-c-surface',d.surface); h.style.setProperty('--wc-c-accent',d.accent);
        h.style.setProperty('--wc-c-accent2',d.accent2); h.style.setProperty('--wc-c-text',d.text);
        h.style.setProperty('--wc-c-text2',d.text2); h.style.setProperty('--wc-c-border',d.border);
        h.style.setProperty('--wc-c-radius',d.radius+'px');
      } catch(e2) {}
    }
  } catch (e) {}
})();
</script>
<script src="<?= SITE_URL ?>/assets/js/wc-theme.js"></script>
<script src="<?= SITE_URL ?>/assets/js/loader-3d.js"></script>
<style>
/* ── GLOBAL RESETS ── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth;font-size:16px}
body{font-family:'Inter',system-ui,sans-serif;color:var(--body-color);background:var(--body-bg);line-height:1.6;transition:background .25s,color .25s}
a{text-decoration:none;color:inherit}
img{max-width:100%;display:block}

/* ── CSS VARIABLES — LIGHT MODE (default) ── */
:root{
  --p:#6366f1;--pd:#4338ca;--pl:#ede9fe;
  --a:#06b6d4;--s:#10b981;--w:#f59e0b;--d:#ef4444;
  --n50:#f9fafb;--n100:#f3f4f6;--n200:#e5e7eb;--n300:#d1d5db;
  --n400:#9ca3af;--n500:#6b7280;--n600:#4b5563;--n700:#374151;
  --n800:#1f2937;--n900:#111827;
  --r:10px;--sh:0 4px 24px rgba(99,102,241,.12);
  --t:.18s ease;
  /* Semantic surface tokens */
  --body-bg:#ffffff;
  --body-color:#1e1b4b;
  --surface:#ffffff;
  --surface-2:var(--n50);
  --surface-3:var(--n100);
  --border:var(--n200);
  --text-primary:var(--n900);
  --text-secondary:var(--n600);
  --text-muted:var(--n500);
  --navbar-bg:rgba(255,255,255,.92);
  --navbar-border:var(--n200);
  --card-bg:#ffffff;
  --card-border:var(--n200);
  --input-bg:#ffffff;
  --input-border:var(--n300);
  --mobile-menu-bg:#ffffff;
}

/* ── CSS VARIABLES — DARK MODE ── */
[data-theme="dark"]{
  --body-bg:#0d1117;
  --body-color:#e2e8f0;
  --surface:#161b22;
  --surface-2:#1c2333;
  --surface-3:#21262d;
  --border:#30363d;
  --text-primary:#f0f6fc;
  --text-secondary:#8b949e;
  --text-muted:#6e7681;
  --n50:#1c2333;--n100:#21262d;--n200:#30363d;--n300:#3d444d;
  --n400:#656d76;--n500:#8b949e;--n600:#9198a1;--n700:#adbac7;
  --n800:#cdd9e5;--n900:#e6edf3;
  --pl:rgba(99,102,241,.18);--pd:#818cf8;
  --navbar-bg:rgba(22,27,34,.96);
  --navbar-border:#30363d;
  --card-bg:#161b22;
  --card-border:#30363d;
  --input-bg:#1c2333;
  --input-border:#30363d;
  --mobile-menu-bg:#161b22;
  --sh:0 4px 24px rgba(0,0,0,.45);
}

/* ── NAVBAR · floating glass command-bar ── */
.navbar{
  position:sticky;top:0;z-index:1000;
  background:var(--navbar-bg);
  backdrop-filter:blur(20px) saturate(1.4);
  -webkit-backdrop-filter:blur(20px) saturate(1.4);
  border-bottom:1px solid var(--navbar-border);
  padding:.65rem 2rem;
  display:flex;align-items:center;justify-content:space-between;
  min-height:64px;
  transition:background .25s,border-color .25s,box-shadow .3s;
}
.navbar::before{
  content:'';position:absolute;top:0;left:8%;right:8%;height:2px;border-radius:2px;
  background:linear-gradient(90deg,transparent,var(--p),#a855f7,var(--a),transparent);
  background-size:200% 100%;
  animation:navSheen 7s linear infinite;
  opacity:.85;pointer-events:none;
}
@keyframes navSheen{to{background-position:200% 0}}
.navbar.scrolled{box-shadow:0 10px 34px rgba(2,6,18,.28)}
.nav-logo{
  display:flex;align-items:center;gap:.65rem;
  font-size:1.22rem;font-weight:900;
  background:linear-gradient(120deg,var(--p) 20%,#a855f7 50%,var(--p) 80%);
  background-size:200% auto;
  -webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;
  letter-spacing:-.02em;
  animation:logoSheen 6s linear infinite;
}
@keyframes logoSheen{to{background-position:200% center}}
.nav-logo .logo-icon{
  width:36px;height:36px;flex-shrink:0;
  background:conic-gradient(from 40deg,var(--p),#a855f7,var(--a),#22d3ee,var(--p));
  border-radius:12px;
  display:flex;align-items:center;justify-content:center;
  color:#fff;font-size:1.05rem;
  box-shadow:0 4px 16px rgba(99,102,241,.45),inset 0 1px 0 rgba(255,255,255,.45);
  animation:logoSpin 9s linear infinite;
  -webkit-text-fill-color:#fff;
}
@keyframes logoSpin{to{filter:hue-rotate(360deg)}}
.nav-links{
  display:flex;align-items:center;gap:.25rem;
  background:color-mix(in srgb,var(--p) 7%,transparent);
  border:1px solid var(--navbar-border);
  border-radius:999px;
  padding:.3rem .45rem;
}
.nav-link{
  position:relative;
  padding:.45rem .95rem;
  border-radius:999px;
  font-size:.855rem;font-weight:500;
  color:var(--text-secondary);
  transition:var(--t);
  white-space:nowrap;
}
.nav-link:hover{color:var(--p)}
.nav-link.active{color:var(--p);font-weight:700}
.nav-link.active::after{
  content:'';position:absolute;left:50%;bottom:2px;transform:translateX(-50%);
  width:16px;height:3px;border-radius:3px;
  background:linear-gradient(90deg,var(--p),#a855f7);
  box-shadow:0 0 8px rgba(99,102,241,.8);
}
.nav-link.cta{
  background:linear-gradient(135deg,var(--p),#7c3aed);
  color:#fff !important;
  padding:.5rem 1.2rem;
  box-shadow:0 4px 14px rgba(99,102,241,.4);
  overflow:hidden;
}
.nav-link.cta::before{
  content:'';position:absolute;top:0;left:-80%;width:50%;height:100%;
  background:linear-gradient(100deg,transparent,rgba(255,255,255,.5),transparent);
  transform:skewX(-20deg);
  animation:ctaShine 3.4s ease-in-out infinite;
}
@keyframes ctaShine{0%{left:-80%}55%,100%{left:180%}}
.nav-link.cta:hover{transform:translateY(-1px);box-shadow:0 8px 22px rgba(99,102,241,.5)}
.hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:8px;border:1px solid var(--navbar-border);border-radius:10px;background:transparent}
.hamburger span{display:block;width:22px;height:2px;background:var(--text-secondary);border-radius:2px;transition:var(--t)}
.hamburger[aria-expanded="true"] span:nth-child(1){transform:translateY(7px) rotate(45deg)}
.hamburger[aria-expanded="true"] span:nth-child(2){opacity:0}
.hamburger[aria-expanded="true"] span:nth-child(3){transform:translateY(-7px) rotate(-45deg)}
.mobile-menu{
  display:none;
  position:fixed;top:76px;left:1rem;right:1rem;
  background:var(--mobile-menu-bg);
  border:1px solid var(--navbar-border);
  border-radius:18px;
  padding:1rem;
  flex-direction:column;gap:.35rem;
  z-index:999;
  box-shadow:0 24px 60px rgba(0,0,0,.35);
  transition:background .25s;
  backdrop-filter:blur(20px);
  -webkit-backdrop-filter:blur(20px);
}
.mobile-menu.open{display:flex}
.mobile-menu.open .nav-link{animation:mobIn .3s cubic-bezier(.22,1,.36,1) backwards}
.mobile-menu.open .nav-link:nth-child(2){animation-delay:.05s}
.mobile-menu.open .nav-link:nth-child(3){animation-delay:.1s}
.mobile-menu.open .nav-link:nth-child(4){animation-delay:.15s}
.mobile-menu.open .nav-link:nth-child(5){animation-delay:.2s}
.mobile-menu.open .nav-link:nth-child(6){animation-delay:.25s}
@keyframes mobIn{from{opacity:0;transform:translateX(-12px)}to{opacity:1;transform:none}}
.mobile-menu .nav-link{font-size:1rem;padding:.65rem 1rem}
.mobile-menu .nav-link.active::after{display:none}
.mobile-menu .nav-link.active{background:var(--pl)}

/* ── THEME TOGGLE BUTTON ── */
.theme-btn {
  display:inline-flex;align-items:center;justify-content:center;
  width:38px;height:38px;border-radius:10px;
  border:1px solid var(--border);
  background:var(--surface);
  color:var(--text-primary);
  cursor:pointer;
  transition:var(--t);
  margin-left:.25rem;
  font-size:1.05rem;
  line-height:1;
}
.theme-btn:hover {
  border-color:var(--p);
  background:var(--pl);
  color:var(--p);
  transform:scale(1.05);
}
.mobile-theme-row {
  display:flex;align-items:center;justify-content:space-between;
  padding:.65rem 1rem;border-top:1px solid var(--border);
  margin-top:.5rem;
  font-size:.9rem;font-weight:600;color:var(--text-secondary);
}

/* ── UTILITY CLASSES (used across all pages) ── */
.container{max-width:1160px;margin:0 auto;padding:0 1.5rem}
.section{padding:5rem 0}
.section-sm{padding:3rem 0}
.text-center{text-align:center}
.badge{
  display:inline-flex;align-items:center;gap:.4rem;
  background:var(--pl);color:var(--pd);
  font-size:.75rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;
  padding:.35rem .9rem;border-radius:999px;
  border:1px solid rgba(99,102,241,.2);
  margin-bottom:1rem;
}
.heading-xl{font-size:clamp(2rem,5vw,3.5rem);font-weight:900;letter-spacing:-.03em;line-height:1.1;color:var(--text-primary)}
.heading-lg{font-size:clamp(1.5rem,3.5vw,2.4rem);font-weight:800;letter-spacing:-.02em;color:var(--text-primary)}
.heading-md{font-size:clamp(1.2rem,2.5vw,1.6rem);font-weight:700;color:var(--text-primary)}
.lead{font-size:1.1rem;color:var(--text-muted);line-height:1.75;max-width:600px}
.lead.center{margin:0 auto}
.gradient-text{
  background:linear-gradient(135deg,var(--p),#a855f7,var(--a));
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
}
.btn{
  display:inline-flex;align-items:center;gap:.5rem;
  padding:.75rem 1.6rem;border-radius:10px;
  font-family:'Inter',sans-serif;font-size:.9rem;font-weight:600;
  border:none;cursor:pointer;transition:var(--t);line-height:1;
}
.btn svg{width:17px;height:17px}
.btn-primary{
  background:linear-gradient(135deg,var(--p),#7c3aed);
  color:#fff;
  box-shadow:0 4px 16px rgba(99,102,241,.35);
}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(99,102,241,.45)}
.btn-outline{background:var(--surface);color:var(--p);border:1.5px solid var(--p)}
.btn-outline:hover{background:var(--pl)}
.btn-lg{padding:1rem 2.2rem;font-size:1rem;border-radius:12px}
.btn-ghost{background:transparent;color:var(--text-secondary);border:1.5px solid var(--border)}
.btn-ghost:hover{color:var(--p);border-color:var(--p);background:var(--pl)}
.card{
  background:var(--card-bg);border-radius:16px;border:1px solid var(--card-border);
  padding:1.75rem;box-shadow:var(--sh);
  transition:transform var(--t),box-shadow var(--t),background .25s,border-color .25s;
}
.card:hover{transform:translateY(-3px);box-shadow:0 12px 40px rgba(99,102,241,.15)}
.grid-2{display:grid;grid-template-columns:repeat(2,1fr);gap:1.5rem}
.grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem}
.grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:1.5rem}
@media(max-width:900px){.grid-3,.grid-4{grid-template-columns:repeat(2,1fr)}}
@media(max-width:580px){.grid-2,.grid-3,.grid-4{grid-template-columns:1fr}}

@media(max-width:768px){
  .nav-links{display:none}
  .hamburger{display:flex}
  .navbar{padding:.65rem 1.25rem}
}
@media (prefers-reduced-motion: reduce){
  .navbar::before,.nav-logo,.nav-logo .logo-icon,.nav-link.cta::before{animation:none !important}
}
</style>

<nav class="navbar" role="navigation" aria-label="Main navigation">
  <a href="<?= $homeHref ?>" class="nav-logo" aria-label="<?= SITE_NAME ?> Home">
    <div class="logo-icon">✦</div>
    <?= SITE_NAME ?>
  </a>
  <div class="nav-links">
    <?php foreach($nav_links as $file => $label): ?>
    <a href="<?= SITE_URL . '/' . $file ?>"
       class="nav-link<?= $current===$file?' active':'' ?><?= $file==='builder.php'?' cta':'' ?>">
      <?= htmlspecialchars($label) ?>
    </a>
    <?php endforeach; ?>
    <?php if ($customerUser): ?>
      <span class="nav-link" style="background:var(--pl);font-weight:700;color:var(--pd);" title="Signed in">👤 <?= htmlspecialchars($customerUser['email']) ?></span>
      <a href="<?= SITE_URL ?>/customer-portal.php?action=logout" class="nav-link">Logout</a>
    <?php else: ?>
      <a href="<?= SITE_URL ?>/customer-portal.php" class="nav-link cta">Login</a>
    <?php endif; ?>
    <!-- Theme controls: quick toggle + Dark/Light/Custom selector -->
    <div class="theme-wrap">
      <button type="button" class="theme-btn" id="themeToggleBtn" aria-label="Toggle dark/light theme" title="Toggle dark/light mode">
        <span class="theme-icon" id="themeToggleIcon">🌙</span>
      </button>
      <select class="theme-select" id="themeModeSelect" aria-label="Appearance mode">
        <option value="dark">Dark</option>
        <option value="light">Light</option>
        <option value="custom">Custom</option>
      </select>
    </div>
  </div>
  <button class="hamburger" id="hamburger" aria-label="Toggle menu" aria-expanded="false">
    <span></span><span></span><span></span>
  </button>
</nav>

<div class="mobile-menu" id="mobileMenu" role="navigation">
  <?php foreach($nav_links as $file => $label): ?>
  <a href="<?= SITE_URL . '/' . $file ?>"
     class="nav-link<?= $current===$file?' active':'' ?>">
    <?= htmlspecialchars($label) ?>
  </a>
  <?php endforeach; ?>
  <?php if ($customerUser): ?>
    <span class="nav-link" style="color:var(--pd);font-weight:700;">👤 <?= htmlspecialchars($customerUser['email']) ?></span>
    <a href="<?= SITE_URL ?>/customer-portal.php?action=logout" class="nav-link">Logout</a>
  <?php else: ?>
    <a href="<?= SITE_URL ?>/customer-portal.php" class="nav-link cta">Login</a>
  <?php endif; ?>
  <div class="mobile-theme-row">
    <span>Appearance</span>
    <span style="display:inline-flex;gap:.4rem;align-items:center">
      <select class="theme-select" id="mobileThemeModeSelect" aria-label="Appearance mode">
        <option value="dark">Dark</option>
        <option value="light">Light</option>
        <option value="custom">Custom</option>
      </select>
    </span>
    <button type="button" class="theme-btn" id="mobileThemeToggleBtn" aria-label="Toggle theme" style="display:none">
      <span class="theme-icon" id="mobileThemeToggleIcon">🌙</span>
    </button>
  </div>
</div>

<script>
const ham = document.getElementById('hamburger');
const mob = document.getElementById('mobileMenu');
ham.addEventListener('click', () => {
  mob.classList.toggle('open');
  ham.setAttribute('aria-expanded', mob.classList.contains('open'));
});

// Unique header touch: shadow deepens once the page scrolls.
(function(){
  try {
    var bar = document.querySelector('.navbar');
    if (!bar) return;
    var onScroll = function () { bar.classList.toggle('scrolled', window.scrollY > 8); };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  } catch (e) {}
})();

// ── Theme Manager (delegates to WCTheme; legacy IDs preserved) ──
(function(){
  function activeMode() {
    if (window.WCTheme) return WCTheme.getMode();
    return document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
  }
  function syncSelects(mode) {
    ['themeModeSelect', 'mobileThemeModeSelect'].forEach(function (id) {
      var s = document.getElementById(id);
      if (s) s.value = (mode === 'dark' || mode === 'light' || mode === 'custom') ? mode : 'dark';
    });
  }
  syncSelects(document.documentElement.getAttribute('data-appearance') || activeMode());
  ['themeModeSelect', 'mobileThemeModeSelect'].forEach(function (id) {
    var s = document.getElementById(id);
    if (s && !s.getAttribute('data-nav-bound')) {
      s.setAttribute('data-nav-bound', '1');
      s.addEventListener('change', function () {
        if (window.WCTheme) WCTheme.setMode(s.value);
        syncSelects(s.value);
      });
    }
  });
  document.addEventListener('wc:theme-change', function (e) {
    if (e && e.detail && e.detail.mode) syncSelects(e.detail.mode);
  });
})();

// ★ Logout loading screen (situation-based 3D scene: secure sign-out)
document.addEventListener('click', (e) => {
  const a = e.target.closest && e.target.closest('a[href*="action=logout"]');
  if (!a) return;
  e.preventDefault();
  const url = a.href;
  try { if (window.Loader3D) Loader3D.show('Signing you out…', 'Securing your session', 'lock'); } catch (err) {}
  setTimeout(() => { window.location.href = url; }, 950);
});
</script>
