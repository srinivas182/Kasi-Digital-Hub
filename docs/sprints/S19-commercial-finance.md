# Sprint 19 - Commercial and finance console

- **Status:** Complete
- **Version:** v0.19.0

## Delivered

- **Catalogue** with metro/rural tiers, floors and ceilings; **split rules** per stream; **four-eyes** approvals.
- **Hub readiness** checklist (company, VAT, verified bank account, franchise agreement, no overdue package invoice).
- **Checkout** with attribution, VAT for the merchant of record, exact splits, payment driver (fake / Paystack),
  signed and idempotent notifications, verifiable receipts / tax invoices, proportional refunds.
- **Point of sale** for cash sales with royalties netted against payouts.
- **Immutable ledger**, monthly statements and payouts, package invoices with reminders and sales blocking (youth
  services untouched), licence invoices with delivery fees, daily reconciliation, ledger CSV.
- **Console** (national) and **hub finance** dashboards. KasiWork employers can buy extra advert slots (ADR-027).
- **Demo:** Tsutsumani hub is ready to sell; demo payments only.

## Issues found and fixed during the sprint

- The approvals query in the console filtered on an ambiguous `status` (the joined `users` table has one) - the same
  mistake as S18. Fixed, and every `users` join in the codebase was scanned for unqualified columns (none left).
- **Column too short for its values (real bug, caught by the local MySQL run).** The order `status` column was 10
  characters, but `part_refunded` is 13: SQLite ignores lengths, MySQL doesn't, so partial refunds couldn't be saved.
  Widened. A scan found the same in S14: quiz question `kind` (8) can't hold `truefalse` (9) - fixed with a new
  migration and a regression test. New architecture test: every value documented next to a string column must fit.
- Two tests compared the stored `shares` JSON in key order (MySQL re-orders keys) - now compared by content.
- The KasiWork hiring browser test opened the *first* advert's applicants; once the commercial test bought an extra
  slot, another test could publish a newer advert that came first. The test now opens the Cashier advert by name.
- The console's revenue summary used a definition list that wasn't valid HTML; accessibility checks caught it - now a
  table.

## Waiting on Ku Tirhisana

- Payment provider and merchant account; real prices and split percentages; the franchise agreement (attorney);
  merchant of record and VAT treatment (tax adviser). Live payments stay off until then.
