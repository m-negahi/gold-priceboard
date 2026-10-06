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

/**
 * Rang e yek token e `@theme` -> `rgb(...)` ke browser khodesh resolve
 * karde (probe element) — `#fff` e kutah ya `var(...)` ham dorost mishe.
 */
async function tokenRgb(name: string): Promise<string> {
    const rgb = await page.evaluate((n) => {
        const probe = document.createElement('div');
        probe.style.backgroundColor = `var(${n})`;
        document.body.appendChild(probe);
        const rgb = getComputedStyle(probe).backgroundColor;
        probe.remove();

        return rgb;
    }, name);

    // Token e nabude = transparent — nabayad ba element e shafaf «barabar» she.
    expect(rgb, `token e ${name} resolve nashod`).not.toBe('rgba(0, 0, 0, 0)');

    return rgb;
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

test.describe('safhe ye karbar (P6-03)', () => {
    test('az lista be safhe ye admin + bakhsh ha', async () => {
        await page.goto('/dashboard/users?q=0' + ADMIN);
        await rows().first().getByRole('link', { name: /^جزئیات/ }).click();

        await expect(page).toHaveURL(/\/dashboard\/users\/\d+$/);
        await expect(page.getByRole('heading', { level: 2, name: 'مشخصات' })).toBeVisible();
        await expect(page.getByRole('heading', { level: 2, name: 'کیف پول' })).toBeVisible();
        await expect(page.getByRole('heading', { level: 2, name: 'سایت‌ها و پلن‌ها' })).toBeVisible();
        await expect(page.locator('[data-user-status]')).toHaveAttribute('data-user-status', 'active');
    });

    test('card e mande rang e surface e token dare', async () => {
        const url = page.url();
        await page.goto(url);

        const card = page.locator('[data-wallet]');
        const expected = await tokenRgb('--color-surface');
        expect(await card.evaluate((el) => getComputedStyle(el).backgroundColor)).toBe(expected);
    });
});

