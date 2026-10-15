# ADR-028: Indicators, funder portal and regional console

- **Status:** Accepted
- **Date:** 2026-10-11
- **Sprint:** S20

## Decisions

1. **Indicators are published by the portal that owns them** through the core `Indicator` extension point (20 in Release
   1). Most are `EventIndicator`s counted from the platform event store, which already records the person's home hub -
   no new tracking. Payload checks run in PHP so JSON behaves the same on MySQL and SQLite. Every indicator has a
   written definition (`insights.indicator.<key>.definition`) shown to funders.
2. **Aggregates only, with small-number suppression:** any value from 1 to 9 is shown as "fewer than 10" in pages, CSV
   and PDF reports (`Insights::cell`). No personal data in any funder or regional response (tested).
3. **Breakdowns:** hub, city, province, metro/rural and age band. **Sex, race and disability are not collected** in
   Release 1, so no such breakdowns; adding them would need explicit consent and a clear purpose under POPIA.
4. **Programmes** (funder, scope, period, age group, targets) are set up by KasiHub staff (`funder.admin`); target
   changes requested later need approval by another staff member.
5. **Snapshots:** each month is frozen on the 5th of the next month (`kasi:funder:freeze`); the current month is live and
   marked provisional, so sent reports never change.
6. **Regional console:** city and provincial coordinators see their hubs' indicators (suppressed), operational health
   counts from the core `HealthSignal` extension point (documents waiting, applications unanswered, referrals waiting,
   open safeguarding concerns as counts, overdue invoices), and an alert when a hub's visitors fall below 60% of its
   3-month average (pro-rata). They can message hub managers; they see no personal data.
7. **Stories** are added by hub staff only with the young person's recorded consent, anonymised.
8. Every funder and regional view and export is audited.
