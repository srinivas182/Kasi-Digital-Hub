# ADR-039: KasiBrand - business websites and the content calendar

- **Status:** Accepted
- **Date:** 2026-10-21
- **Sprint:** S31

## Decisions

1. **Address:** `name.<domain>` (wildcard DNS and SSL in S34) and always `/b/name`. Names are 3-30 lowercase letters,
   numbers or hyphens; portal names and reserved words (www, admin, sars, cipc, official...) and offensive words are
   blocked; first come, first served; an address can change once a month. Custom domains in a later release.
2. **Serving:** a new core `PublicSites` contract (implemented by KasiBrand). The portal host middleware offers unknown
   subdomains to it before its existing redirect to the main site, so Core never depends on KasiBrand. `/b/name` is
   registered outside the web stack: **no session, no cookies, no CSRF token** - nothing about visitors is stored.
3. **The page** is server-rendered Blade with inline styles and no app code, under 50 KB plus lazy images (a tested
   budget), cached for 10 minutes and cleared on every change; title, description, sharing details and LocalBusiness
   structured data. Content comes from the brand kit and, through core contracts, the public KasiMarket shop; the
   WhatsApp number appears only if public.
4. **Publishing** requires a verified ID (the same rule as KasiMarket's ID-verified badge); the prohibited-items list
   (moved to Core so KasiMarket and KasiBrand share one list) refuses; moderation holds flagged text for review;
   moderators can take a site down or restore it (PIN confirmed) and the owner is told.
5. **Statistics:** daily counts of views, WhatsApp taps and order taps (taps pass through the site, then on) - no
   cookies and no personal data.
6. **Content calendar:** planned posts with reminders every 15 minutes (once per post) and "mark as posted"; no
   automatic posting. "Plan my month" builds 8-12 dated slots from the South African calendar in code (public holidays
   with Sunday-to-Monday observance and Easter, grant days, payday, month end, Valentine's, Mother's and Father's Day,
   Black Friday, back to school, festive season; one moment per day, holidays first) and asks the AI only for wording,
   with plain fallbacks so the plan always works.
7. Signal "has a website"; indicators for websites published and posts made.
