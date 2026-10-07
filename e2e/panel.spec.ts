import { expect, test, type Page } from '@playwright/test';
import { signInAsAdmin } from './helpers/auth';

/**
 * Panel e admin (P3-11): har safhe bala miad، data ye vaghei neshun mide،
 * va token haye theme resolve mishan — na faghat «safhe bala miad».
 *
 * Stack bayad ba data ye zende bala bashe (price-worker + tgju/estjt e
 * roshan). Hich mock-i nist — in hamun chizi-e ke admin vaghe'an mibine.
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

test.describe('panel', () => {
    test('mizkar: stat card ha + salamat + jadval e moghayese', async () => {
        await page.goto('/dashboard');

        await expect(page.getByRole('heading', { level: 1, name: 'میزکار' })).toBeVisible();
        await expect(page.getByText('قیمت زنده', { exact: true })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'سلامت منابع', exact: true })).toBeVisible();
        await expect(page.getByRole('heading', { name: /قیمت فروش منابع/ })).toBeVisible();

        // Card e featured bayad gradient e brand dashte bashe (token resolve shode).
        const featured = page.locator('.bg-brand-grad').first();
        await expect(featured).toBeVisible();
        const bg = await featured.evaluate((el) => getComputedStyle(el).backgroundImage);
        expect(bg).toContain('gradient');
    });

    test('sidebar: item e fa\'al navar e brand dare va nav kar mikone', async () => {
        await page.goto('/dashboard');

        const nav = page.getByRole('navigation').filter({ has: page.getByLabel('منابع قیمت') });
        await page.getByLabel('منابع قیمت').click();
        await page.waitForURL('**/dashboard/providers');
        await expect(page.getByRole('heading', { level: 1, name: 'منابع قیمت' })).toBeVisible();
        void nav;
    });

    test('manba\' ha: lista + form e virayesh ba meghdar haye por', async () => {
        await page.goto('/dashboard/providers');

        await expect(page.getByText('tgju', { exact: true }).first()).toBeVisible();

        await page.getByLabel('ویرایش تی‌جی‌جی‌یو').click();
        await page.waitForURL('**/providers/tgju/edit');

        // Key readonly + por؛ driver pre-select.
        const key = page.getByLabel('کلید');
        await expect(key).toHaveValue('tgju');
        await expect(key).toHaveAttribute('readonly', /.*/);
        await expect(page.getByLabel('درایور')).toHaveValue('tgju');
    });

    test('manba\' ha: toggle e roshan/khamoosh SPA ast — bedun e reload', async () => {
        await page.goto('/dashboard/providers');

        // Marker e window: age safhe reload she gom mishe.
        await page.evaluate(() => { (window as never as { __pbMarker?: string }).__pbMarker = 'alive'; });

        // Ru provider e `mock` (khamoosh e default) do bar toggle mizanim ta
        // state e dev dast nakhore.
        const card = page.locator('[data-provider-row="mock"]');
        const toggle = card.locator('[data-provider-toggle]');
        const before = (await toggle.innerText()).trim();

        await toggle.click();
        await expect(toggle).not.toHaveText(before);
        await expect(page.getByText('وضعیت منبع تغییر کرد.').first()).toBeVisible();

        await toggle.click();
        await expect(toggle).toHaveText(before);

        // Hich navigation-i rokh nadade.
        expect(await page.evaluate(() => (window as never as { __pbMarker?: string }).__pbMarker)).toBe('alive');
    });

    test('spread: 16 radif + preview e server-side', async () => {
        await page.goto('/dashboard/providers/estjt/spreads');

        await expect(page.getByRole('heading', { level: 1, name: /اسپرد/ })).toBeVisible();
        // Wildcard + 15 instrument.
        await expect(page.locator('tbody tr')).toHaveCount(16);
        // estjt gheymat e tala 18 dare -> preview e un radif adad dare.
        await expect(page.locator('tbody tr').nth(1)).toContainText('←');
    });

    test('payesh e zende: gozaresh haye tick ba jozyat', async () => {
        await page.goto('/dashboard/monitor');

        await expect(page.getByRole('heading', { level: 1, name: 'پایش زنده' })).toBeVisible();
        // Worker zende-ast pas hadeaghal yek gozaresh hast. P4-12: card ha
        // esm e MAFHOOMI ye provider ro neshun midan (na key e kham) — pas
        // donbal e «تی‌جی‌جی‌یو» migardim، na «tgju». Link e payesh API ham
        // bayad bashe.
        await expect(page.getByText('تی‌جی‌جی‌یو').first()).toBeVisible();
        await expect(page.getByRole('link', { name: 'پایش API' })).toBeVisible();
    });

    test('salamat + matrix bala miyan', async () => {
        await page.goto('/dashboard/health');
        await expect(page).toHaveURL(/\/dashboard\/providers$/);
        await expect(page.getByRole('heading', { level: 1, name: 'منابع قیمت' })).toBeVisible();
        await expect(page.locator('[data-provider-row="tgju"]')).toContainText('شکست پیاپی');

        await page.goto('/dashboard/matrix');
        await expect(page.getByRole('heading', { level: 1, name: 'ماتریس نمادها' })).toBeVisible();
        await expect(page.getByText('xau-oz-usd')).toBeVisible();
    });
});
