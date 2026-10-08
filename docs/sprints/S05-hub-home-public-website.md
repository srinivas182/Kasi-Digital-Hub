# Sprint 5 - Hub home & public website

- **Status:** Complete
- **Version:** v0.5.0

## Objectives

A public website that explains KasiHub, helps people find a hub and sign up, and speaks to employers, funders
and partners - fast on cheap phones and easy to find on Google - plus a personal hub home for every signed-in person.

## Delivered

- **Public pages:** Home (hero, what you can do, how it works, live impact numbers, hubs, partners, call to
  action), Find a hub (search and "near me"), a page per hub (services from its package, contact, hours,
  directions), For employers, For funders and partners, About, Help (FAQ that works without JavaScript),
  Contact. Mobile menu on phones. First-draft copy in the translation files.
- **Search and sharing (ADR-013):** server-rendered pages (verified with the SSR server); description, canonical
  and Open Graph tags from the server; structured data for the organisation, every hub and the FAQ;
  `sitemap.xml`; `robots.txt` (private areas blocked in production, everything blocked in demo/staging);
  private pages marked noindex.
- **Forms:** contact, employer interest and funder/partner enquiries - stored, emailed to the platform team,
  honeypot field, bot check, rate limit, consent line.
- **Cookie-free page counts** per page per day (no personal data, bots ignored).
- **Hub home:** greeting; "Your next steps" with progress (home hub, location, ID, matric for job seekers and
  learners, WhatsApp) linking straight to where each is done; your hub card (address, phone, hours, directions);
  service tiles for the portals you can open ("coming soon" until built); latest updates.
- **Portal SDK extension point:** portals add their own next steps through `HomeContributor` (tagged
  `HomeRegistry::TAG`) - e.g. KasiWork will add "Complete your CV".
- Hubs gained a web address, description, public phone and email. Signed-in people visiting `/` go to their
  hub home. Footer legal links fixed.

## Database changes

`hubs`: `slug`, `description`, `phone`, `email`. New: `enquiries`, `page_views`.

## Tests

PHP 281 (every public page and its tags, structured data, hub visibility, services per package, impact numbers,
sitemap, robots, enquiries incl. validation, honeypot and rate limit, page counts, hub home steps and services,
extension point). Frontend 44. Browser 74 (every public page on phone and desktop with accessibility scans,
"near me" with a simulated location, hub search, FAQ, contact form, sign-up call to action, next steps).

## Notes

- Public first load is 126 KB against the 130 KB budget; Sprint 22 (performance) will trim the shared bundle.
- Content and inputs still needed from Ku Tirhisana are listed in docs/content-inputs.md.
