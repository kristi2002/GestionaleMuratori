# Testing

Dependency-free test suite (no PHPUnit — plain PHP, per the project's
no-extra-frameworks rule) covering the business invariants and a full HTTP
end-to-end simulation of all three roles.

## Prerequisites

- PHP 8.2 CLI with `curl`, `gd`, `pdo_mysql` (XAMPP's PHP qualifies)
- Docker (for the throwaway test database)

## Running

```powershell
# 1. Start (or reuse) the disposable MySQL 8 test container
#    -> gm-test-mysql on 127.0.0.1:3307, root/test
powershell -ExecutionPolicy Bypass -File tests/start-test-db.ps1

# 2. Run everything
C:\xampp\php\php.exe tests\run.php
```

Exit code 0 = all green. The suite **never touches the development database**:
it runs on `gestionale_muratori_test` (dropped and recreated on every run) and
a scratch uploads dir (`tests/.uploads`, git-ignored). Override the test DB via
`GM_TEST_DB_HOST/PORT/USER/PASS` and the HTTP port via `GM_TEST_HTTP_PORT`
(default 8099).

## What is covered

| File | Coverage |
|------|----------|
| `tests/cases/01_unit.php` | `Validate::isQty` (incl. DECIMAL(12,3) overflow), CSRF token generate/verify semantics. |
| `tests/cases/02_stock_and_state.php` | Service-level §4 invariants: reservation decrements stock + writes ledger; insufficient stock rolls back atomically; the full status state machine (legal + illegal transitions, `started_at` once, history rows); completion gate (after-photo + qty_used); `out`/`release` math on completion (cache == ledger after every step); cancellation restores stock; reconciliation heals a corrupted cache. |
| `tests/cases/03_rate_limiter.php` | Login throttling: 5-failure email block (any IP), 20-failure IP block, reset on success. |
| `tests/cases/10_http_e2e.php` | Boots `php -S` and simulates real clients: security headers, CSRF rejection, self-hosted assets, login errors + rate limiting (HTTP 429), the full RBAC matrix (admin/worker/client × each area), admin CRUD incl. warehouse ledger endpoints (negative-stock and overflow blocks, manual `out` forbidden, reconcile), user management (create/duplicate/deactivate/reset password/self-lockout guard), the complete intervention lifecycle over HTTP (create+reserve → worker start → photo upload with real PNGs → signature → completion gate → stock verification), ownership isolation between workers, client portal scoping (projects, photos, reports), PDF/XLSX downloads (magic bytes), password change, logout, worker tabs, dashboard low-stock alert, and no-PHP-warnings output hygiene. |
| `tests/cases/11_concurrency.php` | §9 race criterion: two workers complete interventions on the **same warehouse item at the same instant** (`curl_multi`); asserts no lost update and cache == ledger afterwards. Verifies the report PDF actually embeds photo images. |

174 assertions at the time of writing.

## Conventions for new tests

- Unit/service cases go in `tests/cases/0*.php` (run before the HTTP server
  starts); E2E cases in `tests/cases/1*.php` (run with `$baseUrl` + `$pdo`
  available).
- Use `T::section/ok/equals/throws`; one behavioral claim per assertion message.
- E2E clients: `new HttpClient($baseUrl)` per session; `->login()` handles the
  CSRF token automatically.
