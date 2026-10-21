import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

import { signIn } from './support/auth';

async function expectAccessible(page: Page) {
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    const serious = results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''));
    expect(serious.map((violation) => `${violation.id}: ${violation.help} (${violation.nodes.length})`)).toEqual([]);
}

test('an owner updates her website, sees it live, and plans her month', async ({ page, browser }) => {
    test.setTimeout(120_000);
    const mine = test.info().project.name === 'phone' ? 0 : 1; // phone and desktop share Nomsa's demo business
    await signIn(page, '072 000 0003');
    await page.goto('/brand/site');
    await expect(page.getByRole('heading', { level: 1, name: 'My website' })).toBeVisible({ timeout: 20_000 });
    await expect(page.getByText('Live')).toBeVisible();
    await page
        .getByRole('textbox', { name: 'About your business' })
        .fill('Braids, cornrows and treatments in Soweto. Book on WhatsApp.');
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page.getByText('Saved.')).toBeVisible();
    await expectAccessible(page);

    const visitor = await (await browser.newContext()).newPage(); // a member of the public, not signed in
    await visitor.goto('/b/nomsahair');
    await expect(visitor.getByRole('heading', { level: 1, name: /Nomsa Hair Studio/ })).toBeVisible();
    await expect(visitor.getByRole('link', { name: 'Order on KasiMarket' })).toBeVisible();
    await expectAccessible(visitor);

    await page.goto('/brand/calendar');
    await page.getByRole('button', { name: 'Suggest posts for this month' }).click();
    await expect(page.getByText(/Added \d+ idea|No new ideas/)).toBeVisible();
    const posted = page.getByRole('button', { name: 'Mark as posted' });
    if ((await posted.count()) > mine) {
        await posted.nth(mine).click();
        await expect(page.getByText('Updated.')).toBeVisible();
    }
    await expectAccessible(page);
});
