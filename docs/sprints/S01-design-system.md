# Sprint 1 - Design system & app shell

- **Status:** Complete
- **Version:** v0.1.0

## Objectives

One consistent, accessible, mobile-first look and feel for all portals, so every later sprint builds screens
from shared parts.

## Delivered

- **Design tokens** (`resources/css/app.css`): brand palette, semantic light/dark tokens, radii, shadows,
  focus ring, reduced-motion support. Self-hosted Poppins (Latin subset).
- **Dark mode**: system setting + saved manual toggle, applied before first paint.
- **28 UI components** (`resources/js/components/ui`) - see docs/design-system.md - built on Radix primitives
  where interaction is complex (ADR-007).
- **Platform chrome**: skip link, demo banner, offline notice, language switcher, theme toggle, brand mark,
  portal switcher, bottom navigation, sidebar.
- **Six layouts**: public, auth, app (hub home), portal, console, kiosk (idle warning + automatic sign-out).
- **Navigation model**: `nav` section in `module.json` for all portals; shared with every page.
- **Languages** (ADR-008): `lang/en.json` complete; isiZulu and Xitsonga draft samples (hidden in production);
  `POST /locale`; `<html lang>` kept in step.
- **South African formatters** in PHP and TypeScript, tested against one shared fixture.
- **Installable app** (ADR-009): manifest, icons, service worker (assets only - pages never cached), offline page.
- **Branded error pages** for 403, 404, 419, 429, 500 (when debug is off) and 503, with shared props and
  language applied even when the error happens before the web middleware.
- **UI kit** at `/ui-kit` with every component and a preview of every layout (never in production).
- **Docs**: design system, content guide, ADR-007 to 009.

## Tests

- PHP: 90 tests (formatters against the shared fixture, language switching and draft hiding, shared props,
  navigation labels translated, error pages, UI kit routes, PWA files).
- Frontend: 41 tests (formatters, i18n, OTP input, phone input, score ring, confirm dialog, data table, home page).
- Browser (new, Playwright, phone 360 px and desktop 1280 px): 26 tests - pages fit the screen, axe scan with no
  serious/critical issues in light and dark mode, skip link, dark mode persistence, language switch, 404 page,
  offline page and manifest.

## Performance

Entry 105.5 KB, public home page adds 15.5 KB (public first load 120.9 KB against a 130 KB budget). Pages load
only the components they use.

## Issues found and fixed during the sprint

- Browser tests found that error pages for unknown URLs had no shared props (the error happens before the web
  middleware) - fixed by `App\Support\Errors\ErrorPage`.
- axe found contrast failures for red and blue text on tinted backgrounds and for the dark-mode primary colour -
  fixed with `*-ink` tokens and a lighter dark-mode primary.
- The portal switcher had no accessible name on phones - fixed.
- A menu-based theme toggle pulled ~30 KB into public pages - replaced with a plain button.

## Database / API changes

No database changes. New routes: `POST /locale`, `GET /offline`, `GET /ui-kit`, `GET /ui-kit/layouts/{layout}`.

## Not in this sprint (by design)

Sign-in (S2); role- and package-based menus (S3); real public website content (S5); professional translations (S23).
