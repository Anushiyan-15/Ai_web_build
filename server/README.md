# WebCraft Node.js Business API (`server/`)

Real backend for generated business websites. **Zero npm dependencies** (pure
Node ≥ 18) so it runs anywhere Node exists — no install step can fail.

## What it is

- JSON files are the database: `storage/sites/<slug>/*.json`
  (`business.json`, `features.json`, `users.json`, `settings.json`,
  `audit.json`, `<entity>.json`, `backups/`).
- Legacy read-union: rows previously written by the PHP backend at
  `storage/collections/<slug>/*.json` are visible to the API (dedup by id,
  `sites/` wins). Writes always go to `sites/` — one direction, no forks.
- Operational records are mirrored into the published admin's data files
  (`published/<slug>/admin/data_<entity>.json`, convergent upsert-by-id),
  so the PHP admin dashboard manages the very same rows.
- The PHP backend (`api/business-ops.php`) keeps working unchanged; the
  storefront runtime tries Node first and falls back to PHP on network
  failure (HTTP 4xx validation errors never fall back).

## Run

```bat
cd server
start-node.bat
```

or:

```sh
node server/server.js
```

Environment:

| var | default | purpose |
|---|---|---|
| `PORT` / `WB_PORT` | `3100` | listen port |
| `WB_STORAGE_ROOT` | `<project>/storage` | JSON database root |
| `WB_PUBLISHED_ROOT` | `<project>/published` | published sites (admin mirror + `.auth.json`) |
| `WB_PHP_BIN` | auto (`UniServerZ php83`, then `php`) | bcrypt verify bridge for admin login |
| `WB_CORS` | `*` | CORS origin (same posture as the PHP API) |
| `WB_INSTANCE_TOKEN` | — | panel start/stop identity: echoed on `/health`, stop refuses mismatches |
| `WB_PID_FILE` | — | panel runs: server writes `{pid,token,started_at}` here on boot |

Health: `GET http://localhost:3100/node-api/health`

## Auth

- Admin login verifies the **bcrypt** hash in the site's
  `published/<slug>/admin/.auth.json` (created at publish time) via a local
  PHP bridge — Node never stores passwords. Success mints a 12h Bearer
  token (sha256-stored). Login is rate-limited (5/5min/IP).
- Customers need no token: creates are validated + honeypot-checked;
  reads/cancels match `email`/`phone` ownership; `pay` is ref-capability.

## Endpoints (all JSON, `{success,data?|error?}`)

`POST /node-api/auth/login{slug,username,password}` → `{token}`
`POST /node-api/auth/logout`
`GET /node-api/:slug/:entity?email=&limit=` · `POST` create ·
`PATCH`/`DELETE /node-api/:slug/:entity/:id` (admin)
`POST /node-api/:slug/bookings/hold` · `POST /node-api/:slug/pay` ·
`POST /node-api/:slug/:entity/:id/cancel`
`GET /node-api/:slug/stats` (admin) · `GET/POST /node-api/:slug/settings` (admin)
`POST /node-api/:slug/analyze{html,category?,business?}` (findings + safe plans)
`GET /node-api/:slug/features` · `POST /node-api/:slug/features/apply{module}` (admin)
`POST /node-api/:slug/backup` · `POST /node-api/:slug/restore{name}` (admin)

## Safety model

- Per-file serialized write queue + tmp-file atomic rename; malformed JSON
  is quarantined (`*.corrupt-<ts>`), never fatal; row caps (2000/500).
- Idempotency keys dedupe retries; seat conflicts + duplicate pays rejected;
  `cancelled` is terminal; paid cancels become `refund_pending`.
- Audit log (`audit.json`, capped 500) for logins, mutations, features,
  backups. Backups keep last 10 snapshots.
- **Limitation (honest):** PHP uses `flock`, Node a serialized queue —
  within one process writes are strictly ordered; true cross-process
  (PHP+Node) parallelism on the same file is atomic per write but
  read-modify-write races are possible. Single-writer-per-flow in practice;
  the test suite proves single-process concurrency (parallel holds → 1 win).

## Tests

```sh
node tests/test-node-api.js
```

Spawns the server on an ephemeral port with isolated temp roots, runs the
full matrix (startup, auth, CRUD, persistence+restart, customer↔admin,
concurrency, security, backup/restore, categories), then shuts it down.
