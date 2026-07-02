# Changelog

## 2026-07-02 — Production hardening & platform completion (v1.1)

Everything below was implemented, tested (174 automated assertions, all green)
and documented in this session. Gap IDs reference
[docs/GAP_ANALYSIS.md](docs/GAP_ANALYSIS.md); the plan is
[docs/ROADMAP.md](docs/ROADMAP.md).

### Phase A — Security hardening

- **CSRF protection (S1, S9)** — new `Support\Csrf` (session token, constant-time
  check). Enforced centrally in `public/index.php` for **every POST** (header
  `X-CSRF-Token`, set globally by `app.js`, or `_token` field). Token exposed via
  `<meta name="csrf-token">` in the layout. Logout is now **POST-only** (navbar
  button); the `GET /logout` route was removed.
- **Login rate limiting + auth audit (S2, S10)** — new `login_attempts` table
  (migration `002_login_attempts.sql`) and `Services\LoginRateLimiter`:
  5 failures / 15 min per email (any IP) or 20 per IP → HTTP 429 with an Italian
  message; success clears the counter. Every attempt is recorded (audit trail).
  Window/thresholds configurable via `LOGIN_*` env vars.
- **Session hardening (S3)** — `use_strict_mode`, `Secure` cookie flag
  (`SESSION_SECURE` env, auto-inferred from `APP_URL` scheme), idle timeout for
  authenticated sessions (default 8 h, `SESSION_IDLE_TIMEOUT`), activity tracking.
- **Debug off by default (S4)** — `APP_DEBUG` now defaults to `false` in both
  `config.php` and the front controller; enable explicitly for development.
- **Security headers (S5)** — sent on every response: `X-Content-Type-Options:
  nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: same-origin`, and a CSP
  (`default-src 'self'`; inline styles allowed for Bootstrap). HSTS is added by
  Caddy in production.
- **Self-hosted assets (S6)** — Bootstrap 5.3.3 + jQuery 3.7.1 vendored under
  `public/assets/vendor/`; all CDN references removed (works offline on site,
  satisfies CSP `'self'`, no third-party requests).
- **Password change (S7)** — `/password` page for every role (current password
  required, min 8 chars); "Password" link in the navbar.
- **Env override order** — real environment variables now take precedence over
  `.env` (needed for Docker and tests; `Support\Env`).
- **Timezone pinned** — `date_default_timezone_set(APP_TIMEZONE)` (default
  `Europe/Rome`) in bootstrap, so "today" scheduling logic is correct regardless
  of server timezone.
- **Repo hygiene (T2)** — removed 31 accidental zero-byte files (shell-fragment
  names) that had been committed to the repository root.

### Phase B — Platform completion

- **User management (F1)** — new `/admin/users` area (`Admin\UserController`,
  `views/admin/users/index.php`): list with search + role filter, create
  (role-aware: client logins require a linked company), edit, activate/
  deactivate, password reset via the edit modal. Server-side self-lockout
  guards: an admin cannot deactivate or demote **their own** account.
  `UserModel` extended (list excludes `password_hash` from page-embedded JSON).
- **Admin intervention detail (F2)** — new `GET /admin/interventions/{id}`
  page: full metadata, planned vs used materials, before/during/after photo
  galleries, client signature, completion notes, and the complete **status
  history** (who, when, from → to). New admin streaming routes
  `/admin/photos/{id}(/thumb)` and `/admin/interventions/{id}/signature`.
  List titles now link to the detail page.
- **Operations dashboard (F3, F5)** — `/admin` now shows live KPIs: active
  projects, open interventions, today's interventions by status, and a
  **low-stock alert table** (`qty_in_stock ≤ reorder_level`, active items with a
  threshold), all linking into the filtered lists.
- **Worker task tabs (F4)** — `/worker` now has *Oggi / Prossimi / Completati*
  pills (today's schedule / open future or unscheduled tasks / completions of
  the last 14 days) via `InterventionModel::forWorkerTab()`.
- **Shared photo streaming** — `Services\PhotoStreamService` unifies the
  file-streaming logic used by admin, worker and client controllers
  (authorization stays in each controller).

### Phase C — Deployment (Hetzner)

- **Docker stack** — root `docker-compose.yml`: Caddy (automatic Let's Encrypt
  TLS + HSTS) → PHP-FPM 8.2 app image (`deploy/Dockerfile`: gd, pdo_mysql, zip,
  opcache; Composer vendor stage) → MySQL 8; named volumes for DB data, uploads
  and TLS material. `deploy/Caddyfile`, `deploy/php.ini` (upload limits per D7,
  stderr logging, Europe/Rome), `deploy/env.production.example`, `.dockerignore`.
- **Admin bootstrap script** — `scripts/create-admin.php` creates the first
  admin (or resets an admin password) so production doesn't need the demo seed.
- **Backups (D2)** — `scripts/backup.sh`: nightly `mysqldump` + uploads tarball,
  14-day rotation, documented cron line and **tested restore procedure**.
- **Deployment guide (D1, D4, D5)** — [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md):
  server creation, SSH hardening, ufw, Docker install, DNS/TLS, first deploy,
  updates, monitoring via `/health`, security checklist. The stack was built and
  booted locally via `docker compose` as validation.

### Phase D — Test suite (T1)

- Dependency-free runner `tests/run.php` against a disposable MySQL 8 container
  (`tests/start-test-db.ps1`, port 3307) — the dev database is never touched.
- 174 assertions: unit (validation, CSRF), service-level §4 invariants
  (ledger math, state machine, completion gate, cancellation, reconciliation),
  rate limiter, and a full HTTP end-to-end simulation of all three roles —
  including a true **concurrent-completion race test** (`curl_multi`) proving
  no lost updates on the same warehouse item, and PDF/XLSX content checks.
  Details: [docs/TESTING.md](docs/TESTING.md).

### Documentation

- Rewrote [README.md](README.md) (English, links to all docs) and
  [CLAUDE.md](CLAUDE.md) (project-specific engineering rules).
- New: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md),
  [docs/DATA_MODEL.md](docs/DATA_MODEL.md) (ledger semantics, state machine),
  [docs/API.md](docs/API.md) (every route), [docs/GAP_ANALYSIS.md](docs/GAP_ANALYSIS.md),
  [docs/ROADMAP.md](docs/ROADMAP.md), [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md),
  [docs/TESTING.md](docs/TESTING.md), this changelog.

### Compatibility notes

- New migration `002_login_attempts.sql` — run `php database/migrate.php`.
- `GET /logout` no longer exists (POST with CSRF token instead).
- All POST endpoints now require the CSRF token; any custom client must send
  `X-CSRF-Token` (value from the page's `<meta name="csrf-token">`).
- `APP_DEBUG` defaults to **false**; set `APP_DEBUG=true` in local `.env`.
- Real environment variables now override `.env` values.

### Tooling fix — repo-corrupting hook removed

The claude-flow hooks in `.claude/settings.json` (all `cmd /c node …`) were
being executed in a way that fed the hook-input JSON to `cmd.exe` as a command
line on this Windows setup: every `Write`/`Edit` whose content contained `->` or
`=>` created a zero-byte junk file named after the following token (cmd `>`
redirection). This is exactly how the 31 junk files in the first commit were
born (e.g. `$id`, `'Cantieri`, `prepare(,`). The hooks block was removed
(statusline, permissions and the `.env` read-deny stay); all junk files —
the 31 committed ones and the ones regenerated during this session — were
deleted. If junk files ever reappear, check that no `cmd /c` hooks were
re-added by a claude-flow re-init.

### Known limitations / deferred (unchanged from v1 scope)

- Full PWA/service-worker offline queue (localStorage retry queue remains).
- S3/object storage (StorageInterface ready), labor hours, GPS check-in,
  e-mail notifications, pagination on very large lists (F6/F7/F8 in the gap
  analysis) — need client decisions.
