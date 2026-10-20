# ADR-036: KasiBiz - books (money)

- **Status:** Accepted
- **Date:** 2026-10-20
- **Sprint:** S28

## Decisions

1. **KasiBiz is a portal on `biz.`** in the Enterprise and Full hub packages. Books belong to a KasiStart business (informal
   ones too); only its owners and co-owners see them, and only where the business's hub has KasiBiz. Entrepreneurs and
   traders get access. In a hub assisted session, entries go into the helped person's books with the facilitator
   recorded as the actor.
2. **Cash book:** money in / out with 16 plain categories and three payment types (cash, card/EFT, mobile money);
   opening balances; private receipt photos (vault type `receipt`). **Profit counts trading money only** - loans and
   the owner's own money change cash on hand but not profit.
3. **Corrections** for 30 days, removals are soft deletes, and every change is kept in `biz_entry_changes`.
4. **Other portals' money** comes in through a core extension point (`BusinessTransactions`). KasiMarket provides
   completed orders as sales and refunds as money out (one refund record per order, updated to the total refunded),
   imported once by reference; imported entries can be hidden, never edited. Hub point-of-sale sales are the hub's
   income, not the entrepreneur's, and are not imported.
5. **Who owes what:** customers and suppliers (phone numbers stay inside the business), money owed both ways, marking
   paid writes the cash book entry; WhatsApp reminders are pre-filled for the owner to send, never automatic.
6. **Month summary** with a plain comparison with last month, biggest costs, CSV, and a one-page PDF.
7. **Partners see only signals:** `biz.keeps_records` (entries in 3 of the last 4 months) and a turnover band; partner
   offers can require "keeps business records". Funders see aggregate indicators only.
