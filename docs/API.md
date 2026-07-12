# HTTP routes / API

All routes are declared in [public/index.php](../public/index.php).
Conventions:

- **JSON contract** for every AJAX endpoint: `{ ok: bool, data?: mixed, error?: string }`.
  Errors use proper status codes (401 unauthenticated, 403 forbidden/CSRF,
  404 not found, 422 validation/domain error, 429 rate-limited, 500 unexpected).
- **CSRF**: every POST must carry the session token — header `X-CSRF-Token`
  (sent automatically by `app.js` for all AJAX incl. uploads) or a `_token`
  form field. The token is in the page's `<meta name="csrf-token">`. Missing or
  wrong token → 403.
- Requests with `X-Requested-With: XMLHttpRequest` (or `Accept: application/json`)
  get JSON errors; plain navigation gets HTML error pages.
- RBAC is enforced server-side on **every** action via `AuthGuard` +
  ownership guards. Worker/client "not mine" is reported as 404 (no existence leak).
- Authenticated sessions expire after 8 h idle (`SESSION_IDLE_TIMEOUT`).
- All POST bodies may be form-encoded or JSON.

## Public / any authenticated role

| Method | Path | Description |
|--------|------|-------------|
| GET | `/` | Redirect to role landing page (or `/login`). |
| GET | `/login` | Login page. |
| POST | `/login` | Body: `email`, `password`. → `{ok, data:{redirect}}`; 401 bad credentials, 422 empty fields, **429 after 5 failures/15 min per email (20 per IP)** — attempts audited in `login_attempts`. |
| POST | `/logout` | Clears the session. → `{ok, data:{redirect}}` (or 302 for non-AJAX). *(GET /logout was removed.)* |
| GET | `/password` | Change-password page (any authenticated role). |
| POST | `/password` | Body: `current_password`, `new_password`, `new_password_confirm` (min 8). 422 on wrong current / short / mismatch. |
| GET | `/health` | Readiness probe — checks DB connectivity. `{ok:true,data:{status:"ok"}}` or 500. |

## Admin (role: `admin`)

### Dashboard
| Method | Path | Description |
|--------|------|-------------|
| GET | `/admin` | Operations dashboard: active projects, open interventions, today's interventions by status, low-stock alert table, section links. |
| GET | `/admin/statistics` | Read-only analytics: KPI row + status donuts, monthly interventions trend, expenses-by-category and top-clients bars (pure SVG/CSS). |
| GET | `/admin/financials` | Read-only per-cantiere cash-in (invoiced/collected) vs cash-out (materials at unit_cost + expenses) and margin, with a portfolio KPI row. |
| GET | `/shortcuts` | Keyboard-shortcut guide; for admins, an editor for the "G-then-key" nav shortcuts. Any authenticated user. |
| POST | `/shortcuts` | `shortcuts[<action>]=<key>` map. Admin only. Validates (single letter, unique, "G" reserved), persists overrides → `{ok,data:{shortcuts}}` or `{ok:false,error}` (422). |

### Clients
| Method | Path | Body / params | Description |
|--------|------|---------------|-------------|
| GET | `/admin/clients` | `q` search | HTML list. |
| POST | `/admin/clients` | `name`*, `vat_or_tax_id`, `email`, `phone`, `address`, `notes` | Create → `{ok,data:{id}}`. |
| POST | `/admin/clients/{id}` | same | Update. |
| POST | `/admin/clients/{id}/delete` | — | Delete (cascades to projects/interventions). |

### Projects
| Method | Path | Body / params | Description |
|--------|------|---------------|-------------|
| GET | `/admin/projects` | `q`, `client_id`, `status` | HTML list. |
| POST | `/admin/projects` | `client_id`*, `name`*, `location`, `start_date`* (Y-m-d), `end_date` (≥ start), `invoice_reference`, `status` (`active|on_hold|closed`) | Create. |
| POST | `/admin/projects/{id}` | same | Update. |
| POST | `/admin/projects/{id}/delete` | — | Delete (cascades). |

### Warehouse
| Method | Path | Body / params | Description |
|--------|------|---------------|-------------|
| GET | `/admin/warehouse` | `q` | HTML list. |
| POST | `/admin/warehouse` | `name`*, `sku` (unique), `unit`* (`pcs|kg|m|l|box`), `reorder_level` (≥0) | Create item (stock starts at 0 — load via movement). |
| GET | `/admin/warehouse/{id}` | — | Item detail + full movement ledger. |
| POST | `/admin/warehouse/{id}` | same as create | Update item master data. |
| POST | `/admin/warehouse/{id}/toggle` | — | Activate/deactivate. |
| POST | `/admin/warehouse/{id}/movement` | `type` (`in`\|`adjustment`), `qty` (≠0; `in` must be >0; `adjustment` may be negative), `note` | Manual ledger entry, transaction + row lock; blocks negative stock (configurable) and DECIMAL overflow. → `{ok,data:{qty_in_stock}}`. |
| POST | `/admin/warehouse/{id}/reconcile` | — | Recompute cache from ledger. → `{ok,data:{before,after,changed,message}}`. |
| POST | `/admin/warehouse/{id}/transfer` | `from_location_id`*, `to_location_id`*, `qty`* (>0), `note` | **v2** — move stock between locations (warehouse↔cantiere) in one locked transaction. Writes `transfer_out`+`transfer_in`, refreshes both balances. 422 on same location / invalid qty / inactive item or location / insufficient source stock. → `{ok,data:{from_qty,to_qty}}`. The item detail page also shows per-location balances. |

### Interventions
| Method | Path | Body / params | Description |
|--------|------|---------------|-------------|
| GET | `/admin/interventions` | `project_id`, `worker_id`, `status`, `range` (`today|week`) | HTML list with materials. |
| POST | `/admin/interventions` | `project_id`*, `title`*, `assigned_worker_id` (role-checked), `description`, `scheduled_date`, `scheduled_start_time`, repeated `item_id[]` + `qty_planned[]` | Create + reserve materials in one transaction. 422 with Italian message on insufficient stock / invalid item / duplicate item. |
| GET | `/admin/interventions/{id}` | — | Detail page: metadata, planned vs used materials, photos by type, signature, completion notes, full status history. |
| POST | `/admin/interventions/{id}` | title/worker/description/schedule fields | Update basic fields (no project, no materials, no status). |
| POST | `/admin/interventions/{id}/status` | `to_status` | State-machine transition (admin may also cancel `pending`). Cancellation releases reservations. |
| GET | `/admin/interventions/{id}/signature` | — | Stream the client signature PNG. |

### Users
| Method | Path | Body / params | Description |
|--------|------|---------------|-------------|
| GET | `/admin/users` | `q` search, `role` filter | HTML list (never exposes password hashes). |
| POST | `/admin/users` | `name`*, `email`* (unique), `role`* (`admin|worker|client`), `client_id` (required iff role=client), `password`* (min 8) | Create login. |
| POST | `/admin/users/{id}` | same; `password` optional (sets a new one when non-empty) | Update / reset password. Demoting your own admin account → 422. |
| POST | `/admin/users/{id}/toggle` | — | Activate/deactivate. Deactivating yourself → 422. |

### Photos
| Method | Path | Description |
|--------|------|-------------|
| GET | `/admin/photos/{id}` / `/admin/photos/{id}/thumb` | Stream any photo (admin only). |

### Reports
| Method | Path | Description |
|--------|------|-------------|
| GET | `/admin/projects/{id}/report/pdf` | A4 PDF (mPDF): header, interventions table, materials used (from ledger `out`), before/after photo grid, signatures. |
| GET | `/admin/projects/{id}/report/excel` | XLSX data export (no images). |

## Worker (role: `worker`; ownership enforced on `{id}`)

| Method | Path | Body / params | Description |
|--------|------|---------------|-------------|
| GET | `/worker` | `tab` = `today` (default) \| `upcoming` (open future/unscheduled) \| `done` (completed, last 14 days) | "My Tasks" list. |
| GET | `/worker/interventions/{id}` | — | Task detail: materials, photos by type, signature pad, complete form. |
| POST | `/worker/interventions/{id}/status` | `to_status` ∈ `in_progress|on_hold|cancelled` | Quick transition. |
| POST | `/worker/interventions/{id}/complete` | `qty_used[materialId]` for every material, `completion_notes` | §4.2 commit + §4.4 gate. Only from `in_progress`. 422 when a qty is missing/invalid or no `after` photo. |
| POST | `/worker/interventions/{id}/signature` | `signature` = PNG data-URL (≤5 MB) | Store canvas signature. |
| GET | `/worker/interventions/{id}/signature` | — | Stream saved signature PNG. |
| POST | `/worker/interventions/{id}/photos` | multipart `photo` (JPEG/PNG ≤8 MB, content-sniffed), `type` ∈ `before|during|after` | Upload; GD thumbnail (max 480px) generated best-effort. |
| GET | `/worker/photos/{id}` | — | Stream original (ownership-checked). |
| GET | `/worker/photos/{id}/thumb` | — | Stream thumbnail (falls back to original). |

## Client (role: `client`; `client_id` scoping enforced)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/client` | My projects (read-only). |
| GET | `/client/projects/{id}` | Project detail: interventions + before/after gallery ("during" photos are internal). |
| GET | `/client/photos/{id}` / `/client/photos/{id}/thumb` | Photo streaming with ownership chain photo→intervention→project→client. |
| GET | `/client/projects/{id}/report/pdf` / `.../excel` | Same reports as admin, own projects only. |

*Starred parameters are required.*


## Addendum — Preventivi / Fatture / Spese + project detail (2026-07-08)

Admin routes added (all `AuthGuard::require(..., ['admin'])`, JSON `{ok,data?,error?}` on writes):

- `GET /admin/quotes`, `GET /admin/quotes/create`, `GET /admin/quotes/{id}/edit`, `GET /admin/quotes/{id}/pdf`, `POST /admin/quotes`, `POST /admin/quotes/{id}`, `POST /admin/quotes/{id}/delete`, `POST /admin/quotes/{id}/invoice`.
- `GET /admin/invoices`, `GET /admin/invoices/create`, `GET /admin/invoices/{id}/edit`, `GET /admin/invoices/{id}/print`, `POST /admin/invoices`, `POST /admin/invoices/{id}`, `POST /admin/invoices/{id}/delete`.
- `GET /admin/expenses` (`?category=`), `GET /admin/expenses/create`, `GET /admin/expenses/{id}/edit`, `POST /admin/expenses`, `POST /admin/expenses/{id}`, `POST /admin/expenses/{id}/delete`.
- Project detail: `GET /admin/projects/create|{id}/edit|{id}`, plus `POST .../{id}/documents`, `GET .../{id}/documents/{docId}`, `POST .../{id}/documents/{docId}/delete`, `POST .../{id}/invoices[/{invoiceId}/delete]`, `POST .../{id}/materials[/{materialId}/delete]`, `POST .../{id}/attendance`, `POST .../{id}/workers[/{workerId}/remove]`.

## Addendum — notifications + client quotes (2026-07-10)

Admin (role `admin`):
- `GET /admin/notifications` (`?filter=unread`) — alert feed (list).
- `POST /admin/notifications/{id}/read` — mark one read → `{ok}`.
- `POST /admin/notifications/read-all` — mark all read → `{ok,data:{count}}`.

Client self-service (role `client`; every query scoped by `client_id`, drafts hidden):
- `GET /client/quotes` — the client's non-draft quotes.
- `GET /client/quotes/{id}` — quote detail with line items + accept/reject actions.
- `POST /client/quotes/{id}/accept` / `POST /client/quotes/{id}/reject` — decide a
  *sent* quote → `{ok,data:{status}}`; a foreign, already-decided or expired quote is 422.

Admin list pages (`/admin/interventions|expenses|invoices|quotes`) accept `?page=N`
(25 rows/page; all existing filters preserved).

Not an HTTP route: `php scripts/scheduler.php` (cron) generates the notifications and,
when `MAIL_ENABLED=true`, e-mails the admins a digest. See [CONFIGURATION.md](CONFIGURATION.md).
