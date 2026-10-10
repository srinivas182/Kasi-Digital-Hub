import { expect, type Page } from '@playwright/test';

export const DEMO_PIN = '24680';

/** Demo mode shows the SMS / authenticator code on screen. */
export async function demoCode(page: Page): Promise<string> {
    const text = (await page.getByTestId('demo-code').textContent()) ?? '';
    const match = text.match(/(\d{6})/);
    expect(match, 'demo code shown on screen').not.toBeNull();
    return match![1]!;
}

/** Signs in a demo account through code, PIN and (for staff) the authenticator step. */
export async function signIn(page: Page, phone: string): Promise<void> {
    // Phone and desktop runs sign in the same demo accounts in parallel. A new code for a number replaces the
    // previous one (correct security behaviour), so a run can lose the race - start again when that happens.
    const pin = page.getByLabel('5-digit PIN digit 1 of 5');
    for (let attempt = 1; attempt <= 3; attempt++) {
        await page.goto('/login');
        await page.getByLabel('Cellphone number').fill(phone);
        await page.getByRole('button', { name: 'Continue' }).click();
        await page.getByLabel('6-digit code digit 1 of 6').fill(await demoCode(page));
        await page.getByRole('button', { name: 'Continue' }).click();
        try {
            await expect(pin).toBeVisible({ timeout: 6_000 });
            break;
        } catch (error) {
            if (attempt === 3) throw error;
        }
    }
    await pin.fill(DEMO_PIN);
    await page.getByRole('button', { name: 'Sign in' }).click();

    const setup = page.getByRole('heading', { name: 'Protect your staff account' });
    const challenge = page.getByRole('heading', { name: 'Enter your authenticator code' });
    const home = page.getByRole('heading', { name: /^Welcome,/ });
    await expect(setup.or(challenge).or(home)).toBeVisible();

    if (await setup.isVisible()) {
        await page.getByLabel(/6-digit code from the app/).fill(await demoCode(page));
        await page.getByRole('button', { name: 'Turn on and continue' }).click();
        await page.getByRole('button', { name: "I've saved my codes" }).click();
    } else if (await challenge.isVisible()) {
        await page.getByLabel(/Code or backup code/).fill(await demoCode(page));
        await page.getByRole('button', { name: 'Sign in' }).click();
    }

    await expect(home).toBeVisible();
}
