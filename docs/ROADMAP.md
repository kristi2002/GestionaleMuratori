# Roadmap — from v1 to production platform on Hetzner

> **Status: phases A–E delivered on 2026-07-02** (174 automated assertions
> green; Docker stack build-verified). See [CHANGELOG.md](../CHANGELOG.md) for
> what shipped. Only the "Out of scope" section at the bottom remains open.

Phases are ordered by risk: security first, then the features that make the
platform operable by the client, then infrastructure, then the proof (tests).
Each phase ends runnable and verified. Gap IDs reference
[GAP_ANALYSIS.md](GAP_ANALYSIS.md).

## Phase A — Repo hygiene & security hardening (S1–S7, S9, T2)

1. **A1 Repo cleanup (T2)** — `git rm` the ~40 accidental zero-byte root files.
2. **A2 CSRF protection (S1, S9)**
   - `Support\Csrf`: per-session token (`random_bytes`), constant-time check.
   - Token exposed as `<meta name="csrf-token">` in the layout; `app.js` sends
     `X-CSRF-Token` on every AJAX request (covers FormData uploads too).
   - Central enforcement in the front controller for **every POST** (login included —
     token is on the login page); 419-style 403 JSON/HTML on mismatch.
   - Navbar logout becomes a POST button; `GET /logout` removed.
3. **A3 Login rate limiting (S2, S10)** — migration `002_login_attempts.sql`;
   record failures per email+IP; block after 5 failures in 15 min (Italian message);
   clear on success. Doubles as an auth audit trail.
4. **A4 Session hardening (S3)** — `use_strict_mode`, `Secure` cookie flag driven by
   `SESSION_SECURE` env (default: auto from `APP_URL` scheme), idle timeout
   (default 8h, env-tunable) with regeneration.
5. **A5 Debug default off (S4)** — default `false` in `config.php` and front
   controller; `.env.example` documents `APP_DEBUG=true` for local dev only.
6. **A6 Security headers (S5)** — sent centrally: `X-Content-Type-Options: nosniff`,
   `X-Frame-Options: DENY`, `Referrer-Policy: same-origin`, conservative CSP
   (self + inline styles needed by Bootstrap components).
7. **A7 Self-hosted assets (S6)** — vendor Bootstrap 5.3 + jQuery 3.7 into
   `public/assets/vendor/`; drop CDN links (offline-friendly worker app, CSP `self`).
8. **A8 Password change (S7)** — `/password` page for any authenticated role;
   requires current password; min length 8.

**Acceptance**: POST without token → 403; 6th bad login blocked; cookie flags
visible; no external requests on any page; password change works for all roles.

## Phase B — Platform completion (F1–F5)

1. **B1 User management (F1)** — `/admin/users`: list (search, role filter),
   create (role, client link for `client` role, generated or set password),
   edit, activate/deactivate (self-deactivation blocked), password reset.
   Migration not needed (schema complete).
2. **B2 Admin intervention detail (F2)** — `GET /admin/interventions/{id}`:
   description, schedule, materials (planned vs used), full status history with
   actor+timestamp, photos by type, signature, completion notes. New admin photo
   + signature streaming routes.
3. **B3 Operations dashboard (F3, F5)** — real KPIs on `/admin`: active projects,
   today's interventions by status, open interventions, **low-stock list**
   (`qty_in_stock ≤ reorder_level`, active items), links into filtered lists.
   Low-stock badge also on the warehouse list.
4. **B4 Worker task tabs (F4)** — `/worker` tabs: *Oggi* (default), *Prossimi*
   (future or unscheduled open tasks), *Completati* (last 14 days).

**Acceptance**: admin can onboard a new worker + client login end-to-end without
touching the DB; admin sees photos/history; dashboard numbers match the DB;
worker can reach non-today tasks.

## Phase C — Deployment on Hetzner (D1–D5, D7)

1. **C1 Docker stack** — `deploy/`: `Dockerfile` (php:8.2-fpm + gd/pdo_mysql/zip
   + composer install), `docker-compose.yml` (nginx, app, mysql:8, volumes for DB
   data + `storage/uploads`), nginx vhost (front-controller rewrite, static assets,
   client_max_body_size, security headers, HTTPS-ready), PHP ini overrides
   (upload sizes per D7, opcache, error log to stderr).
2. **C2 Production env template** — `.env.production.example` (debug off, strong DB
   password, `SESSION_SECURE=true`).
3. **C3 Backups (D2)** — `scripts/backup.sh` (mysqldump + uploads tar, 14-day
   rotation) + documented cron and **restore procedure**.
4. **C4 DEPLOYMENT.md** — step-by-step Hetzner guide: server creation, SSH
   hardening, ufw, Docker install, DNS, Let's Encrypt (certbot or Caddy option),
   first deploy (`migrate` + admin user creation), update procedure, monitoring
   via `/health`.

**Acceptance**: `docker compose up` on a clean machine serves the app on :80
with migrations applied; backup script produces a restorable archive.

## Phase D — Test suite & full simulation (T1)

1. **D1 Test harness** — dependency-free runner `tests/run.php` against a dedicated
   `gestionale_muratori_test` DB (fresh migrate + seed per run).
2. **D2 Unit/integration tests** — ledger math (reserve/complete/cancel cycles,
   reconcile, overflow guard, negative-stock block), state machine (all legal +
   illegal transitions), completion gate, validation helpers, CSRF token logic,
   rate limiter.
3. **D3 HTTP end-to-end simulation** — boots `php -S`, then as real HTTP clients:
   login flows for all roles, CSRF rejection, RBAC matrix (worker A vs worker B's
   intervention, client 1 vs client 2's project, worker → admin URLs),
   full lifecycle create→start→photo upload→complete with stock verification,
   user management flow, report downloads (PDF/XLSX magic bytes), rate limiting.
4. **D4 Fix–retest loop** — any failure is fixed and the whole suite re-run until green.

**Acceptance**: one command runs everything green on a fresh database.

## Phase E — Final documentation pass

Update README, ARCHITECTURE, DATA_MODEL, API for everything added; write
TESTING.md and DEPLOYMENT.md finals; full CHANGELOG for review.

## Out of scope (needs client decision)

PWA/service-worker offline mode, S3 storage, labor hours & GPS check-in,
multi-tenancy, invoicing/cost module, e-mail notifications (needs SMTP account).
