#!/usr/bin/env bash
# Runs the PHP tests against MySQL (as CI does) - catches MySQL/SQLite differences before pushing.
# Usage: scripts/test-mysql.sh [pest arguments]   (needs a local MySQL with a kasi_test database)
set -euo pipefail
export DB_CONNECTION=mysql DB_HOST="${DB_HOST:-127.0.0.1}" DB_PORT="${DB_PORT:-3306}" DB_DATABASE="${DB_DATABASE:-kasi_test}"
export DB_USERNAME="${DB_USERNAME:-kasi}" DB_PASSWORD="${DB_PASSWORD:-kasi}"
exec vendor/bin/pest "$@"
