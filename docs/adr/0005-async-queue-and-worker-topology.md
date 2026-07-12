# ADR-0005 — Async processing: queue technology and worker topology

- **Status:** Proposed
- **Date:** 2026-07-11
- **Deciders:** Engineering (owner to confirm)
- **Supersedes:** —
- **Related:** [ADR-0001](0001-enterprise-architecture-direction.md), [ADR-0002](0002-framework-selection-and-migration-mechanics.md), [ADR-0003](0003-tenancy-isolation-model-and-enforcement.md), [ADR-0004](0004-identity-sso-mfa-and-platform-admin.md)

## Context

ADR-0001 identified the lack of async processing as a scale/reliability gap:
PDF generation, e-mail, and notifications run **synchronously in the request**,
and the only scheduled work is a cron script (`scripts/scheduler.php`, which
generates compliance/overdue/quote-expiry/low-stock notifications and optional
e-mail digests). ADR-0002 chose Laravel; ADR-0003 requires every unit of
background work to be **tenant-aware** (a mandatory `TenantAwareJob`); ADR-0004
adds identity events (SCIM sync, session revocation) that are naturally async.

Concrete work that should move off the request path:

- **PDF/report generation** (mPDF; can be slow and memory-heavy — see the
  2026-07-11 temp-dir incident) and **XLSX exports** (PhpSpreadsheet).
- **E-mail** (login/security, notification digests, quote/invoice sends).
- **Notification generation** currently in the cron scheduler.
- **Identity** (ADR-0004): SCIM reconciliation, deprovision → session revocation.
- **Tenant lifecycle** (ADR-0003): provisioning an *isolated-tier* database and
  running migrations against it.
- **Webhooks/integrations** (future) and **search/index** updates (future).

Requirements: at-least-once delivery with **retries + backoff**, a **dead-letter**
path, **visibility** (what's queued/failed/slow), **per-tenant fairness** (no
noisy neighbor starves others), and safe interaction with the ledger's
transactional writes.

## Decision drivers

- Reliability (retries, DLQ, idempotency) over raw throughput at current scale.
- First-class Laravel support to minimize bespoke plumbing (ADR-0001 intent).
- Reuse infrastructure already being added — **Redis** (ADR-0001 Phase 1) — before
  introducing new moving parts.
- Operability: dashboards, alerting, and horizontal worker scaling.
- Correctness with tenancy (ADR-0003) and transactions.

## Considered options (queue backend)

**A. Redis + Laravel Queue + Horizon** *(chosen)*
- **+** Redis is already in the stack for sessions/cache; **Horizon** gives a
  dashboard, metrics, auto-balancing, retry/DLQ management, and tag-based
  monitoring out of the box; simplest path with the best operability-per-effort.
- **−** Redis is not a durable broker by design; needs AOF persistence + HA
  (managed Redis or Sentinel/Cluster) to avoid losing enqueued jobs on failure.

**B. Amazon SQS (or other managed queue)**
- **+** Fully managed durability/HA; scales effortlessly; good for very high volume.
- **−** No Horizon; weaker local dev ergonomics; another external dependency; more
  than current scale needs. A reasonable *later* migration target — the Laravel
  queue abstraction makes switching backends low-cost.

**C. Database queue (MySQL)**
- **+** Zero new infrastructure; transactional with app data.
- **−** Polling load on the primary DB; poor throughput and visibility; contends
  with the ledger's `FOR UPDATE` hotspots. Acceptable only as a dev fallback.

**D. RabbitMQ / Kafka**
- **−** Powerful but operationally heavy; routing/streaming features we don't need
  yet. Over-engineered for current requirements.

## Decision

1. Use **Laravel Queue on Redis, managed by Horizon**, with **managed/HA Redis and
   AOF persistence** so enqueued jobs survive failures.
2. Keep the **Laravel queue abstraction** as the boundary so the backend can move
   to **SQS** later without touching job code, if volume or durability demands it.
3. Convert synchronous work and the cron scheduler to **queued, tenant-aware,
   idempotent jobs**; run **dedicated worker containers** separate from web.
4. Enforce a small set of **job invariants** (below) so async work is safe with
   tenancy and the ledger.

## Worker topology

- **Separate `worker` container(s)** (same image as `app`, command
  `php artisan horizon` / `queue:work`) so background load never blocks web
  requests and workers scale independently. Add to the Compose/Coolify stack;
  scale by replica count.
- **Queues by latency class**, not by feature:
  - `interactive` — user is waiting (on-demand PDF/export): short, high priority.
  - `default` — e-mail, notifications, webhooks.
  - `maintenance` — SCIM reconciliation, digests, low-priority batch.
  - `provisioning` — isolated-tenant DB creation + migration (ADR-0003), long-running.
  Horizon weights concurrency per queue so a burst of digests can't delay an
  interactive export.
- **Per-tenant fairness:** cap concurrency so no single tenant monopolizes a queue;
  for the isolated tier, provisioning runs on its own queue. Revisit dedicated
  per-tenant queues only if a heavy tenant proves problematic (pair with ADR-0001
  Phase 4 per-tenant rate limiting).
- **Scheduling:** replace the external cron with Laravel's scheduler
  (`schedule:run` via one lightweight cron entry, or a scheduler container) which
  **dispatches** the notification/digest jobs onto queues instead of doing the work
  inline. Preserves the existing idempotent, `dedup_key`-based behavior.

## Job invariants (must-hold rules)

1. **Tenant-aware:** every job extends `TenantAwareJob` (ADR-0003) — it carries the
   `tenant_id`, re-enters tenant context on the worker, and all its queries are
   scoped. A job without a tenant (except platform-admin jobs) fails loudly.
2. **Idempotent / at-least-once safe:** jobs may run more than once; use natural
   keys or a processed-marker (the scheduler's `notifications.dedup_key` is the
   model) so retries don't double-send or double-write.
3. **Dispatch after commit:** jobs touching data are dispatched with
   `->afterCommit()` so a rolled-back transaction never enqueues work referencing
   rows that don't exist. **Ledger writes stay synchronous inside the request
   transaction** — we do *not* move stock/`FOR UPDATE` mutations into a job; only
   their side effects (e.g. a confirmation e-mail, a report) are queued.
4. **Retries with backoff + DLQ:** bounded `tries`/`backoff`; exhausted jobs land
   in `failed_jobs` (the dead-letter) and raise an alert (ADR-0001 Phase 0
   observability). No silent drops.
5. **Timeouts & memory:** per-job `timeout`/memory limits (PDF jobs especially,
   given the mPDF footprint); workers restart on memory pressure.

## Delivery of results to users

- On-demand exports become **async**: the request enqueues an `interactive` job and
  returns a "preparing" state; the finished artifact is delivered via a
  notification + a permission-checked download link (files stored per-tenant in
  object storage, ADR-0003). This removes the request-thread blocking that caused
  the PDF incident's blast radius and lets big reports exceed request timeouts.

## Consequences

**Positive**
- Slow/heavy work leaves the request path → faster, more reliable responses.
- Retries + DLQ + Horizon visibility replace silent synchronous failures.
- Tenancy and transaction safety are enforced by shared job base classes/rules.
- Reuses Redis; backend is swappable to SQS later with no job-code change.

**Negative / costs**
- New runtime component (workers) and Redis durability/HA to operate.
- Async UX for exports (preparing → ready) is a product change, not just plumbing.
- Idempotency and `afterCommit` discipline required on every data-touching job.

**Risks & mitigations**
- *Redis loss drops jobs* → managed/HA Redis + AOF; critical flows idempotent and
  reconcilable; consider SQS if durability needs grow.
- *Poison job blocks a queue* → bounded retries + DLQ + alert; isolate long jobs on
  their own queue.
- *Noisy-neighbor tenant* → per-queue concurrency caps; escalate to dedicated
  queue / isolated tier.
- *Accidentally queuing ledger mutations* → invariant #3 + code review; keep stock
  writes synchronous and transactional.

## Open questions

- Managed Redis provider/HA topology (Sentinel vs Cluster vs managed service) and
  persistence settings.
- Is async export UX acceptable for v1, or must small reports stay synchronous
  (hybrid: inline under a size/time threshold, queue above it)?
- Expected peak job volume per tenant — informs whether SQS is needed sooner.
