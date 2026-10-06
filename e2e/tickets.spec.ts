import { expect, test, type Browser, type Page } from '@playwright/test';
import { artisan, expectNoPageOverflow, signInAsAdmin, signInAsCustomer, tokenRgb } from './helpers/auth';

/**
 * Ticket ha (P7-01) tu browser e vaghei: moshtari ticket misaze → admin
 * tu inbox peyda mikone، javab mide، vaziat avaz mikone → moshtari javab
 * ro «پشتیبانی» mibine.
 *
 * ⚠️ DB e DEV: karbar e MOVAGHAT e in spec (va ticket hash — cascade)
 * ghabl/ba'd az suite pak mishe.
 */
test.describe.configure({ mode: 'serial' });

const CUSTOMER = '09990002345';
const SUBJECT = 'تیکت آزمایشی e2e';

const removeTempUser = () =>
    artisan('tinker', `--execute=App\\Modules\\Auth\\Infrastructure\\Persistence\\Models\\User::where('phone', '+989990002345')->delete(); echo 'DONE';`);

let admin: Page;
let customer: Page;

async function customerPage(browser: Browser): Promise<Page> {
    const ctx = await browser.newContext();
    const page = await ctx.newPage();
    await signInAsCustomer(page, CUSTOMER);

    return page;
}

test.beforeAll(async ({ browser }) => {
    removeTempUser();
    admin = await browser.newPage();
    await signInAsAdmin(admin);
    customer = await customerPage(browser);
});

test.afterAll(async () => {
    removeTempUser();
    await admin.close();
    await customer.context().close();
});

test('moshtari tiket e poshtibani misaze', async () => {
    await customer.goto('/account/tickets');
    await expect(customer.getByRole('heading', { level: 1, name: 'تیکت‌های من' })).toBeVisible();
    await customer.locator('[data-ticket-create]').click();

    await customer.getByLabel('موضوع').fill(SUBJECT);
    await customer.getByLabel('متن پیام').fill('سلام، کلید API کار نمی‌کند.');
    await customer.getByRole('button', { name: 'ثبت تیکت' }).click();

    await expect(customer).toHaveURL(/\/account\/tickets\/\d+$/);
    await expect(customer.getByRole('heading', { level: 1, name: SUBJECT })).toBeVisible();
    await expect(customer.locator('[data-ticket-status]')).toHaveAttribute('data-ticket-status', 'open');
    // Payam e movafaghiat ba token e ok.
    const ok = await tokenRgb(customer, '--color-ok', 'color');
    expect(await customer.locator('[data-account-status]').evaluate((el) => getComputedStyle(el).color)).toBe(ok);
});

test('admin tu inbox peyda mikone، javab mide va vaziat avaz mikone', async () => {
    await admin.goto('/dashboard/tickets?q=' + encodeURIComponent(SUBJECT));
    const row = admin.locator('[data-ticket-row]').filter({ hasText: SUBJECT });
    await expect(row).toHaveCount(1);
    await expect(row).toContainText(CUSTOMER);
    await row.getByRole('link', { name: /^جزئیات تیکت/ }).click();

    await expect(admin.getByRole('heading', { level: 1, name: SUBJECT })).toBeVisible();
    await admin.getByLabel('پاسخ پشتیبانی').fill('کلید جدید صادر شد.');
    await admin.getByRole('button', { name: 'ارسال پاسخ' }).click();
    await expect(admin.locator('[data-ticket-message][data-staff="1"]')).toContainText('کلید جدید صادر شد.');

    await admin.locator('[data-ticket-status-form]').getByLabel('وضعیت جدید').selectOption('in_review');
    await admin.locator('[data-ticket-status-form]').getByRole('button', { name: 'ثبت وضعیت' }).click();

    const pill = admin.locator('[data-ticket-status]');
    await expect(pill).toHaveAttribute('data-ticket-status', 'in_review');
    // Pill e «در حال بررسی» = token e warn-soft.
    const warn = await tokenRgb(admin, '--color-warn-soft');
    expect(await pill.evaluate((el) => getComputedStyle(el).backgroundColor)).toBe(warn);
    // Audit: ثبت + تغییر وضعیت.
    await expect(admin.locator('[data-ticket-event]')).toHaveCount(2);
});

test('moshtari javab ro «پشتیبانی» mibine va audit ro nemibine', async () => {
    await customer.goto('/account/tickets');
    await customer.locator('[data-ticket-row]').filter({ hasText: SUBJECT }).getByRole('link').click();

    const staff = customer.locator('[data-ticket-message][data-staff="1"]');
    await expect(staff).toContainText('کلید جدید صادر شد.');
    await expect(staff).toContainText('پشتیبانی');
    await expect(customer.locator('[data-ticket-event]')).toHaveCount(0);
    await expect(customer.locator('[data-ticket-status]')).toHaveAttribute('data-ticket-status', 'in_review');
});

test('safhe haye ticket tu 375px overflow nadaran', async () => {
    for (const page of [admin, customer]) {
        await page.setViewportSize({ width: 375, height: 667 });
        await page.reload();
        await expectNoPageOverflow(page);
    }
    await customer.goto('/account/tickets');
    await expectNoPageOverflow(customer);
});
