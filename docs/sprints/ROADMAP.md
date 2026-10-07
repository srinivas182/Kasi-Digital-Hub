# Release 1 roadmap

25 small sprints. Each sprint: detailed specification approved first -> built with demo data and tests ->
local checks -> one push to `main` -> CI green -> sprint summary. Commercial scope and portal list:
see the Detailed Scope and Portals & Roles documents.

| # | Sprint | Main deliverables |
|---|---|---|
| S0 | Project foundation | Laravel 13 + React/TS/Inertia, module system (Portal SDK base), Docker, CI, quality tooling, demo framework, docs |
| S1 | Design system & app shell | UI components, layouts, design tokens, i18n scaffold, PWA, SA formats, browser tests |
| S2 | Login & profile | Phone + OTP, OTP abuse protection, staff 2-step login, single profile, consent, audit log, age policy |
| S3 | Hierarchy & roles | SA geography data, national > province > city > hub, organisations, 25 roles, scoping, hub entitlements |
| S4 | Documents, notifications, events | Document vault, WhatsApp/SMS/email + preferences, event bus & catalogue, scheduler, updates feed |
| S5 | Hub home & public website | Personal dashboard, next steps, public site, hub finder, legal pages and consent texts |
| S6 | Admin console (part 1) | Hub-in-a-box, module switches, users, verification & moderation queues |
| S7 | KasiHub Ops | QR check-in, facilitator assisted mode, events, bookings, follow-ups, hub dashboard |
| S8 | AI, search & documents core | AI gateway (drivers, prompts, cost tracking, safety), search + vectors, PDF generation |
| S9 | KasiWork: seekers & AI CV | Seeker profile, AI CV builder, PDF CV |
| S10 | KasiWork: employers & listings | Verification, AI listing enhancer, public job pages, Google Jobs data, sitemap |
| S11 | KasiWork: matching | Filters + semantic search + AI re-rank, 0-10 scores, job feed, apply |
| S12 | KasiWork: hiring | Connect, messaging, pipeline, interviews, employer plans & credits |
| S13 | KasiLearn: courses & authoring | Catalogue, course builder, media pipeline (low-data video/images) |
| S14 | KasiLearn: learning & assessments | Player with offline progress, quizzes, auto-marking |
| S15 | KasiLearn: certificates & cohorts | QR certificates, profile/CV sync, score recalculation, attendance, cohorts, vouchers, AI tutor |
| S16 | KasiStart: registration | Readiness check, pre-filled pack, guided BizPortal steps, status tracker |
| S17 | KasiStart: partners & referrals | Partner portal inbox, bank/insurer referrals with consent, CSD checklist |
| S18 | KasiConnect: mentors | Profiles, matching, booking, AI notes, events, safeguarding |
| S19 | Commercial & finance console | Price list, packages, tiers, discounts, royalties, attribution, split settlement, statements |
| S20 | Funder portal & regional console | Programmes, targets, early warnings, roll-ups, exports |
| S21 | Admin console (part 2) & demo | AI cost controls, support tools, help centre, content review, demo stories, role switcher, demo reset |
| S22 | Performance | Octane, caching, query tuning, load tests (100k concurrent), page-speed budgets |
| S23 | Security & compliance | Security review, enforced CSP, POPIA export/delete, accessibility, languages |
| S24 | Deployment | Production images, Nginx, workers, SSL, backups, monitoring, kasidigitalhub.co.za |
