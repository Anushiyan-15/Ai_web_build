<?php
// Shared Footer — fixed green brand
require_once __DIR__ . '/brand-logo.php';
$__footInvert = (isset($wc_theme_surface) && $wc_theme_surface === 'editor');
?>
<style>
.foot-link{transition:color .15s ease;}
.foot-link:hover{color:#16A34A !important;}
</style>
<footer style="background:<?= $__footInvert ? '#0F172A' : '#F0FDF4' ?>;color:<?= $__footInvert ? '#CBD5E1' : '#4B5563' ?>;padding:3.5rem 0 2rem;margin-top:4rem;border-top:1px solid <?= $__footInvert ? '#1F2937' : '#DCFCE7' ?>;">
  <div class="container">
    <div class="grid-4" style="margin-bottom:2.5rem">
      <div>
        <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:1rem">
          <?= wc_brand_logo(['size' => 'sm', 'variant' => $__footInvert ? 'invert' : 'default']) ?>
        </div>
        <p style="font-size:.85rem;line-height:1.7;max-width:220px"><?= SITE_TAGLINE ?></p>
      </div>
      <div>
        <h4 style="color:<?= $__footInvert ? '#F8FAFC' : '#1F2937' ?>;font-size:.85rem;font-weight:700;margin-bottom:1rem;text-transform:uppercase;letter-spacing:.06em">Platform</h4>
        <ul style="list-style:none;display:flex;flex-direction:column;gap:.6rem">
          <li><a href="<?= SITE_URL ?>/builder.php" class="foot-link" style="font-size:.875rem">AI Builder</a></li>
          <li><a href="<?= SITE_URL ?>/client-intake.php" class="foot-link" style="font-size:.875rem">Get a Quote</a></li>
          <li><a href="<?= SITE_URL ?>/contact.php" class="foot-link" style="font-size:.875rem">Contact Us</a></li>
        </ul>
      </div>
      <div>
        <h4 style="color:<?= $__footInvert ? '#F8FAFC' : '#1F2937' ?>;font-size:.85rem;font-weight:700;margin-bottom:1rem;text-transform:uppercase;letter-spacing:.06em">Resources</h4>
        <ul style="list-style:none;display:flex;flex-direction:column;gap:.6rem">
          <li><a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener" class="foot-link" style="font-size:.875rem">Get Free API Key ↗</a></li>
          <li><a href="https://ai.google.dev/gemini-api/docs" target="_blank" rel="noopener" class="foot-link" style="font-size:.875rem">Gemini API Docs ↗</a></li>
        </ul>
      </div>
      <div>
        <h4 style="color:<?= $__footInvert ? '#F8FAFC' : '#1F2937' ?>;font-size:.85rem;font-weight:700;margin-bottom:1rem;text-transform:uppercase;letter-spacing:.06em">Contact</h4>
        <p style="font-size:.875rem;margin-bottom:.5rem">📧 <?= CONTACT_EMAIL ?></p>
        <p style="font-size:.875rem">Built with ❤️ &amp; Gemini AI</p>
      </div>
    </div>
    <div style="border-top:1px solid <?= $__footInvert ? '#1F2937' : '#DCFCE7' ?>;padding-top:1.5rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem">
      <p style="font-size:.8rem">© <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.</p>
      <p style="font-size:.8rem">Powered by <a href="https://deepmind.google/gemini/" target="_blank" style="color:#16A34A">Google Gemini AI</a></p>
    </div>
  </div>
</footer>
