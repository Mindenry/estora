import { chromium } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const dir = path.resolve('wp-content/themes/estora-theme/assets/img');
const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1600, height: 1000 }, deviceScaleFactor: 1 });

for (const name of ['plate-1', 'plate-2', 'plate-3', 'plate-4', 'plate-5', 'plate-6']) {
  const svg = fs.readFileSync(path.join(dir, `${name}.svg`), 'utf8');
  const data = `data:image/svg+xml;base64,${Buffer.from(svg).toString('base64')}`;
  await page.setViewportSize({ width: 1600, height: 1000 });
  await page.setContent(`<style>html,body{margin:0;width:100%;height:100%;overflow:hidden}img{width:100vw;height:100vh;object-fit:cover;display:block}</style><img src="${data}">`);
  await page.waitForTimeout(120);
  await page.screenshot({ path: path.join(dir, `${name}.png`) });
  console.log('rasterised', name);
}

const heroSvg = fs.readFileSync(path.join(dir, 'hero.svg'), 'utf8');
const heroData = `data:image/svg+xml;base64,${Buffer.from(heroSvg).toString('base64')}`;
await page.setViewportSize({ width: 2400, height: 1400 });
await page.setContent(`<style>html,body{margin:0;width:100%;height:100%;overflow:hidden}img{width:100vw;height:100vh;object-fit:cover;display:block}</style><img src="${heroData}">`);
await page.waitForTimeout(120);
await page.screenshot({ path: path.join(dir, 'hero.png') });
console.log('rasterised hero');

await browser.close();
