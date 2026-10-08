import { expect, test } from '@playwright/test';

import { signIn } from './support/auth';

const CASES = [
    {
        phone: '072 000 0001',
        who: 'Thandi (job seeker, learner)',
        sees: ['KasiWork', 'KasiLearn'],
        hidden: ['KasiHub Ops', 'National admin console'],
        hub: 'Tsutsumani Digital Hub',
    },
    {
        phone: '072 000 0020',
        who: 'Rhulani (facilitator)',
        sees: ['KasiWork', 'KasiHub Ops'],
        hidden: ['National admin console', 'Funder & Programme portal'],
        hub: 'Tsutsumani Digital Hub',
    },
    {
        phone: '072 000 0040',
        who: 'Lucky (super admin)',
        sees: ['National admin console', 'Commercial & finance console', 'Regional console'],
        hidden: [],
        hub: null,
    },
];

for (const person of CASES) {
    test(`${person.who} sees only their portals`, async ({ page }) => {
        await signIn(page, person.phone);

        if (person.hub) await expect(page.getByText(person.hub, { exact: true })).toBeVisible();

        await page.getByRole('button', { name: 'All services' }).click();
        for (const portal of person.sees) await expect(page.getByRole('link', { name: portal })).toBeVisible();
        for (const portal of person.hidden) await expect(page.getByRole('link', { name: portal })).toHaveCount(0);
    });
}

test('a person can see their roles on their account page', async ({ page }) => {
    await signIn(page, '072 000 0001');
    await page.goto('/account');
    await expect(page.getByText(/Job seeker · Own account/)).toBeVisible();
    await expect(page.getByLabel(/Your nearest hub/)).toHaveValue(/.+/);
});
