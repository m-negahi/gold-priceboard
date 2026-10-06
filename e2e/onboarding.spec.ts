import { expect, test, type Page } from '@playwright/test';
import { artisan, expectNoPageOverflow, signInAsCustomer, tokenRgb } from './helpers/auth';

/**
 * Onboarding (P7-03) tu browser e vaghei: karbar e taze ba'd az login be
 * `/onboarding` mire؛ «بله» → form e hoghoghi + madrak → ticket e
 * `kyc_upgrade`؛ «خیر» → home va dige porsideh nemishe.
 *
 * ⚠️ DB e DEV: do karbar e MOVAGHAT + ticket/file hashun ghabl/ba'd pak mishan.
 */
test.describe.configure({ mode: 'serial' });

const YES = '09990003456';
const NO = '09990003457';

const removeTempUsers = () =>
    artisan('tinker', `--execute=foreach (['+989990003456', '+989990003457'] as $p) { $u = App\\Modules\\Auth\\Infrastructure\\Persistence\\Models\\User::where('phone', $p)->first(); if ($u) { foreach (App\\Modules\\Ticket\\Infrastructure\\Persistence\\Models\\Ticket::where('user_id', $u->id)->pluck('id') as $id) { Illuminate\\Support\\Facades\\Storage::disk('tickets')->deleteDirectory((string) $id); } $u->delete(); } } echo 'DONE';`);

/** PDF e kuchik e VAGHEI — server mime ro az mohtava (`%PDF-`) migire. */
const PDF = Buffer.from('%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n');

test.beforeAll(() => removeTempUsers());
test.afterAll(() => removeTempUsers());

async function freshCustomer(browser: import('@playwright/test').Browser, phone: string): Promise<Page> {
    const page = await (await browser.newContext()).newPage();
    await signInAsCustomer(page, phone);

    return page;
}

test('«بله»: form e hoghoghi + madrak → ticket e ارتقای سطح', async ({ browser }) => {
    const page = await freshCustomer(browser, YES);
    await expect(page).toHaveURL(/\/onboarding$/);
    await expect(page.getByText('مایل به دریافت فاکتور رسمی هستید؟')).toBeVisible();

    // Form e hoghoghi ta «بله» penhan ast (Alpine — UI-only).
    const legal = page.locator('[data-onboarding-legal]');
    await expect(legal).toBeHidden();
    await page.getByLabel('بله').check();
    await expect(legal).toBeVisible();

    await page.getByLabel('نام ثبتی شرکت').fill('شرکت آزمایشی e2e');
    await page.getByLabel('شناسه ملی شرکت').fill('10380284790');
    await page.getByLabel('کد اقتصادی').fill('411111111111');
    await page.getByLabel('کد پستی').fill('1234567890');
    await page.getByLabel('تلفن ثابت').fill('02112345678');
    await page.getByLabel('نشانی').fill('تهران، خیابان آزمایش');
    await page.getByLabel('کد ملی نماینده').fill('0499370899');
    await page.getByLabel('تاریخ تولد نماینده').fill('1370/01/15');
    await page.locator('input[type="file"]').setInputFiles({ name: 'javaz.pdf', mimeType: 'application/pdf', buffer: PDF });

    // Dokme ye asli = token e brand.
    const submit = page.locator('[data-onboarding-submit]');
    expect(await submit.evaluate((el) => getComputedStyle(el).backgroundColor)).toBe(await tokenRgb(page, '--color-brand'));
    await submit.click();

    await expect(page).toHaveURL(/\/account\/tickets\/\d+$/);
    await expect(page.getByRole('heading', { level: 1 })).toContainText('درخواست ارتقای سطح');
    await expect(page.getByText('ارتقای سطح', { exact: true })).toBeVisible();
    await expect(page.locator('[data-attachment]').filter({ hasText: 'javaz.pdf' })).toHaveCount(1);

    // Dobare: form nist، vaziat e darkhast hast.
    await page.goto('/onboarding');
    await expect(page.locator('[data-onboarding-pending]')).toBeVisible();
    await expect(page.locator('[data-onboarding-form]')).toHaveCount(0);
    await page.context().close();
});

test('khata ye validation (code melli e ghalat) payam e Farsi mide', async ({ browser }) => {
    const page = await freshCustomer(browser, NO);
    await page.getByLabel('بله').check();
    await page.getByLabel('کد ملی نماینده').fill('0499370898');
    await page.locator('[data-onboarding-submit]').click();

    await expect(page).toHaveURL(/\/onboarding$/);
    // Form ba khata dobare BAZ ast (old('answer') = yes).
    await expect(page.locator('[data-onboarding-legal]')).toBeVisible();
    await expect(page.getByText('کد ملی نماینده معتبر نیست.')).toBeVisible();
    await page.context().close();
});

test('«خیر» → home، va login e ba\'di dige onboarding nadare', async ({ browser }) => {
    const page = await freshCustomer(browser, NO);
    await expect(page).toHaveURL(/\/onboarding$/);
    await expectNoPageOverflow(page);
    await page.setViewportSize({ width: 375, height: 667 });
    await expectNoPageOverflow(page);

    await page.getByLabel('خیر').check();
    await page.locator('[data-onboarding-submit]').click();
    await expect(page).toHaveURL((url) => url.pathname === '/');
    await page.context().close();

    const again = await freshCustomer(browser, NO);
    await expect(again).toHaveURL((url) => url.pathname === '/');
    await again.context().close();
});
