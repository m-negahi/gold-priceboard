import { expect, test, type Page } from '@playwright/test';
import { artisan, signInAsAdmin } from './helpers/auth';

/**
 * «اولویت منابع» (P5-07): virayesh e zanjire ye yek symbol tu BROWSER e
 * vaghei — jabeja، save (AJAX)، paydari ba'd az reload، bargasht be
 * pish-farz.
 *
 * ⚠️ Ru DB e DEV ejra mishe: olaviat e ghabli ye symbol dar `afterAll`
 * HATMAN restore mishe. «پر کردن بر اساس اولویت» amdan click NEMISHE —
 * gheymat haye dasti e vaghei ro jaygozin mikone (test e feature poshtesh
 * mikone).
 */
test.describe.configure({ mode: 'serial' });

const SYMBOL = 'xag-925-g-irr';

let page: Page;
let originalPriorities: string[] | null = null;

const setPriorities = (providers: string[]) => {
    const encoded = Buffer.from(JSON.stringify(providers)).toString('base64');
    artisan('tinker', `--execute=app(App\\Modules\\Pricing\\Application\\Actions\\SymbolPriorityAction::class)->set(App\\Modules\\Pricing\\Domain\\Enums\\Instrument::from('${SYMBOL}'), json_decode(base64_decode('${encoded}'), true)); echo 'DONE';`);
};

test.beforeAll(async ({ browser }) => {
    const output = artisan('tinker', `--execute=echo 'PRIORITY_STATE='.base64_encode(json_encode(app(App\\Modules\\Pricing\\Application\\Queries\\SymbolPriorityQuery::class)->customMap()['${SYMBOL}'] ?? []));`);
    const encoded = output.match(/PRIORITY_STATE=([A-Za-z0-9+/=]+)/)?.[1];
    if (!encoded) throw new Error('Olaviat e ghabli baraye restore khande nashod.');
    originalPriorities = JSON.parse(Buffer.from(encoded, 'base64').toString('utf8'));
    setPriorities([]);
    page = await browser.newPage();
    await signInAsAdmin(page);
});

test.afterAll(async () => {
    if (originalPriorities !== null) setPriorities(originalPriorities);
    await page?.close();
});

const row = () => page.locator(`[data-priority-row="${SYMBOL}"]`);
// Badge e vaziat — `getByText('پیش‌فرض')` dokme ye (penhan e) reset ro ham
// peyda mikard va mobham bud.
const badge = () => row().locator('[data-priority-badge]');
const chips = () => row().getByRole('list', { name: 'ترتیب منابع' }).getByRole('listitem');

test.describe('symbol priorities', () => {
    test('pish-farz: tartib e global، manual akhar', async () => {
        await page.goto('/dashboard/priorities');

        await expect(page.getByRole('heading', { level: 1, name: 'تنظیم قیمت نمادها' })).toBeVisible();
        await expect(badge()).toHaveText('پیش‌فرض');
        await expect(chips().last()).toContainText('API اختصاصی');
    });

    test('jabeja + save -> sefareshi va paydar ba\'d az reload', async () => {
        await page.goto('/dashboard/priorities');

        const firstBefore = (await chips().first().innerText()).trim();

        await row().getByRole('button', { name: /^ویرایش اولویت/ }).click();
        // Dovvomi ro bala bebar (ya'ni jaye avvali).
        await row().getByRole('button', { name: /^بالاتر:/ }).nth(1).click();
        await row().getByRole('button', { name: 'ذخیره', exact: true }).click();

        await expect(badge()).toHaveText('سفارشی');
        await expect(chips().first()).not.toHaveText(firstBefore);

        const firstAfter = (await chips().first().innerText()).trim();

        await page.reload();
        await expect(badge()).toHaveText('سفارشی');
        await expect(chips().first()).toHaveText(firstAfter);
        // Manual hamchenan akhar.
        await expect(chips().last()).toContainText('API اختصاصی');
    });

    test('bargasht be pish-farz', async () => {
        await page.goto('/dashboard/priorities');

        await row().getByRole('button', { name: /^بازگشت به پیش‌فرض/ }).click();

        await expect(badge()).toHaveText('پیش‌فرض');
    });

    test('safhe ye dasti be priorities redirect mishe va control haye combined dare', async () => {
        await page.goto('/dashboard/manual');

        await expect(page).toHaveURL(/\/dashboard\/priorities$/);
        // Dokme ye «کپی از اولویت‌ها» hazf shod — backup khodkar ast.
        await expect(page.getByRole('button', { name: 'کپی قیمت‌های تازه از اولویت‌ها' })).toHaveCount(0);
        await expect(row().getByRole('checkbox', { name: /^فقط دستی/ })).toBeVisible();
        await expect(page.getByText('پر کردن همه از یک منبع')).toHaveCount(0);
    });

    test('input e dasti faghat vaghti ke feed ru dasti bashe؛ badge e تک‌منبع', async () => {
        await page.goto('/dashboard/priorities');

        // 18 ayar ru dev 6 manba' e zende dare -> input penhan، kholase ye backup e khodkar namayan.
        const gold = page.locator('[data-priority-row="xau-18k-g-irr"]');
        await expect(gold.locator('[data-manual-summary]')).toBeVisible();
        await expect(gold.locator('[data-manual-input]')).toBeHidden();
        await expect(gold.locator('[data-source-badge]')).toBeHidden();

        // Mes faghat az tgju miad -> badge.
        const copper = page.locator('[data-priority-row="xcu-lb-usd"]');
        await expect(copper.locator('[data-source-badge]')).toHaveText('تک‌منبع');

        // Import e Excel bedun e radif e «فقط دستی» penhan ast.
        await expect(page.getByRole('link', { name: 'نمونه اکسل' })).toBeHidden();
    });
});
