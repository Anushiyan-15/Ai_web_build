<?php
// Shared Navbar — included in every page
if (session_status() === PHP_SESSION_NONE) session_start();
// No-cache: back button after logout must refetch (logged-in view never served stale)
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
require_once __DIR__ . '/brand-logo.php';
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
// The builder keeps its dark editor chrome; everything else is light green.
$wcSurface = (isset($wc_theme_surface) && $wc_theme_surface === 'editor') ? 'editor' : 'light';
$wcMode = $wcSurface === 'editor' ? 'dark' : 'light';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $wcMode ?>" data-wc-surface="<?= $wcSurface ?>" data-appearance="green">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($page_title) ? htmlspecialchars($page_title).' — '.SITE_NAME : SITE_NAME ?></title>
<meta name="description" content="<?= isset($page_desc) ? htmlspecialchars($page_desc) : SITE_TAGLINE ?>">
<meta name="theme-color" content="#16A34A">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/loader-3d.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/wc-brand.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/wc-theme.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/wc-premium-3d.css">
<script>
/* Fixed green appearance bootstrap — legacy theme prefs are cleared, never read. */
(function(){
  try {
    ['wc_theme_mode','wc_theme_custom','wc_theme','wc-theme-mode','wc-theme'].forEach(function(k){
      try { localStorage.removeItem(k); } catch(e) {}
    });
    var h = document.documentElement;
    h.setAttribute('data-theme', <?= json_encode($wcMode) ?>);
    h.setAttribute('data-wc-surface', <?= json_encode($wcSurface) ?>);
    h.setAttribute('data-appearance', 'green');
  } catch (e) {}
})();
</script>
<script src="<?= SITE_URL ?>/assets/js/wc-theme.js"></script>
<script src="<?= SITE_URL ?>/assets/js/loader-3d.js"></script>
<script src="<?= SITE_URL ?>/assets/js/wc-sparkle.js" defer></script>
<style>
/* ── GLOBAL RESETS ── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth;font-size:16px}
body{font-family:'Inter',system-ui,sans-serif;color:var(--body-color);background:var(--body-bg);line-height:1.6}
a{text-decoration:none;color:inherit}
img{max-width:100%;display:block}

/* ── CSS VARIABLES — FIXED GREEN BRAND ── */
:root{
  --p:#16A34A;--pd:#166534;--pl:#DCFCE7;
  --a:#22C55E;--s:#15803D;--w:#f59e0b;--d:#ef4444;
  --n50:#f9fafb;--n100:#f3f4f6;--n200:#e5e7eb;--n300:#d1d5db;
  --n400:#9ca3af;--n500:#6b7280;--n600:#4b5563;--n700:#374151;
  --n800:#1f2937;--n900:#111827;
  --r:10px;--sh:0 4px 24px rgba(22,163,74,.12);
  --t:.18s ease;
  /* Semantic surface tokens */
  --body-bg:#FFFFFF;
  --body-color:#1F2937;
  --surface:#FFFFFF;
  --surface-2:#F0FDF4;
  --surface-3:#DCFCE7;
  --border:#E2E8F0;
  --text-primary:#1F2937;
  --text-secondary:#4B5563;
  --text-muted:#64748B;
  --navbar-bg:rgba(255,255,255,.94);
  --navbar-border:#E2E8F0;
  --card-bg:#FFFFFF;
  --card-border:#E2E8F0;
  --input-bg:#FFFFFF;
  --input-border:#CBD5E1;
  --mobile-menu-bg:#FFFFFF;
}

/* Editor chrome keeps a dark surface with green accents */
[data-wc-surface="editor"]{
  --body-bg:#0B1220;
  --body-color:#F8FAFC;
  --surface:#111827;
  --surface-2:#0F172A;
  --surface-3:#16202F;
  --border:#1F2937;
  --text-primary:#F8FAFC;
  --text-secondary:#CBD5E1;
  --text-muted:#94A3B8;
  --n50:#0F172A;--n100:#16202F;--n200:#1F2937;--n300:#334155;
  --n400:#64748B;--n500:#94A3B8;--n600:#CBD5E1;--n700:#E2E8F0;
  --n800:#F1F5F9;--n900:#F8FAFC;
  --pl:rgba(34,197,94,.16);--pd:#86EFAC;
  --navbar-bg:rgba(11,18,32,.94);
  --navbar-border:#1F2937;
  --card-bg:#111827;
  --card-border:#1F2937;
  --input-bg:#0C1220;
  --input-border:#334155;
  --mobile-menu-bg:#111827;
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
  background:linear-gradient(90deg,transparent,#16A34A,#22C55E,#15803D,transparent);
  background-size:200% 100%;
  animation:navSheen 7s linear infinite;
  opacity:.85;pointer-events:none;
}
@keyframes navSheen{to{background-position:200% 0}}
.navbar.scrolled{box-shadow:0 10px 34px rgba(22,163,74,.14)}
.nav-logo{display:flex;align-items:center;gap:.65rem;text-decoration:none}
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
  background:linear-gradient(90deg,#16A34A,#22C55E);
  box-shadow:0 0 8px rgba(22,163,74,.6);
}
.nav-link.cta{
  background:linear-gradient(135deg,#16A34A,#15803D);
  color:#fff !important;
  padding:.5rem 1.2rem;
  box-shadow:0 4px 14px rgba(22,163,74,.35);
  overflow:hidden;
}
.nav-link.cta::before{
  content:'';position:absolute;top:0;left:-80%;width:50%;height:100%;
  background:linear-gradient(100deg,transparent,rgba(255,255,255,.5),transparent);
  transform:skewX(-20deg);
  animation:ctaShine 3.4s ease-in-out infinite;
}
@keyframes ctaShine{0%{left:-80%}55%,100%{left:180%}}
.nav-link.cta:hover{transform:translateY(-1px);box-shadow:0 8px 22px rgba(22,163,74,.45)}
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
  box-shadow:0 24px 60px rgba(15,23,42,.18);
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
  border:1px solid rgba(22,163,74,.2);
  margin-bottom:1rem;
}
.heading-xl{font-size:clamp(2rem,5vw,3.5rem);font-weight:900;letter-spacing:-.03em;line-height:1.1;color:var(--text-primary)}
.heading-lg{font-size:clamp(1.5rem,3.5vw,2.4rem);font-weight:800;letter-spacing:-.02em;color:var(--text-primary)}
.heading-md{font-size:clamp(1.2rem,2.5vw,1.6rem);font-weight:700;color:var(--text-primary)}
.lead{font-size:1.1rem;color:var(--text-muted);line-height:1.75;max-width:600px}
.lead.center{margin:0 auto}
.gradient-text{
  background:linear-gradient(135deg,#166534,#16A34A,#22C55E);
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
  background:linear-gradient(135deg,#16A34A,#15803D);
  color:#fff;
  box-shadow:0 4px 16px rgba(22,163,74,.3);
}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(22,163,74,.4)}
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
.card:hover{transform:translateY(-3px);box-shadow:0 12px 40px rgba(22,163,74,.15)}
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
  .navbar::before,.nav-link.cta::before{animation:none !important}
}
</style>

<nav class="navbar" role="navigation" aria-label="Main navigation">
  <a href="<?= $homeHref ?>" class="nav-logo" aria-label="//WEBbuilders.lk Home">
    <?= wc_brand_logo(['size' => 'md', 'variant' => $wcSurface === 'editor' ? 'invert' : 'default']) ?>
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
