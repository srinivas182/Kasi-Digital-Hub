import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

import { signIn } from './support/auth';

async function expectAccessible(page: Page) {
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    const serious = results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''));
    expect(serious.map((violation) => `${violation.id}: ${violation.help} (${violation.nodes.length})`)).toEqual([]);
}

test('a funder sees programme impact with small numbers protected', async ({ page }) => {
    await signIn(page, '072 000 0030');
    await page.goto('/funder');
    await expect(page.getByRole('heading', { level: 1, name: 'Programmes and impact' })).toBeVisible();
    await page.getByRole('link', { name: /Limpopo Youth Pathways 2026/ }).click();
    await expect(page.getByText(/any figure below 10 is shown as "fewer than 10"/)).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Month by month' })).toBeVisible();
    await expectAccessible(page);
});

test('a coordinator sees the regional overview', async ({ page, isMobile }) => {
    test.skip(isMobile, 'The regional console is a desktop tool');
    await signIn(page, '072 000 0023'); // provincial coordinator, Limpopo
    await page.goto('/region');
    await expect(page.getByRole('heading', { level: 1, name: /Regional overview - Limpopo/ })).toBeVisible();
    await expectAccessible(page);
});
