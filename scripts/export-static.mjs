/**
 * Build a static portfolio demo of the ESTORA public site for Netlify.
 *
 * The WordPress install stays the source of truth. This script fetches the
 * rendered public pages (English and Thai), mirrors every referenced asset,
 * rewrites internal links to the static structure and adds a small demo layer
 * that keeps search, favourites, compare, inquiries and viewings usable
 * without PHP or MySQL.
 *
 * Usage: node scripts/export-static.mjs
 */
import fs from 'node:fs/promises';
import path from 'node:path';

const BASE = (process.env.ESTORA_BASE_URL || 'http://localhost:8080').replace(/\/$/, '');
const OUT = path.resolve('dist');
const DEMO = path.resolve('netlify-demo');
const REPO_URL = process.env.ESTORA_REPO_URL || 'https://github.com/Mindenry/estora';

const log = (...args) => console.log('  •', ...args);

const getJson = async (url) => {
  const response = await fetch(BASE + url);
  if (!response.ok) throw new Error(`${url} responded ${response.status}`);
  return response.json();
};

const toOutPath = (pathname) => {
  const clean = pathname.replace(/^\/+|\/+$/g, '');
  return clean ? `${clean}/index.html` : 'index.html';
};

const slimProperty = (property) => ({
  id: property.id,
  title: property.title,
  slug: property.slug,
  url: property.url,
  reference: property.reference,
  price: property.price,
  price_formatted: property.price_formatted,
  purpose: property.purpose,
  purpose_label: property.purpose_label,
  type: property.type,
  type_label: property.type_label,
  locations: property.locations || [],
  location: property.location,
  location_raw: property.location_raw,
  land_area: property.land_area,
  usable_area: property.usable_area,
  bedrooms: property.bedrooms,
  bathrooms: property.bathrooms,
  parking: property.parking,
  availability: property.availability,
  availability_label: property.availability_label,
  accepts_inquiries: property.accepts_inquiries,
  amenities: property.amenities,
  image: property.image,
  rent_period: property.rent_period,
  excerpt: property.excerpt,
  address: property.address,
  created_at: property.created_at
});

/* ------------------------------------------------------------------ data --- */
console.log('ESTORA static export');
const [propertiesEn, propertiesTh, posts, categories] = await Promise.all([
  getJson('/wp-json/estora/v1/properties?per_page=100&lang=en'),
  getJson('/wp-json/estora/v1/properties?per_page=100&lang=th'),
  getJson('/wp-json/wp/v2/posts?per_page=100&_fields=link,slug,date'),
  getJson('/wp-json/wp/v2/categories?per_page=100&_fields=link,slug,count')
]);

const cleanJson = (value) => JSON.parse(JSON.stringify(value).split(BASE).join(''));
const demoData = {
  en: cleanJson(propertiesEn.data.map(slimProperty)),
  th: cleanJson(propertiesTh.data.map(slimProperty))
};

/* ----------------------------------------------------------------- pages --- */
const pageDefs = [];
const basePages = [
  ['/', 'index.html'],
  ['/properties/', 'properties/index.html'],
  ['/news/', 'news/index.html'],
  ['/about/', 'about/index.html'],
  ['/contact/', 'contact/index.html'],
  ['/favorites/', 'favorites/index.html'],
  ['/compare/', 'compare/index.html'],
  ['/privacy/', 'privacy/index.html'],
  ['/terms/', 'terms/index.html']
];

for (const lang of ['en', 'th']) {
  for (const [source, out] of basePages) {
    pageDefs.push({ lang, source, out: lang === 'th' ? `th/${out}` : out });
  }
  for (const property of (lang === 'th' ? propertiesTh : propertiesEn).data) {
    const pathname = new URL(property.url).pathname;
    pageDefs.push({ lang, source: pathname, out: lang === 'th' ? `th/${toOutPath(pathname)}` : toOutPath(pathname) });
  }
  for (const post of posts) {
    const pathname = new URL(post.link).pathname;
    pageDefs.push({ lang, source: pathname, out: lang === 'th' ? `th/${toOutPath(pathname)}` : toOutPath(pathname) });
  }
  for (const category of categories) {
    if (!category.count) continue;
    const pathname = new URL(category.link).pathname;
    pageDefs.push({ lang, source: pathname, out: lang === 'th' ? `th/${toOutPath(pathname)}` : toOutPath(pathname) });
  }
}
pageDefs.push({ lang: 'en', source: '/this-page-does-not-exist/', out: '404.html', notFound: true });

/* --------------------------------------------------------------- helpers --- */
const mapUrl = (value, lang) => {
  if (!value) return value;
  if (/^(#|mailto:|tel:|javascript:|data:)/i.test(value)) return value;
  let url;
  try {
    url = new URL(value, BASE);
  } catch (error) {
    return value;
  }
  if (url.origin !== BASE) return value;

  const isAsset = /^\/(wp-content|wp-includes|wp-json|wp-admin|wp-login|demo)\//.test(url.pathname);
  let pathname = url.pathname;
  if (!isAsset) {
    if (lang === 'th') {
      if (pathname !== '/th' && !pathname.startsWith('/th/')) pathname = '/th' + (pathname === '/' ? '/' : pathname);
    } else if (pathname === '/th' || pathname.startsWith('/th/')) {
      pathname = pathname.slice(3) || '/';
    }
  }
  const params = new URLSearchParams(url.search);
  params.delete('lang');
  const query = params.toString();
  return pathname + (query ? '?' + query : '') + url.hash;
};

const badge = (lang) => lang === 'th'
  ? `<div class="estora-demo-badge"><span>เดโมพอร์ตโฟลิโอ · ฟอร์มเป็นข้อมูลจำลอง · <a href="${REPO_URL}" target="_blank" rel="noopener noreferrer">ซอร์สโค้ด</a></span></div>`
  : `<div class="estora-demo-badge"><span>Portfolio demo · forms are simulated · <a href="${REPO_URL}" target="_blank" rel="noopener noreferrer">Source</a></span></div>`;

const rewrite = (html, lang) => {
  let output = html.replace(/<script type="speculationrules">[\s\S]*?<\/script>/, '');
  output = output.replace(/<script[^>]+wp-emoji[^>]*>[\s\S]*?<\/script>/g, '');
  output = output.replace(/(href|action)=(["'])([^"']*)\2/g, (match, attr, quote, value) => {
    return attr + '=' + quote + mapUrl(value, lang) + quote;
  });
  output = output.replace(/content=(["'])(http:\/\/localhost:8080[^"']*)\1/g, (match, quote, value) => {
    return 'content=' + quote + mapUrl(value, lang) + quote;
  });
  output = output.split(BASE).join('');
  output = output.replace('</head>', '<link rel="stylesheet" href="/demo/demo.css">\n</head>');

  const mainMatch = output.match(/<script[^>]*\bid=["']estora-main-js["'][^>]*>/);
  if (mainMatch && mainMatch.index !== undefined) {
    const tagStart = mainMatch.index;
    const tagEnd = output.indexOf('</script>', tagStart) + '</script>'.length;
    const before = output.slice(0, tagStart);
    const mainTag = output.slice(tagStart, tagEnd);
    const after = output.slice(tagEnd);
    output = before +
      '<script src="/demo/demo-data.js"></script>\n<script src="/demo/demo-api.js"></script>\n' +
      mainTag +
      '\n<script src="/demo/demo-archive.js" defer></script>' +
      after;
  } else {
    output = output.replace('</body>', '<script src="/demo/demo-data.js"></script><script src="/demo/demo-api.js"></script><script src="/demo/demo-archive.js" defer></script></body>');
  }

  return output.replace('</body>', badge(lang) + '</body>');
};

/* --------------------------------------------------------------- assets --- */
const assetQueue = [];
const assetSeen = new Set();

const enqueueAsset = (raw, from) => {
  if (!raw) return;
  let url;
  try {
    url = new URL(raw, from || BASE + '/');
  } catch (error) {
    return;
  }
  if (url.origin !== BASE) return;
  if (!/^\/(wp-content|wp-includes)\//.test(url.pathname)) return;
  if (assetSeen.has(url.pathname)) return;
  assetSeen.add(url.pathname);
  assetQueue.push(url.pathname);
};

const collectAssets = (html) => {
  for (const match of html.matchAll(/(?:src|href)=["']([^"']+)["']/g)) enqueueAsset(match[1]);
  for (const match of html.matchAll(/url\((['"]?)([^)'"]+)\1\)/g)) enqueueAsset(match[2]);
  for (const match of html.matchAll(/srcset=["']([^"']+)["']/g)) {
    match[1].split(',').forEach((part) => enqueueAsset(part.trim().split(/\s+/)[0]));
  }
};

const mirrorAsset = async (pathname) => {
  const response = await fetch(BASE + pathname);
  if (!response.ok) {
    log('skip', pathname, response.status);
    return;
  }
  const buffer = Buffer.from(await response.arrayBuffer());
  const file = path.join(OUT, decodeURIComponent(pathname));
  await fs.mkdir(path.dirname(file), { recursive: true });
  await fs.writeFile(file, buffer);

  if (/\.css$/i.test(pathname)) {
    const text = buffer.toString('utf8');
    for (const match of text.matchAll(/url\((['"]?)([^)'"]+)\1\)/g)) {
      const ref = match[2];
      if (/^(data:|https?:)/i.test(ref)) continue;
      enqueueAsset(ref, BASE + pathname);
    }
  }
};

/* ----------------------------------------------------------------- write --- */
let rendered = 0;
for (const page of pageDefs) {
  const source = page.source + (page.lang === 'th' ? (page.source.includes('?') ? '&' : '?') + 'lang=th' : '');
  const response = await fetch(BASE + source, { redirect: 'follow' });
  const html = await response.text();
  collectAssets(html);

  const file = path.join(OUT, page.out);
  await fs.mkdir(path.dirname(file), { recursive: true });
  await fs.writeFile(file, rewrite(html, page.lang));
  rendered += 1;
  if (rendered % 12 === 0) log('rendered', rendered, 'pages');
}

log('rendered', rendered, 'pages');

let mirrored = 0;
for (let index = 0; index < assetQueue.length; index += 1) {
  await mirrorAsset(assetQueue[index]);
  mirrored += 1;
  if (mirrored % 25 === 0) log('mirrored', mirrored, 'assets');
}
log('mirrored', mirrored, 'assets');

/* ------------------------------------------------------------- demo layer --- */
await fs.mkdir(path.join(OUT, 'demo'), { recursive: true });
for (const file of ['demo.css', 'demo-api.js', 'demo-archive.js']) {
  await fs.copyFile(path.join(DEMO, file), path.join(OUT, 'demo', file));
}
await fs.writeFile(
  path.join(OUT, 'demo', 'demo-data.js'),
  'window.ESTORA_DEMO = ' + JSON.stringify(demoData) + ';\n'
);

await fs.writeFile(path.join(OUT, '_redirects'), '/*  /404.html  404\n');
await fs.writeFile(path.join(OUT, 'robots.txt'), 'User-agent: *\nAllow: /\n');
await fs.writeFile(
  path.join(OUT, 'demo', 'README.txt'),
  'Static portfolio build of the ESTORA public site. WordPress, the CRM and the back office are not part of this demo.\n' +
  'Search, filters, favourites, compare, the payment estimator and both languages work in the browser against bundled data.\n' +
  'Inquiry and viewing forms are simulated and do not send anything.\n'
);

const files = await fs.readdir(OUT, { recursive: true });
log('dist ready:', files.length, 'entries');
