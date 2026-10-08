# Kasi Digital Hub (KasiHub)

National platform for South Africa's Kasi Digital Hubs: jobs, skills, business formalisation and mentorship
in one place, delivered online and through physical hubs in every city.

- **Owner:** Ku Tirhisana Consultancy (Pty) Ltd
- **Built by:** Mayura Consultancy Services (MCS)
- **Production domain:** kasidigitalhub.co.za (deployed in Sprint 24)
- **Working brand:** KasiHub (configurable in `config/kasi.php`)

## Release 1 portals

| Group | Portals |
|---|---|
| Front doors | Public website (`Site`), Hub home (`Hub`) |
| Service portals | KasiWork (`Work`), KasiLearn (`Learn`), KasiStart (`Start`), KasiConnect mentors (`Connect`) |
| Operations | KasiHub Ops (`HubOps`), Regional console (`Region`), Funder & Programme portal (`Funder`), Partner portal (`Partner`) |
| National control | National admin console (`Admin`), Commercial & finance console (`Commercial`) |

Later releases add KasiMarket, KasiBiz and KasiBrand as new modules - no changes to existing portals.

## Stack

Laravel 13 (PHP 8.3+) · React + TypeScript via Inertia.js · Tailwind CSS · Radix UI · MySQL 8.4 · Redis + Horizon ·
Meilisearch · Pest · Larastan (level 8) · Vitest · Playwright + axe · Vite.

## Quick start

```bash
scripts/setup.sh          # Linux / macOS / WSL  (Windows: scripts/setup.ps1)
```

Then open http://localhost:8080. Full instructions: [docs/setup.md](docs/setup.md).

## Documentation

- [Setup guide](docs/setup.md)
- [Architecture](docs/architecture.md) and [ADRs](docs/adr)
- [Design system](docs/design-system.md) and [content guide](docs/content-guide.md) - live UI kit at `/ui-kit`
- [Sprint records](docs/sprints)
- [Demo environment and demo accounts](docs/demo.md)
- [Content and inputs needed before launch](docs/content-inputs.md)
- [Contributing & definition of done](CONTRIBUTING.md)
- [Changelog](CHANGELOG.md)
