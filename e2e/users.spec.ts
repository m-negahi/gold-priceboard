import { expect, test, type Page } from '@playwright/test';
import { ADMIN, signInAsAdmin } from './helpers/auth';

/**
 * Karbaran e panel (Phase 6) tu browser e vaghei.
 *
 * ⚠️ Ru DB e DEV ejra mishe: faghat KHUNDAN e lista + filter ha. Har
 * karbar-i ke test misaze dar `afterAll` disable/pak-sazi mishe (P6-04+).
 */
test.describe.configure({ mode: 'serial' });

let page: Page;

test.beforeAll(async ({ browser }) => {
    page = await browser.newPage();
    await signInAsAdmin(page);
});

test.afterAll(async () => {
    await page.close();
});

const rows = () => page.locator('[data-user-row]');

/** `#rrggbb` e yek token az `:root` -> `rgb(r, g, b)`. */
async function tokenRgb(name: string): Promise<string> {
    const hex = await page.evaluate((n) => getComputedStyle(document.documentElement).getPropertyValue(n).trim(), name);
    const [r, g, b] = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16));

    return `rgb(${r}, ${g}, ${b})`;
}

test.describe('lista ye karbaran (P6-02)', () => {
    test('sidebar «مشتریان» fa\'al ast va be lista mire', async () => {
        await page.goto('/dashboard');
        await page.locator('aside').getByRole('link', { name: 'مشتریان' }).first().click();

        await expect(page).toHaveURL(/\/dashboard\/users/);
        await expect(page.getByRole('heading', { level: 1, name: 'مشتریان' })).toBeVisible();
        await expect(rows().first()).toBeVisible();
    });

    test('search ba mobile e admin hamun radif ro neshun mide', async () => {
        await page.goto('/dashboard/users');
        await page.getByPlaceholder('جستجو با موبایل یا نام').fill('0' + ADMIN);
        await page.getByRole('button', { name: 'اعمال فیلتر' }).click();

        await expect(page).toHaveURL(/q=0?9128452427/);
        await expect(rows()).toHaveCount(1);
        await expect(rows().first()).toContainText('0' + ADMIN);
        await expect(rows().first()).toContainText('ادمین');
    });

    test('filter e naghsh «ادمین» faghat admin ha', async () => {
        await page.goto('/dashboard/users?role=admin');

        const count = await rows().count();
        expect(count).toBeGreaterThan(0);

        for (let i = 0; i < count; i++) {
            await expect(rows().nth(i)).toContainText('ادمین');
        }
    });

    test('pill e «فعال» rang e token e ok-soft dare', async () => {
        await page.goto('/dashboard/users?q=0' + ADMIN);

        const pill = rows().first().getByText('فعال', { exact: true });
        await expect(pill).toBeVisible();

        const expected = await tokenRgb('--color-ok-soft');
        expect(await pill.evaluate((el) => getComputedStyle(el).backgroundColor)).toBe(expected);
    });
});
