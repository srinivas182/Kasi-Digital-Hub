import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

import { signIn } from './support/auth';

async function expectAccessible(page: Page) {
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    const serious = results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''));
    expect(serious.map((violation) => `${violation.id}: ${violation.help} (${violation.nodes.length})`)).toEqual([]);
}

async function cartWithOil(page: Page) {
    await page.goto('/market/search?q=hair+oil');
    await page
        .getByRole('link', { name: /Home-made hair oil/ })
        .first()
        .click();
    await page.getByRole('button', { name: 'Add to cart' }).click();
    await expect(page.getByText('Added to your cart.')).toBeVisible();
    await page.getByRole('link', { name: 'View cart' }).click();
}

test('a buyer pays online on the payment page and the order is paid', async ({ page }) => {
    await signIn(page, '084 000 0079'); // a demo citizen (named demo people's feeds are read by other tests)
    await cartWithOil(page);
    await page.getByRole('radio', { name: /Pay now online/ }).check();
    await expectAccessible(page);
    await page.getByRole('button', { name: 'Pay now' }).click();
    await expect(page.getByRole('heading', { name: 'Demo payment' })).toBeVisible();
    await page.getByRole('button', { name: 'Pay', exact: true }).click();
    await expect(page.getByText('Paid online')).toBeVisible();
    await expectAccessible(page);
});

test('the hub checks in a parcel and the buyer collects it with a code', async ({ browser }) => {
    test.setTimeout(120_000); // three people (buyer, trader, hub staff) sign in and act
    const buyer = await (await browser.newContext()).newPage();
    await signIn(buyer, '084 000 0080');
    await cartWithOil(buyer);
    await buyer.getByRole('radio', { name: /Collect at/ }).check();
    await buyer.getByRole('radio', { name: /Pay when I get it/ }).check();
    await buyer.getByRole('button', { name: 'Place order' }).click();
    const heading = buyer.getByRole('heading', { level: 1, name: /^Order KM-/ }); // wait for the order page, not the cart
    await expect(heading).toBeVisible();
    const number = (await heading.innerText()).replace('Order ', '');

    const trader = await (await browser.newContext()).newPage();
    await signIn(trader, '072 000 0003');
    await trader.goto('/market/shop/orders');
    await trader.getByRole('link', { name: new RegExp(number) }).click();
    await trader.getByRole('button', { name: 'Accept' }).click();
    await expect(trader.getByText('Order updated.')).toBeVisible();

    const staff = await (await browser.newContext()).newPage();
    await signIn(staff, '072 000 0025'); // Soweto hub facilitator - Nomsa's hub
    await staff.goto('/market/desk');
    // First visit to a portal that loads its page list on demand, with three browsers running: allow longer.
    await expect(staff.getByRole('heading', { level: 1, name: 'KasiMarket collection desk' })).toBeVisible({
        timeout: 20_000,
    });
    await expectAccessible(staff);
    await staff.getByRole('listitem').filter({ hasText: number }).getByRole('button', { name: 'Check in' }).click();
    await expect(staff.getByText(/Checked in/)).toBeVisible();

    await buyer.goto('/home/updates');
    const code =
        (
            await buyer
                .getByText(/Your collection code is \d{6}/)
                .first()
                .innerText()
        ).match(/\d{6}/)?.[0] ?? '';
    expect(code).toMatch(/^\d{6}$/);

    const row = staff.getByRole('listitem').filter({ hasText: number });
    await row.getByRole('textbox', { name: 'Buyer’s code' }).fill(code);
    await row.getByRole('button', { name: 'Hand over' }).click();
    await expect(staff.getByText('Handed over.')).toBeVisible();
});
