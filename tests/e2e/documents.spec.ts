import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

import { signIn } from './support/auth';

async function expectAccessible(page: Page) {
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    const serious = results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''));
    expect(serious.map((violation) => `${violation.id}: ${violation.help} (${violation.nodes.length})`)).toEqual([]);
}

test('a person uploads a document to their vault', async ({ page }) => {
    await signIn(page, '072 000 0002');
    await page.goto('/account?tab=documents');
    await expect(page.getByRole('heading', { name: 'Upload a document' })).toBeVisible();
    await expect(page.getByText('Not accepted')).toBeVisible(); // demo: rejected proof of address
    await expectAccessible(page);

    await page.getByLabel(/Type of document/).selectOption('qualification');
    await page.getByLabel(/^File/).setInputFiles({
        name: 'certificate.pdf',
        mimeType: 'application/pdf',
        buffer: Buffer.from('%PDF-1.4\nDemo certificate'),
    });
    await page.getByRole('button', { name: 'Upload', exact: true }).click();

    await expect(page.getByText(/Document uploaded/)).toBeVisible();
    await expect(page.getByRole('list', { name: 'My documents' }).getByText('Qualification').first()).toBeVisible();
});

test('notification choices and the updates feed are reachable and accessible', async ({ page }) => {
    await signIn(page, '072 000 0001');

    await page
        .getByRole('link', { name: /Notifications/ })
        .first()
        .click();
    await expect(page.getByRole('heading', { name: 'Updates' })).toBeVisible();
    await expect(page.getByText('CV workshop at Tsutsumani hub on Saturday')).toBeVisible();
    await expectAccessible(page);

    await page.goto('/account?tab=notifications');
    await expect(page.getByRole('checkbox', { name: 'Security - SMS' })).toBeDisabled();
    await page.getByRole('checkbox', { name: 'Jobs - SMS' }).check();
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.getByText('Your notification choices are saved.')).toBeVisible();
    await expectAccessible(page);
});
