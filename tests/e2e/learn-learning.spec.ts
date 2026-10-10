import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

import { signIn } from './support/auth';

async function expectAccessible(page: Page) {
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    const serious = results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''));
    expect(serious.map((violation) => `${violation.id}: ${violation.help} (${violation.nodes.length})`)).toEqual([]);
}

async function openCourse(page: Page, name: RegExp) {
    await page.goto('/learn/courses');
    await page.getByRole('link', { name }).first().click();
    await expect(page.getByRole('heading', { level: 1, name })).toBeVisible();
    const go = page.getByRole('link', { name: 'Go to my course' });
    if (await go.count()) await go.click();
    else await page.getByRole('button', { name: "Enrol - it's free" }).click();
    await expect(page.getByRole('heading', { name: 'Use offline' })).toBeVisible();
}

test('a learner enrols, finishes a lesson and passes a graded quiz', async ({ page }) => {
    test.setTimeout(90_000);
    await signIn(page, '072 000 0001');
    await openCourse(page, /Customer service essentials/);
    await expectAccessible(page);

    await page.getByRole('link', { name: 'First impressions' }).click();
    await expectAccessible(page);
    const finish = page.getByRole('button', { name: "I've finished this lesson" });
    if (await finish.count()) await finish.click();
    await expect(page.getByText('Finished', { exact: true })).toBeVisible();

    await page.goto(page.url().replace(/\/lessons\/.*/, ''));
    await page.getByRole('link', { name: 'Quiz: customer service' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'Quiz: customer service' })).toBeVisible();
    const already = page.getByText('You already passed this quiz.');
    if (!(await already.count())) {
        await page.getByRole('radio', { name: 'R18' }).check();
        await page.getByRole('radio', { name: 'Listen calmly and let them explain' }).check();
        await page.getByRole('button', { name: 'Check my answers' }).click();
        await expect(page.getByText(/You passed with 100%/)).toBeVisible();
    }
    await expectAccessible(page);
});

test('a learner downloads a course, learns offline and the progress syncs later', async ({ page, context }) => {
    test.setTimeout(90_000);
    await signIn(page, '072 000 0003');
    await openCourse(page, /Digital skills basics/);
    page.on('dialog', (dialog) => void dialog.accept());
    const download = page.getByRole('button', { name: 'Download for offline' });
    if (await download.count()) await download.click();
    await expect(page.getByText('Downloaded - you can learn without data.')).toBeVisible({ timeout: 30_000 });

    await page.goto('/learn/offline');
    await page.getByRole('button', { name: /Digital skills basics/ }).click();
    await context.setOffline(true);
    await page.getByRole('button', { name: 'Email in five steps' }).click();
    await page.getByRole('button', { name: "I've finished this lesson" }).click();
    await expect(page.getByText(/1 updates waiting to be sent/)).toBeVisible();
    await expectAccessible(page);

    await context.setOffline(false);
    await expect(page.getByText(/updates waiting to be sent/)).toHaveCount(0, { timeout: 15_000 });
});

test('an assessor sees the work waiting to be assessed', async ({ page, isMobile }) => {
    test.skip(isMobile, 'Assessment is a desktop tool');
    await signIn(page, '072 000 0012');
    await page.goto('/learn/assess');
    await expect(page.getByRole('heading', { level: 1, name: 'Assess work' })).toBeVisible();
    await expectAccessible(page);
});
