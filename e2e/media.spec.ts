import { expect, test } from '@playwright/test';
import { artisan, expectNoPageOverflow, signInAsAdmin, tokenRgb } from './helpers/auth';

/** Faghat fixture haye hamin spec ru DB e dev pak mishan. */
const cleanup = () => artisan('tinker', '--execute=' + String.raw`
$p = App\Modules\Pricing\Infrastructure\Persistence\Models\Provider::withTrashed()->where('key', 'e2e-media-review')->first();
if ($p) { app(App\Modules\Media\Actions\SyncMediaUsageAction::class)->forget($p); $p->forceDelete(); }
foreach (App\Modules\Media\Infrastructure\Persistence\Models\Media::where('name', 'e2e-media-review.svg')->get() as $m) { app(App\Modules\Media\Actions\DeleteMediaAction::class)->handle($m); }
echo 'DONE';
`);

test.beforeAll(cleanup);
test.afterAll(cleanup);

test('ketabkhane: upload → virayesh → logo → guard e hazf → hazf, desktop va mobile', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', error => errors.push(error.message));
    await signInAsAdmin(page);
    await page.goto('/dashboard/media');
    await expect(page.getByRole('heading', { name: 'کتابخانه‌ی رسانه', exact: true })).toBeVisible();
    await page.locator('[data-media-open-upload]').click();
    await page.locator('[data-media-upload-input]').setInputFiles({
        name: 'e2e-media-review.svg', mimeType: 'image/svg+xml',
        buffer: Buffer.from('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 80"><rect width="80" height="80" rx="20" fill="green"/></svg>'),
    });
    await page.locator('[name="media[alt]"]').fill('لوگوی تست رسانه');
    await page.locator('[data-media-upload-submit]').click();
    const row = page.getByRole('row').filter({ hasText: 'e2e-media-review.svg' });
    await expect(row).toBeVisible();
    expect(await row.getByRole('img').evaluate((el: HTMLImageElement) => el.complete && el.naturalWidth > 0)).toBe(true);
    await row.getByRole('button', { name: 'ویرایش', exact: true }).click();
    await page.locator('[name="edit[alt]"]').fill('لوگوی ویرایش‌شده');
    await page.getByRole('button', { name: 'ذخیره', exact: true }).click();
    await expect(row).toContainText('لوگوی ویرایش‌شده');
    await expect(page.getByRole('dialog', { name: 'ویرایش رسانه' })).toBeHidden();
    const button = page.locator('[data-media-open-upload]');
    expect(await button.evaluate(el => getComputedStyle(el).backgroundColor)).toBe(await tokenRgb(page, '--color-brand'));
    await page.screenshot({ path: 'test-results/media-library.png', fullPage: true });
    await page.goto('/dashboard/providers/create');
    await page.locator('[name="key"]').fill('e2e-media-review');
    await page.locator('[name="driver"]').selectOption('mock');
    await page.locator('[name="name"]').fill('E2E Media');
    await page.locator('[name="name_fa"]').fill('تست رسانه');
    await page.locator('[data-media-picker]').getByRole('button', { name: 'انتخاب از کتابخانه' }).click();
    await page.getByLabel('جستجوی رسانه').fill('e2e-media-review');
    await expect(page.locator('[data-media-library-item]')).toHaveCount(1);
    await page.locator('[data-media-library-item]').click();
    await page.locator('[data-media-library-confirm]').click();
    await expect(page.locator('[data-media-picker-alt]')).toHaveText('لوگوی ویرایش‌شده');
    await page.getByRole('button', { name: /ساخت منبع/ }).click();
    await expect(page).toHaveURL(/\/dashboard\/providers$/);
    await page.goto('/dashboard/providers/e2e-media-review/edit');
    await expect(page.locator('[data-media-picker-alt]')).toHaveText('لوگوی ویرایش‌شده');
    await page.setViewportSize({ width: 375, height: 667 });
    await expect.poll(async () => (await page.locator('aside').first().boundingBox())?.x).toBeGreaterThanOrEqual(375);
    await expectNoPageOverflow(page);
    await page.screenshot({ path: 'test-results/media-provider-mobile.png', fullPage: true });
    await page.goto('/dashboard/media?q=e2e-media-review');
    await expect(row).toContainText('1');
    await row.getByRole('button', { name: 'حذف', exact: true }).click();
    await page.getByRole('button', { name: 'تأیید', exact: true }).click();
    await expect(page.getByText(/این تصویر در/).first()).toBeVisible();
    await expect(row).toBeVisible();
    await expect(page.locator('[data-confirm]')).toBeHidden();

    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBe(0);
    await expectNoPageOverflow(page);
    await page.screenshot({ path: 'test-results/media-mobile.png', fullPage: true });
    await page.goto('/dashboard/providers/e2e-media-review/edit');
    await page.locator('[data-media-picker]').getByRole('button', { name: 'برداشتن' }).click();
    await page.getByRole('button', { name: /ذخیره/ }).click();
    await expect(page).toHaveURL(/\/dashboard\/providers$/);
    await page.goto('/dashboard/media?q=e2e-media-review');
    await row.getByRole('button', { name: 'حذف', exact: true }).click();
    await page.getByRole('button', { name: 'تأیید', exact: true }).click();
    await expect(row).toHaveCount(0);
    expect(errors).toEqual([]);
});
