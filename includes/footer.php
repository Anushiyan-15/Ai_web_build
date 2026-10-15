<?php // Shared Footer — theme-aware (follows Dark / Light / Custom) ?>
<style>
.foot-link{transition:color .15s ease;}
.foot-link:hover{color:var(--wc-text, #fff) !important;}
</style>
<footer style="background:var(--wc-surface-2, var(--n900));color:var(--wc-text-2, var(--n400));padding:3.5rem 0 2rem;margin-top:4rem;border-top:1px solid var(--wc-border, transparent);transition:background .25s,color .25s;">
  <div class="container">
    <div class="grid-4" style="margin-bottom:2.5rem">
      <div>
        <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:1rem">
          <div style="width:30px;height:30px;background:linear-gradient(135deg,var(--p),#a855f7);border-radius:8px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.9rem">✦</div>
          <span style="font-size:1.1rem;font-weight:800;color:var(--wc-text, #fff)"><?= SITE_NAME ?></span>
        </div>
        <p style="font-size:.85rem;line-height:1.7;max-width:220px"><?= SITE_TAGLINE ?></p>
      </div>
      <div>
        <h4 style="color:var(--wc-text, #fff);font-size:.85rem;font-weight:700;margin-bottom:1rem;text-transform:uppercase;letter-spacing:.06em">Platform</h4>
        <ul style="list-style:none;display:flex;flex-direction:column;gap:.6rem">
          <li><a href="<?= SITE_URL ?>/builder.php" class="foot-link" style="font-size:.875rem">AI Builder</a></li>
          <li><a href="<?= SITE_URL ?>/client-intake.php" class="foot-link" style="font-size:.875rem">Get a Quote</a></li>
          <li><a href="<?= SITE_URL ?>/contact.php" class="foot-link" style="font-size:.875rem">Contact Us</a></li>
        </ul>
      </div>
      <div>
        <h4 style="color:var(--wc-text, #fff);font-size:.85rem;font-weight:700;margin-bottom:1rem;text-transform:uppercase;letter-spacing:.06em">Resources</h4>
        <ul style="list-style:none;display:flex;flex-direction:column;gap:.6rem">
          <li><a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener" class="foot-link" style="font-size:.875rem">Get Free API Key ↗</a></li>
          <li><a href="https://ai.google.dev/gemini-api/docs" target="_blank" rel="noopener" class="foot-link" style="font-size:.875rem">Gemini API Docs ↗</a></li>
        </ul>
      </div>
      <div>
        <h4 style="color:var(--wc-text, #fff);font-size:.85rem;font-weight:700;margin-bottom:1rem;text-transform:uppercase;letter-spacing:.06em">Contact</h4>
        <p style="font-size:.875rem;margin-bottom:.5rem">📧 <?= CONTACT_EMAIL ?></p>
        <p style="font-size:.875rem">Built with ❤️ &amp; Gemini AI</p>
      </div>
    </div>
    <div style="border-top:1px solid var(--wc-border, var(--n800));padding-top:1.5rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem">
      <p style="font-size:.8rem">© <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.</p>
      <p style="font-size:.8rem">Powered by <a href="https://deepmind.google/gemini/" target="_blank" style="color:var(--p)">Google Gemini AI</a></p>
    </div>
  </div>
</footer>
