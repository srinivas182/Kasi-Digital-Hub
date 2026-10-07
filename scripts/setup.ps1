# One-command local setup (Windows PowerShell). Requires Docker Desktop.
$ErrorActionPreference = "Stop"
Set-Location (Join-Path $PSScriptRoot "..")

if (-not (Test-Path ".env")) { Copy-Item ".env.example" ".env" }
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate --ansi
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force

Write-Host ""
Write-Host "KasiHub is running:  http://localhost:8080"
Write-Host "Mail (Mailpit):      http://localhost:8025"
