# Data model

Source of truth: [database/migrations/001_init.sql](../database/migrations/001_init.sql).
All tables InnoDB, utf8mb4_unicode_ci. ENUM values stay English; the view layer
translates them via `lang/it.php` (`Lang::label`).

Relations are strict: **Client 1→N Projects 1→N Interventions**.

## Tables

### users
| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT UNSIGNED PK | |
| name | VARCHAR(190) | |
| email | VARCHAR(190) UNIQUE | login identifier |
| password_hash | VARCHAR(255) | `password_hash()` (bcrypt/argon per PHP default) |
| role | ENUM admin/worker/client | |
| client_id | FK → clients, NULL | only for role=client (`ON DELETE SET NULL`) |
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
| qty_in_stock | DECIMAL(12,3) — **cached** running total (see ledger) |
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
| type | ENUM `in` / `out` / `reserve` / `release` / `adjustment` |
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
each `database/migrations/*.sql` file is applied once, in filename order.

## Inventory: ledger semantics (the hard part)

`qty_in_stock` is a cache. The truth is `SUM` over `stock_movements`, with this
**sign convention** (implemented in `WarehouseItemModel::recomputeStock()`):

| type | weight | meaning |
|------|--------|---------|
| `in` | +qty | restock / initial load |
| `reserve` | −qty | stock held when an intervention is created |
| `release` | +qty | reserved stock returned (cancellation, or unused surplus at completion) |
| `adjustment` | +qty (signed) | manual correction; negative value = write-down |
| `out` | **0** | audit trail of actual consumption |

`out` is intentionally weight-0: the physical stock was already decremented by the
matching `reserve` at creation; at completion, `release` of `(qty_planned − qty_used)`
corrects the net effect down to exactly −qty_used. `out` rows exist so that reports
can total *actual* material usage per project (`StockMovementModel::usedByProject`).

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
