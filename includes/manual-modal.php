<?php
// includes/manual-modal.php — Shared "User Manual" popup (builder.php + studio.php).
// Usage: include this file once per page, plus one button calling
// openUserManual(). Self-styled (uman-*) so it works on any page
// without depending on local modal CSS.
//
// src is RELATIVE (manual.php sits next to the calling page) so the popup
// works on any host / port / scheme. An absolute SITE_URL broke it everywhere
// except localhost (blank dark modal — the iframe background showing through).
?>
<style>
.uman-overlay{display:none;position:fixed;inset:0;z-index:100001;background:rgba(0,0,0,.78);backdrop-filter:blur(8px);align-items:center;justify-content:center;padding:1.25rem;}
.uman-overlay.open{display:flex;animation:umanIn .25s cubic-bezier(.22,1,.36,1);}
@keyframes umanIn{from{opacity:0;transform:scale(.98);}to{opacity:1;transform:scale(1);}}
.uman-box{background:#111622;border:1.5px solid #28334d;border-radius:18px;width:min(1020px,96vw);height:min(88vh,900px);display:flex;flex-direction:column;overflow:hidden;box-shadow:0 25px 70px rgba(0,0,0,.7);}
.uman-bar{display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.7rem 1.1rem;background:#0d121c;border-bottom:1px solid #1e293b;flex-shrink:0;}
.uman-bar strong{color:#fff;font-size:.92rem;display:flex;align-items:center;gap:.5rem;font-family:inherit;}
.uman-bar .uman-actions{display:flex;align-items:center;gap:.5rem;}
.uman-bar a,.uman-bar button{font-family:inherit;font-size:.75rem;font-weight:700;border-radius:8px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;}
.uman-bar a{color:#38bdf8;padding:.35rem .6rem;border:1px solid #1e3a5f;background:#0a0f1c;}
.uman-bar a:hover{border-color:#38bdf8;}
.uman-bar button{background:#1e293b;border:1px solid #334155;color:#fff;padding:.35rem .7rem;}
.uman-bar button:hover{background:#7f1d1d;border-color:#ef4444;}
.uman-framewrap{flex:1;position:relative;min-height:0;display:flex;flex-direction:column;background:#060911;}
.uman-frame{flex:1;border:none;background:#060911;min-height:0;}
.uman-loading{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.5rem;color:#94a3b8;font-size:.85rem;font-weight:600;background:#060911;text-align:center;padding:1rem;}
.uman-loading span{font-size:.75rem;font-weight:400;}
.uman-loading a{color:#38bdf8;}
@media (prefers-reduced-motion: reduce){.uman-overlay.open{animation:none;}}
</style>
<div class="uman-overlay" id="uman-overlay" role="dialog" aria-label="User manual" onclick="if(event.target===this)closeUserManual()">
  <div class="uman-box">
    <div class="uman-bar">
      <strong>📘 User Manual — how to use, edit &amp; publish</strong>
      <span class="uman-actions">
        <a href="manual.php" target="_blank" rel="noopener">↗ Open full page</a>
        <button onclick="closeUserManual()">✕ Close</button>
      </span>
    </div>
    <div class="uman-framewrap">
      <div class="uman-loading" id="uman-loading">📘 Loading manual…<br><span>Blank for more than a few seconds? <a href="manual.php" target="_blank" rel="noopener">Open full page ↗</a></span></div>
      <iframe class="uman-frame" id="uman-frame" title="User manual" onload="umanFrameLoaded()"></iframe>
    </div>
  </div>
</div>
<script>
(function(){
  var umanLoaded = false, umanTimer = null;
  window.openUserManual = function(){
    try{
      var f = document.getElementById('uman-frame');
      if(f && !f.getAttribute('src')) f.src = 'manual.php';
      var l = document.getElementById('uman-loading');
      if(l) l.style.display = 'flex';
      umanLoaded = false;
      document.getElementById('uman-overlay').classList.add('open');
      if(umanTimer) clearTimeout(umanTimer);
      umanTimer = setTimeout(function(){
        if(!umanLoaded){
          var l2 = document.getElementById('uman-loading');
          if(l2) l2.innerHTML = '⚠️ Manual did not load inside the popup.<br><span><a href="manual.php" target="_blank" rel="noopener">Open full page ↗</a> instead.</span>';
        }
      }, 9000);
    }catch(e){}
  };
  window.umanFrameLoaded = function(){
    umanLoaded = true;
    if(umanTimer){ clearTimeout(umanTimer); umanTimer = null; }
    try{ document.getElementById('uman-loading').style.display = 'none'; }catch(e){}
  };
  window.closeUserManual = function(){
    try{ document.getElementById('uman-overlay').classList.remove('open'); }catch(e){}
    if(umanTimer){ clearTimeout(umanTimer); umanTimer = null; }
  };
  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape'){ try{ closeUserManual(); }catch(err){} }
  });
})();
</script>
