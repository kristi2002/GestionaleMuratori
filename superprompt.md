# MASTER PROMPT — Field Service & Construction Management System

> Paste this whole thing into Claude Code as the first message. It is written for a **PHP 8.2+ / PDO / MySQL 8** backend with a **vanilla JS + jQuery/AJAX** mobile-first frontend and **mPDF** for reports. If you want Node/Express + React instead, only the "Stack & Conventions" and frontend sections change — the data model and business logic below are stack-agnostic and stay the same.

---

## 0. How I want you to work (read first)

You are a senior full-stack developer and software architect. We are building a real production system, not a demo.

- **Build in the phases listed in §7, in order.** Finish, run, and self-verify each phase before starting the next. Do not scaffold the whole app at once.
- After each phase, give me a 3-line summary: what you built, how to test it, what's next.
- **Ask me before** making any decision that's hard to reverse: auth library choice, table renames, anything multi-tenant. Otherwise proceed and state assumptions inline.
- Prefer boring, readable code over clever code. No frameworks beyond what's listed. No ORMs — raw PDO with prepared statements only.
- Every DB write that touches stock or status MUST run inside a transaction.
- Write a short `README.md` with setup steps and seed-data instructions, and keep it updated.

## 1. What the system does

A company manages construction sites and on-site technical interventions. The system tracks work progress, a mobile workforce, real-time warehouse inventory, and generates client-facing reports. Three roles, three experiences:

- **Admin** — desktop dashboard. Full CRUD on Clients, Projects, Interventions, Warehouse. Assigns work, manages stock, exports reports.
- **Worker** — minimalist mobile web app. Sees only "My Tasks Today," changes intervention status, records actual materials used, uploads before/during/after photos, captures client signature on completion.
- **Client** — read-only mobile/desktop view of *their* projects only. Sees before/after photos and downloads PDF reports.

## 2. Stack & conventions

- **Language: all user-facing text is in Italian** — every UI label, button, menu, validation message, status name, and all PDF/Excel report text. Keep code, comments, variable names, and DB column names in English. Don't translate the ENUM *values* in the schema (e.g. status stays `in_progress` in the DB) — map them to Italian labels in the view layer (e.g. `in_progress → In corso`). Use a single `lang/it.php` array for UI strings so nothing is hardcoded.
- PHP 8.2+, PDO, MySQL 8 (InnoDB, utf8mb4). No ORM.
- Frontend: server-rendered pages + jQuery/AJAX for SPA-like updates. Mobile-first card UI. Minimal CSS (Bootstrap 5 is fine), green accent for status/success.
- PDF: **mPDF**. Excel export: **PhpSpreadsheet**.
- Auth: session-based login, password hashing with `password_hash()`. Role + (for clients) `client_id` stored on the user.
- Photos: stored on disk under `/storage/uploads/{project_id}/{intervention_id}/`, served via a controller that checks permissions. Generate a thumbnail on upload. **Abstract storage behind a `StorageInterface`** so we can swap to S3 later without touching controllers.
- All API responses JSON: `{ ok: bool, data?, error? }`. All input validated server-side.
- Folder layout: `/public` (entry + assets), `/src` (controllers, models, services), `/views`, `/storage`, `/config`.

## 3. Data model (authoritative — build exactly this)

```
users            (id, name, email UNIQUE, password_hash, role ENUM['admin','worker','client'],
                  client_id NULL FK->clients, is_active, created_at)

clients          (id, name, vat_or_tax_id, email, phone, address, notes, created_at)

projects         (id, client_id FK, name, location, lat NULL, lng NULL,
                  start_date, end_date NULL, invoice_reference NULL,
                  status ENUM['active','on_hold','closed'], created_at)

interventions    (id, project_id FK, assigned_worker_id NULL FK->users,
                  title, description, scheduled_date NULL, scheduled_start_time NULL,
                  status ENUM['pending','in_progress','on_hold','completed','cancelled'] DEFAULT 'pending',
                  started_at NULL, completed_at NULL,
                  client_signature_path NULL, completion_notes NULL, created_at)

intervention_status_history
                 (id, intervention_id FK, from_status NULL, to_status,
                  changed_by FK->users, changed_at)

warehouse_items  (id, name, sku NULL UNIQUE, unit ENUM['pcs','kg','m','l','box'],
                  qty_in_stock DECIMAL(12,3) DEFAULT 0, reorder_level DECIMAL(12,3) DEFAULT 0,
                  is_active, created_at)

intervention_materials
                 (id, intervention_id FK, item_id FK->warehouse_items,
                  qty_planned DECIMAL(12,3), qty_used DECIMAL(12,3) NULL,
                  is_reserved BOOL DEFAULT 0)

stock_movements  (id, item_id FK, type ENUM['in','out','reserve','release','adjustment'],
                  qty DECIMAL(12,3), intervention_id NULL FK, user_id FK,
                  note NULL, created_at)
                 -- this ledger is the source of truth; qty_in_stock is a cached running total

photos           (id, intervention_id FK, project_id FK, type ENUM['before','during','after'],
                  file_path, thumb_path, uploaded_by FK->users, created_at)
```

Relations are strict: `Client 1→N Projects 1→N Interventions`. Add appropriate FK constraints and indexes (FKs, `interventions.assigned_worker_id`, `interventions.scheduled_date`, `stock_movements.item_id`).

## 4. The hard business logic — get these exactly right

### 4.1 Inventory is a ledger, not a number
`warehouse_items.qty_in_stock` is a **cached** running total. Every change to stock writes a row in `stock_movements`. Provide a method that can recompute `qty_in_stock` from the ledger for reconciliation. Restocking = an `in` movement. Manual corrections = `adjustment`.

### 4.2 Reservation → commit flow (this is the core feature)
- **On intervention create**, admin picks materials with `qty_planned`. For each, write a `reserve` movement and decrement `qty_in_stock`, set `is_reserved=1`. Reject creation if stock is insufficient (configurable: allow negative or block — default **block**).
- **On worker completion**, the worker confirms `qty_used` per material (defaults to `qty_planned`, editable). Then in **one transaction**:
  1. For each material: write an `out` movement for `qty_used`, and a `release` movement for `(qty_planned − qty_used)` if positive (returns unused reserved stock).
  2. Recompute affected items' `qty_in_stock`.
- **On cancellation** of a non-completed intervention: write `release` movements for all reserved quantities and restore stock.
- All stock reads inside these transactions use `SELECT ... FOR UPDATE` to prevent race conditions when two interventions complete simultaneously.

### 4.3 Status state machine (enforce server-side)
Allowed transitions only:
```
pending     → in_progress, cancelled
in_progress → on_hold, completed, cancelled
on_hold     → in_progress, cancelled
completed   → (terminal)
cancelled   → (terminal)
```
Every transition writes an `intervention_status_history` row, sets `started_at` on first `in_progress`, `completed_at` on `completed`. Reject illegal transitions with a clear error. A worker can only transition interventions assigned to them.

### 4.4 Completion gate
An intervention cannot move to `completed` unless: at least one `after` photo exists AND `qty_used` is set for every linked material. (Make the signature optional but supported.)

## 5. Reporting (1-click for Admin)
Endpoint: project report as **PDF (mPDF)** and **Excel (PhpSpreadsheet)**. Contents:
- Header: client, project name, location, dates, invoice reference, company logo placeholder.
- Table of interventions: title, dates, worker, final status.
- Materials used across the project: item, unit, total `qty_used` (from ledger `out` movements, not planned).
- Photo section: before/after grid per intervention (embed thumbnails, scale to fit).
- Client signature image if present, and totals.
Make the PDF a clean printable A4 layout.

## 6. RBAC (enforce on every endpoint, server-side)
- **Admin**: everything.
- **Worker**: read own assigned interventions + their parent project (read-only); write status, `qty_used`, photos, signature on own interventions only. No warehouse, no client data beyond what's needed for the task.
- **Client**: read-only, filtered to `WHERE projects.client_id = session.client_id`. Can download reports for own projects only. Never sees stock, costs, or other clients.
Centralize this in a middleware/guard; never trust the frontend.

## 7. Build order (phases — do in sequence)

1. **Foundation**: project skeleton, config, PDO connection, migrations for all tables in §3, seed script (1 admin, 2 workers, 2 clients, 5 projects, 10 warehouse items, sample interventions). README setup.
2. **Auth + RBAC**: login/logout, session, role guard middleware, the three role landing pages (empty shells).
3. **Admin CRUD**: Clients, Projects, Warehouse items (with `in`/`adjustment` movements + ledger view). Plain tables, modals for create/edit.
4. **Interventions + reservation logic** (§4.1, §4.2 create side, §4.3): admin creates interventions, assigns worker, picks materials, reservations decrement stock.
5. **Worker mobile app**: "My Tasks Today" (filter by `scheduled_date` = today + assigned to me), status transitions, `qty_used` entry, photo upload with thumbnails, signature capture (canvas → PNG). Completion runs §4.2 commit + §4.4 gate.
6. **Client view**: read-only project list, before/after gallery, report download.
7. **Reporting**: PDF + Excel per §5.
8. **Polish**: input validation pass, error states, the reconciliation method for the ledger, and a basic offline-friendly upload (queue failed uploads in `localStorage`, retry on reconnect) — see §8.

## 8. Notes & nice-to-haves (mention if you have spare capacity, don't block on them)
- **Offline**: workers often have no signal on site. At minimum, compress images client-side before upload and retry failed uploads. A full PWA/service-worker queue is a stretch goal — flag it, don't build it unless I ask.
- **Scheduling**: `scheduled_date` already exists; a simple "today / this week" dispatch list for admin is a cheap win.
- **GPS/time check-in** and **labor hours** (worker rate × time) are out of scope for v1 but design the schema so they can be added (don't paint us into a corner).
- Keep `StorageInterface` clean so S3 is a drop-in later.

## 9. Quality bar / acceptance
Before you call a phase done, confirm:
- It runs with no PHP warnings on the seed data.
- Stock math is correct after a full create→complete cycle (qty_in_stock matches the ledger sum).
- Two interventions completing on the same item don't corrupt stock (transaction + FOR UPDATE).
- A worker cannot touch another worker's intervention; a client cannot see another client's project (test by switching sessions).
- Illegal status transitions are rejected.
- The PDF opens and shows real seeded data including photos.

Start with **Phase 1** now. Ask me anything blocking before you begin.
