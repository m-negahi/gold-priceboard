import { expect, test, type Page } from '@playwright/test';
import { ADMIN, artisan, resetOtp, signInAsAdmin } from './helpers/auth';

/**
 * Karbaran e panel (Phase 6) tu browser e vaghei.
 *
 * ⚠️ Ru DB e DEV ejra mishe: faghat KHUNDAN e lista + filter ha. Har
 * karbar-i ke test misaze dar `afterAll` disable/pak-sazi mishe (P6-04+).
 */
test.describe.configure({ mode: 'serial' });

let page: Page;

/** Karbar e MOVAGHAT e e2e — ghabl/ba'd az suite pak mishe (DB e dev). */
const TEMP_PHONE = '09990001234';

const removeTempUser = () =>
    artisan('tinker', `--execute=App\\Modules\\Auth\\Infrastructure\\Persistence\\Models\\User::where('phone', '+989990001234')->delete(); echo 'DONE';`);

test.beforeAll(async ({ browser }) => {
    removeTempUser();
    page = await browser.newPage();
    await signInAsAdmin(page);
});

test.afterAll(async () => {
    removeTempUser();
    await page.close();
});

const rows = () => page.locator('[data-user-row]');

/**
 * Rang e yek token e `@theme` -> `rgb(...)` ke browser khodesh resolve
 * karde (probe element) — `#fff` e kutah ya `var(...)` ham dorost mishe.
 */
async function tokenRgb(name: string): Promise<string> {
    const rgb = await page.evaluate((n) => {
        const probe = document.createElement('div');
        probe.style.backgroundColor = `var(${n})`;
        document.body.appendChild(probe);
        const rgb = getComputedStyle(probe).backgroundColor;
        probe.remove();

        return rgb;
    }, name);

    // Token e nabude = transparent — nabayad ba element e shafaf «barabar» she.
    expect(rgb, `token e ${name} resolve nashod`).not.toBe('rgba(0, 0, 0, 0)');

    return rgb;
}

test.describe('lista ye karbaran (P6-02)', () => {
    test('sidebar «مشتریان» fa\'al ast va be lista mire', async () => {
        await page.goto('/dashboard');
        await page.locator('aside').getByRole('link', { name: 'مشتریان' }).first().click();

        await expect(page).toHaveURL(/\/dashboard\/users/);
        await expect(page.getByRole('heading', { level: 1, name: 'مشتریان' })).toBeVisible();
        await expect(rows().first()).toBeVisible();
    });

    test('search ba mobile e admin hamun radif ro neshun mide', async () => {
        await page.goto('/dashboard/users');
        await page.getByPlaceholder('جستجو با موبایل یا نام').fill('0' + ADMIN);
        await page.getByRole('button', { name: 'اعمال فیلتر' }).click();

        await expect(page).toHaveURL(/q=0?9128452427/);
        await expect(rows()).toHaveCount(1);
        await expect(rows().first()).toContainText('0' + ADMIN);
        await expect(rows().first()).toContainText('ادمین');
    });

    test('filter e naghsh «ادمین» faghat admin ha', async () => {
        await page.goto('/dashboard/users?role=admin');

        const count = await rows().count();
        expect(count).toBeGreaterThan(0);

        for (let i = 0; i < count; i++) {
            await expect(rows().nth(i)).toContainText('ادمین');
        }
    });

    test('pill e «فعال» rang e token e ok-soft dare', async () => {
        await page.goto('/dashboard/users?q=0' + ADMIN);

        const pill = rows().first().getByText('فعال', { exact: true });
        await expect(pill).toBeVisible();

        const expected = await tokenRgb('--color-ok-soft');
        expect(await pill.evaluate((el) => getComputedStyle(el).backgroundColor)).toBe(expected);
    });
});

test.describe('safhe ye karbar (P6-03)', () => {
    test('az lista be safhe ye admin + bakhsh ha', async () => {
        await page.goto('/dashboard/users?q=0' + ADMIN);
        await rows().first().locator('[data-user-detail-link]').click();

        await expect(page).toHaveURL(/\/dashboard\/users\/\d+$/);
        await expect(page.getByRole('heading', { level: 2, name: 'مشخصات' })).toBeVisible();
        await expect(page.getByRole('heading', { level: 2, name: 'کیف پول' })).toBeVisible();
        await expect(page.getByRole('heading', { level: 2, name: 'سایت‌ها و پلن‌ها' })).toBeVisible();
        await expect(page.locator('[data-user-status]')).toHaveAttribute('data-user-status', 'active');
    });

    test('card e mande rang e surface e token dare', async () => {
        const url = page.url();
        await page.goto(url);

        const card = page.locator('[data-wallet]');
        const expected = await tokenRgb('--color-surface');
        expect(await card.evaluate((el) => getComputedStyle(el).backgroundColor)).toBe(expected);
    });
});

test.describe('sakht + naghsh (P6-04)', () => {
    test('sakht e moshtari az form -> safhe ye karbar', async () => {
        await page.goto('/dashboard/users');
        await page.locator('[data-user-create]').click();
        await expect(page.getByRole('heading', { level: 1, name: 'کاربر جدید' })).toBeVisible();

        await page.getByLabel('موبایل').fill(TEMP_PHONE);
        await page.getByLabel('نام', { exact: true }).fill('کاربر آزمایشی e2e');
        await page.getByRole('button', { name: 'ساخت کاربر' }).click();

        await expect(page).toHaveURL(/\/dashboard\/users\/\d+$/);
        await expect(page.getByRole('heading', { level: 1, name: 'کاربر آزمایشی e2e' })).toBeVisible();
        await expect(page.getByText(TEMP_PHONE).first()).toBeVisible();
    });

    test('shomare ye tekrari khata mide', async () => {
        await page.goto('/dashboard/users/create');
        await page.getByLabel('موبایل').fill(TEMP_PHONE);
        await page.getByRole('button', { name: 'ساخت کاربر' }).click();

        await expect(page.getByText('کاربری با این شمارهٔ موبایل از قبل وجود دارد.')).toBeVisible();
    });

    test('ertegha be admin va bargasht', async () => {
        await page.goto('/dashboard/users?q=' + TEMP_PHONE);
        await rows().first().getByRole('link', { name: /^جزئیات/ }).click();

        await page.getByRole('button', { name: 'ارتقا به ادمین' }).click();
        await expect(page.getByRole('button', { name: 'تبدیل به مشتری' })).toBeVisible();

        await page.getByRole('button', { name: 'تبدیل به مشتری' }).click();
        await expect(page.getByRole('button', { name: 'ارتقا به ادمین' })).toBeVisible();
    });

    test('admin naghsh e khodesh ro nemigire', async () => {
        await page.goto('/dashboard/users?q=0' + ADMIN);
        await rows().first().getByRole('link', { name: /^جزئیات/ }).click();

        await page.getByRole('button', { name: 'تبدیل به مشتری' }).click();
        await expect(page.locator('[data-user-error]')).toHaveText('نمی‌توانید نقش ادمین را از خودتان بگیرید.');
        // Error box ba token e down-soft.
        const expected = await tokenRgb('--color-down-soft');
        expect(await page.locator('[data-user-error]').evaluate((el) => getComputedStyle(el).backgroundColor)).toBe(expected);
    });
});

test.describe('disable/enable (P6-05)', () => {
    test('session e baz e karbar e disable-shode mioftad + login payam mide', async ({ browser }) => {
        // Karbar e movaghat (az P6-04) ba OTP e bypass login mikone.
        const ctx = await browser.newContext();
        const customer = await ctx.newPage();
        resetOtp(TEMP_PHONE.slice(1));
        await customer.goto('/login');
        await customer.getByLabel('شماره موبایل').fill(TEMP_PHONE);
        await customer.getByRole('button', { name: 'دریافت کد ورود' }).click();
        await customer.getByRole('button', { name: 'پر کردن خودکار' }).click();
        // P7-03: karbar e taze avval be onboarding mire.
        await customer.waitForURL((url) => url.pathname === '/onboarding');

        // Admin disable mikone.
        await page.goto('/dashboard/users?q=' + TEMP_PHONE);
        await rows().first().getByRole('link', { name: /^جزئیات/ }).click();
        await page.getByLabel('دلیل غیرفعال‌سازی').fill('آزمایش e2e');
        await page.locator('[data-user-disable]').click();
        await expect(page.locator('[data-user-status]')).toHaveAttribute('data-user-status', 'disabled');
        await expect(page.getByText('آزمایش e2e')).toBeVisible();

        // Request e ba'di ye session e baz -> /login ba payam.
        await customer.goto('/');
        await expect(customer).toHaveURL(/\/login$/);
        const notice = customer.locator('[data-login-notice]');
        await expect(notice).toHaveText('حساب کاربری شما غیرفعال شده است.');
        const danger = await customer.evaluate(() => {
            const probe = document.createElement('div');
            probe.style.color = 'var(--color-danger)';
            document.body.appendChild(probe);
            const rgb = getComputedStyle(probe).color;
            probe.remove();
            return rgb;
        });
        expect(await notice.evaluate((el) => getComputedStyle(el).color)).toBe(danger);
        await ctx.close();

        // Enable -> vaziat bar migarde.
        await page.locator('[data-user-enable]').click();
        await expect(page.locator('[data-user-status]')).toHaveAttribute('data-user-status', 'active');
    });
});

test.describe('wallet e dasti (P6-06)', () => {
    /**
     * Karbar e SABET e e2e baraye wallet: ledger append-only ast (P6-07 —
     * trigger e DB delete ro rad mikone)، pas in karbar hich vaght pak
     * nemishe؛ har ejra yek sharj + hamun ghadr kasr → mande sefr mimune.
     */
    const WALLET_PHONE = '+989990009999';

    test('sharj ba dalil → ledger + mande، kasr e hamun ghadr → bargasht', async () => {
        const out = artisan('tinker', `--execute=$u = App\\Modules\\Auth\\Infrastructure\\Persistence\\Models\\User::firstOrCreate(['phone' => '${WALLET_PHONE}'], ['name' => 'e2e wallet']); echo 'ID=' . $u->id;`);
        const id = /ID=(\d+)/.exec(out)?.[1];
        expect(id, out).toBeTruthy();

        await page.goto(`/dashboard/users/${id}`);
        const balance = page.locator('[data-wallet-balance]');
        const before = (await balance.count()) > 0 ? await balance.innerText() : null;

        const form = page.locator('[data-wallet-adjust]');
        await form.getByLabel('نوع').selectOption('credit');
        await form.getByLabel('مبلغ (تومان)').fill('1,000');
        await form.getByLabel('دلیل').fill('آزمایش e2e شارژ');
        await form.locator('[data-wallet-adjust-submit]').click();

        const firstRow = page.locator('[data-ledger-row]').first();
        await expect(firstRow).toContainText('اصلاح دستی ادمین');
        await expect(firstRow).toContainText('آزمایش e2e شارژ');
        await expect(firstRow.locator('[data-ledger-admin]')).toContainText('0' + ADMIN);
        await expect(firstRow).toContainText('+1,000');
        // Rang e mablagh e sharj = token e ok.
        const ok = await tokenRgb('--color-ok');
        const amountCell = firstRow.locator('td').nth(2);
        expect(await amountCell.evaluate((el) => getComputedStyle(el).color)).toBe(ok);

        await form.getByLabel('نوع').selectOption('debit');
        await form.getByLabel('مبلغ (تومان)').fill('1000');
        await form.getByLabel('دلیل').fill('آزمایش e2e برگشت');
        await form.locator('[data-wallet-adjust-submit]').click();

        await expect(page.locator('[data-ledger-row]').first()).toContainText('−1,000');
        if (before !== null) {
            await expect(balance).toHaveText(before);
        }
    });

    test('kasr e bishtar az mande khata mide', async () => {
        await page.goto('/dashboard/users?q=09990009999');
        await rows().first().getByRole('link', { name: /^جزئیات/ }).click();
        const form = page.locator('[data-wallet-adjust]');
        await form.getByLabel('نوع').selectOption('debit');
        await form.getByLabel('مبلغ (تومان)').fill('99999999999');
        await form.getByLabel('دلیل').fill('نباید ثبت شود');
        await form.locator('[data-wallet-adjust-submit]').click();

        await expect(page.locator('[data-user-error]')).toHaveText('موجودی کیف پول کافی نیست.');
    });
});

test.describe('usage e API (P6-08)', () => {
    test('chart e 30 rooz ba bar e emruz be rang e brand', async () => {
        await page.goto('/dashboard/users/1');
        const chart = page.locator('[data-usage-chart]');
        await expect(chart.getByRole('heading', { name: 'مصرف API' })).toBeVisible();

        const bars = chart.locator('[role="img"] > div');
        await expect(bars).toHaveCount(30);

        // Bar e akhar (emruz) fa'al = brand-500؛ baghi hachure.
        const today = bars.last().locator('.rounded-full');
        const brand = await tokenRgb('--color-brand-500');
        expect(await today.evaluate((el) => getComputedStyle(el).backgroundColor)).toBe(brand);

        // Jam' ha adad-and (raghame Latin).
        await expect(chart).toHaveAttribute('data-usage-total-calls', /^\d+$/);
    });
});
