# Gap analysis — production readiness (Hetzner) & platform completeness

> **Status update (same day):** every 🔴 and 🟠 item below was closed by the
> v1.1 release — see [CHANGELOG.md](../CHANGELOG.md) for the fix-by-fix mapping.
> Only the 🟡 items in §2 (F6–F7) and §5 remain open by design (client decisions).
> This document is kept as the "before" snapshot that motivated the roadmap.

Assessment date: 2026-07-02. Baseline: full v1 implementation of the original
8-phase specification (see `superprompt.md`), verified by direct code review of
every source file.

Severity: 🔴 blocker (must fix before going live) · 🟠 important (fix in first
production iteration) · 🟡 nice-to-have.

## 1. Security

| # | Gap | Severity | Detail |
|---|-----|----------|--------|
| S1 | **No CSRF protection** | 🔴 | Zero CSRF tokens in the codebase. Every state-changing endpoint (`POST /admin/*`, `/worker/*`, login) is callable cross-site. Since auth is cookie-based with `SameSite=Lax`, top-level form POSTs from a malicious site would succeed. Fix: session CSRF token + `X-CSRF-Token` header on all AJAX (all writes are AJAX), enforced centrally. |
| S2 | **No login rate limiting** | 🔴 | `POST /login` allows unlimited brute force. Fix: per-email+IP throttling table with lockout window. |
| S3 | **Session hardening incomplete** | 🟠 | Cookie is `httponly` + `SameSite=Lax` but never `Secure`; no `use_strict_mode`; no idle timeout. Fix: `Secure` flag (config-driven), strict mode, absolute/idle lifetime. |
| S4 | **Debug defaults to ON** | 🔴 | `Config::get('app.debug', true)` and `.env.example APP_DEBUG=true` — a missing env var in production would leak stack traces. Fix: default `false` everywhere; enable explicitly in dev. |
| S5 | **No security headers** | 🟠 | No `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, CSP, HSTS. Fix: send centrally from the front controller; HSTS at the web-server layer. |
| S6 | **Assets from public CDN** | 🟠 | Bootstrap/jQuery from jsdelivr: breaks the offline-first worker app when the CDN is unreachable, third-party dependency in login page, GDPR-relevant. Fix: self-host under `public/assets/vendor/`. |
| S7 | **No password management** | 🟠 | No password change; all seed users share `password`. Fix: change-password for every logged-in user + admin reset. |
| S8 | **`/health` leaks nothing but is unauthenticated** | 🟡 | Acceptable (needed for monitoring); keep response minimal. |
| S9 | **Logout via GET** | 🟡 | `GET /logout` is CSRF-triggerable (annoyance only). Fix alongside S1 by making the navbar logout a POST with token. |
| S10 | **No audit of auth events** | 🟡 | Failed/successful logins are not logged. Fix with S2 (attempts table doubles as audit). |

Verified non-gaps (already correct): output escaping via `View::e()` throughout;
prepared statements everywhere; upload validation by content sniffing
(`getimagesize`) + size caps; uploads outside the web root, streamed through
permission-checked controllers; ownership guards that don't leak existence;
password hashing via `password_hash()`; global exception handler that never
shows stack traces when debug is off.

## 2. Platform completeness (client-facing functionality)

| # | Gap | Severity | Detail |
|---|-----|----------|--------|
| F1 | **No user management UI** | 🔴 | Users can only be created by the seed script. Admin must be able to create/edit/deactivate workers and client logins, and reset passwords — otherwise the platform is unusable in production. |
| F2 | **Admin cannot see intervention detail** | 🟠 | Admin list shows materials but there is no detail page: no photos, no status history, no signature, no completion notes. Admin also has **no photo routes at all** (only worker/client streaming exists). Fix: `/admin/interventions/{id}` detail page + admin photo/signature streaming. |
| F3 | **Dashboard is an empty shell** | 🟠 | Static navigation cards only. A real operations dashboard is a cheap, high-value win: counts (active projects, today's interventions by status), low-stock alert list (`qty_in_stock ≤ reorder_level`, already in the schema but unused), recent activity. |
| F4 | **Worker sees only today** | 🟠 | No way to see tomorrow's or overdue tasks, nor recently completed work. Fix: simple "Oggi / Prossimi / Completati" tabs on the worker list. |
| F5 | **No low-stock signal** | 🟠 | `reorder_level` exists; warehouse list has a "Sotto scorta" string but nothing surfaced prominently. Tie into F3. |
| F6 | **No pagination** | 🟡 | All lists load everything. Fine at current scale (hundreds of rows); revisit when data grows. |
| F7 | Client cannot see completion notes / signature | 🟡 | Only photos + report. The PDF report contains them — acceptable for v1.1. |
| F8 | Labor hours, GPS check-in | ✅ | GPS check-in shipped (Badge di Cantiere). Labor-hours **costing** added 2026-07-19 (migration 025): `hourly_rate` on workers/subcontractors, `LaborCostService`, `/admin/financials/labor` report, folded into the project P&L. |

## 3. Deployment & operations (Hetzner)

| # | Gap | Severity | Detail |
|---|-----|----------|--------|
| D1 | **No deployment artifacts** | 🔴 | No Dockerfile/compose, no nginx/PHP-FPM config, no production guide. Fix: Docker Compose stack (nginx + php-fpm + MySQL 8) + step-by-step Hetzner guide (firewall, TLS via Let's Encrypt, domain). |
| D2 | **No backup strategy** | 🔴 | Data (DB) + uploads (photos/signatures) are business-critical. Fix: nightly `mysqldump` + uploads archive, rotation, restore procedure documented and tested. |
| D3 | **No production error logging destination** | 🟠 | `error_log()` goes wherever PHP is configured. Fix: explicit log file under `storage/logs/` (git-ignored) + rotation note; keep stderr in Docker. |
| D4 | **No HTTPS enforcement** | 🟠 | App never redirects to HTTPS (must be at proxy) and session cookie isn't `Secure` (S3). Handled by the nginx config + S3. |
| D5 | **Uploads dir must be writable & persistent** | 🟠 | Document volume mounting + permissions in the deploy guide. |
| D6 | **No CI** | 🟡 | Once a test suite exists (T1), a GitHub Actions workflow is trivial. Optional for a single-server deployment. |
| D7 | PHP `upload_max_filesize`/`post_max_size` defaults (2M/8M) < app cap (8M) | 🟠 | Client-side compression masks it, but raw camera uploads can exceed it. Set explicit PHP ini values in deployment. |

## 4. Quality / testing

| # | Gap | Severity | Detail |
|---|-----|----------|--------|
| T1 | **No automated tests at all** | 🔴 | The hardest logic (ledger math, state machine, RBAC, completion gate) is untested. Fix: dependency-free PHP test runner in `/tests` against a dedicated `_test` database + an HTTP end-to-end simulation (login as each role, full create→reserve→complete cycle, cross-role access attempts, CSRF behavior). |
| T2 | Repo hygiene | 🟠 | ~40 accidental zero-byte files with shell-fragment names (`$id`, `'Cantieri`, `prepare(,` …) are committed at the repo root. Remove from git and disk. |
| T3 | `superprompt.md` in repo root | 🟡 | Historical spec; keep (referenced by docs) — it documents intent. |

## 5. Explicitly deferred (documented, not planned now)

- ~~Full PWA/service-worker offline queue (spec §8 stretch goal; localStorage retry queue exists).~~
  **Done (2026-07-19):** unified IndexedDB **outbox** replaces the localStorage queues,
  covering timbrature, intervention status/completion and photos; flushed on reconnect and
  via service-worker Background Sync (survives a closed tab). See CHANGELOG.
- ~~Push notifications (PWA exists but no web-push).~~ **Done (2026-07-19):** dependency-free
  VAPID Web Push (openssl only), contentless tickle + `/push/pending` fetch. Wired to the
  scheduler (admins), client quote/invoice events, and **workers** (overdue + newly-assigned
  interventions, user-scoped feed). Opt-in on the Badge di Cantiere screen.
- S3/object storage (StorageInterface ready).
- Multi-tenancy, e-invoicing, cost/pricing module — out of original scope; would need
  client sign-off on schema changes first.

## Conclusion

The core business engine (inventory ledger, state machine, RBAC, reporting) is
solid and matches the spec. What separates this from a production platform is:
**(a)** security hardening (CSRF, rate limiting, debug default, session, headers),
**(b)** the administrative surface (user management, intervention detail,
operational dashboard), **(c)** deployment/backup infrastructure for Hetzner, and
**(d)** an automated test suite proving the invariants. These are addressed in
phases in [ROADMAP.md](ROADMAP.md).

---

## 6. v2 gaps — Italian construction platform (assessment 2026-07-06)

v1.1 is production-grade but is a *generic* field-service system. To be the
"full-fledged platform" the client (an *impresa edile* in the Marche region) needs,
it must gain the Italian construction legal + operational feature set. Domain detail:
[DOMAIN_IT.md](DOMAIN_IT.md). Severity here: 🔴 legal/fine risk · 🟠 operational · 🟡 QoL.

| # | Gap | Sev | Status |
|---|-----|-----|--------|
| V1 | **Single-location inventory** — no per-site stock; material can't be tracked warehouse→cantiere. | 🟠 | ✅ **closed (this PR)** — `stock_locations`, `stock_balances`, `StockTransferService`, location-aware ledger. |
| V2 | **`complete()` phantom-release bug** — released surplus for never-reserved materials, inflating stock. | 🔴 | ✅ **closed (this PR)** — release guarded by `is_reserved`; regression-tested. |
| V3 | **No cost data** — no `unit_cost`, so no valuation / accountant export / S.A.L. pricing. | 🟠 | ⏳ schema landed (`warehouse_items.unit_cost`); export in Phase 6. |
| V4 | **Badge di Cantiere** — no digital attendance / geolocation (Decreto 332/2026). | 🔴 | ⏳ schema landed (`site_attendance`); feature Phase 4a. |
| V5 | **Giornale dei Lavori** — no daily works log (DPR 380/2001). | 🔴 | ⏳ schema landed (`daily_logs`+equipment); feature Phase 4b. |
| V6 | **S.A.L. generator** — no progress-statement documents. | 🔴 | ⏳ schema landed (`sal_documents`/`sal_lines`); feature Phase 4c. |
| V7 | **Scadenzario Sicurezza** — no expiry tracking for DURC/POS/PSC/Patente a Crediti (D.Lgs. 81/2008). | 🔴 | ⏳ schema landed (`compliance_documents`); feature Phase 4d. |
| V8 | **No subcontractor role/portal** — subappaltatori can't be managed or given scoped access. | 🟠 | ⏳ schema landed (`subcontractors`, role enum, M:N); portal Phase 3. |
| V9 | **No true offline mode** — only a photo `localStorage` retry queue; thick-walled sites need PWA. | 🟠 | ✅ IndexedDB outbox (timbrature + status/completion + photos) + Background Sync (2026-07-19). |
| V10 | **No geolocated photos** — evidence lacks coordinates/time. | 🟡 | ⏳ schema landed (`photos.lat/lng/captured_at`); feature Phase 5a. |
| V11 | **Hardcoded-Italian report labels** — report builders bypass `lang/it.php`. | 🟡 | ⏳ chipped away as report files are touched (Phase 6). |
| V12 | **Deploy is plain-Docker only** — client wants Coolify on Hetzner. | 🟠 | ⏳ Phase 7. |

**This PR (v2 foundation)** closes V1 and V2 and lands the schema for V3–V10, so every
later phase builds on stable tables. Remaining phases are sequenced in
[ROADMAP.md](ROADMAP.md).

---

## 7. 2026-07-10 hardening pass — closed & remaining

A full code re-audit before the "sellable platform" milestone found **no security
blockers** (RBAC, CSRF, transactions, `FOR UPDATE` locking, prepared statements,
escaping all verified correct) but surfaced three **redesign regressions** and a set
of correctness/UX gaps — all now closed (see [CHANGELOG.md](../CHANGELOG.md)).

| # | Gap | Sev | Status |
|---|-----|-----|--------|
| H1 | **GPS clock-in/out dead** — the "juli" `app.js` rewrite dropped the attendance handlers; the headline field feature did nothing. | 🔴 | ✅ restored (+ offline queue) |
| H2 | **Change-password page dead** — missing `js-password-form` handler. | 🟠 | ✅ restored |
| H3 | **Blank dashboard KPI icons** — referenced a removed SVG sprite. | 🟠 | ✅ switched to Bootstrap-Icons |
| H4 | **No proactive alerting** — expiry/overdue/low-stock data existed but nothing notified. | 🟠 | ✅ notification bell + daily scheduler (+ optional e-mail) |
| H5 | Invoice/quote PDF filenames ignored their prefix; warehouse null-deref; client `during`-photo id exposure; S.A.L. upload uncapped; seed orphaned 010–014 tables. | 🟠 | ✅ all fixed + regression-tested |
| H6 | No pagination; N+1 on interventions list; missing status/ledger indexes. | 🟡 | ✅ Paginator on the 4 big lists; N+1 batched; migration 015 |
| H7 | Hardcoded Italian in report PDFs / error pages / `app.js`. | 🟡 | ✅ moved to `lang/it.php` (+ JS i18n bridge) |
| H8 | No client self-service on quotes. | 🟡 | ✅ accept/reject in the portal |

### Still open (deliberate, need client sign-off)
- **e-Fatturazione (FatturaPA / SDI)** — invoices are internal receipts; legal Italian
  e-invoicing through the Sistema di Interscambio is a separate, larger integration.
- **Multi-tenancy** — single-company install by design.
- **Push notifications / SMS** — in-app + e-mail cover the need for now.
- Pagination on the remaining lower-volume lists (clients/users/compliance/daily-logs)
  — the reusable `Paginator` makes each a small follow-up.
