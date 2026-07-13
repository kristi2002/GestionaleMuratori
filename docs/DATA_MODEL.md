# Data model

Source of truth: [database/migrations/](../database/migrations/) — `001_init.sql` and
`002_login_attempts.sql` for v1, `003`–`009` for the v2 multi-site/platform schema.
All tables InnoDB, utf8mb4_unicode_ci. ENUM values stay English; the view layer
translates them via `lang/it.php` (`Lang::label`).

Relations are strict: **Client 1→N Projects 1→N Interventions**. v2 adds a
**Subcontractor** subject (M:N with projects) and a **StockLocation** per project
(the cantiere), so inventory can move between the warehouse and each site.

## Tables

### users
| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT UNSIGNED PK | |
| name | VARCHAR(190) | |
| job_title | VARCHAR(120) NULL | qualifica, shown on the profile page (migration 021) |
| email | VARCHAR(190) UNIQUE | login identifier |
| phone | VARCHAR(40) NULL | contact phone (migration 021) |
| hire_date | DATE NULL | drives the "anni di esperienza" tenure (migration 021) |
| avatar_path | VARCHAR(255) NULL | stored avatar, served via `UserController::avatar` only (migration 021) |
| password_hash | VARCHAR(255) | `password_hash()` (bcrypt/argon per PHP default) |
| role | ENUM admin/worker/client/subcontractor | `subcontractor` added in v2 (migration 004) |
| client_id | FK → clients, NULL | only for role=client (`ON DELETE SET NULL`) |
| subcontractor_id | FK → subcontractors, NULL | only for role=subcontractor (`ON DELETE SET NULL`), v2 |
| is_active | TINYINT(1) DEFAULT 1 | inactive users cannot log in |
| created_at | DATETIME | |

### clients
`id, name, vat_or_tax_id, email, phone, address, notes, created_at` — company registry.
Deleting a client **cascades** to its projects (and from there to interventions/photos).

### projects
| Column | Notes |
|--------|-------|
| client_id | FK → clients, CASCADE |
| name, location | |
| lat, lng | DECIMAL(10,7), NULL — reserved for future GPS features |
| start_date (NOT NULL), end_date (NULL) | |
| invoice_reference | free text |
| status | ENUM `active` / `on_hold` / `closed` |

### interventions
| Column | Notes |
|--------|-------|
| project_id | FK → projects, CASCADE |
| assigned_worker_id | FK → users, NULL, SET NULL |
| title, description | |
| scheduled_date, scheduled_start_time | drive the worker "today" list & admin dispatch filters |
| status | ENUM `pending`/`in_progress`/`on_hold`/`completed`/`cancelled`, DEFAULT pending |
| started_at | set once, on first transition to `in_progress` (COALESCE guard) |
| completed_at | set by completion |
| client_signature_path | relative path under storage/uploads |
| completion_notes | worker-entered |

Indexes: `project_id`, `assigned_worker_id`, `scheduled_date`.

### intervention_status_history
Append-only audit of every transition: `intervention_id`, `from_status` (NULL on
creation), `to_status`, `changed_by` (FK users, RESTRICT), `changed_at`.

### warehouse_items
| Column | Notes |
|--------|-------|
| name | |
| sku | NULL, UNIQUE |
| unit | ENUM `pcs`/`kg`/`m`/`l`/`box` |
| qty_in_stock | DECIMAL(12,3) — **cached** balance at the **main warehouse** (location 1); see ledger |
| unit_cost | DECIMAL(12,4), NULL — v2 (migration 003); accountant export / S.A.L. pricing |
| reorder_level | DECIMAL(12,3) — low-stock threshold |
| is_active | inactive items cannot be planned on new interventions |

### intervention_materials
Planned/used materials per intervention: `intervention_id` (CASCADE), `item_id`
(RESTRICT), `qty_planned`, `qty_used` (NULL until completion), `is_reserved`
(1 while stock is held by an open intervention).

### stock_movements — the inventory ledger
| Column | Notes |
|--------|-------|
| item_id | FK → warehouse_items, RESTRICT |
| location_id | FK → stock_locations, RESTRICT — **v2**; DEFAULT 1 (main warehouse). Pre-v2 rows backfilled to 1 |
| type | ENUM `in`/`out`/`reserve`/`release`/`adjustment`/`transfer_in`/`transfer_out` (last two v2) |
| qty | DECIMAL(12,3); `adjustment` carries its own sign, others are positive |
| intervention_id | NULL for manual movements, SET NULL on intervention delete |
| user_id | who caused the movement (RESTRICT) |
| note, created_at | |

### photos
`intervention_id` (CASCADE), `project_id` (CASCADE), `type` ENUM
`before`/`during`/`after`, `file_path`, `thumb_path` (NULL when GD failed),
`uploaded_by` (RESTRICT), `created_at`. Files live in
`storage/uploads/{project_id}/{intervention_id}/` and are **only** served through
permission-checked controllers, never as static files.

### login_attempts (migration 002)
Login throttling + authentication audit: `email`, `ip`, `succeeded`,
`attempted_at`; indexed on (email, attempted_at) and (ip, attempted_at).
`LoginRateLimiter` blocks after 5 failures/15 min per email (any IP) or 20 per
IP; a successful login deletes that email's failure rows.

### migrations
Bookkeeping table written by `database/migrate.php` (filename + applied_at);
each `database/migrations/*.sql` file is applied once, in filename order. The runner
splits on `;`+newline, strips only full-line `--` comments, and does **not** wrap a
file in a transaction (DDL auto-commits) — so each statement must be `;`-newline
terminated and comments kept on their own lines.

## v2 tables (multi-site + platform schema, migrations 003–009)

Most of these back features that land in later PRs; the schema is created up-front so
every phase builds on stable tables. Wired and used **now**: `stock_locations`,
`stock_balances`, and `warehouse_items.unit_cost`.

| Table (migration) | Purpose |
|-------------------|---------|
| `stock_locations` (003) | `id, name, kind ENUM('warehouse','site'), project_id NULL (CASCADE), is_active`. Row **id=1** is the main warehouse; every project gets one `site` row (its cantiere). |
| `stock_balances` (003) | Per-`(item_id, location_id)` cached balance, `UNIQUE(item_id, location_id)`. The location-scoped analogue of `qty_in_stock`; recomputed from the ledger, never written without a movement. |
| `subcontractors` (004) | `name, vat_or_tax_id, email, phone, notes, is_active`. |
| `project_subcontractors` (004) | M:N `project_id`↔`subcontractor_id`, `UNIQUE`. |
| `site_attendance` (005) | Badge di Cantiere: `project_id, user_id?/subcontractor_id?, person_name, entry_at, exit_at?, entry/exit lat/lng, note`. |
| `daily_logs` / `equipment` / `daily_log_equipment` (006) | Giornale dei Lavori: one row per `(project_id, log_date)` with weather/temps/workers/work, an `is_closed` lock, and an equipment join. |
| `sal_documents` / `sal_lines` (007) | S.A.L.: numbered progress documents (`draft`/`issued`/`signed`) with priced line items. |
| `compliance_documents` (008) | Scadenzario Sicurezza: polymorphic `(subject_type, subject_id)`, `doc_type` (DURC/POS/PSC/patente_crediti/…), `issue_date`, `expiry_date` (indexed), `credits`, `file_path`. |
| `photos.lat/lng/captured_at` (009) | Geolocated photo evidence columns. |

## Inventory: ledger semantics (the hard part)

`qty_in_stock` is a cache of the **main-warehouse (location 1)** balance; `stock_balances`
caches every `(item, location)` balance the same way. The truth is `SUM` over
`stock_movements` filtered by location, with this **sign convention** (implemented in
`WarehouseItemModel::recomputeStock()` for the warehouse and `StockBalanceModel::recompute()`
per location):

| type | weight | meaning |
|------|--------|---------|
| `in` | +qty | restock / initial load |
| `reserve` | −qty | stock held when an intervention is created |
| `release` | +qty | reserved stock returned (cancellation, or unused surplus at completion) |
| `adjustment` | +qty (signed) | manual correction; negative value = write-down |
| `transfer_in` | +qty | stock arriving at a location (v2) |
| `transfer_out` | −qty | stock leaving a location (v2) |
| `out` | **0** | audit trail of actual consumption |

`out` is intentionally weight-0: the physical stock was already decremented by the
matching `reserve` at creation; at completion, `release` of `(qty_planned − qty_used)`
corrects the net effect down to exactly −qty_used. `out` rows exist so that reports
can total *actual* material usage per project (`StockMovementModel::usedByProject`).

**Transfers** are a paired write — `transfer_out` at the source location plus
`transfer_in` at the destination, of equal qty — so an item's grand total across all
locations is conserved. `StockTransferService::transfer()` runs the pair in one
transaction, locks the `warehouse_items` row `FOR UPDATE` (serialising every movement
for that item), guards the source balance against going negative (unless
`ALLOW_NEGATIVE_STOCK`), and refreshes both location balances. **Interventions** reserve
and consume at the main warehouse by default (`InterventionService::create/complete`
take an optional `locationId`, default 1), so v1 dashboard/low-stock logic is unchanged.

> **v2 fix:** `complete()` now emits the surplus `release` **only for materials that were
> actually reserved** (`is_reserved = 1`). Previously it released `(qty_planned − qty_used)`
> for every material row, so a never-reserved row (e.g. imported/seeded with `is_reserved=0`
> and no offsetting `reserve` movement) added phantom stock. This mirrors the cancel path,
> which only releases `reservedForUpdate` rows.

**Lifecycle example** (item starts at 100, plan 20, use 15):

| event | movement | qty_in_stock |
|-------|----------|--------------|
| seed | in 100 | 100 |
| intervention created | reserve 20 | 80 |
| completion (used 15) | out 15 (w=0) + release 5 | 85 |

Net: 100 − 15 = 85 ✔. Cancellation instead writes `release 20` → back to 100.

**Concurrency**: every stock read inside these transactions uses
`SELECT … FOR UPDATE`; multi-item operations lock items in ascending id order to
avoid deadlocks. **Reconciliation**: admin action recomputes the cache from the
ledger and reports drift (`POST /admin/warehouse/{id}/reconcile`).

**Overflow guard**: MySQL without `STRICT_TRANS_TABLES` silently clamps
out-of-range DECIMALs; `Validate::isQty()` enforces the `DECIMAL(12,3)` ceiling
(`999999999.999`) on every input and computed total.

## Intervention status state machine

```
pending ──→ in_progress ──→ completed   (terminal)
   │             │  ↑
   │             ↓  │
   │          on_hold
   │             │
   └──────┬──────┘
          ↓
      cancelled                          (terminal)
```

- Enforced server-side in `InterventionService` (`ALLOWED_TRANSITIONS`).
- `completed` is reachable **only** via `InterventionService::complete()` which
  enforces the completion gate: ≥1 `after` photo AND `qty_used` set for every
  linked material. Signature optional.
- Every transition appends to `intervention_status_history`; `started_at` is set
  on the first `in_progress`, `completed_at` on completion.
- Cancellation of a non-completed intervention releases all reserved quantities.
- Workers can only transition interventions assigned to them, and the worker UI
  exposes only `in_progress`/`on_hold`/`cancelled` (+ the complete flow).

## Extension points designed in

- `projects.lat/lng` — GPS check-in later.
- `StorageInterface` — S3-compatible object storage drop-in.
- Schema tolerates labor-hours tracking later (worker rate × time) without
  repainting: new tables would reference `interventions`/`users`.


## Addendum — new tables (2026-07-08, migrations 010–014)

- `project_workers(project_id, user_id)` — roster of operai per cantiere (M:N).
- `project_documents` — per-project file attachments (served through the permission-checked controller, never statically).
- `project_invoices(number, issue_date, amount, status[draft|issued|paid], note)` — billing rows linked to a project.
- `project_materials(item_id, qty, note)` — informational per-project material log; **not** part of the stock ledger (does not move `stock_movements` / `qty_in_stock`).
- `project_absences(project_id, user_id, absence_date)` — absence-by-default site attendance register (only exceptions stored).
- `quotes` + `quote_lines` — estimates with line items (`vat_rate`, status draft|sent|accepted|rejected|expired), printable to PDF, convertible to a `project_invoices` row.
- `expenses(expense_date, category[meals|fuel|vehicle|clothing|other], amount, worker_id?, project_id?)` — running costs outside materials.

All foreign keys target existing `clients` / `projects` / `users` / `warehouse_items`.


## Addendum — automation + indexing (2026-07-10, migrations 015–016)

### 015 — performance indexes (no new tables)
Composite/status indexes for the filters and ledger-recompute paths that previously
table-scanned:
- `interventions(status)`, `interventions(completed_at)`
- `stock_movements(item_id, location_id)`, `stock_movements(created_at)`
- `project_invoices(status)`, `sal_documents(status)`

### 016 — `notifications` (admin alert feed)
| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT UNSIGNED PK | |
| type | ENUM `compliance_expiry`/`quote_expired`/`intervention_overdue`/`low_stock`/`system` | |
| severity | ENUM `info`/`warning`/`danger` | drives the row stripe/colour |
| title, body, link | VARCHAR | `link` is a relative URL into the relevant page |
| dedup_key | VARCHAR(190), **UNIQUE** (nullable) | idempotency key — the scheduler `INSERT IGNORE`s, so one row per logical event |
| is_read, read_at | TINYINT / DATETIME | |
| created_at | DATETIME | |

Indexes: `UNIQUE(dedup_key)`, `(is_read, created_at)`. The feed is **global to the
admin role** (a small firm shares one operational view); rows are generated by
`SchedulerService` (see [ARCHITECTURE.md](ARCHITECTURE.md)), never by request handlers.
`dedup_key` grammar: `compliance:{id}:{expiry_date}`, `quote_expired:{id}`,
`intervention_overdue:{id}:{scheduled_date}`, `low_stock:{id}:{YYYY-MM}` (the last
re-alerts at most monthly per item).
