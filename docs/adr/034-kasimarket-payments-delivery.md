# ADR-034: KasiMarket - payments and delivery

- **Status:** Accepted
- **Date:** 2026-10-20
- **Sprint:** S26

## Decisions

1. **Portals don't call each other:** KasiMarket asks the core `Payments` contract to start or refund a sale; the
   commercial portal implements it (`SalePayments`), and confirmation comes back through the existing `Fulfiller`
   extension point (`market_order`).
2. **The trader is the seller and a ledger party:** commercial orders can carry a trader share and a payee reference
   (`market:<shop>`); trader ledger entries record it; refunds reverse any set of shares proportionally
   (`Money::proportionAll`). The merchant columns were widened (4 -> 8) for `trader`.
3. **Split at source** with a new `market` stream (placeholder: Kasi 5%, hub 3%, trader 92%; the rule's "partner" column
   is the seller); the delivery fee goes 100% to the trader; without a hub, the hub's share goes to Kasi. The trader's
   share is paid straight to their payout account; Kasi's account receives the Kasi and hub shares, and the hub's
   share appears in its monthly statement and payout.
4. **Online payment is optional per shop** and needs a bank confirmation letter verified in the document queue (a demo
   payout account is used until live payments; real provider accounts are created in S34). Cash stays available.
5. **Pending online payments hold stock for 30 minutes**, then the order is cancelled and stock returned.
6. **Automatic full refunds** when a paid order is declined, cancelled, lapses or is not collected; traders can give
   partial refunds up to what was paid.
7. **Hub collection points:** hubs opt in; staff check parcels in (the buyer gets a 6-digit code), hand over by code
   (5 wrong tries lock it), and parcels not collected within 7 days go back to the trader with a refund if paid.
8. **Delivery zones** (up to 5) with fees, and free delivery over a threshold.
9. **Bundle:** each portal has a `pages.ts` registry; only the first-paint portals (public site, sign-in, hub home) are
   in the entry bundle, and the size check fails if lazy portals' pages leak back (first load 125.0 -> 120.2 KB).
