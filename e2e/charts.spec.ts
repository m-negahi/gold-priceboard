import { expect, test, type Page } from '@playwright/test';
import { signInAsAdmin } from './helpers/auth';

/**
 * Chart e panel e admin (P5-08): load ba deep-link، taghir e baze (haftegi =
 * tajmi' e server-side)، khati/candle، link az matrix، va token e brand.
 *
 * Data ye vaghei ye dev (Influx) — faghat JARAYAN check mishe، na adad.
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

test.describe('admin chart', () => {
    test('safhe ba deep-link load mishe va KLineCharts canvas misaze', async () => {
        const data = page.waitForResponse((r) => r.url().includes('/dashboard/charts/data') && r.url().includes('period=h1'));
        await page.goto('/dashboard/charts?symbol=xau-18k-g-irr&period=h1');

        expect((await data).status()).toBe(200);
        await expect(page.getByRole('heading', { level: 1, name: 'نمودار قیمت' })).toBeVisible();
        await expect(page.locator('[data-admin-chart] canvas').first()).toBeVisible();
        await expect(page.getByLabel('نماد', { exact: true })).toHaveValue('xau-18k-g-irr');
    });

    test('baze ye haftegi az server miad va URL be-rooz mishe', async () => {
        await page.goto('/dashboard/charts?symbol=xau-18k-g-irr');

        const weekly = page.waitForResponse((r) => r.url().includes('/dashboard/charts/data') && r.url().includes('period=w1'));
        await page.getByRole('radio', { name: 'هفتگی' }).click();

        const res = await weekly;
        expect(res.status()).toBe(200);
        expect((await res.json()).period).toBe('w1');
        await expect(page).toHaveURL(/period=w1/);
        await expect(page.getByRole('radio', { name: 'هفتگی' })).toHaveAttribute('aria-checked', 'true');
    });

    test('dokme ye baze ye entekhab-shode rang e brand e token dare', async () => {
        await page.goto('/dashboard/charts?symbol=xau-18k-g-irr&period=d1');

        const active = page.getByRole('radio', { name: 'روزانه' });
        await expect(active).toHaveAttribute('aria-checked', 'true');

        const token = await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--color-brand-700').trim());
        const [r, g, b] = [1, 3, 5].map((i) => parseInt(token.slice(i, i + 2), 16));

        // Poll: `transition-colors` vasat e transition rgba(…, 0.03) mide.
        await expect.poll(() => active.evaluate((el) => getComputedStyle(el).backgroundColor)).toBe(`rgb(${r}, ${g}, ${b})`);
    });

    test('taghir e manba\' dobare fetch mikone', async () => {
        await page.goto('/dashboard/charts?symbol=xau-18k-g-irr&period=d1');
        await page.waitForResponse((r) => r.url().includes('/dashboard/charts/data'));

        const options = await page.getByLabel('منبع', { exact: true }).locator('option').evaluateAll((els) => els.map((e) => (e as HTMLOptionElement).value).filter((v) => v !== ''));
        test.skip(options.length === 0, 'hich provider e fa\'ali tu dev nist.');

        const refetch = page.waitForResponse((r) => r.url().includes('/dashboard/charts/data') && r.url().includes(`provider=${options[0]}`));
        await page.getByLabel('منبع', { exact: true }).selectOption(options[0]);

        expect((await refetch).status()).toBe(200);
    });

    test('link e chart az radif e matrix', async () => {
        await page.goto('/dashboard/matrix');

        // Faghat dakhel e jadval — sidebar ham link e «نمودار قیمت» dare.
        const link = page.locator('table').getByRole('link', { name: /^نمودار / }).first();
        await expect(link).toBeVisible();
        await link.click();

        await expect(page).toHaveURL(/\/dashboard\/charts\?symbol=/);
        await expect(page.getByRole('heading', { level: 1, name: 'نمودار قیمت' })).toBeVisible();
    });
});
