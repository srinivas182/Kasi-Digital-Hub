# ADR-001: Modular monolith with one module per portal

- **Status:** Accepted
- **Date:** 2026-10-07
- **Sprint:** S0

## Context

Release 1 has 12 portals plus a shared kernel, and later releases add more. We need portals to be built,
tested and switched on independently, without the operational cost of many services.

## Decision

One Laravel application. Each portal is a module in `modules/<Module>` with a `module.json` manifest,
auto-discovered by `ModuleServiceProvider`.

## Consequences

- One deployment, one database connection pool, simple operations for a small team.
- Clear boundaries enforced by architecture tests; high-traffic modules (matching, marketplace) can be
  extracted into services later if load requires.
- Every new module needs two PSR-4 lines in composer.json (guarded by a test).

## Alternatives considered

Microservices from day one - rejected: network, deployment and data-consistency overhead with no benefit at current scale.
