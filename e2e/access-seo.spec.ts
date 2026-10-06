import { expect, test, type Page } from '@playwright/test';
import { artisan, expectNoPageOverflow, signInAsAdmin, signInAsCustomer } from './helpers/auth';

/** Naghsh e movaghat; access e admin e asli va SEO e ghabli dar cleanup dast-nakhorde mimunan. */
test.describe.configure({ mode: 'serial' });
const PHONE = '09990004568';
const E164 = '+989990004568';
const ROLE = 'E2E SEO reader 4568';
let admin: Page;
let reader: Page;
let userId: number;
let seoBackup: string | undefined;

function cleanupAccess(): void {
    artisan('tinker', `--execute=$u = App\\Modules\\Auth\\Infrastructure\\Persistence\\Models\\User::where('phone', '${E164}')->first(); $ids = Illuminate\\Support\\Facades\\DB::table('access_roles')->where('name', '${ROLE}')->pluck('id'); Illuminate\\Support\\Facades\\DB::table('access_audits')->where('action', 'role_saved')->whereIn('subject_id', $ids)->delete(); if ($u) { Illuminate\\Support\\Facades\\DB::table('access_audits')->where('action', 'roles_assigned')->where('subject_id', $u->id)->delete(); $u->delete(); } Illuminate\\Support\\Facades\\DB::table('access_roles')->whereIn('id', $ids)->delete(); echo 'DONE';`);
}

test.beforeAll(async ({ browser }) => {
    cleanupAccess();
    const snapshot = artisan('tinker', `--execute=$r = Illuminate\\Support\\Facades\\DB::table('seo_meta')->where('seoable_type', 'page')->where('seoable_id', 'home')->first(); echo 'BACKUP:'.base64_encode(json_encode($r)).':DONE';`);
    seoBackup = snapshot.match(/BACKUP:([A-Za-z0-9+/=]+):DONE/)![1];
    const fixture = artisan('tinker', `--execute=$u = App\\Modules\\Auth\\Infrastructure\\Persistence\\Models\\User::create(['phone' => '${E164}', 'name' => 'مدیر موقت بررسی دسترسی']); $u->forceFill(['is_admin' => true])->save(); echo 'USER:'.$u->id.':DONE';`);
    userId = Number(fixture.match(/USER:(\d+):DONE/)![1]);
    admin = await browser.newPage();
    reader = await browser.newPage();
    await signInAsAdmin(admin);
});

test.afterAll(async () => {
    if (seoBackup) {
        artisan('tinker', `--execute=$r = json_decode(base64_decode('${seoBackup}'), true); Illuminate\\Support\\Facades\\DB::transaction(function () use ($r) { $q = Illuminate\\Support\\Facades\\DB::table('seo_meta')->where('seoable_type', 'page')->where('seoable_id', 'home'); $q->delete(); if ($r) { Illuminate\\Support\\Facades\\DB::table('seo_meta')->insert($r); } }); echo 'DONE';`);
    }
    cleanupAccess();
    await admin?.close();
    await reader?.close();
});

test('naghsh e readonly az UI takhsis migirad va write ra rad mikonad', async () => {
    await admin.goto('/dashboard/access');
    const create = admin.locator('[data-role-create]');
    await create.getByLabel('نام نقش').fill(ROLE);
    await create.locator('input[value="seo.view"]').check();
    await create.getByRole('button', { name: 'ساخت نقش' }).click();
    await expect(admin.getByText('نقش ذخیره شد.', { exact: true })).toBeVisible();
    const assignment = admin.locator(`[data-admin-access="${userId}"]`);
    await assignment.getByLabel('دسترسی کامل', { exact: true }).uncheck();
    await assignment.getByLabel(ROLE, { exact: true }).check();
    await assignment.getByRole('button', { name: 'ذخیره دسترسی' }).click();
    await expect(admin.getByText('دسترسی مدیر ذخیره شد.', { exact: true })).toBeVisible();
    await signInAsCustomer(reader, PHONE);
    await expect(reader).toHaveURL(/\/dashboard\/seo$/);
    await expect(reader.getByRole('button', { name: 'ذخیره تنظیمات' })).toHaveCount(0);
    await expect(reader.locator('[data-nav-item="access"]')).toHaveCount(0);
    const csrf = await reader.locator('input[name="_token"]').first().inputValue();
    const denied = await reader.request.put('/dashboard/seo/home', { form: { _token: csrf, title: 'غیرمجاز' } });
    expect(denied.status()).toBe(403);
    expect((await reader.request.get('/dashboard/access')).status()).toBe(403);
    await admin.setViewportSize({ width: 375, height: 667 });
    await expectNoPageOverflow(admin);
    await admin.screenshot({ path: 'test-results/access-mobile.png', fullPage: true });
});

test('SEO az UI zakhire mishavad va HTML e public escape ast', async () => {
    await admin.goto('/dashboard/seo');
    const title = 'عنوان آزمایشی سئو';
    const description = '</script><script>window.__seoInjected = true</script>';
    await admin.getByLabel('عنوان', { exact: true }).fill(title);
    await admin.getByLabel('توضیحات', { exact: true }).fill(description);
    await admin.getByRole('button', { name: 'ذخیره تنظیمات' }).click();
    await expect(admin.getByText('تنظیمات سئو ذخیره شد.', { exact: true })).toBeVisible();
    await expect(admin.getByLabel('توضیحات', { exact: true })).toHaveValue(description);
    await expectNoPageOverflow(admin);
    await admin.screenshot({ path: 'test-results/seo-mobile.png', fullPage: true });
    await admin.goto('/');
    await expect(admin).toHaveTitle(title);
    await expect(admin.locator('meta[name="description"]')).toHaveAttribute('content', description);
    expect(await admin.evaluate(() => '__seoInjected' in window)).toBe(false);
    expect(await admin.locator('script[type="application/ld+json"]').evaluate((el) => JSON.parse(el.textContent!)['@graph'])).toBeDefined();
});
