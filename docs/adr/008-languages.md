# ADR-008: Laravel JSON translations shared to React

- **Status:** Accepted
- **Date:** 2026-10-07
- **Sprint:** S1

## Context

The platform must support South Africa's languages; translators should work with one set of files.

## Decision

Strings live in `lang/<code>.json` with stable dotted keys. Laravel shares the current language's strings
(merged over English) with every page; React reads them through `useTranslation()`. The choice is stored in an
unencrypted `kasi_locale` cookie (from Sprint 2 the user profile takes priority). Draft languages are hidden in
production unless `KASI_SHOW_DRAFT_LANGUAGES=true`.

## Consequences

- One source for server and client text; English fallback for missing keys.
- Only the current language is sent to the browser.
- Professional translation is required before a language goes live (planned for S23).

## Alternatives considered

A separate JavaScript i18n library with its own files - rejected: two sets of strings to keep in sync.
