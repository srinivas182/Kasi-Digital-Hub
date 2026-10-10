import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

import { signIn } from './support/auth';

async function expectAccessible(page: Page) {
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    const serious = results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''));
    expect(serious.map((violation) => `${violation.id}: ${violation.help} (${violation.nodes.length})`)).toEqual([]);
}

test('an entrepreneur follows the formalisation journey', async ({ page }) => {
    await signIn(page, '072 000 0003');
    await page.goto('/start');
    await expect(page.getByRole('heading', { level: 1, name: 'My business' })).toBeVisible();
    await expectAccessible(page);
    await page
        .getByRole('link', { name: /Nomsa Hair Studio/ })
        .first()
        .click();
    await expect(page.getByRole('heading', { name: 'Your steps to a formal business' })).toBeVisible();
    await page.getByRole('button', { name: 'Get a B-BBEE sworn affidavit' }).click();
    await expect(page.getByRole('link', { name: 'Download the pre-filled affidavit' })).toBeVisible();
    await expectAccessible(page);
    await page.goto(`${page.url().split('?')[0]}/guide`);
    await expect(page.getByRole('heading', { level: 1, name: 'How should your business be set up?' })).toBeVisible();
    await expectAccessible(page);
});

test('an entrepreneur works out a price in the plan builder', async ({ page }) => {
    await signIn(page, '072 000 0003');
    await page.goto('/start');
    await page
        .getByRole('link', { name: /Nomsa Hair Studio/ })
        .first()
        .click();
    await page.getByRole('link', { name: 'My business plan' }).click();
    await page.getByRole('spinbutton', { name: 'Cost per item (R)' }).fill('100');
    await page.getByRole('spinbutton', { name: 'Markup (%)' }).fill('50');
    await page.getByRole('spinbutton', { name: 'Or your price (R)' }).fill('');
    await page.getByRole('spinbutton', { name: 'Fixed costs a month (R)' }).fill('1000');
    await expect(page.getByText('R150.00')).toBeVisible();
    await expect(page.getByText('20', { exact: true })).toBeVisible();
    await expectAccessible(page);
});

test('the KasiHub team edits the step content', async ({ page, isMobile }) => {
    test.skip(isMobile, 'Content editing is a desktop tool');
    await signIn(page, '072 000 0040');
    await page.goto('/start/admin/steps');
    await expect(page.getByRole('heading', { level: 1, name: 'Business steps (content)' })).toBeVisible();
    await expectAccessible(page);
});
