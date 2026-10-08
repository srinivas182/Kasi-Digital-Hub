import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

import { DEMO_PIN, demoCode } from './support/auth';

async function expectAccessible(page: Page) {
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    const serious = results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''));
    expect(serious.map((violation) => `${violation.id}: ${violation.help} (${violation.nodes.length})`)).toEqual([]);
}

async function enterPhone(page: Page, phone: string) {
    await page.goto('/login');
    await page.getByLabel('Cellphone number').fill(phone);
    await page.getByRole('button', { name: 'Continue' }).click();
}

function randomMobile(): string {
    return `083${String(Math.floor(1_000_000 + Math.random() * 8_999_999))}`;
}

test('a new person signs up with phone, code, PIN, age, name and consent', async ({ page }) => {
    await enterPhone(page, randomMobile());
    await expect(page.getByRole('heading', { name: 'Enter the code we sent' })).toBeVisible();
    await expectAccessible(page);
    await page.getByLabel('6-digit code digit 1 of 6').fill(await demoCode(page));
    await page.getByRole('button', { name: 'Continue' }).click();

    await expect(page.getByRole('heading', { name: 'Create your free account' })).toBeVisible();
    await expectAccessible(page);
    await page.getByLabel('Choose a 5-digit PIN digit 1 of 5').fill('86420');
    await page.getByLabel('Enter the PIN again digit 1 of 5').fill('86420');
    await page.getByRole('button', { name: 'Next' }).click();

    await page.getByLabel(/Date of birth/).fill('2003-04-11');
    await page.getByRole('button', { name: 'Next' }).click();

    await page.getByLabel(/First name/).fill('Ayanda');
    await page.getByLabel(/Surname/).fill('Dlamini');
    await page.getByRole('button', { name: 'Next' }).click();

    await page.getByRole('checkbox', { name: /I have read and agree/ }).click();
    await page.getByRole('switch', { name: 'Match me to jobs' }).click();
    await expectAccessible(page);
    await page.getByRole('button', { name: 'Create my account' }).click();

    await expect(page.getByRole('heading', { name: 'Welcome, Ayanda' })).toBeVisible();
    await expect(page.getByText('Your account is ready.')).toBeVisible();
});

test('a returning person signs in on a new device with code then PIN, and manages their account', async ({ page }) => {
    await enterPhone(page, '072 000 0001');
    await page.getByLabel('6-digit code digit 1 of 6').fill(await demoCode(page));
    await page.getByRole('button', { name: 'Continue' }).click();

    await expect(page.getByRole('heading', { name: 'Enter your PIN' })).toBeVisible();
    await expectAccessible(page);
    await page.getByLabel('5-digit PIN digit 1 of 5').fill(DEMO_PIN);
    await page.getByRole('button', { name: 'Sign in' }).click();

    await expect(page.getByRole('heading', { name: /Welcome, Thandi/ })).toBeVisible();
    await page.goto('/account');
    await expect(page.getByRole('heading', { name: 'My account' })).toBeVisible();
    await expectAccessible(page);
    await page.getByRole('tab', { name: 'Privacy' }).click();
    await expect(page.getByRole('switch', { name: 'Match me to jobs' })).toBeVisible();
    await expectAccessible(page);

    await page.getByRole('button', { name: 'Sign out' }).first().click();
    await expect(page.getByRole('heading', { name: 'Sign in or create your free account' })).toBeVisible();
});

test('staff sign in with PIN and an authenticator code', async ({ page }) => {
    await enterPhone(page, '072 000 0040');
    await page.getByLabel('6-digit code digit 1 of 6').fill(await demoCode(page));
    await page.getByRole('button', { name: 'Continue' }).click();
    await page.getByLabel('5-digit PIN digit 1 of 5').fill(DEMO_PIN);
    await page.getByRole('button', { name: 'Sign in' }).click();

    const setup = page.getByRole('heading', { name: 'Protect your staff account' });
    const challenge = page.getByRole('heading', { name: 'Enter your authenticator code' });
    await expect(setup.or(challenge)).toBeVisible();

    if (await setup.isVisible()) {
        await expectAccessible(page);
        await page.getByLabel(/6-digit code from the app/).fill(await demoCode(page));
        await page.getByRole('button', { name: 'Turn on and continue' }).click();
        await expect(page.getByRole('heading', { name: 'Save your backup codes' })).toBeVisible();
        await page.getByRole('button', { name: "I've saved my codes" }).click();
    } else {
        await page.getByLabel(/Code or backup code/).fill(await demoCode(page));
        await page.getByRole('button', { name: 'Sign in' }).click();
    }

    await expect(page.getByRole('heading', { name: /Welcome, Lucky/ })).toBeVisible();
});

test('signed-out visitors are sent to sign in', async ({ page }) => {
    await page.goto('/home');
    await expect(page).toHaveURL(/\/login$/);
    await expectAccessible(page);
});
