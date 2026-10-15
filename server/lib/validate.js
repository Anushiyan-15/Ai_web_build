'use strict';
/* server/lib/validate.js — server-side validation + sanitization.
 * Mirrors the PHP rules (same accepted shapes) so both stacks converge.
 */
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function stripTags(s) {
  return String(s).replace(/<[^>]*>/g, '');
}
function sanitizeRecord(rec) {
  const out = {};
  for (const [k, v] of Object.entries(rec || {})) {
    out[k] = typeof v === 'string' ? stripTags(v).trim() : v;
  }
  return out;
}
function ownerOf(r) {
  return String((r && (r.email || r.phone || r.customer)) || '').toLowerCase();
}
function phoneMatch(a, b) {
  const da = String(a).replace(/[^0-9]/g, '');
  const db = String(b).replace(/[^0-9]/g, '');
  if (da.length < 7 || db.length < 7) return false;
  if (da.length >= 9 && db.length >= 9) return da.slice(-9) === db.slice(-9);
  return da === db || da.endsWith(db) || db.endsWith(da);
}
function matchesIdentity(r, identities) {
  const owners = [r && r.email, r && r.phone, r && r.customer]
    .map(v => String(v || '').trim().toLowerCase()).filter(Boolean);
  const contacts = (Array.isArray(identities) ? identities : [identities])
    .map(v => String(v || '').trim().toLowerCase()).filter(Boolean);
  return owners.some(owner => contacts.some(contact => owner === contact
    || (!owner.includes('@') && !contact.includes('@') && phoneMatch(owner, contact))));
}
function normSeats(seats) {
  if (typeof seats === 'string') {
    const t = seats.trim();
    // Mirrors the PHP rule: JSON first, then comma/space lists split —
    // "1A, 2B" must never become one seat.
    if (t[0] === '[' || t[0] === '{') {
      try { const j = JSON.parse(t); if (Array.isArray(j)) seats = j; } catch (_) {}
    }
    if (typeof seats === 'string') seats = t.split(/[\s,;|]+/).filter(Boolean);
  }
  return [...new Set((Array.isArray(seats) ? seats : [seats]).map(String).filter(Boolean))];
}
function activeStatus(st) {
  return st === 'held' || st === 'awaiting_payment' || st === 'confirmed';
}
function holdLive(r) {
  if ((r && r.status) !== 'held') return true;
  const ca = Date.parse((r && r.created_at) || '');
  return !(ca && Date.now() - ca > 15 * 60 * 1000);
}
function uid(prefix) {
  const c = require('node:crypto');
  return (prefix || 'r_') + c.randomBytes(8).toString('hex');
}

module.exports = { EMAIL, stripTags, sanitizeRecord, ownerOf, phoneMatch, matchesIdentity, normSeats, activeStatus, holdLive, uid };
