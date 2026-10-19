# ADR-031: Portal subdomains, single sign-on and the public job board

- **Status:** Accepted
- **Date:** 2026-10-19
- **Sprint:** S23

## Decisions

1. **One application, one subdomain per portal** (`my.`, `jobs.`, `learn.`, `start.`, `connect.`, `hub.`, `partner.`,
   `funder.`, `region.`, `admin.`; the main domain for the public site, help and sign-in). `market.`, `biz.` and
   `brand.` are reserved for S24-S31. One setting switches it on: `KASI_SUBDOMAINS=true`, `KASI_DOMAIN=<base domain>`.
   Off means one host, exactly as before.
2. **Portals own path prefixes** (`config/kasi.php` -> `subdomains.portals`, longest prefix wins). A global middleware
   (`PortalHost`) sends page loads on the wrong host to the owner: a permanent redirect, or a full-page switch for
   in-app navigation. Old links and QR codes keep working. Form posts and webhooks are never redirected; checkout and
   payment pages are shared; hosts that aren't ours (server IP, load balancer health checks) are never redirected.
3. **Entry points are clean** (a subdomain on its own opens its portal); paths inside portals are unchanged for now.
4. **Single sign-on:** the session cookie covers the whole domain, so signing in or out applies everywhere. Sign-in is
   on the main domain and returns people to the page they asked for.
5. **PIN re-confirmation** for 12 sensitive staff actions, valid 15 minutes; five wrong PINs sign the person out.
6. **Public job board** at `/jobs` (the home of `jobs.`): live adverts from verified employers only, nothing personal.
7. **Browser tests and CI run with subdomains on** (`*.kasi.test` resolved by the browser), as production will.
8. **Production (S34)** needs wildcard DNS (`*.<domain>`), certificates for the subdomains (wildcard via DNS
   validation, or a certificate listing each), `SESSION_SECURE_COOKIE=true`, and app files from one host (`ASSET_URL`)
   with CORS for our subdomains.
