import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

import { signIn } from './support/auth';

async function expectAccessible(page: Page) {
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    const serious = results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''));
    expect(serious.map((violation) => `${violation.id}: ${violation.help} (${violation.nodes.length})`)).toEqual([]);
}

test('a young person sees her mentorship and cannot share contact details too early', async ({ page }) => {
    await signIn(page, '072 000 0003');
    await page.goto('/connect');
    await expect(page.getByRole('heading', { level: 1, name: 'Mentoring' })).toBeVisible();
    await expectAccessible(page);
    await page.getByRole('link', { name: /Your mentor Palesa/ }).click();
    await expect(page.getByRole('heading', { name: 'Our agreement' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Report a concern' })).toBeVisible();
    await expectAccessible(page);

    await page.getByLabel('Write a message').fill('Please WhatsApp me on 072 123 4567');
    await page.getByRole('button', { name: 'Send' }).click();
    await expect(page.getByText(/can't be shared before 2 sessions/)).toBeVisible();
});

test('a young person finds a mentor with reasons', async ({ page }) => {
    await signIn(page, '072 000 0002');
    await page.goto('/connect/find?goal=business&interests=catering');
    await expect(page.getByRole('heading', { level: 1, name: 'Find a mentor' })).toBeVisible();
    await expect(page.getByText('Knows about: catering')).toBeVisible();
    await expectAccessible(page);
});

test('the coordinator vets mentors and the safeguarding officer sees the queue', async ({ page, isMobile }) => {
    test.skip(isMobile, 'Vetting and safeguarding are desktop tools');
    await signIn(page, '072 000 0040');
    await page.goto('/connect/coordinator');
    await expect(page.getByRole('heading', { level: 1, name: 'Mentor vetting' })).toBeVisible();
    await expectAccessible(page);
    await page.goto('/connect/safeguarding');
    await expect(page.getByRole('heading', { level: 1, name: 'Safeguarding' })).toBeVisible();
    await expectAccessible(page);
});
