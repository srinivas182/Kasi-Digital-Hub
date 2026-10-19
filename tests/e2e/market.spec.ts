import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

import { signIn } from './support/auth';

async function expectAccessible(page: Page) {
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    const serious = results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''));
    expect(serious.map((violation) => `${violation.id}: ${violation.help} (${violation.nodes.length})`)).toEqual([]);
}

test('a visitor browses KasiMarket, searches and opens a product', async ({ page }) => {
    await page.goto('/market');
    await expect(page.getByRole('heading', { level: 1, name: 'KasiMarket' })).toBeVisible();
    await expectAccessible(page);
    await page.getByRole('textbox', { name: 'What are you looking for?' }).fill('braids');
    await page.getByRole('button', { name: 'Search', exact: true }).click();
    await expect(page.getByText(/\d+ found/)).toBeVisible();
    await expectAccessible(page);
    await page
        .getByRole('link', { name: /Box braids/ })
        .first()
        .click();
    await expect(page.getByRole('heading', { level: 1, name: /Box braids/ })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Order on WhatsApp' })).toBeVisible();
    await expectAccessible(page);
    await page.getByRole('link', { name: '← Nomsa Hair Studio' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'Nomsa Hair Studio' })).toBeVisible();
    await expectAccessible(page);
});

test('the trader adds a product from a phone', async ({ page }) => {
    await signIn(page, '072 000 0003');
    await page.goto('/market/shop');
    await expect(page.getByRole('heading', { level: 1, name: 'Nomsa Hair Studio' })).toBeVisible();
    await expectAccessible(page);
    await page.getByRole('link', { name: 'Add a product' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'New product' })).toBeVisible();
    await expectAccessible(page);
    await page.getByRole('textbox', { name: 'What is it?' }).fill(`Bantu knots ${test.info().project.name}`);
    await page.getByRole('spinbutton', { name: 'Price (R)' }).first().fill('180');
    await page.getByRole('combobox', { name: 'Stock' }).selectOption('service');
    await page.getByRole('button', { name: 'Publish' }).click();
    await expect(page.getByText(/Your product is live|Our team is checking it/)).toBeVisible();
});
