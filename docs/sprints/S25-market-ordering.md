# Sprint 25 - KasiMarket: ordering

- **Status:** Complete
- **Version:** v0.25.0

## Delivered

Cart grouped by shop with rechecked prices and stock; in-app and WhatsApp orders (tracked by reference); stock
reserved safely and given back; the order journey with reasons and estimates; 24-hour reminders and 48-hour lapses;
moderated order messages; phone shared only by choice; assisted ordering at hubs; buyer and trader order pages;
collection and delivery settings; funder indicators for completed orders and their value (ADR-033).

## Issues found and fixed during the sprint

- When a trader cancelled, the buyer would have received the message written for traders ("The buyer cancelled
  it"); buyers now get their own message (tested).
- The new ordering browser tests first ordered as Thandi; their notifications pushed an older demo update off her
  feed, which the documents test reads. Ordering tests now use demo citizens (084 000 0077 / 0078).
- The order recorder takes event names from the class, so each order event is its own small class.

## To watch

Public first load is 125.0 of 130 KB - exactly the warning line. Find what grew before adding more shared code.

## Waiting on Ku Tirhisana

Buyer terms; confirmation of the 48-hour response rule and the 18+ minimum for buyers.
