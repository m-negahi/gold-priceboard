import { defineConfig, devices } from '@playwright/test';

/**
 * E2E az HOST rooye app-e Docker-i ejra mishe (docs/TESTING.md). Stack bayad az ghabl bala bashe:
 * docker compose -f docker-compose.local.yml up -d
 *
 * Yek worker, bedoone parallel: hame-ye spec ha yek database va yek session store ro share
 * mikonan (seed-haye artisan, `otp:flush`-e yek shomare, radif-haye movaghat) — do spec-e hamzaman
 * dade-ye hamdige ro pak mikonan.
 */
export default defineConfig({
    testDir: './e2e',
    fullyParallel: false,
    workers: 1,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    reporter: process.env.CI ? 'github' : 'list',
    timeout: 30_000,
    expect: { timeout: 5_000 },
    use: {
        baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://localhost:8300',
        locale: 'fa-IR',
        timezoneId: 'Asia/Tehran',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
