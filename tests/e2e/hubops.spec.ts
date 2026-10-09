import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

import { signIn } from './support/auth';

async function expectAccessible(page: Page) {
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    const serious = results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''));
    expect(serious.map((violation) => `${violation.id}: ${violation.help} (${violation.nodes.length})`)).toEqual([]);
}

test('the door screen shows a check-in code and is accessible', async ({ page }) => {
    await page.goto('/kiosk/tsutsumani/demo-door');
    await expect(page.getByRole('heading', { name: 'Welcome! Scan to check in' })).toBeVisible();
    await expect(page.getByRole('img', { name: 'Welcome! Scan to check in' })).toBeVisible();
    await expectAccessible(page);
});

test('a visitor checks in by scanning the door code', async ({ page }) => {
    await signIn(page, '072 000 0002');
    const code = await page.request.get('/kiosk/tsutsumani/demo-door/code');
    const { url } = (await code.json()) as { url: string };

    await page.goto(url);
    await expect(page.getByRole('heading', { name: 'Check in at Tsutsumani Digital Hub' })).toBeVisible();
    await expectAccessible(page);
    await page.getByRole('radio', { name: 'Learning' }).check();
    await page.getByRole('button', { name: 'Check in' }).click();
    await expect(page.getByText('Welcome to Tsutsumani Digital Hub! Your visit is recorded.')).toBeVisible();
});

test.describe('hub facilitator', () => {
    test.beforeEach(async ({ page }) => {
        await signIn(page, '072 000 0020');
    });

    test('dashboard and check-in desk are accessible', async ({ page }) => {
        await page.goto('/hub-ops');
        await expect(page.getByRole('heading', { level: 1, name: 'Hub dashboard' })).toBeVisible();
        await expectAccessible(page);

        await page.goto('/hub-ops/check-in?q=Thandi');
        await expect(page.getByRole('heading', { level: 1, name: 'Check-in desk' })).toBeVisible();
        await expect(page.getByText('Thandi Mabasa').first()).toBeVisible();
        await expectAccessible(page);
    });

    test('events list and event page are accessible', async ({ page }) => {
        await page.goto('/hub-ops/events');
        await expect(page.getByRole('heading', { level: 1, name: 'Events' })).toBeVisible();
        await expectAccessible(page);
        await page.getByRole('link', { name: /Retail and hospitality job day/ }).click();
        await expect(page.getByRole('heading', { level: 1, name: 'Retail and hospitality job day' })).toBeVisible();
        await expect(page.getByRole('img', { name: 'Attendance QR code' })).toBeVisible();
        await expectAccessible(page);
    });

    test('registration form is accessible', async ({ page }) => {
        await page.goto('/hub-ops/register');
        await expect(page.getByRole('heading', { level: 1, name: 'Register someone at the hub' })).toBeVisible();
        await expectAccessible(page);
    });
});

test('a young person books an event and sees it on their hub home', async ({ page }) => {
    await signIn(page, '072 000 0001');
    await page.goto('/events');
    await expect(page.getByRole('heading', { name: 'Events at the hubs' })).toBeVisible();
    await expectAccessible(page);

    await page
        .getByRole('link', { name: /CV that gets interviews/ })
        .first()
        .click();
    await expectAccessible(page);
    const book = page.getByRole('button', { name: /Book my place|Join the waiting list/ });
    if (await book.isVisible()) await book.click();
    await expect(page.getByRole('button', { name: 'Cancel my place' })).toBeVisible();

    await page.goto('/home');
    await expect(page.getByRole('heading', { name: 'Your upcoming events' })).toBeVisible();
});
