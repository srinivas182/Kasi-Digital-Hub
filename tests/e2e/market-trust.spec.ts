import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

import { signIn } from './support/auth';

async function expectAccessible(page: Page) {
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    const serious = results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''));
    expect(serious.map((violation) => `${violation.id}: ${violation.help} (${violation.nodes.length})`)).toEqual([]);
}

test('a buyer reviews an order and raises a problem; the trader responds; support decides', async ({ browser }) => {
    test.setTimeout(150_000); // buyer, trader and support each sign in and act
    const buyer = await (await browser.newContext()).newPage();
    await signIn(buyer, '084 000 0081'); // a demo citizen
    await buyer.goto('/market/search?q=hair+oil');
    await buyer
        .getByRole('link', { name: /Home-made hair oil/ })
        .first()
        .click();
    await buyer.getByRole('button', { name: 'Add to cart' }).click();
    await buyer.getByRole('link', { name: 'View cart' }).click();
    await buyer.getByRole('radio', { name: /I’ll collect it/ }).check();
    await buyer.getByRole('radio', { name: /Pay when I get it/ }).check();
    await buyer.getByRole('button', { name: 'Place order' }).click();
    const heading = buyer.getByRole('heading', { level: 1, name: /^Order KM-/ });
    await expect(heading).toBeVisible();
    const number = (await heading.innerText()).replace('Order ', '');

    const trader = await (await browser.newContext()).newPage();
    await signIn(trader, '072 000 0003');
    await trader.goto('/market/shop/orders');
    await trader.getByRole('link', { name: new RegExp(number) }).click();
    for (const step of ['Accept', 'Ready to collect', 'Handed over and paid']) {
        await trader.getByRole('button', { name: step }).click();
        await expect(trader.getByText('Order updated.')).toBeVisible();
    }

    await buyer.reload();
    await buyer.getByRole('radio', { name: '4 stars' }).check();
    await buyer.getByRole('textbox', { name: 'Tell others (optional)' }).fill('Good oil, small bottle.');
    await buyer.getByRole('button', { name: 'Post review' }).click();
    await expect(buyer.getByText('Thanks for your review!')).toBeVisible();
    await buyer.getByRole('button', { name: 'Something went wrong with this order?' }).click();
    await buyer.getByRole('combobox', { name: 'What happened?' }).selectOption('damaged');
    await buyer
        .getByRole('textbox', { name: 'Explain what happened' })
        .fill('The lid was cracked and some oil leaked.');
    await expectAccessible(buyer);
    await buyer.getByRole('button', { name: 'Send to the trader' }).click();
    await expect(buyer.getByText(/asked to respond within 3 days/)).toBeVisible();

    await trader.reload();
    await trader.getByRole('textbox', { name: 'Your response' }).fill('Sorry - I can replace the bottle on Saturday.');
    await trader.getByRole('button', { name: 'Send response' }).click();
    await expect(trader.getByText('Your response has been sent.')).toBeVisible();

    const support = await (await browser.newContext()).newPage();
    await signIn(support, '072 000 0043'); // support agent
    await support.goto('/admin/inbox');
    await support.getByRole('link', { name: new RegExp(`Market dispute ${number}`) }).click();
    await expect(support.getByRole('heading', { level: 1, name: `Market dispute ${number}` })).toBeVisible({
        timeout: 20_000,
    });
    await expectAccessible(support);
    await support
        .getByRole('textbox', { name: /Reason \(both sides see this\)/ })
        .fill('Trader will replace the bottle; agreed with the buyer.');
    await support.getByRole('button', { name: 'Decide' }).click();
    // Sensitive staff action: confirm the PIN, then decide.
    await expect(support.getByRole('heading', { name: 'Confirm your PIN' })).toBeVisible();
    await support.getByLabel('5-digit PIN').fill('24680');
    await support.getByRole('button', { name: 'Confirm' }).click();
    await support
        .getByRole('textbox', { name: /Reason \(both sides see this\)/ })
        .fill('Trader will replace the bottle; agreed with the buyer.');
    await support.getByRole('button', { name: 'Decide' }).click();
    await expect(support.getByText(/Decision recorded/)).toBeVisible();

    await buyer.reload();
    await expect(buyer.getByText('Mediated').first()).toBeVisible();
});
