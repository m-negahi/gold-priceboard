import { expect, test, type Page } from '@playwright/test';
import { artisan, expectNoPageOverflow, signInAsAdmin, tokenRgb } from './helpers/auth';

/**
 * «تأیید سایت‌ها» (P8-03) tu panel e admin: ticket e pending e site liste
 * mishe ba dokme haye «استعلام نماد» / «تأیید سایت» / «رد سایت».
 *
 * ⚠️ Hich approve/reject-i zade NEMISHE (estelam e enamad be provider e vaghei
 * mire). Karbar + site e MOVAGHAT ba tinker sakhte va ba'd pak mishe (cascade).
 */
test.describe.configure({ mode: 'serial' });

const PHONE = '+989990005678';
const SITE_URL = 'https://e2e-sites.example.com';

const removeFixture = () =>
    artisan('tinker', `--execute=App\\Modules\\Auth\\Infrastructure\\Persistence\\Models\\User::where('phone', '${PHONE}')->delete(); echo 'DONE';`);

const createFixture = () =>
    artisan('tinker', `--execute=$u = App\\Modules\\Auth\\Infrastructure\\Persistence\\Models\\User::factory()->create(['phone' => '${PHONE}', 'name' => 'مشتری e2e سایت']); $c = app(App\\Modules\\Api\\Application\\Actions\\CreateSiteAction::class)->execute($u->id, 'سایت e2e', '${SITE_URL}'); echo 'DONE ticket=' . $c['ticket_id'];`);

let page: Page;
let ticketId: string;

test.beforeAll(async ({ browser }) => {
    removeFixture();
    ticketId = createFixture().match(/ticket=(\d+)/)![1];
    page = await browser.newPage();
    await signInAsAdmin(page);
});

test.afterAll(async () => {
    removeFixture();
    await page.close();
});

test('lista ye darkhast ha ticket e pending ro ba kontrol ha neshun mide', async () => {
    await page.goto('/dashboard/sites');
    await expect(page.getByRole('heading', { level: 1, name: 'درخواست‌های سایت' })).toBeVisible();

    const card = page.locator('article').filter({ hasText: SITE_URL });
    await expect(card).toHaveCount(1);
    await expect(card).toContainText(`تیکت #${ticketId}`);
    await expect(card).toContainText('هنوز استعلامی ثبت نشده است.');
    await expect(card.getByRole('button', { name: 'استعلام نماد' })).toBeVisible();
    await expect(card.getByRole('button', { name: 'تأیید سایت' })).toBeVisible();
    await expect(card.getByRole('button', { name: 'رد سایت' })).toBeVisible();
    await expect(card.getByPlaceholder('دلیل رد')).toHaveAttribute('required', '');
    expect(await card.locator('form').count()).toBe(3);
});

test('pill e pending token e warn-soft dare va dokme ye taeed token e brand', async () => {
    const card = page.locator('article').filter({ hasText: SITE_URL });
    const pill = card.getByText('pending', { exact: true });
    expect(await pill.evaluate((el) => getComputedStyle(el).backgroundColor)).toBe(await tokenRgb(page, '--color-warn-soft'));
    const approve = card.getByRole('button', { name: 'تأیید سایت' });
    expect(await approve.evaluate((el) => getComputedStyle(el).backgroundColor)).toBe(await tokenRgb(page, '--color-brand'));
});

test('sidebar «سایت‌ها» be hamin safhe mire', async () => {
    await page.goto('/dashboard');
    await page.locator('aside').getByRole('link', { name: 'سایت‌ها', exact: true }).first().click();
    await expect(page).toHaveURL(/\/dashboard\/sites$/);
});

test('safhe tu 375px overflow nadare', async () => {
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/dashboard/sites');
    await expectNoPageOverflow(page);
});
