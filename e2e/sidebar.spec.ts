import { expect, test, type Page } from '@playwright/test';
import { signInAsAdmin } from './helpers/auth';

/**
 * 🔴 Tooltip e rail e BASTE e sidebar (bug e gozaresh-shode 2026-10-06):
 * tooltip `absolute` dakhel e `<nav class="overflow-x-hidden">` (va shell e
 * `overflow: hidden`) bud va HICH vaght dide nemishod. Hala yek tooltip e
 * teleport-shode be body ba `position: fixed` ast.
 *
 * Check: dide mishe، BIRUN az rail ast (samt e chap dar RTL)، kamel dakhel
 * e viewport، ham-taraz ba item، va ba khoruj e mouse penhan mishe.
 */
test.describe.configure({ mode: 'serial' });

let page: Page;

test.beforeAll(async ({ browser }) => {
    page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
    await signInAsAdmin(page);
});

test.afterAll(async () => {
    await page.close();
});

async function collapseRail(): Promise<void> {
    const aside = page.locator('aside[x-data="panelSidebar"]');

    if (((await aside.boundingBox())?.width ?? 0) > 100) {
        await page.getByRole('button', { name: 'باز و بسته کردن منو' }).click();
    }

    await expect.poll(async () => (await aside.boundingBox())?.width ?? 0).toBeLessThan(100);
}

test.describe('sidebar rail tooltip', () => {
    for (const [label, role] of [['ماتریس نمادها', 'link'], ['پایش', 'link'], ['خروج', 'button']] as const) {
        test(`«${label}»: tooltip birun az rail dide mishe`, async () => {
            await page.goto('/dashboard');
            await collapseRail();

            const target = page.getByRole(role, { name: label, exact: true });
            await target.hover();

            const tip = page.locator('[data-sidebar-tooltip]');
            await expect(tip).toBeVisible();
            await expect(tip).toHaveText(label);

            const t = (await tip.boundingBox())!;
            const item = (await target.boundingBox())!;

            // RTL: rail samt e rast — tooltip kamel samt e CHAP e item.
            expect(t.x + t.width).toBeLessThanOrEqual(item.x);
            // Kamel dakhel e viewport (clip nashode).
            expect(t.x).toBeGreaterThanOrEqual(0);
            // Ham-taraz e amudi ba item (±2px).
            expect(Math.abs((t.y + t.height / 2) - (item.y + item.height / 2))).toBeLessThanOrEqual(2);
        });
    }

    test('ba khoruj e mouse penhan mishe va tu sidebar e baz aslan nemiyad', async () => {
        await page.goto('/dashboard');
        await collapseRail();

        await page.getByRole('link', { name: 'ماتریس نمادها', exact: true }).hover();
        await expect(page.locator('[data-sidebar-tooltip]')).toBeVisible();

        await page.mouse.move(600, 450);
        await expect(page.locator('[data-sidebar-tooltip]')).toBeHidden();

        // Sidebar e baz label dare — tooltip ma'ni nadare.
        await page.getByRole('button', { name: 'باز و بسته کردن منو' }).click();
        await page.getByRole('link', { name: 'ماتریس نمادها', exact: true }).hover();
        await expect(page.locator('[data-sidebar-tooltip]')).toBeHidden();
    });
});
