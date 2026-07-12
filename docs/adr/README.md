# Architecture Decision Records

This directory records significant architectural decisions for Gestionale
Muratori, one file per decision, using a lightweight
[Nygard-style](https://cognitect.com/blog/2011/11/15/documenting-architecture-decisions)
format.

Each ADR is immutable once **Accepted**: to change a decision, add a new ADR that
**supersedes** the old one (update the old one's status to point at the new one).

| ADR | Title | Status |
|-----|-------|--------|
| [0001](0001-enterprise-architecture-direction.md) | Direction for evolving to an enterprise, multi-tenant SaaS | Proposed |
| [0002](0002-framework-selection-and-migration-mechanics.md) | Framework selection (Laravel) and strangler migration mechanics | Proposed |
| [0003](0003-tenancy-isolation-model-and-enforcement.md) | Tenancy isolation model and enforcement | Proposed |
| [0004](0004-identity-sso-mfa-and-platform-admin.md) | Identity: SSO, MFA, provisioning, and platform admin | Proposed |
| [0005](0005-async-queue-and-worker-topology.md) | Async processing: queue technology and worker topology | Proposed |
| [0006](0006-future-field-and-ai-platform-direction-parked.md) | Future field & AI platform direction (parked for later) | Accepted (defer) |

## Statuses

`Proposed` → under discussion · `Accepted` → agreed, being implemented ·
`Superseded by ADR-N` · `Deprecated` · `Rejected`.

## Adding an ADR

Copy the structure of an existing file, use the next number, and add a row above.
Keep each ADR to one decision; split follow-on choices (e.g. the concrete tenancy
isolation model, the SSO provider, the queue technology) into their own ADRs.
