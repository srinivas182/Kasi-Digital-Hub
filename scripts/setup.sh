#!/usr/bin/env bash
# One-command local setup (Linux / macOS / WSL). Requires Docker.
set -euo pipefail
cd "$(dirname "$0")/.."

[ -f .env ] || cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate --ansi
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force

echo
echo "KasiHub is running:  http://localhost:8080"
echo "Mail (Mailpit):      http://localhost:8025"
