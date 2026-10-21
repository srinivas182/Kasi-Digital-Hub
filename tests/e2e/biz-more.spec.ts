import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

import { signIn } from './support/auth';

async function expectAccessible(page: Page) {
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    const serious = results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''));
    expect(serious.map((violation) => `${violation.id}: ${violation.help} (${violation.nodes.length})`)).toEqual([]);
}

test('an owner keeps stock, meets a deadline and shares a summary', async ({ page, browser }) => {
    test.setTimeout(120_000);
    // Unique per attempt (retry-safe); phone and desktop share Nomsa's demo business, so each uses its own records.
    const name = `Hair gel ${test.info().project.name} ${test.info().retry}-${Date.now() % 100000}`;
    const mine = test.info().project.name === 'phone' ? 0 : 1;
    await signIn(page, '072 000 0003');

    await page.goto('/biz/stock');
    await expect(page.getByRole('heading', { level: 1, name: 'Stock' })).toBeVisible({ timeout: 20_000 });
    await page.getByRole('textbox', { name: 'Name' }).fill(name);
    await page.getByRole('spinbutton', { name: 'How many do you have now?' }).fill('10');
    await page.getByRole('spinbutton', { name: 'Reorder when down to' }).fill('3');
    await page.getByRole('button', { name: 'Add', exact: true }).click();
    await expect(page.getByText('Stock item added.')).toBeVisible();
    const card = page.getByRole('listitem').filter({ hasText: name });
    await card.getByRole('button', { name: 'Record a change' }).click();
    await card.getByRole('combobox', { name: 'What happened?' }).selectOption('counted');
    await card.getByRole('spinbutton', { name: 'How many did you count?' }).fill('2');
    await card.getByRole('button', { name: 'Save' }).click();
    await expect(card.getByText('Low')).toBeVisible();
    await expectAccessible(page);

    await page.goto('/biz/compliance');
    await expect(page.getByText(/not tax or legal advice/)).toBeVisible();
    await page.getByRole('checkbox', { name: /I employ people/ }).check();
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page.getByText('SARS EMP201 (PAYE and UIF)').first()).toBeVisible();
    await page.getByRole('button', { name: 'Mark as done' }).nth(mine).click(); // a different deadline per project
    await expect(page.getByText('Marked as done.')).toBeVisible();
    await expectAccessible(page);

    await page.goto('/biz/share');
    await page.getByRole('textbox', { name: /Who is it for/ }).fill('Ubuntu Community Bank');
    await page.getByRole('button', { name: 'Make a 7-day link' }).click();
    const link = (await page.getByText(/\/biz\/shared\/[A-Za-z0-9]{40}/).innerText()).trim();
    await expectAccessible(page);

    const partner = await (await browser.newContext()).newPage();
    await partner.goto(link);
    await expect(partner.getByRole('heading', { level: 1, name: /Business summary/ })).toBeVisible();
    await expect(partner.getByText('Months with a profit')).toBeVisible();
    await expectAccessible(partner);
});
