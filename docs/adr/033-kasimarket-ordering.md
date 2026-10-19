# ADR-033: KasiMarket - ordering

- **Status:** Accepted
- **Date:** 2026-10-19
- **Sprint:** S25

## Decisions

1. **One order per shop:** the cart (saved to the account) is grouped by shop, and each shop's items become one
   order. Prices and stock are rechecked whenever the cart is shown and when the order is placed; each order line
   keeps the title, option and price **as they were when ordered**.
2. **Buyers must be 18+** to place orders in Release 1; 16-17-year-olds can browse.
3. **In-app or WhatsApp:** "Send on WhatsApp" creates the order first (reference `KM-YYYY-NNNNNN`) and then opens
   WhatsApp with the items, total and reference, so WhatsApp orders are tracked too.
4. **Stock is reserved when the order is placed**, inside a transaction and only if it is still there (the last item
   can never be sold twice), and given back when an order is declined, cancelled or lapses.
5. **The journey:** placed -> accepted (with an estimate) or declined (reason required) -> ready / out for delivery ->
   completed; the buyer can cancel until the trader accepts, and can confirm receipt; after acceptance either side
   cancels with a reason.
6. **No ghosting:** an hourly job reminds traders at 24 hours and lapses orders at 48 hours (heartbeat on the system
   status page).
7. **Payment is cash or arranged directly** on collection or delivery; no platform fee. Buyers are reminded never to
   pay before receiving their order. Online payment follows in S26.
8. **Privacy:** traders see the buyer's first name, and their phone number only if the buyer chose to share it;
   order messages are moderated; only the buyer and the shop's owners can open an order.
9. **Assisted ordering:** in a hub assisted session the order belongs to the person helped, and the facilitator is
   recorded as the one who placed it.
10. Funder indicators: orders completed and their value.
