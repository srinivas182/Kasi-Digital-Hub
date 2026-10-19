import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

import { signIn } from './support/auth';

async function expectAccessible(page: Page) {
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    const serious = results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''));
    expect(serious.map((violation) => `${violation.id}: ${violation.help} (${violation.nodes.length})`)).toEqual([]);
}

test('anyone browses the public job board and opens a job', async ({ page }) => {
    await page.goto('/jobs');
    await expect(page.getByRole('heading', { level: 1, name: 'Jobs' })).toBeVisible();
    await expectAccessible(page);
    await page.getByRole('combobox', { name: 'Type of work' }).selectOption('full_time');
    await expect(page.getByText(/\d+ jobs/)).toBeVisible();
    const first = page.locator('main li a').first();
    if ((await first.count()) > 0) {
        await first.click();
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    }
});

test('one sign-in works across portals (single sign-on)', async ({ page }) => {
    test.skip(!process.env.SUBDOMAINS, 'Only meaningful with portal subdomains on');
    await signIn(page, '072 000 0001');
    await page.goto('/learn/my');
    await expect(page).toHaveURL(/\/\/learn\.kasi\.test:\d+\/learn\/my/);
    await page.goto('/work/applications');
    await expect(page).toHaveURL(/\/\/jobs\.kasi\.test:\d+\/work\/applications/);
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
});
