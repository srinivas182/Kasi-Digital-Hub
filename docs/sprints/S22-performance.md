# Sprint 22 - Performance and scale

- **Status:** Complete
- **Version:** v0.22.0

## Delivered

Octane (Swoole) with safety audit and CI under Octane; lazy-loading guard; query budgets; database-side indicator
counting with a covering index and 5-minute cache; text sent once per language/version; presigned file delivery for
object storage; trusted proxies by configuration; large-volume seeding command; k6 load-test kit; capacity document
(ADR-030).

## Issues found and fixed during the sprint

- **Check-in desk search loaded each person's home hub and "already here?" separately** - caught by the new
  lazy-loading guard in the browser suite; now loaded in bulk (regression test).
- **Indicator age filter left out people on their birthday** - caught by a new exactness test; dates now compared as
  dates.
- **Indicators were slow at volume** (4.7 s for all, 1.1 s for "different people") - database counting plus a covering
  index: 0.77 s, then cached.
- **No trusted proxies** - behind a load balancer every visitor would share one IP and sign-in limits would block
  everyone; now configured by `TRUSTED_PROXIES`.
- **Every page carried the full text file** (~178 KB) - now sent once.
- A pre-existing architecture test only passed when another test had booted the app; fixed.

## Process notes

- Stopping processes is always its own command (a filter matched its own command line three times).
- Logs are only read from the end (two large outputs this sprint).

## Decisions for Ku Tirhisana

Hosting and load-test budget for staging (S24); agreement on the definition of "100 000 concurrent users".
