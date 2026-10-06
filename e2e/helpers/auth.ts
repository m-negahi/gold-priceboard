import { spawnSync } from 'node:child_process';
import { expect, type Page } from '@playwright/test';

/**
 * Dar-e vorood-e moshtarak-e hame-ye spec-haye panel (docs/TESTING.md §1).
 *
 * Stack ba `OTP_BYPASS=true` bala-st: code-i ke server ba SMS ferestade rooye safhe ham hast ba
 * yek dokme-ye por-kardan-e khodkar — RequestOtpAction kharej az local/testing in ro rad mikone,
 * pas hich endpoint-e test-only tu app nist. `otp:flush` cooldown va rate limit-e shomare ro
 * ghabl az har vorood pak mikone ta limit ha tu app kamelan armed bemunand va suite tu chand
 * sanie ejra beshe.
 */
export const ADMIN = '9128452427';

/**
 * Yek command-e artisan dakhel-e container. Movafagh = exit 0, **ya** exit 134 ba yek khat-e
 * INFO/DONE tu khorooji: PHP-e CLI zir-e Swoole/Octane gah-gahi BA'D az tamum kardan-e kar sar-e
 * shutdown abort mikone (`zend_mm_heap corrupted`, SIGABRT = 134) va nabayad test ro bekoshe.
 * Har chiz-e dige (exit 1 ba INFO, exit 134 bedoone INFO, …) yek khata-ye vaghei-e va ba
 * khorooji throw mishe.
 */
export function artisan(...args: string[]): string {
    const run = spawnSync('docker', ['compose', '-f', 'docker-compose.local.yml', 'exec', '-T', 'octane', 'php', 'artisan', ...args], { encoding: 'utf8' });
    const output = `${run.stdout}${run.stderr}`;
    const abortedAfterSuccess = run.status === 134 && /INFO|DONE/.test(output);

    if (run.status !== 0 && !abortedAfterSuccess) {
        throw new Error(`artisan ${args.join(' ')} failed (exit ${run.status}):\n${output}`);
    }

    return output;
}

/** Code-e zende, cooldown va shomarande-haye rate limit-e yek shomare ro pak mikone. */
export function resetOtp(phone: string = ADMIN): void {
    const output = artisan('otp:flush', phone);

    if (!output.includes('OTP state cleared')) {
        throw new Error(`otp:flush did not run:\n${output}`);
    }
}

/** Shomare → daryaft-e code → por-kardan-e khodkar (bypass) → /panel. */
export async function signInAsAdmin(page: Page): Promise<void> {
    resetOtp(ADMIN);
    await page.goto('/login');
    await page.getByLabel('شماره موبایل').fill('0' + ADMIN);
    await page.getByRole('button', { name: 'دریافت کد ورود' }).click();
    await expect(page.getByRole('heading', { name: 'کد ورود' })).toBeVisible();
    await page.getByRole('button', { name: 'پر کردن خودکار' }).click();
    await page.waitForURL('**/dashboard');
}

/**
 * Vorud e yek moshtari ba OTP e bypass (`local` = `09…`). Maghsad e ba'd az
 * login ro check nemikone (home ya onboarding — P7-03)، faghat inke az
 * `/login` birun rafte bashe.
 */
export async function signInAsCustomer(page: Page, local: string): Promise<void> {
    resetOtp(local.slice(1));
    await page.goto('/login');
    await page.getByLabel('شماره موبایل').fill(local);
    await page.getByRole('button', { name: 'دریافت کد ورود' }).click();
    await expect(page.getByRole('heading', { name: 'کد ورود' })).toBeVisible();
    await page.getByRole('button', { name: 'پر کردن خودکار' }).click();
    await page.waitForURL((url) => url.pathname !== '/login');
}

/**
 * Rang e yek token e `@theme` -> `rgb(...)` ke browser khodesh resolve
 * karde (probe element). Token e nabude = transparent -> throw.
 */
export async function tokenRgb(page: Page, name: string, property: 'backgroundColor' | 'color' = 'backgroundColor'): Promise<string> {
    const rgb = await page.evaluate(([n, prop]) => {
        const probe = document.createElement('div');
        probe.style[prop as 'color'] = `var(${n})`;
        document.body.appendChild(probe);
        const value = getComputedStyle(probe)[prop as 'color'];
        probe.remove();

        return value;
    }, [name, property]);

    expect(rgb, `token e ${name} resolve nashod`).not.toBe('rgba(0, 0, 0, 0)');

    return rgb;
}

/** Hich overflow e ofoghi ye PAGE (375px — P4-19). */
export async function expectNoPageOverflow(page: Page): Promise<void> {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow, 'safhe overflow e ofoghi dare').toBeLessThanOrEqual(0);
}
