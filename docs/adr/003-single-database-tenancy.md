# ADR-003: Single database with hierarchical tenant scoping

- **Status:** Accepted
- **Date:** 2026-10-07
- **Sprint:** S0

## Context

The platform runs many hubs nationally. Jobs, courses and mentors must form one national pool, while hub,
city, province and funder data must stay scoped.

## Decision

One MySQL database. Tenant-owned records carry hierarchy keys (province, city, hub, organisation) and are
scoped automatically by role (implemented in Sprint 3). Large funders can later receive a dedicated
deployment as a premium option.

## Consequences

- National network effects; adding a hub is configuration, not a new database.
- Scoping must be enforced centrally and tested for every model (Sprint 3 adds guard tests).

## Alternatives considered

Database per hub - rejected: breaks the national pool and becomes unmanageable at hundreds of hubs.
