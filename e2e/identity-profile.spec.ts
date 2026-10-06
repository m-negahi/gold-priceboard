import { expect, test, type Page } from '@playwright/test';
import { artisan, expectNoPageOverflow, signInAsAdmin, tokenRgb } from './helpers/auth';

/** Profile e haghighi/hoghoghi va peyvast e private ba kit e mojud. */
test.describe.configure({ mode: 'serial' });
const PHONE = '09990004567';
const E164 = '+989990004567';
const PDF = Buffer.from('%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n');
let page: Page;
let userId: string;

const cleanup = () => artisan('tinker', `--execute=$u = App\\Modules\\Auth\\Infrastructure\\Persistence\\Models\\User::where('phone', '${E164}')->first(); if ($u) { foreach (App\\Modules\\Ticket\\Infrastructure\\Persistence\\Models\\Ticket::where('user_id', $u->id)->pluck('id') as $id) { Illuminate\\Support\\Facades\\Storage::disk('tickets')->deleteDirectory((string) $id); } $u->delete(); } echo 'DONE';`);

test.beforeAll(async ({ browser }) => {
    cleanup();
    page = await browser.newPage();
    await signInAsAdmin(page);
});

test.afterAll(async () => {
    cleanup();
    await page.close();
});

test('sakht e haghighi va virayesh e hoghoghi audit darad', async () => {
    await page.goto('/dashboard/users/create');
    await page.getByRole('textbox', { name: /^موبایل/ }).fill(PHONE);
    await page.getByLabel('نام', { exact: true }).fill('کاربر آزمایشی احراز هویت');
    await page.getByLabel('کد ملی', { exact: true }).fill('0499370899');
    await page.getByLabel('تاریخ تولد (شمسی)', { exact: true }).fill('1370/01/15');
    await page.getByLabel('کد پستی', { exact: true }).fill('1234567890');
    await page.getByLabel('نشانی', { exact: true }).fill('تهران، خیابان آزمایش');
    await page.getByRole('button', { name: 'ساخت کاربر' }).click();
    await expect(page).toHaveURL(/\/dashboard\/users\/\d+$/);
    userId = page.url().split('/').pop()!;
    await page.getByRole('link', { name: 'ویرایش اطلاعات و استعلام زحل' }).click();
    await expect(page.locator('[name="national_code"]')).toHaveValue('0499370899');
    await page.getByLabel('نوع مشتری').selectOption('legal');
    await page.getByLabel('نام ثبتی شرکت').fill('شرکت آزمایشی احراز هویت');
    await page.getByLabel('شناسه ملی شرکت').fill('10380284790');
    await page.getByLabel('کد ملی نماینده').fill('0499370899');
    await page.getByLabel('تاریخ تولد نماینده (شمسی)').fill('1370/01/15');
    await page.getByRole('button', { name: 'ذخیره و بررسی با زحل' }).click();
    await expect(page.getByRole('heading', { name: 'تاریخچه تغییرات' })).toBeVisible();
    await expect(page.getByLabel('نام ثبتی شرکت')).toHaveValue('شرکت آزمایشی احراز هویت');
    await expect(page.locator('[data-verification-report]')).toBeVisible();
    const surface = await tokenRgb(page, '--color-surface');
    expect(await page.locator('[data-verification-report]').evaluate((el) => getComputedStyle(el).backgroundColor)).toBe(surface);
    await page.screenshot({ path: 'test-results/identity-profile-desktop.png', fullPage: true });
});

test('admin ticket ra ba do peyvast baraye karbar misazad', async () => {
    await page.goto('/dashboard/tickets/create');
    await page.getByRole('combobox', { name: /^کاربر/ }).selectOption(userId);
    await page.getByLabel('موضوع').fill('تیکت چندپیوستی آزمایشی');
    await page.getByLabel('متن پیام').fill('دو مدرک خصوصی برای بررسی');
    await page.locator('input[type="file"]').setInputFiles([
        { name: 'first.pdf', mimeType: 'application/pdf', buffer: PDF },
        { name: 'second.pdf', mimeType: 'application/pdf', buffer: PDF },
    ]);
    await expect(page.locator('[data-private-attachments]')).toContainText('first.pdf');
    await expect(page.locator('[data-private-attachments]')).toContainText('second.pdf');
    await page.getByRole('button', { name: 'ثبت تیکت', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard\/tickets\/\d+$/);
    await expect(page.locator('[data-attachment]')).toHaveCount(2);
});

test('form haye hoviat va ticket dar 375px overflow nadarand', async () => {
    await page.setViewportSize({ width: 375, height: 667 });
    expect((await page.goto(`/dashboard/users/${userId}/edit`))!.status()).toBe(200);
    await expect(page.locator('[data-verification-report]')).toBeVisible();
    await expect(page.getByLabel('نام ثبتی شرکت')).toHaveValue('شرکت آزمایشی احراز هویت');
    await expectNoPageOverflow(page);
    await page.screenshot({ path: 'test-results/identity-profile-mobile.png', fullPage: true });
    expect((await page.goto('/dashboard/tickets/create'))!.status()).toBe(200);
    await page.getByLabel('نوع درخواست').selectOption('kyc_upgrade');
    await page.getByLabel('نوع مشتری').selectOption('legal');
    await expect(page.getByLabel('شناسه ملی شرکت')).toBeVisible();
    await expectNoPageOverflow(page);
    await page.screenshot({ path: 'test-results/identity-ticket-mobile.png', fullPage: true });
});
