# ADR-0006 — Future field & AI platform direction (parked for later)

- **Status:** Accepted (decision: **defer**; continue evolving the current platform)
- **Date:** 2026-07-12
- **Deciders:** Engineering (owner)
- **Supersedes:** —
- **Related:** [ADR-0001](0001-enterprise-architecture-direction.md), [ADR-0002](0002-framework-selection-and-migration-mechanics.md), [ADR-0003](0003-tenancy-isolation-model-and-enforcement.md)

## Context

A modern, greenfield stack was proposed for taking the product to enterprise +
mobile + AI (details below). It is attractive and contains ideas worth pursuing.
However, there is a **client meeting imminent and the platform cannot change now**,
and ADR-0002 already decided to **evolve the existing PHP system via a strangler
migration** rather than attempt a big-bang rewrite (live customers, correct
ledger/financial logic, 455 tests).

This ADR **records the proposed alternative and its assessment so the decision is
made on the record and the good ideas aren't lost** — it does *not* adopt the full
stack now. It is a "parked" direction with explicit triggers for revisiting.

## The proposed alternative stack (captured verbatim in intent)

**1. Mobile / field layer**
- React Native (Expo) to share TS business logic with the web dashboard.
- Offline-first sync engine: PowerSync or WatermelonDB + local SQLite; snap
  photos / clock in / log materials fully offline, sync on reconnect.
- Geolocation: react-native-maps + Expo Location (check-ins, geo-fencing sites).

**2. Admin dashboard / office layer**
- Next.js (React) on the T3 stack (Next.js, TypeScript, tRPC, Tailwind).
- State/data: Zustand or TanStack Query for large real-time tables.
- UI: shadcn/ui or MUI.

**3. Database & backend core**
- PostgreSQL (relational — correct for financial/inventory data).
- Supabase (managed Postgres + Auth + Edge Functions), with **PostGIS**
  (distances/site perimeters) and **pgvector** (AI embeddings).
- ORM: Prisma or Drizzle (Drizzle favored for performance/edge).

**4. AI & automation layer (the competitive edge)**
- Meta WhatsApp Business API so workers text a bot instead of opening the app.
- LLM parser: unstructured voice/text → strict JSON for the DB.
- Document parsing: AWS Textract / DocumentAI for PDFs (computo metrico,
  supplier invoices) → rows.

**5. Infrastructure / DevOps**
- Hosting: Vercel (Next.js). File storage: S3 / Supabase Storage.
- AI-first editor (Cursor) for a small team.

## Assessment (summary of the review)

**Genuinely right / worth adopting**
- **Offline-first is the strongest idea** — near table-stakes for construction;
  the current server-rendered PWA can't truly deliver it.
- **PostgreSQL over MySQL is well-justified** here: Postgres has **row-level
  security** (directly strengthens the ADR-0003 tenancy enforcement that MySQL
  can't back-stop), plus PostGIS (geo) and pgvector (AI).
- **WhatsApp + LLM intake is the real differentiator** — removes the field
  data-entry friction that plagues this category.

**Two hard pushbacks**
1. **Offline-first collides with ledger integrity.** The domain depends on the
   `stock_movements` ledger being the source of truth with `SELECT … FOR UPDATE`
   locking. Authoritative *offline* writes cannot preserve that (two offline
   workers consume the same last stock → corruption on sync). **Binding
   constraint (below).**
2. **Large new surface for a small team** — offline sync, multi-tenant RLS, and
   AI-into-financials are each hard subsystems; AI tooling speeds typing, not
   essential complexity.

**Component cautions**
- **Supabase:** great accelerator, but its Auth is weaker on enterprise SSO/SCIM
  than a dedicated broker (ADR-0004); the `service_role` key **bypasses RLS**
  (one leak = cross-tenant exposure); BaaS ceilings/lock-in at scale.
- **tRPC vs sync seam:** PowerSync syncs mobile **directly against Postgres**,
  bypassing tRPC → two data-access paradigms to reconcile.
- **LLM → financial/stock writes must be human-in-the-loop** (proposed rows a
  human confirms; raw note retained for audit). Named models (Claude 3.5 Sonnet,
  GPT-4o) are already superseded — use current generation (e.g. Claude Opus 4.8 /
  Sonnet 5 / Haiku 4.5; Haiku-class is enough for structured extraction).
- **Compliance:** WhatsApp/Meta + US LLM + Textract routes PII/jobsite data
  outside the EU → GDPR/data-residency workstream (may force an isolated/EU tier).
- **Cost/lock-in:** Vercel + Supabase + per-message WhatsApp + per-token LLM +
  Textract = five metered vendors; model unit economics per active site early.

## Decision

1. **Continue evolving the current PHP → Laravel platform** per ADR-0001…0005.
   Do **not** adopt the greenfield stack now.
2. **Adopt the two winning ideas incrementally, as additive services** on top of
   the proven system-of-record — not as a rewrite:
   - a **React Native offline-first mobile field app**, and
   - a **WhatsApp / LLM intake service**,
   both talking to the existing backend via API.
3. **Park the full stack-swap** (PHP→TS/Supabase) as a future option, revisited
   only against the triggers below.

## Binding constraint carried forward (applies to any mobile/AI work)

- **Field entries are append-only *proposed events*** ("worker X reports using 4
  bags at site Rossi"), which the **server reconciles against the ledger** — they
  are **not** authoritative stock/balance writes. Photos, clock-ins, and material-
  *usage events* sync offline safely (append-only); **stock balances and financial
  postings stay server-authoritative** behind the existing transactional locking.
- **LLM/OCR outputs touching stock or money are human-confirmed** (or auto-
  confirmed only above a confidence threshold, with the source note attached for
  audit). No silent AI writes into the ledger.

## Revisit triggers (when to reconsider the greenfield path)

- The current app is reclassified as a disposable prototype (no meaningful
  customer/data lock-in to preserve).
- A move to **PostgreSQL** is on the table anyway — then re-open ADR-0003 to use
  **Postgres RLS** for tenancy (a strict improvement over the MySQL app-layer
  guards), and re-evaluate Supabase for the core.
- The mobile/AI additive services outgrow the PHP backend's API and a unified
  TS monorepo would materially reduce duplication.
- Team scales enough to own offline-sync + RLS + AI subsystems concurrently.

## Consequences

- **Positive:** platform stability through the client engagement; no rewrite risk;
  the good ideas are recorded and pursued incrementally; a clear, testable safety
  boundary (proposed-events vs authoritative ledger) is set for all field/AI work.
- **Negative:** duplication between a PHP backend and a TS mobile/AI layer in the
  interim; some ideas (Postgres RLS, unified monorepo) deferred rather than taken.

## Open questions (unchanged from the series)

- Is the current app a keeper (assumed **yes** here) or a throwaway prototype?
- Team size / timeline / budget for the additive mobile + AI services?
- Willingness to move to PostgreSQL (would improve tenancy regardless of path)?
