# Sprint 23 - Portal subdomains, single sign-on and the public job board

- **Status:** Complete
- **Version:** v0.23.0

## Delivered

Portal subdomains behind one setting; host-aware redirects that keep every old link working; single sign-on and
sign-out across portals; sign-in on the main domain with return to the requested page; PIN re-confirmation for
sensitive staff actions; public job board on `jobs.`; browser tests and CI with subdomains on (ADR-031).

## Deviations from the specification

- **Menus keep their normal links**; the server switches to the right subdomain (one small extra hop per portal
  switch) instead of rewriting links across ~30 pages.
- **No minimum-pay filter on the job board yet:** pay is stored per hour/day/week/month, so a fair filter needs a
  monthly equivalent; to be added properly rather than approximated.

## Issues found and fixed during the sprint

- Unknown subdomains with no matching route returned 404: the host check now runs for every request (global
  middleware).
- The test server's readiness check (and, in production, load-balancer health checks) use the server's IP, which was
  being redirected: hosts that aren't ours are now served untouched.
- Browser options for the phone tests weren't getting the subdomain resolver rule.
