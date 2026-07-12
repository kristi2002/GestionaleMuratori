# ADR-0002 — Framework selection (Laravel) and strangler migration mechanics

- **Status:** Proposed
- **Date:** 2026-07-11
- **Deciders:** Engineering (owner to confirm)
- **Supersedes:** —
- **Related:** [ADR-0001](0001-enterprise-architecture-direction.md), [ARCHITECTURE.md](../ARCHITECTURE.md), [DATA_MODEL.md](../DATA_MODEL.md)

## Context

[ADR-0001](0001-enterprise-architecture-direction.md) decided the **direction**:
adopt a mainstream PHP framework and migrate incrementally via the strangler
pattern, rather than continue investing in the bespoke framework or attempt a
big-bang rewrite. It explicitly deferred **which** framework and **how** the
migration runs to this record.

Constraints carried over from the current system:

- PHP 8.2, single MySQL 8 database, Docker Compose (PHP-FPM `app` + Caddy `web` +
  MySQL) on Coolify. Server-rendered views; no JS build tooling; no SPA.
- Correct, valuable **domain logic** to preserve: the `stock_movements` ledger as
  source of truth with `qty_in_stock` cache; every stock/status write in a
  transaction with `SELECT … FOR UPDATE` in ascending-id order; `completed`
  reachable only via `InterventionService::complete()`; RBAC on every endpoint.
- All user-facing text is Italian via `lang/it.php`; DB ENUMs stay English.
- A `tests/run.php` harness with 455 tests we must not lose coverage from.

## Decision drivers

- Batteries-included support for the four enterprise drivers in ADR-0001
  (tenancy scoping, SSO/MFA, queues, billing, caching).
- Preserve server-rendered UX and the existing Blade-compatible view style
  without adopting an SPA.
- Largest hiring pool and community/security-patch cadence.
- Clean interop with a **shared database** during the strangler period.
- Faithful, safe re-expression of the transactional/ledger invariants.

## Considered options

### A. Laravel *(chosen)*
- **+** First-party or de-facto-standard packages for every ADR-0001 need:
  global query scopes (tenancy), queues + Horizon, Redis cache/session, Socialite
  + SAML/OIDC and Fortify (MFA), **Cashier** (subscription billing), Sanctum
  (API tokens), Telescope + Sentry integrations, Eloquent migrations, Pest/PHPUnit.
- **+** Blade templating is close to the current PHP-view style → low view-porting
  friction; Laravel Localization maps cleanly onto the `lang/it.php` model.
- **+** Largest hiring pool; strong SaaS/tenancy ecosystem (`stancl/tenancy`).
- **−** More "magic"/conventions to learn; opinionated structure.

### B. Symfony
- **+** Explicit, highly decoupled, excellent long-term for large teams; strong
  DI/messenger; Doctrine ORM is powerful with the unit-of-work pattern.
- **−** More assembly required for SaaS concerns (billing, tenancy, MFA) — fewer
  batteries included; steeper ramp for a small team; smaller hiring pool locally.

### C. Slim / Mezzio (micro-framework)
- **−** Closer to what we already have; would leave most enterprise concerns
  bespoke. Rejected — it re-opens the problem ADR-0001 closed.

**Choice: Laravel**, because the decisive factors in ADR-0001 (multi-tenant SaaS
+ security/compliance + team hireability) are exactly where Laravel's included
batteries and hiring pool pay off, and Blade minimizes view-migration cost.
Symfony would be a defensible alternative for a larger, platform-oriented team.

## Decision

1. Adopt **Laravel** (latest LTS-supported stable at project start) as the target
   framework.
2. Migrate via the **strangler pattern over a shared MySQL database**, routing
   traffic at the edge and porting modules one at a time.
3. Re-express domain invariants explicitly in the Laravel layer (see mechanics);
   **do not** rely on Eloquent conveniences that would weaken the ledger/locking
   guarantees.

## Migration mechanics

### Edge routing (the "strangler fig")

- Introduce the Laravel app as a **third container** (`app_next`, PHP-FPM) beside
  the existing `app`.
- Update **Caddy** (`deploy/Caddyfile`) to route by path prefix: migrated routes
  → `app_next`; everything else → the legacy `app`. Move prefixes over as modules
  are ported. This is the single switch that "straggles" the old app.
- No user-visible cutover per module — only a routing table change.

### Shared session (so a user stays logged in across both apps)

- Move sessions to a **shared Redis store** (also a Phase-1 item in ADR-0001).
  Configure Laravel's session driver and a small shim in the legacy app to read/
  write the **same** Redis session keys and cookie name/domain.
- Standardize on one cookie (`SESSION_SECURE=true`, HttpOnly, SameSite) across
  both apps. This lets the reverse proxy hand a request to either app
  transparently during the transition.

### Shared database contract

- Both apps read/write **the same schema** during migration. To avoid two
  migration tools fighting: **freeze legacy `database/migrate.php`** for new
  changes once Laravel is in; from that point **all schema changes go through
  Laravel migrations**. Import the current schema as an initial Laravel migration
  (or `schema:dump` baseline).
- Eloquent models map to existing tables/columns (English names already match
  conventions; set `$table`/`$fillable`/casts explicitly). Keep DB ENUM values
  English; translate only in views (unchanged rule).

### Preserving the ledger + locking invariants (critical)

- Port stock/status writes as **explicit** Laravel code, not naive Eloquent saves:
  wrap in `DB::transaction()`, take row locks with `->lockForUpdate()` in
  **ascending id order**, and **always** write a `stock_movements` row before
  touching the `qty_in_stock` cache — mirroring today's rules and
  `docs/DATA_MODEL.md` sign conventions.
- Keep `completed` reachable only through the ported
  `InterventionService::complete()` equivalent; add a test asserting no other
  path sets it.
- Add **characterization tests** (see below) around each such module *before*
  porting it, so behavior is pinned.

### Auth & RBAC

- Re-implement `AuthGuard::require()` as Laravel middleware; port worker/client
  ownership guards as policies/gates that **return 404 for "not mine"** (preserve
  current semantics, not Laravel's default 403).
- Defer SSO/MFA to ADR-0004; the initial port keeps the existing email+password
  so the two apps share credentials via the shared session.

### Views, assets, i18n

- Port PHP views to **Blade** (mechanical: `<?= $e(...) ?>` → `{{ }}`, which
  auto-escapes; keep the vendored Bootstrap/jQuery assets as-is, no build step).
- Map `lang/it.php` keys onto Laravel localization files; keep `Lang::get`/
  `Lang::label` call-site semantics via a thin helper to reduce churn.

### Testing

- Stand up **Pest/PHPUnit** in the Laravel app. For each module: write
  characterization tests against current behavior, port, then make them pass.
- Keep `tests/run.php` running against the **legacy** app until its last module is
  ported; CI runs both suites during the transition (ADR-0001 Phase 0 gives us CI).
- Add explicit **concurrency tests** for the ported stock paths (the legacy suite
  already has `11_concurrency.php` to mirror).

### Suggested porting order (low-risk leaf → core)

1. **Reports/exports** (mPDF/PhpSpreadsheet) — leaf, read-mostly, already isolated
   in `Services\Report\*`; good first proof of the strangler wiring.
2. **Read-only admin lists** (clients, subcontractors).
3. **Quotes/invoices/expenses** (write, but no stock coupling).
4. **Warehouse + interventions + the stock ledger** — the core invariants, ported
   last with the most test scaffolding.
5. Retire the legacy `app` container and its bespoke plumbing when empty.

### Rollback

- Because routing is per-prefix at the proxy, a problematic module is rolled back
  by **pointing its prefix back at the legacy `app`** — no data migration to
  reverse (shared DB). Keep the legacy container deployable until fully drained.

## Consequences

**Positive**
- Every ADR-0001 enterprise need now has a maintained component behind it.
- Server-rendered UX and Italian-first i18n are preserved; no SPA rewrite.
- The ledger/locking guarantees are carried over deliberately and test-pinned.
- Per-prefix routing gives cheap, reversible increments.

**Negative / costs**
- Dual-stack operational complexity during migration (two PHP containers, shared
  Redis session, one owner of schema migrations).
- Team ramp on Laravel conventions and Eloquent (with discipline to not let
  Eloquent conveniences erode the locking rules).
- View/test porting is real, if mostly mechanical, work.

**Risks & mitigations**
- *Eloquent weakening transactional integrity* → mandate explicit
  `DB::transaction()` + `lockForUpdate()` for stock/status; code-review checklist;
  concurrency tests as gate.
- *Two migration tools diverging* → freeze legacy migrations; Laravel owns schema.
- *Session/cookie mismatch causing logout loops* → shared Redis + single cookie
  contract validated in staging before any production prefix moves.

## Open questions

- Confirm **Laravel** over Symfony with the owner (this ADR assumes Laravel).
- Pin the **Laravel version / support window** at project start.
- Does the team prefer **Pest** or **PHPUnit** as the primary test tool?
