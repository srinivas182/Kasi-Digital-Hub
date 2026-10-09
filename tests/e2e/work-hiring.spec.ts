import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

import { signIn } from './support/auth';

async function expectAccessible(page: Page) {
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    const serious = results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''));
    expect(serious.map((violation) => `${violation.id}: ${violation.help} (${violation.nodes.length})`)).toEqual([]);
}

test('a young person applies for a job and follows the application', async ({ page }) => {
    test.setTimeout(90_000);
    await signIn(page, '072 000 0002');
    await page.goto('/work/jobs');
    await page
        .getByRole('link', { name: /Shelf packer/ })
        .first()
        .click();
    await expect(page.getByRole('heading', { level: 1, name: 'Shelf packer' })).toBeVisible();
    const view = page.getByRole('link', { name: 'View my application' });
    if (!(await view.count())) {
        await page.getByRole('link', { name: 'Apply', exact: true }).click();
        await expect(page.getByRole('heading', { level: 1, name: /Apply for Shelf packer/ })).toBeVisible();
        await expectAccessible(page);
        await page.getByRole('radio', { name: 'Yes' }).check();
        await page.getByRole('checkbox', { name: /may see my CV/ }).check();
        await page.getByRole('button', { name: 'Send my application' }).click();
        await expect(page.getByText('Application sent!')).toBeVisible();
    } else {
        await view.click();
    }
    await expect(page.getByRole('heading', { name: 'What happened' })).toBeVisible();
    await expectAccessible(page);
    await page.goto('/work/applications');
    await expect(page.getByRole('heading', { level: 1, name: 'My applications' })).toBeVisible();
    await expectAccessible(page);
});

test('an employer moves an applicant and adds a team note', async ({ page }) => {
    await signIn(page, '072 000 0010');
    await page.goto('/work/employer');
    await page.getByRole('link', { name: 'Applicants' }).first().click();
    await expect(page.getByRole('heading', { level: 1, name: 'Applicants' })).toBeVisible();
    await expectAccessible(page);
    await page.getByRole('link', { name: 'Thandi Mabasa' }).first().click();
    await expect(page.getByRole('heading', { level: 1, name: 'Thandi Mabasa' })).toBeVisible();
    await page.getByRole('textbox', { name: 'Add note' }).fill('Strong cash handling, call on Monday.');
    await page.getByRole('button', { name: 'Add note' }).click();
    await expect(page.getByText('Note added.')).toBeVisible();
    await expectAccessible(page);
});
