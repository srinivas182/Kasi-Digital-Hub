# Sprint 27 - KasiMarket: trust and safety

- **Status:** Complete (KasiMarket complete)
- **Version:** v0.27.0

## Delivered

Reviews with replies and minimum-count ratings; private reliability signals and the "Replies quickly" badge; buyer
no-shows; disputes with private evidence, trader responses, settlement and support decisions; shop reports, strikes,
suspension and restoring; trader insights and CSV; business signals for partner eligibility (ADR-035).

## Deviation

- "Average days to resolve a dispute" is not a funder indicator (indicators count or sum); funders see disputes
  opened and resolved, and the support queue shows the age of each dispute.

## Issues found and fixed during the sprint

- A column-type change on a foreign key could have been refused by MySQL; the migration keeps the exact type and was
  proven on MySQL as well as SQLite.
- The moderators' form could have submitted "strike" on a suspended shop where only "restore" is shown; it now
  always sends the action shown.
- The trader dashboard's header gained a fourth button and overflowed a 360 px phone ("Add a product" became
  unclickable, caught by the phone browser test); the buttons now wrap.
- Photo inputs for disputes had no accessible label (caught by the accessibility check); the order page had two
  buttons called "Send" - the dispute button is now "Send to the trader".
