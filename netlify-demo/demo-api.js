/* ESTORA static portfolio demo — REST shim.
 *
 * The real site talks to /wp-json/estora/v1. On Netlify there is no PHP, so this
 * file answers the same endpoints from the bundled demo data and localStorage.
 * It is loaded before the theme's main.js so `window.fetch` is already patched.
 */
(() => {
  'use strict';

  const params = new URLSearchParams(window.location.search);
  const requested = params.get('lang');
  if (requested === 'th' && !window.location.pathname.startsWith('/th/')) {
    params.delete('lang');
    const query = params.toString();
    window.location.replace('/th' + window.location.pathname + (query ? '?' + query : '') + window.location.hash);
    return;
  }
  if (requested === 'en' && window.location.pathname.startsWith('/th/')) {
    params.delete('lang');
    const query = params.toString();
    window.location.replace((window.location.pathname.slice(3) || '/') + (query ? '?' + query : '') + window.location.hash);
    return;
  }

  const demo = window.ESTORA_DEMO || { en: [], th: [] };
  const lang = window.ESTORA_DEMO_LANG || (document.documentElement.lang === 'th' ? 'th' : 'en');
  const properties = Array.isArray(demo[lang]) ? demo[lang] : [];
  const byId = new Map(properties.map((property) => [Number(property.id), property]));
  const FAVOURITES = 'estora_demo_favorites';
  const realFetch = window.fetch.bind(window);

  const readFavourites = () => {
    try {
      const raw = JSON.parse(window.localStorage.getItem(FAVOURITES) || '[]');
      return Array.isArray(raw) ? raw.map(Number).filter(Boolean) : [];
    } catch (error) {
      return [];
    }
  };
  const writeFavourites = (ids) => {
    try {
      window.localStorage.setItem(FAVOURITES, JSON.stringify(ids));
    } catch (error) {
      /* private mode */
    }
  };
  const respond = (body, status = 200) => Promise.resolve(new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json; charset=utf-8' }
  }));
  const reference = (prefix) => prefix + '-' + Math.random().toString(36).slice(2, 8).toUpperCase();
  const pad = (value) => String(value).padStart(2, '0');

  const slotsFor = (date) => {
    if (!date) return { closed: true, message: '', slots: [] };
    const day = new Date(date + 'T00:00:00').getDay();
    if (day === 0) return { closed: true, message: '', slots: [] };
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    if (new Date(date + 'T00:00:00') < today) return { closed: false, message: '', slots: [] };

    const slots = [];
    for (let hour = 9; hour < 18; hour += 1) {
      for (const minute of [0, 45]) {
        if (hour === 12) continue;
        if (hour === 17 && minute === 45) continue;
        const start = pad(hour) + ':' + pad(minute);
        slots.push({ start, available: true });
      }
    }
    return { closed: false, message: '', slots };
  };

  window.fetch = function demoFetch(input, init) {
    const raw = typeof input === 'string' ? input : (input && input.url) || '';
    let url;
    try {
      url = new URL(raw, window.location.origin);
    } catch (error) {
      return realFetch(input, init);
    }

    const marker = '/wp-json/estora/v1';
    if (!url.pathname.startsWith(marker)) return realFetch(input, init);

    const path = url.pathname.slice(marker.length) || '/';
    const method = ((init && init.method) || 'GET').toUpperCase();

    if (path === '/favorites' && method === 'GET') {
      const ids = readFavourites();
      const items = ids.map((id) => byId.get(id)).filter(Boolean);
      return respond({ success: true, data: { ids, properties: items }, message: '' });
    }

    if (path === '/favorites' && method === 'POST') {
      let body = {};
      try { body = JSON.parse((init && init.body) || '{}'); } catch (error) { body = {}; }
      const id = Number(body.property_id);
      const ids = readFavourites();
      if (id && !ids.includes(id)) ids.push(id);
      writeFavourites(ids);
      return respond({ success: true, data: { favorited: true, count: ids.length, ids }, message: '' });
    }

    if (/^\/favorites\/\d+$/.test(path) && method === 'DELETE') {
      const id = Number(path.split('/').pop());
      const ids = readFavourites().filter((value) => value !== id);
      writeFavourites(ids);
      return respond({ success: true, data: { favorited: false, count: ids.length, ids }, message: '' });
    }

    if (/^\/properties\/\d+$/.test(path)) {
      const id = Number(path.split('/').pop());
      const property = byId.get(id);
      return property
        ? respond({ success: true, data: property, message: '' })
        : respond({ success: false, data: null, message: 'Not found' }, 404);
    }

    if (path === '/properties') {
      return respond({ success: true, data: properties, message: '', meta: { pagination: { page: 1, per_page: properties.length, total: properties.length, total_pages: 1 } } });
    }

    if (path === '/appointments/availability') {
      const date = url.searchParams.get('date') || '';
      return respond({ success: true, data: slotsFor(date), message: '' });
    }

    if (path === '/appointments' && method === 'POST') {
      const ref = reference('AP');
      return respond({ success: true, data: { public_ref: ref, reference: ref }, message: archive().viewingRequested });
    }

    if (path === '/inquiries' && method === 'POST') {
      const ref = reference('LD');
      return respond({ success: true, data: { lead_ref: ref, public_ref: ref, reference: ref }, message: archive().inquiryReceived });
    }

    return respond({ success: true, data: {}, message: '' });
  };

  function archive() {
    return lang === 'th'
      ? { viewingRequested: 'ส่งคำขอนัดชมแล้ว', inquiryReceived: 'ได้รับคำถามแล้ว' }
      : { viewingRequested: 'Viewing requested', inquiryReceived: 'Inquiry received' };
  }
})();
