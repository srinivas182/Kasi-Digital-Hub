#!/usr/bin/env bash
# Every local check before a push, stopping at the first failure (S16).
# Usage: scripts/prepush.sh            - all checks, PHP tests on SQLite and MySQL
#        E2E=1 scripts/prepush.sh      - also the full browser suite (always before a sprint's final push)
#        SKIP_MYSQL=1 scripts/prepush.sh - without the MySQL run (only when MySQL isn't available)
set -euo pipefail
cd "$(dirname "$0")/.."

step() { printf '\n== %s\n' "$1"; }

step "Generated docs";              php artisan kasi:docs:generate >/dev/null
step "PHP style (Pint)";             vendor/bin/pint --test
step "Static analysis (Larastan)";   vendor/bin/phpstan analyse --memory-limit=1G --no-progress
step "Formatting (Prettier)";        npm run -s format:check
step "Lint (ESLint)";                npm run -s lint
step "Types (TypeScript)";           npm run -s typecheck
step "Frontend tests (Vitest)";      npx vitest run --silent
step "PHP tests (SQLite)";           vendor/bin/pest --parallel
if [ "${SKIP_MYSQL:-0}" != "1" ]; then
    step "PHP tests (MySQL, as CI)"; scripts/test-mysql.sh --parallel
fi
step "Build and bundle budgets";     CI=true npm run -s build >/dev/null && npm run -s size
if [ "${E2E:-0}" = "1" ]; then
    step "Browser tests (Playwright, phone and desktop)"
    rm -rf test-results database/e2e.sqlite
    MAIL_MAILER=smtp npx playwright test --reporter=line
    rm -rf test-results database/e2e.sqlite
fi
step "Dependency audits";            composer audit --no-interaction && npm audit --audit-level=high

printf '\nAll checks passed.\n'
