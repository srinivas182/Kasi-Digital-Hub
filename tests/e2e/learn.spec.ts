import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

import { signIn } from './support/auth';

async function expectAccessible(page: Page) {
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    const serious = results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''));
    expect(serious.map((violation) => `${violation.id}: ${violation.help} (${violation.nodes.length})`)).toEqual([]);
}

test('a young person browses the catalogue, previews a lesson and saves a course', async ({ page }) => {
    await signIn(page, '072 000 0002');
    await page.goto('/learn/courses');
    await expect(page.getByRole('heading', { level: 1, name: 'Courses' })).toBeVisible();
    await expectAccessible(page);
    await page
        .getByRole('link', { name: /Digital skills basics/ })
        .first()
        .click();
    await expect(page.getByRole('heading', { level: 1, name: /Digital skills basics/ })).toBeVisible();
    await expect(page.getByText('Data needed')).toBeVisible();
    await expectAccessible(page);
    await page.getByRole('link', { name: 'Your phone is a tool' }).click();
    await expect(page.getByText('Free preview lesson')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Key points' })).toBeVisible();
    await expectAccessible(page);
});

test('a public course page is readable without signing in', async ({ page }) => {
    await page.goto('/learn/courses');
    await page
        .getByRole('link', { name: /Customer service essentials/ })
        .first()
        .click();
    await expect(page.getByText('Sign in or create a free account to save this course.')).toBeVisible();
    await expectAccessible(page);
});

test('a course author writes a lesson in the editor and sees the checks', async ({ page, isMobile }) => {
    test.skip(isMobile, 'Course authoring is a desktop tool');
    await signIn(page, '072 000 0012');
    await page.goto('/learn/provider');
    await page.getByRole('link', { name: 'CV and interview skills (demo, draft)' }).click();
    await expect(page.getByRole('heading', { name: 'Before you submit' })).toBeVisible();
    await expectAccessible(page);

    await page.getByRole('textbox', { name: 'Lesson title' }).fill('Writing your CV');
    await page.getByRole('button', { name: 'Add lesson' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'Writing your CV' })).toBeVisible();
    const editor = page.getByRole('textbox', { name: 'Lesson text' });
    await editor.click();
    await page.keyboard.type('A good CV is short and clear.');
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page.getByText('Saved.')).toBeVisible();
    await expect(page.getByText('No problems found.')).toBeVisible();
    await expectAccessible(page);
});
