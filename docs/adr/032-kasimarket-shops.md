# ADR-032: KasiMarket - shops and products

- **Status:** Accepted
- **Date:** 2026-10-19
- **Sprint:** S24

## Decisions

1. **A shop belongs to a KasiStart business** (informal ones too), reached only through the core `BusinessFacts`
   contract. Opening one needs an owner or co-owner aged **18+** whose hub has KasiMarket (Enterprise and Full
   packages); it gives the `trader` role (assigned through the shared role service, as for entrepreneurs).
2. **Products** have a category (10 seeded), price (cents, VAT-inclusive), unit, stock (quantity, made to order,
   service; sold out at zero), simple options, and up to 4 photos re-encoded as small WebP versions (320 px and 800 px)
   by `Core\Media\WebpImage`, which also strips metadata such as GPS location. Alt text is required.
3. **Safety at listing:** a prohibited-items list refuses clear breaches with a reason (placeholder until Ku Tirhisana
   confirms the list); the platform moderation checks hold unclear ones; a shop's **first 3 products are reviewed** in
   the S21 review inbox. Reviewer decisions publish or refuse products automatically. Anyone signed in can report a
   product into the same inbox.
4. **Public market** on `market.` (home, search with filters, shop and product pages with Google product data,
   WhatsApp ordering and sharing), indexed by platform search; nothing personal; the shop's WhatsApp number is shown
   only with the trader's consent.
5. **Listing is free**; Kasi and the hub earn only on online-paid orders (S26).
6. Funder indicators: shops opened and products listed.
