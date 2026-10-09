import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

import { signIn } from './support/auth';

async function expectAccessible(page: Page) {
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    const serious = results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''));
    expect(serious.map((violation) => `${violation.id}: ${violation.help} (${violation.nodes.length})`)).toEqual([]);
}

test('a verified employer writes a job advert with help and publishes it', async ({ page }) => {
    test.setTimeout(90_000);
    await signIn(page, '072 000 0010');
    await page.goto('/work/employer');
    await expect(page.getByText('Verified employer')).toBeVisible();
    await expectAccessible(page);

    await page.goto('/work/employer/listings/create');
    await page
        .getByRole('textbox', { name: 'Your notes' })
        .fill('need a weekend baker helper in Giyani, R35 an hour, no experience needed');
    await page.getByRole('button', { name: 'Write my advert' }).click();
    await expect(page.getByRole('textbox', { name: 'About the job' })).toHaveValue(/\(Demo AI\)/);
    await page.getByRole('textbox', { name: 'Job title' }).fill('Baker helper');
    await page.getByRole('spinbutton', { name: 'Pay (rand)' }).fill('35');
    await expectAccessible(page);
    await page.getByRole('button', { name: 'Publish' }).click();
    await expect(
        page.getByText(/Your job advert is live|You can have 3 active job adverts|being checked/),
    ).toBeVisible();
});

test('a small business registers as a community employer', async ({ page }) => {
    await signIn(page, '072 000 0003');
    await page.goto('/work/employer/register');
    await expectAccessible(page);
    await page.getByRole('switch', { name: /not registered with CIPC/ }).click();
    await page.getByRole('textbox', { name: 'Business name' }).fill('Nomsa Hair Studio (test)');
    await page.getByRole('checkbox', { name: /I confirm/ }).check();
    await page.getByRole('button', { name: 'Register my business' }).click();
    await expect(page.getByText(/Your hub will contact you/)).toBeVisible();
    await expect(page.getByText('Community employer').first()).toBeVisible();
});

test('a young person finds and saves a job', async ({ page }) => {
    await signIn(page, '072 000 0001');
    await page.goto('/work/jobs');
    await expect(page.getByRole('heading', { level: 1, name: 'Find jobs' })).toBeVisible();
    await expectAccessible(page);
    await page
        .getByRole('link', { name: /Shelf packer/ })
        .first()
        .click();
    await expect(page.getByText('Verified employer')).toBeVisible();
    await expectAccessible(page);
    const save = page.getByRole('button', { name: 'Save', exact: true });
    if (await save.count()) await save.click();
    await expect(page.getByRole('button', { name: 'Saved', exact: true })).toBeVisible();
});

test('a public job page is readable without signing in', async ({ page }) => {
    await signIn(page, '072 000 0001');
    await page.goto('/work/jobs');
    const href = await page
        .getByRole('link', { name: /Cashier/ })
        .first()
        .getAttribute('href');
    const id = href?.split('/').pop();
    await page.context().clearCookies(); // now a visitor who is not signed in
    await page.goto(`/jobs/${id}`);
    await expect(page.getByRole('heading', { level: 1, name: 'Cashier' })).toBeVisible();
    await expect(page.getByText(/Sign in or create a free account/)).toBeVisible();
    await expectAccessible(page);
});
