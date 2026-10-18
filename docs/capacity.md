# Capacity - measurements and sizing plan (S22)

## Definition (agreed target)

"100 000 concurrent users" = 100 000 signed-in people active within 5 minutes: about **3 300 requests/second on
average, designed for 10 000/s at peaks**. Targets: p95 server time **<= 300 ms** for young people's pages
(<= 800 ms for staff consoles), errors **< 0.1%**, no lost queue jobs.

## What was measured in S22 (sandbox - small shared CPU, SQLite, debug mode)

| Measurement | Result |
|---|---|
| Browser suite under Octane (Swoole 6.0.2) | 174 passed, 0 failed - no state carried between requests |
| Public home page, 30 requests, classic PHP vs Octane | median 77 ms vs 61 ms (p95 95 vs 81 ms) |
| Key pages - database queries (demo data) | 7-35 per page; budgets enforced in CI (`QueryBudgetTest`) |
| Indicators at 500 000 events / 50 000 people (MySQL) | all indicators, 6 months: **4.7 s -> 0.77 s**; with age filter **7.5 s -> 1.6 s** (database counting + covering index); then cached 5 minutes |
| Text sent per page | ~178 KB (46 KB compressed) on every page -> **once per language/version**; later pages under a third of the size |
| k6 smoke (20 virtual users, 1 min, Octane + SQLite) | 479 requests, 0.2% errors (SQLite lock), all checks pass - **kit verified; timings not representative** |

The sandbox timings are **not** capacity figures: SQLite locks under concurrent writes, the CPU is shared and the app
runs in debug mode. Capacity must be measured on staging (S24) with MySQL, Redis and production settings.

## How to measure on staging (S24)

1. Staging with production settings (MySQL 8.4, Redis, Octane, `APP_DEBUG=false`, config/route/view cache), demo mode
   on (demo accounts), `TRUSTED_PROXIES` set so each virtual user counts as its own visitor.
2. `kasi:perf:seed --people=200000 --events=5000000` (never production).
3. k6 from several load generators (or k6 cloud): `PROFILE=load VUS=2000` per generator, then `PROFILE=peak RPS=...`,
   then a 2-hour `PROFILE=soak`.
4. Record per profile: requests/s per app server at p95 <= 300 ms, database CPU and connections, Redis memory, queue
   lag. Fill in the sizing table below from those numbers.

## Sizing table (to be filled from the staging run)

| Concurrent people | Requests/s (avg / peak) | App servers (Octane) | MySQL | Redis | Queue workers |
|---|---|---|---|---|---|
| 10 000 | 330 / 1 000 | measure | measure | measure | measure |
| 50 000 | 1 650 / 5 000 | measure | measure (+ read replica?) | measure | measure |
| 100 000 | 3 300 / 10 000 | measure | measure (+ read replica) | measure | measure |

## Design choices that make scaling out possible

- Octane (Swoole) with a memory-safety audit and a test against static state; fake drivers reset per request.
- Stateless app servers: sessions, cache, queues and rate limits in Redis; files in S3-compatible object storage
  delivered by 5-minute presigned links (CDN-ready); af-south-1 by default for data residency.
- Trusted proxies by configuration, so rate limits and audit logs see real visitor IPs behind a load balancer.
- Database counting for indicators with a covering index, 5-minute cache of aggregates.
- Lazy loading is an error outside production; query budgets for 14 key pages; text sent once per language/version.
