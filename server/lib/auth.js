'use strict';
/* server/lib/auth.js — admin authentication for the Node API.
 * Passwords are verified with PHP's password_verify via a local bridge
 * (hashes stay bcrypt; Node never stores or invents passwords).
 * Success mints a random Bearer token (sha256-stored, 12h expiry).
 * Login is rate-limited (5 attempts / 5 min / IP).
 */
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const { execFile } = require('node:child_process');
const { siteDir, readJson, writeJson } = require('./store');

function phpBin() {
  const cands = [];
  if (process.env.WB_PHP_BIN) cands.push(process.env.WB_PHP_BIN);
  cands.push('C:\\UniServerZ\\core\\php83\\php.exe', 'php');
  for (const c of cands) {
    try {
      if (c === 'php' || fs.existsSync(c)) return c;
    } catch (_) {}
  }
  return 'php';
}

// timingSafe compare of sha256(token) against stored hashes happens on
// strings of equal length; bridge verifies the bcrypt password itself.
function phpVerify(password, hash) {
  return new Promise((resolve) => {
    const code = '$h=getenv("WC_HASH");$p=getenv("WC_PASS");exit(password_verify($p,$h)?0:1);';
    const child = execFile(phpBin(), ['-r', code], {
      env: { ...process.env, WC_HASH: hash, WC_PASS: password },
      timeout: 10000,
    }, (err) => resolve(!err));
    child.on('error', () => resolve(false));
  });
}

async function readAuthFile(slug) {
  // canonical location: published/<slug>/admin/.auth.json (env-overridable roots)
  const published = process.env.WB_PUBLISHED_ROOT
    || path.join(__dirname, '..', '..', 'published');
  const f = path.join(published, slug, 'admin', '.auth.json');
  try {
    const j = JSON.parse(await fs.promises.readFile(f, 'utf8'));
    return (j && typeof j === 'object') ? j : null;
  } catch (_) { return null; }
}

const attempts = new Map(); // ip -> [timestamps]
function rateOk(ip) {
  const now = Date.now();
  const list = (attempts.get(ip) || []).filter(t => now - t < 5 * 60 * 1000);
  attempts.set(ip, list);
  return list.length < 5;
}
function rateHit(ip) {
  const list = attempts.get(ip) || [];
  list.push(Date.now());
  attempts.set(ip, list.slice(-20));
}

function newToken() { return crypto.randomBytes(32).toString('hex'); }
function hashToken(t) { return crypto.createHash('sha256').update(t).digest('hex'); }

async function loadTokens(slug) {
  const t = await readJson(path.join(siteDir(slug), 'tokens.json'), []);
  return Array.isArray(t) ? t : [];
}
async function saveTokens(slug, arr) {
  await writeJson(path.join(siteDir(slug), 'tokens.json'), arr.slice(0, 50));
}
async function mintToken(slug, username) {
  const t = newToken();
  const arr = await loadTokens(slug);
  arr.unshift({ h: hashToken(t), user: username, exp: Date.now() + 12 * 3600 * 1000 });
  await saveTokens(slug, arr.filter(x => x.exp > Date.now()));
  return t;
}
async function checkToken(slug, bearer) {
  if (!bearer) return null;
  const h = hashToken(String(bearer));
  const arr = await loadTokens(slug);
  const now = Date.now();
  let ok = null;
  for (const x of arr) {
    if (x.exp > now && x.h.length === h.length) {
      try {
        if (crypto.timingSafeEqual(Buffer.from(x.h), Buffer.from(h))) ok = x;
      } catch (_) {}
    }
  }
  return ok;
}
async function revokeToken(slug, bearer) {
  if (!bearer) return;
  const h = hashToken(String(bearer));
  await saveTokens(slug, (await loadTokens(slug)).filter(x => x.h !== h));
}

module.exports = {
  phpBin, phpVerify, readAuthFile,
  rateOk, rateHit, mintToken, checkToken, revokeToken,
};
