# ADR-0001 — Direction for evolving to an enterprise, multi-tenant SaaS

- **Status:** Proposed
- **Date:** 2026-07-11
- **Deciders:** Engineering (owner to confirm)
- **Supersedes:** —
- **Related:** [ARCHITECTURE.md](../ARCHITECTURE.md), [DATA_MODEL.md](../DATA_MODEL.md), [ROADMAP.md](../ROADMAP.md), [GAP_ANALYSIS.md](../GAP_ANALYSIS.md)

## Context

Gestionale Muratori today is a **custom PHP 8.2 MVC application** (no framework,
no ORM): a hand-rolled router/autoloader, raw PDO with prepared statements
against a single MySQL 8 instance, server-rendered PHP views with vendored
Bootstrap 5 / jQuery, mPDF + PhpSpreadsheet for documents, and a bespoke
`tests/run.php` harness (455 tests). It is deployed as a Docker Compose stack
(PHP-FPM app + Caddy web + MySQL) on Coolify, single-replica, with uploads on a
local named volume and file-based sessions.

The stack has real strengths we want to preserve:

- **Strong domain integrity** — the inventory ledger (`stock_movements`) is the
  source of truth with `qty_in_stock` as a cache; every stock/status write runs
  in a transaction with `SELECT … FOR UPDATE` in ascending-id order; `completed`
  is reachable only through `InterventionService::complete()`.
- **Sound security fundamentals** — `AuthGuard::require()` on every endpoint,
  ownership guards, CSRF, rate limiting, universal prepared statements and output
  escaping, a strict `default-src 'self'` CSP.
- **Low dependency surface** (two runtime libraries).

We now want to take the product to **enterprise grade**, and the owner has
confirmed **all four** of these drivers are in scope simultaneously:

1. **Scale & reliability** — many concurrent users, HA, horizontal scale, backup/DR.
2. **Multi-tenant SaaS** — serve multiple client organizations with data isolation.
3. **Security & compliance** — SSO/MFA, audit trails, GDPR, secrets management.
4. **Team & maintainability** — grow a team; long-term maintainability and hireability.

### Problem

The **architecture** is fundamentally suitable for enterprise; the gaps are in
**stack choices and operational maturity**. The four drivers *together* — not
individually — change the calculus:

- The app enforces authorization/tenancy with **hand-written `WHERE` clauses**.
  In a multi-tenant system a single forgotten `AND tenant_id = ?` is a
  cross-tenant data leak — a security *and* compliance incident. Raw PDO cannot
  structurally enforce scoping.
- There is **no observability or alerting**: errors go only to container stderr
  (`error_log = /proc/self/fd/2`). The recent bug where *every* PDF endpoint
  returned 500 in production (mPDF's temp dir was unwritable by `www-data`; see
  CHANGELOG 2026-07-11) went unnoticed and unalerted — diagnosis required manual
  reproduction. This is unacceptable at enterprise scale.
- **Horizontal scale is blocked**: file-based sessions and a local upload volume
  are not shared across replicas.
- **No async processing**: PDF/email/notification work is synchronous; the
  scheduler is a cron script.
- The **custom framework** has no community security patches, no ecosystem, and a
  high onboarding cost — a bus-factor and hiring risk as the team grows.

## Decision drivers

- Enforce tenant isolation *structurally*, not by developer discipline.
- Minimize risk to the valuable, correct domain logic already in place.
- Prefer maintained, patched building blocks over bespoke plumbing.
- Deliver value incrementally; avoid a big-bang rewrite and a long dark period.
- Keep the option of stronger physical isolation for specific regulated customers.

## Considered options

### A. Keep the custom stack, harden it in place
Add Redis sessions/cache, S3 uploads, a queue, observability, and a repository
layer that enforces `tenant_id` scoping.
- **+** No migration cost; smallest immediate change.
- **−** Tenancy scoping, auth (SSO/MFA), billing, and queues all remain bespoke
  and unpatched. Every one of the four drivers keeps costing custom code. Hiring
  stays hard. The structural leak risk is only mitigated by convention.

### B. Big-bang rewrite on a mainstream framework
Rebuild on Laravel/Symfony, cut over once.
- **+** Clean target architecture.
- **−** High risk, long no-value period, easy to under-estimate domain edge cases,
  business stalls during the rewrite. Rejected.

### C. Incremental migration to Laravel via the strangler pattern *(chosen)*
Stand up Laravel alongside the current app sharing the same database; route new
and changed features through it; port modules one at a time; retire the bespoke
plumbing as modules move.
- **+** Framework-enforced **global query scopes** (tenant isolation in one
  place), **queues + workers**, **Redis cache/session drivers**, an **auth
  ecosystem** (Socialite/SAML/OIDC, MFA), **Cashier** for subscription billing,
  Eloquent migrations, PHPUnit/Pest, and first-class observability integrations —
  all maintained and patched.
- **+** Domain logic ports cleanly (ledger rules, transactional stock service,
  RBAC matrix, state machine). Only the non-differentiating plumbing is discarded.
- **+** Value ships continuously; each ported module is independently releasable.
- **−** Two codebases coexist during migration; requires routing/session-sharing
  discipline and a shared schema contract. Team must learn framework conventions.

Symfony is a viable alternative to Laravel with the same reasoning; Laravel is
preferred here for the batteries-included SaaS features (Cashier, first-party
tenancy packages, larger hiring pool). **The framework choice itself is deferred
to a follow-up ADR**, but the *direction* (adopt a mainstream framework
incrementally) is decided here.

### Tenancy isolation sub-decision (direction only)

| Model | Isolation | Ops cost |
|-------|-----------|----------|
| Shared DB + `tenant_id` per row + enforced global scope | Logical | Low |
| Schema-per-tenant | Medium | Medium |
| Database-per-tenant | Physical (strongest) | High |

**Direction:** default to **shared-DB + `tenant_id` with framework-enforced
scoping**, and offer **database-per-tenant** only for customers whose contracts
require hard physical isolation. The exact model is finalized in a follow-up ADR.

## Decision

1. **Adopt a mainstream PHP framework (Laravel, pending confirmation) and migrate
   incrementally using the strangler pattern** — not a rewrite, not continued
   investment in the bespoke framework.
2. **Introduce multi-tenancy as shared-DB + `tenant_id` with structurally
   enforced query scoping**, reserving database-per-tenant for regulated
   customers.
3. **Raise operational maturity first** (observability, HA data, horizontal-scale
   enablers, async processing) so it de-risks the migration rather than following
   it.

## Consequences

**Positive**
- Tenant isolation becomes a structural guarantee, directly serving security &
  compliance.
- SSO/MFA, billing, queues, caching arrive as maintained components, not code we
  own forever.
- Horizontal scale and HA become achievable; the team grows against a hireable,
  documented stack.
- The correct domain model is preserved and re-expressed, not lost.

**Negative / costs**
- A 6–12 month program for a small team (evolutionary, shippable in increments —
  not a weekend refactor).
- Temporary dual-stack complexity (routing, shared sessions, shared schema).
- Team ramp-up on framework conventions.

**Risks & mitigations**
- *Domain regressions during port* → port module-by-module behind the existing
  test suite; add characterization tests before moving each module.
- *Cross-tenant leaks* → land tenancy + enforced scoping before onboarding a
  second tenant; add automated tests that assert queries are tenant-scoped.
- *Migration stalls mid-way* → keep each phase independently valuable so partial
  completion still improves the product.

## Implementation phases

Sequenced so earlier phases de-risk later ones. Each is independently valuable.

- **Phase 0 — Stop flying blind:** structured logging + error aggregation
  (Sentry) + **alerting**; CI running the suite on every push; a staging
  environment; managed/replicated MySQL with automated **backups + tested
  restores (PITR)**. *(reliability, compliance)*
- **Phase 1 — Horizontal-scale enablers:** Redis sessions/cache; S3-compatible
  object storage for uploads; a **job queue** for PDFs/emails/notifications/
  scheduler. *(scale & reliability)*
- **Phase 2 — Framework foundation (strangler):** stand up Laravel on the shared
  DB; route new features through it; port a leaf module (e.g. reports/exports)
  first. *(team & maintainability)*
- **Phase 3 — Tenancy + identity:** add `tenant_id` + enforced global scoping;
  tenant onboarding/provisioning; per-tenant config; **SSO (OIDC/SAML) + MFA**;
  generalize the existing `intervention_status_history` pattern into a broader
  **audit log**; secrets management; GDPR data-subject flows.
  *(multi-tenant, security & compliance)*
- **Phase 4 — SaaS operations:** subscription billing, usage metering, plan
  limits, back-office, per-tenant rate limiting and tenant-aware observability.

## Follow-up ADRs (to be written)

- ADR-0002 — Framework selection (Laravel vs Symfony) and migration mechanics.
- ADR-0003 — Concrete tenancy isolation model and enforcement.
- ADR-0004 — Identity: SSO/MFA/SCIM provider and session strategy.
- ADR-0005 — Async/queue technology and worker topology.

## Open questions

- Is this a **live product with customers** (favors strangler) or **pre-launch**
  (a cleaner framework start may be cheaper)?
- Any customers with **hard data-residency / physical-isolation** requirements
  that force database-per-tenant from day one?
- Target concurrency / SLA, to size the HA and scaling work.
