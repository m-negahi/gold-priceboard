import { expect, test, type Page } from '@playwright/test';
import { artisan, signInAsAdmin } from './helpers/auth';

/**
 * Upstream inspector (P4-14): call haye MA be provider ha — jadval، filter e
 * provider/faghat-khata، va token haye theme (na faghat «safhe bala miad»).
 *
 * Yek entry e shekast-khorde ye DETERMINIST ba tinker push mishe ta filter e
 * «faghat khataha» bedun e vabastegi be hale vaghei ye provider ha test she.
 * (Daftarche gozara-st va trim mishe — entry e e2e zarar nadare.)
 */
test.describe.configure({ mode: 'serial' });

const FAIL_URL = 'https://e2e-fail.example/prices';

let page: Page;

test.beforeAll(async ({ browser }) => {
    artisan(
        'tinker',
        `--execute=app(App\\Modules\\Pricing\\Domain\\Ports\\UpstreamLogPort::class)->push(new App\\Modules\\Pricing\\Application\\Dto\\UpstreamCallDto(providerKey: 'e2e', succeeded: false, at: Carbon\\CarbonImmutable::now(), method: 'GET', url: '${FAIL_URL}', status: null, latencyMs: 1234, attempt: 2, error: 'cURL error 7: e2e network down', symbolCount: 0)); echo 'DONE';`,
    );

    page = await browser.newPage();
    await signInAsAdmin(page);
});

test.afterAll(async () => {
    await page.close();
});

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

    test('filter e «faghat khataha» faghat radif haye nakam ro negah midare', async () => {
        await page.goto('/dashboard/monitor/upstream');

        await page.getByLabel('فقط خطاها').check();

        const rows = page.locator('tbody tr');
        await expect(rows.filter({ hasText: 'e2e-fail.example' }).first()).toBeVisible();

        // HAME ye radif haye baghi-mande bayad nakam bashan (pill e sabz e
        // 200 nabayad dide she) — in hamun «filter vaghe'an filter mikone» ast.
        await expect(page.locator('tbody').getByText('200', { exact: true })).toHaveCount(0);

        await page.getByLabel('فقط خطاها').uncheck();
    });

    test('filter e provider faghat hamun manba ro neshun mide', async () => {
        await page.goto('/dashboard/monitor/upstream');

        await page.getByLabel('منبع:').selectOption('e2e');

        const rows = page.locator('tbody tr');
        await expect(rows.first()).toBeVisible();
        const count = await rows.count();

        for (let i = 0; i < count; i++) {
            await expect(rows.nth(i)).toContainText('e2e');
        }
    });

    test('token haye theme resolve shodan (rang e khata az --color-down)', async () => {
        await page.goto('/dashboard/monitor/upstream');

        const error = page.getByText('cURL error 7: e2e network down').first();
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
