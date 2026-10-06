import { expect, test } from '@playwright/test';

/**
 * Tablo ye omumi (P5-06): bala miad، data neshun mide، poll mikone، va
 * 🔴 SEFR request e biruni mizane (ghanun e proje: hich CDN/font/analytics
 * e kharej — TradingView ru host block ast va font local).
 */
test.describe('public board', () => {
    test('tablo ba token ha va data ye vaghei bala miad', async ({ page }) => {
        await page.goto('/');

        await expect(page.getByRole('heading', { name: /قیمت لحظه‌ای طلا/ })).toBeVisible();
        // Hadeaghal yek gheymat (worker zende-ast).
        await expect(page.locator('[data-price]').first()).toBeVisible();
        // Soton haye moghayese.
        await expect(page.getByText('کمترین').first()).toBeVisible();
        await expect(page.getByText('بیشترین').first()).toBeVisible();

        // Token haye theme resolve mishan (brand e sabz).
        const header = page.locator('header').first();
        const bg = await header.evaluate((el) => getComputedStyle(el).backgroundColor);
        expect(bg).not.toBe('rgba(0, 0, 0, 0)');
    });

    test('poll e /board.json kar mikone va ETag dare', async ({ page }) => {
        await page.goto('/');

        const res = await page.evaluate(async () => {
            const r = await fetch('/board.json');
            const etag = r.headers.get('ETag');
            // Poll e dovom ba hamun ETag bayad 304 bede.
            const r2 = await fetch('/board.json', { headers: { 'If-None-Match': etag ?? '' } });
            return { first: r.status, etag, second: r2.status };
        });

        expect(res.first).toBe(200);
        expect(res.etag).toBeTruthy();
        expect(res.second).toBe(304);
    });

    test('🔴 sefr request e biruni (hich CDN/font/analytics)', async ({ page }) => {
        const external: string[] = [];

        page.on('request', (req) => {
            const url = req.url();
            if (!url.startsWith('http://localhost') && !url.startsWith('data:') && !url.startsWith('blob:')) {
                external.push(url);
            }
        });

        await page.goto('/', { waitUntil: 'networkidle' });

        expect(external, `request haye biruni: ${external.join(', ')}`).toEqual([]);
    });
});
