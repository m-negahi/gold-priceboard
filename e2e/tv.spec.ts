import { expect, test, type Page } from '@playwright/test';

/**
 * Board e TV (P5-03): OMUMI (bedun e login)، zamine ye tire az token،
 * font e bozorg، poll e `/tv.json` + flash e sabz/ghermez، bedun e
 * overflow tu 375px، va sefr request e biruni.
 *
 * Data ye vaghei ye dev — faghat SHEKL/jarayan check mishe، na adad.
 * Hich chiz-i ro avaz nemikone (faghat khundan).
 */
type TvComponent = { etag: string | null; refresh(): Promise<void> };

async function tvReady(page: Page): Promise<void> {
    await page.waitForFunction(() => {
        const root = document.querySelector('[x-data^="tvBoard"]');
        const alpine = (window as never as { Alpine?: { $data(el: Element): { clock?: string } } }).Alpine;

        return Boolean(root && alpine && alpine.$data(root).clock);
    });
}

test.describe('tv board', () => {
    test('bedun e login bala miad ba token haye tire va font e bozorg', async ({ page }) => {
        const res = await page.goto('/tv');
        expect(res?.status()).toBe(200);
        expect(page.url()).toContain('/tv');

        const tile = page.locator('[data-tv-tile]').first();
        await expect(tile).toBeVisible();
        await expect(page.locator('[data-tv-price]').first()).toHaveText(/^\d{1,3}(,\d{3})*(\.\d+)?$/);

        // Chrome e site (dokme ye «ورود») ru TV nist.
        await expect(page.getByRole('link', { name: /ورود/ })).toHaveCount(0);

        // Token ha: body = surface-dark (#1d1d1f)، kashi = surface-dark-2 (#2a2a2d).
        const bodyBg = await page.evaluate(() => getComputedStyle(document.body).backgroundColor);
        expect(bodyBg).toBe('rgb(29, 29, 31)');
        expect(await tile.evaluate((el) => getComputedStyle(el).backgroundColor)).toBe('rgb(42, 42, 45)');

        // Font e gheymat az `--text-tv-price` (clamp ba kaf e 1.75rem = 28px).
        const size = await page.locator('[data-tv-price]').first().evaluate((el) => parseFloat(getComputedStyle(el).fontSize));
        expect(size).toBeGreaterThanOrEqual(28);

        // Noindex.
        await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', /noindex/);

        // Saat e zende (shamsi، raghame Latin).
        await tvReady(page);
        await expect(page.locator('[data-tv-clock]')).toHaveText(/^\d{2}:\d{2}:\d{2}$/);
    });

    test('poll e /tv.json flash e sabz/ghermez mizane', async ({ page }) => {
        await page.goto('/tv');
        await tvReady(page);

        const tile = page.locator('[data-tv-tile]').first();
        const symbol = await tile.getAttribute('data-tv-tile');

        // Gheymat e «ghabli» ro kamtar az vaghei mikonim -> poll e ba'di
        // (bedun e ETag) bayad flash e SABZ bezane. Hich data-i avaz nemishe.
        await page.evaluate(async (sym) => {
            const root = document.querySelector('[x-data^="tvBoard"]')!;
            const el = document.querySelector(`[data-tv-tile="${sym}"]`) as HTMLElement;
            el.dataset.value = String(Number(el.dataset.value) - 1);
            const alpine = (window as never as { Alpine: { $data(el: Element): TvComponent } }).Alpine;
            const c = alpine.$data(root);
            c.etag = null;
            await c.refresh();
        }, symbol);

        await expect(tile).toHaveClass(/tv-flash-up/);
        // 🔴 Animation VAGHE'AN ejra mishe (na class e bi-asar — hamun bug e
        // `var(--ease)` e ta'rif-nashode ke kol e animation ro `none` mikard).
        expect(await tile.evaluate((el) => getComputedStyle(el).animationName)).toBe('pb-tv-flash-up');

        await page.evaluate(async (sym) => {
            const root = document.querySelector('[x-data^="tvBoard"]')!;
            const el = document.querySelector(`[data-tv-tile="${sym}"]`) as HTMLElement;
            el.dataset.value = String(Number(el.dataset.value) + 1);
            const alpine = (window as never as { Alpine: { $data(el: Element): TvComponent } }).Alpine;
            const c = alpine.$data(root);
            c.etag = null;
            await c.refresh();
        }, symbol);

        await expect(tile).toHaveClass(/tv-flash-down/);
        expect(await tile.evaluate((el) => getComputedStyle(el).animationName)).toBe('pb-tv-flash-down');
    });

    test('/tv.json ETag dare va poll e bi-taghir 304 migire', async ({ page }) => {
        await page.goto('/tv');

        const res = await page.evaluate(async () => {
            const r = await fetch('/tv.json');
            const etag = r.headers.get('ETag');
            const r2 = await fetch('/tv.json', { headers: { 'If-None-Match': etag ?? '' } });
            return { first: r.status, etag, second: r2.status };
        });

        expect(res.first).toBe(200);
        expect(res.etag).toMatch(/^W\/"tv-/);
        expect(res.second).toBe(304);
    });

    test('375px: bedun e overflow e ofoghi', async ({ browser }) => {
        const page = await browser.newPage({ viewport: { width: 375, height: 667 } });
        await page.goto('/tv');
        await expect(page.locator('[data-tv-tile]').first()).toBeVisible();

        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);

        // Gheymat az kashi birun nemizane (font be arz e kashi baste ast).
        const spill = await page.locator('[data-tv-price]').evaluateAll((els) => els.filter((el) => el.scrollWidth > el.clientWidth + 1).length);
        expect(spill).toBe(0);
        await page.close();
    });

    test('🔴 sefr request e biruni', async ({ page }) => {
        const external: string[] = [];
        page.on('request', (req) => {
            const url = req.url();
            if (!url.startsWith('http://localhost') && !url.startsWith('data:') && !url.startsWith('blob:')) external.push(url);
        });

        await page.goto('/tv', { waitUntil: 'networkidle' });

        expect(external, `request haye biruni: ${external.join(', ')}`).toEqual([]);
    });
});
