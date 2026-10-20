# Sprint 26 - KasiMarket: payments and delivery

- **Status:** Complete
- **Version:** v0.26.0

## Delivered

Online payment with split at source (trader, hub, Kasi) through a core Payments contract; trader payment readiness;
30-minute stock holds; automatic and partial refunds; hub collection points with codes, a staff desk and 7-day
returns; delivery zones and free delivery; the hub's market share in its statement; "Get help" with the order number;
the page-registry bundle fix (ADR-034).

## Deviations

- **Hub handling fee:** not built; it is R0 in Release 1, and a fee would need its own share in the split. To revisit
  if Ku Tirhisana wants hubs to charge one.
- **Payout accounts at the provider** are created when live payments are switched on (S34); the demo uses a demo payout
  account once the trader's bank letter is verified.

## Issues found and fixed during the sprint

- The commercial `merchant` column (4 characters) couldn't hold `trader` - widened by a new migration before any test.
- The entry bundle listed every page; now each portal loads its own list (first load 125.0 -> 120.2 KB), with a guard.
- Demo data: Nomsa's shop text named Tsutsumani, but her hub is Soweto - corrected, and a Soweto hub facilitator
  (Zanele Dube, 072 000 0025) added for the collection desk.
- Tests: the shared sign-in helper now waits for the sign-in's portal switch to finish (protects every browser test);
  the three-person collection test needed longer limits.
