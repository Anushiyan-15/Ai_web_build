'use strict';
/* server/server.js — WebCraft business-operations backend (Node.js, zero deps).
 * JSON files are the database: storage/sites/<slug>/*.json (+ legacy
 * read-union storage/collections/<slug>/*.json). Published admin dashboards
 * manage the very same rows via the admin mirror (convergent upsert-by-id).
 * Run:  node server/server.js   (PORT / WB_* env; see server/README.md)
 */
const http = require('node:http');
const fs = require('node:fs');
const fsp = require('node:fs/promises');
const path = require('node:path');
const crypto = require('node:crypto');
const store = require('./lib/store');
const auth = require('./lib/auth');
const v = require('./lib/validate');
const biz = require('./lib/business');

const PORT = Number(process.env.PORT || process.env.WB_PORT || 3100);
const CORS = process.env.WB_CORS || '*';
const MAX_BODY = 1024 * 1024;
// Instance token lets a control panel prove a live server is the one IT
// started (stop refuses anything else). Unset for manual runs.
const INSTANCE_TOKEN = String(process.env.WB_INSTANCE_TOKEN || '');
const PID_FILE = String(process.env.WB_PID_FILE || '');

function send(res, code, body) {
  const s = JSON.stringify(body);
  res.writeHead(code, {
    'Content-Type': 'application/json',
    'Access-Control-Allow-Origin': CORS,
    'Access-Control-Allow-Methods': 'GET,POST,PATCH,DELETE,OPTIONS',
    'Access-Control-Allow-Headers': 'Content-Type, Authorization',
    'Content-Length': Buffer.byteLength(s),
  });
  res.end(s);
}
const ok = (res, data, extra) => send(res, 200, { success: true, data, ...(extra || {}) });
const fail = (res, error, code = 400) => send(res, code, { success: false, error });

// Simple per-IP rate buckets (login strict, API lenient).
const buckets = new Map();
function rateAllow(key, max, windowMs) {
  const now = Date.now();
  const list = (buckets.get(key) || []).filter(t => now - t < windowMs);
  if (list.length >= max) { buckets.set(key, list); return false; }
  list.push(now);
  buckets.set(key, list.slice(-100));
  return true;
}

function readBody(req) {
  return new Promise((resolve, reject) => {
    let n = 0;
    const chunks = [];
    req.on('data', (c) => {
      n += c.length;
      if (n > MAX_BODY) { reject(new Error('body too large')); req.destroy(); return; }
      chunks.push(c);
    });
    req.on('end', () => {
      const s = Buffer.concat(chunks).toString('utf8');
      if (!s) return resolve({});
      try { resolve(JSON.parse(s)); } catch (_) { resolve({ _raw: s }); }
    });
    req.on('error', reject);
  });
}
function bearer(req) {
  const h = req.headers.authorization || '';
  const m = h.match(/^Bearer\s+(.+)$/i);
  return m ? m[1].trim() : null;
}
function ipOf(req) {
  return (req.socket && req.socket.remoteAddress) || 'unknown';
}
async function needAdmin(req, res, slug) {
  const t = await auth.checkToken(slug, bearer(req));
  if (!t) { fail(res, 'Admin only', 403); return null; }
  return t;
}
function meOf(req, body) {
  return String((body && (body.email || body.phone)) || '').toLowerCase();
}

async function handle(req, res) {
  const u = new URL(req.url || '/', 'http://x');
  const parts = u.pathname.split('/').filter(Boolean);
  if (req.method === 'OPTIONS') return send(res, 204, {});

  // GET /node-api/health
  if (parts.length === 2 && parts[0] === 'node-api' && parts[1] === 'health' && req.method === 'GET') {
    let sites = 0;
    try { sites = (await fsp.readdir(path.join(store.roots().storage, 'sites'))).length; } catch (_) {}
    return ok(res, { ok: true, uptime: Math.round(process.uptime()), sites, storage: 'json', node: process.version, instance: INSTANCE_TOKEN === '' ? null : INSTANCE_TOKEN });
  }

  // POST /node-api/control/stop
  if (parts.length === 3 && parts[0] === 'node-api' && parts[1] === 'control' && parts[2] === 'stop' && req.method === 'POST') {
    let body = {};
    try { body = await readBody(req); } catch (_) {}
    if (INSTANCE_TOKEN !== '' && body.token !== INSTANCE_TOKEN) {
      return fail(res, 'Unauthorized', 403);
    }
    ok(res, { stopping: true });
    setTimeout(() => {
      try { if (PID_FILE && fs.existsSync(PID_FILE)) fs.unlinkSync(PID_FILE); } catch (_) {}
      process.exit(0);
    }, 150);
    return;
  }
  if (parts[0] !== 'node-api') return fail(res, 'Not found', 404);

  // POST /node-api/auth/login {slug,username,password}
  if (parts.length === 3 && parts[1] === 'auth' && parts[2] === 'login' && req.method === 'POST') {
    const body = await readBody(req);
    const slug = store.cleanSlug(body.slug);
    if (!rateAllow('login:' + ipOf(req), 5, 5 * 60 * 1000)) return fail(res, 'Too many attempts. Wait 5 minutes.', 429);
    const af = await auth.readAuthFile(slug);
    if (!af || !af.username || !af.hash) return fail(res, 'Invalid username or password', 401);
    if (String(af.username).toLowerCase() !== String(body.username || '').toLowerCase()) return fail(res, 'Invalid username or password', 401);
    if (!(await auth.phpVerify(String(body.password || ''), String(af.hash)))) return fail(res, 'Invalid username or password', 401);
    const token = await auth.mintToken(slug, af.username);
    await store.audit(slug, { event: 'login', user: af.username });
    return ok(res, { token });
  }
  // POST /node-api/auth/logout
  if (parts.length === 3 && parts[1] === 'auth' && parts[2] === 'logout' && req.method === 'POST') {
    const body = await readBody(req);
    await auth.revokeToken(store.cleanSlug(body.slug), bearer(req));
    return ok(res, { ok: true });
  }

  // Site-scoped routes: /node-api/:slug/...
  const slug = store.cleanSlug(parts[1]);
  const rest = parts.slice(2);

  // POST /node-api/:slug/auth/bridge {ts, sig} — SSO handoff minted by the
  // PHP admin page (which already verified the admin session). 5-min window,
  // constant-time HMAC check against storage/sites/<slug>/node_secret.
  if (rest.length === 2 && rest[0] === 'auth' && rest[1] === 'bridge' && req.method === 'POST') {
    const body = await readBody(req);
    const ts = Number(body.ts || 0);
    const sig = String(body.sig || '');
    if (!ts || !sig || Math.abs(Date.now() - ts * 1000) > 5 * 60 * 1000) {
      return fail(res, 'Expired handoff', 403);
    }
    let secret = '';
    try { secret = String(await fsp.readFile(path.join(store.siteDir(slug), 'node_secret'), 'utf8')).trim(); } catch (_) {}
    if (secret.length < 32) return fail(res, 'Bridge not configured', 403);
    const good = crypto.timingSafeEqual(
      Buffer.from(crypto.createHmac('sha256', secret).update(ts + ':' + slug).digest('hex')),
      Buffer.from(sig.length === 64 ? sig : '0'.repeat(64)));
    if (!good) return fail(res, 'Bad handoff', 403);
    const token = await auth.mintToken(slug, 'admin:sso');
    await store.audit(slug, { event: 'login', user: 'admin:sso' });
    return ok(res, { token });
  }

  // POST /node-api/:slug/analyze {html, category?, business?}
  if (rest.length === 1 && rest[0] === 'analyze' && req.method === 'POST') {
    const body = await readBody(req);
    let manifest = null;
    try {
      manifest = await store.readJson(path.join(store.siteDir(slug), 'business.json'), null);
    } catch (_) { manifest = null; }
    if (!manifest) {
      const cat = body.category && biz.CATEGORIES[body.category]
        ? body.category
        : biz.detectCategory((body.business || '') + ' ' + (body.html || '').slice(0, 2000));
      manifest = biz.manifestFor(slug, body.business || slug, cat, []);
    }
    const r = biz.analyze(manifest, body.html || '');
    return ok(res, { manifest: { category: manifest.category, label: manifest.label, entities: manifest.entities }, ...r });
  }

  // GET /node-api/:slug/features  (+ POST /node-api/:slug/features/apply {module})
  if (rest.length === 1 && rest[0] === 'features' && req.method === 'GET') {
    if (!(await needAdmin(req, res, slug))) return;
    const feats = await store.readJson(path.join(store.siteDir(slug), 'features.json'), []);
    return ok(res, Array.isArray(feats) ? feats : []);
  }
  if (rest.length === 2 && rest[0] === 'features' && rest[1] === 'apply' && req.method === 'POST') {
    const admin = await needAdmin(req, res, slug);
    if (!admin) return;
    const body = await readBody(req);
    const name = String((body.module && body.module.name) || body.name || '').trim().slice(0, 60);
    if (!name) return fail(res, 'Module name required');
    if (!rateAllow('apply:' + ipOf(req), 10, 60 * 1000)) return fail(res, 'Too many requests', 429);
    await backupSite(slug, 'pre-feature');
    const id = name.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '').slice(0, 40) || 'module';
    const fields = Array.isArray((body.module && body.module.fields) || body.fields)
      ? (body.module.fields || body.fields).slice(0, 12).map(String) : ['title', 'details'];
    const feats = await store.readJson(path.join(store.siteDir(slug), 'features.json'), []);
    const list = Array.isArray(feats) ? feats : [];
    if (list.some(f => f.id === id)) return fail(res, 'Module already installed', 409);
    const mod = { id, name, fields, installed_at: new Date().toISOString(), by: admin.user || 'admin' };
    list.push(mod);
    await store.writeJson(path.join(store.siteDir(slug), 'features.json'), list);
    // entity data file (entity convention <id>.json)
    await store.writeJson(path.join(store.siteDir(slug), id + '.json'), []);
    // admin data file too: the published Manage Data page auto-discovers
    // data_*.json tabs, so the new module is manageable immediately.
    try {
      const ad = store.adminDir(slug);
      const st = await fsp.stat(ad).catch(() => null);
      if (st && st.isDirectory()) {
        await store.writeJson(path.join(ad, 'data_' + id + '.json'), []);
      }
    } catch (_) {}
    await store.audit(slug, { event: 'feature.apply', module: id, user: admin.user || 'admin' });
    return ok(res, mod);
  }

  // POST /node-api/:slug/backup  +  POST /node-api/:slug/restore {name}
  if (rest.length === 1 && rest[0] === 'backup' && req.method === 'POST') {
    if (!(await needAdmin(req, res, slug))) return;
    const name = await backupSite(slug, 'manual');
    await store.audit(slug, { event: 'backup', name });
    return ok(res, { name });
  }
  if (rest.length === 1 && rest[0] === 'restore' && req.method === 'POST') {
    if (!(await needAdmin(req, res, slug))) return;
    const body = await readBody(req);
    const name = String(body.name || '').replace(/[^a-z0-9\-_.]/gi, '');
    if (!name) return fail(res, 'Backup name required');
    const f = store.safeJoin(store.siteDir(slug), 'backups', name);
    const snap = await store.readJson(f, null);
    if (!snap || typeof snap !== 'object') return fail(res, 'Backup not found', 404);
    await backupSite(slug, 'pre-restore');
    for (const [k, val] of Object.entries(snap.files || {})) {
      if (!/^[\w\-.]+\.json$/.test(k)) continue;
      await store.writeJson(store.safeJoin(store.siteDir(slug), k), val);
    }
    await store.audit(slug, { event: 'restore', name });
    return ok(res, { ok: true });
  }

  // GET/POST /node-api/:slug/settings (admin)
  if (rest.length === 1 && rest[0] === 'settings') {
    if (!(await needAdmin(req, res, slug))) return;
    const f = path.join(store.siteDir(slug), 'settings.json');
    if (req.method === 'GET') return ok(res, await store.readJson(f, {}));
    if (req.method === 'POST') {
      const body = await readBody(req);
      const cur = await store.readJson(f, {});
      const next = { ...cur };
      for (const [k, val] of Object.entries(body || {})) {
        if (/key|secret|pass|token/i.test(k)) continue; // never store secrets via settings
        next[k] = val;
      }
      await store.writeJson(f, next);
      await store.audit(slug, { event: 'settings.update' });
      return ok(res, next);
    }
    return fail(res, 'Method not allowed', 405);
  }

  // GET /node-api/:slug/stats (admin)
  if (rest.length === 1 && rest[0] === 'stats' && req.method === 'GET') {
    if (!(await needAdmin(req, res, slug))) return;
    const counts = {};
    for (const e of ['bookings', 'orders', 'inquiries', 'subscribers', 'appointments', 'payments', 'products', 'reservations', 'tickets', 'rentals', 'enrollments', 'services']) {
      counts[e] = (await store.loadEntity(slug, e)).length;
    }
    return ok(res, { counts, storage: 'json', uptime: Math.round(process.uptime()) });
  }

  // Entity routes
  const entity = store.cleanEntity(rest[0] || '');
  if (!rest.length) return fail(res, 'Not found', 404);
  if (!entity) return fail(res, 'Missing entity');

  // GET /node-api/:slug/:entity?email=&limit= (+ public catalog search)
  if (rest.length === 1 && req.method === 'GET') {
    const q = u.searchParams;
    const rows = await store.loadEntity(slug, entity);
    const admin = await auth.checkToken(slug, bearer(req));
    if (!admin && (biz.PUBLIC_ENTITIES || []).includes(entity)) {
      const params = {};
      for (const [k, val] of q.entries()) params[k] = val;
      return ok(res, biz.publicFilter(rows, params, 100));
    }
    if (!admin) {
      const identities = [q.get('email'), q.get('phone')].filter(Boolean);
      if (!identities.length) return ok(res, []);
      const own = rows.filter(r => v.matchesIdentity(r, identities));
      return ok(res, own.slice(0, 100));
    }
    const limit = Math.min(500, Math.max(1, Number(q.get('limit')) || 100));
    return ok(res, rows.slice(0, limit));
  }

  // POST /node-api/pay is reserved — fall through to the pay handler below.
  if (rest.length === 1 && entity === 'pay' && req.method === 'POST') {
    // handled below
  } else if (rest.length === 1 && req.method === 'POST') {
    if (!rateAllow('create:' + ipOf(req), 60, 60 * 1000)) return fail(res, 'Too many requests', 429);
    const body = await readBody(req);
    if (String(body.company || '').trim()) return fail(res, 'Spam detected', 403);
    const out = await createRecord(slug, entity, body.record || {}, body.idempotency_key || '');
    if (!out.ok) return fail(res, out.error);
    return ok(res, out.rec, out.deduped ? { deduped: true } : undefined);
  }

  // PATCH /node-api/:slug/:entity/:id  +  DELETE .../:id  (admin)
  if (rest.length === 2 && (req.method === 'PATCH' || req.method === 'DELETE')) {
    const admin = await needAdmin(req, res, slug);
    if (!admin) return;
    const id = String(rest[1]).slice(0, 80);
    if (req.method === 'DELETE') {
      const out = await store.transact(slug, entity, async (rows) => {
        const i = rows.findIndex(r => r && String(r.id) === id);
        if (i < 0) return { result: null, error: 'Not found' };
        rows.splice(i, 1);
        return { rows, result: { ok: true } };
      });
      if (out.error) return fail(res, out.error, 404);
      await store.mirrorAdmin(slug, entity, { id }, true);
      await store.purgeLegacy(slug, entity, id);
      await store.audit(slug, { event: 'record.delete', entity, id, user: admin.user });
      return ok(res, { ok: true });
    }
    const body = await readBody(req);
    const patch = v.sanitizeRecord(body.patch || body.record || {});
    delete patch.id;
    const out = await store.transact(slug, entity, async (rows) => {
      const r = rows.find(x => x && String(x.id) === id);
      if (!r) return { result: null, error: 'Not found' };
      if ((r.status || '') === 'cancelled' && (patch.status || 'cancelled') !== 'cancelled') {
        return { result: null, error: 'Cancelled records cannot be reactivated' };
      }
      Object.assign(r, patch, { updated_at: new Date().toISOString() });
      return { rows, result: r };
    });
    if (out.error) return fail(res, out.error, out.error === 'Not found' ? 404 : 400);
    await store.mirrorAdmin(slug, entity, out.result);
    await store.audit(slug, { event: 'record.update', entity, id, user: admin.user });
    return ok(res, out.result);
  }

  // POST /node-api/:slug/bookings/hold {trip_id,seats,...}
  if (rest.length === 2 && rest[1] === 'hold' && req.method === 'POST') {
    const body = await readBody(req);
    const trip = String(body.trip_id || '');
    const seats = v.normSeats(body.seats || []);
    if (!trip || !seats.length) return fail(res, 'trip_id + seats required');
    const out = await store.transact(slug, entity, async (rows) => {
      for (const r of rows) {
        if ((r.trip_id || '') !== trip || !v.activeStatus(r.status || '') || !v.holdLive(r)) continue;
        for (const s of seats) {
          if (v.normSeats(r.seats || []).includes(s)) return { result: null, error: 'Seat ' + s + ' unavailable' };
        }
      }
      const rec = { id: v.uid('h_'), trip_id: trip, seats, status: 'held', payment_status: 'unpaid',
        ref: 'HOLD-' + require('node:crypto').randomBytes(4).toString('hex').toUpperCase(),
        created_at: new Date().toISOString(), updated_at: new Date().toISOString(),
        expires_at: new Date(Date.now() + 15 * 60 * 1000).toISOString() };
      rows.push(rec);
      return { rows, result: rec };
    });
    if (out.error) return fail(res, out.error);
    await store.mirrorAdmin(slug, entity, out.result);
    return ok(res, out.result);
  }

  // POST /node-api/:slug/pay {ref,method,amount}
  if (rest.length === 1 && entity === 'pay' && req.method === 'POST') {
    const body = await readBody(req);
    const ref = String(body.ref || '');
    if (!ref) return fail(res, 'Missing ref');
    const out = await store.transact(slug, 'bookings', async (rows) => {
      const b = rows.find(r => (r.ref || '') === ref || String(r.id || '') === ref);
      if (!b) return { result: null, error: 'Booking not found' };
      if (b.status === 'cancelled' || b.status === 'expired') return { result: null, error: 'Booking ' + b.status + ' — payment rejected' };
      if (b.payment_status === 'paid') return { result: null, error: 'Already paid — duplicate rejected' };
      Object.assign(b, { payment_status: 'paid', status: 'confirmed', paid_at: new Date().toISOString(),
        pay_method: String(body.method || 'counter'), pay_amount: Math.round(Number(body.amount || 0) * 100) / 100,
        updated_at: new Date().toISOString() });
      return { rows, result: b };
    });
    if (out.error) return fail(res, out.error, out.error === 'Booking not found' ? 404 : 400);
    const b = out.result;
    await store.transact(slug, 'payments', async (rows) => {
      rows.push({ id: v.uid('p_'), booking_ref: ref, method: b.pay_method, amount: b.pay_amount, status: 'completed', created_at: new Date().toISOString() });
      return { rows, result: true };
    });
    await store.mirrorAdmin(slug, 'bookings', b);
    return ok(res, b);
  }

  // POST /node-api/:slug/:entity/:id/cancel {email?}
  if (rest.length === 3 && rest[2] === 'cancel' && req.method === 'POST') {
    const body = await readBody(req);
    const id = String(rest[1]).slice(0, 80);
    const admin = await auth.checkToken(slug, bearer(req));
    const out = await store.transact(slug, entity, async (rows) => {
      // Accept the internal id OR the customer-facing ref (tickets and
      // lookup flows only carry the ref — same rule as pay).
      const r = rows.find(x => x && (String(x.id) === id || String(x.ref || '').toUpperCase() === id.toUpperCase()));
      if (!r) return { result: null, error: 'Not found' };
      if (!admin) {
        const me = meOf(req, body);
        const owner = v.ownerOf(r);
        if (!me || (owner && owner !== me)) return { result: null, error: 'Not your record' };
      }
      if (r.status === 'cancelled' || r.status === 'expired') return { result: null, error: 'Already ' + r.status };
      Object.assign(r, { status: 'cancelled', cancelled_at: new Date().toISOString(), updated_at: new Date().toISOString() });
      if (r.payment_status === 'paid') r.payment_status = 'refund_pending';
      return { rows, result: r };
    });
    if (out.error) {
      const code = out.error === 'Not your record' ? 403 : (out.error === 'Not found' ? 404 : 400);
      return fail(res, out.error, code);
    }
    await store.mirrorAdmin(slug, entity, out.result);
    return ok(res, out.result, { message: 'Cancelled; capacity released' });
  }

  return fail(res, 'Not found', 404);
}

async function createRecord(slug, entity, record, idemKey) {
  let rec = v.sanitizeRecord(record || {});
  if (!rec || !Object.keys(rec).length) return { ok: false, error: 'Empty record' };
  if (rec.email && !v.EMAIL.test(rec.email)) return { ok: false, error: 'Invalid email' };
  const out = await store.transact(slug, entity, async (rows) => {
    if (idemKey) {
      const hit = rows.find(r => r && r.idempotency_key === idemKey);
      if (hit) return { result: { rec: hit, deduped: true } };
    }
    rec.id = rec.id || v.uid('r_');
    rec.created_at = new Date().toISOString();
    rec.updated_at = new Date().toISOString();
    if (idemKey) rec.idempotency_key = idemKey;
    if (entity === 'bookings') {
      const trip = String(rec.trip_id || '');
      const seats = v.normSeats(rec.seats || []);
      if (!seats.length) return { result: null, error: 'Select at least one seat' };
      if (!trip) return { result: null, error: 'Missing trip' };
      // Hold → confirm conversion (same rule as the PHP backend): the
      // customer's own live hold (proven by hold_ref, covering every seat)
      // converts instead of blocking its own confirmation.
      const holdRef = String(rec.hold_ref || '');
      let consumeId = '';
      for (const r of rows) {
        if ((r.trip_id || '') !== trip || !v.activeStatus(r.status || '') || !v.holdLive(r)) continue;
        if ((r.status || '') === 'held' && holdRef && (r.ref || '') === holdRef) {
          const held = v.normSeats(r.seats || []);
          if (seats.every(s => held.includes(s))) { consumeId = String(r.id || ''); continue; }
        }
        for (const s of seats) {
          if (v.normSeats(r.seats || []).includes(s)) return { result: null, error: 'Seat ' + s + ' already reserved' };
        }
      }
      if (consumeId) rows = rows.filter(r => String(r && r.id) !== consumeId);
      rec.seats = seats;
      rec.ref = rec.ref || ('BK-' + require('node:crypto').randomBytes(4).toString('hex').toUpperCase());
      rec.status = rec.status || 'awaiting_payment';
      rec.payment_status = rec.payment_status || 'unpaid';
    }
    if (entity === 'orders') {
      let items = rec.items || [];
      if (typeof items === 'string') { try { items = JSON.parse(items); } catch (_) { items = []; } }
      if (!Array.isArray(items) || !items.length) return { result: null, error: 'Order has no items' };
      rec.items = items;
      rec.total = Math.round(Number(rec.total || 0) * 100) / 100;
      rec.ref = rec.ref || ('ORD-' + require('node:crypto').randomBytes(4).toString('hex').toUpperCase());
      rec.status = rec.status || 'pending';
      rec.payment_status = rec.payment_status || 'unpaid';
    }
    if (entity === 'appointments' && rec.date && rec.time) {
      const keys = rec.doctor ? ['doctor', 'date', 'time'] : (rec.service ? ['service', 'date', 'time'] : ['date', 'time']);
      const same = (a, b) => keys.every(k => String(a[k] || '').toLowerCase() === String(b[k] || '').toLowerCase());
      for (const r of rows) {
        if (!r || ['cancelled', 'expired', 'returned', 'checked_out'].includes(r.status)) continue;
        if (same(r, rec)) return { result: null, error: 'Slot already booked' };
      }
    }
    rows.push(rec);
    return { rows, result: { rec, deduped: false } };
  });
  if (out.error) return { ok: false, error: out.error };
  await store.mirrorAdmin(slug, entity, out.result.rec);
  return { ok: true, rec: out.result.rec, deduped: !!out.result.deduped };
}

async function backupSite(slug, reason) {
  const dir = store.siteDir(slug);
  const files = {};
  let entries = [];
  try { entries = await fsp.readdir(dir); } catch (_) {}
  for (const name of entries) {
    if (name === 'backups' || !name.endsWith('.json')) continue;
    try { files[name] = JSON.parse(await fsp.readFile(path.join(dir, name), 'utf8')); } catch (_) {}
  }
  const stamp = new Date().toISOString().replace(/[:.]/g, '-') + '-' + reason;
  const bdir = path.join(dir, 'backups');
  await fsp.mkdir(bdir, { recursive: true });
  await fsp.writeFile(path.join(bdir, stamp + '.json'), JSON.stringify({ at: new Date().toISOString(), reason, files }, null, 2));
  const all = (await fsp.readdir(bdir)).filter(f => f.endsWith('.json')).sort();
  for (const old of all.slice(0, Math.max(0, all.length - 10))) {
    try { await fsp.unlink(path.join(bdir, old)); } catch (_) {}
  }
  return stamp + '.json';
}

const server = http.createServer((req, res) => {
  handle(req, res).catch((e) => {
    try { fail(res, 'Server error', 500); } catch (_) {}
    console.error('[node-api]', e && e.message);
  });
});
server.listen(PORT, () => {
  console.log('[node-api] listening on :' + PORT);
  if (PID_FILE !== '') {
    try {
      require('node:fs').writeFileSync(PID_FILE, JSON.stringify({ pid: process.pid, token: INSTANCE_TOKEN, started_at: new Date().toISOString() }));
    } catch (e) { console.error('[node-api] pid file unwritable: ' + (e && e.message)); }
  }
});
module.exports = { server };
