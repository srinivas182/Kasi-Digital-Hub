# ADR-004: Every external service behind a driver

- **Status:** Accepted
- **Date:** 2026-10-07
- **Sprint:** S0

## Context

AI, SMS, WhatsApp, email, payments, search and storage cost money per call and differ by provider. Demos and CI
must never incur cost or reach real users.

## Decision

Each service is accessed through an interface with drivers selected in `config/kasi.php`. Fake/log drivers are
the default for local, CI and demo environments.

## Consequences

- Zero-cost demos and tests; providers can be swapped without code changes.
- Each new provider needs a driver and contract tests.

## Alternatives considered

Calling provider SDKs directly - rejected: lock-in, costly tests, risk of messaging real people from demos.
