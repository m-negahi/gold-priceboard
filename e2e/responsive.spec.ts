import { expect, test, type Page } from '@playwright/test';
import { signInAsAdmin } from './helpers/auth';

/**
 * Responsive e KOL e panel (P4-19): hich safhe-i tu 375px nabayad overflow
 * e ofoghi ye PAGE dashte bashe — jadval haye pahn faghat DAKHEL e
 * container e `overflow-x-auto` ye khodeshun scroll mikonan.
 *
 * In spec negahban e. Safhe ye jadid e panel؟ Be `PAGES` ezafe-sh kon.
 */
test.describe.configure({ mode: 'serial' });

const PAGES: Array<{ path: string; heading: RegExp }> = [
    { path: '/dashboard', heading: /میزکار/ },
    { path: '/dashboard/providers', heading: /منابع قیمت/ },
    { path: '/dashboard/providers/create', heading: /منبع/ },
    { path: '/dashboard/providers/tgju/edit', heading: /ویرایش/ },
    { path: '/dashboard/providers/tgju/spreads', heading: /اسپرد/ },
    { path: '/dashboard/manual', heading: /قیمت دستی/ },
    { path: '/dashboard/priorities', heading: /اولویت منابع/ },
    { path: '/dashboard/health', heading: /سلامت منابع/ },
    { path: '/dashboard/monitor', heading: /پایش زنده/ },
    { path: '/dashboard/monitor/api', heading: /پایش API/ },
    { path: '/dashboard/monitor/upstream', heading: /پایش منابع/ },
    { path: '/dashboard/matrix', heading: /ماتریس/ },
    { path: '/dashboard/charts', heading: /نمودار قیمت/ },
    { path: '/dashboard/users', heading: /مشتریان/ },
    { path: '/dashboard/users/create', heading: /کاربر جدید/ },
    { path: '/dashboard/tickets', heading: /تیکت‌ها/ },
    // Admin e dev (seeder) id 1 ast.
    { path: '/dashboard/users/1', heading: /./ },
];

let page: Page;

test.beforeAll(async ({ browser }) => {
    page = await browser.newPage({ viewport: { width: 375, height: 667 } });
    await signInAsAdmin(page);
});

test.afterAll(async () => {
    await page.close();
});

const SIDEBAR = 'aside[x-data="panelSidebar"]';

/**
 * Sabr ta Alpine component e sidebar VAGHE'AN init shode bashe — click e
 * ghabl az init rooye `@click`/`@…window` e Alpine hich kari nemikone
 * (hamun dars e flakiness e `public/hot` tu TASKS).
 */
async function sidebarReady(): Promise<void> {
    await page.waitForFunction((selector) => {
        const el = document.querySelector(selector);
        const alpine = (window as never as { Alpine?: { $data(el: Element): { open?: boolean } } }).Alpine;

        return Boolean(el && alpine && typeof alpine.$data(el!).open === 'boolean');
    }, SIDEBAR);
}

/** `translate` e computed e aside — '100% ...' ya'ni baste، '0px'/'none' ya'ni baz. */
async function drawerHidden(): Promise<boolean> {
    return page.locator(SIDEBAR).evaluate((el) => getComputedStyle(el).translate.startsWith('100%'));
}

test.describe('drawer e mobile (P4-19)', () => {
    test('sidebar tu mobile baste-ast va ba hamburger baz/baste mishe', async () => {
        await page.goto('/dashboard');
        await sidebarReady();

        // Baste (hatta GHABL az Alpine ham baste bud — class e static).
        expect(await drawerHidden()).toBe(true);

        // Hamburger e topbar bazesh mikone.
        await page.getByLabel('باز کردن منو').click();
        await expect.poll(drawerHidden).toBe(false);

        // Poll: transition e 200ms bayad tamum she ta drawer sar e jash beshine.
        await expect.poll(async () => {
            const box = await page.locator(SIDEBAR).boundingBox();

            return box === null ? Infinity : box.x + box.width;
        }, { message: 'drawer e baz bayad dakhel e viewport bashe' }).toBeLessThanOrEqual(376);

        // Dokme ye bastan e drawer.
        await page.getByLabel('بستن منو').click();
        await expect.poll(drawerHidden).toBe(true);
    });

    test('backdrop click drawer ro mibande', async () => {
        await page.goto('/dashboard');
        await sidebarReady();

        await page.getByLabel('باز کردن منو').click();
        await expect.poll(drawerHidden).toBe(false);

        // Click ru nahie ye birun e drawer (samt e chap e viewport e RTL).
        await page.mouse.click(40, 320);
        await expect.poll(drawerHidden).toBe(true);
    });
});

test.describe('panel @ 375px', () => {
    for (const { path, heading } of PAGES) {
        test(`${path} bedun e overflow e ofoghi`, async () => {
            await page.goto(path);

            // Safhe vaghe'an load shode — na yek 500 ya redirect.
            await expect(page.getByRole('heading', { level: 1, name: heading })).toBeVisible();

            // 🔴 Check e asli: scrollWidth e document == clientWidth.
            // (+1 baraye gerd-shodan e subpixel.)
            const { scrollWidth, clientWidth } = await page.evaluate(() => ({
                scrollWidth: document.documentElement.scrollWidth,
                clientWidth: document.documentElement.clientWidth,
            }));

            expect(
                scrollWidth,
                `${path}: page ${scrollWidth}px pahn ast ru viewport e ${clientWidth}px — yek element az container esh zade birun.`,
            ).toBeLessThanOrEqual(clientWidth + 1);
        });
    }
});
