# Sprint 16 - KasiStart business registration and formalisation

- **Status:** Complete
- **Version:** v0.16.0

## Delivered

- `scripts/prepush.sh` - every local check, stopping at the first failure (SQLite and MySQL).
- **Business profile** with co-owners; "Your business" on the hub home.
- **Formalisation journey:** legal-form guide (guidance, not legal advice), personalised steps, proof uploads,
  pre-filled B-BBEE affidavit, editable step content with "last checked" dates (ADR-024).
- **Business plan builder** with AI help, price and break-even calculator, verifiable one-page summary.
- **Readiness score** with reasons; events for funder numbers. Demo: Nomsa Hair Studio (sole proprietor).

## Issues found and fixed during the sprint

- **Rate limits were shared between features (real bug).** Laravel's `throttle:X,Y` keys the counter on the signed-in
  person only, so all limited routes shared one allowance - busy job-seeking could block registering a business.
  Limits are now per person and per route (`ThrottlePerRoute`), with a test. Found by the full browser run.
- **Money on the calculator used the browser's locale** (`R150,00` in some browsers) - it now uses the platform's
  money format everywhere.
- **Flaky test sign-ins:** phone and desktop runs sign in the same demo accounts in parallel, and a new code replaces
  the previous one (correct security behaviour). The test helper now starts again when it loses that race.
- The pre-push script gained `E2E=1` (full browser suite), used before every sprint's final push.

## Waiting on Ku Tirhisana

- Legal review of the draft steps, the legal-form guide and the affidavit wording before launch.
- Whether hubs have staff who are commissioners of oaths.
