import { expect, test, type Page } from '@playwright/test';
import { expectNoPageOverflow, signInAsAdmin, tokenRgb } from './helpers/auth';

/**
 * «پلن‌های API» (P8-01) tu panel e admin: lista + form e sakht. Input haye
 * mablagh (`x-form.money`) jodakonande ye hezargan ro ZENDE mizanan.
 *
 * Hich plan-i SUBMIT nemishe (DB e dev) — faghat render + raftar e input.
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

test('lista: heading، jadval va dokme ye «پلن جدید»', async () => {
    await page.goto('/dashboard/plans');
    await expect(page.getByRole('heading', { level: 1, name: 'پلن‌های API' })).toBeVisible();
    await expect(page.locator('table thead')).toContainText('قیمت (ریال)');
    const create = page.getByRole('link', { name: 'پلن جدید' });
    await expect(create).toBeVisible();
    expect(await create.evaluate((el) => getComputedStyle(el).backgroundColor)).toBe(await tokenRgb(page, '--color-brand'));
    // Ya radif e plan hast ya payam e khali — hich halat e sevomi na.
    const rows = page.locator('table tbody tr');
    expect(await rows.count()).toBeGreaterThan(0);
});

test('form e sakht: hame ye field ha render mishan', async () => {
    await page.goto('/dashboard/plans/create');
    await expect(page.getByRole('heading', { level: 1, name: 'پلن جدید' })).toBeVisible();
    for (const label of ['کلید لاتین', 'نام پلن', 'مدت (روز)', 'فراخوانی مشمول دوره', 'فراخوانی مشمول هر روز', 'قیمت اشتراک (ریال)', 'هزینه مازاد هر فراخوانی (ریال)', 'ترتیب']) {
        await expect(page.getByLabel(label), label).toBeVisible();
    }
    await expect(page.getByLabel('فعال')).toBeChecked();
});

test('input e gheymat: adad e Farsi → Latin ba jodakonande ye hezargan', async () => {
    const price = page.locator('input[name="price_rial"]');
    await price.fill('');
    await price.pressSequentially('۱۲۵۰۰۰۰');
    await expect(price).toHaveValue('1,250,000');
    await expect(price).toHaveAttribute('dir', 'ltr');

    const overage = page.locator('input[name="cost_per_call_rial"]');
    await overage.fill('');
    await overage.pressSequentially('500');
    await expect(overage).toHaveValue('500');
    await overage.pressSequentially('0');
    await expect(overage).toHaveValue('5,000');
});

test('safhe ha tu 375px overflow nadaran', async () => {
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/dashboard/plans');
    await expectNoPageOverflow(page);
    await page.goto('/dashboard/plans/create');
    await expectNoPageOverflow(page);
});
