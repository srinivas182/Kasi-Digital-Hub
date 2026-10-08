# ADR-012: One notifier, a platform event log, and a private document vault

- **Status:** Accepted
- **Date:** 2026-10-07
- **Sprint:** S4

## Context

Every portal needs to tell people what happened (WhatsApp, SMS, email, in-app), react to what happened in other
portals, and handle sensitive documents. Messaging costs money; WhatsApp needs Meta-approved templates; many users
share devices; POPIA applies to ID documents.

## Decision

- **Notifications** are classes (`KasiNotification`) declaring category, channels, WhatsApp template and text keys.
  One `Notifier` applies the rules: in-app always (the updates feed); WhatsApp only with consent; SMS when WhatsApp
  is not possible, for security, or as fallback for important messages; email only to confirmed addresses;
  preferences per category; duplicate suppression; a daily cap; quiet hours 20:00-07:00 SAST (except security).
  Every delivery is logged with status and estimated cost.
- **Events** extend `PlatformEvent`, are dispatched only after commit, recorded in the append-only
  `platform_events` log, and consumed by listeners (portals never call each other). Manifests declare published and
  consumed events; `docs/event-catalogue.md` and `docs/whatsapp-templates.md` are generated and checked by tests.
- **Documents** live on a private disk, are virus-scanned before use (ClamAV in production), opened only through
  short-lived signed links with a permission check, shared per organisation and purpose with consent, and deleted
  with their files. Every access is audited.

## Consequences

- Costs are controlled and visible; people get messages on the channel they chose.
- New portals plug in by adding notification and event classes - no shared-code changes.
- WhatsApp templates must be approved before go-live (list ready).

## Alternatives considered

- Laravel's built-in notification channels only - rejected: no place for quiet hours, caps, cost logging or
  WhatsApp-to-SMS fallback without wrapping them anyway.
- Synchronous event handling - rejected: slow requests and events for rolled-back changes.
- Public file URLs or long-lived links - rejected: unacceptable for ID documents on shared devices.
