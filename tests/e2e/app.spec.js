const { test, expect } = require('@playwright/test');

const BASE = process.env.ESTORA_BASE_URL || 'http://localhost:8080';

async function login(page, email = 'admin@estora.test', password = 'Estora!Admin!2026') {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', email);
  await page.fill('#user_pass', password);
  await Promise.all([page.waitForURL(/estora-admin/), page.click('#wp-submit')]);
}

test.describe('ESTORA application', () => {
  test('each role lands on its own dashboard', async ({ browser }) => {
    const roles = [
      ['admin@estora.test', 'Super Admin', 'Estora!Admin!2026', true],
      ['operations@estora.test', 'Administrator', 'Estora!Agent!2026', false],
      ['manager@estora.test', 'Sales Manager', 'Estora!Agent!2026', false],
      ['agent@estora.test', 'Sales Agent', 'Estora!Agent!2026', false],
      ['property@estora.test', 'Property Manager', 'Estora!Agent!2026', false],
      ['marketing@estora.test', 'Marketing Staff', 'Estora!Agent!2026', false]
    ];

    for (const [email, label, password, hasWpAdmin] of roles) {
      const context = await browser.newContext();
      const page = await context.newPage();
      await login(page, email, password);
      await expect(page).toHaveURL(/\/estora-admin\/$/);
      await expect(page.locator('.estora-usermenu__meta small')).toHaveText(label);
      await expect(page.locator('[data-kpis] .estora-kpi').first()).toBeVisible();
      const nav = page.locator('.estora-navlink span');
      if (hasWpAdmin) {
        await expect(nav.filter({ hasText: 'Settings' })).toBeVisible();
      }
      if (email.startsWith('agent@')) {
        await expect(nav.filter({ hasText: 'Settings' })).toHaveCount(0);
        await expect(nav.filter({ hasText: 'Reports' })).toHaveCount(0);
      }
      if (email.startsWith('marketing@')) {
        await expect(nav.filter({ hasText: 'Leads' })).toHaveCount(0);
        await expect(nav.filter({ hasText: 'Content' })).toBeVisible();
      }
      await context.close();
    }
  });

  test('navigation, search and permission boundary', async ({ page }) => {
    await login(page);
    await page.click('.estora-navlink[href$="/leads/"]');
    await expect(page).toHaveURL(/\/estora-admin\/leads\/$/);
    await expect(page.locator('[data-leads-table] tbody tr').first()).toBeVisible();

    await page.fill('#estora-app-search', 'Courtyard');
    await page.press('#estora-app-search', 'Enter');
    await expect(page).toHaveURL(/q=Courtyard/);
    await expect(page.locator('[data-lead-search]')).toHaveValue('Courtyard');

    await page.goto(BASE + '/estora-admin/pipeline/');
    await expect(page.locator('[data-leads-board]')).toBeVisible();
    await expect(page.locator('.estora-kanban-card').first()).toBeVisible();

    await page.goto(BASE + '/estora-admin/unknown-module/');
    await expect(page.locator('.estora-appstate')).toContainText('does not exist');
  });

  test('staff booking dialog and appointment actions', async ({ page }) => {
    await login(page);
    await page.goto('/estora-admin/appointments/');
    await page.click('[data-new-appointment]');
    const dialog = page.locator('[data-staff-appointment-dialog]');
    await expect(dialog).toBeVisible();
    const property = dialog.locator('[data-staff-property]');
    await expect(property.locator('option').nth(1)).toBeAttached({ timeout: 15000 });
    const value = await property.locator('option').nth(1).getAttribute('value');
    await property.selectOption(value);

    const date = new Date();
    date.setDate(date.getDate() + 4);
    while (date.getDay() === 0) date.setDate(date.getDate() + 1);
    await dialog.locator('[name="date"]').fill(date.toISOString().slice(0, 10));
    await expect(dialog.locator('[data-staff-slot]').first()).toBeVisible({ timeout: 15000 });
    await dialog.locator('[data-staff-slot]:not([disabled])').first().click();
    await dialog.locator('[name="name"]').fill('QA E2E Staff Viewing');
    await dialog.locator('[name="phone"]').fill('+66 91 555 7777');
    await dialog.locator('button[type="submit"]').click();
    await expect(dialog).toBeHidden({ timeout: 15000 });
  });

  test('dashboard period filter and quick actions', async ({ page }) => {
    await login(page);
    const summary = page.locator('[data-range-summary]');
    await expect(summary).toBeVisible();
    await page.selectOption('[data-dashboard-range]', '7');
    await page.waitForTimeout(900);
    await expect(summary).toContainText('leads captured');
    await expect(page.locator('[data-kpis] .estora-kpi').first()).toBeVisible();

    await page.getByRole('link', { name: /Add lead/i }).first().click();
    await expect(page).toHaveURL(/\/estora-admin\/leads\//);
    await expect(page.locator('[data-lead-dialog]')).toBeVisible();
    await page.locator('[data-close-dialog]').click();
  });

  test('logout and mobile drawer', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    expect(overflow).toBeLessThanOrEqual(1);
    await page.click('[data-app-nav-toggle]');
    await expect(page.locator('.estora-sidebar')).toBeVisible();
    await expect(page.locator('.estora-navlink').filter({ hasText: 'Appointments' })).toBeVisible();
    await page.locator('.estora-app-scrim').click({ force: true });
    await page.click('[data-user-menu-toggle]');
    await expect(page.locator('[data-user-menu-panel]')).toBeVisible();
    const logout = page.locator('[data-user-menu-panel] a', { hasText: 'Log out' });
    await logout.click();
    await expect(page).toHaveURL(/wp-login\.php/);
  });
});
