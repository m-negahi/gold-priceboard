import { expect, test, type Page } from '@playwright/test';
import { artisan, signInAsAdmin } from './helpers/auth';

/**
 * پایش tick (P4-18): kholase، toggle e nama، filter ha، va penhan budan e
 * tick e «بدون اجرا» be soorat e default.
 *
 * Do gozaresh e DETERMINIST push mishan (yek tick ba rad e `ons` e provider e
 * `e2e`، yek tick e skipped) ta test be baz/baste budan e bazaar vabaste
 * nabashe. Daftarche gozara-st (50 ta) — entry e e2e zarar nadare.
 *
 * ⚠️ Radif/card haye filter-shode ba `x-show` PENHAN mishan، na hazf.
 */
test.describe.configure({ mode: 'serial' });

let page: Page;

test.beforeAll(async ({ browser }) => {
    const report = (extra: string) => `app(App\\Modules\\Pricing\\Domain\\Ports\\HotCachePort::class)->putTickReport([${extra}]);`;

    artisan(
        'tinker',
        '--execute=' + report(`'at' => now()->subSeconds(30)->toIso8601String(), 'skipped' => true, 'skip_reason' => "hich provider e fa'ali nobat nadasht", 'accepted' => 0, 'rejected' => 0, 'duration_ms' => 0, 'providers' => [], 'sinks' => [], 'rejections' => [], 'unmapped' => []`)
            + report(`'at' => now()->toIso8601String(), 'skipped' => false, 'skip_reason' => null, 'accepted' => 5, 'rejected' => 1, 'duration_ms' => 900, 'providers' => ['e2e' => ['succeeded' => true, 'latency_ms' => 900, 'symbol_count' => 6, 'retried' => false, 'error' => null]], 'sinks' => [], 'rejections' => [['provider' => 'e2e', 'symbol' => 'e2e-ons', 'side' => 'last', 'reason' => 'stale:90000s', 'category' => 'stale', 'minor' => 41000000]], 'unmapped' => []`)
            + " echo 'DONE';",
    );

    page = await browser.newPage();
    await signInAsAdmin(page);
});

test.afterAll(async () => {
    await page.close();
});

test.describe('tick monitor', () => {
    test('kholase ye per-provider dide mishe', async () => {
        await page.goto('/dashboard/monitor');

        await expect(page.getByRole('heading', { level: 1, name: 'پایش زنده' })).toBeVisible();
        await expect(page.locator('[data-tick-summary="e2e"]')).toBeVisible();
    });

    test('tick e «بدون اجرا» be soorat e default penhan-e va ba toggle dide mishe', async () => {
        await page.goto('/dashboard/monitor');

        const skipped = page.locator('[data-tick-report]').filter({ hasText: 'بدون اجرا' });
        await expect(skipped.filter({ visible: true })).toHaveCount(0);

        await page.getByLabel(/نمایش tick های بدون اجرا/).check();
        await expect(skipped.filter({ visible: true }).first()).toBeVisible();
    });

    test('nama ye «جدول ردها» + filter e symbol', async () => {
        await page.goto('/dashboard/monitor');

        await page.getByRole('radio', { name: /جدول ردها/ }).click();

        const rows = page.locator('table tbody tr').filter({ visible: true });
        await expect(rows.filter({ hasText: 'e2e-ons' }).first()).toBeVisible();

        await page.getByLabel('نماد:').selectOption('e2e-ons');
        // Khod-retry: hich radif e dide-shode-i ba symbol e digar nist.
        await expect(rows.filter({ hasNotText: 'e2e-ons' })).toHaveCount(0);
    });

    test('filter e provider timeline ro mahdud mikone', async () => {
        await page.goto('/dashboard/monitor');

        await page.getByRole('group', { name: 'فیلتر منبع' }).getByRole('button', { name: 'e2e', exact: true }).click();

        // Faghat card haye TIMELINE — kart haye kholase amdan filter nemishan.
        const cards = page.locator('[data-tick-report]').filter({ visible: true });
        await expect(cards.first()).toBeVisible();
        // Har card e tick e dide-shode pill e provider e e2e ro dare.
        await expect(cards.filter({ hasNotText: 'e2e' })).toHaveCount(0);
    });
});
