# Changelog

All notable changes to the Kasi Digital Hub platform. Versions follow `0.<sprint>.<patch>` until Release 1.

## [0.5.0] - 2026-10-08 - Sprint 5: Hub home & public website

### Added
- Public website: home, find a hub (search and "near me"), hub pages, employers, funders and partners, about,
  help, contact; mobile menu.
- Search and sharing: server-rendered tags, structured data, sitemap.xml, robots.txt (ADR-013).
- Enquiry forms with spam protection; cookie-free page counts.
- Hub home: next steps with progress, your hub, service tiles, latest updates; `HomeContributor` extension point.
- docs/content-inputs.md - wording and details needed from Ku Tirhisana before launch.

### Fixed
- Footer links to the terms and privacy pages.

## [0.4.0] - 2026-10-07 - Sprint 4: Documents, notifications & events

### Added
- Private document vault: virus scanning, signed short-lived links, consent-based sharing, audited access,
  photo shrinking, expiry reminders, "Documents" account tab.
- Notifications over in-app, WhatsApp, SMS and email with preferences, consent rules, quiet hours, duplicate
  suppression, daily caps, SMS fallback and cost logging; "Notifications" account tab.
- Platform events (after-commit) with an append-only event log and listeners; generated event catalogue and
  WhatsApp template list.
- "What changed and why" updates feed with a bell and unread count.
- Horizon queue priorities, scheduler with heartbeats, Docker scheduler and optional ClamAV services.
- Demo documents, events, deliveries and feeds; ADR-012.

### Fixed
- Server messages in partly translated languages fall back to English instead of showing raw keys.

## [0.3.0] - 2026-10-07 - Sprint 3: Hierarchy, organisations & roles

### Added
- South African geography: 9 provinces, 8 metros and pilot municipalities; CSV importer for the official list.
- National structure: province -> city -> hub; places (villages, townships); organisations and members.
- 24 scoped roles declared in module manifests, held through role assignments (ADR-011).
- Access resolver with caching, hub data scoping (`visibleTo`), `portal` middleware and gate.
- Hub packages (Base, Growth, Enterprise, Full) and add-ons, audited.
- Role-filtered portal menus; home hub and location on sign-up and profile; "My roles" on the account page.
- `kasi:roles` and `kasi:geography:import` commands.
- Demo structure: 12 hubs, 10 organisations, 16 demo staff and citizens with roles, 500 demo citizens.

## [0.2.0] - 2026-10-07 - Sprint 2: Login & profile

### Added
- Phone + PIN sign-in with SMS codes only for new devices, PIN reset and sign-up; remembered devices.
- Abuse protection for one-time codes (per number, IP, device and range; mobile-only; daily cap; bot check).
- Sign-up with age policy (guardian consent for 16-17; under 16 declined), consent per purpose (POPIA).
- Staff authenticator second step with backup codes.
- Account settings: profile, email confirmation, PIN, phone change, devices, privacy choices, deletion request.
- Idle timeouts, remote device sign-out, security alerts, audit log with retention.
- Demo accounts and on-screen demo codes; docs/demo.md; ADR-010.

### Changed
- App-bar menus use a lightweight disclosure component (smaller signed-in pages).
- Form controls split into separate files so pages only load what they use.

## [0.1.0] - 2026-10-07 - Sprint 1: Design system & app shell

### Added
- Design tokens, light/dark themes and self-hosted Poppins font.
- UI component library (28 components) on Radix UI primitives, plus platform chrome components.
- Six layouts: public, auth, app, portal, console and kiosk (automatic sign-out when idle).
- Module navigation (`nav` in module.json) shared with every page; portal switcher.
- Language switching (English; isiZulu and Xitsonga draft samples, hidden in production).
- South African formatters (money, phone, date) in PHP and TypeScript with a shared test fixture.
- Installable web app: manifest, icons, service worker and offline page.
- Branded error pages; internal UI kit at /ui-kit.
- Playwright browser tests with axe accessibility scans (phone and desktop) in CI.
- Docs: design system, content guide, ADR-007 to ADR-009.

## [0.0.1] - 2026-10-07 - Sprint 0: Project foundation

### Added
- Laravel 13 + Inertia.js + React + TypeScript + Tailwind CSS application.
- Module system (Portal SDK foundation) with 13 Release 1 modules.
- Platform shell page, `/version` endpoint, `/up` health check, baseline security headers.
- Platform configuration (`config/kasi.php`): branding, drivers, demo mode, SA defaults.
- Demo-data framework: `DemoSeeder`, `kasi:demo:reset`, `SouthAfricanFaker`.
- Docker Compose development stack and setup scripts.
- Quality tooling (Pint, Larastan L8, Pest, ESLint, Prettier, Vitest, bundle budget) and CI workflow.
- Documentation: README, setup, architecture, ADR-001-006, roadmap, contributing guide.
