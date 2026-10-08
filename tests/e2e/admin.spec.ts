import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

import { signIn } from './support/auth';

async function expectAccessible(page: Page) {
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    const serious = results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''));
    expect(serious.map((violation) => `${violation.id}: ${violation.help} (${violation.nodes.length})`)).toEqual([]);
}

test('a super admin works through the console', async ({ page }) => {
    await signIn(page, '072 000 0040');

    await page.goto('/admin');
    await expect(page.getByRole('heading', { name: 'National overview' })).toBeVisible();
    await expectAccessible(page);

    await page.goto('/admin/verification');
    await expect(page.getByRole('heading', { name: 'Document verification' })).toBeVisible();
    await expectAccessible(page);
    await page.getByRole('button', { name: 'Verify' }).click();
    await expect(page.getByText(/Document verified/)).toBeVisible();

    await page.goto('/admin/people?q=Thandi');
    await page.getByRole('link', { name: 'Thandi Mabasa' }).first().click();
    await expect(page.getByRole('heading', { name: 'Thandi Mabasa' })).toBeVisible();
    await expect(page.getByText(/Job seeker · Own account/)).toBeVisible();
    await expectAccessible(page);

    for (const [path, heading] of [
        ['/admin/hubs', 'Hubs'],
        ['/admin/organisations', 'Organisations'],
        ['/admin/enquiries', 'Enquiries'],
        ['/admin/audit', 'Audit log'],
    ] as const) {
        await page.goto(path);
        await expect(page.getByRole('heading', { level: 1, name: heading })).toBeVisible();
        await expectAccessible(page);
    }
});

test('a support agent sees a smaller console menu', async ({ page, isMobile }) => {
    test.skip(isMobile, 'The console sidebar is a desktop feature');
    await signIn(page, '072 000 0043');
    await page.goto('/admin');
    const menu = page.getByRole('navigation', { name: 'National admin console' });
    await expect(menu.getByRole('link', { name: 'Verification' })).toBeVisible();
    await expect(menu.getByRole('link', { name: 'Audit log' })).toHaveCount(0);
});
