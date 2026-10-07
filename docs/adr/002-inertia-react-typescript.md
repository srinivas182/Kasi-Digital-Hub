# ADR-002: Inertia.js + React + TypeScript, SSR for public pages

- **Status:** Accepted
- **Date:** 2026-10-07
- **Sprint:** S0

## Context

We need a fast, mobile-friendly React frontend, Laravel's security model, public pages that load instantly and
are indexed by Google (jobs, courses), and an API for future mobile apps and partners.

## Decision

React + TypeScript (strict) rendered through Inertia.js. Server-side rendering enabled in production for public
pages. A versioned REST API (`/api/v1`) is built alongside for mobile and partner use.

## Consequences

- One codebase and one deployment; Laravel sessions, CSRF and authorisation apply to every page.
- SSR requires a Node process in production (Sprint 24).
- The API is maintained in parallel for endpoints mobile/partners need.

## Alternatives considered

Separate Next.js frontend over an API - rejected: two applications to secure, deploy and keep in sync.
