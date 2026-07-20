# ADR-0009 — Invoicing automation scope: S.A.L.→draft invoice; defer FatturaPA & recurring

- **Status:** Accepted
- **Date:** 2026-07-16
- **Deciders:** Engineering (owner)
- **Supersedes:** —
- **Related:** [ADR-0007](0007-evolve-existing-stack-earn-from-one-client-first.md)

## Context

The "invoicing & recurring docs" automation priority originally scoped three things:
(a) auto-draft an invoice from completed work, (b) recurring invoices, and (c)
FatturaPA/SDI e-invoicing groundwork. During the pass we re-examined each against how
this business actually bills and against ADR-0007 (earn from one client first, no
gold-plating).

Domain facts that shaped the decision:

- Italian construction billing is **milestone-based via S.A.L.** (Stato Avanzamento
  Lavori / progress billing), not fixed monthly subscriptions.
- The project's own docs record that **e-invoicing (SDI/FatturaPA) is handled by the
  client's commercialista**, not this system.
- `project_invoices` already exists with a working `FinancialsService` for the money
  math and a PDF builder.

## Decision

1. **Ship: one-click draft invoice from an issued/signed S.A.L.**
   (`SalController::toInvoice`). It creates a **draft** `project_invoices` row with the
   amount taken from the S.A.L. and a suggested next invoice number, then hands off to
   the normal invoice edit flow. Draft-only and fully editable — the automation removes
   re-keying, it does not post anything final or irreversible.
2. **Defer recurring invoices.** Construction billing is milestone/S.A.L.-driven;
   a cadence-based recurring generator would model a billing pattern this client does
   not use. Not built.
3. **Defer FatturaPA/SDI e-invoicing** (including the XML-export stub). The
   commercialista/SDI own e-invoicing; certified SDI transmission is legally intricate
   and out of scope for this round. Recorded as a candidate for a dedicated later phase.

## Consequences

- **Positive:** the delivered automation matches the real billing workflow and cuts
  the manual step that actually hurts (turning an approved S.A.L. into an invoice);
  draft-only output keeps a human in the loop over every number that leaves the
  system; no speculative subsystems (recurring engine, SDI XML) to maintain.
- **Negative:** invoices are not auto-finalized or transmitted — a human still issues
  and the commercialista still files with SDI; if the client later wants in-system
  FatturaPA, it is a fresh, dedicated project (fiscal fields, XML builder, SDI
  transmission, testing) rather than an extension of this pass.

## Revisit triggers

- The client wants to issue e-invoices from this system → dedicated FatturaPA phase.

> **Revisited 2026-07-20.** The "issue e-invoices from this system" trigger fired. A dedicated
> FatturaPA phase was built: structured fiscal fields (company + client), a real fiscal invoice
> (line items, IVA, natura/reverse charge, ritenuta, bollo, split), a FatturaPA v1.2 XML builder
> + pre-flight validator, and an e-invoice preparation pipeline (store → optional CAdES sign →
> lifecycle ledger). Signing and transmission are **off by default** and require the firm's
> certificate + SdI provider (see docs/CONFIGURATION.md); XML generation needs no config. The
> plain-XML-to-commercialista path this ADR chose remains valid and is still the default.
- A billing pattern emerges that is genuinely periodic (e.g. maintenance contracts) →
  reconsider recurring invoices.
