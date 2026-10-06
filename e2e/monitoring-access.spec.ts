import { test, expect } from '@playwright/test';
import { signInAsAdmin } from './helpers/auth';

test('monitoring guest ra be login mifreste va admin dashboard ra mibine', async ({ page }) => {
    await page.goto('/horizon');
    await expect(page).toHaveURL(/\/login$/);
    await page.goto('/telescope');
    await expect(page).toHaveURL(/\/login$/);

    await signInAsAdmin(page);
    await page.goto('/horizon');
    await expect(page.getByRole('heading', { name: 'Overview', exact: true })).toBeVisible();
    await page.screenshot({ path: 'test-results/horizon-review.png', fullPage: true });
    await page.goto('/telescope');
    await expect(page.getByText('Requests', { exact: true }).first()).toBeVisible();
    await page.screenshot({ path: 'test-results/telescope-review.png', fullPage: true });
});
