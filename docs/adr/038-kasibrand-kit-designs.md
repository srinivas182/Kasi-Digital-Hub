# ADR-038: KasiBrand - brand kit, designs and the WhatsApp catalogue

- **Status:** Accepted
- **Date:** 2026-10-21
- **Sprint:** S30

## Decisions

1. **KasiBrand on `brand.`** in the Enterprise and Full packages; one brand kit per KasiStart business; owners and
   co-owners (entrepreneurs and traders get access); assisted sessions; only at hubs with KasiBrand.
2. **Logo:** an upload (compressed WebP, metadata stripped, the owner confirms it is theirs to use) or a generated text
   logo drawn as SVG in 6 shapes (text escaped, colours validated). No AI image generation in Release 1.
3. **Colours:** 8 tested palettes or custom, with a WCAG AA contrast check and a suggested readable shade; three
   lettering styles using fonts already on the device; tagline ideas through the AI writing help (budgets apply).
4. **Designs from 8 templates** with plain fields and length limits (text wrapped and trimmed safely); the brand kit is
   applied when drawing, so kit changes update every design. The price list pre-fills from listed KasiMarket products.
5. **PNG made on the phone** from the same SVG as the preview; **PDF** through the platform's PDF service (placeholder on
   staging). Downloads are logged for the funder indicator.
6. **WhatsApp catalogue:** up to 30 listed products as branded images plus a ready-to-send message with prices and
   links; nothing is sent for the owner and no WhatsApp Business API is used.
7. **Portal boundaries:** KasiBrand reads the public shop and listed products through a new core `BusinessProducts`
   contract implemented by KasiMarket.
8. Signal "has a brand kit" for partner offers; indicators for brand kits and downloads.

## Known limitation

Inside the phone-made PNG, browsers don't load pictures from another address, so an uploaded logo or product photo can
be missing until app files are served from one address (S34). The PDF always includes them, and the editor says so.
