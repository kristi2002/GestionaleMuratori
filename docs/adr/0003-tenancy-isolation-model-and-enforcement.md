# ADR-0003 — Tenancy isolation model and enforcement

- **Status:** Proposed
- **Date:** 2026-07-11
- **Deciders:** Engineering (owner to confirm)
- **Supersedes:** —
- **Related:** [ADR-0001](0001-enterprise-architecture-direction.md), [ADR-0002](0002-framework-selection-and-migration-mechanics.md), [DATA_MODEL.md](../DATA_MODEL.md)

## Context

[ADR-0001](0001-enterprise-architecture-direction.md) set the direction:
**shared-DB + `tenant_id` with structurally enforced scoping**, reserving
database-per-tenant for regulated customers. [ADR-0002](0002-framework-selection-and-migration-mechanics.md)
chose **Laravel**. This ADR finalizes the **concrete tenancy model, the schema
changes, and — most importantly — how isolation is *enforced* so a forgotten
scope cannot leak data across tenants**.

The system is single-tenant today: every table is owned by the one implicit
organization, and authorization is expressed with hand-written `WHERE` clauses
(`AuthGuard` + ownership guards). The correctness-critical subsystems are the
`stock_movements` ledger and the `SELECT … FOR UPDATE` locking discipline; the
tenancy design must not weaken either.

**Key platform constraint:** unlike PostgreSQL, **MySQL 8 has no row-level
security**. We therefore cannot lean on the database to enforce tenant scoping —
enforcement must live in the application layer, backed by automated guards, with
database-per-tenant as the hard-isolation escape hatch.

## Decision drivers

- **Structural enforcement** — isolation must not depend on a developer
  remembering a `WHERE` clause on each query.
- **Compliance** — offer strong (physical) isolation to customers who require it.
- **Cost/scale** — cheap onboarding and pooled resources for the common case.
- **Preserve invariants** — ledger-before-cache and ascending-id lock order must
  survive unchanged.
- **Smooth migration** — the existing single tenant must backfill cleanly.

## Considered options

### A. Shared DB + `tenant_id`, app-enforced global scope
- **+** Cheapest onboarding/scale; one schema, one migration set; simple ops.
- **−** Logical isolation only; a scoping bug is a cross-tenant leak; MySQL can't
  back-stop it. Requires disciplined enforcement + automated guards.

### B. Database-per-tenant
- **+** Physical isolation; per-tenant backup/restore, encryption, data residency;
  trivial "delete a tenant". Strong compliance story.
- **−** Provisioning/migration fan-out (run migrations across N databases);
  connection management; costlier at high tenant counts.

### C. Schema-per-tenant (one MySQL database per tenant on a shared server)
- **+** Middle ground on isolation.
- **−** MySQL treats schemas as databases anyway, so this collapses toward (B)
  with fewer benefits; migration fan-out without the clean per-tenant backup story
  of separate servers. Not worth it.

### D. Hybrid: pooled shared-DB by default, DB-per-tenant tier for regulated
customers, behind **one tenant abstraction** *(chosen)*
- **+** Common case is cheap (A); regulated case gets physical isolation (B);
  `stancl/tenancy` supports both single-DB and multi-DB behind the same tenant
  resolution and lifecycle, so application code is written once.
- **−** Must design for both from the start (connection switching, id strategy).

## Decision

Adopt the **hybrid model (D)**:

1. **Default tier: shared database + `tenant_id`** on every tenant-owned table,
   with a **mandatory Eloquent global scope** as the primary enforcement and
   **automated guards** as defense-in-depth.
2. **Regulated tier: database-per-tenant**, provisioned through the same tenant
   abstraction (`stancl/tenancy`), for customers with data-residency or physical-
   isolation requirements.
3. Tenancy lands **before the second tenant is onboarded**, and the stock/ledger
   modules are ported (ADR-0002) with tenancy already in place.

## Model & schema

### Tenant identity and resolution
- New `tenants` table (id, name, slug, tier `pooled|isolated`, status, created_at).
- **Resolution middleware** sets the "current tenant" per request from the chosen
  strategy (subdomain `acme.app.example`, custom domain, or authenticated user's
  `tenant_id`). Strategy pinned in an open question below.
- The resolved tenant is stored in a **request-scoped container singleton**
  (`Tenancy::current()`), never in a static mutable global, so queue workers and
  tests set it explicitly.

### Tenant-owned tables (pooled tier)
- Add `tenant_id BIGINT UNSIGNED NOT NULL` + FK to `tenants(id)` on every
  business table (projects, clients, interventions, warehouse_items,
  stock_movements, photos, quotes, invoices, expenses, daily_logs, sal_*,
  compliance_*, subcontractors, notifications, …).
- **Every composite index and unique constraint gains `tenant_id` as its leading
  column.** E.g. a unique `quotes.number` becomes unique `(tenant_id, number)`;
  hot lookups become `(tenant_id, …)`.
- **Backfill:** create `tenants` row id=1 for the current org; set `tenant_id=1`
  everywhere; then add NOT NULL + FK. One reversible Laravel migration.
- **IDs stay global** `BIGINT AUTO_INCREMENT` (not per-tenant) — simpler, keeps
  the ascending-id lock order valid, and avoids id-rewrite during backfill.
  `tenant_id` is a *scoping* column, not part of the primary key.

### Users and roles
- Users belong to a tenant (`users.tenant_id`). The existing roles
  (`admin`/`worker`/`client`) become **per-tenant** roles.
- Add a **platform super-admin** identity that operates *outside* tenant scope for
  support/ops; its access is audited (ADR-0001 audit-log item) and gated
  separately from tenant roles.

## Enforcement (the core of this ADR)

Because MySQL offers no RLS, isolation is enforced in **four layers**, so no
single mistake leaks data:

1. **`BelongsToTenant` trait + global scope (primary).** Every tenant-owned
   Eloquent model uses a trait that (a) adds `where tenant_id = current` to *all*
   queries via a global scope and (b) auto-fills `tenant_id` on create. Developers
   write ordinary queries; scoping is automatic.
2. **Write-path guard.** The trait blocks `create`/`save` when no tenant is set
   (outside super-admin context), so a missing tenant fails loudly instead of
   writing an unscoped row.
3. **Automated conformance test (defense-in-depth).** A test enumerates every
   model mapped to a tenant-owned table and **fails CI** if it lacks
   `BelongsToTenant`. This turns "someone forgot to scope a new model" from a
   production leak into a red build. Complement with a cross-tenant access test:
   seed tenant A and B, authenticate as A, assert every list/show endpoint cannot
   read B's rows.
4. **Isolated tier (hard guarantee).** For regulated customers, database-per-
   tenant makes cross-tenant access physically impossible regardless of app bugs.

### Raw SQL / the ledger
- The stock service uses explicit transactions and `lockForUpdate()` (ADR-0002).
  Those queries must **also** carry `tenant_id` — add it to the lock/select and to
  the `stock_movements` insert. Ascending-id lock order is unaffected (ids stay
  global). Add a concurrency test that runs two tenants in parallel and asserts
  neither sees the other's stock and locks don't cross tenants.
- Any remaining raw PDO in legacy code (pre-port) must have `tenant_id` added to
  its `WHERE`; these are the riskiest sites and should be ported to Eloquent early.

### Cross-cutting scoping
- **Storage:** object keys are prefixed per tenant (`tenants/{id}/uploads/…`);
  signed URLs are tenant-checked by the serving controller (photos/signatures are
  already permission-served, never static).
- **Cache/queue:** cache keys are namespaced by tenant; **every queued job carries
  the tenant id and re-enters tenant context on the worker** (a job that forgets
  this would run unscoped — enforced by a base `TenantAwareJob`).
- **PDF/report generation:** already per-record; ensure the `ReportDataService`
  queries run inside the tenant scope.

## Onboarding & lifecycle
- **Pooled tenant:** insert `tenants` row, seed per-tenant defaults (roles, config).
  Instant.
- **Isolated tenant:** provision a database, run the migration set against it,
  seed defaults — automated via the tenancy package's lifecycle hooks.
- **Offboarding / GDPR erasure:** pooled → delete-by-`tenant_id` (FKs cascade);
  isolated → drop the database. Both feed the audit log.

## Consequences

**Positive**
- Isolation is structural (global scope) *and* fail-safe (CI conformance test),
  not convention-dependent.
- A physical-isolation tier exists for compliance without a second codebase.
- Ledger/locking invariants are preserved; ids and lock order are unchanged.
- Onboarding a pooled tenant is a single insert.

**Negative / costs**
- Wide but mechanical migration: `tenant_id` + reworked indexes/unique keys on
  ~all tables, plus a careful backfill.
- Every model, job, and cache key must be tenant-aware; the base traits/classes
  reduce but don't remove the discipline required.
- Two tiers to test and operate (pooled + isolated).

**Risks & mitigations**
- *Forgotten scope on a new model* → CI conformance test fails the build.
- *Cross-tenant id enumeration* (guessing another tenant's `/projects/{id}`) →
  the global scope makes the row invisible → existing "404 for not-mine" holds.
- *Unique-constraint breakage during backfill* → recompute all unique keys as
  `(tenant_id, …)` in the same migration; test on a prod-shaped dataset first.
- *Noisy-neighbor on the pooled tier* → per-tenant rate limiting (ADR-0001
  Phase 4); offer the isolated tier to heavy customers.
- *Unscoped queue jobs* → mandatory `TenantAwareJob` base + a test that jobs set
  tenant context.

## Open questions

- **Tenant resolution strategy:** subdomain, custom domain, or user-attribute
  only? (Affects auth, cookies, TLS, and the Caddy/routing config.)
- Which customers (if any) require **database-per-tenant** at launch vs later?
- Do any require **per-tenant encryption keys / BYOK** or in-region storage?
- Should the **platform super-admin** be a separate identity system entirely
  (stronger separation) — likely folded into ADR-0004 (identity)?
