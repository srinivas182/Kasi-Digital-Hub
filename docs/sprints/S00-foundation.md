# Sprint 0 - Project foundation

- **Status:** Complete
- **Version:** v0.0.1

## Objectives

A production-grade foundation that later sprints build on without restructuring: module architecture,
tooling, CI under 10 minutes, demo-data framework and documentation.

## Delivered

- Laravel 13 (PHP 8.3+), Inertia.js + React 19 + TypeScript (strict), Tailwind CSS 4, Vite 8, SSR entry.
- Module system: `module.json` manifests, `ModuleRegistry` (discovery, validation, dependency ordering,
  config switch-off), `ModuleServiceProvider` (providers, web routes, `/api/v1` routes, migrations).
- 13 modules scaffolded: Core, Site, Hub, HubOps, Work, Learn, Start, Connect, Region, Funder, Partner,
  Admin, Commercial - each with its own PSR-4 namespace and standard folders.
- Platform shell page (`Site/Home`) listing the Release 1 portals.
- `config/kasi.php`: branding, version, module switches, service drivers (fake/log by default), demo mode,
  South African defaults.
- Demo framework: `DemoSeeder` orchestrator, `kasi:demo:reset` (guarded), `SouthAfricanFaker` (SA names,
  places, +27 numbers, ID numbers that always fail the Luhn check).
- `/up` health check, `/version` endpoint, baseline security headers (CSP report-only), JSON log channel.
- Docker Compose dev stack: PHP-FPM, Nginx, MySQL 8.4, Redis 7, Meilisearch, Mailpit, Horizon worker, Vite.
- Quality: Pint (strict types everywhere), Larastan level 8, Pest (unit, feature, architecture, module tests),
  ESLint, Prettier, Vitest, bundle-size budget.
- CI: one workflow on push to `main`, path filters, parallel jobs, cancellation of superseded runs,
  dependency audits.
- Docs: README, setup guide, architecture, ADR-001 to ADR-006, roadmap, contributing guide, changelog.

## Database changes

None beyond Laravel's default tables (users, cache, jobs). The users table is redesigned in Sprint 2.

## API changes

`GET /version` (JSON). Module API routes are mounted under `/api/v1` when a module enables them.

## Tests

46 PHP tests (incl. architecture rules for all module boundaries) and 3 frontend tests.

## Notes and deviations from the specification

- **Octane** is deferred to Sprint 22 (performance), where it is installed with its server runtime and
  load-tested. Horizon is installed and configured now.
- **Dependabot** is not enabled because work stays on `main`; dependency vulnerabilities are caught by
  `composer audit` and `npm audit` in CI instead.
- **Branch strategy:** all work is committed directly to `main` (one atomic push per sprint), as agreed.
- **Public pages module:** the public website is its own module (`Site`), giving 13 modules rather than 12.
