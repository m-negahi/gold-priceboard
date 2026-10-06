import { expect, test, type Page } from '@playwright/test';
import { artisan, signInAsAdmin } from './helpers/auth';

/**
 * Upstream inspector (P4-14/P4-18/P4-20): call haye MA be provider ha —
 * kholase، filter ha، jozyat e Telescope-vaar، va token haye theme.
 *
 * Yek entry e shekast-khorde ye DETERMINIST (ba id e sabet) ba tinker push
 * mishe ta test be hale vaghei ye provider ha vabaste nabashe. (Daftarche
 * gozara-st va trim mishe — entry e e2e zarar nadare.)
 *
 * ⚠️ Radif haye filter-shode ba `x-show` PENHAN mishan، na hazf — pas har
 * shomaresh bayad `visible: true` bashe.
 */
test.describe.configure({ mode: 'serial' });

const FAIL_URL = 'https://e2e-fail.example/prices';
const FAIL_ID = 'e2e0fail0001';

let page: Page;

test.beforeAll(async ({ browser }) => {
    artisan(
        'tinker',
        `--execute=app(App\\Modules\\Pricing\\Domain\\Ports\\UpstreamLogPort::class)->push(new App\\Modules\\Pricing\\Application\\Dto\\UpstreamCallDto(providerKey: 'e2e', succeeded: false, at: Carbon\\CarbonImmutable::now(), method: 'GET', url: '${FAIL_URL}', status: null, latencyMs: 1234, attempt: 2, error: 'cURL error 7: e2e network down', symbolCount: 0, id: '${FAIL_ID}', requestHeaders: ['User-Agent' => 'PriceBoard-e2e', 'Authorization' => '[redacted]'], responseBody: '{"error":"e2e payload"}')); echo 'DONE';`,
    );

    page = await browser.newPage();
    await signInAsAdmin(page);
});

test.afterAll(async () => {
    await page.close();
});

const visibleRows = () => page.locator('tbody tr').filter({ visible: true });

test.describe('upstream monitor', () => {
    test('jadval ba entry e shekast-khorde + status e «قطع»', async () => {
        await page.goto('/dashboard/monitor/upstream');

        await expect(page.getByRole('heading', { level: 1, name: 'پایش منابع' })).toBeVisible();

        const failRow = page.locator('tbody tr').filter({ hasText: 'e2e-fail.example' }).first();
        await expect(failRow).toBeVisible();
        // Khata ye ettesal status e HTTP nadare — «قطع»، na yek adad e sakhtegi.
        await expect(failRow.getByText('قطع')).toBeVisible();
        await expect(failRow.getByText('cURL error 7: e2e network down')).toBeVisible();
        // Retry neshun dade mishe (talash e 2).
        await expect(failRow.getByText('↻ 2')).toBeVisible();
    });

    test('kholase ye per-provider va khata haye por-tekrar', async () => {
        await page.goto('/dashboard/monitor/upstream');

        const card = page.locator('[data-upstream-summary="e2e"]');
        await expect(card).toBeVisible();
        await expect(card.getByText('مختل')).toBeVisible();

        await expect(page.getByRole('heading', { name: 'خطاهای پرتکرار' })).toBeVisible();
    });

    test('filter e «خطا» faghat radif haye nakam ro negah midare', async () => {
        await page.goto('/dashboard/monitor/upstream');

        await page.getByRole('radio', { name: 'خطا' }).click();

        await expect(visibleRows().filter({ hasText: 'e2e-fail.example' }).first()).toBeVisible();
        // HAME ye radif haye DIDE-shode bayad nakam bashan — pill e 200 nabayad dide she.
        await expect(page.locator('tbody').getByText('200', { exact: true }).filter({ visible: true })).toHaveCount(0);
    });

    test('filter e provider faghat hamun manba ro neshun mide', async () => {
        await page.goto('/dashboard/monitor/upstream');

        await page.getByRole('group', { name: 'فیلتر منبع' }).getByRole('button', { name: /^e2e/ }).click();

        await expect(visibleRows().first()).toContainText('e2e');
        // Khod-retry: «hich radif e DIDE-shode-i bedun e e2e nist». (Halghe ru
        // `count()` race dasht — shomaresh ghabl az penhan shodan gerefte mishod.)
        await expect(visibleRows().filter({ hasNotText: 'e2e' })).toHaveCount(0);
    });

    test('search tu matn e khata', async () => {
        await page.goto('/dashboard/monitor/upstream');

        await page.getByLabel('جستجو').fill('e2e network');

        await expect(visibleRows().first()).toContainText('e2e network down');
        await expect(visibleRows().filter({ hasNotText: 'e2e network' })).toHaveCount(0);
    });

    test('jozyat e call: request header (redact) + payload e pasokh', async () => {
        await page.goto('/dashboard/monitor/upstream');

        await page.locator('tbody tr').filter({ hasText: 'e2e-fail.example' }).first()
            .getByRole('link', { name: /^جزئیات/ }).click();
        await page.waitForURL(`**/dashboard/monitor/upstream/${FAIL_ID}`);

        await expect(page.getByText('PriceBoard-e2e')).toBeVisible();
        await expect(page.getByText('[redacted]')).toBeVisible();
        await expect(page.getByText('"error": "e2e payload"')).toBeVisible();
    });

    test('id e monghazi 404 e sadegh mide', async () => {
        const response = await page.goto('/dashboard/monitor/upstream/nist0000');

        expect(response?.status()).toBe(404);
        await expect(page.getByText('از دفترچه خارج شده')).toBeVisible();
    });

    test('token haye theme resolve shodan (rang e khata az --color-down)', async () => {
        await page.goto('/dashboard/monitor/upstream');

        const error = page.locator('tbody').getByText('cURL error 7: e2e network down').first();
        await expect(error).toBeVisible();

        const { token, color } = await error.evaluate((el) => ({
            token: getComputedStyle(document.documentElement).getPropertyValue('--color-down').trim(),
            color: getComputedStyle(el).color,
        }));

        expect(token).not.toBe('');

        // #rrggbb -> rgb(r, g, b) ta ba computed style moghayese she.
        const [r, g, b] = [1, 3, 5].map((i) => parseInt(token.slice(i, i + 2), 16));
        expect(color).toBe(`rgb(${r}, ${g}, ${b})`);
    });
});
