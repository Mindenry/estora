const { test, expect } = require('@playwright/test');

async function login(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'Estora!Admin!2026');
  await Promise.all([page.waitForURL(/estora-admin/), page.click('#wp-submit')]);
}

test.describe('Back office', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('dashboard shows database figures and notifications', async ({ page }) => {
    await page.goto('/estora-admin/');
    await expect(page.locator('.estora-head h1')).toHaveText('Dashboard');
    await expect(page.locator('.estora-kpi').first()).toBeVisible();
    await expect(page.locator('[data-lead-trend] svg')).toBeVisible();
    const values = await page.locator('.estora-kpi__value').allInnerTexts();
    expect(values.length).toBeGreaterThanOrEqual(6);
    for (const value of values) {
      expect(value.trim()).not.toBe('—');
    }
    await page.click('[data-notifications-toggle]');
    await expect(page.locator('[data-notifications-panel]')).toBeVisible();
  });

  test('lead list, drawer, note and stage control', async ({ page }) => {
    await page.goto('/estora-admin/leads/');
    await expect(page.locator('[data-leads-table] tbody tr').first()).toBeVisible();
    await page.locator('[data-leads-table] tbody tr').first().click();
    const drawer = page.locator('[data-lead-drawer]');
    await expect(drawer).toBeVisible();
    await expect(drawer.locator('[data-drawer-status]')).toBeVisible();
    const note = `QA E2E note ${Date.now()}`;
    await drawer.locator('[data-drawer-note] textarea').fill(note);
    await drawer.locator('[data-drawer-note] button[type="submit"]').click();
    await expect(drawer.locator('.estora-note__body', { hasText: note })).toBeVisible();
    await page.click('[data-close-drawer]');
    await expect(drawer).toBeHidden();

    await page.click('[data-leads-view="board"]');
    await expect(page.locator('.estora-column').first()).toBeVisible();
    await expect(page.locator('.estora-kanban-card').first()).toBeVisible();
  });

  test('appointments calendar and list', async ({ page }) => {
    await page.goto('/estora-admin/appointments/');
    await expect(page.locator('[data-calendar] .estora-day').first()).toBeVisible();
    await expect(page.locator('[data-appointment-table] tbody tr').first()).toBeVisible();
    await page.click('[data-calendar-view="week"]');
    await expect(page.locator('[data-calendar] [data-day]')).toHaveCount(7);
    await page.click('[data-calendar-view="day"]');
    await expect(page.locator('[data-calendar] .estora-day').first()).toBeVisible();
  });

  test('inventory, reports and settings screens', async ({ page }) => {
    await page.goto('/estora-admin/properties/');
    await expect(page.locator('[data-inventory-table] tbody tr').first()).toBeVisible();
    await expect(page.locator('[data-inventory-summary] .estora-kpi').first()).toBeVisible();

    await page.goto('/estora-admin/reports/');
    await expect(page.locator('[data-report-kpis] .estora-kpi').first()).toBeVisible();
    const exportHref = await page.locator('.estora-exports__buttons a').first().getAttribute('href');
    expect(exportHref).toContain('admin-post.php?action=estora_export');

    await page.goto('/estora-admin/settings/');
    await expect(page.locator('[data-settings-form] [name="company_name"]')).toHaveValue('ESTORA');
    await page.goto('/estora-admin/activity/');
    await expect(page.locator('[data-audit-table]')).toBeVisible();
  });
});
