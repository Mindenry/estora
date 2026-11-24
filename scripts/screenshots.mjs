import { chromium } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const BASE = process.env.ESTORA_BASE_URL || 'http://localhost:8080';
const outDir = path.resolve('docs/screenshots');
fs.mkdirSync(outDir, { recursive: true });

const browser = await chromium.launch();
const errors = [];

function watch(page) {
  page.on('console', (msg) => { if (msg.type() === 'error') errors.push(msg.text()); });
  page.on('pageerror', (err) => errors.push(`pageerror: ${err.message}`));
  return page;
}

const publicContext = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
const publicPage = watch(await publicContext.newPage());

const shot = async (page, name, { fullPage = false } = {}) => {
  await page.waitForTimeout(500);
  await page.screenshot({ path: path.join(outDir, `${name}.png`), fullPage });
  console.log('  captured', name);
};

const visit = async (page, url) => {
  await page.goto(BASE + url, { waitUntil: 'networkidle' });
};

await visit(publicPage, '/');
await shot(publicPage, 'home');
await shot(publicPage, 'home-full', { fullPage: true });

await visit(publicPage, '/properties/?availability=available');
await shot(publicPage, 'properties');
await publicPage.locator('.estora-card__title a').first().click();
await publicPage.waitForLoadState('networkidle');
await shot(publicPage, 'property-detail');
await shot(publicPage, 'property-detail-full', { fullPage: true });

const detailFav = publicPage.locator('.estora-property-head [data-favorite]');
if ((await detailFav.getAttribute('aria-pressed')) !== 'true') {
  await detailFav.click();
  await publicPage.waitForTimeout(500);
}
await visit(publicPage, '/favorites/');
await publicPage.waitForSelector('.estora-card');
await shot(publicPage, 'favorites');

await visit(publicPage, '/properties/?availability=available');
await publicPage.locator('[data-compare]').nth(0).click();
await publicPage.locator('[data-compare]').nth(1).click();
await visit(publicPage, '/compare/');
await publicPage.waitForSelector('.estora-compare');
await shot(publicPage, 'compare');

await visit(publicPage, '/about/');
await shot(publicPage, 'about');

// Mobile public views (no admin bar).
await publicPage.setViewportSize({ width: 390, height: 844 });
await visit(publicPage, '/');
await shot(publicPage, 'home-mobile');
await visit(publicPage, '/properties/');
await shot(publicPage, 'properties-mobile');
await publicPage.click('[data-nav-toggle]');
await shot(publicPage, 'home-mobile-nav');

// Back office in a separate authenticated context.
const adminContext = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
const adminPage = watch(await adminContext.newPage());
await adminPage.goto(BASE + '/wp-login.php');
await adminPage.fill('#user_login', 'admin');
await adminPage.fill('#user_pass', 'Estora!Admin!2026');
await Promise.all([adminPage.waitForURL(/wp-admin/), adminPage.click('#wp-submit')]);

await adminPage.goto(BASE + '/wp-admin/admin.php?page=estora-dashboard');
await adminPage.waitForSelector('.estora-kpi');
await shot(adminPage, 'admin-dashboard');

await adminPage.goto(BASE + '/wp-admin/admin.php?page=estora-leads');
await adminPage.waitForSelector('[data-leads-table] tbody tr');
await shot(adminPage, 'admin-leads');
await adminPage.locator('[data-leads-table] tbody tr').first().click();
await adminPage.waitForSelector('[data-drawer-status]');
await shot(adminPage, 'admin-lead-detail');
await adminPage.click('[data-close-drawer]');
await adminPage.click('[data-leads-view="board"]');
await adminPage.waitForSelector('.estora-kanban-card');
await shot(adminPage, 'admin-kanban');

await adminPage.goto(BASE + '/wp-admin/admin.php?page=estora-appointments');
await adminPage.waitForSelector('[data-calendar] .estora-day');
await shot(adminPage, 'admin-appointments');

await adminPage.goto(BASE + '/wp-admin/admin.php?page=estora-inventory');
await adminPage.waitForSelector('[data-inventory-table] tbody tr');
await shot(adminPage, 'admin-inventory');

await adminPage.goto(BASE + '/wp-admin/admin.php?page=estora-reports');
await adminPage.waitForSelector('[data-report-kpis] .estora-kpi');
await shot(adminPage, 'admin-reports');

await adminPage.goto(BASE + '/wp-admin/admin.php?page=estora-settings');
await adminPage.waitForSelector('[data-settings-form]');
await shot(adminPage, 'admin-settings');

await adminPage.setViewportSize({ width: 390, height: 844 });
await adminPage.goto(BASE + '/wp-admin/admin.php?page=estora-dashboard');
await adminPage.waitForSelector('.estora-kpi');
await shot(adminPage, 'admin-dashboard-mobile');

console.log('console errors:', errors);
await browser.close();
