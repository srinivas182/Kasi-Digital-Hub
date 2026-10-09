import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

import { signIn } from './support/auth';

async function expectAccessible(page: Page) {
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    const serious = results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''));
    expect(serious.map((violation) => `${violation.id}: ${violation.help} (${violation.nodes.length})`)).toEqual([]);
}

test('a young person builds a job profile with writing help and makes a CV', async ({ page }) => {
    test.setTimeout(90_000);
    await signIn(page, '072 000 0002');
    await page.goto('/work/profile');
    await expectAccessible(page);

    await page.getByRole('textbox', { name: 'Headline' }).fill('Hard-working general worker');
    await page.getByRole('textbox', { name: 'About you' }).fill('I am strong, I arrive on time and I learn fast.');
    await page.getByRole('button', { name: 'Save and continue' }).click();

    await expect(page.getByRole('heading', { name: 'General worker' })).toBeVisible();
    await expectAccessible(page);
    await page.getByRole('button', { name: 'Help me write CV points' }).click();
    await expect(page.getByText('Suggestion - check it, change it or ignore it')).toBeVisible();
    await page.getByRole('button', { name: 'Use this' }).click();
    await expect(page.getByText('(Demo AI)').first()).toBeVisible();

    await page.goto('/work/profile?step=skills');
    for (const skill of ['Customer service', 'Cleaning', 'Cooking']) {
        const add = page.getByRole('button', { name: `+ ${skill}` });
        if (await add.count()) await add.click(); // already added by the other screen size
        await expect(page.getByRole('list', { name: 'Your skills' }).getByText(skill)).toBeVisible();
    }
    await page.getByRole('button', { name: 'Save and continue' }).click();
    await expect(page).toHaveURL(/step=looking_for/);

    await page.goto('/work/cv');
    await expect(page.getByRole('heading', { level: 1, name: 'My CV' })).toBeVisible();
    await expect(page.getByText('Never on your CV: ID number, photo, date of birth or street address.')).toBeVisible();
    await expectAccessible(page);
    await page.getByRole('radio', { name: 'Simple (prints cheaply)' }).check();
    await page.getByRole('button', { name: 'Create my CV' }).click();
    await expect(page.getByText('Your CV is ready.')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Download PDF' }).first()).toBeVisible(); // the other screen size may have made one too

    await page.getByRole('button', { name: 'Get a share link' }).first().click();
    await expect(page.getByRole('link', { name: 'Send on WhatsApp' })).toHaveAttribute(
        'href',
        /^https:\/\/wa\.me\/\?text=/,
    );
    await expectAccessible(page);
});

test('the voice-note button explains what to do when there is no microphone', async ({ page }) => {
    await signIn(page, '072 000 0001');
    await page.goto('/work/profile');
    await page.getByRole('button', { name: 'Record a voice note' }).click();
    await expect(page.getByText(/can't use the microphone|Turning your voice note/)).toBeVisible();
});
