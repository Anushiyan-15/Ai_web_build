/* assets/js/biz-intelligence.js — client for the Universal Intelligence Engine */
window.BizIntel = (function () {
  'use strict';
  function base() { try { if (typeof SITE_URL !== 'undefined' && SITE_URL) return SITE_URL; } catch (e) {} return ''; }
  async function analyze(data, description) {
    const r = await fetch(base() + '/api/biz-intelligence.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'analyze', data, description: description || '' })
    });
    return r.json();
  }
  async function diff(oldSpec, newSpec) {
    const r = await fetch(base() + '/api/biz-intelligence.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'diff', old: oldSpec, new: newSpec })
    });
    return r.json();
  }
  return { analyze, diff };
})();
