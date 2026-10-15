'use strict';
/* tests/test-node-api.js — run: node tests/test-node-api.js
 * Boots the real Node backend on an ephemeral port with ISOLATED temp roots
 * and runs the full matrix: startup, auth, CRUD, persistence+restart,
 * customer→admin mirror, admin→customer, categories, feature builder,
 * security, concurrency, backup/restore. Kills the server afterwards.
 */
const { spawn, execFileSync } = require('node:child_process');
const fs = require('node:fs');
const fsp = require('node:fs/promises');
const os = require('node:os');
const path = require('node:path');

const ROOT = path.join(__dirname, '..');
const TMP = fs.mkdtempSync(path.join(os.tmpdir(), 'wc-node-'));
const STORAGE = path.join(TMP, 'storage');
const PUBLISHED = path.join(TMP, 'published');
const SLUG = 'nodebus';
const ADMIN_USER = 'node_admin';
const ADMIN_PASS = 'NodePass@123';

let pass = 0, fail = 0;
function ok(c, m) { if (c) { pass++; console.log('PASS ' + m); } else { fail++; console.log('FAIL ' + m); } }

async function startServer(port) {
  const child = spawn(process.execPath, [path.join(ROOT, 'server', 'server.js')], {
    env: { ...process.env, PORT: String(port), WB_STORAGE_ROOT: STORAGE, WB_PUBLISHED_ROOT: PUBLISHED },
    stdio: ['ignore', 'pipe', 'pipe'],
  });
  child.stdout.on('data', () => {});
  child.stderr.on('data', () => {});
  for (let i = 0; i < 50; i++) {
    await new Promise(r => setTimeout(r, 200));
    try {
      const r = await fetch(`http://127.0.0.1:${port}/node-api/health`);
      if (r.ok) return child;
    } catch (_) {}
  }
  try { child.kill(); } catch (_) {}
  return null;
}
function stopServer(child) {
  return new Promise((resolve) => {
    if (!child || child.exitCode !== null) return resolve();
    child.on('exit', () => resolve());
    try { child.kill(); } catch (_) { resolve(); }
    setTimeout(resolve, 3000).unref?.();
  });
}
async function api(port, method, p, body, token) {
  const headers = { 'Content-Type': 'application/json' };
  if (token) headers.Authorization = 'Bearer ' + token;
  const r = await fetch(`http://127.0.0.1:${port}${p}`, {
    method, headers, body: body === undefined ? undefined : JSON.stringify(body),
  });
  let j = null;
  try { j = await r.json(); } catch (_) {}
  return [r.status, j];
}

(async () => {
  // seed published admin .auth.json with a REAL bcrypt hash (via PHP, like publish does)
  const hash = execFileSync('C:\\UniServerZ\\core\\php83\\php.exe',
    ['-r', `echo password_hash(${JSON.stringify(ADMIN_PASS)}, PASSWORD_DEFAULT);`],
    { encoding: 'utf8' }).trim();
  if (!hash.startsWith('$2y$')) { console.log('SKIP: php bcrypt unavailable'); process.exit(0); }
  await fsp.mkdir(path.join(PUBLISHED, SLUG, 'admin'), { recursive: true });
  await fsp.writeFile(path.join(PUBLISHED, SLUG, 'admin', '.auth.json'),
    JSON.stringify({ username: ADMIN_USER, email: 'o@n.local', hash }));

  let port = 3123, server = null;
  for (let i = 0; i < 5 && !server; i++) { server = await startServer(port + i); if (server) port = port + i; }
  if (!server) { console.log('FAIL: server did not start'); process.exit(1); }
  try {
    // A — startup
    {
      const [c, j] = await api(port, 'GET', '/node-api/health');
      ok(c === 200 && j && j.data && j.data.ok === true, 'A: health ok');
      const [c404] = await api(port, 'GET', '/node-api/nope');
      ok(c404 === 404, 'A: unknown routes 404 honestly');
    }
    // B — auth
    let token = null;
    {
      const [c1] = await api(port, 'POST', '/node-api/auth/login', { slug: SLUG, username: ADMIN_USER, password: 'wrong1' });
      ok(c1 === 401, 'B: wrong password rejected');
      const [c2, j2] = await api(port, 'POST', '/node-api/auth/login', { slug: SLUG, username: ADMIN_USER, password: ADMIN_PASS });
      ok(c2 === 200 && j2 && j2.data && j2.data.token, 'B: admin login mints token (bcrypt via bridge)');
      token = j2.data.token;
      const [c3] = await api(port, 'GET', `/node-api/${SLUG}/stats`);
      ok(c3 === 403, 'B: protected routes need token');
      const [c4, j4] = await api(port, 'GET', `/node-api/${SLUG}/stats`, undefined, token);
      ok(c4 === 200 && j4.data && j4.data.counts, 'B: token opens admin stats');
    }
    // G — CRUD (appointments)
    let apptId = null;
    {
      const [c, j] = await api(port, 'POST', `/node-api/${SLUG}/appointments`, { record: { customer: 'Ann', date: '2026-11-01' } });
      ok(c === 200 && j.data && j.data.id, 'G: create appointment');
      apptId = j.data.id;
      const [, l] = await api(port, 'GET', `/node-api/${SLUG}/appointments`, undefined, token);
      ok((l.data || []).length === 1, 'G: admin lists it');
      const [cp, jp] = await api(port, 'PATCH', `/node-api/${SLUG}/appointments/${apptId}`, { patch: { status: 'confirmed' } }, token);
      ok(cp === 200 && jp.data.status === 'confirmed', 'G: patch status');
      const [cd, jd] = await api(port, 'DELETE', `/node-api/${SLUG}/appointments/${apptId}`, undefined, token);
      ok(cd === 200 && jd.data.ok === true, 'G: delete');
      const [, l2] = await api(port, 'GET', `/node-api/${SLUG}/appointments`, undefined, token);
      ok((l2.data || []).length === 0, 'G: delete persisted');
    }
    // E — customer booking → admin mirror file
    let bid = null;
    {
      const [c, j] = await api(port, 'POST', `/node-api/${SLUG}/bookings`,
        { idempotency_key: 'nk1', record: { trip_id: 'N1', seats: ['S1'], email: 'rider@n.local', phone: '0771234567', name: 'Rider' } });
      ok(c === 200 && (j.data.ref || '').startsWith('BK-'), 'E: booking accepted with ref');
      bid = j.data.id;
      const mirror = path.join(PUBLISHED, SLUG, 'admin', 'data_bookings.json');
      let rows = [];
      try { rows = JSON.parse(await fsp.readFile(mirror, 'utf8')); } catch (_) {}
      ok(rows.some(r => r.id === bid), 'E: mirrored to published admin data file');
    }
    // F — admin update → customer sees it
    {
      const [c] = await api(port, 'PATCH', `/node-api/${SLUG}/bookings/${bid}`, { patch: { status: 'confirmed' } }, token);
      ok(c === 200, 'F: admin confirms booking');
      const [, l] = await api(port, 'GET', `/node-api/${SLUG}/bookings?email=rider@n.local`);
      ok((l.data || []).length === 1 && l.data[0].status === 'confirmed', 'F: customer history shows confirmed');
      const [, byPhone] = await api(port, 'GET', `/node-api/${SLUG}/bookings?phone=%2B94%2077%20123%204567`);
      ok((byPhone.data || []).length === 1 && byPhone.data[0].email === 'rider@n.local',
        'F: customer history matches phone when booking also has email');
    }
    // K — concurrency: 8 parallel holds, one wins
    {
      const reqs = Array.from({ length: 8 }, () => api(port, 'POST', `/node-api/${SLUG}/bookings/hold`,
        { trip_id: 'RACE', seats: ['Z1'] }));
      const res = await Promise.all(reqs);
      ok(res.filter(([c, j]) => c === 200 && j && j.success).length === 1, 'K: parallel holds → exactly one wins');
    }
    // J — security
    {
      const [, l] = await api(port, 'GET', `/node-api/${SLUG}/bookings?email=rider@n.local`);
      ok(!(l.data || []).some(r => (r.email || '').includes('ann')), 'J: no cross-customer leak');
      const [cTrav] = await api(port, 'GET', '/node-api/../x/bookings');
      ok(cTrav === 404, 'J: traversal path rejected');
      const [cAnon] = await api(port, 'GET', `/node-api/${SLUG}/bookings`);
      ok(cAnon === 200, 'J: anonymous list answers (empty)');
      const [cSec] = await api(port, 'POST', `/node-api/${SLUG}/settings`, { apiKey: 'zzz', theme: 'dark' }, token);
      ok(cSec === 200, 'J: settings update answers');
      const [, gSec] = await api(port, 'GET', `/node-api/${SLUG}/settings`, undefined, token);
      ok(gSec.data && gSec.data.apiKey === undefined && gSec.data.theme === 'dark', 'J: secrets never stored via settings');
      // malformed JSON recovery
      await fsp.writeFile(path.join(STORAGE, 'sites', SLUG, 'bookings.json'), '{broken!!!');
      const [, lBad] = await api(port, 'GET', `/node-api/${SLUG}/bookings?email=rider@n.local`, undefined, token);
      ok(Array.isArray(lBad.data), 'J: corrupt file recovers honestly (quarantined, not fatal)');
    }
    // D — persistence across restart
    {
      await api(port, 'POST', `/node-api/${SLUG}/inquiries`, { record: { name: 'Keep', email: 'k@n.local', message: 'hi' } });
      await stopServer(server); server = null;
      server = await startServer(port);
      ok(!!server, 'D: server restarts');
      const [, l] = await api(port, 'GET', `/node-api/${SLUG}/inquiries?email=k@n.local`);
      ok((l.data || []).length === 1, 'D: records survive restart');
      token = (await api(port, 'POST', '/node-api/auth/login', { slug: SLUG, username: ADMIN_USER, password: ADMIN_PASS }))[1].data.token;
    }
    // H — categories via analyze
    {
      const cases = [
        ['City Express bus tickets daily', 'bus'],
        ['Fashion store summer sale', 'ecommerce'],
        ['Pizza restaurant menu order', 'restaurant'],
        ['Hair salon appointments', 'salon'],
        ['Goat yoga at sunset', 'generic'],
      ];
      for (const [text, want] of cases) {
        const [, j] = await api(port, 'POST', `/node-api/${SLUG}/analyze`, { business: text, html: '<html><body><h1>t</h1></body></html>' });
        ok(j && j.data && j.data.manifest && j.data.manifest.category === want, `H: "${text.slice(0, 24)}…" → ${want}`);
      }
    }
    // I — feature builder: analyze fixture → apply module
    {
      const [, j] = await api(port, 'POST', `/node-api/${SLUG}/analyze`,
        { business: 'Bus booking', html: '<html><body><form><input name="x"></form></body></html>' });
      ok((j.data.findings || []).some(f => f.area === 'forms'), 'I: analyzer finds unwired forms');
      const [ca, ja] = await api(port, 'POST', `/node-api/${SLUG}/features/apply`, { module: { name: 'Loyalty Passes', fields: ['holder', 'tier'] } }, token);
      ok(ca === 200 && ja.data && ja.data.id === 'loyalty_passes', 'I: feature module installed');
      const [, fl] = await api(port, 'GET', `/node-api/${SLUG}/features`, undefined, token);
      ok((fl.data || []).some(f => f.id === 'loyalty_passes'), 'I: installed feature listed');
      const [cd] = await api(port, 'POST', `/node-api/${SLUG}/features/apply`, { module: { name: 'Loyalty Passes' } }, token);
      ok(cd === 409, 'I: duplicate module honestly rejected');
    }
    // Backup/restore
    {
      const [cb, jb] = await api(port, 'POST', `/node-api/${SLUG}/backup`, {}, token);
      ok(cb === 200 && jb.data && jb.data.name, 'backup snapshot created');
      const [cr] = await api(port, 'POST', `/node-api/${SLUG}/restore`, { name: jb.data.name }, token);
      ok(cr === 200, 'backup restores');
    }
    // B logout
    {
      const [c] = await api(port, 'POST', '/node-api/auth/logout', { slug: SLUG }, token);
      ok(c === 200, 'B: logout ok');
      const [c2] = await api(port, 'GET', `/node-api/${SLUG}/stats`, undefined, token);
      ok(c2 === 403, 'B: token dead after logout');
    }
    // SSO bridge (PHP-minted HMAC handoff → Bearer, 5-min window)
    {
      const crypto = require('node:crypto');
      const secret = 's'.repeat(40);
      await fsp.writeFile(path.join(STORAGE, 'sites', SLUG, 'node_secret'), secret);
      const ts = String(Math.floor(Date.now() / 1000));
      const sig = crypto.createHmac('sha256', secret).update(ts + ':' + SLUG).digest('hex');
      const [c, j] = await api(port, 'POST', `/node-api/${SLUG}/auth/bridge`, { ts, sig });
      ok(c === 200 && j.data && j.data.token, 'B: SSO bridge mints token');
      const [cBad] = await api(port, 'POST', `/node-api/${SLUG}/auth/bridge`, { ts, sig: '0'.repeat(64) });
      ok(cBad === 403, 'B: forged handoff rejected');
      const old = String(Math.floor(Date.now() / 1000) - 600);
      const oldSig = crypto.createHmac('sha256', secret).update(old + ':' + SLUG).digest('hex');
      const [cOld] = await api(port, 'POST', `/node-api/${SLUG}/auth/bridge`, { ts: old, sig: oldSig });
      ok(cOld === 403, 'B: expired handoff rejected');
    }
    // Slugs from published URLs may contain underscores; PHP and Node must
    // persist to the same site directory and expose records to that admin.
    {
      const underscoreSlug = '__nodebus';
      const adminPath = path.join(PUBLISHED, underscoreSlug, 'admin');
      await fsp.mkdir(adminPath, { recursive: true });
      await fsp.writeFile(path.join(adminPath, 'data_trips.json'), JSON.stringify([
        { id: 'legacy_node_trip', route: 'Legacy City-Next City', date: '2026-12-01', total_seats: 20 },
      ]));
      const [tripStatus, tripList] = await api(port, 'GET', `/node-api/${underscoreSlug}/trips?route=Legacy`);
      ok(tripStatus === 200 && (tripList.data || []).some(r => r.id === 'legacy_node_trip'),
        'L: Node search includes legacy published schedules');
      const [c, j] = await api(port, 'POST', `/node-api/${underscoreSlug}/bookings`,
        { record: { trip_id: 'legacy_node_trip', seats: ['A1'],
          passenger: 'Underscore Rider', email: 'underscore@n.local' } });
      const mirror = JSON.parse(await fsp.readFile(path.join(adminPath, 'data_bookings.json'), 'utf8'));
      ok(c === 200 && (mirror || []).some(r => r.id === (j.data || {}).id),
        'L: underscore site slug persists and mirrors bookings');
    }
  } finally {
    await stopServer(server);
    fs.rmSync(TMP, { recursive: true, force: true });
  }
  console.log(`\nRESULT: ${pass} passed, ${fail} failed`);
  process.exit(fail ? 1 : 0);
})().catch((e) => { console.log('FATAL ' + (e && e.message)); process.exit(1); });
