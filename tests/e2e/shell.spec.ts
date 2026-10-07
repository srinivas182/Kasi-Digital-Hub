import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

const LAYOUTS = ['public', 'auth', 'app', 'portal', 'console', 'kiosk'];

async function expectNoSidewaysScroll(page: Page) {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    expect(overflow).toBeLessThanOrEqual(1);
}

async function expectAccessible(page: Page) {
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    const serious = results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''));
    expect(serious.map((violation) => `${violation.id}: ${violation.help} (${violation.nodes.length})`)).toEqual([]);
}

test('home page renders with the demo banner and no sideways scrolling', async ({ page }) => {
    await page.goto('/');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await expect(page.getByText(/Demo environment/)).toBeVisible();
    await expectNoSidewaysScroll(page);
});

test('home page and UI kit pass the accessibility scan', async ({ page }) => {
    await page.goto('/');
    await expectAccessible(page);
    await page.goto('/ui-kit');
    await expect(page.getByRole('heading', { name: 'KasiHub UI kit' })).toBeVisible();
    await expectAccessible(page);
    await expectNoSidewaysScroll(page);
});

for (const layout of LAYOUTS) {
    test(`${layout} layout renders, fits the screen and is accessible`, async ({ page }) => {
        await page.goto(`/ui-kit/layouts/${layout}`);
        await expect(page.locator('#main')).toBeVisible();
        await expectNoSidewaysScroll(page);
        await expectAccessible(page);
    });
}

test('the skip link is the first thing a keyboard user reaches', async ({ page }) => {
    await page.goto('/');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await page.keyboard.press('Tab');
    const skip = page.getByRole('link', { name: 'Skip to main content' });
    await expect(skip).toBeFocused();
    await expect(skip).toHaveAttribute('href', '#main');
});

test('dark mode can be switched on and is remembered', async ({ page }) => {
    await page.emulateMedia({ colorScheme: 'light' });
    await page.goto('/');
    const toggle = page.getByRole('button', { name: /^Theme:/ });
    await toggle.click(); // system -> light
    await toggle.click(); // light -> dark
    await expect(page.locator('html')).toHaveClass(/dark/);
    await page.reload();
    await expect(page.locator('html')).toHaveClass(/dark/);
});

test('the interface language can be changed', async ({ page, isMobile }) => {
    test.skip(isMobile, 'Language switcher sits in the footer on phones; covered on desktop');
    await page.goto('/');
    await page.getByRole('combobox', { name: 'Language' }).first().selectOption('zu');
    await expect(page.locator('html')).toHaveAttribute('lang', 'zu');
    // The switcher's own label is now in isiZulu (draft strings).
    await expect(page.getByRole('combobox', { name: 'Ulimi' }).first()).toBeVisible();
});

test('unknown pages show the branded 404 page', async ({ page }) => {
    const response = await page.goto('/this-page-does-not-exist');
    expect(response?.status()).toBe(404);
    await expect(page.getByRole('heading', { name: "We can't find that page" })).toBeVisible();
    await expectAccessible(page);
});

test('the offline page and web app manifest are available', async ({ page, request }) => {
    await page.goto('/offline');
    await expect(page.getByRole('heading', { name: "You're offline" })).toBeVisible();
    const manifest = await request.get('/manifest.webmanifest');
    expect(manifest.ok()).toBeTruthy();
});

test('dark mode meets contrast requirements', async ({ page, isMobile }) => {
    test.skip(isMobile, 'Theme colours are the same on every screen size; checked once on desktop');
    await page.emulateMedia({ colorScheme: 'dark' });
    for (const path of ['/', '/ui-kit', '/ui-kit/layouts/console']) {
        await page.goto(path);
        await expect(page.locator('html')).toHaveClass(/dark/);
        await expect(page.locator('#main')).toBeVisible();
        await expectAccessible(page);
    }
});
