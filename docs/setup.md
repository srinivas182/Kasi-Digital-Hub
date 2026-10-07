# Setup guide

## Requirements

- Docker Desktop (or Docker Engine + Compose v2)
- Git

PHP and Node do not need to be installed locally; everything runs in containers.

## First run

```bash
git clone https://github.com/srinivas182/Kasi-Digital-Hub.git
cd Kasi-Digital-Hub
scripts/setup.sh            # Windows: powershell -File scripts/setup.ps1
```

| Service | URL |
|---|---|
| App | http://localhost:8080 |
| Vite dev server (hot reload) | http://localhost:5173 |
| Mailpit (all local email) | http://localhost:8025 |
| Meilisearch | http://localhost:7700 |
| MySQL | localhost:3306 (kasi / secret) |

## Everyday commands

```bash
docker compose up -d                          # start
docker compose exec app php artisan migrate   # run migrations
docker compose exec app vendor/bin/pest       # PHP tests
docker compose exec vite npm test             # frontend tests
docker compose exec app php artisan kasi:demo:reset --force   # reload demo data (demo mode only)
```

## Quality checks before pushing

Run these locally - CI runs the same checks once per push:

```bash
composer lint && composer analyse && composer test
npm run typecheck && npm run lint && npm run format:check && npm test && npm run build && npm run size
```

## Environments

| Environment | Purpose | Demo mode | External services |
|---|---|---|---|
| Local | Development | On | Demo drivers (no real SMS, AI, payments) |
| CI | Automated checks | Off | Demo drivers |
| Demo / staging | Client demos, UAT | On | Demo drivers, or sandbox accounts |
| Production | Live platform | **Never** | Real providers |

Demo mode and drivers are set in `.env` (see `.env.example`).
