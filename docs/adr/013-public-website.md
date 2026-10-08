# ADR-013: Public website - server-rendered, no map tiles, cookie-free page counts

- **Status:** Accepted
- **Date:** 2026-10-08
- **Sprint:** S5

## Context

The public website must be found on Google, preview well when shared on WhatsApp, load fast on prepaid data,
and respect POPIA.

## Decision

- **Server rendering:** public pages are rendered on the server (Inertia SSR) so content is readable without
  JavaScript. Search and link-preview tags (description, canonical, Open Graph) and schema.org structured data
  (Organization, LocalBusiness per hub, FAQPage) are rendered by the root view from a `seo` prop, so they are
  present even if the SSR process is down. Pages without a `seo` prop are `noindex`.
- **No map tiles:** "Find a hub" sorts hubs by distance using the phone's location (with permission, never sent
  to the server or stored) and offers a "Get directions" link that opens the phone's own maps app.
- **Cookie-free page counts:** one counter per page (route name) per day; no IP, cookie, user or device stored;
  bots ignored. No third-party analytics, so no cookie banner is needed.
- **Crawling:** generated `sitemap.xml` (public pages and every hub); `robots.txt` blocks private areas in
  production and everything in demo/staging.

## Consequences

- Good search visibility and previews with no tracking and minimal data use.
- Production runs the SSR process (`php artisan inertia:start-ssr`) under Supervisor (S24).
- No visual map; added later on hub pages only if Ku Tirhisana wants it.

## Alternatives considered

- Map tiles (OpenStreetMap / Google) - rejected for data cost and third-party requests.
- Google Analytics - rejected: tracking cookies, consent banner, POPIA exposure.
