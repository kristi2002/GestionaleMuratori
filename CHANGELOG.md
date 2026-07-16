# Changelog

## 2026-07-16 — Deployment-readiness pass, Phase 5: scheduling & dispatch

Turn the flat intervention list into a workload command-centre. No schema change.
Suite **589 green**.

- **Dispatch board** — `GET /admin/interventions/dispatch`: active (non-completed)
  scheduled interventions for a 7-day window (`?from=`, week paging), grouped by worker
  then day, each worker card showing their **load** count. Unassigned work has its own
  bucket. `InterventionModel::dispatchBetween()` backs it.
- **Double-booking detection** — any worker with 2+ jobs on the same day is flagged
  inline ("Più interventi nello stesso giorno"), computed from per-(worker,date) counts.
- **Quick reassignment** — a per-row worker `<select>` posts to
  `POST /admin/interventions/{id}/reassign` (`worker_id` 0 = unassign), validating the
  target is actually a worker; the board reloads to regroup and re-flag.
  `InterventionModel::reassign()` + a small `.js-reassign` handler.
- **Discoverability** — the interventions sidebar submenu now leads with **Piano di
  lavoro** (dispatch) and **Calendario**, above the status filters.
- **Tests** — dispatch RBAC (worker 403 / admin 200), reassign persistence, non-worker
  rejection (422), unassign-to-NULL, and worker-cannot-reassign, in case 19.
  Service worker `v25 → v26` (app.js changed).

## 2026-07-16 — Deployment-readiness pass, Phase 4: client self-service

Give the client portal its own voice: an in-app notification feed and read-only
visibility of what's been billed. Suite **582 green**.

- **User-scoped notifications (migration 023)** — `notifications.user_id` (nullable
  FK → `users`, `ON DELETE CASCADE`, indexed with `is_read`). NULL preserves the
  admin/global feed exactly (scheduler + existing rows unchanged); a non-NULL id
  addresses one user. `NotificationModel` gained a `?int $userId` scope on every
  read/mark method (default null = global), so a client can only ever see or mark
  their own rows.
- **Client notification feed** — `Client\NotificationController`
  (`/client/notifications`, `…/read-all`, `…/{id}/read`), a client feed view, and the
  topbar **bell now shows for clients** (their own unread count) as well as admins.
- **Event fan-out** — `App\Services\NotificationService::notifyClient()` creates one
  notification per active portal user of a client (dedup_key suffixed with the user id
  so the global-UNIQUE constraint dedups per recipient). Wired into the Phase 2 events:
  sending a quote / issuing an invoice now e-mails **and** rings the client's bell.
  Added `UserModel::clientUserIds()`; `ProjectInvoiceModel::findWithDetails` now also
  returns `client_id`.
- **Read-only billing for the client** — the client project page lists that project's
  **issued/paid** invoices (number, date, amount, status). Drafts are never shown.
- **Tests** — case 19 gains per-user scoping (ownership on mark-read), the client feed
  RBAC + invoice fan-out integration, and the draft-hidden / issued-visible invoice
  checks.
- **Scope note** — client document center, project-progress timeline, and quote
  e-signature remain candidates for a later client-portal iteration.

## 2026-07-16 — Deployment-readiness pass, Phase 3: invoicing automation

Automate the invoice-creation drudgery, staying inside the Italian construction
billing model (progress billing against an approved S.A.L. — not subscriptions).
Suite **567 green**.

- **S.A.L. → draft invoice** — `SalController::toInvoice`
  (`POST /admin/sal/{id}/invoice`) turns an *issued or signed* Stato Avanzamento
  Lavori into a **draft** `project_invoices` row: auto-numbered
  (`nextNumberSuggestion`, per-year sequential), today's date, amount copied from the
  S.A.L., note back-referencing the S.A.L. number. Draft on purpose — the admin
  reviews, then issues through the normal flow, and *issuing* is what e-mails the
  client (Phase 2). A "Genera fattura" button appears on the S.A.L. page once the
  document leaves draft. Mirrors the existing `QuoteController::toInvoice` pattern.
- **Automatic invoice numbering** — verified already present:
  `ProjectInvoiceModel::nextNumberSuggestion()` (MAX+1 within the current year,
  gap-free-forward) already pre-fills the invoice create form.
- **Tests** — `tests/cases/15_sal_http.php` gains the conversion path: RBAC (worker
  403), draft-S.A.L. guard (422), draft-status/amount/note assertions.
- **Deliberately deferred** — *recurring invoices* (construction billing is
  milestone/S.A.L.-based, not subscription — poor domain fit) and *FatturaPA/SDI XML*
  (the project's own stance, per the 2026-07-16 purchase-orders note, is that the
  commercialista + SDI handle electronic invoicing). Both await an explicit need.

## 2026-07-16 — Deployment-readiness pass, Phase 2: transactional e-mail live

The `Mailer` (SMTP/`mail`, built earlier) was only wired to the daily alert digest;
the password-reset link already used it too. This phase makes e-mail an event-driven
channel for the client-facing document flow. Still off until `MAIL_ENABLED=true` + the
`MAIL_*` vars are set — everything degrades to a silent no-op. Suite **560 green**.

- **`App\Services\MailService`** — transactional (event-driven) e-mail, distinct from
  the scheduler digest. Pure, unit-tested `build*()` methods (branded HTML shell,
  localized via `lang/it.php` `mail.*`) + thin send wrappers that gate on
  `Mailer::isEnabled()` and a valid recipient. Messages: **quote sent to client**,
  **invoice issued to client**, and an **admin test e-mail**.
- **Wiring (best-effort, after commit)** — `QuoteController` e-mails the client on the
  draft→`sent` transition (create or update), `InvoiceController` on the →`issued`
  transition; each only fires on the actual transition, never on later edits, and is
  wrapped so a mail failure can never break the request (`Logger::exception` on error).
  Recipient is the row's own `client_email` (already joined by `QuoteModel::find` /
  `ProjectInvoiceModel::findWithDetails`).
- **Admin test e-mail** — `POST /admin/notifications/test-email` sends a test to the
  logged-in admin so SMTP can be verified from the UI; a button on the notifications
  page reports the outcome inline (mail-disabled → clean 422, never a 500). `Dialog.alert`
  gained an optional title arg for the success notice; new `js.notice` i18n key.
- **Tests** — `tests/cases/21_mail_service.php` (message building + disabled-gate,
  offline) and a `test-email` RBAC/behaviour section in case 19. Also fixed a **latent
  test bug**: case 19 logged in as `worker1`, whose password case 10 changes and never
  restores, so the "worker blocked" check ran against an *anonymous* client — switched to
  `worker2` (like cases 12–18) and strengthened the assertion to a real 403.
- **Scope note** — a client/worker in-app notification feed (user-scoped notifications)
  moves to Phase 4 (client self-service, where its UI lives); per-user e-mail
  preferences deferred (client-facing mail targets the client-company address, not a
  user row, so a per-user toggle maps poorly — revisit with multi-tenancy).

## 2026-07-16 — Deployment-readiness pass, Phase 1: deploy hardening

- **Compliance orphan fix** — `compliance_documents` uses a polymorphic
  `subject_type`/`subject_id` with no foreign key, so deleting a project used to
  leave dangling Scadenzario rows whose subject no longer resolved. Added
  `ComplianceDocumentModel::deleteForSubject()` and made `ProjectController::destroy`
  run the project delete + compliance cleanup in one transaction. Project is the only
  deletable compliance subject today (workers/subcontractors are deactivated, not
  deleted; clients aren't a compliance subject). Covered by
  `tests/cases/09_compliance.php` (3 new assertions; suite **544 green**).
- **Deployment docs** — `DEPLOYMENT_COOLIFY.md` now documents nightly
  `scripts/backup.sh` as a second Coolify Scheduled Task (with the off-site-copy and
  tested-restore caveats), and a prominent **single-replica constraint** (file-based
  sessions + local `uploads` volume) with the rationale and the scale-up-not-out
  guidance.

## 2026-07-16 — Deployment-readiness pass, Phase 0: docs sync + hygiene

Opening phase of the Hetzner/Coolify production-readiness effort. No behavior, route,
or schema change — this pass makes the repo tell the truth and removes dead weight so
the following feature phases build on a clean base. Full suite **541 green** throughout.

- **JS i18n compliance** — every user-facing Italian literal in `public/assets/js/app.js`
  (dialog/confirm labels, login progress, delete/remove buttons, error + offline notices)
  now routes through the existing `GM.t(key, fallback)` bridge. The layout injects a
  `<script id="gm-i18n">` dictionary built from `lang/it.php` (new `js.*` group + reused
  `common.*`/`auth.*`/`attendance.offline_queued` keys), so the "no hardcoded Italian in
  JS" rule finally holds and future locales are unblocked. Behavior is byte-identical
  (fallbacks equal the former literals).
- **PWA polish** — `manifest.webmanifest` `theme_color`/`background_color` corrected from
  the retired green (`#2e7d32`/`#fff`) to the Navy shell (`#080D1A`/`#0A0F1E`) to match the
  layout meta. Service worker bumped `gm-shell-v23 → v24` (JS/manifest changed).
- **Repo hygiene** — removed six committed `*.desktop-orig*.bak` leftovers from the
  desktop→muratori redesign (`views/layout`, `views/admin/projects/index`,
  `ProjectController`, `ProjectModel`, `app.css`, `app.js`).
- **Docs re-synced to reality** — README, `docs/TESTING.md`, `docs/DEPLOYMENT_COOLIFY.md`,
  `docs/ARCHITECTURE.md`, `docs/API.md`, `docs/ROADMAP.md` corrected from the stale
  "451 assertions / migrations 001–016" baseline to the current **541 assertions /
  migrations 001–022**, and now list the shipped-but-undocumented modules (suppliers,
  purchase orders + DDT, audit log, password reset, profile fields, project notes,
  keyboard shortcuts) plus `PurchaseOrderReceiptService` and case `20`. Dated CHANGELOG
  milestone counts left intact as historical record.

## 2026-07-16 — Buoni d'Ordine (purchase orders) + suppliers

First supplier-facing document set — the app's document layer previously pointed only
at clients, and inbound stock had no document behind it. Shipped as two commits: the
CRUD/PDF layer, then the stock-writing receipt layer.

- **Schema (migration 022)** — `suppliers` (fornitori, separate from subcontractors),
  `purchase_orders` + `purchase_order_lines` (line `item_id` nullable so non-stock
  lines can be ordered too), and a `stock_movements.purchase_order_line_id` column that
  ties inbound `type='in'` movements to their ordering document. PO header carries a
  `project_id` from day one for per-cantiere cost reporting.
- **CRUD + PDF** — `SupplierController` / `PurchaseOrderController` (admin-only),
  list/form views with the shared `page_head` + KPI + pill-filter kit, printable A4
  order PDF, sidebar + quick-create nav, IT strings, seed suppliers & sample orders.
- **Receiving (DDT)** — `PurchaseOrderReceiptService` books a delivery as one
  `type='in'` movement per line (one transaction, items locked `FOR UPDATE` in
  ascending id order, caches refreshed from the ledger). `qty_received` is never
  stored — always summed from the ledger; header status is derived (partial → received).
  Partial deliveries accumulate; over-receipt is warned not blocked; a PO with any
  delivery is locked against edit/delete.
- **Stock valuation** — `warehouse_items.unit_cost` is deliberately **not** overwritten
  on receipt (blind overwrite would corrupt historical valuation and distort S.A.L.
  margins); Weighted Average Cost is deferred to a later phase. Supplier-invoice
  reconciliation is out of scope (Italian SDI + commercialista handle it).
- **Tests** — `tests/cases/20_purchase_orders.php` covers receipt math vs the ledger,
  partial→full transition, over-receipt, and the receipt guards. Full suite 541 green.
  Service worker bumped `gm-shell-v22 → v23` (JS changed).

## 2026-07-14 — Full "muratori design" refresh across every page

App-wide restyle to match the `muratori design/` mockups, extending the Navy +
Orange dark shell. **Design intent only — no fabricated data:** every KPI, chart,
badge, and progress bar is backed by a real DB aggregate; mockup elements with no
schema/route backing (QR badge generator, permissions matrix, fake ratings, export
history, budget-vs-actual, etc.) were deliberately omitted rather than faked.

- **Shared component kit** (`public/assets/css/app.css`) — added theme-flipping
  `--surface-*`/`--ink-*` tokens plus reusable components: page header
  (`.app-page-title`), pill filter tabs (`.app-pill`), right detail rail
  (`.app-rail`/`.app-dl`), avatar stacks (`.app-avatars`), progress meters
  (`.app-meter`), card media headers (`.app-card-media`), horizontal stepper
  (`.app-stepper`), filled/glowing alert banners (`.app-banner*`), star ratings
  (`.app-stars`), and colored/solid KPI variants (`.gm-kpi.is-*`,
  `.gm-kpi-solid.is-*`). New partials: `page_head`, `filter_pills`. See
  `docs/DESIGN_SYSTEM.md`.
- **Admin pages** — Projects, Clients, Interventions, Warehouse, Invoices, Quotes,
  Expenses, Subcontractors, Statistics, Financials, Users, Daily Logs, S.A.L.,
  Compliance, Exports, Audit all rebuilt with `page_head` + real-data KPI rows +
  pill filters + status badges; detail pages use the main+rail layout, and the
  S.A.L. detail shows the real document-lifecycle stepper. New read-only aggregate
  methods were added to the relevant models/services to back the KPIs and charts.
- **Portals & utility pages** — Worker (tasks/attendance = "Badge di Cantiere",
  adapted to real clock-in data), Client (projects + quotes), Subcontractor,
  Notifications, Search, Shortcuts, and 403/404/500 error pages restyled to the
  same system.
- **Service worker** bumped `gm-shell-v21 → v22` (CSS changed).
- Full test suite green (526 passed); every page verified to render without PHP
  errors under its role.
- **New page — Client profile** (`GET /admin/clients/{id}`, from the *Profilo
  Cliente* mockup): identity card (contacts, note, "cliente da N anni"),
  real financial stat cards (invoiced / paid / outstanding), quick stats
  (active projects, next deadline, last payment), a 12-month invoiced line
  chart, the client's projects with real intervention-completion progress bars,
  their invoices table, and an activity timeline (invoices/quotes/projects).
  All from new read-only `ClientModel` aggregates. The Clienti list "Vedi
  profilo"/card links now open this profile (edit is reached via its Modifica
  button). Mockup's rating stars, "referente", and VIP/tags omitted (no schema).

## 2026-07-14 — Redesigned login + financials, new operaio profile

Second design pass over three mockup-driven pages:

- **Login** — split-screen: a navy brand hero (headline, feature checklist) beside
  a themed sign-in card. Form logic unchanged (AJAX login, forgot-password, demo
  creds); no fabricated features added. Hero collapses to form-only on phones.
- **Fatturazione & Preventivi** (`/admin/financials`) — rebuilt around the mockup:
  a month chip + "Nuova fattura" CTA, four KPI cards (invoiced-this-month with a
  real 12-month sparkline, collected, outstanding, margin), an **Andamento
  fatturato** bar chart, and a **Riepilogo pagamenti** panel of outstanding by
  client. New data comes from a real query on `project_invoices.issue_date` —
  nothing invented (`FinancialsService` now returns `months` + `current_month`).
- **Operaio / user profile** (`GET /admin/users/{id}`, **new page**) — identity
  card with avatar (upload + permission-checked serve, initials fallback), job
  title, tenure from hire date; vivid metric cards (hours this month, presences,
  current cantiere); a monthly attendance heatmap; assigned interventions; and
  personal compliance documents with freshness pills. All from existing tables.

Schema: migration **021** adds `users.job_title`, `phone`, `hire_date`,
`avatar_path`; the user form and validation gained those fields. Avatars are
stored via the Storage disk and streamed through `UserController::avatar`
(never static), like photos/signatures. New routes: `GET /admin/users/{id}`,
`GET|POST /admin/users/{id}/avatar`. 526 tests pass. sw.js → gm-shell-v21.

## 2026-07-13 — Visual redesign: Navy + Orange design system

Full restyle of the app shell and every page onto a deep-navy + orange identity
(mockup-driven), with no markup rewrites for most pages — the change is carried
by the CSS token layer so all `.app-*` / Bootstrap components inherit it.

- **Palette:** the brand accent moves from green to **orange** (`#F97316`). The
  legacy `--app-green*` variable names are kept as aliases (now orange) so the
  hundreds of existing usages flip with no per-view churn. Genuine *success*
  semantics (completed/valid status pills, "worked" attendance days, success
  badges) are restored to real green (`#10B981`) via new `--app-success*` tokens.
- **Navy shell:** the top bar is deep navy (`#080D1A`) in every theme; the
  sidebar is a distinct navy panel in dark mode. **Dark is now the default look**
  (page `#0A0F1E`, cards `#0D1426`, borders `#1E2A44`) to match the mockups; the
  light theme is retained as a toggle (navy header, white sidebar/cards).
- **Dashboard hero:** new warm-gradient welcome banner (localized Italian long
  date, greeting, at-a-glance chips) at the top of the admin dashboard.
- **Accents:** orange top-accent on record cards, orange KPI stripe, orange
  brand chip/logo (favicon + logo SVGs recolored), orange focus rings.
- **Charts & status colors** (statistics, financials health bars, calendar
  events) rebased on the new semantic palette (orange primary series, green =
  success, blue = in-progress, amber = on-hold, red = cancelled). Sparkline
  palette updated in `app.js`. PDF report header rebranded orange.
- **Mobile bottom navigation** (`< lg`): a fixed navy tab bar with a role-aware
  subset of the menu (admin: Home / Progetti / Sicurezza / Magazzino; worker:
  Interventi / Presenze; client: Progetti / Preventivi). Admins get a raised
  centre **＋ FAB** that opens a bottom quick-create action sheet (project,
  intervention, quote, invoice, expense). Hidden at `lg+` where the sidebar
  takes over; content gets bottom padding only on mobile.

Presentational only — no routes, schema or backend behavior changed. 526 tests pass.

## 2026-07-12 — Production hardening: pagination, auto-migrate, reset, audit

Deployment-readiness batch:
- **Pagination** on the projects, clients, subcontractors and warehouse lists
  (they previously loaded every row). CSV exports/dropdowns unaffected.
- **Auto-migrate on deploy:** `deploy/entrypoint.sh` runs `migrate.php` (idempotent,
  with DB-warmup retries) before serving — no more manual migrate step. `.sh`
  files pinned to LF via `.gitattributes`.
- **Self-service password reset** (`/forgot-password` → e-mailed single-use,
  1-hour token → `/reset-password`), no account enumeration.
- **Audit log** (`/admin/audit`): who created/updated/deleted what, wired into
  user management, deletes and invoice writes.
- (DB backup/restore already shipped in `scripts/backup.sh` + docs.)

Migrations 019 (password_resets) and 020 (audit_log) auto-apply on next deploy.
526 tests pass.

## 2026-07-12 — Platform features: global search, calendar, CSV export

- **Global search:** navbar search box (admin) + `/admin/search` results page —
  LIKE lookups across projects, interventions, clients, subcontractors and
  warehouse items, grouped with status badges and deep links.
- **Interventions calendar:** `/admin/interventions/calendar` — a Monday-first
  month grid of scheduled interventions with colour-coded event chips and a
  Calendario/Elenco toggle on the list page.
- **CSV export:** "Esporta CSV" on the clients, projects, interventions and
  expenses lists, exporting the currently-filtered rows (UTF-8 BOM + `;` for
  Italian Excel) via the new `Support\Csv` helper.

All admin-only, native (no external deps, CSP-safe). 506 tests pass.

## 2026-07-12 — Project detail: Promemoria (reminders/notes)

New **Promemoria** tab on the project page: add reminders with an optional due
date, tick them done (strike-through), and delete them. Overdue open reminders
show a red date badge; the tab shows a green count of open items.

Migration `018_project_notes.sql` (new `project_notes` table), `ProjectNoteModel`,
admin-only `storeNote`/`toggleNote`/`deleteNote` endpoints (validated, ownership-
checked, `{ok,data,error}`), reusing the existing `js-crud-form`/`js-crud-delete`
JS. Italian strings, CSS. Service worker → `gm-shell-v13`. 494 tests pass.

**Prod:** run `php database/migrate.php` in the app container to apply 018 (the
tab renders without it, but adding a reminder 500s until the table exists).

## 2026-07-12 — Project detail: interventions, subcontractors, photos, map link

Richer project (cantiere) detail page:
- **Interventi tab** (now the default): the project's interventions — title,
  worker, date, status — with a "Nuovo intervento" shortcut.
- **Subappaltatori tab:** subcontractors assigned to the project with their
  DURC/compliance status badge.
- **Foto tab:** before/during/after photo gallery across the project's
  interventions (thumbnails via the permission-checked photo controller).
- **Map link:** a CSP-safe "Apri nella mappa" link next to the location (uses the
  project's lat/lng when set, else the address).

New `PhotoModel::forProject`, extra data in `ProjectController::show`, Italian
strings, gallery CSS. Service worker → `gm-shell-v12`. 486 tests pass.

## 2026-07-12 — UI: sticky project header on the project detail page

The project's identity bar (name, status, client, location, period, workers +
Edit/PDF/Excel actions) is now a sticky context bar that docks just under the top
navbar (`position: sticky; top: --app-topbar-h`), so the key project info stays
visible while scrolling through the tabs. Made slightly more compact (h5, small
buttons). Service worker → `gm-shell-v11`.

## 2026-07-12 — DURC/compliance gating + per-cantiere financials on project page

- **Compliance gating (subappaltatori):** the subcontractors list now shows a
  document-status badge per subcontractor (In regola / In scadenza / **Scaduti**),
  computed from `compliance_documents` expiry dates (`ComplianceDocumentModel::
  statusForSubjects`), plus a red banner counting subs with expired docs (e.g.
  DURC) — verify before assigning work or paying. Read-only flagging, no new schema.
- **Financials on the project page:** each cantiere's detail page gets a summary
  card (invoiced / collected + outstanding / costs / margin with health colour),
  via `FinancialsService::forProject`, linking to the full `/admin/financials`.
- e2e tests for both. **485 tests pass.**

## 2026-07-12 — New: per-cantiere financial dashboard (`/admin/financials`)

"Andamento Economico" — cash-in vs cash-out and live margin per construction
site, the visibility feature competitors (Pillar / Edilizia in Cloud) lead with,
built natively over data already captured (no AI, no new schema):

- KPI row: total invoiced (issued+paid), collected (paid), costs, margin (+ %).
- Per-cantiere table: invoiced, collected, outstanding, costs (materials valued
  at `warehouse_items.unit_cost` + logged expenses), margin with a health colour
  (loss / thin <15% / ok) and an inline cost-incidence bar.
- New read-only `FinancialsService` (grouped queries merged in PHP to avoid
  double-counting), admin-only `FinancialsController`, sidebar entry + "R"
  shortcut, Italian strings, e2e tests. Service worker → `gm-shell-v10`.
  **481 tests pass.**

## 2026-07-12 — New: customizable keyboard shortcuts

Admins can now remap the "G-then-key" navigation shortcuts to their own keys on
the `/shortcuts` page (edit the key next to each destination, Save, or Reset to
defaults). Overrides persist per user and take effect app-wide.

- New `App\Support\Shortcuts` is the single source of truth (defaults, merge,
  validation — single letter, unique, "G" reserved); `app.js` and the editor
  build off it so they never drift.
- Migration `017_user_shortcuts.sql` adds `users.shortcuts` (JSON overrides);
  loaded into the session at login, injected into `body[data-shortcuts]` for the
  global handler. New `POST /shortcuts` endpoint (admin-only, validated,
  `{ok,data,error}`). Also added a shortcut for the new Statistiche page ("T").
- Italian strings, CSS for the editable key input, e2e tests (save/persist,
  duplicate + reserved rejected, worker blocked). Service worker → `gm-shell-v9`.
  **477 tests pass.**

## 2026-07-12 — New: statistics dashboard (`/admin/statistics`)

Read-only analytics page for admins: a KPI row (active projects, interventions
this month, low-stock items, revenue from paid invoices) plus charts —
projects / interventions / quotes / invoices by status (donuts), interventions
per month (trend bars), expenses by category and top clients (horizontal bars).

All charts are **pure inline SVG/CSS with no JavaScript library** (CSP-safe —
no CDN, matching the app's existing no-chart-lib approach). New
`StatisticsService` (plain grouped COUNT/SUM, read-only), `StatisticsController`
(admin-only), three reusable chart partials (`chart_donut`, `chart_hbars`,
`chart_vbars`), a sidebar nav entry, Italian strings, and e2e tests (admin 200 +
charts present, worker/client 403). Service worker bumped to `gm-shell-v8`.
**468 tests pass.**

## 2026-07-12 — UI: one consistent filter design across all list pages

Converted the remaining `row g-2` filter forms (clienti, subappaltatori,
magazzino, utenti, scadenzario sicurezza) to the standard `app-filter-card` +
`app-filter-grid` used elsewhere: green `btn-success` "Cerca" with a search icon,
and the shared inline "Azzera filtri" reset. Every admin list page now shares the
same filter-card design. Added hover styling to the reset link (muted → soft-green
chip), a `app-filter-check` helper for the compliance checkbox, and bumped the
service-worker cache to `gm-shell-v7`.

## 2026-07-12 — UI: unify the interventions filter with the other list pages

The interventions filter was a bare `row g-2` form with a grey outline "Cerca"
button, visually inconsistent with the card-based filters elsewhere. Wrapped it
in the standard `app-filter-card` + `app-filter-grid` (new `app-filter-grid-selects`
preset for its three dropdowns), gave "Cerca" the green `btn-success` + search
icon, moved the Oggi/settimana/Tutte range toggle inside the card, and switched
the reset link to the shared inline `filter_clear`. Added select `aria-label`s.

## 2026-07-12 — UI: "Azzera filtri" inline on the filter row

On the single-row filter pages (projects, quotes, invoices) the reset-filters
link now sits inline at the end of the row, right after "Cerca", filling the
grid's previously-empty trailing column (removing the dead gap). The
`partials/filter_clear` partial gained an `inline` flag; interventions keeps its
inline `col-auto` reset; expenses (a wrapping multi-row filter) keeps the
right-aligned link below the row. Bumped the service-worker cache to
`gm-shell-v6` so the CSS tweak isn't served stale.

## 2026-07-12 — CI + storage driver factory

- **CI (`.github/workflows/ci.yml`):** the full suite (unit + service + HTTP e2e,
  462 tests) now runs on every push/PR against a MySQL 8 service with PHP 8.2 and
  the app's extensions — regressions like the production PDF 500 are caught before
  deploy. Added a status badge to the README.
- **Storage factory (`App\Support\Storage\Storage::disk()`):** the six call sites
  that hard-wired `new LocalStorage(...)` now resolve the driver from config
  (`STORAGE_DRIVER`, default `local`). This makes the existing `StorageInterface`
  promise real — uploads can move to S3 (ADR-0001 Phase 1, a prerequisite for
  horizontal scale) by adding one factory case, with no call-site changes.
  Behavioural no-op today; regression tests in `tests/cases/01_unit.php`.

## 2026-07-12 — Observability: structured error logging + optional alerting

Uncaught 500s were written as a single free-text log line and nothing else, so a
production error was effectively invisible (the 2026-07-11 PDF incident alerted
no one). Added `App\Support\Logger`:

- Every uncaught exception is now logged as **one structured JSON line** prefixed
  `gm ` (type, message, file:line, request method/path, user id, trace) — greppable
  in the container's stderr.
- Each request gets a short **correlation id**, shown to the user on the error page
  (`errors.reference`) and included in the log line, so a user report maps straight
  to a log entry.
- Optional **webhook alerting** (`ALERT_WEBHOOK_URL`, Slack/Discord/Teams-style),
  off by default, best-effort, throttled per error signature (`ALERT_MIN_INTERVAL`).
  All logging/alerting is guarded — it never throws and never masks the original error.
- Regression tests in `tests/cases/01_unit.php`. **462 tests pass.**

## 2026-07-11 — Fix: every PDF (report/invoice/quote/S.A.L.) 500s in production

All PDF endpoints returned **500** on the production container (e.g.
`/admin/projects/{id}/report/pdf`). Root cause: mPDF's default scratch dir is
`vendor/mpdf/mpdf/tmp`, but the image copies the repo as root and only
`chown`s `storage/` to `www-data` — so when PHP-FPM (running as www-data) had
mPDF write its font cache on the first render, it hit
`Mpdf\MpdfException: Temporary files directory ... is not writable`. The failure
was global; it looked project-specific only because the service worker was
serving a stale cached success for one project. Local dev never hit it (the dev
user owns `vendor/`).

- New `Services\Report\MpdfFactory` builds every mPDF instance with an explicit
  `tempDir` under the writable `storage/` tree (`config storage.pdf_temp_path`,
  overridable via `PDF_TEMP_PATH`); it creates the dir on first use. All four
  builders (report, invoice, quote, S.A.L.) go through it.
- `deploy/Dockerfile` pre-creates `storage/tmp/mpdf` (chowned to www-data);
  `storage/tmp/` is gitignored.
- Regression test in `tests/cases/01_unit.php` asserts the temp dir is outside
  `vendor/`, writable, and that a builder renders a valid `%PDF`. **455 tests pass.**

## 2026-07-10 — Create/edit modals → dedicated pages

Converted the admin create/edit **modals** into full **pages** (matching the
projects/quotes/invoices/expenses pattern already in place). The POST store/update
endpoints are unchanged; each page form is a `.js-crud-form` with `data-redirect`
back to the list. **Done: clients, users, subcontractors, compliance, magazzino
(warehouse items), S.A.L., giornale dei lavori** — new `.../create` (and
`.../{id}/edit` where the entity had an edit modal) GET routes + `create()`/`edit()`
controller methods + `admin/<entity>/form.php` views; index pages link to those
pages and the modal markup is removed.
- Users: password stays blank on edit (blank = unchanged); the role→linked-client
  field toggle is preserved (server-set initial state + existing `js-user-role` JS).
- Compliance: added the missing subject-type→subject-field toggle JS so the form
  works for every soggetto (operaio / subappaltatore / cantiere / impresa), not
  just the default.
- S.A.L. / giornale: create-only pages (records are edited on their show page);
  the previously-hidden project_id became a labelled cantiere selector.
- Subcontractors "assegna progetti" and warehouse movement/transfer/reconcile
  features left intact.
- **Interventi**: create page carries the planned-material editor, and in doing so
  fixes a latent bug — the modal's "Aggiungi materiale"/remove buttons never worked
  (the `js-material-add`/`js-material-remove` handlers didn't exist); they're now
  implemented with a `<template>` clone. The edit page shows the basic fields
  editable and the planned materials read-only (server-side `update()` only touches
  the basic fields — materials reserve stock and are set at creation). Verified
  end-to-end: create-with-material reserves the correct quantity.

Every admin create/edit modal is now a dedicated page. All 451 tests pass.

## 2026-07-10 — UX batch: dashboard, filters, exports, keyboard shortcuts

A phased pass over recurring UX requests, each verified in a running browser.
No schema change; one additive route (`/shortcuts`).

- **Dashboard** — removed the "Sezioni" card grid (it merely duplicated the
  sidebar) and replaced it with an **Azioni rapide** panel: one-click shortcuts to
  the common create flows (nuovo progetto / preventivo / fattura / spesa).
- **PDF/Excel download spinner** — the page-loading overlay was shown on
  `beforeunload` but a file download never unloads the page, so the spinner span
  forever (e.g. "Scarica PDF" on Preventivi). The overlay is now suppressed for
  download / new-tab / in-page links and cleared on `pageshow`/focus/visibility
  plus a safety timeout.
- **Clear-filters** — filtered list pages (progetti, preventivi, fatture, spese,
  interventi) now show an **Azzera filtri** link whenever a filter is applied,
  via a new `partials/filter_clear` partial.
- **Interventi row actions** — the per-row Modifica / Avvia / Sospendi / Annulla
  buttons no longer wrap onto two lines; they sit on one line (the table still
  scrolls inside its `.table-responsive` wrapper on narrow screens).
- **Selects & date pickers** — `<option>`/`<optgroup>` lists pick up the app
  palette (and stay legible in dark mode), and native date/month/time picker
  indicators get a pointer cursor, a soft hover chip, and a visible icon in dark
  mode.
- **Consistent primary action** — "Nuovo …" buttons moved out of the filter grid
  to the top-right of the page header on progetti / preventivi / fatture / spese,
  matching interventi / clienti; Badge di Cantiere gained the standard back button
  + breadcrumb + filter card.
- **Esportazioni** — the single accountant form became a proper "Esportazioni
  disponibili" table, adding a working **Report di cantiere** export (project
  picker → PDF/Excel, reusing the existing per-project report endpoints).
- **Keyboard shortcuts** — new `/shortcuts` guide page (topbar ⌨ button and the
  `?` key open it). `/` focuses search; `g` then a section key navigates
  (admin). Handler ignores keystrokes while typing in a field.

## 2026-07-10 — UI polish: card alignment, button placement & i18n regressions

A focused pass over card-internal alignment and button placement, done by driving
the running app in a browser (not by intuition). No behavior or schema change.

### Fixed — missing `lang/it.php` keys left by the "juli" redesign
Twelve keys resolved to their raw dotted path on real pages (e.g. buttons literally
read `common.open`, breadcrumbs `nav.dashboard`, back buttons `common.back`).
Audited every `Lang::get`/`$t` call across `views/` (566 distinct keys); the 12
missing ones now render Italian:
- Added `common.open`, `common.back`, `common.reset_filters`.
- Added `nav.dashboard`, `nav.projects`, `nav.breadcrumb` (used by the shared
  `back_button` / `breadcrumb` partials — affected ~10 pages each).
- Added `admin.interventions.filter_date_{from,to}{,_short}` (the expenses/quotes/
  invoices date-range filter).
- **Root cause of `report.pdf` / `report.excel` blanking: a duplicate top-level
  `report` key** — the second literal silently overrode the first in the array.
  Merged the button labels into the surviving block and removed the dead duplicate.

### Improved — Progetti card footer (button placement)
The record-card footer wrapped its delete button onto a second line (`flex-wrap` +
`ms-auto`), leaving an orphaned right-aligned "Elimina". Reworked to a single
aligned row — primary **Apri**, then PDF/Excel and delete as compact
`app-icon-btn` icon buttons (tooltip + `aria-label` preserved), delete pinned right.
Footers now line up across cards. Verified in light and dark themes.

### Fixed — mobile/tablet responsiveness
Audited the app at phone width (375px) across the dashboard, list pages, record-card
grids, detail pages and forms. One real break found and fixed: the **project detail
header** button group (`d-flex … flex-shrink-0`) forced its ~417px content width even
after wrapping, pushing the whole page into horizontal scroll on phones. Dropped
`flex-shrink-0` so the group shrinks and its buttons wrap (Modifica / Report PDF on
one line, Report Excel on the next); desktop still keeps all three on one row.
Everything else already behaved: wide tables (interventi, quote/invoice line items)
scroll inside their `.table-responsive` wrappers instead of overflowing the page,
filter grids collapse to a single column, and card grids stack — verified no
page-level horizontal overflow on any audited screen.

### Redesigned — sidebar navigation
The narrow 96px icon-rail (centered icon over a tiny label) read as cramped and
cheap once widened. Rebuilt as a **240px left-aligned row menu** (CSS-only, in
`app.css` — the `layout.php` markup is unchanged): each item is icon + label on one
line, hover and the active page fill the whole row with a soft green rounded **pill**
(no icon chips, no side stripes). Sub-nav labels wrap instead of truncating and the
expand caret is centred on the row's right edge. Dark theme and the mobile
off-canvas drawer (now 260px) updated to match. Verified on desktop (light + dark)
and the mobile drawer.

## 2026-07-10 — Platform hardening: automation, proactive alerts, indexing & polish

A deployment-readiness and "full platform" pass: fixed regressions left by the
"juli" redesign, added a **notification + scheduler automation layer** (the headline
feature), a **config-gated SMTP mailer**, **query indexes**, **list pagination**,
**client quote self-service**, and moved the last hardcoded strings into `lang/it.php`.
**No new runtime framework; CSP `'self'`, self-hosted assets and global CSRF preserved.**
The automated suite grows **398 → 451 assertions** (all green on a fresh DB).

### Fixed — regressions from the "juli" redesign (were user-facing breakage)
- **GPS clock-in/out (Badge di Cantiere) restored.** The rewritten `app.js` had
  dropped the `js-attendance-in/out` handlers, so the field timbratura did nothing.
  Re-added with best-effort geolocation **and** the offline action queue
  (`gm_action_queue_v1`, replays on reconnect) — matching what `sw.js` advertises.
- **Change-password restored.** The `js-password-form` handler was missing, leaving
  `/password` a dead raw POST; inline success/error feedback works again.
- **Dashboard KPI icons fixed.** `dashboard.php` referenced an SVG sprite
  (`#i-building`…) that the current `layout.php` no longer injects (it lived only in
  a `.bak`), so the cards rendered blank. Switched to the already-loaded Bootstrap-Icons.

### Fixed — correctness / safety (from a full code audit)
- **Invoice/quote PDF filenames.** `ReportFilename::make()` ignored the prefix its
  callers passed, so invoices/quotes downloaded as `report-*.pdf`; now
  `fattura-*.pdf` / `preventivo-*.pdf` (verified end-to-end).
- **Warehouse movement null-deref.** `addMovement()` used the `FOR UPDATE` row with
  no null check — a concurrent delete threw a 500 inside an open transaction. Guarded.
- **Client `during`-photo exposure.** The client gallery hides progress photos, but
  the stream served any type by id; the stream now 404s `during` photos too.
- **S.A.L. signature upload** now capped at 5 MB before base64-decoding (parity with
  the worker signature path).
- **Seed integrity.** `database/seed.php` truncated `clients`/`projects`/… but not
  the migration 010–014 tables (`quotes`, `expenses`, `project_invoices`, …), so
  re-seeding orphaned those rows. Added them, plus demo quotes/invoices/expenses/roster.
- **Login page** no longer prints demo credentials when `APP_ENV=production`.

### Added — automation platform (notifications + scheduler + mailer)
- **`notifications` table** (migration 016) + `NotificationModel`, admin topbar
  **bell with unread count**, and `/admin/notifications` (list, mark-read, mark-all).
- **`SchedulerService`** + `scripts/scheduler.php` (cron entrypoint): generates
  **idempotent** alerts (dedup-keyed) for **compliance-document expiries**
  (DURC/POS/Patente a Crediti…), **quotes past `valid_until`** (auto-set `expired`),
  **overdue interventions**, and **low stock**. Re-running the same day adds nothing.
- **`Support\Mailer`** — dependency-free, **disabled by default** (`MAIL_ENABLED=false`).
  Transports `smtp` (compact STARTTLS/SSL client over `fsockopen`) or PHP `mail`.
  When enabled, the scheduler e-mails admins a digest of the fresh alerts.

### Added — performance & UX
- **Indexes** (migration 015): `interventions(status)`, `interventions(completed_at)`,
  `stock_movements(item_id, location_id)`, `stock_movements(created_at)`,
  `project_invoices(status)`, `sal_documents(status)`.
- **Fixed the N+1** on the admin interventions list (one batched material query).
- **Pagination** (`Support\Paginator` + `partials/pagination.php`, 25/page) on the
  interventions, expenses, invoices and quotes lists, preserving active filters.
- **Client quote self-service** — clients see their non-draft quotes at
  `/client/quotes` and **accept/reject** the sent ones (ownership-guarded).
- **PWA:** `sw.js` `gm-shell-v5` now precaches the Inter web-fonts + `icon-512`;
  `manifest.webmanifest` `theme_color` corrected (red → brand green).

### Added — i18n & configuration
- Moved the last hardcoded Italian into `lang/it.php`: **report/PDF labels** (~70
  strings across `views/reports/*`), the **error pages**, and a small **JS i18n
  bridge** (`GM.t(key, fallback)` reading an optional `#gm-i18n` dictionary).
- New config groups (all env-driven): **`company.*`** (contractor identity on
  invoice/quote/S.A.L. PDFs — previously blank), **`mail.*`**, **`scheduler.*`**.
  Full reference: [docs/CONFIGURATION.md](docs/CONFIGURATION.md).

### Tests
- `tests/cases/00_paginator_mailer.php` — Paginator math, `ReportFilename` prefix,
  Mailer message construction + disabled gate.
- `tests/cases/19_scheduler_notifications.php` — scheduler idempotency + correct
  alert generation, notification read-state, admin-page RBAC, model pagination
  windows, and the full client accept/reject/ownership flow. **451 assertions green.**

## 2026-07-08 — "juli" design adoption + Preventivi/Fatture/Spese + project detail page

Merged the frontend and extra modules from the parallel ("juli") build onto this
core codebase. **No new runtime framework, CSP `'self'` preserved, assets self-hosted,
CSRF still enforced globally in `public/index.php`.**

### Design (frontend)
- Adopted the juli **sidebar/navbar look**: an icon-rail sidebar with stacked
  icon+label, expandable sub-menus (Interventi by status, Spese by category), a
  green top navbar, Bootstrap-Icons, and a richer `app.css` (filter cards, stat
  tiles, timeline, in-app dialogs, loading overlay, attendance calendar).
- **Preserved from this build:** dark/light theme toggle (`gm_theme` cookie,
  `[data-bs-theme]`), PWA (manifest + service worker `gm-shell-v4`, now precaching
  Bootstrap-Icons), and the CSRF meta/token flow. `app.js` merges the juli
  behaviours (CRUD modals, quote line editor, attendance register, photo queue,
  signature pad, date-range picker) with this build's CSRF header, service-worker
  registration, KPI sparklines, theme toggle and POST logout.
- Vendored `bootstrap-icons.min.css` + fonts and the juli logo/favicon SVGs.

### New modules
- **Preventivi (Quotes)** — `QuoteController`, `QuoteModel`, list/form views, live
  line editor, PDF export, and quote→invoice conversion. Routes under `/admin/quotes`.
- **Fatture (Invoices)** — `InvoiceController`, `ProjectInvoiceModel`, list/form
  views, printable receipt. Routes under `/admin/invoices`.
- **Spese (Expenses)** — `ExpenseController`, `ExpenseModel`, category-filtered
  list, totals. Routes under `/admin/expenses`.
- **Project detail page** ("Apri") — `ProjectController` gains `show` plus workers,
  attendance register (absence-by-default), materials log, documents (upload/
  download), and per-project invoices. `ProjectModel` gains worker-roster methods.
  The per-project **stock-location** creation on project create is preserved.
- `Validate::isDate` / `Validate::isMoney` added (used by the new controllers).

### Schema
- New migrations `010`–`014`: `project_workers`, `project_documents`,
  `project_invoices`, `project_materials`, `project_absences`, `quotes`,
  `quote_lines`, `expenses`. All FK to existing `clients`/`projects`/`users`/
  `warehouse_items`. `project_materials` is an informational log — it does **not**
  touch the stock ledger, so the inventory-ledger invariant is unaffected.

### Notes
- Verified statically (no PHP runtime was available in the build sandbox): all 91
  admin routes resolve to existing controller methods, all 63 `View::render`
  targets exist, and all Italian `Lang` keys used by the new views resolve. Run
  the test suite locally (`php tests\run.php`) before deploying — see README.
- `*.desktop-orig*.bak` copies of the pre-merge `layout.php`/`app.css`/`app.js`/
  `ProjectController`/`ProjectModel`/`projects/index.php` were left in the tree for
  easy diffing; safe to delete (originals are also in git history).

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
- **Phase 4 legal-feature pages brought to dashboard standard** — semantic
  status pills (S.A.L. Bozza/Emesso/Firmato; compliance Scaduto/In scadenza),
  severity-striped Scadenzario rows, and tabular-mono dates/amounts/credits
  across Giornale, Scadenzario, attendance register, and S.A.L. Verified in
  Chrome, light + dark.
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
