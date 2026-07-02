# Gestionale Muratori — Claude Code project instructions

Field Service & Construction Management System. PHP 8.2 custom MVC (no framework),
MySQL 8/MariaDB via raw PDO, jQuery/Bootstrap 5 frontend, mPDF + PhpSpreadsheet reports.
Full docs: [README.md](README.md), [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md),
[docs/DATA_MODEL.md](docs/DATA_MODEL.md), [docs/API.md](docs/API.md).

## Environment (Windows / XAMPP)

- PHP: `C:\xampp\php\php.exe` (not on PATH)
- Migrate: `C:\xampp\php\php.exe database\migrate.php`
- Seed (idempotent, truncates!): `C:\xampp\php\php.exe database\seed.php`
- Dev server: `C:\xampp\php\php.exe -S localhost:8000 -t public public/index.php`
- Tests: `C:\xampp\php\php.exe tests\run.php` (see docs/TESTING.md)
- Seed logins: `admin@gestionale.local` / `worker1@…` / `client1@…`, password `password`

## Hard rules

- **All user-facing text in Italian via `lang/it.php`** (`Lang::get`/`Lang::label`).
  Never hardcode Italian strings in PHP/JS. Code, comments, DB columns in English.
  DB ENUM values stay English (`in_progress`), translated only in views.
- **No ORM, no new frameworks.** Raw PDO prepared statements only.
- **Every DB write touching stock or status runs in a transaction** with
  `SELECT … FOR UPDATE`; lock multiple items in ascending id order (deadlock avoidance).
- The inventory ledger (`stock_movements`) is the source of truth;
  `qty_in_stock` is a cache. Never update the cache without a ledger row.
  Sign convention: in/release/adjustment(+signed) add, reserve subtracts, **out is weight-0**
  (see docs/DATA_MODEL.md before touching stock code).
- `completed` status is only reachable through `InterventionService::complete()`.
- **RBAC on every endpoint**: `AuthGuard::require()` first line of every action;
  worker/client ownership guards return 404 for "not mine".
- Escape all output with `View::e()`. Validate all input server-side
  (`Validate::isQty` for quantities — DECIMAL(12,3) overflow guard).
- JSON responses always `{ ok, data?, error? }`.
- Keep files under 500 lines. No working files in repo root — use `/src`, `/tests`,
  `/docs`, `/config`, `/scripts`, `/database`.
- Never commit secrets or `.env`. Photos/signatures are served only through
  permission-checked controllers, never as static files.

## After changes

- Run the test suite; verify migrations apply cleanly on a fresh DB and the app
  boots with the seed data (acceptance: stock math matches ledger after a full
  create→complete cycle; illegal transitions rejected; cross-role access blocked).
- Update the relevant docs in `/docs` and `CHANGELOG.md` when behavior, routes,
  or schema change.
