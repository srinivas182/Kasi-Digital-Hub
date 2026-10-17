import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

import { signIn } from './support/auth';

async function expectAccessible(page: Page) {
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    const serious = results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''));
    expect(serious.map((violation) => `${violation.id}: ${violation.help} (${violation.nodes.length})`)).toEqual([]);
}

test('anyone reads a help article and says whether it helped', async ({ page }) => {
    await page.goto('/help');
    await page.getByRole('link', { name: 'I forgot my PIN' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'I forgot my PIN' })).toBeVisible();
    await expectAccessible(page);
    await page.getByRole('button', { name: 'Yes' }).click();
    await expect(page.getByText('Thank you for telling us.')).toBeVisible();
});

test('a young person asks for help from the app', async ({ page }) => {
    await signIn(page, '072 000 0002');
    await page.goto('/work/profile');
    await expect(page.getByRole('link', { name: 'Help with this page' })).toBeVisible();
    await page.goto('/help/requests');
    await page.getByLabel('Short title').fill('Cannot upload my ID');
    await page.getByLabel('What happened?').fill('The upload keeps failing on my phone.');
    await page.getByRole('button', { name: 'Send' }).click();
    await expect(page.getByText(/reply within 2 working days/)).toBeVisible();
    await expectAccessible(page);
});

test('staff work the support queue, review inbox and system status; the demo stories page works', async ({
    page,
    isMobile,
}) => {
    test.skip(isMobile, 'Staff tools are desktop tools');
    await signIn(page, '072 000 0040');
    for (const [url, heading] of [
        ['/admin/support', 'Support queue'],
        ['/admin/inbox', 'Review inbox'],
        ['/admin/system', 'System status'],
        ['/admin/help', 'Help articles'],
        ['/demo', 'Demo stories'],
    ] as const) {
        await page.goto(url);
        await expect(page.getByRole('heading', { level: 1, name: heading })).toBeVisible();
        await expectAccessible(page);
    }
});
