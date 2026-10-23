<?php
// includes/theme-head.php — fixed green theme bootstrap for pages WITHOUT nav.php
// (variations, studio, portal, site-manager, publish, intake).
// Emits: 1) FOUC-safe fixed-appearance bootstrap (no stored prefs are read)
//        2) wc-brand.css + wc-theme.css + wc-premium-3d.css
//        3) wc-theme.js + wc-sparkle.js
// Requires config.php (SITE_URL) loaded before include.
$__themeBase = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
$__themeSurface = (isset($wc_theme_surface) && $wc_theme_surface === 'editor') ? 'editor' : 'light';
$__themeMode = $__themeSurface === 'editor' ? 'dark' : 'light';
?>
<script>
(function(){
  try {
    // Clear every legacy appearance preference — the brand theme is fixed.
    ['wc_theme_mode','wc_theme_custom','wc_theme','wc-theme-mode','wc-theme'].forEach(function(k){
      try { localStorage.removeItem(k); } catch(e) {}
    });
    var h = document.documentElement;
    h.setAttribute('data-theme', <?= json_encode($__themeMode) ?>);
    h.setAttribute('data-wc-surface', <?= json_encode($__themeSurface) ?>);
    h.setAttribute('data-appearance', 'green');
  } catch (e) {}
})();
</script>
<link rel="stylesheet" href="<?= htmlspecialchars($__themeBase) ?>/assets/css/wc-brand.css">
<link rel="stylesheet" href="<?= htmlspecialchars($__themeBase) ?>/assets/css/wc-theme.css">
<link rel="stylesheet" href="<?= htmlspecialchars($__themeBase) ?>/assets/css/wc-premium-3d.css">
<script src="<?= htmlspecialchars($__themeBase) ?>/assets/js/wc-theme.js"></script>
<script src="<?= htmlspecialchars($__themeBase) ?>/assets/js/wc-sparkle.js" defer></script>
