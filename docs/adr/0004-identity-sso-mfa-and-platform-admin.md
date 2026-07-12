# ADR-0004 — Identity: SSO, MFA, provisioning, and platform admin

- **Status:** Proposed
- **Date:** 2026-07-11
- **Deciders:** Engineering (owner to confirm)
- **Supersedes:** —
- **Related:** [ADR-0001](0001-enterprise-architecture-direction.md), [ADR-0002](0002-framework-selection-and-migration-mechanics.md), [ADR-0003](0003-tenancy-isolation-model-and-enforcement.md)

## Context

ADR-0001 named SSO/MFA and an audit trail as security/compliance requirements.
ADR-0002 chose Laravel and, during the strangler migration, keeps the existing
**email + password** so the legacy and new apps can share a Redis session.
ADR-0003 introduced **per-tenant roles** and flagged a **platform super-admin**
that operates outside tenant scope, deferring the identity design to here.

Today's authentication is local: `users` rows with a password hash, roles
`admin`/`worker`/`client`, `AuthGuard::require()` on every endpoint, ownership
guards that return **404 for "not mine"**, CSRF, and rate limiting (login attempt
lockout in `login_attempts`). There is no SSO, no MFA, and no organization-level
identity configuration.

Enterprise buyers typically require: **SSO (SAML 2.0 and/or OIDC)** federated to
their IdP (Okta, Entra ID, Google Workspace), **MFA** for local accounts,
**SCIM** user provisioning/deprovisioning, and a clear separation between tenant
users and platform staff.

## Decision drivers

- Meet enterprise SSO/MFA/SCIM expectations without building an IdP.
- **Per-tenant identity configuration** — each tenant federates to *its own* IdP;
  identity resolution must compose with ADR-0003 tenant resolution.
- Preserve current semantics (roles, "404 for not-mine", attempt lockout) and the
  shared-session migration path from ADR-0002.
- Strong separation for platform staff (support/ops) with audited, least-privilege
  cross-tenant access.
- Avoid storing more credential material than necessary (compliance).

## Considered options

### Federation / SSO layer

**A. Managed auth platform (Auth0 / WorkOS / Clerk).**
- **+** SSO (SAML+OIDC), SCIM, MFA, directory sync as a service; fastest path to
  enterprise-readiness; offloads protocol/security maintenance. WorkOS in
  particular targets B2B multi-tenant SSO/SCIM.
- **−** Per-MAU cost; an external dependency in the auth path; some data
  (identities) leaves our boundary — must be checked against tenant compliance.

**B. Self-hosted IdP/broker (Keycloak).**
- **+** Full control, no per-user fee, on-prem/in-region friendly (data residency).
- **−** We operate a security-critical HA service (realms, upgrades, backups); real
  ops burden.

**C. Build on Laravel packages directly (Socialite + a SAML lib + Fortify MFA).**
- **+** No third party; cheapest at small scale; full control.
- **−** We own SAML/OIDC/SCIM edge cases and their security patches — exactly the
  bespoke burden ADR-0001 chose to stop taking on.

### MFA (for local, non-SSO accounts)
- TOTP (authenticator apps) + recovery codes via **Laravel Fortify**; optional
  WebAuthn/passkeys later. (When a tenant uses SSO, MFA is enforced at their IdP.)

### Platform admin identity
- **P1. Same users table, super-admin flag** — simple, but one compromised row
  crosses all tenants; weaker separation.
- **P2. Separate platform-admin identity + its own MFA-required login, distinct
  from tenant auth** *(preferred)* — stronger blast-radius control and auditability.

## Decision

1. **Adopt a managed B2B auth platform (recommendation: WorkOS; Auth0 as
   alternative) as the SSO/SCIM broker**, configured **per tenant** so each
   organization federates to its own IdP. Final vendor pinned after the compliance
   review in the open questions.
2. **Local accounts** (tenants without SSO, plus break-glass) use Laravel
   **Fortify** with **TOTP MFA + recovery codes**; keep the existing attempt
   lockout (`login_attempts`) semantics.
3. **Platform staff use a separate identity** (P2) with mandatory MFA, isolated
   from tenant auth, and **all cross-tenant actions are written to the audit log**.
4. Provisioning via **SCIM** where the tenant's IdP supports it; otherwise
   just-in-time (JIT) provisioning on first SSO login, mapping IdP groups → tenant
   roles.
5. **Authorization stays ours.** SSO/SCIM decide *who you are*; the app's
   per-tenant RBAC (ADR-0003) decides *what you can do*. Keep policies/gates and
   the **404-for-not-mine** rule unchanged.

## Mechanics

### Identity resolution (composes with ADR-0003)
- Tenant is resolved first (ADR-0003 middleware). The tenant record carries its
  **identity config**: `auth_mode` (`local` | `sso`), IdP connection id, allowed
  domains, SCIM enablement, MFA policy.
- SSO login → broker performs SAML/OIDC with the tenant's IdP → returns a verified
  identity → we map it to a `users` row **within that tenant** (JIT-create if
  absent), then establish the normal session.

### User model & data
- `users` gains: `tenant_id` (ADR-0003), `auth_provider` (`local`|`sso`),
  `external_id` (IdP subject, unique per tenant), MFA fields (secret, confirmed_at,
  recovery codes — encrypted), and existing role.
- **We never store IdP passwords.** For SSO users the local password hash is null.
- Group/role mapping table: IdP group → tenant role, applied on each SSO login and
  on SCIM updates (single source of truth = the IdP when SSO is on).

### Sessions & the migration window (ADR-0002)
- Sessions remain a **shared Redis store** with one cookie contract so legacy and
  Laravel apps interoperate. SSO/MFA are implemented in the **Laravel** app;
  routes needing SSO are moved to Laravel first. The legacy app continues to
  accept the shared session for not-yet-ported routes but does **not** implement
  SSO itself.

### Deprovisioning
- SCIM `deactivate` (or IdP-side disable) → immediate session revocation
  (Redis) + user disabled. This is a compliance requirement, not a nicety:
  offboarding at the tenant's IdP must promptly cut access here.

### Audit
- Login, MFA enrollment/challenge, SSO connection changes, SCIM events, role
  changes, and **every platform-admin cross-tenant action** are appended to the
  audit log (generalize the existing `intervention_status_history` pattern per
  ADR-0001).

## Consequences

**Positive**
- Enterprise SSO/MFA/SCIM without operating an IdP or owning protocol security.
- Per-tenant federation fits the multi-tenant model cleanly.
- Platform-staff blast radius is contained and audited.
- Authorization stays in-app, so current RBAC/ownership semantics are preserved.

**Negative / costs**
- Per-MAU vendor cost and an external dependency in the login path (needs an
  availability/fallback story — break-glass local admin with MFA).
- Identity data touches a third party → must clear each tenant's compliance/data-
  residency terms (may push some tenants to the isolated tier or Keycloak).
- More `users` fields and mapping logic; SCIM edge cases to test.

**Risks & mitigations**
- *Broker outage locks everyone out* → retain MFA-protected **break-glass** local
  admin per tenant; cache short-lived sessions; document runbook.
- *IdP group→role mismapping grants over-privilege* → default-deny mapping,
  reviewed per tenant; changes audited.
- *Stale access after offboarding* → SCIM deactivate + immediate Redis session
  revocation; periodic reconciliation job.
- *Data-residency conflict with a managed broker* → offer Keycloak/self-host or the
  ADR-0003 isolated tier for those tenants.

## Open questions

- **Vendor:** WorkOS vs Auth0 vs self-hosted Keycloak — decide after the
  compliance/data-residency and pricing review.
- Minimum viable at launch: is **local + MFA** enough for v1, with **SSO/SCIM**
  as a fast-follow, or is SSO a launch blocker for the first enterprise customer?
- **Tenant resolution strategy** (still open from ADR-0003) directly shapes SSO
  callback URLs and cookie scope — must be settled with this ADR.
- Passkeys/WebAuthn for local accounts now or later?
