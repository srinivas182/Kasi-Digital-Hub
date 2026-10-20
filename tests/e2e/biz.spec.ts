import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

import { signIn } from './support/auth';

async function expectAccessible(page: Page) {
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    const serious = results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''));
    expect(serious.map((violation) => `${violation.id}: ${violation.help} (${violation.nodes.length})`)).toEqual([]);
}

test('a business owner records money in and out and sees the month', async ({ page }) => {
    await signIn(page, '072 000 0003');
    await page.goto('/biz');
    await expect(page.getByRole('heading', { level: 1, name: 'Nomsa Hair Studio' })).toBeVisible({ timeout: 20_000 });
    await expect(page.getByText('Profit', { exact: true })).toBeVisible();
    await expectAccessible(page);

    await page.getByRole('button', { name: 'Money in' }).click();
    await page.getByRole('spinbutton', { name: 'Amount (R)' }).fill('120');
    await page.getByRole('group', { name: 'What for?' }).getByText('Services', { exact: true }).click();
    await page.getByRole('group', { name: 'How was it paid?' }).getByText('Mobile money', { exact: true }).click();
    await page.getByRole('textbox', { name: 'Note (optional)' }).fill(`Wash and blow-dry ${test.info().project.name}`);
    await expectAccessible(page);
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByText('Saved.')).toBeVisible();

    await page.getByRole('button', { name: 'Money out' }).click();
    await page.getByRole('spinbutton', { name: 'Amount (R)' }).fill('35');
    await page.getByRole('group', { name: 'What for?' }).getByText('Transport', { exact: true }).click();
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByText('Saved.')).toBeVisible();
    await expect(page.getByText(/Wash and blow-dry/).first()).toBeVisible();

    await page.getByRole('link', { name: 'Who owes what' }).first().click();
    await expect(page.getByRole('heading', { level: 1, name: 'Who owes what' })).toBeVisible();
    await expect(page.getByText(/Gogo Dlamini/).first()).toBeVisible();
    await expect(page.getByRole('link', { name: 'Remind on WhatsApp' }).first()).toHaveAttribute(
        'href',
        /wa\.me\/27725550101/,
    );
    await expectAccessible(page);
});
