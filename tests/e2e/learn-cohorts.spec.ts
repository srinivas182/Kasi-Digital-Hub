import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

import { signIn } from './support/auth';

async function expectAccessible(page: Page) {
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    const serious = results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''));
    expect(serious.map((violation) => `${violation.id}: ${violation.help} (${violation.nodes.length})`)).toEqual([]);
}

test('a young person joins a hub cohort with its code', async ({ page }) => {
    await signIn(page, '072 000 0002');
    await page.goto('/learn/join/KASI24');
    await expect(page.getByText('Tsutsumani, October (demo)')).toBeVisible();
    await expectAccessible(page);
    await page.getByRole('button', { name: 'Join', exact: true }).click();
    await expect(page.getByText(/You joined Tsutsumani, October|My learning/).first()).toBeVisible();
});

test('a learner sees their certificates page', async ({ page }) => {
    await signIn(page, '072 000 0001');
    await page.goto('/learn/certificates');
    await expect(page.getByRole('heading', { level: 1, name: 'My certificates' })).toBeVisible();
    await expectAccessible(page);
});

test('a provider runs the cohort and sees moderation', async ({ page, isMobile }) => {
    test.skip(isMobile, 'Cohort management and moderation are desktop tools');
    await signIn(page, '072 000 0012');
    await page.goto('/learn/cohorts');
    await page.getByRole('link', { name: /Tsutsumani, October \(demo\)/ }).click();
    await expect(page.getByText('KASI24').first()).toBeVisible();
    await expect(page.getByText('Role-play: serving customers')).toBeVisible();
    await expectAccessible(page);
    await page.goto('/learn/moderation');
    await expect(page.getByRole('heading', { level: 1, name: 'Moderation' })).toBeVisible();
    await expectAccessible(page);
    await page.goto('/learn/provider');
    await expect(page.getByRole('heading', { name: 'Who signs your certificates' })).toBeVisible();
    await expectAccessible(page);
});
