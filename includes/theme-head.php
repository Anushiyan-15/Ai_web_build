<?php
// includes/theme-head.php — shared theme bootstrap for pages WITHOUT nav.php
// (variations, studio, portal, site-manager, publish, intake). Emits:
// 1) FOUC-safe inline bootstrap (dark/light/custom + custom vars)
// 2) wc-theme.css link  3) wc-theme.js script
// Requires config.php (SITE_URL) loaded before include.
$__themeBase = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
?>
<script>
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
<link rel="stylesheet" href="<?= htmlspecialchars($__themeBase) ?>/assets/css/wc-theme.css">
<link rel="stylesheet" href="<?= htmlspecialchars($__themeBase) ?>/assets/css/wc-premium-3d.css">
<script src="<?= htmlspecialchars($__themeBase) ?>/assets/js/wc-theme.js"></script>
