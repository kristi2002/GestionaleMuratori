# ADR-0007 — Evolve the existing PHP stack; earn from one client first

- **Status:** Accepted
- **Date:** 2026-07-16
- **Deciders:** Engineering (owner)
- **Supersedes:** —
- **Related:** [ADR-0001](0001-enterprise-architecture-direction.md), [ADR-0002](0002-framework-selection-and-migration-mechanics.md), [ADR-0003](0003-tenancy-isolation-model-and-enforcement.md), [ADR-0006](0006-future-field-and-ai-platform-direction-parked.md)

## Context

Two directions were on the table for turning Gestionale Muratori into a
"full-fledged platform":

1. A **greenfield rebuild** (Next.js + Supabase/Postgres + Drizzle + React Native +
   AI WhatsApp intake + shared-schema multi-tenant SaaS), captured in the pasted
   architecture brief and assessed in [ADR-0006](0006-future-field-and-ai-platform-direction-parked.md).
2. **Evolving the current app** — a mature PHP 8.2 custom-MVC system (raw PDO,
   MySQL 8, 23 functional modules, a correct `stock_movements` ledger, hardened
   security, already deployed on Coolify/Hetzner and test-green).

The owner also asked the strategic question: *what if I want to sell this to more
than one company?* — i.e. multi-tenancy is a real future goal, not a hypothetical.

The earlier ADR series (0001–0005) **proposed** an enterprise Laravel + multi-tenant
direction but was never `Accepted` — those ADRs are still `Proposed`. ADR-0006
already parked the full greenfield swap. This ADR resolves the near-term direction
on the record.

## Assessment

- The existing app already covers **~80% of the greenfield brief's functionality**,
  is deployed, and is backed by a growing green test suite. A rewrite would cost
  months to reach *parity* — before adding any new value — and would not, on its
  own, improve office-dashboard latency (the app is already server-rendered and
  fast for a single-office workload).
- The genuinely-new capabilities in the brief (AI WhatsApp/voice intake, native
  offline mobile, multi-tenancy) are **additive services** on top of the current
  backend; none of them *requires* a rewrite (see ADR-0006's binding constraint:
  field/AI writes are proposed events, the server stays authoritative over the
  ledger).
- There is exactly **one paying client today.** Optimising for a multi-tenant SaaS
  before earning from that client is premature; the highest-value work is finishing
  the platform features the current client needs.
- Multi-tenancy done properly (shared-schema `organization_id` everywhere +
  cross-tenant test matrix, or Postgres RLS) is a **dedicated project**, not a flag
  to bolt on mid-stream.

## Decision

1. **Evolve the existing PHP stack. Do not rewrite, and do not introduce a new
   framework or ORM** (consistent with the project's hard rules in `CLAUDE.md`).
2. **Earn from the current client first:** deliver the deployment hardening + the
   four focused automation batches (email/notifications, invoicing, client
   self-service, scheduling/dispatch) — the 2026-07-16 platform pass — on the
   current stack.
3. **Defer multi-tenancy to a dedicated later phase (Phase 8), not started now.**
   For the first few paying customers, the bridge is **instance-per-tenant**
   (a separate Coolify stack + database per customer) — simple, fully isolated, no
   schema change — until shared-schema multi-tenancy is worth building.
4. **Keep the greenfield / enterprise proposals parked** (ADR-0001…0006 remain
   `Proposed`/`Accepted (defer)`), to be revisited only against ADR-0006's triggers
   — most notably a decision to move to PostgreSQL, which would re-open
   [ADR-0003](0003-tenancy-isolation-model-and-enforcement.md) to use Postgres RLS
   for DB-enforced tenant isolation.

## Consequences

- **Positive:** no rewrite risk; the working, deployed, test-green system keeps
  shipping value to the paying client; multi-tenancy stays a clean, deliberate
  future project rather than a rushed retrofit; instance-per-tenant gives real
  isolation for early customers with zero new code.
- **Negative:** instance-per-tenant does not scale operationally past a handful of
  customers (per-instance ops, no shared admin); shared-schema multi-tenancy debt is
  deferred, not eliminated; if a customer contractually demands DB-enforced
  isolation, the MySQL app-layer guards may force the Postgres+RLS decision earlier.

## Revisit triggers

- More than a few paying customers make instance-per-tenant ops painful → build
  Phase 8 (shared-schema multi-tenancy, or Postgres RLS).
- A customer requires DB-enforced cross-tenant isolation → re-open ADR-0003.
- The additive mobile/AI services (ADR-0006) are greenlit → they attach to this
  backend via API, still no rewrite.
