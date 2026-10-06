/* ═══════════════════════════════════════════════════════════════
   assets/js/loader-3d.js — Shared fullscreen 3D loader helper
   Usage: Loader3D.show('Crafting…', 'Please wait') · Loader3D.text('…') · Loader3D.hide()
   ═══════════════════════════════════════════════════════════════ */
window.Loader3D = window.Loader3D || (function () {
  'use strict';

  var overlay = null;
  var msgEl = null;
  var subEl = null;
  var sceneBox = null;

  var SCENES = {
    home:
      '<div class="wcl-hb" style="transform:scale(.82);">' +
      '<div class="wcl-hb-sun"></div>' +
      '<div class="wcl-hb-cloud c1"></div><div class="wcl-hb-cloud c2"></div>' +
      '<div class="wcl-hb-bricks"><i></i><i></i><i></i></div>' +
      '<div class="wcl-hb-walls"></div><div class="wcl-hb-roof"></div>' +
      '<div class="wcl-hb-door"></div>' +
      '<div class="wcl-hb-win w1"></div><div class="wcl-hb-win w2"></div>' +
      '<div class="wcl-hb-chimney"><div class="wcl-hb-smoke"><i></i><i></i><i></i></div></div>' +
      '<div class="wcl-hb-man"><div class="wcl-hb-bob">' +
      '<div class="wcl-hb-head"><span class="hat"></span></div>' +
      '<div class="wcl-hb-body"></div>' +
      '<div class="wcl-hb-legs"><i></i><i></i></div>' +
      '<div class="wcl-hb-arm"><span class="hammer"></span></div>' +
      '</div></div>' +
      '<div class="wcl-hb-ground"></div>' +
      '</div>',
    mail:
      '<div class="wcl-mail">' +
      '<div class="env-back"></div>' +
      '<div class="letter"></div>' +
      '<div class="env-front"></div>' +
      '<div class="env-flap"></div>' +
      '<div class="speed"><i></i><i></i><i></i></div>' +
      '</div>',
    rocket:
      '<div class="wcl-rocket">' +
      '<div class="star s1"></div><div class="star s2"></div><div class="star s3"></div>' +
      '<div class="rbody"></div><div class="rfin l"></div><div class="rfin r"></div>' +
      '<div class="rwin"></div><div class="flame"></div>' +
      '<div class="smoke"><i></i><i></i><i></i></div>' +
      '</div>',
    lock:
      '<div class="wcl-lock">' +
      '<div class="shackle"></div>' +
      '<div class="lbody"></div>' +
      '<div class="hole"></div>' +
      '<div class="key"></div>' +
      '</div>',
    save:
      '<div class="wcl-save">' +
      '<div class="halo"></div>' +
      '<div class="paper"></div>' +
      '<div class="wline"></div>' +
      '<div class="wline l2"></div>' +
      '<div class="pencil"></div>' +
      '<div class="done"></div>' +
      '<div class="spark s1">✦</div>' +
      '<div class="spark s2">✦</div>' +
      '<div class="spark s3">✦</div>' +
      '</div>',
    radar:
      '<div class="wcl-radar">' +
      '<div class="ring"></div><div class="ring r2"></div><div class="ring r3"></div>' +
      '<div class="sweep"></div>' +
      '<div class="blip b1"></div><div class="blip b2"></div>' +
      '</div>',
    db:
      '<div class="wcl-db">' +
      '<div class="cyl-top"></div>' +
      '<div class="cyl-body"></div>' +
      '<div class="cyl-base"></div>' +
      '<div class="orbit"><i></i><i></i></div>' +
      '<div class="pkt"><i></i><i></i><i></i></div>' +
      '<div class="ok"></div>' +
      '</div>',
    gallery:
      '<div class="wcl-gallery">' +
      '<div class="wcl-g-ring"></div><div class="wcl-g-ring r2"></div>' +
      '<div class="wcl-g-card c1"><i></i><i></i><i></i></div>' +
      '<div class="wcl-g-card c2"><i></i><i></i><i></i></div>' +
      '<div class="wcl-g-card c3"><i></i><i></i><i></i></div>' +
      '<div class="wcl-g-spark s1">✦</div>' +
      '<div class="wcl-g-spark s2">✦</div>' +
      '<div class="wcl-g-spark s3">✦</div>' +
      '</div>',
    back:
      '<div class="wcl-back">' +
      '<div class="ring r1"></div><div class="ring r2"></div><div class="ring r3"></div>' +
      '<div class="core"></div>' +
      '<div class="arrow">←</div>' +
      '<div class="orbit"><i></i><i></i></div>' +
      '<div class="trail"><i></i><i></i><i></i></div>' +
      '</div>'
  };

  function ensure() {
    if (overlay) return;
    overlay = document.createElement('div');
    overlay.id = 'wcl-overlay';
    overlay.innerHTML =
      '<div id="wcl-scene"></div>' +
      '<div class="wcl-msg" id="wcl-msg">Working</div>' +
      '<div class="wcl-bar"><i></i></div>' +
      '<div class="wcl-sub" id="wcl-sub">Please wait</div>';
    document.body.appendChild(overlay);
    msgEl = overlay.querySelector('#wcl-msg');
    subEl = overlay.querySelector('#wcl-sub');
    sceneBox = overlay.querySelector('#wcl-scene');
  }

  function setScene(type) {
    ensure();
    sceneBox.innerHTML = SCENES[type] || SCENES.home;
  }

  function show(message, sub, type) {
    ensure();
    setScene(type || 'home');
    if (message) msgEl.textContent = message;
    if (typeof sub === 'string') subEl.textContent = sub;
    overlay.classList.add('on');
    document.body.style.overflow = 'hidden';
  }

  function text(message) {
    ensure();
    if (message) msgEl.textContent = message;
  }

  function hide() {
    if (!overlay) return;
    overlay.classList.remove('on');
    document.body.style.overflow = '';
  }

  // Button helper: Loader3D.btn(el, true) locks with spinner, (el, false) restores
  function btn(el, busy, busyText) {
    if (!el) return;
    if (busy) {
      if (!el.dataset.wclOrig) el.dataset.wclOrig = el.innerHTML;
      el.classList.add('wcl-btn-busy');
      el.disabled = true;
      if (busyText) el.innerHTML = busyText;
    } else {
      el.classList.remove('wcl-btn-busy');
      el.disabled = false;
      if (el.dataset.wclOrig) { el.innerHTML = el.dataset.wclOrig; delete el.dataset.wclOrig; }
    }
  }

  return { show: show, hide: hide, text: text, btn: btn };
})();
