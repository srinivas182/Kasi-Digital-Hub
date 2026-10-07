# Changelog

All notable changes to the Kasi Digital Hub platform. Versions follow `0.<sprint>.<patch>` until Release 1.

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
