# Architecture

Custom, dependency-light PHP MVC. No framework: the whole HTTP layer is
~300 lines of readable code under `src/Http` and `src/Support`. Composer is used
only for report generation (mPDF, PhpSpreadsheet).

## Request lifecycle

```
Caddy/nginx/Apache or `php -S`
  └─ public/index.php            front controller
       ├─ src/bootstrap.php      Composer autoload → bundled PSR-4 fallback → Env::load(.env)
       │                         → date_default_timezone_set(APP_TIMEZONE, default Europe/Rome)
       ├─ Session::start()       strict mode, HttpOnly, SameSite=Lax, Secure (config)
       ├─ Lang::load()           lang/it.php
       ├─ security headers       nosniff, X-Frame-Options DENY, Referrer-Policy, CSP 'self'
       ├─ Url::setBase()         subfolder vs docroot support
       ├─ Request::fromGlobals() merges query + form + JSON body
       ├─ idle-timeout check     authenticated sessions expire after SESSION_IDLE_TIMEOUT (8h)
       ├─ CSRF gate              every POST needs X-CSRF-Token header (or _token field) → 403
       ├─ Router::dispatch()     first matching METHOD+path wins, {param} → handler arg
       │    └─ Controller action
       │         ├─ AuthGuard::require($req, [roles])    RBAC — first line of every action
       │         ├─ ownership guards (worker/client)     404 on not-mine (no existence leak)
       │         ├─ Model / Service calls (PDO)
       │         └─ Response::ok()/fail()/html()/redirect()
       └─ catch-all: logs Throwable, 500 page/JSON (rethrow when APP_DEBUG=true; default false)
```

## Layers

### `src/Support` — framework primitives
| Class | Responsibility |
|-------|---------------|
| `Env` | Tiny `.env` parser; falls back to real environment variables. |
| `Config` | `config/config.php` + dot-notation access (`db.host`). |
| `Database` | PDO factory. One shared connection per request (`pdo()`); `ERRMODE_EXCEPTION`, native prepares, no stringified fetches. |
| `Session` | Cookie-hardened session wrapper + flash messages. |
| `Auth` | `attempt/login/logout/check/user/role/clientId`, role → landing page. Session stores a minimal snapshot, never client-supplied identity. |
| `Request` | Method, base-stripped path, merged input (`query`+`form`+JSON), `wantsJson()`. |
| `Response` | JSON contract `{ok, data?, error?}`, redirects, HTML. |
| `Router` (`src/Http`) | GET/POST tables, `{param}` → regex `([^/]+)`, dispatches to `[Class, method]`. |
| `View` | PHP templates from `/views`, optional `layout`, `View::e()` escaper, shared data (`base`, `user`). |
| `Lang` | `lang/it.php` dot-notation lookup + `label(group, enumValue)` for DB ENUM translation. |
| `Url` | Base-path aware URL builder (subfolder or docroot). |
| `Validate` | Shared quantity validation incl. `DECIMAL(12,3)` overflow ceiling (MySQL without `STRICT_TRANS_TABLES` truncates silently — the app rejects instead). |
| `Csrf` | Session-bound token (`random_bytes(32)`), constant-time check, read from `X-CSRF-Token` header or `_token` field. Enforced centrally for every POST. |
| `Storage\StorageInterface` + `LocalStorage` | Uploaded-file abstraction (S3 drop-in later). Files under `storage/uploads/{project_id}/{intervention_id}/` (root overridable via `UPLOADS_PATH`). |

### `src/Http/Middleware` — guards (call-at-top-of-action pattern)
- **`AuthGuard::require($request, $roles)`** — 401/redirect when unauthenticated,
  403 when role not allowed. Returns the session user snapshot.
- **`InterventionOwnerGuard`** — worker may only see/touch interventions where
  `assigned_worker_id = session.user.id`; missing and not-mine both → 404.
- **`ClientProjectGuard`** — client may only see projects where
  `client_id = session.user.client_id`; missing and not-mine both → 404.

### `src/Models` — one class per table, raw PDO
`ClientModel`, `ProjectModel`, `UserModel`, `InterventionModel`,
`InterventionMaterialModel`, `WarehouseItemModel`, `StockMovementModel`,
`PhotoModel`, and (v2) `StockLocationModel`, `StockBalanceModel`. Locking variants
(`findForUpdate`, `reservedForUpdate`, `forInterventionForUpdate`) exist for every
read that participates in a stock or status transaction. `WarehouseItemModel::refreshCaches($item, $location)`
is the single coordination point after any movement write: it recomputes the
`(item, location)` balance and, when the location is the main warehouse, `qty_in_stock`.

### `src/Services` — business logic
- **`InterventionService`** — the heart of the system:
  - `create()` — intervention + material reservation in one transaction; items
    locked in deterministic id order (deadlock avoidance); insufficient stock
    blocks creation (configurable via `ALLOW_NEGATIVE_STOCK`).
  - `transition()` — server-side state machine; cancellation releases all
    reserved quantities.
  - `complete()` — completion gate (§ after-photo + qty_used), `out` + surplus
    `release` movements, cache recompute, history row — one transaction.
  - `'completed'` is *only* reachable through `complete()`, never `transition()`.
  - v2: `create()`/`complete()` take an optional `locationId` (default = main
    warehouse); `complete()` releases the unused surplus **only for materials that
    were actually reserved** (`is_reserved = 1`), so never-reserved rows can't
    inflate stock.
- **`StockTransferService`** (v2) — moves stock between two locations
  (warehouse↔cantiere) as a paired `transfer_out`+`transfer_in` write in one
  transaction; locks the item row `FOR UPDATE`, guards the source balance, and
  refreshes both location caches. Total stock across locations is conserved.
- **`Services\Report`** — `ReportDataService` (shared data gathering),
  `PdfReportBuilder` (mPDF over `views/reports/pdf.php`), `ExcelReportBuilder`
  (PhpSpreadsheet, data-only export), `ReportFilename`.
- **`LoginRateLimiter`** — sliding-window throttle on the `login_attempts`
  table (5 failures/15 min per email, 20 per IP; success resets). The table
  doubles as an authentication audit trail.
- **`PhotoStreamService`** — streams photos/thumbnails/signatures from storage
  with correct content type; every controller runs its own authorization first.

### Controllers
Thin: guard → validate input → call model/service → `Response`. Validation
helpers return `null` after sending a `fail()` response, so actions read top-down.

- `Admin\*` — Clients, Projects, Warehouse (+ ledger + reconcile), Interventions
  (list + detail with history/photos/signature, materials, status), Users
  (create/edit/toggle/password-reset with self-lockout guards), Photos
  (streaming), Reports (PDF/Excel).
- `Worker\*` — TaskController (task tabs today/upcoming/done, detail, status,
  complete, signature), PhotoController (upload + permission-checked streaming).
- `Client\*` — read-only projects, permission-checked photo streaming, reports.
- `AuthController` — login (rate-limited), POST logout, change password.

## Frontend

- Single `public/assets/js/app.js` (jQuery). Patterns:
  - `Api.get/post` helper — always `X-Requested-With: XMLHttpRequest` so the
    server answers JSON, plus the `X-CSRF-Token` header (read from the layout's
    meta tag, also set globally via `$.ajaxSetup`).
  - Generic CRUD modal driven by `data-*` attributes (`.js-crud-form`,
    `.js-crud-new/edit/delete`) shared by all admin resource pages.
  - Worker photo upload: client-side canvas compression (max 1600px, JPEG 0.8),
    offline retry queue in `localStorage` flushed on `online` event.
  - Signature pad: canvas → PNG data URL → POST.
  - POST logout button; password-change form handler; user-modal role/client sync.
- Bootstrap 5.3 + jQuery 3.7 **self-hosted** under `public/assets/vendor/`
  (no CDN: offline-friendly for on-site workers, satisfies CSP `'self'`).
- Minimal `app.css`, green accent, mobile-first cards.

## Error handling

- Global catch-all in `public/index.php`: every uncaught `Throwable` is logged
  via `error_log()`; users see the generic 500 page (or JSON error), never a
  stack trace. With `APP_DEBUG=true` the exception is rethrown for development.
- Domain errors (`RuntimeException` from services) surface as HTTP 422 with an
  Italian message from `lang/it.php`.

## Conventions

- All user-facing strings via `Lang::get()` — never hardcode Italian text.
- Every DB write touching stock or status runs inside a transaction with
  `SELECT … FOR UPDATE` locks; lock acquisition in deterministic (sorted-id) order.
- All output escaped with `View::e()`; all SQL through prepared statements.
- JSON contract everywhere: `{ ok: bool, data?, error? }`.
- Keep files under 500 lines; validate at system boundaries (controllers).
