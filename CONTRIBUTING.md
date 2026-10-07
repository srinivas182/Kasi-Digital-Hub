# Contributing

## Workflow

1. Every sprint starts with a detailed specification that is approved before building.
2. Work happens on `main`. Each sprint is pushed as **one atomic commit** after all local checks pass.
3. CI must be green before the next sprint starts.

## Local checks (mandatory before pushing)

```bash
composer lint && composer analyse && composer test
CI=true npm run typecheck && npm run lint && npm run format:check && CI=true npm test && npm run build && npm run size
npx playwright test          # browser tests (needs built assets)
```

## Coding standards

- PHP: `declare(strict_types=1)` everywhere, Laravel Pint style, Larastan level 8, final classes by default.
- Thin controllers; business logic in Actions/Services inside the module's `src/Application`.
- Modules use only the shared kernel (`App\`, `Modules\Core\`) - never another portal's classes (ADR-005).
- TypeScript strict mode; React function components; no `any`.
- Screens are built from `resources/js/components` and `resources/js/layouts` (docs/design-system.md) and follow
  docs/content-guide.md.
- External services only through drivers (ADR-004).
- Services must never keep the HTTP request (or user) from their constructor - resolve it per call with
  `app('request')`. Controllers are cached per route and Octane reuses objects (an architecture test enforces this).
- Never commit secrets or real personal data. Demo data must be fictitious.

## Definition of done (every sprint)

1. All specified features built, **including demo data** (seeders listed in the module manifest).
2. Tests: unit and feature (plus browser tests for key journeys from S1).
3. Larastan level 8, Pint, ESLint and Prettier clean.
4. CI green on `main`.
5. Docs updated: sprint record in `docs/sprints/`, ADRs for new decisions, `CHANGELOG.md`, `VERSION`.
6. Sprint summary: files created/changed, database, API, UI, security and performance notes, next steps.

## Commit messages

`S<n>: <summary>` for sprint commits, e.g. `S0: project foundation`.
