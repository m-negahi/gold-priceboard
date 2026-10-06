import { expect, test, type Page } from '@playwright/test';
import { ADMIN, resetOtp } from './helpers/auth';

/**
 * Kol e dar e vorud tu BROWSER e vaghei: shomare type mishe, code miad,
 * auto-fill mizane, be mizkar miresim. (Adapt az noskhe ye ghabli —
 * maghsad e admin inja `/dashboard` ast.)
 *
 * Stack ba `BYPASS_OTP=true` bala-st: code-i ke «SMS shod» ru safhe ham
 * hast ba dokme ye por-kardan e khodkar. Hich endpoint e test-only tu app
 * nist — rate limit ha KAMELAN armed-and va har test aval vaziat e OTP e
 * khodesh ro ba `otp:flush` (local-only) pak mikone.
 */
test.describe.configure({ mode: 'serial' });

async function requestCode(page: Page, phone = ADMIN) {
    await page.goto('/login');
    await page.getByLabel('شماره موبایل').fill('0' + phone);
    await page.getByRole('button', { name: 'دریافت کد ورود' }).click();
    await expect(page.getByRole('heading', { name: 'کد ورود' })).toBeVisible();
}

test.describe('login', () => {
    test.beforeEach(() => resetOtp(ADMIN));

    test('step e shomare: focus, normalize ye raghame farsi, dokme ye hushyar', async ({ page }) => {
        await page.goto('/login');

        await expect(page.getByRole('heading', { name: 'ورود به حساب' })).toBeVisible();
        await expect(page.getByLabel('شماره موبایل')).toBeFocused();
        // Dokme montazer e yek shomare ye ma'ghul e.
        await expect(page.getByRole('button', { name: 'دریافت کد ورود' })).toBeDisabled();

        // Raghame FARSI type mishe — bayad English beshine (kol e zanjire ye
        // normalize, az client ta server).
        await page.getByLabel('شماره موبایل').fill('۰۹۱۲۸۴۵۲۴۲۷');
        await expect(page.getByLabel('شماره موبایل')).toHaveValue('09128452427');
        await expect(page.getByRole('button', { name: 'دریافت کد ورود' })).toBeEnabled();
    });

    test('bypass code ro neshun mide va auto-fill admin ro vared e mizkar mikone', async ({ page }) => {
        await requestCode(page);

        await expect(page.getByText('حالت آزمایشی')).toBeVisible();
        await page.getByRole('button', { name: 'پر کردن خودکار' }).click();

        await page.waitForURL('**/dashboard');
        // Mizkar e VAGHEI (P3-05) — h1 e safhe.
        await expect(page.getByRole('heading', { level: 1, name: 'میزکار' })).toBeVisible();
    });

    test('refresh e vasat e flow be step e code bar migarde — shomarande az server', async ({ page }) => {
        await requestCode(page);

        await page.reload();

        // Server step ro tasmim migire: bedun e dobare ferestadan e shomare.
        await expect(page.getByRole('heading', { name: 'کد ورود' })).toBeVisible();
        await expect(page.getByText('حالت آزمایشی')).toBeVisible();
    });

    test('code e ghalat khata ye rushan mide va karbar birun mimune', async ({ page }) => {
        await requestCode(page);

        for (let box = 1; box <= 6; box++) {
            await page.getByLabel(`رقم ${box}`).fill('0');
        }

        // Payam 2 ja render mishe (hint + region e aria-live e penhan) —
        // faghat un-i ke vaghean DIDE mishe mohem e.
        await expect(page.getByText(/کد نادرست است/).filter({ visible: true }).first()).toBeVisible();
        await expect(page).toHaveURL(/\/login/);
    });
});

/**
 * 🔴 Token haye ENTERPRISE e theme — na faghat «safhe bala miad».
 *
 * Hame ye rang/andaze az `@theme` e `app.css` miad (ghanun e proje: hich
 * hex tu Blade). In test sabet mikone token ha vaghean RESOLVE mishan:
 * age yek token hazf/typo she, computed style e element ha transparent ya
 * default mishe va hamin ja mishkane — na tu cheshm e avvalin karbar.
 */
test.describe('design tokens', () => {
    /**
     * 🔴 Bug e vaghei: code tu YEK browser sakhte shod، browser e DOVOM ru
     * hamun shomare 429 gereft va ru step e code bedun e code va bedun e
     * tozih gir kard. Store faghat hash dare، pas code e ghadimi reveal
     * NEMISHE — vali UI bayad bege chera va chi kar kone.
     */
    test('code e session e digar: tozih e bypass be jaye safhe ye khali', async ({ browser }) => {
        // In describe `beforeEach(resetOtp)` nadare — state e test e ghabli
        // vagarna browser e AVAL ro ham 429 mikone.
        resetOtp(ADMIN);

        const first = await browser.newContext();
        const second = await browser.newContext();

        try {
            // Browser e aval code migire (kart e bypass ba code).
            const a = await first.newPage();
            await requestCode(a);
            await expect(a.locator('[data-otp-bypass]')).toBeVisible();

            // Browser e dovom (cookie/session e joda) ru hamun shomare -> 429.
            const b = await second.newPage();
            await requestCode(b);

            // Code e ghadimi reveal NEMISHE…
            await expect(b.locator('[data-otp-bypass]')).toBeHidden();
            // …vali tozih e «ارسال دوباره» dide mishe، na safhe ye khali.
            await expect(b.locator('[data-otp-bypass-pending]')).toBeVisible();
            await expect(b.locator('[data-otp-bypass-pending]')).toContainText('ارسال دوباره');
        } finally {
            await first.close();
            await second.close();
        }
    });

    test('token haye brand ru dokme ye asli resolve mishan', async ({ page }) => {
        await page.goto('/login');

        const button = page.getByRole('button', { name: 'دریافت کد ورود' });
        const styles = await button.evaluate((el) => {
            const c = getComputedStyle(el);
            return { bg: c.backgroundColor, color: c.color, radius: c.borderRadius, font: c.fontFamily };
        });

        // bg-brand bayad yek rang e VAGHEI bashe (token resolve shode).
        expect(styles.bg).toMatch(/^(rgb|okl(ch|ab)|color)/);
        expect(styles.bg).not.toBe('rgba(0, 0, 0, 0)');
        expect(styles.color).not.toBe(styles.bg);
        // rounded-xl != 0px — scale e radius e theme.
        expect(parseFloat(styles.radius)).toBeGreaterThan(8);
        // Vazirmatn e self-host load shode.
        expect(styles.font).toContain('Vazirmatn');
    });

    test('jahat va zaban e sahih e enterprise: rtl + fa + viewport e mobile salem', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/login');

        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expect(page.locator('html')).toHaveAttribute('lang', 'fa');

        // Hich scroll e ofoghi tu mobile (ghanun e layout).
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);

        await expect(page.getByLabel('شماره موبایل')).toBeVisible();
    });
});
