/* ESTORA static portfolio demo — archive filtering.
 *
 * The WordPress archive filters server-side. On Netlify the same query string is
 * applied in the browser against the bundled property data, so search, province,
 * area, price, availability, sorting and pagination all still work.
 */
(() => {
  'use strict';

  const form = document.querySelector('[data-filter-form]');
  const grid = document.querySelector('.estora-grid.estora-grid--3');
  if (!form || !grid) return;

  const lang = window.ESTORA_DEMO_LANG || (document.documentElement.lang === 'th' ? 'th' : 'en');
  const thai = lang === 'th';
  const demo = window.ESTORA_DEMO || {};
  const all = Array.isArray(demo[lang]) ? demo[lang] : [];
  const perPage = 9;
  const basePath = window.location.pathname.replace(/\/+$/, '') + '/';
  const escapeHtml = (value) => String(value == null ? '' : value).replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
  const money = new Intl.NumberFormat(thai ? 'th-TH' : 'en-US', { style: 'currency', currency: 'THB', currencyDisplay: 'narrowSymbol', maximumFractionDigits: 0 });

  const labels = thai
    ? { compare: 'เปรียบเทียบ', added: 'เพิ่มแล้ว', save: 'บันทึกทรัพย์', province: 'จังหวัด', area: 'พื้นที่', purpose: 'วัตถุประสงค์', type: 'ประเภท', keyword: 'คำค้น', bedrooms: 'ห้องนอน', availability: 'สถานะ', min: 'ราคาต่ำสุด', max: 'ราคาสูงสุด', clear: 'ล้างทั้งหมด', showing: 'แสดง', of: 'จาก', homes: 'รายการ', noResults: 'ไม่พบทรัพย์ที่ตรงกับเงื่อนไข', noResultsBody: 'ลองขยายช่วงราคาหรือลบตัวกรองทำเลออก ทรัพย์ที่เผยแพร่ทั้งหมดจะแสดงที่นี่', browse: 'ดูทรัพย์ทั้งหมด', month: 'เดือน', year: 'ปี' }
    : { compare: 'Compare', added: 'Added', save: 'Save property', province: 'Province', area: 'Area', purpose: 'Purpose', type: 'Type', keyword: 'Keyword', bedrooms: 'Bedrooms', availability: 'Availability', min: 'Min price', max: 'Max price', clear: 'Clear all', showing: 'Showing', of: 'of', homes: 'properties', noResults: 'No properties match this search', noResultsBody: 'Try widening the price range or removing the location filter. Everything currently published is shown here.', browse: 'Browse all properties', month: 'month', year: 'year' };

  const placeMap = { province: {}, district: {} };
  const destinations = {
    'bangkok': thai ? 'กรุงเทพมหานคร' : 'Bangkok',
    'chiang-mai': thai ? 'เชียงใหม่' : 'Chiang Mai',
    'phuket': thai ? 'ภูเก็ต' : 'Phuket',
    'hua-hin': thai ? 'หัวหิน' : 'Hua Hin',
    'pattaya': thai ? 'พัทยา' : 'Pattaya',
    'khon-kaen': thai ? 'ขอนแก่น' : 'Khon Kaen'
  };
  all.forEach((property) => {
    if (property.location_raw && property.location) {
      placeMap.province[property.location_raw.province] = property.location.province;
      placeMap.district[property.location_raw.district] = property.location.district;
    }
  });

  const keys = ['purpose', 'type', 'province', 'district', 'q', 'min_price', 'max_price', 'bedrooms', 'availability', 'sort', 'location'];

  const readState = () => {
    const params = new URLSearchParams(window.location.search);
    const state = {};
    keys.forEach((key) => { state[key] = (params.get(key) || '').trim(); });
    state.page = Math.max(1, Number(params.get('page')) || 1);
    return state;
  };

  const stateToParams = (state) => {
    const params = new URLSearchParams();
    keys.forEach((key) => {
      const value = (state[key] || '').trim();
      if (value && !(key === 'sort' && value === 'newest')) params.set(key, value);
    });
    if (state.page > 1) params.set('page', String(state.page));
    return params;
  };

  const filtered = (state) => {
    const query = state.q.toLowerCase();
    const items = all.filter((property) => {
      if (state.purpose && property.purpose !== state.purpose) return false;
      if (state.type && property.type !== state.type) return false;
      if (state.province && (!property.location_raw || property.location_raw.province !== state.province)) return false;
      if (state.district && (!property.location_raw || property.location_raw.district !== state.district)) return false;
      if (state.location && !(property.locations || []).includes(state.location)) return false;
      if (state.availability && property.availability !== state.availability) return false;
      if (state.bedrooms && Number(property.bedrooms) < Number(state.bedrooms)) return false;
      if (state.min_price && Number(property.price) < Number(state.min_price)) return false;
      if (state.max_price && Number(property.price) > Number(state.max_price)) return false;
      if (query) {
        const haystack = [property.title, property.address, property.reference].join(' ').toLowerCase();
        if (!haystack.includes(query)) return false;
      }
      return true;
    });

    const sort = state.sort || 'newest';
    items.sort((a, b) => {
      if (sort === 'price_asc') return Number(a.price) - Number(b.price);
      if (sort === 'price_desc') return Number(b.price) - Number(a.price);
      if (sort === 'area_asc') return Number(a.usable_area) - Number(b.usable_area);
      if (sort === 'area_desc') return Number(b.usable_area) - Number(a.usable_area);
      const left = Date.parse(a.created_at || '') || 0;
      const right = Date.parse(b.created_at || '') || 0;
      return sort === 'oldest' ? left - right : right - left;
    });
    return items;
  };

  const icon = (paths, size = 16) => '<svg class="estora-icon" width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + paths + '</svg>';
  const ICONS = {
    heart: '<path d="M20.8 5.6a5 5 0 0 0-7.1 0L12 7.3l-1.7-1.7a5 5 0 1 0-7.1 7.1L12 21l8.8-8.3a5 5 0 0 0 0-7.1Z"/>',
    pin: '<path d="M12 21s7-5.2 7-11a7 7 0 1 0-14 0c0 5.8 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5"/>',
    bed: '<path d="M3 18v-7h18v7"/><path d="M3 18h18"/><path d="M6 11V8h5v3"/><path d="M14 11V8h4v3"/>',
    bath: '<path d="M4 12h16v3a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4v-3Z"/><path d="M7 12V6a2 2 0 0 1 4 0"/><path d="M4 19h16"/>',
    area: '<path d="M4 9V4h5"/><path d="M20 15v5h-5"/><path d="M4 4l16 16"/>',
    parking: '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M9 17V7h4a3 3 0 0 1 0 6H9"/>'
  };

  const compareIds = () => {
    try {
      const raw = JSON.parse(window.localStorage.getItem('estora_compare') || '[]');
      return Array.isArray(raw) ? raw.map(Number) : [];
    } catch (error) {
      return [];
    }
  };
  const favouriteIds = () => {
    try {
      const raw = JSON.parse(window.localStorage.getItem('estora_demo_favorites') || '[]');
      return Array.isArray(raw) ? raw.map(Number) : [];
    } catch (error) {
      return [];
    }
  };

  function card(property, compare, favourites) {
    const image = property.image && property.image.url ? property.image.url : '';
    const suffix = property.purpose === 'rent'
      ? '<span> / ' + (property.rent_period === 'year' ? labels.year : labels.month) + '</span>'
      : '<span></span>';
    const specs = [];
    if (property.bedrooms) specs.push('<li>' + icon(ICONS.bed) + '<span>' + escapeHtml(property.bedrooms) + '</span></li>');
    if (property.bathrooms) specs.push('<li>' + icon(ICONS.bath) + '<span>' + escapeHtml(property.bathrooms) + '</span></li>');
    if (property.usable_area) specs.push('<li>' + icon(ICONS.area) + '<span>' + escapeHtml(Number(property.usable_area).toLocaleString()) + ' m²</span></li>');
    if (property.parking) specs.push('<li>' + icon(ICONS.parking) + '<span>' + escapeHtml(property.parking) + '</span></li>');
    const place = [property.location && property.location.district, property.location && property.location.province].filter(Boolean).join(', ');
    const saved = favourites.includes(Number(property.id));
    const compared = compare.includes(Number(property.id));

    return '<article class="estora-card" data-property-id="' + escapeHtml(property.id) + '">' +
      '<a class="estora-card__media" href="' + escapeHtml(property.url) + '" aria-label="' + escapeHtml(property.title) + '">' +
      '<img src="' + escapeHtml(image) + '" width="1600" height="1000" alt="' + escapeHtml((property.image && property.image.alt) || property.title) + '" loading="lazy" decoding="async">' +
      '<span class="estora-chip estora-chip--' + escapeHtml(property.purpose) + '">' + escapeHtml(property.purpose_label) + '</span></a>' +
      '<button type="button" class="estora-fav" data-favorite="' + escapeHtml(property.id) + '" aria-label="' + labels.save + '" aria-pressed="' + (saved ? 'true' : 'false') + '">' + icon(ICONS.heart, 18) + '</button>' +
      '<div class="estora-card__body">' +
      '<p class="estora-card__price">' + escapeHtml(property.price_formatted) + suffix + '</p>' +
      '<h3 class="estora-card__title"><a href="' + escapeHtml(property.url) + '">' + escapeHtml(property.title) + '</a></h3>' +
      '<p class="estora-card__location">' + icon(ICONS.pin, 14) + escapeHtml(place) + '</p>' +
      '<ul class="estora-card__specs">' + specs.join('') + '</ul>' +
      '<div class="estora-card__footer"><span class="estora-muted">' + escapeHtml(property.type_label) + '</span>' +
      '<button type="button" class="estora-compare-btn" data-compare="' + escapeHtml(property.id) + '" aria-pressed="' + (compared ? 'true' : 'false') + '">' + (compared ? labels.added : labels.compare) + '</button></div>' +
      '</div></article>';
  }

  function toast(message, tone) {
    const el = document.querySelector('[data-estora-toast]');
    if (!el) return;
    el.textContent = message;
    el.dataset.tone = tone || 'ok';
    el.hidden = false;
    window.clearTimeout(toast.timer);
    toast.timer = window.setTimeout(() => { el.hidden = true; }, 2600);
  }

  function bindCardActions() {
    grid.querySelectorAll('[data-favorite]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const id = Number(btn.dataset.favorite);
        const active = btn.getAttribute('aria-pressed') === 'true';
        btn.disabled = true;
        try {
          const response = await window.fetch('/wp-json/estora/v1/favorites' + (active ? '/' + id : ''), {
            method: active ? 'DELETE' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: active ? undefined : JSON.stringify({ property_id: id })
          });
          const payload = await response.json();
          btn.setAttribute('aria-pressed', payload.data.favorited ? 'true' : 'false');
          document.querySelectorAll('[data-fav-count]').forEach((el) => { el.textContent = String(payload.data.count); });
          toast(payload.data.favorited ? (thai ? 'บันทึกในรายการโปรดแล้ว' : 'Saved to favourites') : (thai ? 'ลบออกจากรายการโปรดแล้ว' : 'Removed from favourites'));
        } catch (error) {
          toast(thai ? 'เกิดข้อผิดพลาด' : 'Something went wrong', 'error');
        } finally {
          btn.disabled = false;
        }
      });
    });

    grid.querySelectorAll('[data-compare]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const id = Number(btn.dataset.compare);
        let ids = compareIds();
        if (ids.includes(id)) {
          ids = ids.filter((value) => value !== id);
        } else {
          if (ids.length >= 3) {
            toast(thai ? 'เปรียบเทียบได้สูงสุดสามทรัพย์' : 'You can compare three properties at a time.', 'error');
            return;
          }
          ids.push(id);
        }
        window.localStorage.setItem('estora_compare', JSON.stringify(ids));
        const added = ids.includes(id);
        btn.setAttribute('aria-pressed', added ? 'true' : 'false');
        btn.textContent = added ? labels.added : labels.compare;
        toast(added ? (thai ? 'เพิ่มในรายการเปรียบเทียบแล้ว' : 'Added to comparison') : (thai ? 'นำออกจากรายการเปรียบเทียบแล้ว' : 'Removed from comparison'));
      });
    });
  }

  function renderChips(state, items) {
    let root = document.querySelector('.estora-activefilters');
    if (!root) {
      root = document.createElement('div');
      root.className = 'estora-activefilters';
      root.setAttribute('aria-label', thai ? 'ตัวกรองที่ใช้' : 'Active filters');
      grid.parentNode.insertBefore(root, grid);
    }

    const chips = [];
    const add = (key, label, value) => {
      if (!value) return;
      chips.push('<a href="#" data-chip="' + key + '">' + escapeHtml(label) + ': ' + escapeHtml(value) + ' <span aria-hidden="true">×</span></a>');
    };
    add('q', labels.keyword, state.q);
    if (state.purpose) add('purpose', labels.purpose, state.purpose === 'rent' ? (thai ? 'ให้เช่า' : 'For rent') : (thai ? 'ขาย' : 'For sale'));
    if (state.type) {
      const sample = all.find((property) => property.type === state.type);
      add('type', labels.type, sample ? sample.type_label : state.type);
    }
    add('province', labels.province, placeMap.province[state.province] || state.province);
    add('district', labels.area, placeMap.district[state.district] || state.district);
    add('location', labels.area, destinations[state.location] || state.location);
    add('bedrooms', labels.bedrooms, state.bedrooms ? state.bedrooms + '+' : '');
    if (state.availability) {
      const sample = all.find((property) => property.availability === state.availability);
      add('availability', labels.availability, sample ? sample.availability_label : state.availability);
    }
    if (state.min_price) add('min_price', labels.min, money.format(Number(state.min_price)));
    if (state.max_price) add('max_price', labels.max, money.format(Number(state.max_price)));
    if (!chips.length) {
      root.innerHTML = '';
      root.hidden = true;
      return;
    }
    root.hidden = false;
    root.innerHTML = chips.join('') + '<a href="#" data-chip="all">' + labels.clear + '</a>';
    root.querySelectorAll('[data-chip]').forEach((link) => {
      link.addEventListener('click', (event) => {
        event.preventDefault();
        const key = link.dataset.chip;
        if (key === 'all') {
          window.history.pushState({}, '', basePath);
          syncForm(readState());
          draw();
          return;
        }
        state[key] = '';
        state.page = 1;
        const params = stateToParams(state);
        window.history.pushState({}, '', basePath + (params.toString() ? '?' + params.toString() : ''));
        syncForm(readState());
        draw();
      });
    });
  }

  function syncForm(state) {
    keys.forEach((key) => {
      const field = form.querySelector('[name="' + key + '"]');
      if (field && field.tagName !== 'BUTTON') field.value = state[key] || (field.name === 'sort' ? 'newest' : '');
    });
  }

  function draw() {
    const state = readState();
    const items = filtered(state);
    const pages = Math.max(1, Math.ceil(items.length / perPage));
    const page = Math.min(state.page, pages);
    const slice = items.slice((page - 1) * perPage, page * perPage);
    const favourites = favouriteIds();
    const compare = compareIds();

    grid.innerHTML = slice.length
      ? slice.map((property) => card(property, compare, favourites)).join('')
      : '';

    let empty = document.querySelector('.estora-demo-empty');
    if (!slice.length) {
      if (!empty) {
        empty = document.createElement('div');
        empty.className = 'estora-empty estora-demo-empty';
        grid.parentNode.insertBefore(empty, grid.nextSibling);
      }
      empty.innerHTML = '<h2>' + labels.noResults + '</h2><p>' + labels.noResultsBody + '</p><a class="estora-btn" href="' + basePath + '">' + labels.browse + '</a>';
    } else if (empty) {
      empty.remove();
    }

    grid.hidden = !slice.length;
    bindCardActions();
    renderChips(state, items);

    const shown = slice.length;
    const count = document.querySelector('.estora-resultcount');
    if (count) count.textContent = labels.showing + ' ' + shown + ' ' + labels.of + ' ' + items.length;

    const meta = document.querySelector('.estora-pagehead__meta span:first-child');
    if (meta) meta.textContent = new Intl.NumberFormat(thai ? 'th-TH' : 'en-US').format(items.length) + ' ' + labels.homes;

    let pagination = document.querySelector('.estora-pagination');
    if (!pagination) {
      pagination = document.createElement('nav');
      pagination.className = 'estora-pagination';
      grid.parentNode.appendChild(pagination);
    }
    pagination.innerHTML = '';
    if (pages > 1) {
      for (let index = 1; index <= pages; index += 1) {
        const element = document.createElement(index === page ? 'span' : 'a');
        if (index === page) element.className = 'current';
        else element.href = '#';
        element.textContent = String(index);
        if (index !== page) {
          element.addEventListener('click', (event) => {
            event.preventDefault();
            const next = readState();
            next.page = index;
            const params = stateToParams(next);
            window.history.pushState({}, '', basePath + (params.toString() ? '?' + params.toString() : ''));
            draw();
            const top = document.querySelector('.estora-filterbar');
            if (top) top.scrollIntoView({ behavior: 'smooth', block: 'start' });
          });
        }
        pagination.appendChild(element);
      }
    }
    pagination.hidden = pages <= 1;
  }

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    Array.from(form.elements).forEach((field) => { field.disabled = false; });
    const data = new FormData(form);
    const next = {};
    keys.forEach((key) => { next[key] = (data.get(key) || '').toString().trim(); });
    next.page = 1;
    const params = stateToParams(next);
    window.history.pushState({}, '', basePath + (params.toString() ? '?' + params.toString() : ''));
    draw();
    const top = document.querySelector('.estora-filterbar');
    if (top) top.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  window.addEventListener('popstate', () => {
    syncForm(readState());
    draw();
  });

  draw();
  window.__ESTORA_DEMO_ARCHIVE = true;
})();
