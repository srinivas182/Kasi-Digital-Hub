# ADR-015: Hub operations - rotating door codes, assisted sessions, events

- **Status:** Accepted
- **Date:** 2026-10-09
- **Sprint:** S7

## Context

Hub visits and event attendance are what funders pay for, so the numbers must be trustworthy. Many visitors
have no smartphone or have never used an online service, and staff must be able to help them without
learning their PIN or impersonating them (POPIA).

## Decisions

1. **Door check-in uses a QR code that changes every 2 minutes**, shown on a door screen. Codes are an HMAC of
   the hub and the time window (current and previous window accepted) - nothing stored. The door screen opens
   with a secret link (hash stored, replaceable by the hub manager), so no staff account is signed in on a public
   screen. A valid scan is held for 15 minutes so people can sign in first. Front-desk lookup and anonymous
   walk-ins cover people without phones. One visit per person per hub per day (unique index).
2. **Assisted registration**: the code goes to the person's own phone (proof of number and presence), the person
   types their own PIN on the device, consent is recorded with channel `assisted` and the facilitator, and the
   person receives an SMS naming who helped. Adults only; under-18s need a guardian on their own phone.
3. **`AssistedSession`** (Core extension point): a facilitator helps a person who is checked in at the hub today,
   for up to 30 minutes, staying signed in as themselves. Every action is audited as "by facilitator, for person".
   Later portals (CV builder S9, enrolment S13) use the same session. No "log in as user".
4. **Events**: capacity is checked under a row lock; waiting lists move up first come, first served, when people
   cancel or places are added. Attendance by a signed event QR link (valid from 30 minutes before the start to
   the end) or ticked by staff; attendance also counts as a hub visit.
5. **`HubActivity`** (Core extension point): portals publish hub activity; the public hub page shows public
   events and the hub home shows a person's own - so the website and hub home never depend on KasiHub Ops.
6. **Retention**: visits older than 24 months lose the person and staff member (monthly job); totals remain.
7. **Hub internet connections** are stored on the hub and raise the per-IP sign-in code limit (S6 fix).

## Consequences

- Hub numbers resist gaming from home; staff help is accountable; door screens hold no credentials.
- Hubs without a working door screen still check people in at the desk.
