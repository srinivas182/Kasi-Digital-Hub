# ADR-009: Installable app with a conservative service worker

- **Status:** Accepted
- **Date:** 2026-10-07
- **Sprint:** S1

## Context

Users have weak signal and limited data, but many share hub computers, so personal data must never stay on a
device.

## Decision

A web app manifest makes KasiHub installable. `public/sw.js` caches hashed build assets and icons (cache-first)
and the offline page. Pages and API responses are **never** cached; with no connection the offline page is
shown. The service worker registers in production builds only.

## Consequences

- Instant repeat visits and a friendly offline message.
- No offline use of personal pages (by design). Offline drafts for specific forms can be added per feature later.

## Alternatives considered

A full offline-first app (cached pages and data) - rejected for now: privacy risk on shared devices and complex sync.
