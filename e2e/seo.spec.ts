import { expect, test } from '@playwright/test';

/**
 * SEO e site e omumi (P5-05): JSON-LD ru safhe ye asli (WebSite + Product/
 * AggregateOffer + FAQPage)، FAQ e ru safhe ba HAMUN soal ha، canonical/OG،
 * sitemap index va `robots.txt` e dynamic.
 *
 * Data ye vaghei ye dev — faghat shekl check mishe. Hich chiz avaz nemishe.
 */
type Node = { '@type': string; [key: string]: unknown };

test.describe('seo', () => {
    test('JSON-LD e safhe ye asli parse mishe va FAQ ba safhe yeki-e', async ({ page }) => {
        await page.goto('/');

        const scripts = page.locator('script[type="application/ld+json"]');
        await expect(scripts).toHaveCount(1);

        const ld = JSON.parse((await scripts.textContent()) ?? '{}') as { '@context': string; '@graph': Node[] };
        expect(ld['@context']).toBe('https://schema.org');

        const types = ld['@graph'].map((n) => n['@type']);
        expect(types).toContain('WebSite');
        expect(types).toContain('FAQPage');

        // Worker zende-ast -> hadeaghal yek Product ba AggregateOffer e reshte-i.
        const product = ld['@graph'].find((n) => n['@type'] === 'Product') as Node & { offers: Record<string, unknown> };
        expect(product).toBeTruthy();
        expect(product.offers['@type']).toBe('AggregateOffer');
        expect(String(product.offers.lowPrice)).toMatch(/^\d+(\.\d+)?$/);
        expect(['IRR', 'USD']).toContain(product.offers.priceCurrency);

        // FAQ e JSON-LD == FAQ e DIDE-shode ru safhe (shart e Google).
        const faq = ld['@graph'].find((n) => n['@type'] === 'FAQPage') as Node & { mainEntity: Array<{ name: string }> };
        const visible = page.locator('[data-faq] summary');
        await expect(visible).toHaveCount(faq.mainEntity.length);
        for (const [i, q] of faq.mainEntity.entries()) {
            await expect(visible.nth(i)).toHaveText(q.name);
        }

        // Canonical + OG.
        await expect(page.locator('link[rel="canonical"]')).toHaveAttribute('href', /localhost:8300/);
        await expect(page.locator('meta[property="og:locale"]')).toHaveAttribute('content', 'fa_IR');
    });

    test('FAQ baz/baste mishe va token e surface resolve shode', async ({ page }) => {
        await page.goto('/');

        const first = page.locator('[data-faq] details').first();
        await first.locator('summary').click();
        await expect(first).toHaveAttribute('open', '');
        await expect(first.locator('p')).toBeVisible();

        // `bg-surface` az `@theme` (#ffffff).
        const bg = await page.locator('[data-faq]').evaluate((el) => getComputedStyle(el).backgroundColor);
        expect(bg).toBe('rgb(255, 255, 255)');
    });

    test('sitemap index + sitemap e safhe ha XML-and', async ({ request }) => {
        const index = await request.get('/sitemap.xml');
        expect(index.status()).toBe(200);
        expect(index.headers()['content-type']).toContain('application/xml');
        const body = await index.text();
        expect(body).toContain('<sitemapindex');
        expect(body).toContain('/sitemaps/pages.xml');

        const pages = await (await request.get('/sitemaps/pages.xml')).text();
        expect(pages).toContain('<urlset');
        expect(pages).toContain('<loc>http://localhost:8300</loc>');
        expect(pages).not.toContain('/tv');
    });

    test('robots.txt dynamic-e (dev = Disallow kol)', async ({ request }) => {
        const res = await request.get('/robots.txt');
        expect(res.status()).toBe(200);
        expect(res.headers()['content-type']).toContain('text/plain');
        // Dev production nist -> kol e site bas-te.
        expect(await res.text()).toBe('User-agent: *\nDisallow: /\n');
    });

    test('375px: safhe ye asli ba FAQ overflow e ofoghi nadare', async ({ browser }) => {
        const page = await browser.newPage({ viewport: { width: 375, height: 667 } });
        await page.goto('/');
        await expect(page.locator('[data-faq]')).toBeVisible();

        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await page.close();
    });
});
