import { expect, test, type Page } from '@playwright/test';
import { artisan, signInAsAdmin } from './helpers/auth';

/**
 * «اولویت منابع» (P5-07): virayesh e zanjire ye yek symbol tu BROWSER e
 * vaghei — jabeja، save (AJAX)، paydari ba'd az reload، bargasht be
 * pish-farz.
 *
 * ⚠️ Ru DB e DEV ejra mishe: symbol e test dar `afterAll` HATMAN be
 * pish-farz bar migarde. «پر کردن بر اساس اولویت» amdan click NEMISHE —
 * gheymat haye dasti e vaghei ro jaygozin mikone (test e feature poshtesh
 * mikone).
 */
test.describe.configure({ mode: 'serial' });

const SYMBOL = 'xag-925-g-irr';

let page: Page;

const resetSymbol = () =>
    artisan('tinker', `--execute=app(App\\Modules\\Pricing\\Application\\Actions\\SymbolPriorityAction::class)->set(App\\Modules\\Pricing\\Domain\\Enums\\Instrument::from('${SYMBOL}'), []); echo 'DONE';`);

test.beforeAll(async ({ browser }) => {
    resetSymbol();
    page = await browser.newPage();
    await signInAsAdmin(page);
});

test.afterAll(async () => {
    resetSymbol();
    await page.close();
});

const row = () => page.locator(`[data-priority-row="${SYMBOL}"]`);
// Badge e vaziat — `getByText('پیش‌فرض')` dokme ye (penhan e) reset ro ham
// peyda mikard va mobham bud.
const badge = () => row().locator('[data-priority-badge]');
const chips = () => row().getByRole('list', { name: 'ترتیب منابع' }).getByRole('listitem');

test.describe('symbol priorities', () => {
    test('pish-farz: tartib e global، manual akhar', async () => {
        await page.goto('/dashboard/priorities');

        await expect(page.getByRole('heading', { level: 1, name: 'اولویت منابع' })).toBeVisible();
        await expect(badge()).toHaveText('پیش‌فرض');
        await expect(chips().last()).toContainText('دستی');
    });

    test('jabeja + save -> sefareshi va paydar ba\'d az reload', async () => {
        await page.goto('/dashboard/priorities');

        const firstBefore = (await chips().first().innerText()).trim();

        await row().getByRole('button', { name: /^ویرایش اولویت/ }).click();
        // Dovvomi ro bala bebar (ya'ni jaye avvali).
        await row().getByRole('button', { name: /^بالاتر:/ }).nth(1).click();
        await row().getByRole('button', { name: 'ذخیره' }).click();

        await expect(badge()).toHaveText('سفارشی');
        await expect(chips().first()).not.toHaveText(firstBefore);

        const firstAfter = (await chips().first().innerText()).trim();

        await page.reload();
        await expect(badge()).toHaveText('سفارشی');
        await expect(chips().first()).toHaveText(firstAfter);
        // Manual hamchenan akhar.
        await expect(chips().last()).toContainText('دستی');
    });

    test('bargasht be pish-farz', async () => {
        await page.goto('/dashboard/priorities');

        await row().getByRole('button', { name: /^بازگشت به پیش‌فرض/ }).click();

        await expect(badge()).toHaveText('پیش‌فرض');
    });

    test('safhe ye dasti dokme ye «پر کردن بر اساس اولویت» dare', async () => {
        await page.goto('/dashboard/manual');

        const card = page.locator('[data-seed-priority]');
        await expect(card.getByRole('button', { name: 'پر کردن بر اساس اولویت' })).toBeVisible();
        await expect(card.getByRole('link', { name: 'تنظیم اولویت‌ها' })).toHaveAttribute('href', /\/dashboard\/priorities$/);
    });
});
