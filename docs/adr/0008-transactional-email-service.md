# ADR-0008 — Transactional email as a config-gated, best-effort service

- **Status:** Accepted
- **Date:** 2026-07-16
- **Deciders:** Engineering (owner)
- **Supersedes:** —
- **Related:** [ADR-0007](0007-evolve-existing-stack-earn-from-one-client-first.md)

## Context

The platform already had `Support\Mailer` (SMTP/`mail()`, disabled unless
`MAIL_ENABLED=true`) wired only to the nightly digest. The 2026-07-16 pass needed
**transactional** email — notify the client when a quote is sent or an invoice is
issued — plus an in-app notification feed for those same events.

Constraints from the existing architecture:

- All user-facing text must go through `lang/it.php` (`Lang::get/label`) — no
  hardcoded Italian.
- Writes touching stock/status/money run in a DB transaction; **email must never be
  inside that transaction** (an SMTP timeout must not roll back a committed invoice).
- Email delivery depends on the client providing real SMTP credentials in Coolify
  secrets; until then the system must behave correctly with mail **off**.
- No new framework/mailer library (ADR-0007) — reuse `Support\Mailer`.

## Decision

1. Add **`src/Services/MailService.php`** as the single transactional-email surface.
   Message bodies are **pure builders** (`buildQuoteSent`, `buildInvoiceIssued`) —
   localized via `Lang`, unit-testable offline with no SMTP — wrapped by thin
   `quoteSent` / `invoiceIssued` / `test` send methods.
2. **Gate every send on `Mailer::isEnabled()`.** With mail disabled the service is a
   clean no-op; nothing crashes, and the in-app notification still fires.
3. **Send best-effort, after commit.** The controller commits the state transition
   first, then attempts the email; a mail failure is swallowed/logged, never
   surfaced as a 500 and never rolls back the business write.
4. Pair every transactional email with an **in-app notification** via
   `NotificationService` (see the client-scoped feed, ADR-0007 / migration 023), so
   the user is informed even when mail is off.
5. Provide an **admin "test email" action** that returns a clean `422 {ok:false}`
   when mail is disabled (proving configuration without a crash) and a `502` on a
   genuine send failure — RBAC-guarded to admins only.

## Consequences

- **Positive:** the app is fully functional with mail off (dev, and prod before SMTP
  is provisioned); business transactions are never at the mercy of the mail server;
  message building is deterministic and covered by offline tests
  (`tests/cases/21_mail_service.php`); one place owns transactional mail.
- **Negative:** best-effort delivery means a dropped email is silent to the user
  (mitigated by the always-on in-app notification); no delivery/bounce tracking or
  retry queue yet (acceptable at current volume — revisit if the client needs
  guaranteed delivery, which would justify a queue/relay like Postmark/Brevo).

## Alternatives considered

- **Send inside the transaction** — rejected: couples financial correctness to SMTP
  availability.
- **A durable mail queue now** — deferred: over-engineered for one client's volume;
  the after-commit best-effort path plus the in-app feed is sufficient today.
