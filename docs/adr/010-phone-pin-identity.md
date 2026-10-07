# ADR-010: Phone + PIN identity, SMS codes only for new devices, authenticator for staff

- **Status:** Accepted
- **Date:** 2026-10-07
- **Sprint:** S2

## Context

Millions of users, many without email, on basic phones. Sending an SMS code on every sign-in would cost real money
at national scale and attract SMS-pumping fraud. Staff accounts can see many people's data and are SIM-swap targets.

## Decision

- Citizens sign in with **phone number + 5-digit PIN**. A one-time SMS code is required only for a new or
  forgotten device, sign-up, PIN reset, phone change and guardian consent.
- A device can be **remembered for 30 days** (opt-in, never on shared hub computers).
- Staff, organisation and national roles add an **authenticator app (TOTP)** with 8 one-time backup codes. SMS is
  not used as a second factor.
- One-time codes: 6 digits, 5 minutes, 5 attempts, single use, stored as HMAC only. Abuse protection per number,
  IP, device and number range, mobile-only numbers, a daily SMS cap and a bot check.
- No passwords and no email requirement. Sign-in responses never reveal whether a number is registered.
- Custom actions on Laravel's session guard (Fortify rejected: built around email + password).

## Consequences

- Very low SMS cost for returning users; strong protection for staff.
- People who lose their phone and backup options need assisted recovery at a hub (Sprint 7).
- The SMS gateway and bot-check provider are drivers chosen at deployment (ADR-004).

## Alternatives considered

- SMS code on every sign-in - rejected: cost and fraud exposure.
- Email + password - rejected: many users have no email; passwords are forgotten and reused.
- SMS as staff second factor - rejected: SIM-swap risk.
