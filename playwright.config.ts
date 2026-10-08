import { defineConfig, devices } from '@playwright/test';
import { closeSync, openSync } from 'node:fs';

/**
 * Browser tests (smoke level): key pages at phone and desktop sizes, keyboard use,
 * dark mode, language switching and automated accessibility scans (axe).
 * Assets must be built first (npm run build). CI: see the "e2e" job.
 */
const PORT = Number(process.env.E2E_PORT ?? 8123);
const executablePath = process.env.PW_CHROMIUM_PATH || undefined;

// The e2e database file must exist before migrations run.
closeSync(openSync('database/e2e.sqlite', 'a'));

export default defineConfig({
    testDir: 'tests/e2e',
    fullyParallel: true,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? [['github'], ['list']] : 'list',
    use: {
        baseURL: `http://127.0.0.1:${PORT}`,
        trace: 'retain-on-failure',
        launchOptions: { executablePath },
    },
    projects: [
        {
            name: 'phone',
            use: { ...devices['Pixel 5'], viewport: { width: 360, height: 740 }, launchOptions: { executablePath } },
        },
        {
            name: 'desktop',
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 1280, height: 800 },
                launchOptions: { executablePath },
            },
        },
    ],
    webServer: {
        // Fresh demo database (file-based so it persists between requests), then serve.
        command: `php artisan cache:clear && php artisan migrate:fresh --seed --force && php artisan serve --host=127.0.0.1 --port=${PORT}`,
        url: `http://127.0.0.1:${PORT}/up`,
        reuseExistingServer: !process.env.CI,
        timeout: 60_000,
        env: {
            APP_ENV: 'local',
            APP_DEBUG: 'false',
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: `${process.cwd()}/database/e2e.sqlite`,
            SESSION_DRIVER: 'file',
            QUEUE_CONNECTION: 'sync',
            MAIL_MAILER: 'log',
            KASI_DEMO_MODE: 'true',
            KASI_OTP_PER_IP_HOUR: '1000',
            KASI_THROTTLE_MULTIPLIER: '50',
            KASI_OTP_PER_PHONE_15_MIN: '50',
            KASI_OTP_PER_DEVICE_HOUR: '1000',
            KASI_OTP_PER_PHONE_DAY: '200',
            CACHE_STORE: 'file',
        },
    },
});
