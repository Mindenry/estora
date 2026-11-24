import { chromium } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const BASE = process.env.ESTORA_BASE_URL || 'http://localhost:8080';
const out = path.resolve('docs/screenshots');
fs.mkdirSync(out, { recursive: true });

const browser = await chromium.launch();
const errors = [];
const watch = (page) => {
  page.on('console', (msg) => { if (msg.type() === 'error') errors.push(`${page.url()} :: ${msg.text()}`); });
  page.on('pageerror', (err) => errors.push(`${page.url()} :: ${err.message}`));
  return page;
};
const shot = async (page, name, fullPage = false) => {
  await page.waitForTimeout(500);
  if (fullPage) {
    await page.evaluate(async () => {
      const step = Math.max(320, Math.floor(window.innerHeight * 0.8));
      for (let y = 0; y < document.body.scrollHeight; y += step) {
        window.scrollTo(0, y);
        await new Promise((resolve) => setTimeout(resolve, 70));
      }
      window.scrollTo(0, 0);
    });
    await page.waitForTimeout(650);
  }
  await page.screenshot({ path: path.join(out, `${name}.png`), fullPage });
  console.log('  captured', name);
};
const loadLazy = async (page) => {
  await page.evaluate(async () => {
    const step = Math.max(320, Math.floor(window.innerHeight * 0.8));
    for (let y = 0; y < document.body.scrollHeight; y += step) {
      window.scrollTo(0, y);
      await new Promise((resolve) => setTimeout(resolve, 80));
    }
    window.scrollTo(0, 0);
    await new Promise((resolve) => setTimeout(resolve, 120));
  });
  await page.waitForTimeout(400);
};
const login = async (page, email = 'admin@estora.test', password = 'Estora!Admin!2026') => {
  await page.goto(BASE + '/wp-login.php');
  await page.fill('#user_login', email);
  await page.fill('#user_pass', password);
  await Promise.all([page.waitForURL(/estora-admin/), page.click('#wp-submit')]);
  await page.waitForSelector('.estora-shell');
};

/* Public site */
const publicContext = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
const publicPage = watch(await publicContext.newPage());
await publicPage.goto(BASE + '/', { waitUntil: 'networkidle' });
await shot(publicPage, 'home');
await loadLazy(publicPage);
await shot(publicPage, 'home-full', true);
await publicPage.goto(BASE + '/properties/?availability=available', { waitUntil: 'networkidle' });
await shot(publicPage, 'properties');
await publicPage.locator('.estora-card__title a').first().click();
await publicPage.waitForLoadState('networkidle');
await shot(publicPage, 'property-detail');
await loadLazy(publicPage);
await shot(publicPage, 'property-detail-full', true);
const detailFav = publicPage.locator('.estora-property-head [data-favorite]');
if ((await detailFav.getAttribute('aria-pressed')) !== 'true') {
  await detailFav.click();
  await publicPage.waitForTimeout(400);
}
await publicPage.goto(BASE + '/favorites/', { waitUntil: 'networkidle' });
await publicPage.waitForSelector('.estora-card');
await shot(publicPage, 'favorites');
await publicPage.goto(BASE + '/properties/?availability=available', { waitUntil: 'networkidle' });
await publicPage.locator('[data-compare]').nth(0).click();
await publicPage.locator('[data-compare]').nth(1).click();
await publicPage.goto(BASE + '/compare/', { waitUntil: 'networkidle' });
await publicPage.waitForSelector('.estora-compare');
await shot(publicPage, 'compare');
await publicPage.goto(BASE + '/about/', { waitUntil: 'networkidle' });
await shot(publicPage, 'about');
await publicPage.goto(BASE + '/news/', { waitUntil: 'networkidle' });
await shot(publicPage, 'news', true);
await publicPage.locator('.estora-journal-feature h3 a').first().click();
await publicPage.waitForLoadState('networkidle');
await shot(publicPage, 'article', true);
await publicPage.setViewportSize({ width: 390, height: 844 });
await publicPage.goto(BASE + '/', { waitUntil: 'networkidle' });
await shot(publicPage, 'home-mobile');
await publicPage.click('[data-nav-toggle]');
await shot(publicPage, 'home-mobile-nav');
await publicPage.click('[data-nav-toggle]');
await publicPage.goto(BASE + '/news/', { waitUntil: 'networkidle' });
await shot(publicPage, 'news-mobile', true);

/* Thai edition (separate context so the language cookie is isolated) */
const thaiContext = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
const thaiPage = watch(await thaiContext.newPage());
await thaiPage.goto(BASE + '/?lang=th', { waitUntil: 'networkidle' });
await shot(thaiPage, 'home-th');
await loadLazy(thaiPage);
await shot(thaiPage, 'home-th-full', true);
await thaiPage.goto(BASE + '/properties/?lang=th', { waitUntil: 'networkidle' });
await shot(thaiPage, 'properties-th');
await thaiPage.locator('.estora-card__title a').first().click();
await thaiPage.waitForLoadState('networkidle');
await shot(thaiPage, 'property-detail-th');
await thaiPage.goto(BASE + '/news/?lang=th', { waitUntil: 'networkidle' });
await shot(thaiPage, 'news-th', true);
await thaiContext.close();

/* Login screen (fresh context) */
const loginContext = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
const loginPage = watch(await loginContext.newPage());
await loginPage.goto(BASE + '/wp-login.php');
await shot(loginPage, 'login');

/* Role dashboards */
const roles = {
  'role-admin': ['admin@estora.test', 'Estora!Admin!2026'],
  'role-operations': ['operations@estora.test', 'Estora!Agent!2026'],
  'role-manager': ['manager@estora.test', 'Estora!Agent!2026'],
  'role-agent': ['agent@estora.test', 'Estora!Agent!2026'],
  'role-property': ['property@estora.test', 'Estora!Agent!2026'],
  'role-marketing': ['marketing@estora.test', 'Estora!Agent!2026']
};
for (const [name, [email, password]] of Object.entries(roles)) {
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = watch(await context.newPage());
  await login(page, email, password);
  await page.waitForTimeout(900);
  await shot(page, name, true);
  await context.close();
}

/* Admin app modules */
const adminContext = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
const adminPage = watch(await adminContext.newPage());
await login(adminPage);

const modules = [
  ['app-overview', '/estora-admin/'],
  ['app-properties', '/estora-admin/properties/'],
  ['app-leads', '/estora-admin/leads/'],
  ['app-kanban', '/estora-admin/pipeline/'],
  ['app-appointments', '/estora-admin/appointments/'],
  ['app-agents', '/estora-admin/agents/'],
  ['app-locations', '/estora-admin/locations/'],
  ['app-campaigns', '/estora-admin/campaigns/'],
  ['app-content', '/estora-admin/content/'],
  ['app-reports', '/estora-admin/reports/'],
  ['app-settings', '/estora-admin/settings/'],
  ['app-users', '/estora-admin/users/'],
  ['app-activity', '/estora-admin/activity/'],
  ['app-notifications', '/estora-admin/notifications/']
];
for (const [name, route] of modules) {
  await adminPage.goto(BASE + route, { waitUntil: 'networkidle' });
  await adminPage.waitForTimeout(700);
  await shot(adminPage, name, true);
}

await adminPage.goto(BASE + '/estora-admin/leads/', { waitUntil: 'networkidle' });
await adminPage.waitForSelector('[data-leads-table] tbody tr');
await adminPage.locator('[data-leads-table] tbody tr').first().click();
await adminPage.waitForSelector('[data-drawer-status]');
await shot(adminPage, 'app-lead-detail');

/* Native property editor (media and content still WordPress) */
const propertyResponse = await adminPage.request.get(BASE + '/wp-json/estora/v1/properties?per_page=1');
const property = (await propertyResponse.json()).data[0];
await adminPage.goto(`${BASE}/wp-admin/post.php?post=${property.id}&action=edit`, { waitUntil: 'networkidle' });
await adminPage.waitForSelector('#estora-property-details');
await adminPage.locator('#estora-property-details').scrollIntoViewIfNeeded();
await shot(adminPage, 'app-property-editor');

/* Mobile app */
await adminPage.setViewportSize({ width: 390, height: 844 });
await adminPage.goto(BASE + '/estora-admin/', { waitUntil: 'networkidle' });
await adminPage.waitForSelector('[data-kpis] .estora-kpi');
await shot(adminPage, 'app-mobile-dashboard', true);
await adminPage.click('[data-app-nav-toggle]');
await shot(adminPage, 'app-mobile-nav');

console.log('console errors:', errors);
await browser.close();
