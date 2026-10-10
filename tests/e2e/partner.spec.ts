import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

import { signIn } from './support/auth';

async function expectAccessible(page: Page) {
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    const serious = results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''));
    expect(serious.map((violation) => `${violation.id}: ${violation.help} (${violation.nodes.length})`)).toEqual([]);
}

test('an entrepreneur finds support she qualifies for and sends a referral', async ({ page }) => {
    await signIn(page, '072 000 0003');
    await page.goto('/support');
    await expect(page.getByRole('heading', { level: 1, name: 'Support for your business' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'You qualify' })).toBeVisible();
    await expectAccessible(page);

    await page.getByRole('link', { name: 'Business basics training (demo)' }).click();
    const existing = page.getByRole('link', { name: /You already sent a referral/ });
    await expect(page.getByRole('heading', { name: 'Ask for this support' })).toBeVisible();
    if (!(await existing.count())) {
        await page.getByRole('checkbox', { name: /I agree to share/ }).check();
        await page.getByRole('button', { name: 'Send referral' }).click();
        await expect(page.getByText(/Referral sent/)).toBeVisible();
    }
    await expectAccessible(page);
});

test('a partner sees the referral pipeline and the offer form', async ({ page, isMobile }) => {
    test.skip(isMobile, 'The partner portal is a desktop tool');
    await signIn(page, '072 000 0050');
    await page.goto('/partner');
    await expect(page.getByRole('heading', { name: 'Referrals' })).toBeVisible();
    await expectAccessible(page);
    await page.goto('/partner/offers/create');
    await expect(page.getByRole('heading', { name: 'Who qualifies' })).toBeVisible();
    await expectAccessible(page);
});
