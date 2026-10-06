import { expect, test, type Page } from '@playwright/test';
import { artisan, expectNoPageOverflow, signInAsCustomer, tokenRgb } from './helpers/auth';

/**
 * Panel e moshtari (P8): `/account` + sites/subscriptions/wallet/usage tu
 * browser e vaghei. Moshtari yek site misaze va ramz e YEK-BARE ro mibine.
 *
 * ⚠️ DB e DEV: karbar e MOVAGHAT (+ api_client/ticket/wallet — cascade) ghabl/ba'd
 * pak mishe. Hich topup e vaghei zade nemishe — faghat vojud e form check mishe.
 */
test.describe.configure({ mode: 'serial' });

const CUSTOMER = '09990004567';
const SITE_NAME = 'سایت آزمایشی e2e';
const SITE_URL = 'https://e2e-billing.example.com';

const removeTempUser = () =>
    artisan('tinker', `--execute=App\\Modules\\Auth\\Infrastructure\\Persistence\\Models\\User::where('phone', '+989990004567')->delete(); echo 'DONE';`);

let page: Page;

test.beforeAll(async ({ browser }) => {
    removeTempUser();
    page = await (await browser.newContext()).newPage();
    await signInAsCustomer(page, CUSTOMER);
});

test.afterAll(async () => {
    removeTempUser();
    await page.context().close();
});

test('har 5 bakhsh e /account render mishe va nav e fa\'al token e brand dare', async () => {
    const sections: Array<[string, string]> = [
        ['/account', 'نمای کلی'],
        ['/account/sites', 'سایت‌ها'],
        ['/account/subscriptions', 'اشتراک‌ها'],
        ['/account/wallet', 'کیف پول'],
        ['/account/usage', 'مصرف'],
    ];
    const brand = await (async () => {
        await page.goto('/account');

        return tokenRgb(page, '--color-brand');
    })();

    for (const [path, label] of sections) {
        await page.goto(path);
        await expect(page.getByRole('heading', { level: 1, name: 'حساب من' })).toBeVisible();
        const active = page.getByRole('navigation', { name: 'بخش‌های حساب' }).getByRole('link', { name: label, exact: true });
        expect(await active.evaluate((el) => getComputedStyle(el).backgroundColor), `nav e ${path}`).toBe(brand);
    }
});

test('nama ye kolli: stat card ha + shoru\' e sari\'', async () => {
    await page.goto('/account');
    await expect(page.getByText('موجودی کیف پول')).toBeVisible();
    await expect(page.getByText('اشتراک فعال')).toBeVisible();
    await expect(page.getByRole('link', { name: 'افزودن سایت' })).toBeVisible();
});

test('sabt e site: client e pending + ramz e yek-bare', async () => {
    await page.goto('/account/sites');
    await expect(page.getByText('هنوز سایتی ثبت نکرده‌اید.')).toBeVisible();

    await page.getByLabel('نام سایت').fill(SITE_NAME);
    await page.getByLabel('آدرس سایت').fill(SITE_URL);
    await page.getByRole('button', { name: 'ثبت سایت' }).click();

    await expect(page).toHaveURL(/\/account\/sites$/);
    const credentials = page.locator('[data-site-credentials]');
    await expect(credentials).toBeVisible();
    await expect(credentials).toContainText('رمز فقط همین بار نمایش داده می‌شود');
    const secret = (await credentials.locator('dd').nth(1).textContent())?.trim() ?? '';
    expect(secret.length).toBeGreaterThan(16);

    const row = page.locator('tbody tr').filter({ hasText: SITE_NAME });
    await expect(row).toHaveCount(1);
    await expect(row).toContainText(SITE_URL);
    await expect(row).toContainText('در انتظار تأیید');

    // Refresh: ramz dige nist (flash e yek-bare).
    await page.reload();
    await expect(page.locator('[data-site-credentials]')).toHaveCount(0);
    await expect(page.getByText(secret)).toHaveCount(0);
});

test('eshterak ha: site e pending tu select e kharid nist', async () => {
    await page.goto('/account/subscriptions');
    await expect(page.getByText('اشتراک‌های من')).toBeVisible();
    await expect(page.getByText('اشتراکی ثبت نشده است.')).toBeVisible();
    // Faghat site e TAEED-shode ghabel e entekhab e؛ site e pending e ma nabayad bashe.
    for (const select of await page.getByRole('combobox', { name: 'انتخاب سایت' }).all()) {
        await expect(select.locator('option').filter({ hasText: SITE_NAME })).toHaveCount(0);
    }
});

test('kif e pul: mande sefr + form e topup (ya payam e dargah e tanzim-nashode)', async () => {
    await page.goto('/account/wallet');
    await expect(page.getByText('موجودی کیف پول')).toBeVisible();
    await expect(page.getByText('گردشی ثبت نشده است.')).toBeVisible();

    // Form faghat vaghti dargah config shode render mishe (config('billing.vandar.api_key') / zibal.merchant).
    const form = page.locator('form[action$="/account/wallet/topup"]');
    if ((await form.count()) === 1) {
        await expect(form.locator('input[name="amount_rial"]')).toBeVisible();
        await expect(form.locator('input[name="request_key"]')).toHaveCount(1);
        await expect(form.getByRole('button', { name: 'پرداخت با زیبال' })).toBeVisible();
    } else {
        await expect(page.getByText('درگاه پرداخت هنوز تنظیم نشده است.')).toBeVisible();
    }
});

test('masraf: do stat card + jadval e ruzane', async () => {
    await page.goto('/account/usage');
    await expect(page.getByText('فراخوانی 30 روز اخیر')).toBeVisible();
    await expect(page.getByText('هزینه 30 روز اخیر')).toBeVisible();
    await expect(page.locator('table')).toBeVisible();
});

test('safhe haye /account tu 375px overflow nadaran', async () => {
    await page.setViewportSize({ width: 375, height: 667 });
    for (const path of ['/account', '/account/sites', '/account/wallet']) {
        await page.goto(path);
        await expectNoPageOverflow(page);
    }
});
