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
<script src="<?= SITE_URL ?>/assets/js/loader-3d.js"></script>
<style>
/* ── GLOBAL RESETS ── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth;font-size:16px}
body{font-family:'Inter',system-ui,sans-serif;color:#1e1b4b;background:#fff;line-height:1.6}
a{text-decoration:none;color:inherit}
img{max-width:100%;display:block}

/* ── CSS VARIABLES ── */
:root{
  --p:#6366f1;--pd:#4338ca;--pl:#ede9fe;
  --a:#06b6d4;--s:#10b981;--w:#f59e0b;--d:#ef4444;
  --n50:#f9fafb;--n100:#f3f4f6;--n200:#e5e7eb;--n300:#d1d5db;
  --n400:#9ca3af;--n500:#6b7280;--n600:#4b5563;--n700:#374151;
  --n800:#1f2937;--n900:#111827;
  --r:10px;--sh:0 4px 24px rgba(99,102,241,.12);
  --t:.18s ease;
}

/* ── NAVBAR ── */
.navbar{
  position:sticky;top:0;z-index:1000;
  background:rgba(255,255,255,.92);
  backdrop-filter:blur(16px);
  border-bottom:1px solid var(--n200);
  padding:0 2rem;
  display:flex;align-items:center;justify-content:space-between;
  height:64px;
}
.nav-logo{
  display:flex;align-items:center;gap:.6rem;
  font-size:1.2rem;font-weight:800;color:var(--p);
  letter-spacing:-.02em;
}
.nav-logo .logo-icon{
  width:34px;height:34px;
  background:linear-gradient(135deg,var(--p),#a855f7);
  border-radius:9px;
  display:flex;align-items:center;justify-content:center;
  color:#fff;font-size:1rem;
}
.nav-links{display:flex;align-items:center;gap:.25rem}
.nav-link{
  padding:.45rem .9rem;
  border-radius:8px;
  font-size:.875rem;font-weight:500;
  color:var(--n600);
  transition:var(--t);
}
.nav-link:hover{color:var(--p);background:var(--pl)}
.nav-link.active{color:var(--p);background:var(--pl);font-weight:600}
.nav-link.cta{
  background:linear-gradient(135deg,var(--p),#7c3aed);
  color:#fff !important;
  padding:.45rem 1.1rem;
  box-shadow:0 4px 12px rgba(99,102,241,.3);
}
.nav-link.cta:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(99,102,241,.4)}
.hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:8px;border:none;background:transparent}
.hamburger span{display:block;width:22px;height:2px;background:var(--n600);border-radius:2px;transition:var(--t)}
.mobile-menu{
  display:none;
  position:fixed;top:64px;left:0;right:0;
  background:#fff;border-bottom:1px solid var(--n200);
  padding:1rem 1.5rem;
  flex-direction:column;gap:.5rem;
  z-index:999;
  box-shadow:0 8px 24px rgba(0,0,0,.08);
}
.mobile-menu.open{display:flex}
.mobile-menu .nav-link{font-size:1rem;padding:.65rem 1rem}

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
.heading-xl{font-size:clamp(2rem,5vw,3.5rem);font-weight:900;letter-spacing:-.03em;line-height:1.1}
.heading-lg{font-size:clamp(1.5rem,3.5vw,2.4rem);font-weight:800;letter-spacing:-.02em}
.heading-md{font-size:clamp(1.2rem,2.5vw,1.6rem);font-weight:700}
.lead{font-size:1.1rem;color:var(--n500);line-height:1.75;max-width:600px}
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
.btn-outline{background:#fff;color:var(--p);border:1.5px solid var(--p)}
.btn-outline:hover{background:var(--pl)}
.btn-lg{padding:1rem 2.2rem;font-size:1rem;border-radius:12px}
.btn-ghost{background:transparent;color:var(--n600);border:1.5px solid var(--n300)}
.btn-ghost:hover{color:var(--p);border-color:var(--p);background:var(--pl)}
.card{
  background:#fff;border-radius:16px;border:1px solid var(--n200);
  padding:1.75rem;box-shadow:var(--sh);
  transition:transform var(--t),box-shadow var(--t);
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
  .navbar{padding:0 1.25rem}
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
