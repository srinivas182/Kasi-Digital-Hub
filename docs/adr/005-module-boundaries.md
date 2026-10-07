# ADR-005: Modules communicate through contracts and events only

- **Status:** Accepted
- **Date:** 2026-10-07
- **Sprint:** S0

## Context

Portals are connected (a certificate in KasiLearn raises a match score in KasiWork) but must remain
independently changeable and removable.

## Decision

A module may use the shared kernel (`App\`, `Modules\Core\`) but never another portal's classes. Cross-portal
behaviour uses Core contracts and domain events (event catalogue, Sprint 4). Enforced by Pest architecture
tests.

## Consequences

- Portals can be added, removed or rewritten safely.
- Some indirection: cross-portal features go through events rather than direct calls.

## Alternatives considered

Free cross-module calls - rejected: tangled dependencies that make the Portal SDK impossible.
