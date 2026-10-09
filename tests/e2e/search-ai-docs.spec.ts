import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

import { signIn } from './support/auth';

async function expectAccessible(page: Page) {
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    const serious = results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''));
    expect(serious.map((violation) => `${violation.id}: ${violation.help} (${violation.nodes.length})`)).toEqual([]);
}

test('a signed-in person searches across hubs, events and help', async ({ page }) => {
    await signIn(page, '072 000 0001');
    await page.getByRole('link', { name: 'Search' }).first().click();
    await page.getByRole('searchbox', { name: 'Search hubs, events and help' }).fill('Gyani');
    await page.getByRole('button', { name: 'Search' }).click();
    await expect(page.getByRole('heading', { name: 'Hubs' })).toBeVisible();
    await expect(page.getByRole('region', { name: 'Hubs' }).getByRole('link', { name: /Giyani Central/ })).toBeVisible();
    await expectAccessible(page);
});

test('signed-in pages fit a small phone screen without sideways scrolling', async ({ page }) => {
    await signIn(page, '072 000 0002');
    for (const path of ['/home', '/account?tab=documents', '/events', '/search?q=hub']) {
        await page.goto(path);
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        expect(overflow, path).toBeLessThanOrEqual(0);
    }
});

test('anyone can check a document code', async ({ page }) => {
    await page.goto('/verify');
    await expect(page.getByRole('heading', { name: 'Check a document' })).toBeVisible();
    await page.getByRole('textbox', { name: 'Document code' }).fill('ABCDEFGH23');
    await page.getByRole('button', { name: 'Check' }).click();
    await expect(page.getByText("We can't find a document with this code")).toBeVisible();
    await expectAccessible(page);
});

test('hub staff get help writing an event description', async ({ page }) => {
    await signIn(page, '072 000 0020');
    await page.goto('/hub-ops/events/create');
    await page.getByRole('textbox', { name: 'Title' }).fill('CV workshop');
    await page.getByRole('textbox', { name: 'Your notes' }).fill('cv workshop saturday 9am, bring ID and certificates');
    await page.getByRole('button', { name: 'Write the description' }).click();
    await page.getByRole('button', { name: 'Use this text' }).click();
    await expect(page.getByRole('textbox', { name: 'Description' })).toHaveValue(/\(Demo AI\)/);
    await expectAccessible(page);
});

test.describe('national admin', () => {
    test.beforeEach(async ({ page }) => {
        await signIn(page, '072 000 0040');
    });

    for (const [path, heading] of [
        ['/admin/ai', 'AI controls'],
        ['/admin/moderation', 'Content review'],
    ] as const) {
        test(`${heading} page is accessible`, async ({ page }) => {
            await page.goto(path);
            await expect(page.getByRole('heading', { level: 1, name: heading })).toBeVisible();
            await expectAccessible(page);
        });
    }
});
