# Sprint 31 - KasiBrand part 2: business websites and the content calendar

- **Status:** Complete (KasiBrand complete; every Release 1 portal built)
- **Version:** v0.31.0

## Delivered

One-page business websites at name.<domain> and /b/name (address rules, verified-ID publishing, moderation and the
shared prohibited-items list, take-down, private statistics, a tiny cookie-free page with structured data); the content
calendar with an AI month plan from the South African calendar, reminders and "mark as posted"; demo website for Nomsa
Hair Studio (ADR-039).

## Issues found and fixed during the sprint

- The prohibited-items list lived inside KasiMarket; moved to Core so websites use the same list (KasiMarket unchanged,
  46 tests pass).
- The copied notification class still carried KasiMarket's notification key and WhatsApp template; corrected.
- Tests caught that `/b/name` set session and security cookies; the route now runs outside the web stack (no cookies).
- The public-sites binding was missing after an edit didn't apply; caught by the tests.
- The address check cleared its result inside a React effect (lint); results now remember the name they were for.
- The website editor's photo picker had no accessible label (caught by the accessibility check).
- The support-offers page went one query over its budget (the new "has a website" signal); both KasiBrand signals
  now come from one query, so the budget is unchanged.
- Payday and Christmas both fell on 25 December 2026; one moment per day, holidays first.

## Waiting on Ku Tirhisana

The reserved and blocked website names; whether sites show a small "Made with KasiHub" footer; website terms (what may
be published).

## CI follow-up

- The first CI run hit the browser job's 10-minute limit: flaky retries of the KasiBiz browser test (each up to 2
  minutes) on top of a ~200-test suite. Root cause of the flake: KasiBiz's compliance profile and KasiBrand's kit and
  website were saved with update-or-insert, so two saves for the same business at the same moment (phone and desktop
  in parallel - or two co-owners in production) could both insert and fail on the duplicate key. All four now use an
  atomic upsert (tested on SQLite and MySQL).
- The browser job is now two parallel jobs (phone, desktop), each well inside the 10-minute limit.
- A HubOps search test (S22) expected exactly 6 people named "Zanele"; random test data occasionally produced a 7th
  match. It now uses a made-up name random data can't produce.

## Noted for Sprint 33 (stability)

- CI marked three desktop browser tests flaky in S30 (passed on retry).
