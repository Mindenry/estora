const { test, expect } = require('@playwright/test');

async function noHorizontalScroll(page) {
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  expect(overflow, 'page must not scroll horizontally').toBeLessThanOrEqual(1);
}

test.describe('Responsive and accessibility checks', () => {
  test('mobile public pages and navigation', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    for (const path of ['/', '/properties/', '/about/', '/contact/']) {
      await page.goto(path);
      await expect(page.locator('h1').first()).toBeVisible();
      await noHorizontalScroll(page);
    }
    await page.goto('/');
    await page.click('[data-nav-toggle]');
    await expect(page.locator('[data-nav]')).toBeVisible();
    await expect(page.locator('[data-nav] a').first()).toBeVisible();
  });

  test('mobile admin dashboard stays manageable', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/wp-login.php');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'Estora!Admin!2026');
    await Promise.all([page.waitForURL(/wp-admin/), page.click('#wp-submit')]);
    await page.goto('/wp-admin/admin.php?page=estora-dashboard');
    await expect(page.locator('.estora-kpi').first()).toBeVisible();
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    expect(overflow).toBeLessThanOrEqual(1);
  });

  test('keyboard focus and image alternatives', async ({ page }) => {
    await page.goto('/properties/');
    await page.keyboard.press('Tab');
    const focused = await page.evaluate(() => document.activeElement && document.activeElement.className);
    expect(String(focused)).toBeTruthy();
    const imagesWithoutAlt = await page.locator('img:not([alt])').count();
    expect(imagesWithoutAlt).toBe(0);
    const controlsWithoutLabel = await page.locator('input:not([type="hidden"]):not([aria-label]):not([id])').count();
    expect(controlsWithoutLabel).toBe(0);
  });
});
