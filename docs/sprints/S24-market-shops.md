# Sprint 24 - KasiMarket: shops and products

- **Status:** Complete
- **Version:** v0.24.0

## Delivered

Shops for KasiStart businesses (18+, hubs with KasiMarket), products with options, stock and small WebP photos,
listing safety (prohibited items, moderation, first-3 review, reports), the public market on `market.`, the trader
dashboard and product form, demo shop for Nomsa Hair Studio (ADR-032).

## Deviation

- "Has an online shop" as a KasiStart business fact for partner eligibility is moved to S27: using it also needs a
  change to the partner eligibility rules, which belongs with the trust and reports sprint.

## Issues found and fixed during the sprint

- KasiMarket didn't declare its dependency on KasiStart, so its demo data ran before the businesses existed.
- The first version assigned the trader role directly instead of through the shared (audited) role service.

## To watch

Public first load is 124.9 of 130 KB (warning line 125).

## Waiting on Ku Tirhisana

Prohibited and restricted items list; trader terms; confirmation that traders are 18+.
