import { expect, test } from '@playwright/test';

/**
 * Safhe ye instrument (`/price/{symbol}`، P10-13) ru data ye VAGHEI ye dev:
 * SSR e toman، jadval e manba' ha، chart e inline (KLineCharts)، JSON-LD،
 * link az tablo، 375px bedun e overflow.
 */
test.describe('instrument page', () => {
    test('az tablo be safhe ye instrument', async ({ page }) => {
        await page.goto('/');
        await page.locator('[data-instrument-link="xau-18k-g-irr"]').click();

        await expect(page).toHaveURL(/\/price\/xau-18k-g-irr$/);
        await expect(page.getByRole('heading', { level: 1 })).toContainText('قیمت طلای 18 عیار');
    });

    test('jadval e manba\' ha + amar + chart + related', async ({ page }) => {
        await page.goto('/price/xau-18k-g-irr');

        const rows = page.locator('[data-provider-row]');
        test.skip((await rows.count()) === 0, 'hich gheymati ru dev nist.');

        await expect(rows.first().locator('[data-price]')).not.toHaveText('');
        await expect(page.locator('[data-stat="min"]')).not.toHaveText('');
        await expect(page.locator('[data-stat="avg"]')).not.toHaveText('');

        // Chart: KLineCharts canvas bedun e modal.
        await expect(page.locator('[data-instrument-chart] canvas').first()).toBeVisible();
        await page.getByRole('radio', { name: '1 ساعت' }).click();
        await expect(page.getByRole('radio', { name: '1 ساعت' })).toHaveAttribute('aria-checked', 'true');

        // Related + canonical.
        await expect(page.locator('[data-related] a').first()).toHaveAttribute('href', /\/price\//);
        await expect(page.locator('link[rel="canonical"]')).toHaveAttribute('href', /\/price\/xau-18k-g-irr$/);

        // Toman e tablo: footnote.
        await expect(page.getByText('قیمت‌ها به تومان است.')).toBeVisible();
    });

    test('JSON-LD: Breadcrumb + Product', async ({ page }) => {
        await page.goto('/price/xau-18k-g-irr');

        const raw = await page.locator('script[type="application/ld+json"]').first().textContent();
        const ld = JSON.parse(raw ?? '{}');
        const nodes: Array<Record<string, unknown>> = ld['@graph'] ?? [ld];
        const types = nodes.map((n) => n['@type']);

        expect(types).toContain('BreadcrumbList');
        if (types.includes('Product')) {
            const product = nodes.find((n) => n['@type'] === 'Product') as Record<string, any>;
            expect(product.sku).toBe('xau-18k-g-irr');
            expect(product.offers['@type']).toBe('AggregateOffer');
        }
    });

    test('375px bedun e overflow e ofoghi', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 800 });
        await page.goto('/price/xau-coin-emami-irr');
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();

        const { scrollWidth, clientWidth } = await page.evaluate(() => ({
            scrollWidth: document.documentElement.scrollWidth,
            clientWidth: document.documentElement.clientWidth,
        }));
        expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 1);
    });

    test('symbol e nashenas 404', async ({ page }) => {
        const res = await page.goto('/price/nist');
        expect(res?.status()).toBe(404);
    });
});
