import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

import { signIn } from './support/auth';

async function expectAccessible(page: Page) {
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    const serious = results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''));
    expect(serious.map((violation) => `${violation.id}: ${violation.help} (${violation.nodes.length})`)).toEqual([]);
}

test('an employer at the advert limit buys an extra advert (demo payment)', async ({ page }) => {
    await signIn(page, '072 000 0010');
    await page.goto('/work/employer');
    await page.getByRole('link', { name: 'Add an extra advert' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'Checkout' })).toBeVisible();
    await expectAccessible(page);
    await page.getByRole('button', { name: 'Pay securely' }).click();
    await expect(page.getByRole('heading', { name: 'Demo payment' })).toBeVisible();
    await page.getByRole('button', { name: 'Pay', exact: true }).click();
    await expect(page.getByText('Paid - thank you!')).toBeVisible();
    await expectAccessible(page);
});

test('the hub owner sells at the point of sale', async ({ page }) => {
    await signIn(page, '072 000 0024');
    await page.goto('/commercial/hub');
    await expect(page.getByRole('heading', { name: 'Point of sale' })).toBeVisible();
    await expectAccessible(page);
    await page.getByRole('button', { name: 'Sell' }).click();
    await expect(page.getByText(/Sold - receipt/)).toBeVisible();
});

test('the national team sees the commercial console', async ({ page, isMobile }) => {
    test.skip(isMobile, 'The commercial console is a desktop tool');
    await signIn(page, '072 000 0040');
    await page.goto('/commercial');
    await expect(page.getByRole('heading', { level: 1, name: 'Commercial and finance' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Catalogue and split rules' })).toBeVisible();
    await expectAccessible(page);
});
