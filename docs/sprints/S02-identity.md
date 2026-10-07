# Sprint 2 - Login & profile

- **Status:** Complete
- **Version:** v0.2.0

## Objectives

Secure, low-cost sign-in for millions of users on basic phones; one profile for every portal; consent and
audit built in (POPIA).

## Delivered

- **Accounts** (`users`, ULID ids): verified E.164 phone, names, date of birth, language, hashed PIN, status,
  age band, optional verified email, soft deletes.
- **Sign-in** (ADR-010): phone -> SMS code (new device only) -> PIN -> authenticator (staff). Remembered devices
  sign in with phone + PIN (no SMS). Forgotten PIN reset by SMS code. Responses never reveal registration.
- **One-time code protection**: 6 digits, 5 minutes, 5 attempts, single use, HMAC-stored; limits per number
  (3 / 15 min, 10 / day), per IP, per device and per number range; SA mobile numbers only (premium, toll-free
  and landlines blocked); daily SMS cap; bot-check driver; per-IP route throttles.
- **PIN rules**: 5 digits, no repeated or sequential PINs; 5 wrong tries = 15-minute lockout.
- **Age policy** (config): 18+ full access; 16-17 guardian consent (code to guardian's phone), then KasiLearn
  only (`adult` middleware for adults-only portals); under 16 politely declined.
- **Consent** (POPIA): versioned terms and privacy notice (placeholder text), consent per purpose with full
  history, explicit "no" recorded, re-acceptance when documents change, public `/legal/terms` and `/legal/privacy`.
- **Staff second step**: authenticator app (QR code generated server-side), 8 one-time backup codes,
  rate-limited challenge.
- **Account settings**: profile and language, optional email with signed confirmation link, PIN change, phone
  change (code to new number + current PIN, alerts to old and new number), devices list with remote sign-out
  and "sign out all others", privacy choices, account deletion request.
- **Sessions**: regenerated on sign-in; idle timeout 2 h citizens / 30 min staff; remote device sign-out ends
  that session; security alerts on new-device sign-in, PIN and phone changes.
- **Audit log** for sign-ins, failures, lockouts, codes, consent and account changes (no codes or PINs ever
  logged); retention pruning scheduled.
- **Hub home** placeholder at `/home`; user menu with account and sign-out in every signed-in layout.
- **Demo**: 11 demo accounts (docs/demo.md), codes shown on screen in demo mode.

## Database changes

New: `users` (redesigned), `sessions`, `user_devices`, `otp_challenges`, `staff_two_factor`,
`consent_documents`, `consents`, `guardian_consents`, `audit_logs`. Removed Laravel's default users and password
reset tables.

## New routes

`/login` (+ `/code`, `/pin`, `/forgot`, `/new-pin`), `/signup` (+ `/declined`, `/guardian`), `/two-factor/*`,
`/logout`, `/home`, `/account/*`, `/consents/review`, `/legal/{terms|privacy}`.

## Tests

PHP 161 (sign-up, sign-in, lockout, PIN reset, every abuse limit, age policy and guardian consent, staff
authenticator and backup codes, account settings, device sign-out, consent history and re-acceptance, idle
timeouts, audit hygiene, a new architecture rule). Frontend 41. Browser 34 (sign-up, returning sign-in,
account, staff authenticator - phone and desktop, with accessibility scans).

## Issues found and fixed during the sprint

- Services that kept the HTTP request from their constructor returned stale data when reused (cached
  controllers, and Octane later). They now resolve the request per call; an architecture test enforces this.
- Shared page data touched the session on error pages rendered before the session starts - guarded.
- Signed-in pages carried ~35 KB of menu-positioning code; app-bar menus now use a lightweight disclosure
  component (hub home 20 KB, account page 39 KB).

## Deviations from the specification

- Demo PIN is **24680**, not 12345 - 12345 is rejected by the PIN rules.
- WhatsApp security alerts arrive with the messaging service (S4); SMS (log driver) is used now.
- Account deletion is recorded now and carried out by the POPIA tooling in S23.
