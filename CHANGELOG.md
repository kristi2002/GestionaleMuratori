# Changelog

## 2026-07-06 — "Cantiere" UI redesign (frontend only)

A ground-up visual redesign layered on the existing Bootstrap 5.3 stack — **no
build step, no new runtime framework, CSP `'self'` preserved, assets self-hosted**.
Grounded in field-service/construction UI research (Procore/Fieldwire patterns,
outdoor-readability guidance): concrete-grey neutrals, a single hi-vis **safety-amber**
accent, blueprint-steel for links, and disciplined semantic status colours.

- **Design-token layer in `app.css`** — the "Cantiere" palette as CSS custom
  properties mapped onto Bootstrap's `--bs-*` variables; **light + dark themes**
  via `[data-bs-theme]`, persisted in a `gm_theme` cookie and rendered server-side
  (no flash; no CSP-violating inline script).
- **Self-hosted Inter** (`@font-face`, woff2 400–800) + a curated inline **SVG icon
  sprite** — both CSP-clean and offline-friendly; no CDN.
- **New app shell** in `layout.php` — anthracite topbar with brand chip + theme
  toggle, and a persistent **admin sidebar** (grouped, role-aware nav, active state).
  Workers/clients/subcontractors keep the minimal top-bar experience.
- **Restyled components** — KPI tiles (mono number, icon, accent stripe, red when it
  needs attention), status pills, severity-striped tables, cards, forms, and
  glove-friendly ≥48px field buttons (the amber "Timbra Entrata").
- Dashboard KPIs and the two alert tables reworked; card headers made theme-aware.
  New `admin.nav.*` labels in `lang/it.php`.
- **No behavioural change** — the 398-assertion suite stays green; only the skin moved.

## 2026-07-06 — v2 platform: legal compliance, field UX, exports (Phases 3–8)

Builds the full application layer on top of the v2 schema: the subcontractor portal,
all four Italian legal must-haves (Badge di Cantiere, Giornale dei Lavori, S.A.L.,
Scadenzario Sicurezza), geolocated photo evidence + an offline PWA, the accountant
Excel export, and Coolify deployment hardening.

**Test status: 398 assertions green** (202 prior + 196 new) on a fresh database.

### Phase 3 — Subcontractor role & portal

- `subcontractor` registered in `UserController::ROLES` and `Auth::homeFor` (→ `/sub`);
  users can be linked to a subcontractor company (`users.subcontractor_id`).
- Admin **`SubcontractorController`** — CRUD + M:N project assignment
  (`SubcontractorModel`, `ProjectSubcontractorModel`, `syncProjects`).
- **`Sub\*` portal** (`/sub`, `/sub/projects/{id}`, photo streaming) behind
  `SubcontractorProjectGuard` — assigned projects only, 404 on not-mine, **no
  inventory/cost exposure**. Seed adds a `sub1@gestionale.local` login.

### Phase 4a — Badge di Cantiere Digitale (Decreto 332/2026)

- **`SiteAttendanceModel`** + shared **`AttendanceController`** — field clock in/out
  (`/attendance`) for workers and subcontractors with best-effort GPS; single open
  attendance enforced; WGS84 coordinate validation (`Validate::isLatitude/isLongitude`).
- Admin register `GET /admin/attendance` (per project + day, GPS map links).

### Phase 4b — Giornale dei Lavori (DPR 380/2001)

- **`DailyLogModel`/`EquipmentModel`** + **`DailyLogController`** — one log per
  `(project, date)`, equipment join, **closed-day immutability** (edits/close/equipment
  rejected once `is_closed`).
- **`WeatherService`** — Open-Meteo auto-fill (WMO→Italian map), best-effort, disabled
  in tests via `WEATHER_ENABLED=false`. Seed adds project coordinates + an equipment catalog.

### Phase 4c — Generatore di S.A.L.

- **`SalDocumentModel`/`SalLineModel`** + **`SalController`** — per-project numbered
  documents, priced line items (optionally from `warehouse_items.unit_cost`),
  `draft → issued → signed` state machine; **`SalPdfBuilder`** renders the locked PDF on
  issue; DL signature captured (canvas PNG). Issued documents are frozen.

### Phase 4d — Scadenzario Sicurezza (D.Lgs. 81/2008)

- **`ComplianceDocumentModel`** + **`ComplianceController`** — CRUD over polymorphic
  subjects (worker/company/subcontractor/project), doc types (DURC/POS/PSC/patente_crediti/…),
  `credits` for the Patente a Crediti. Dashboard widget surfaces documents expiring
  **≤30 days** (or already expired), highlighted red.

### Phase 5 — Field UX: geo-photos + offline PWA

- Photo upload now captures `photos.lat/lng/captured_at` (shown on the admin
  intervention detail with an OpenStreetMap link).
- Installable PWA: `manifest.webmanifest`, `sw.js` (cache-first shell), `offline.html`,
  app icons; SW registered scope-aware in `app.js`.
- Generic offline write queue for the Badge di Cantiere (timbrature persisted in
  `localStorage`, replayed on reconnect), alongside the existing photo queue.

### Phase 6 — Accountant export

- **`AccountantExportDataService`/`AccountantExportBuilder`** + **`ExportController`** —
  monthly `.xlsx` (`/admin/exports/accountant?month=YYYY-MM`) with material cost
  (qty × `unit_cost`), worker hours (from attendance), and per-cantiere cost centres.

### Phase 7 — Coolify deployment

- App image adds `ca-certificates` (outbound HTTPS for Open-Meteo); `WEATHER_ENABLED`/
  `WEATHER_TIMEOUT` wired through both compose files and `env.production.example`.
- New **`docs/DEPLOYMENT_COOLIFY.md`** — end-to-end Coolify-on-Hetzner guide.

### Phase 8 — Tests & docs

- New cases: `05`–`09` (unit: subcontractor, attendance, daily log, S.A.L., compliance)
  and `12`–`18` (HTTP e2e for each feature + PWA shell + accountant export). Docs updated.

## 2026-07-06 — v2 foundation: multi-site inventory (Phases 0–2)

First PR of the v2 "full-fledged Italian construction platform" effort. Delivers the
documentation baseline, the complete v2 database schema, and the multi-site inventory
feature (plus a confirmed stock-inflation bug fix). Later v2 phases (subcontractor
portal, legal compliance features, PWA, accountant export, Coolify deploy) follow in
subsequent PRs — see [docs/ROADMAP.md](docs/ROADMAP.md). Gap IDs reference
[docs/GAP_ANALYSIS.md](docs/GAP_ANALYSIS.md) §6.

**Test status: 202 assertions green** (174 v1 + 28 v2) on a fresh database.

### Phase 0 — Documentation baseline

- Corrected `docs/DATA_MODEL.md`, `docs/API.md`, `docs/ARCHITECTURE.md` to current code
  truth and documented the location ledger + the `complete()` fix.
- New **`docs/DOMAIN_IT.md`** — Italian construction domain (Badge di Cantiere, Giornale
  dei Lavori, S.A.L., Scadenzario Sicurezza, DURC/POS/PSC/Patente a Crediti, glossary,
  decree references) and how each maps to app entities.
- Rewrote `docs/ROADMAP.md` (v2 9-phase plan, reservation-model decision) and
  `docs/GAP_ANALYSIS.md` §6 (v2 gaps V1–V12, closed vs. planned).

### Phase 1 — v2 schema, models, seed

- Migrations **`003`–`009`**: `stock_locations` (+ default warehouse id=1),
  location-aware `stock_movements` (`location_id`, `transfer_in`/`transfer_out` types),
  `stock_balances` cache, `warehouse_items.unit_cost`; `subcontractors` + `subcontractor`
  role + `project_subcontractors`; `site_attendance`; `daily_logs`/`equipment`/
  `daily_log_equipment`; `sal_documents`/`sal_lines`; `compliance_documents`;
  `photos.lat/lng/captured_at`. Pre-existing ledger rows backfill to the main warehouse.
- New models `StockLocationModel`, `StockBalanceModel`.
- Seed extended with the main warehouse, a site location per project, item unit costs,
  and a subcontractor (+ project assignment).

### Phase 2 — Multi-site inventory (per-location balances + transfers)

- **Location-aware ledger.** `WarehouseItemModel::recomputeStock` now computes the
  **main-warehouse (location 1)** balance and understands transfer movements;
  `StockBalanceModel::recompute` does the same per location; `refreshCaches($item,$loc)`
  keeps both caches reconciled after every movement write.
- **`StockTransferService`** — moves stock warehouse↔cantiere as a paired
  `transfer_out`+`transfer_in` write in one transaction, locks the item `FOR UPDATE`,
  guards the source balance against going negative, refreshes both caches. Total stock
  across locations is conserved. Route `POST /admin/warehouse/{id}/transfer`; the item
  detail page shows per-location balances + a transfer form; the list gets a Trasferisci
  action.
- **Auto site location** created on project creation (`ProjectController::store`).
- **Reservation model = additive/site-optional.** `InterventionService::create/complete`
  take an optional `locationId` (default = main warehouse), so v1 behaviour and all
  existing tests are unchanged.
- **Bug fix.** `complete()` now emits the surplus `release` **only for materials that
  were actually reserved** (`is_reserved = 1`). Previously it released
  `(qty_planned − qty_used)` for every material, so a never-reserved row (e.g. the seed's
  `is_reserved=0` materials on the `in_progress` intervention) added phantom stock.
- **Tests.** New `tests/cases/04_multisite_stock.php` + a concurrent-transfer race in
  `11_concurrency.php`, including a regression for the `complete()` non-inflation fix.

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
