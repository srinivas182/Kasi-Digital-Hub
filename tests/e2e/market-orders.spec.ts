import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

import { signIn } from './support/auth';

async function expectAccessible(page: Page) {
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    const serious = results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''));
    expect(serious.map((violation) => `${violation.id}: ${violation.help} (${violation.nodes.length})`)).toEqual([]);
}

async function addOilToCart(page: Page) {
    await page.goto('/market/search?q=hair+oil');
    await page
        .getByRole('link', { name: /Home-made hair oil/ })
        .first()
        .click();
    await page.getByRole('button', { name: 'Add to cart' }).click();
    await expect(page.getByText('Added to your cart.')).toBeVisible();
    await page.getByRole('link', { name: 'View cart' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'My cart' })).toBeVisible();
}

test('a buyer orders for collection and the trader accepts and completes it', async ({ browser }) => {
    const buyer = await (await browser.newContext()).newPage();
    // A demo citizen, not a named demo person: order notifications would fill a named person's updates feed,
    // which other tests read.
    await signIn(buyer, '084 000 0077');
    await addOilToCart(buyer);
    await expectAccessible(buyer);
    await buyer.getByRole('button', { name: 'Place order' }).click();
    await expect(buyer.getByText('Order placed! The trader will reply within 48 hours.')).toBeVisible();
    const number = (await buyer.getByRole('heading', { level: 1 }).innerText()).replace('Order ', '');
    await expectAccessible(buyer);

    const trader = await (await browser.newContext()).newPage();
    await signIn(trader, '072 000 0003');
    await trader.goto('/market/shop/orders');
    await expect(trader.getByRole('heading', { level: 1, name: 'Orders' })).toBeVisible();
    await expectAccessible(trader);
    await trader.getByRole('link', { name: new RegExp(number) }).click();
    await trader.getByRole('textbox', { name: /When will it be ready/ }).fill('Saturday 10:00');
    await trader.getByRole('button', { name: 'Accept' }).click();
    await expect(trader.getByText('Order updated.')).toBeVisible();
    await trader.getByRole('button', { name: 'Ready to collect' }).click();
    await trader.getByRole('button', { name: 'Handed over and paid' }).click();
    await expect(trader.getByText('Completed').first()).toBeVisible();

    await buyer.reload();
    await expect(buyer.getByText('Completed').first()).toBeVisible();
});

test('a buyer sends an order on WhatsApp and it is tracked', async ({ page }) => {
    await signIn(page, '084 000 0078');
    await addOilToCart(page);
    await page.getByRole('button', { name: 'Send on WhatsApp' }).click();
    await expect(page.getByText('Order created. Now send it on WhatsApp.')).toBeVisible();
    const link = page.getByRole('link', { name: 'Open WhatsApp' });
    await expect(link).toHaveAttribute('href', /wa\.me\/27720000003\?text=.*KM-/);
    await page.goto('/market/orders');
    await expect(page.getByRole('heading', { level: 1, name: 'My orders' })).toBeVisible();
    await expectAccessible(page);
});
