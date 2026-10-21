# Changelog

All notable changes to the Kasi Digital Hub platform. Versions follow `0.<sprint>.<patch>` until Release 1.

## [0.29.0] - 2026-10-21 - Sprint 29: KasiBiz part 2 (stock, compliance, summaries)

### Added
- Stock with history, stock-takes and KasiMarket-linked quantities (core ProductStock contract); daily low-stock
  messages; compliance calendar (CIPC, SARS turnover tax, VAT, EMP201, COIDA, B-BBEE, trading permit) with reminders;
  shared summaries by 7-day links; partner signals "compliance up to date" and "tracks stock" (ADR-037). KasiBiz is
  complete.

### Fixed
- KasiMarket notifications now appear under "business" in notification settings; missing menu icons; band labels.

## [0.28.0] - 2026-10-20 - Sprint 28: KasiBiz part 1 (money)

### Added
- KasiBiz: cash book with categories, payment types, opening balances and private receipts; profit versus cash;
  30-day corrections with history; KasiMarket sales imported through a core extension point; customers, suppliers
  and money owed with WhatsApp reminders; month summary, CSV and PDF; partner signals (ADR-036).

### Fixed
- Missing success text colour token (15 places) and a wrong "text on primary" class (4 places).

## [0.27.0] - 2026-10-20 - Sprint 27: KasiMarket trust and safety

### Added
- Reviews and trader replies, ratings from 3 reviews, "Replies quickly" badge, private reliability signals, buyer
  no-shows, disputes with private evidence and support decisions, shop reports, strikes and suspension, trader
  insights and CSV, business signals for partner eligibility (ADR-035). KasiMarket is complete.

## [0.26.0] - 2026-10-20 - Sprint 26: KasiMarket payments and delivery

### Added
- Online payment for KasiMarket orders with the split at source (trader, hub, Kasi) via a core Payments contract;
  trader payment readiness (verified bank letter); 30-minute stock holds; automatic and partial refunds; hub
  collection points with 6-digit codes, a staff desk and 7-day returns; delivery zones and free delivery; hub market
  share in statements; "Get help" with the order (ADR-034).

### Changed
- Pages load per portal (first load 125.0 -> 120.2 KB), with a guard in the size check.

### Fixed
- Commercial merchant columns widened for marketplace sellers.

## [0.25.0] - 2026-10-19 - Sprint 25: KasiMarket ordering

### Added
- Cart and checkout per shop; in-app and WhatsApp orders tracked by reference; stock reserved and given back; order
  journey with 24-hour reminders and 48-hour lapses; moderated order messages; phone shared only by choice; assisted
  ordering at hubs; trader orders dashboard and collection/delivery settings (ADR-033).

## [0.24.0] - 2026-10-19 - Sprint 24: KasiMarket shops and products

### Added
- KasiMarket: shops for KasiStart businesses, products with options, stock and small WebP photos (metadata stripped),
  prohibited-items checks, first-3 review and reports via the review inbox, public market on market. with WhatsApp
  ordering and sharing, trader dashboard, demo shop (ADR-032).

## [0.23.0] - 2026-10-19 - Sprint 23: Portal subdomains and the public job board

### Added
- Portal subdomains (my., jobs., learn., start., connect., hub., partner., funder., region., admin.) with single
  sign-on and sign-out, redirects that keep old links working, sign-in on the main domain with return; public job
  board; PIN re-confirmation for sensitive staff actions; browser tests with subdomains on (ADR-031).

## [0.22.0] - 2026-10-17 - Sprint 22: Performance and scale

### Added
- Laravel Octane (Swoole) with memory-safety reset and test; browser suite under Octane in CI; query budgets;
  presigned file delivery for object storage; trusted proxies by configuration; `kasi:perf:seed`; k6 load-test kit;
  capacity document (ADR-030).

### Changed
- Indicators counted by the database with a covering index and a 5-minute cache.
- Text sent once per language and version instead of with every page.

### Fixed
- Check-in desk search no longer queries per person.
- Indicator age filter includes people on their birthday.

## [0.21.0] - 2026-10-11 - Sprint 21: Admin console part 2 and the complete demo

### Added
- Support requests and staff queue, safe person summaries, account help with one-time codes; help centre with
  reviewed articles and context help; AI cost projection and budget alerts; review inbox; system status; demo
  stories, role switcher, demo reset and a demo health check (ADR-029).

## [0.20.0] - 2026-10-11 - Sprint 20: Funder portal and regional console

### Added
- Indicator and health-signal extension points; funder programmes with targets, trends, breakdowns, frozen monthly
  snapshots, verifiable impact reports and CSV, all with small-number suppression; regional console for city and
  provincial coordinators (ADR-028).

### Fixed
- Commercial and finance admins regain access to hub dashboards and comparison (lost in S19).
- Scrolling tables in the commercial console are reachable by keyboard.
- Role permissions are now guarded by a snapshot test.

## [0.19.0] - 2026-10-11 - Sprint 19: Commercial and finance console

### Added
- National catalogue with price bands and split rules (four-eyes), hub readiness, checkout with split settlement
  (fake / Paystack drivers), point of sale, immutable ledger, statements, payouts, package invoices, reconciliation,
  refunds, receipts / tax invoices; KasiWork extra advert slots (ADR-027).

### Fixed
- Quiz true/false questions: the question type column was too short for MySQL (S14).

### Changed
- CI keeps the test server's output and Laravel's log when browser tests fail; the test server runs with --no-reload.

## [0.18.0] - 2026-10-10 - Sprint 18: KasiConnect mentors

### Added
- Mentor vetting and approval, explainable mentor suggestions, agreements, sessions with private notes, moderated
  messages with early contact-detail blocking, group sessions at hubs, hours certificates.
- Safeguarding: concern reporting, officer queue, urgent pause, under-18 protections and guardian SMS (ADR-026).

## [0.17.0] - 2026-10-10 - Sprint 17: KasiStart partners and referrals

### Added
- Partner registration with data-sharing agreement, offers with structured eligibility, explainable "Support for you",
  consented referrals with time-limited audited document access, partner pipeline with no-ghosting rules, two-sided
  outcomes and follow-ups (ADR-025).
- Architecture test: every rendered Inertia page has a page file.

## [0.16.0] - 2026-10-10 - Sprint 16: KasiStart business registration

### Added
- Business profiles with co-owners, personalised formalisation journey with proof, legal-form guide, pre-filled
  B-BBEE affidavit, editable step content, business plan builder with calculator and verifiable summary, readiness
  score (ADR-024).
- `scripts/prepush.sh` runs every local check and stops at the first failure.

### Fixed
- Rate limits are counted per person and per route (they were shared across all limited routes).
- The business calculator shows money in the platform format on every browser.

## [0.15.0] - 2026-10-10 - Sprint 15: KasiLearn certificates, cohorts and moderation

### Added
- Verifiable certificates and statements of results (on the KasiWork CV), learning record PDF, revocation.
- Cohorts with hub approval, join codes, waiting lists, hub sessions with attendance, dashboard and nudges.
- Moderation sampling, re-assessment, certificate withdrawal and re-issue, CSV report (ADR-023).

### Changed
- Public first load reduced to about 122.9 KB (tailwind-merge replaced); CI warns above 125 KB.

## [0.14.0] - 2026-10-10 - Sprint 14: KasiLearn learning and assessments

### Added
- Enrolment, learning player with data saver, offline downloads with progress sync, practice and graded quizzes
  with a builder and AI-suggested questions, assignments and assessor marking, course completion (ADR-022).
- `scripts/test-mysql.sh` runs the PHP tests against MySQL locally, as CI does.

### Changed
- CI runs on Ubuntu 24.04 (pinned) with actions/cache v5.

## [0.13.0] - 2026-10-10 - Sprint 13: KasiLearn courses and authoring

### Added
- Training provider registration, team and accreditation claims; course authoring with a safe rich-text editor,
  AI writing help, accessibility checks and data sizes; low-data video/audio conversion; review and publishing
  with frozen versions; catalogue with public course pages and "Courses for you" (ADR-021).

### Security
- Dependency advisories resolved (TipTap 3.31.3, shell-quote).

## [0.12.0] - 2026-10-09 - Sprint 12: KasiWork hiring

### Added
- Applying, my applications, employer pipeline with bulk moves, notes, interviews and messages; blind
  shortlisting; no-ghosting outcomes; two-sided hire confirmation and retention check-ins (ADR-020).

## [0.11.0] - 2026-10-09 - Sprint 11: KasiWork matching

### Added
- Explainable match scores with reasons and gaps, synonym and embedding-based similar skills (ADR-019).
- "Jobs for you", invitations to apply, profile-view history, hiding from employers, daily job alerts.
- Suggested anonymised candidates for verified employers; matching insights with a fairness check.

## [0.10.0] - 2026-10-09 - Sprint 10: KasiWork for employers and job listings

### Added
- Employer registration (CIPC or community employer), verification checklist, team, company profile, dashboard.
- Job listings with required pay (minimum wage check), fair-language and scam checks, review queue, expiry and
  renewal; "Help me write it" for adverts; job board, saved jobs and public job pages (ADR-018).
- Occupation starter list and official OFO import command.

## [0.9.0] - 2026-10-09 - Sprint 9: KasiWork for job seekers

### Added
- Six-step job profile (informal work counts), AI-assisted CV points and summary with a fact check, verifiable
  CV PDFs in two templates with share links and WhatsApp sharing, voice notes (English first), assisted CV
  building, hub home next steps (ADR-017).

## [0.8.0] - 2026-10-09 - Sprint 8: AI, search and document generation

### Added
- Shared AI layer with provider drivers, versioned prompts, identifier removal, budgets, switches, audit and
  fallbacks; moderation and translation services; admin AI controls and content review queue (ADR-016).
- Platform search (hubs, events, help) with permission-aware results.
- PDF documents with QR verification, revocation and share links; certificates of attendance for hub events.
- "Help me write it" for hub event descriptions.

### Fixed
- Signed-in pages no longer scroll sideways on 360 px phones (theme switch moved into the account menu on phones).

## [0.7.0] - 2026-10-09 - Sprint 7: KasiHub Ops

### Added
- Door-screen check-in with a QR code that changes every 2 minutes; front-desk check-in and walk-ins.
- Assisted registration and assisted help for people at the hub (AssistedSession extension point, ADR-015).
- Hub events with sign-ups, waiting lists, reminders, cancellation and attendance; events on the public hub page
  and the hub home (HubActivity extension point).
- Hub dashboard, coordinator comparison and CSV export; hub settings (internet connection, door screen,
  facilitators); 24-month visit anonymisation.

## [0.6.0] - 2026-10-08 - Sprint 6: National admin console, part 1

### Added
- Admin console: overview, people (search, roles, suspension), hubs (create, edit, packages), organisations
  (verification, members), document verification queue, enquiries inbox, audit log with CSV export.
- Fine-grained permissions declared in module manifests; permission-filtered menus (ADR-014).
- Mandatory reasons for sensitive actions; national roles only by super admins.
- Demo operations admin and support agent; demo verification queue, pending organisations and enquiries.

### Fixed
- Roles can no longer be given with an empty or non-existent scope.
- Sign-in code limits now tell shared hub computers apart (anonymous browser id) and allow trusted hub IPs.

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
