# Dominio: edilizia italiana (cantiere)

Reference for the Italian construction domain the platform serves — the legal
obligations an *impresa edile* carries, the vocabulary, and how each maps to app
entities. The client is a reconstruction firm in the **Marche** region (post-sisma
2016), so the compliance surface below is not optional paperwork: missing or expired
documents can trigger fines or a site shutdown.

> Scope note: the v2 foundation landed the **schema** for every item below plus the
> multi-site inventory. The user-facing features — the Badge di Cantiere clock in/out
> (`/attendance`) + admin register, the Giornale dei Lavori form with Open-Meteo weather
> and closed-day lock, the S.A.L. generator (locked PDF + DL sign-off), and the
> Scadenzario Sicurezza CRUD + ≤30-day dashboard widget — are now **delivered** (v2
> Phases 3–8). See [ROADMAP.md](ROADMAP.md) and [CHANGELOG.md](../CHANGELOG.md).

## Legal obligations

| Obligation | What it is | Legal reference | App mapping |
|-----------|------------|-----------------|-------------|
| **Badge di Cantiere Digitale** | Digital attendance: who was on site, when they entered/left, ideally with geolocation. | Decreto 332/2026 (digital site badge) | `site_attendance` (per project, `entry_at`/`exit_at`, entry/exit lat/lng). Subjects: `users` (workers) and `subcontractors`. |
| **Giornale dei Lavori** | Daily works log kept by the Direttore dei Lavori: weather, workforce present, equipment, work performed. Entries are **immutable once the day is closed**. | DPR 380/2001 (Testo Unico Edilizia) | `daily_logs` (one per `project_id`+`log_date`, `is_closed` lock) + `equipment`/`daily_log_equipment`. Weather auto-filled from `projects.lat/lng`. |
| **S.A.L. — Stato Avanzamento Lavori** | Work-progress statement: a numbered document certifying the value of work done in a period, used for staged invoicing and DL sign-off. | Contract/appalto milestones; issued to the Direttore dei Lavori | `sal_documents` (numbered per project, `draft`→`issued`→`signed`) + `sal_lines` (priced items). Item pricing uses `warehouse_items.unit_cost`. |
| **Scadenzario Sicurezza** | Expiry tracking for mandatory safety/compliance documents so nothing lapses. | D.Lgs. 81/2008 (safety), DURC rules, Patente a Crediti (D.L. 19/2024) | `compliance_documents` (`doc_type`, `expiry_date` indexed, `credits` for the patente). Dashboard surfaces items expiring ≤30 days. |

## Glossary

| Term | Meaning |
|------|---------|
| **Cantiere** | Construction site. In the app, each project auto-gets a `stock_locations` row of kind `site` — its cantiere — so material can be transferred warehouse→cantiere and tracked with a per-site balance. |
| **Impresa edile** | Construction/building firm (the app's owner/operator). |
| **Subappalto / Subappaltatore** | Subcontract / subcontractor — a company working under the main contractor on a project. Modelled by `subcontractors` (M:N to projects via `project_subcontractors`) and a `subcontractor` user role. |
| **Direttore dei Lavori (DL)** | Works Director — the professional (often an engineer/architect) supervising execution, who keeps the Giornale dei Lavori and signs off each S.A.L. |
| **S.A.L.** | *Stato Avanzamento Lavori* — see the table above. |
| **DURC** | *Documento Unico di Regolarità Contributiva* — single certificate that a firm is up to date on social-security/insurance contributions. Time-limited; tracked in `compliance_documents`. |
| **POS** | *Piano Operativo di Sicurezza* — a firm's site-specific safety plan. |
| **PSC** | *Piano di Sicurezza e Coordinamento* — the coordination safety plan for the whole site (multiple firms). |
| **Patente a Crediti** | Credit-based "licence" required to operate on construction sites (from 2024): a firm starts with a credit balance that can be reduced by violations. Tracked via `compliance_documents.doc_type = 'patente_crediti'` and the `credits` column. |
| **Giacenza** | Stock on hand. `warehouse_items.qty_in_stock` is the main-warehouse giacenza; `stock_balances` holds the per-location giacenza. |
| **Trasferimento** | A stock transfer between two locations (warehouse↔cantiere), recorded as a paired `transfer_out`+`transfer_in` in the ledger. |

## How the domain maps to the ledger

Materials for a cantiere are (optionally) transferred from the **Magazzino Centrale**
(location 1) to the project's **site** location, then reserved/consumed by
interventions. The inventory ledger (`stock_movements`) stays the single source of
truth; per-location `stock_balances` and the main-warehouse `qty_in_stock` are caches
recomputed from it. See [DATA_MODEL.md](DATA_MODEL.md) for the sign convention and the
transfer semantics.
