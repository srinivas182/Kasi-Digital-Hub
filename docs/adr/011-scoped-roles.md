# ADR-011: Scoped role assignments declared by modules (not spatie/laravel-permission)

- **Status:** Accepted
- **Date:** 2026-10-07
- **Sprint:** S3

## Context

The platform has 24 Release 1 roles held at different levels: a person's own account, an organisation, a hub,
a city, a province or nationally. People hold several roles at once. Portals must be able to add their own roles
later (Portal SDK). Hub staff may only deliver the portals their hub's package includes.

## Decision

- **Role assignments** store person + role + scope (`self | organisation | hub | municipality | province |
  national`) + optional expiry + who granted it.
- **Roles are declared by modules** in `module.json`, each with its scope, whether it is a staff role, and its
  access level per portal (`view < use < assist < manage`) - the agreed Portals & Roles matrix. An automated test
  holds the manifests to that matrix.
- `AccessResolver` computes a person's level per portal (highest of their roles) and the hubs they may see;
  hub-scoped roles only count for portals their hub has switched on (`HubEntitlements`). Results are cached and
  invalidated by an access version (role changes) and an entitlements version (package changes).
- Checks: `portal:Module,level` route middleware, the `portal` gate, `BelongsToHub::visibleTo()` for queries.
  A guard test fails if a model with a hub column does not use the trait.
- Staff, organisation and national roles switch the authenticator second step on automatically.

## Consequences

- One clear, auditable model for mixed scopes; new portals add roles without shared-code changes.
- Fine-grained permissions inside a portal are added by each module as it is built (on top of access levels).
- We maintain the access code ourselves (small and fully tested).

## Alternatives considered

- spatie/laravel-permission - rejected: its "teams" support one scope dimension per assignment and keeps
  permissions in database tables, away from module manifests.
- spatie with a synthetic "scope key" as team - workable but awkward and harder to audit.
