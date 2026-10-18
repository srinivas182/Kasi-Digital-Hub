# ADR-030: Performance and scale

- **Status:** Accepted
- **Date:** 2026-10-17
- **Sprint:** S22

## Decisions

1. **Laravel Octane with Swoole** (Octane 2.20.0, Swoole 6.0.2 - versions at least two weeks old). Memory-safety audit:
   singletons are stateless drivers/registries; the fake drivers' static state is reset after every request
   (`App\Octane\ResetFakeDrivers`); `OctaneSafetyTest` fails on any new static state. CI runs the browser suite under
   Octane.
2. **Lazy loading is an error outside production**; key pages have tested query budgets (`QueryBudgetTest`).
3. **Indicators are counted by the database** (one grouped query per indicator) with an age range applied as a join on
   date of birth, a covering index `(name, hub_id, occurred_at, user_id)`, and a 5-minute cache of aggregates. Daily
   pre-computed totals were rejected: "different people this month" cannot be summed from days.
4. **Text is sent once** per language and text version (Inertia once-prop), not with every page.
5. **Files** are delivered by 5-minute presigned links when stored in S3-compatible object storage (local disk streams
   as before); permission is checked first, every time.
6. **Trusted proxies by configuration** (`TRUSTED_PROXIES`), so rate limits and audit logs see real visitor IPs behind
   a load balancer.
7. **First-load budget stays 130 KB**: the entry bundle is framework code (React, Inertia); our own code in it is ~2 KB.
   The once-only text saves more data on every page than trimming the first load would.
8. **Capacity is proven on staging (S24)** with the k6 kit (`loadtest/`) and `kasi:perf:seed`; see `docs/capacity.md`.
