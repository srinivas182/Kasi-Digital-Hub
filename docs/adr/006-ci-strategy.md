# ADR-006: CI on main with path filters, parallel jobs and a 10-minute budget

- **Status:** Accepted
- **Date:** 2026-10-07
- **Sprint:** S0

## Context

Work is merged sprint by sprint directly on `main`. GitHub Actions minutes must be used efficiently and CI
should pass or fail within about 10 minutes.

## Decision

- Checks are run locally before pushing; each sprint is pushed as one atomic commit, so CI runs once per sprint.
- One workflow on push to `main`; docs-only changes skip CI; PHP and JS jobs run only when their files change.
- Jobs run in parallel; superseded runs are cancelled.
- Larastan level 8, Pint, Pest (MySQL), TypeScript, ESLint, Prettier, Vitest, production build (incl. SSR),
  bundle budget and dependency audits.

## Consequences

- Fast feedback, low minute usage.
- Because there are no pull requests, local checks before pushing are mandatory.

## Alternatives considered

Full suite on every push to multiple branches - rejected: slow and wasteful.
