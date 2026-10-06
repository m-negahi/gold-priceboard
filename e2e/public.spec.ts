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

    /**
     * 🔴 Flash e tablo VAGHE'AN animate mishe: ghablan `.flash-up` ba
     * `var(--ease)` e ta'rif-nashode `animation: none` mishod va hichvaght
     * dide nemishod (P5-03). Fagat khundan — hich data-i avaz nemishe.
     */
    test('poll e board flash e sabz mizane (animation zende)', async ({ page }) => {
        await page.goto('/');
        await page.waitForFunction(() => {
            const root = document.querySelector('[x-data^="board("]');
            const alpine = (window as never as { Alpine?: { $data(el: Element): { lastSell?: object } } }).Alpine;
            return Boolean(root && alpine && alpine.$data(root).lastSell);
        });

        const cell = page.locator('[data-cell]').first();
        const key = await cell.getAttribute('data-cell');

        await page.evaluate(async (k) => {
            type Board = { etag: string | null; lastSell: Record<string, number>; refresh(): Promise<void> };
            const root = document.querySelector('[x-data^="board("]')!;
            const c = (window as never as { Alpine: { $data(el: Element): Board } }).Alpine.$data(root);
            const r = await fetch('/board.json');
            const data = await r.json();
            const [symbol, provider] = (k as string).split(':');
            const row = data.rows.find((x: { symbol: string }) => x.symbol === symbol);
            c.lastSell[k as string] = row.cells[provider].sell - 1;
            c.etag = null;
            await c.refresh();
        }, key);

        await expect(cell).toHaveClass(/flash-up/);
        expect(await cell.evaluate((el) => getComputedStyle(el).animationName)).toBe('pb-flash-up');
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
