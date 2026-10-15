'use strict';
/* server/lib/store.js — JSON files as the database.
 * Layout per site: <ROOT>/sites/<slug>/{business,features,users,settings,audit,tokens}.json
 *   + <entity>.json + backups/<ts>.json
 * Legacy read-union: storage/collections/<slug>/<entity>.json (PHP writer) is
 * read when sites/ has no copy, so no record is ever hidden. Writes always go
 * to sites/ (+ admin mirror), never to the legacy path.
 * Safety: per-file serialized write queue, tmp-file + atomic rename, malformed
 * JSON recovery (renamed to .corrupt-<ts>), row caps, path-traversal guards.
 */
const fs = require('node:fs');
const fsp = require('node:fs/promises');
const path = require('node:path');
const crypto = require('node:crypto');

function roots() {
  const storage = process.env.WB_STORAGE_ROOT
    || path.join(__dirname, '..', '..', 'storage');
  const published = process.env.WB_PUBLISHED_ROOT
    || path.join(__dirname, '..', '..', 'published');
  return { storage, published };
}
function siteDir(slug) { return path.join(roots().storage, 'sites', slug); }
function legacyDir(slug) { return path.join(roots().storage, 'collections', slug); }
function adminDir(slug) { return path.join(roots().published, slug, 'admin'); }

function cleanSlug(s) {
  const v = String(s || 'site').replace(/[^a-z0-9_-]/gi, '').slice(0, 60);
  return v || 'site';
}
function cleanEntity(s) {
  return String(s || '').replace(/[^a-z0-9_]/gi, '').slice(0, 40);
}
function safeJoin(base, ...parts) {
  const p = path.resolve(base, ...parts);
  if (p !== base && !p.startsWith(base + path.sep)) throw new Error('path escape blocked');
  return p;
}

async function readJson(file, fallback) {
  try {
    const raw = await fsp.readFile(file, 'utf8');
    if (!raw.trim()) return fallback;
    return JSON.parse(raw);
  } catch (e) {
    if (e && e.code === 'ENOENT') return fallback;
    // Malformed: quarantine, never crash, never lose silently.
    try {
      await fsp.rename(file, file + '.corrupt-' + Date.now());
    } catch (_) {}
    return fallback;
  }
}

// Per-file serialized writers (single-process concurrency safety).
const queues = new Map();
function queued(file, fn) {
  const prev = queues.get(file) || Promise.resolve();
  const next = prev.then(fn, fn);
  queues.set(file, next.catch(() => {}));
  return next;
}
async function writeJsonAtomic(file, value) {
  await fsp.mkdir(path.dirname(file), { recursive: true });
  const tmp = file + '.tmp-' + process.pid + '-' + crypto.randomBytes(4).toString('hex');
  await fsp.writeFile(tmp, JSON.stringify(value, null, 2), 'utf8');
  await fsp.rename(tmp, file);
}
function writeJson(file, value) {
  return queued(file, () => writeJsonAtomic(file, value));
}

function newer(a, b) {
  const ta = Date.parse((a && (a.updated_at || a.created_at)) || '') || 0;
  const tb = Date.parse((b && (b.updated_at || b.created_at)) || '') || 0;
  return tb > ta ? b : a;
}
async function loadEntity(slug, entity) {
  // Convergent union of both stores (sites/ ← Node writer, collections/ ←
  // PHP writer), deduped by id with newer updated_at winning. Either
  // backend's rows stay visible to both backends' reads.
  const f = safeJoin(siteDir(slug), entity + '.json');
  let rows = await readJson(f, null);
  if (rows === null) rows = [];
  if (!Array.isArray(rows)) rows = [];
  const legacy = await readJson(safeJoin(legacyDir(slug), entity + '.json'), []);
  const byId = new Map();
  for (const r of rows) { if (r && typeof r === 'object' && r.id != null) byId.set(String(r.id), r); }
  for (const r of (Array.isArray(legacy) ? legacy : [])) {
    if (!r || typeof r !== 'object' || r.id == null) continue;
    const id = String(r.id);
    if (!byId.has(id)) byId.set(id, r);
    else byId.set(id, newer(byId.get(id), r));
  }
  if (entity === 'trips' && byId.size === 0) {
    const publishedTrips = await readJson(safeJoin(adminDir(slug), 'data_trips.json'), []);
    for (const r of (Array.isArray(publishedTrips) ? publishedTrips : [])) {
      if (r && typeof r === 'object' && r.id != null) byId.set(String(r.id), r);
    }
  }
  return [...byId.values()];
}
// Best-effort purge of a legacy collections copy (prevents ghost
// resurrection after deletes; reads already dedupe by id).
async function purgeLegacy(slug, entity, id) {
  try {
    id = String(id || '');
    if (!id) return false;
    const file = safeJoin(legacyDir(slug), entity + '.json');
    const rows = await readJson(file, null);
    if (!Array.isArray(rows)) return false;
    const kept = rows.filter(r => String((r && r.id) || '') !== id);
    if (kept.length === rows.length) return false;
    await writeJsonAtomic(file, kept);
    return true;
  } catch (_) { return false; }
}
async function saveEntity(slug, entity, rows) {
  rows = Array.isArray(rows) ? rows.slice(0, 2000) : [];
  await writeJson(safeJoin(siteDir(slug), entity + '.json'), rows);
  return rows;
}

// Atomic read-modify-write inside the per-file queue.
// fn(rows) → { rows, result } to commit, or { result, error } to abort.
// Reads are the convergent union (sites/ + legacy); commits land in sites/.
async function transact(slug, entity, fn) {
  const file = safeJoin(siteDir(slug), entity + '.json');
  return queued(file, async () => {
    const rows = await loadEntity(slug, entity);
    const out = await fn(rows);
    if (!out || out.error) return out || { error: 'aborted' };
    await writeJsonAtomic(file, (out.rows || rows).slice(0, 2000));
    return out;
  });
}
const MIRROR = ['bookings', 'orders', 'appointments', 'inquiries', 'subscribers', 'payments',
  'products', 'customers', 'coupons', 'menu_items', 'reservations', 'rooms', 'services', 'staff',
  'doctors', 'patients', 'properties', 'viewings', 'courses', 'enrollments', 'tutors',
  'packages', 'departures', 'events', 'tickets', 'units', 'rentals', 'vehicles', 'service_jobs',
  'plans', 'projects', 'buses', 'routes'];
// Mirror into the published admin's data files (same convergent upsert-by-id
// rule as the PHP mirror): the admin dashboard manages these very rows.
async function mirrorAdmin(slug, entity, rec, del = false) {
  if (!MIRROR.includes(entity)) return false;
  const dir = adminDir(slug);
  try {
    const st = await fsp.stat(dir);
    if (!st.isDirectory()) return false;
  } catch (_) { return false; }
  const file = path.join(dir, entity === 'inquiries' ? 'data_inquiries.json' : 'data_' + entity + '.json');
  const id = String((rec && rec.id) || '');
  await queued(file, async () => {
    let rows = await readJson(file, []);
    if (!Array.isArray(rows)) rows = [];
    if (del && id) {
      rows = rows.filter(r => String((r && r.id) || '') !== id);
    } else if (id) {
      const row = { ...rec, updated_at: new Date().toISOString() };
      if (entity === 'inquiries' && !('read' in row)) row.read = false;
      const i = rows.findIndex(r => r && String(r.id) === id);
      if (i >= 0) rows[i] = { ...rows[i], ...row };
      else rows.unshift(row);
      rows = rows.slice(0, 2000);
    }
    await writeJsonAtomic(file, rows);
  });
  return true;
}

async function audit(slug, event) {
  const file = safeJoin(siteDir(slug), 'audit.json');
  await queued(file, async () => {
    let rows = await readJson(file, []);
    if (!Array.isArray(rows)) rows = [];
    rows.unshift({ at: new Date().toISOString(), ...event });
    await writeJsonAtomic(file, rows.slice(0, 500));
  });
}

module.exports = {
  roots, siteDir, legacyDir, adminDir,
  cleanSlug, cleanEntity, safeJoin,
  readJson, writeJson, loadEntity, saveEntity, transact, mirrorAdmin, audit, MIRROR,
  purgeLegacy,
};
