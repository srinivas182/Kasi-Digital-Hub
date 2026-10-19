import { defineConfig, devices } from '@playwright/test';
import { closeSync, openSync } from 'node:fs';

/**
 * Browser tests (smoke level): key pages at phone and desktop sizes, keyboard use,
 * dark mode, language switching and automated accessibility scans (axe).
 * Assets must be built first (npm run build). CI: see the "e2e" job.
 */
const PORT = Number(process.env.E2E_PORT ?? 8123);
const executablePath = process.env.PW_CHROMIUM_PATH || undefined;

// SUBDOMAINS=1 (S23): portals on real subdomains of kasi.test, all resolved to this machine by the browser,
// exactly as production uses *.kasidigitalhub.co.za. CI runs the suite this way.
const SUBDOMAINS = Boolean(process.env.SUBDOMAINS);
const BASE = SUBDOMAINS ? `http://kasi.test:${PORT}` : `http://127.0.0.1:${PORT}`;
// Service workers, location and offline storage need a secure address; production uses HTTPS, so the test
// browser treats the http test addresses as secure.
const TEST_HOSTS = [
    '',
    'my.',
    'jobs.',
    'learn.',
    'start.',
    'connect.',
    'hub.',
    'partner.',
    'funder.',
    'region.',
    'admin.',
].map((s) => `http://${s}kasi.test:${PORT}`);
// CI uses Playwright's full Chromium in headless mode (not the stripped headless shell), like local runs, so the
// "treat test addresses as secure" flag applies to service workers and location.
const channel = executablePath ? undefined : 'chromium';
const browserArgs = SUBDOMAINS
    ? [
          '--host-resolver-rules=MAP kasi.test 127.0.0.1, MAP *.kasi.test 127.0.0.1',
          `--unsafely-treat-insecure-origin-as-secure=${TEST_HOSTS.join(',')}`,
      ]
    : [];

// The e2e database file must exist before migrations run.
closeSync(openSync('database/e2e.sqlite', 'a'));

export default defineConfig({
    testDir: 'tests/e2e',
    fullyParallel: true,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? [['github'], ['list']] : 'list',
    use: {
        baseURL: BASE,
        trace: 'retain-on-failure',
        channel,
        launchOptions: { executablePath, args: browserArgs },
    },
    projects: [
        {
            name: 'phone',
            use: {
                ...devices['Pixel 5'],
                viewport: { width: 360, height: 740 },
                channel,
                launchOptions: { executablePath, args: browserArgs },
            },
        },
        {
            name: 'desktop',
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 1280, height: 800 },
                channel,
                launchOptions: { executablePath, args: browserArgs },
            },
        },
    ],
    webServer: {
        // Fresh demo database (file-based so it persists between requests), then serve. The server's output is kept in
        // storage/logs/e2e-server.log, which CI uploads with Laravel's log when the browser job fails (S18).
        // --no-reload: the server must never restart itself in the middle of a run.
        // OCTANE=1 runs the suite under Laravel Octane (Swoole), as production will (S22).
        command: `php artisan cache:clear && php artisan migrate:fresh --seed --force && ${process.env.OCTANE ? `php artisan octane:start --server=swoole --workers=4 --max-requests=500 --host=127.0.0.1 --port=${PORT}` : `php artisan serve --no-reload --host=127.0.0.1 --port=${PORT}`} 2>&1 | tee -a storage/logs/e2e-server.log`,
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
            ...(SUBDOMAINS
                ? { KASI_SUBDOMAINS: 'true', KASI_DOMAIN: 'kasi.test', APP_URL: BASE, SESSION_DOMAIN: '.kasi.test' }
                : {}),
            KASI_OTP_PER_IP_HOUR: '1000',
            KASI_THROTTLE_MULTIPLIER: '50',
            KASI_OTP_PER_PHONE_15_MIN: '50',
            KASI_OTP_PER_DEVICE_HOUR: '1000',
            KASI_OTP_PER_PHONE_DAY: '200',
            CACHE_STORE: 'file',
        },
    },
});
