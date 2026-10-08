# Sprint 4 - Documents, notifications & events

- **Status:** Complete
- **Version:** v0.4.0

## Objectives

Shared services every portal relies on: a secure document vault, notifications that respect people's choices and
costs, a cross-portal event bus, scheduled jobs, and the "what changed and why" updates feed.

## Delivered

- **Document vault** (ADR-012): 8 document types with optional expiry; private disk (switchable to S3-compatible
  storage); phone photos shrunk to 2000 px; virus scanning before use (fake scanner in demo/CI, ClamAV driver for
  production with fail-closed behaviour); verify once / reuse everywhere; consent-based sharing per organisation and
  purpose; signed 5-minute download links with a permission check on open; deletion removes the file; every upload,
  view, share and deletion audited; daily expiry reminders. "Documents" tab on the account page with a "Take a
  photo" option on phones.
- **Notifications**: catalogue of notification classes (welcome, document verified / rejected / expiring, role
  given, security alerts); channels in-app, WhatsApp (template driver), SMS, email; preferences per category and
  channel ("Notifications" tab); security can't be switched off; WhatsApp only with consent; marketing only with
  consent; duplicate suppression; daily cap; quiet hours 20:00-07:00 SAST with release from 07:00; SMS fallback for
  important WhatsApp failures; delivery log with estimated costs. Security alerts now run through the notifier.
- **Events**: `PlatformEvent` base (after-commit), 10 core events, append-only platform event log, listeners that
  send notifications; manifests declare published/consumed events.
- **Updates feed**: `/home/updates` with read/unread, "why" for each item, bell with unread count in every
  signed-in layout.
- **Generated docs**: `php artisan kasi:docs:generate` writes `docs/event-catalogue.md` and
  `docs/whatsapp-templates.md`; tests fail if they are out of date.
- **Queues and scheduler**: Horizon queues security > notifications > documents > events > default; dashboard for
  super and operations admins only; scheduled jobs with heartbeats; Docker `scheduler` service and an optional
  ClamAV service.
- **Languages**: server messages in a partly translated language now fall back to English instead of showing raw keys.
- **Demo**: sample documents (verified, pending, rejected, expired, shared), six months of registration events,
  welcome message deliveries with costs, and updates feeds for the main demo accounts.

## Database changes

New: `documents`, `document_shares`, `notification_preferences`, `notification_deliveries`, `platform_events`,
`updates`.

## Tests

PHP 253 - vault (scan, quarantine, signed links, sharing, deletion, photo shrinking, validation, reminders),
notifications (channels, consent, preferences, security, quiet hours and release, duplicates, daily cap,
marketing, email, cost, fallback, language), events (recording, rollback safety, catalogue), updates feed,
scheduler and queue dashboard access. Frontend 42. Browser 46 - including document upload, the updates feed and
notification choices, with accessibility scans.

## Issues found and fixed during the sprint

- Laravel does not fall back to English for missing JSON translation keys - people using draft isiZulu or
  Xitsonga would have seen raw keys in server messages. Fixed with an English-fallback translation loader.

## Not in this sprint

- Web push notifications (deferred, as agreed).
- Real WhatsApp, SMS and email providers and ClamAV in production are connected at deployment (S24); the
  template list in docs/whatsapp-templates.md should be submitted to Meta early.
- Document verification screens for reviewers arrive with the admin console (S6).
