/**
 * Smoke-test the exported Netlify demo against a static server.
 *
 *   npm run export:static
 *   npm run serve:dist
 *   npm run test:static
 */
import assert from 'node:assert/strict';
import { chromium } from '@playwright/test';

const BASE = (process.env.ESTORA_STATIC_URL || 'http://localhost:8090').replace(/\/$/, '');
const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
const page = await context.newPage();
const errors = [];
const failed = [];

page.on('pageerror', (error) => errors.push(error.message));
page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
page.on('response', (response) => { if (response.status() >= 400) failed.push(response.status() + ' ' + response.url()); });

const check = async (label, fn) => {
  try {
    await fn();
    console.log('  ok  ' + label);
  } catch (error) {
    console.error('  FAIL ' + label + ': ' + error.message);
    process.exitCode = 1;
  }
};

console.log('ESTORA static demo · ' + BASE);

await check('English home renders with local fonts', async () => {
  const response = await page.goto(BASE + '/', { waitUntil: 'networkidle' });
  assert.equal(response.status(), 200);
  assert.match(await page.locator('h1').innerText(), /A home you can trust/);
  assert.equal(await page.evaluate(() => document.fonts.check('16px Fraunces')), true);
});

await check('Thai home renders', async () => {
  await page.goto(BASE + '/th/', { waitUntil: 'networkidle' });
  assert.equal(await page.locator('html').getAttribute('lang'), 'th');
  assert.match(await page.locator('h1').innerText(), /บ้านที่คุณไว้ใจได้/);
});

await check('archive filters by province and area', async () => {
  await page.goto(BASE + '/properties/', { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);
  assert.equal(await page.locator('.estora-card').count(), 9);
  await page.selectOption('#estora-province', 'Bangkok');
  await page.selectOption('#estora-district', 'Watthana');
  await page.waitForTimeout(1200);
  assert.match(page.url(), /province=Bangkok/);
  assert.match(page.url(), /district=Watthana/);
  assert.equal(await page.locator('.estora-card').count(), 3);
});

await check('sort is applied in the browser', async () => {
  await page.goto(BASE + '/properties/', { waitUntil: 'networkidle' });
  await page.selectOption('#estora-sort', 'price_desc');
  await page.waitForTimeout(1200);
  assert.match(await page.locator('.estora-card__price').first().innerText(), /28,500,000/);
});

await check('favourites and comparison survive navigation', async () => {
  await page.goto(BASE + '/properties/', { waitUntil: 'networkidle' });
  await page.waitForTimeout(400);
  await page.locator('[data-favorite]').first().click();
  await page.locator('[data-compare]').first().click();
  await page.locator('[data-compare]').nth(1).click();
  await page.waitForTimeout(400);
  await page.goto(BASE + '/favorites/', { waitUntil: 'networkidle' });
  await page.waitForTimeout(600);
  assert.equal(await page.locator('.estora-card').count(), 1);
  await page.goto(BASE + '/compare/', { waitUntil: 'networkidle' });
  await page.waitForTimeout(600);
  assert.ok(await page.locator('.estora-compare tbody tr').count() >= 10);
});

await check('payment estimate and purchase costs calculate', async () => {
  await page.goto(BASE + '/properties/courtyard-house-nimman/', { waitUntil: 'networkidle' });
  assert.match(await page.locator('[data-calc-out]').innerText(), /63,207/);
  assert.match(await page.locator('[data-calc-cash]').innerText(), /2,656,250/);
  await page.fill('[data-calc-rate]', '5');
  await page.locator('[data-calc-rate]').dispatchEvent('input');
  await page.waitForTimeout(200);
  assert.notEqual(await page.locator('[data-calc-out]').innerText(), '฿63,207');
});

await check('inquiry and viewing forms are simulated successfully', async () => {
  await page.goto(BASE + '/contact/', { waitUntil: 'networkidle' });
  await page.fill('[data-inquiry-form] [name="name"]', 'Demo Visitor');
  await page.fill('[data-inquiry-form] [name="phone"]', '0800000000');
  await page.fill('[data-inquiry-form] [name="message"]', 'Static demo check.');
  await page.check('[data-inquiry-form] [name="consent"]');
  await page.click('[data-inquiry-form] button[type="submit"]');
  await page.waitForTimeout(500);
  assert.match(await page.locator('[data-inquiry-form] [data-form-success]').innerText(), /LD-/);

  await page.goto(BASE + '/properties/courtyard-house-nimman/', { waitUntil: 'networkidle' });
  const date = new Date(Date.now() + 86400000 * 3).toISOString().slice(0, 10);
  await page.fill('[data-appointment-form] [name="date"]', date);
  await page.waitForTimeout(500);
  assert.ok(await page.locator('.estora-slot').count() > 0);
  await page.locator('.estora-slot').first().click();
  await page.fill('[data-appointment-form] [name="name"]', 'Demo Visitor');
  await page.fill('[data-appointment-form] [name="phone"]', '0800000000');
  await page.check('[data-appointment-form] [name="consent"]');
  await page.click('[data-appointment-form] button[type="submit"]');
  await page.waitForTimeout(500);
  assert.match(await page.locator('[data-appointment-form] [data-form-success]').innerText(), /AP-/);
});

await check('journal and article pages render', async () => {
  await page.goto(BASE + '/news/', { waitUntil: 'networkidle' });
  assert.equal(await page.locator('.estora-article-card').count(), 7);
  await page.locator('.estora-journal-feature h3 a').first().click();
  await page.waitForLoadState('networkidle');
  assert.ok((await page.locator('h1').innerText()).length > 8);
});

await check('mobile layout has no horizontal overflow', async () => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(BASE + '/', { waitUntil: 'networkidle' });
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth), 0);
});

assert.deepEqual(errors, [], 'browser errors: ' + errors.join(' | '));
assert.deepEqual([...new Set(failed)], [], 'failed requests: ' + [...new Set(failed)].join(' | '));

await browser.close();
console.log(process.exitCode ? 'Static demo checks failed.' : 'Static demo checks passed.');
