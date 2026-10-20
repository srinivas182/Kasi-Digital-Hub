# ADR-035: KasiMarket - trust and safety

- **Status:** Accepted
- **Date:** 2026-10-20
- **Sprint:** S27

## Decisions

1. **Reviews:** only the buyer of a completed (not fully refunded) order, within 30 days, one per order; moderated;
   one public trader reply; reviewers shown as first name and initial; a shop rating only from 3 reviews.
2. **Reliability:** the trader's private signals (median time to accept, acceptance, lapses, completion over 90 days);
   the public "Replies quickly" badge needs at least 5 orders and a median acceptance within 12 hours.
3. **Buyer no-shows:** traders mark cash orders the buyer didn't collect; after 3 in 90 days the buyer can only pay
   online for 90 days and is told why. Private to the buyer and KasiHub support.
4. **Disputes:** within 14 days, with up to 4 photos stored privately in the document vault (type
   `dispute_evidence`, opened through audited signed links); the trader responds within 3 days or the two settle;
   otherwise KasiHub support decides (PIN re-confirmation). Refunds go through the core Payments contract and only for
   online payments; cash disputes are mediated. Unresolved disputes appear in the review inbox.
5. **Enforcement:** shops can be reported; moderators give strikes (expire after 180 days, PIN re-confirmation); with 3
   active strikes a shop can be suspended - hidden, open orders cancelled and refunded; moderators can restore it after
   an appeal through Get help.
6. **Business signals:** a new core extension point lets portals contribute facts about a business; KasiStart's
   business facts include them as `signals`, and partner offers can require an online shop, a number of KasiMarket
   orders, or a minimum rating - without any portal reading another's tables.
7. **Insights:** sales by week, top products, views to orders, repeat buyers, rating, online and cash amounts, and a
   CSV of a year's orders.
