# Gestionale Muratori — Field Service & Construction Management

Management platform for construction sites, on-site technical interventions, real-time
warehouse inventory and client-facing reports. Three roles, three experiences:

| Role | Experience |
|------|-----------|
| **Admin** (Amministratore) | Desktop dashboard: full CRUD on clients, projects, interventions, warehouse; assigns work, manages stock, exports reports, manages users. |
| **Worker** (Operaio) | Minimalist mobile web app: "My Tasks Today", status changes, actual material quantities, before/during/after photos, client signature capture. |
| **Client** (Cliente) | Read-only mobile/desktop view of *their* projects only: before/after photos and downloadable PDF/Excel reports. |

> All **user-facing text is Italian** (single source: [lang/it.php](lang/it.php)).
> Code, comments, and DB column names are English. DB ENUM values stay in English
> and are translated in the view layer (`in_progress` → *In corso*).

## Stack

- **PHP 8.2+** — no framework; small custom MVC in `/src` (router, controllers, models, services)
- **MySQL 8 / MariaDB** (InnoDB, utf8mb4) — raw **PDO** with prepared statements, no ORM
- **Frontend** — server-rendered PHP views + jQuery/AJAX, Bootstrap 5, mobile-first
- **PDF** — mPDF · **Excel** — PhpSpreadsheet (Composer)
- Required PHP extensions: `pdo_mysql`, `gd`, `mbstring`, `zip`, `fileinfo`

## Documentation

| Document | Contents |
|----------|----------|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Layers, request lifecycle, folder structure, conventions |
| [docs/DATA_MODEL.md](docs/DATA_MODEL.md) | Full schema (v1 + v2 tables), inventory ledger semantics, status state machine |
| [docs/DOMAIN_IT.md](docs/DOMAIN_IT.md) | Italian construction domain: legal obligations, glossary, entity mapping |
| [docs/API.md](docs/API.md) | Every route: method, role, parameters, responses |
| [docs/GAP_ANALYSIS.md](docs/GAP_ANALYSIS.md) | Production-readiness gap analysis (security, features, ops) |
| [docs/ROADMAP.md](docs/ROADMAP.md) | Phased implementation plan toward production on Hetzner |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | Hetzner deployment guide (Docker + bare-metal) |
| [docs/DEPLOYMENT_COOLIFY.md](docs/DEPLOYMENT_COOLIFY.md) | Coolify-on-Hetzner deployment guide (Docker Compose build pack) |
| [docs/CONFIGURATION.md](docs/CONFIGURATION.md) | Every environment variable (DB, sessions, company PDF identity, mail, scheduler, weather) |
| [docs/TESTING.md](docs/TESTING.md) | Test suite and full-flow simulation instructions |
| [CHANGELOG.md](CHANGELOG.md) | Changes per release/session |

## Quick start (local, Windows + XAMPP)

XAMPP's PHP is not on PATH — use the full path `C:\xampp\php\php.exe` (shown as `php` below).

1. **Start** Apache and MySQL from the XAMPP control panel.

2. **Configure the environment**:
   ```powershell
   Copy-Item .env.example .env
   ```
   Defaults match a standard XAMPP install (`root`, no password, database
   `gestionale_muratori`). Adjust as needed. **In production set `APP_DEBUG=false`
   and a dedicated DB user** — see [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).

3. **Create the schema** (also creates the database if missing):
   ```powershell
   php database\migrate.php
   ```

4. **Load sample data**:
   ```powershell
   php database\seed.php
   ```

5. **Install Composer dependencies** (mPDF + PhpSpreadsheet for reports):
   ```powershell
   composer install --no-dev
   ```
   Without `vendor/` the app still runs; only report downloads fail.

6. **Serve it**:
   - under `htdocs`: `http://localhost/GestionaleMuratori/public/`
   - or with the built-in dev server (needed for clean URLs like `/login`):
     ```powershell
     php -S localhost:8000 -t public public/index.php
     ```
     then open `http://localhost:8000/`.

## Seed data & credentials

`database/seed.php` is **idempotent** (truncates and reloads). It creates
1 admin, 2 workers, 2 client companies (+1 login each), 5 projects, 10 warehouse
items and 6 sample interventions. Password for every account: `password`.

| Email | Role |
|-------|------|
| `admin@gestionale.local`   | Admin |
| `worker1@gestionale.local` | Worker |
| `worker2@gestionale.local` | Worker |
| `client1@gestionale.local` | Client (Edilizia Rossi) |
| `client2@gestionale.local` | Client (Costruzioni Bianchi) |

## Core business rules (summary — details in [docs/DATA_MODEL.md](docs/DATA_MODEL.md))

- **Inventory is a ledger.** `warehouse_items.qty_in_stock` is a cached running total;
  the source of truth is `stock_movements`. An admin action can recompute (reconcile)
  the cache from the ledger at any time.
- **Reserve → commit flow.** Creating an intervention reserves planned materials
  (`reserve` movement, stock decremented, blocked if insufficient). Worker completion
  writes `out` for used quantities and `release` for unused surplus — all in one
  transaction with `SELECT … FOR UPDATE` row locks.
- **Status state machine** (server-enforced, every change recorded in
  `intervention_status_history`):
  `pending → in_progress|cancelled`, `in_progress → on_hold|completed|cancelled`,
  `on_hold → in_progress|cancelled`; `completed`/`cancelled` are terminal.
- **Completion gate.** An intervention cannot complete without ≥1 "after" photo and
  `qty_used` for every linked material. Client signature is optional but supported.
- **RBAC on every endpoint.** Workers only touch interventions assigned to them;
  clients only ever see `WHERE projects.client_id = session.client_id`.

## Project layout

```
/public      entry point (index.php), .htaccess, assets (css/js)
/src         application code
  /Controllers   Admin/, Worker/, Client/ + Auth, Dashboard
  /Http          Router + Middleware (AuthGuard, ownership guards)
  /Models        one class per table, raw PDO
  /Services      InterventionService (stock + state machine), Report/
  /Support       Auth, Config, Database, Env, Lang, Request, Response,
                 Session, Url, Validate, View, Storage/ (StorageInterface)
/views       PHP templates (layout + per-area folders)
/lang        it.php — every user-facing string
/config      config.php (reads .env)
/database    migrations/*.sql + migrate.php + seed.php
/storage     uploads (photos, signatures) — git-ignored
/docs        project documentation
/tests       test suite (see docs/TESTING.md)
```

## Security model (v1.1)

- CSRF token required on every POST (`X-CSRF-Token` header, wired automatically
  in `app.js`); logout is POST-only.
- Login rate limiting (5 failures/15 min per email, 20 per IP → HTTP 429) with a
  full authentication audit trail (`login_attempts`).
- Hardened sessions: strict mode, HttpOnly, SameSite=Lax, `Secure` in production,
  8-hour idle timeout. Password change page for every role (`/password`).
- Security headers on every response (nosniff, X-Frame-Options DENY,
  Referrer-Policy, CSP `'self'`); HSTS via Caddy in production.
- Debug mode **off by default**; uncaught errors are logged, never shown.
- Assets self-hosted (no CDN) — the worker app works on sites with bad
  connectivity and no third party sees your traffic.

## Testing

```powershell
powershell -ExecutionPolicy Bypass -File tests/start-test-db.ps1   # throwaway MySQL 8 in Docker
C:\xampp\php\php.exe tests\run.php                                  # 451 assertions
```

The suite runs on its own database and covers the ledger math, the state
machine, RBAC, CSRF, rate limiting, uploads/reports, and a real concurrency
race test. Details: [docs/TESTING.md](docs/TESTING.md).

## Production deployment (Hetzner)

One-command Docker stack (Caddy with automatic HTTPS → PHP-FPM → MySQL 8):

```bash
cp deploy/env.production.example .env   # fill in domain + secrets
docker compose up -d --build
docker compose exec app php database/migrate.php
docker compose exec app php scripts/create-admin.php "Nome" admin@example.com 'password'
```

Full step-by-step guide (server hardening, backups, restore, updates,
monitoring): [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).

## Development status

The original 8-phase specification (see [superprompt.md](superprompt.md)) is fully
implemented, plus the v1.1 production-hardening release: security (CSRF, rate
limiting, sessions, headers), user management UI, admin intervention detail with
photos/history, operations dashboard with low-stock alerts, worker task tabs, and
Docker deployment. The **v2 platform** then added multi-site inventory (per-location balances +
warehouse↔cantiere transfers), the **subcontractor portal**, all four Italian legal
must-haves (**Badge di Cantiere** GPS attendance, **Giornale dei Lavori** with
Open-Meteo weather auto-fill, **S.A.L.** generator with locked PDF + DL sign-off,
**Scadenzario Sicurezza** expiry dashboard), **geolocated photos + an offline PWA**,
and the **accountant Excel export** — deployed via Coolify on Hetzner. The
**2026-07-10 hardening pass** then fixed redesign regressions and added a
**proactive alert engine** (in-app notification bell + daily scheduler for expiring
compliance docs, overdue interventions, auto-expiring quotes and low stock; optional
e-mail digests via a config-gated SMTP mailer), **list pagination**, **client quote
self-service** (accept/reject in the portal), query **indexes**, and full report/PDF
i18n. See [docs/DOMAIN_IT.md](docs/DOMAIN_IT.md), [docs/ROADMAP.md](docs/ROADMAP.md),
[docs/CONFIGURATION.md](docs/CONFIGURATION.md) and
[docs/DEPLOYMENT_COOLIFY.md](docs/DEPLOYMENT_COOLIFY.md). A 451-assertion automated
test suite backs it.
History: [CHANGELOG.md](CHANGELOG.md) · plan: [docs/ROADMAP.md](docs/ROADMAP.md) ·
remaining ideas: [docs/GAP_ANALYSIS.md](docs/GAP_ANALYSIS.md) §6.
