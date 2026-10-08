import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

import { signIn } from './support/auth';

async function expectAccessible(page: Page) {
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    const serious = results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''));
    expect(serious.map((violation) => `${violation.id}: ${violation.help} (${violation.nodes.length})`)).toEqual([]);
}

const PAGES = [
    ['/', 'Your future starts in your kasi'],
    ['/hubs', 'Find a hub'],
    ['/hubs/tsutsumani', 'Tsutsumani Digital Hub'],
    ['/employers', 'Hire local talent - matched and ready'],
    ['/funders', 'Fund outcomes you can see'],
    ['/about', 'One platform. A hub in every kasi.'],
    ['/help', 'Help and questions'],
    ['/contact', 'Contact us'],
] as const;

for (const [path, heading] of PAGES) {
    test(`public page ${path} renders, fits the screen and is accessible`, async ({ page }) => {
        await page.goto(path);
        await expect(page.getByRole('heading', { level: 1, name: heading })).toBeVisible();
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        expect(overflow).toBeLessThanOrEqual(1);
        await expectAccessible(page);
    });
}

test('the home page sends visitors to sign up', async ({ page }) => {
    await page.goto('/');
    await page.getByRole('link', { name: 'Create my free account' }).first().click();
    await expect(page.getByRole('heading', { name: 'Sign in or create your free account' })).toBeVisible();
});

test('"near me" sorts hubs by distance from the phone location', async ({ page, context }) => {
    await context.grantPermissions(['geolocation']);
    await context.setGeolocation({ latitude: -26.25, longitude: 27.9 }); // Soweto
    await page.goto('/hubs');
    await page.getByRole('button', { name: 'Show hubs near me' }).click();

    const first = page.getByRole('list', { name: 'Find a hub' }).getByRole('listitem').first();
    await expect(first).toContainText('Soweto Hub');
    await expect(first).toContainText('km away');
});

test('the hub search filters the list', async ({ page }) => {
    await page.goto('/hubs');
    await page.getByLabel('Search by hub, place or city').fill('ethekwini');
    await expect(page.getByRole('list', { name: 'Find a hub' }).getByRole('listitem')).toHaveCount(3);
});

test('the help page answers questions without JavaScript tricks', async ({ page }) => {
    await page.goto('/help');
    await page.getByText('Does it cost anything?').click();
    await expect(page.getByText(/KasiHub is free for young people/)).toBeVisible();
});

test('a visitor can send a message through the contact form', async ({ page }) => {
    await page.goto('/contact');
    await page.getByLabel(/Your name/).fill('Ayanda Dlamini');
    await page.getByLabel('Email address').fill('ayanda@example.co.za');
    await page.getByLabel(/Your message/).fill('How do I find the hub closest to Umlazi?');
    await page.getByRole('checkbox', { name: /may use these details/ }).click();
    await page.getByRole('button', { name: 'Send message' }).click();
    await expect(page.getByText(/we've received your message/)).toBeVisible();
});

test('the hub home shows next steps that link to where they are done', async ({ page }) => {
    await signIn(page, '072 000 0003'); // Nomsa (entrepreneur, Soweto) still has open steps
    await expect(page.getByRole('heading', { name: 'Your next steps' })).toBeVisible();
    await expectAccessible(page);
    await page
        .getByRole('link', { name: /Get updates on WhatsApp|Upload your ID|Add where you live/ })
        .first()
        .click();
    await expect(page.getByRole('heading', { name: 'My account' })).toBeVisible();
});
