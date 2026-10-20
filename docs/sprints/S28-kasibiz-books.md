# Sprint 28 - KasiBiz part 1: money

- **Status:** Complete
- **Version:** v0.28.0

## Delivered

KasiBiz on `biz.`: cash book with categories, payment types, opening balances and private receipts; profit versus cash;
30-day corrections with history; KasiMarket sales and refunds imported through a core extension point; customers,
suppliers and money owed with WhatsApp reminders; month summary, CSV and PDF; "keeps records" and turnover-band
signals for partners; demo books for Nomsa Hair Studio (ADR-036).

## Deviation

- Hub point-of-sale sales are not imported: they are the hub's income (recorded in the commercial console).

## Issues found and fixed during the sprint

- The first KasiMarket refund import would have created a new record (with the running total) for every partial
  refund; refunds are now one record per order whose amount follows the total refunded.
- **Platform-wide styling:** `text-success-text` had no colour token, so "success" text in 15 places showed in the
  default colour since early sprints; added a success text colour for light and dark themes (5.4:1 contrast).
- `text-on-primary` (used in KasiBiz and in the job board's and KasiMarket search's page numbers) doesn't exist; the
  correct token is `text-primary-fg` (caught by the accessibility check as low contrast).
- Local environment: the demo seeder needs Redis running (hub saves clear a cached value).
