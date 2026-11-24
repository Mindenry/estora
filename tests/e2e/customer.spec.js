const { test, expect } = require('@playwright/test');

const QA = `QA E2E ${Date.now()}`;
const QA_EMAIL = `qa-e2e-${Date.now()}@estora-qa.test`;

test.describe('Visitor journeys', () => {
  test('homepage → search → property detail', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('h1')).toContainText('A home you can trust');
    await expect(page.locator('.estora-search').first()).toBeVisible();
    await expect(page.locator('.estora-searchcard')).toBeVisible();
    await expect(page.locator('.estora-hero__proof')).toBeVisible();
    await expect(page.locator('.estora-card').first()).toBeVisible();

    await page.goto('/properties/?purpose=rent');
    await expect(page.locator('.estora-chip--rent').first()).toBeVisible();
    const rentChips = await page.locator('.estora-chip--rent').count();
    const saleChips = await page.locator('.estora-chip--sale').count();
    expect(rentChips).toBeGreaterThan(0);
    expect(saleChips).toBe(0);

    await page.goto('/properties/?sort=price_asc');
    const prices = await page.locator('.estora-card__price').allInnerTexts();
    expect(prices.length).toBeGreaterThan(1);

    await page.locator('.estora-card__title a').first().click();
    await expect(page.locator('.estora-property-head h1')).toBeVisible();
    await expect(page.locator('.estora-specstrip')).toBeVisible();
    await expect(page.locator('[data-gallery-main] img')).toBeVisible();
  });

  test('favourite and compare a property', async ({ page }) => {
    await page.goto('/properties/');
    const firstCard = page.locator('.estora-card').first();
    const title = await firstCard.locator('.estora-card__title').innerText();
    const fav = firstCard.locator('[data-favorite]');
    await fav.click();
    await expect(fav).toHaveAttribute('aria-pressed', 'true');
    await expect(page.locator('[data-fav-count]').first()).toHaveText('1');

    await page.goto('/favorites/');
    await expect(page.locator('.estora-card__title', { hasText: title.split('\n')[0] }).first()).toBeVisible();

    await page.goto('/properties/');
    const cards = page.locator('.estora-card');
    await cards.nth(0).locator('[data-compare]').click();
    await cards.nth(1).locator('[data-compare]').click();
    await page.goto('/compare/');
    await expect(page.locator('.estora-compare')).toBeVisible();
    await expect(page.locator('.estora-compare thead th')).toHaveCount(3);
    await page.locator('.estora-compare__remove').first().click();
    await expect(page.locator('.estora-compare thead th')).toHaveCount(2);
  });

  test('inquiry and viewing request succeed', async ({ page }) => {
    await page.goto('/properties/?availability=available');
    await page.locator('.estora-card__title a').first().click();
    await expect(page.locator('[data-inquiry-form]')).toBeVisible();

    await page.fill('[data-inquiry-form] [name="name"]', QA);
    await page.fill('[data-inquiry-form] [name="phone"]', '+66 91 234 5678');
    await page.fill('[data-inquiry-form] [name="email"]', QA_EMAIL);
    await page.fill('[data-inquiry-form] [name="message"]', 'Playwright integration inquiry.');
    await page.check('[data-inquiry-form] [name="consent"]');
    await page.click('[data-inquiry-form] button[type="submit"]');
    await expect(page.locator('[data-inquiry-form] [data-form-success]')).toBeVisible();
    await expect(page.locator('[data-inquiry-form] [data-form-success]')).toContainText('LD-');

    const date = new Date();
    date.setDate(date.getDate() + 3);
    while (date.getDay() === 0) date.setDate(date.getDate() + 1);
    const iso = date.toISOString().slice(0, 10);
    await page.fill('[data-appointment-form] [name="date"]', iso);
    const slot = page.locator('.estora-slot:not([disabled])').first();
    await expect(slot).toBeVisible();
    await slot.click();
    await page.fill('[data-appointment-form] [name="name"]', QA);
    await page.fill('[data-appointment-form] [name="phone"]', '+66 91 234 5679');
    await page.fill('[data-appointment-form] [name="email"]', QA_EMAIL);
    await page.check('[data-appointment-form] [name="consent"]');
    await page.click('[data-appointment-form] button[type="submit"]');
    await expect(page.locator('[data-appointment-form] [data-form-success]')).toContainText('AP-');
  });

  test('Thai and English switch persists across navigation', async ({ page }) => {
    await page.goto('/?lang=th');
    await expect(page.locator('html')).toHaveAttribute('lang', 'th');
    await expect(page.locator('h1')).toContainText('บ้านที่คุณไว้ใจได้');
    await page.goto('/properties/');
    await expect(page.locator('h1').first()).toContainText('ทรัพย์สิน');
    await expect(page.locator('.estora-card__title').first()).toContainText('บ้าน');
    await page.getByRole('link', { name: 'EN', exact: true }).first().click();
    await expect(page).toHaveURL(/lang=en/);
    await expect(page.locator('h1').first()).toContainText('Properties');
    await page.goto('/');
    await expect(page.locator('h1')).toContainText('A home you can trust');
  });

  test('public pages render without errors', async ({ page }) => {
    const errors = [];
    page.on('pageerror', (err) => errors.push(err.message));
    for (const path of ['/about/', '/contact/', '/news/', '/privacy/', '/terms/', '/not-a-real-page/']) {
      const response = await page.goto(path);
      expect(response.status(), `${path} status`).toBeLessThan(500);
      await expect(page.locator('h1').first()).toBeVisible();
    }
    expect(errors).toEqual([]);
  });
});
