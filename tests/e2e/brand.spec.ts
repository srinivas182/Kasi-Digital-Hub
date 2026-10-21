import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

import { signIn } from './support/auth';

async function expectAccessible(page: Page) {
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    const serious = results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''));
    expect(serious.map((violation) => `${violation.id}: ${violation.help} (${violation.nodes.length})`)).toEqual([]);
}

test('an owner sets up a brand kit and makes a flyer, then downloads it', async ({ page }) => {
    test.setTimeout(90_000);
    await signIn(page, '072 000 0003');
    await page.goto('/brand/kit');
    await expect(page.getByRole('heading', { level: 1, name: 'Your brand kit' })).toBeVisible({ timeout: 20_000 });
    await page.getByRole('button', { name: 'Forest and amber' }).click();
    await page.getByRole('textbox', { name: 'Tagline' }).fill('Beautiful hair, done with love');
    await page.getByRole('button', { name: 'Save brand kit' }).click();
    await expect(page.getByText(/Brand kit saved/)).toBeVisible();
    await expectAccessible(page);

    await page.goto('/brand/make/flyer');
    await expect(page.getByRole('heading', { level: 1, name: 'Flyer' })).toBeVisible();
    await page.getByRole('textbox', { name: 'Headline' }).fill('December braids special');
    await page.getByRole('textbox', { name: 'Text' }).fill('Box braids R300 - book on WhatsApp');
    await expect(page.getByRole('img', { name: /December braids special/ })).toBeVisible();
    await expectAccessible(page);
    const download = page.waitForEvent('download');
    await page.getByRole('button', { name: 'Download picture (PNG)' }).click();
    expect((await download).suggestedFilename()).toBe('flyer.png');
    await page.getByRole('button', { name: 'Save design' }).click();
    await expect(page.getByText('Design saved.')).toBeVisible();
});
