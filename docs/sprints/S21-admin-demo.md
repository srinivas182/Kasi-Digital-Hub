# Sprint 21 - Admin console part 2 and the complete demo

- **Status:** Complete
- **Version:** v0.21.0

## Delivered

- **Support:** help requests ("Get help"), staff queue with overdue flags, internal notes and saved replies, safe person
  summaries, unlock and phone change with one-time codes (ADR-029).
- **Help centre:** 24 starter articles, review before publishing, safe format, search, feedback, context help links.
- **AI costs:** projection, spend by hub, 80% / 100% alerts (hourly job).
- **Review inbox** across portals; **system status** page.
- **Demo:** six guided stories, role switcher, demo reset, DEMO banner, automated demo health check.

## Issues found and fixed during the sprint

- **Signed time differences (Carbon 3):** the system status page first measured "hours since the last run" from now to
  the past, which is negative, so overdue jobs looked fine. Fixed; the codebase has no other instance.
- **Demo story links:** the new demo health check found three wrong links on its first run (CV, matches and hub pages).
- **Context help map:** a new test found three route names that didn't exist.
- **Accessibility:** the help request's screenshot field wasn't labelled - now the platform's labelled file input.
- **Test-only:** the S20 funder privacy test could fail when the random surname of the young person in the test matched
  the signed-in funder's own (factory names repeat); the person now has a unique name and number.
- **Test-only:** a demo-guard test switched the app to "production", which also turns on CSRF checks in tests; the
  production guard is now checked directly.

## To watch

Public first load is 124.5 of 130 KB (warning line 125). S22 (performance) should win back headroom.

## Waiting on Ku Tirhisana

- Review of the 24 starter help articles; who answers support requests and in which hours; AI budget per feature.
