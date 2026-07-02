# Gap analysis — production readiness (Hetzner) & platform completeness

> **Status update (same day):** every 🔴 and 🟠 item below was closed by the
> v1.1 release — see [CHANGELOG.md](../CHANGELOG.md) for the fix-by-fix mapping.
> Only the 🟡 items in §2 (F6–F8) and §5 remain open by design (client decisions).
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
| F8 | Labor hours, GPS check-in | 🟡 | Explicitly out of scope for v1 by the spec; schema is ready (`lat`/`lng`). |

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

- Full PWA/service-worker offline queue (spec §8 stretch goal; localStorage retry queue exists).
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
