import { expect, test, type Page } from '@playwright/test';
import { signInAsAdmin } from './helpers/auth';

/**
 * Safhe ye «امور مالی» (2026-10-06): 6 tab، bazeh ye gozaresh e mahane ba
 * pill + date picker e Jalali، jadval e mahane ba radif e jam'، form e sharj
 * e kif pool ba input e comma-dar. Hich tarakoneshi SABT nemishe (data ye
 * vaghei ye dev).
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

test.describe('finance page', () => {
    test('6 tab dare va tab e darkhasti az query baz mishe', async () => {
        await page.goto('/dashboard/finance?tab=invoices');

        const tabs = page.getByRole('tab');
        await expect(tabs).toHaveCount(6);
        await expect(tabs.filter({ hasText: 'فاکتورها' })).toHaveAttribute('aria-selected', 'true');
        await expect(page.locator('[data-invoices]')).toBeVisible();
        await expect(page.locator('[data-monthly-report]')).toBeHidden();
    });

    test('gozaresh e mahane: pill haye bazeh، date picker e Jalali، radif e jam', async () => {
        await page.goto('/dashboard/finance?tab=report&period=3m');

        await expect(page.locator('[data-period="3m"]')).toHaveClass(/border-brand/);
        await expect(page.locator('[data-monthly-report] tbody tr')).toHaveCount(3);
        await expect(page.locator('[data-report-totals]')).toContainText('جمع');

        // Date picker e kit (na `type=date`): hidden input ISO.
        const period = page.locator('[data-report-period]');
        await expect(period.locator('[data-jalali-date]')).toHaveCount(2);
        await expect(period.locator('input[type="date"]')).toHaveCount(0);

        // Rang e pill e fa'al az token e brand miad.
        const brand = await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--color-brand').trim());
        expect(brand).not.toBe('');
    });

    test('tab e sharj: input e mablagh ba jodakonande ye hezargan', async () => {
        await page.goto('/dashboard/finance?tab=wallet');

        const form = page.locator('[data-wallet-adjust]');
        await expect(form).toBeVisible();

        const amount = form.locator('input[name="amount"]');
        await amount.fill('');
        await amount.pressSequentially('۱۲۵۰۰۰۰');
        await expect(amount).toHaveValue('1,250,000');
        // Hich submit-i — data ye dev.
    });

    test('tab e tarakonesh ha filter e Jalali dare va CSV link e bazeh ro negah midare', async () => {
        await page.goto('/dashboard/finance?tab=ledger&from=2026-09-23&to=2026-10-22');

        const panel = page.getByRole('tabpanel');
        await expect(panel.locator('[data-jalali-date]')).toHaveCount(2);
        const csv = page.getByRole('link', { name: 'خروجی CSV' });
        await expect(csv).toHaveAttribute('href', /from=2026-09-23/);
    });
});
