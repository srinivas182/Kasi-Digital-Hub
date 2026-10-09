import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

import { signIn } from './support/auth';

async function expectAccessible(page: Page) {
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    const serious = results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''));
    expect(serious.map((violation) => `${violation.id}: ${violation.help} (${violation.nodes.length})`)).toEqual([]);
}

test('a young person sees jobs that suit them, with reasons', async ({ page }) => {
    await signIn(page, '072 000 0001');
    await page.goto('/work/matches');
    await expect(page.getByRole('heading', { level: 1, name: 'Jobs for you' })).toBeVisible();
    await expect(page.getByText(/% match/).first()).toBeVisible();
    await expect(page.getByText('Why it suits you').first()).toBeVisible();
    await expectAccessible(page);

    for (const tab of ['Invitations', 'Who viewed me', 'Hidden from']) {
        await page.getByRole('link', { name: tab }).click();
        await expect(page.getByRole('link', { name: tab })).toHaveAttribute('aria-current', 'page');
    }
    await expectAccessible(page);
});

test('an employer sees anonymised suggested candidates and invites one', async ({ page }) => {
    await signIn(page, '072 000 0010');
    await page.goto('/work/employer');
    await page.getByRole('link', { name: 'Suggested candidates' }).first().click();
    await expect(page.getByRole('heading', { level: 1, name: 'Suggested candidates' })).toBeVisible();
    await expect(
        page.getByText('Names and phone numbers are shown only after a person accepts your invitation.'),
    ).toBeVisible();
    await expectAccessible(page);

    const invite = page.getByRole('button', { name: 'Invite to apply' }).first();
    if (await invite.count()) {
        await invite.click();
        await expect(page.getByText(/Invitation sent|already invited/)).toBeVisible();
    }
});
