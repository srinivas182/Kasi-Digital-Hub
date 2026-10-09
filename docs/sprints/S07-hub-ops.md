# Sprint 7 - KasiHub Ops (hub operations)

- **Status:** Complete
- **Version:** v0.7.0

## Delivered

- **Check-in:** door screen with a QR code that changes every 2 minutes (secret link, no sign-in on the screen,
  says when it is offline); scanning holds the check-in while people sign in; reasons for visiting; front-desk
  lookup by name or last phone digits (masked numbers); anonymous walk-ins; one visit per person per hub per day.
- **Members:** member list with last visit; follow-up filters for no ID document and no visit in 30 days.
- **Assisted services:** facilitators help people who are checked in today (profile details and document
  photos, with the person present), all audited; banner shows who is being helped.
- **Assisted registration:** code to the person's own phone, the person's own PIN, consent recorded as assisted,
  SMS naming the facilitator, visit counted. Adults only.
- **Events:** job days, workshops, info sessions, classes; public / members / learners audiences (under-18s only
  learners events); sign-ups by the person or by staff; waiting lists that move up automatically with a
  notification; day-before reminders; cancellation with a reason (everyone told); attendance by event QR code or
  staff tick. Public events on the hub's website page; "Your upcoming events" on the hub home; Events in the menu.
- **Hub performance:** visits per day, different people, walk-ins, new members, documents verified, events and
  attendance, reasons for visits; 7/30/90 days; CSV export; comparison table for coordinators.
- **Hub settings (managers):** public details and hours, hub internet connection (higher sign-in code limit),
  door-screen link, facilitators appointed and removed with a reason.
- **Retention:** visits older than 24 months anonymised monthly.
- **Permissions:** `hubops.*` for owners and managers; facilitators check-in/members/assist/events; coordinators
  and national staff dashboard and comparison.
- **Demo:** six weeks of visits at the 12 hubs, 66 events with sign-ups, waiting lists and attendance; door screens
  at `/kiosk/{hub-slug}/demo-door`.

## Issues found and fixed during the sprint

- Hub internet addresses were read with a filter that broke on Laravel's key-passing - caught by the new test.
- The assist form failed when an optional field was left out - caught by the new test.

## Not in this sprint (scope document "Later")

Resource booking, print credits, equipment and connectivity, daily task lists, AI follow-up suggestions.
