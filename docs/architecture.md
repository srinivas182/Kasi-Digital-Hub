# Architecture

## Overview

KasiHub is a **modular monolith** (ADR-001): one Laravel application where each portal is a self-contained
module in `modules/`. Modules share one login, one profile, one payments system and one impact database
through the shared kernel (`modules/Core` plus `app/`), and communicate only through contracts and events
(ADR-005). New portals plug in by adding a module - the **Portal SDK**.

```
                ┌──────────────────────────────────────────────────────────┐
  Browser /     │                     Laravel application                   │
  phone (PWA) ──►  Inertia + React pages (SSR for public pages)             │
  Partners    ──►  REST API /api/v1                                         │
                │  ┌──────┐┌──────┐┌──────┐┌──────┐┌────────┐┌─────┐ ...    │
                │  │ Site ││ Hub  ││ Work ││Learn ││ Start  ││ ... │ modules │
                │  └──┬───┘└──┬───┘└──┬───┘└──┬───┘└───┬────┘└──┬──┘        │
                │     └───────┴── contracts & events ──┴────────┘           │
                │                 Core (shared kernel)                      │
                └───────┬──────────────┬──────────────┬─────────────────────┘
                     MySQL 8.4       Redis (cache,    Meilisearch
                   (read replicas    sessions,        (search +
                    at scale)        queues)          vectors)
```

## Module layout

```
modules/<Module>/
  module.json            manifest: name, group, release, dependencies, routes,
                         entitlement, roles, permissions, events, impact metrics,
                         demo seeders
  src/                   PHP code, namespace Modules\<Module>\ (Domain, Application,
                         Http, Models, Database\Seeders, Database\Factories, Providers)
  routes/web.php         web routes (web middleware)
  routes/api.php         API routes, auto-prefixed /api/v1
  database/migrations/   module migrations (auto-loaded)
  resources/js/Pages/    React pages - Inertia name "<Module>/<Page>"
  tests/                 Pest tests, namespace Modules\<Module>\Tests\
```

`App\Providers\ModuleServiceProvider` discovers manifests via `App\Support\Modules\ModuleRegistry`, sorts
modules by dependency, and registers providers, routes and migrations for enabled modules. Modules can be
switched off with `KASI_MODULES_DISABLED`. Per-hub switches (entitlements) arrive in Sprint 3.

Adding a module: create the folder and `module.json`, add its two PSR-4 lines to `composer.json` (a test
fails if they are missing), and add it to the architecture test list.

## National structure and access (ADR-011)

```
National -> Province -> City (metro or local municipality) -> Hub
Organisations: employers, training providers, partners, funders, hub operators, platform
```

- **Roles** are declared in module manifests (scope + access level per portal) and held through **role
  assignments** with a scope. `AccessResolver` gives the level per portal and the hubs a person may see.
- **Hub packages** (`config/kasi.php` -> `hubs`) switch on the portals a hub may deliver locally; national
  services stay open to everyone.
- **Guards:** `->middleware('portal:HubOps,manage')`, `Gate::allows('portal', ['Work', 'assist'])`, and
  `Model::query()->visibleTo($user, 'Work')` for hub-owned data (models with a hub column use `BelongsToHub`).
- **Commands:** `kasi:roles assign|revoke|list|available`, `kasi:geography:import`.

## Key conventions

- **Tenancy (ADR-003):** single database, every tenant-owned row scoped by the national → province → city →
  hub hierarchy (Sprint 3).
- **External services (ADR-004):** AI, SMS, WhatsApp, payments, search and storage sit behind drivers. Demo
  and CI use fake/log drivers.
- **Time:** stored in UTC, displayed in SAST (Africa/Johannesburg). Currency ZAR. Phones in E.164 (+27).
- **Demo data:** every module ships demo seeders listed in its manifest; `DemoSeeder` runs them in
  dependency order; `kasi:demo:reset` rebuilds the demo environment (demo mode only).
- **Performance budgets:** first-load JS ≤ 150 KB gzip, each page ≤ 60 KB gzip (checked in CI).

## Scale path

Built from day one for horizontal scale: stateless app servers, Redis for sessions/cache/queues, heavy work
(AI, matching, notifications) on queues, search outside MySQL. The VPS deployment (Sprint 24) serves demos
and pilot hubs; national scale moves the same code to an autoscaling cloud cluster.
