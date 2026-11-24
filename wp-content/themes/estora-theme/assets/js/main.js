/* ESTORA public interactions — vanilla ES2020, no build step. */
(() => {
  'use strict';

  const config = window.estoraData || {};
  const api = (config.rest || '/wp-json/estora/v1').replace(/\/$/, '');
  const strings = config.strings || {};
  const $ = (selector, scope = document) => scope.querySelector(selector);
  const $$ = (selector, scope = document) => Array.from(scope.querySelectorAll(selector));

  const storage = {
    get(key, fallback) {
      try {
        const raw = localStorage.getItem(key);
        return raw ? JSON.parse(raw) : fallback;
      } catch (error) {
        return fallback;
      }
    },
    set(key, value) {
      try {
        localStorage.setItem(key, JSON.stringify(value));
      } catch (error) {
        /* private mode */
      }
    }
  };

  const visitorToken = (() => {
    let token = storage.get('estora_visitor', '');
    if (!/^[a-f0-9]{32,64}$/i.test(token)) {
      const bytes = new Uint8Array(24);
      crypto.getRandomValues(bytes);
      token = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
      storage.set('estora_visitor', token);
    }
    document.cookie = `estora_visitor=${token}; Path=/; Max-Age=31536000; SameSite=Lax`;
    return token;
  })();

  async function request(path, options = {}) {
    const headers = Object.assign(
      {
        'Content-Type': 'application/json',
        'X-WP-Nonce': config.nonce || '',
        'X-Estora-Visitor': visitorToken
      },
      options.headers || {}
    );
    const response = await fetch(api + path, Object.assign({}, options, { headers, credentials: 'same-origin' }));
    let payload = null;
    try {
      payload = await response.json();
    } catch (error) {
      payload = null;
    }
    if (!response.ok || !payload || payload.success === false) {
      const message = (payload && payload.message) || strings.error || 'Request failed.';
      const err = new Error(message);
      err.status = response.status;
      err.payload = payload;
      throw err;
    }
    return payload;
  }

  let toastTimer = null;
  function toast(message, tone = 'ok') {
    const el = $('[data-estora-toast]');
    if (!el) return;
    el.textContent = message;
    el.dataset.tone = tone;
    el.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { el.hidden = true; }, 4200);
  }

  function debounce(fn, wait = 300) {
    let timer;
    return (...args) => {
      clearTimeout(timer);
      timer = setTimeout(() => fn(...args), wait);
    };
  }

  function utm() {
    const params = new URLSearchParams(window.location.search);
    const keys = ['utm_source', 'utm_medium', 'utm_campaign'];
    const saved = storage.get('estora_utm', {});
    const out = {};
    keys.forEach((key) => {
      const value = params.get(key);
      if (value) out[key] = value;
      else if (saved[key]) out[key] = saved[key];
    });
    if (Object.keys(out).length) storage.set('estora_utm', Object.assign({}, saved, out));
    return out;
  }
  utm();

  /* ---------- header ---------- */
  const header = $('.estora-header');
  const setStuck = () => {
    if (header) header.classList.toggle('is-stuck', window.scrollY > 8);
  };
  setStuck();
  window.addEventListener('scroll', setStuck, { passive: true });

  const navToggle = $('[data-nav-toggle]');
  const nav = $('[data-nav]');
  if (navToggle && nav) {
    navToggle.addEventListener('click', () => {
      const open = nav.classList.toggle('is-open');
      navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  /* ---------- favorites ---------- */
  function setFavCount(count) {
    $$('[data-fav-count]').forEach((el) => { el.textContent = String(count); });
  }

  function markFavorites(ids) {
    $$('[data-favorite]').forEach((btn) => {
      const active = ids.includes(Number(btn.dataset.favorite));
      btn.setAttribute('aria-pressed', active ? 'true' : 'false');
      btn.setAttribute('aria-label', active ? 'Remove from favourites' : 'Save property');
    });
    setFavCount(ids.length);
  }

  async function loadFavorites() {
    try {
      const payload = await request('/favorites');
      markFavorites((payload.data.ids || []).map(Number));
      return payload.data;
    } catch (error) {
      markFavorites([]);
      return { ids: [], properties: [] };
    }
  }

  $$('[data-favorite]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const id = Number(btn.dataset.favorite);
      if (!id) return;
      btn.disabled = true;
      const wasActive = btn.getAttribute('aria-pressed') === 'true';
      try {
        const payload = wasActive
          ? await request(`/favorites/${id}`, { method: 'DELETE' })
          : await request('/favorites', { method: 'POST', body: JSON.stringify({ property_id: id }) });
        const nowActive = Boolean(payload.data.favorited);
        btn.setAttribute('aria-pressed', nowActive ? 'true' : 'false');
        setFavCount(Number(payload.data.count) || 0);
        toast(nowActive ? strings.saved : strings.removed);
      } catch (error) {
        toast(error.message || strings.error, 'error');
      } finally {
        btn.disabled = false;
      }
    });
  });

  if ($('[data-favorite]') || $('[data-fav-count]')) loadFavorites();

  /* ---------- compare ---------- */
  const compareLimit = Number(config.compareLimit) || 3;
  const compareIds = () => storage.get('estora_compare', []).map(Number).filter(Boolean).slice(0, compareLimit);

  function syncCompareButtons() {
    const ids = compareIds();
    $$('[data-compare]').forEach((btn) => {
      const active = ids.includes(Number(btn.dataset.compare));
      btn.setAttribute('aria-pressed', active ? 'true' : 'false');
      btn.textContent = active ? 'Added' : 'Compare';
    });
    const counter = $('[data-compare-count]');
    if (counter) counter.textContent = String(ids.length);
  }

  $$('[data-compare]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const id = Number(btn.dataset.compare);
      let ids = compareIds();
      if (ids.includes(id)) {
        ids = ids.filter((x) => x !== id);
      } else {
        if (ids.length >= compareLimit) {
          toast(strings.compareFull || `Up to ${compareLimit} properties.`, 'error');
          return;
        }
        ids.push(id);
      }
      storage.set('estora_compare', ids);
      syncCompareButtons();
      toast(ids.includes(id) ? 'Added to comparison' : 'Removed from comparison');
    });
  });
  syncCompareButtons();

  /* ---------- comparison table ---------- */
  const compareRoot = $('[data-compare-table]');
  if (compareRoot) {
    const ids = compareIds();
    if (!ids.length) {
      compareRoot.innerHTML = '<div class="estora-empty"><h2>No properties selected</h2><p>Add up to three properties from any listing to compare price, size and specification.</p><a class="estora-btn" href="' + (config.propertyArchive || '/properties/') + '">Browse properties</a></div>';
    } else {
      Promise.all(ids.map((id) => request(`/properties/${id}`).then((p) => p.data).catch(() => null)))
        .then((items) => {
          const properties = items.filter(Boolean);
          if (!properties.length) throw new Error(strings.error);
          renderCompare(compareRoot, properties);
        })
        .catch(() => {
          compareRoot.innerHTML = '<div class="estora-empty"><h2>Comparison unavailable</h2><p>Please try again.</p></div>';
        });
    }
  }

  const escapeHtml = (value) => String(value == null ? '' : value).replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
  const money = (value) => '฿' + Number(value || 0).toLocaleString('en-US');

  function renderCompare(root, properties) {
    const rows = [
      ['Price', (p) => `<strong>${money(p.price)}</strong>${p.purpose === 'rent' ? ' / month' : ''}`],
      ['Purpose', (p) => p.purpose_label || ''],
      ['Type', (p) => p.type_label || ''],
      ['Location', (p) => `${escapeHtml(p.location.district || '')}${p.location.district && p.location.province ? ', ' : ''}${escapeHtml(p.location.province || '')}`],
      ['Land area', (p) => (p.land_area ? `${Number(p.land_area).toLocaleString()} m²` : '—')],
      ['Usable area', (p) => (p.usable_area ? `${Number(p.usable_area).toLocaleString()} m²` : '—')],
      ['Bedrooms', (p) => (p.bedrooms || '—')],
      ['Bathrooms', (p) => (p.bathrooms || '—')],
      ['Parking', (p) => (p.parking || '—')],
      ['Availability', (p) => escapeHtml(p.availability_label || '')],
      ['Amenities', (p) => (p.amenities && p.amenities.length ? escapeHtml(p.amenities.join(', ')) : '—')]
    ];
    let html = '<div class="estora-compare-wrap"><table class="estora-compare"><thead><tr><th scope="col">Attribute</th>';
    properties.forEach((p) => {
      html += `<th scope="col"><a href="${escapeHtml(p.url)}">${escapeHtml(p.title)}</a><br><button class="estora-compare__remove" data-compare-remove="${p.id}">Remove</button></th>`;
    });
    html += '</tr></thead><tbody>';
    rows.forEach(([label, render]) => {
      html += `<tr><th scope="row">${label}</th>`;
      properties.forEach((p) => { html += `<td>${render(p)}</td>`; });
      html += '</tr>';
    });
    html += '</tbody></table></div>';
    root.innerHTML = html;
    $$('[data-compare-remove]', root).forEach((btn) => {
      btn.addEventListener('click', () => {
        storage.set('estora_compare', compareIds().filter((x) => x !== Number(btn.dataset.compareRemove)));
        window.location.reload();
      });
    });
  }

  /* ---------- favorites page ---------- */
  const favoritesRoot = $('[data-favorites-list]');
  if (favoritesRoot) {
    loadFavorites().then((data) => {
      if (!data.properties || !data.properties.length) {
        favoritesRoot.innerHTML = `<div class="estora-empty"><h2>${escapeHtml(strings.favoritesEmpty || 'No saved properties yet.')}</h2><p>Use the heart on any listing to keep it here.</p><a class="estora-btn" href="${escapeHtml(config.propertyArchive || '/properties/')}">Browse properties</a></div>`;
        return;
      }
      favoritesRoot.innerHTML = `<div class="estora-grid estora-grid--3">${data.properties.map(cardHtml).join('')}</div>`;
      $$('[data-favorite]', favoritesRoot).forEach((btn) => {
        btn.setAttribute('aria-pressed', 'true');
        btn.addEventListener('click', async () => {
          try {
            await request(`/favorites/${btn.dataset.favorite}`, { method: 'DELETE' });
            btn.closest('.estora-card').remove();
            toast(strings.removed);
          } catch (error) {
            toast(error.message, 'error');
          }
        });
      });
    });
  }

  function cardHtml(p) {
    const image = p.image && p.image.url ? p.image.url : '';
    const specs = [];
    if (p.bedrooms) specs.push(`${p.bedrooms} bed`);
    if (p.bathrooms) specs.push(`${p.bathrooms} bath`);
    if (p.usable_area) specs.push(`${Number(p.usable_area).toLocaleString()} m²`);
    return `<article class="estora-card" data-property-id="${p.id}">
      <a class="estora-card__media" href="${escapeHtml(p.url)}"><img src="${escapeHtml(image)}" alt="${escapeHtml(p.image && p.image.alt ? p.image.alt : p.title)}" loading="lazy" width="800" height="600"><span class="estora-chip estora-chip--${escapeHtml(p.purpose)}">${escapeHtml(p.purpose_label)}</span></a>
      <button type="button" class="estora-fav" data-favorite="${p.id}" aria-label="Remove from favourites" aria-pressed="true">♥</button>
      <div class="estora-card__body">
        <p class="estora-card__price">${escapeHtml(p.price_formatted)}</p>
        <h3 class="estora-card__title"><a href="${escapeHtml(p.url)}">${escapeHtml(p.title)}</a></h3>
        <p class="estora-card__location">${escapeHtml(p.location.district || '')}${p.location.province ? ', ' + escapeHtml(p.location.province) : ''}</p>
        <ul class="estora-card__specs"><li>${escapeHtml(specs.join(' · '))}</li></ul>
      </div>
    </article>`;
  }

  /* ---------- inquiry form ---------- */
  $$('[data-inquiry-form]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const button = $('button[type="submit"]', form);
      const errorEl = $('[data-form-error]', form);
      const successEl = $('[data-form-success]', form);
      if (errorEl) errorEl.hidden = true;
      button.disabled = true;
      const original = button.textContent;
      button.textContent = strings.sending || 'Sending…';
      const data = Object.fromEntries(new FormData(form).entries());
      data.consent = form.querySelector('[name="consent"]') ? form.querySelector('[name="consent"]').checked : true;
      Object.assign(data, utm());
      data.message = data.message || '';
      try {
        const payload = await request('/inquiries', { method: 'POST', body: JSON.stringify(data) });
        form.reset();
        if (successEl) {
          successEl.hidden = false;
          successEl.textContent = `${payload.message} Reference: ${payload.data.lead_ref}`;
        }
        toast(payload.message);
      } catch (error) {
        if (errorEl) {
          errorEl.hidden = false;
          errorEl.textContent = error.message;
        }
        toast(error.message, 'error');
      } finally {
        button.disabled = false;
        button.textContent = original;
      }
    });
  });

  /* ---------- appointment form ---------- */
  $$('[data-appointment-form]').forEach((form) => {
    const dateInput = $('[name="date"]', form);
    const slotsRoot = $('[data-slots]', form);
    const timeInput = $('[data-time-input]', form);

    const loadSlots = async () => {
      if (!dateInput || !dateInput.value) return;
      slotsRoot.innerHTML = '<p class="estora-muted">Loading available times…</p>';
      try {
        const params = new URLSearchParams({ date: dateInput.value, property_id: form.dataset.propertyId || '0' });
        const payload = await request(`/appointments/availability?${params.toString()}`);
        const data = payload.data;
        if (data.closed || !data.slots.length) {
          slotsRoot.innerHTML = `<p class="estora-slots__empty">${escapeHtml(data.message || strings.closed)}</p>`;
          return;
        }
        slotsRoot.innerHTML = data.slots.map((slot) => `<button type="button" class="estora-slot" data-slot="${slot.start}" ${slot.available ? '' : 'disabled'}>${slot.start}</button>`).join('');
        $$('[data-slot]', slotsRoot).forEach((btn) => {
          btn.addEventListener('click', () => {
            timeInput.value = btn.dataset.slot;
            $$('[data-slot]', slotsRoot).forEach((b) => b.classList.remove('is-selected'));
            btn.classList.add('is-selected');
          });
        });
      } catch (error) {
        slotsRoot.innerHTML = `<p class="estora-slots__empty">${escapeHtml(error.message || strings.error)}</p>`;
      }
    };

    if (dateInput) {
      dateInput.min = new Date().toISOString().slice(0, 10);
      dateInput.addEventListener('change', () => { timeInput.value = ''; loadSlots(); });
    }

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const button = $('button[type="submit"]', form);
      const errorEl = $('[data-form-error]', form);
      const successEl = $('[data-form-success]', form);
      if (!timeInput.value) {
        if (errorEl) {
          errorEl.hidden = false;
          errorEl.textContent = 'Choose an available time.';
        }
        return;
      }
      if (errorEl) errorEl.hidden = true;
      button.disabled = true;
      const original = button.textContent;
      button.textContent = strings.sending || 'Sending…';
      const data = Object.fromEntries(new FormData(form).entries());
      data.consent = form.querySelector('[name="consent"]') ? form.querySelector('[name="consent"]').checked : true;
      Object.assign(data, utm());
      try {
        const payload = await request('/appointments', { method: 'POST', body: JSON.stringify(data) });
        form.reset();
        slotsRoot.innerHTML = '';
        if (successEl) {
          successEl.hidden = false;
          successEl.textContent = `${payload.message} Your reference is ${payload.data.public_ref}.`;
        }
        toast('Viewing requested');
      } catch (error) {
        if (errorEl) {
          errorEl.hidden = false;
          errorEl.textContent = error.message;
        }
        if (error.status === 409) loadSlots();
      } finally {
        button.disabled = false;
        button.textContent = original;
      }
    });
  });

  /* ---------- gallery ---------- */
  const gallery = $('[data-gallery]');
  if (gallery) {
    const mainImage = $('[data-gallery-main] img', gallery);
    const mainLink = $('[data-gallery-main]', gallery);
    $$('[data-gallery-thumb]', gallery).forEach((thumb) => {
      thumb.addEventListener('click', () => {
        if (mainImage) {
          mainImage.src = thumb.dataset.src;
          mainImage.alt = thumb.dataset.alt || '';
        }
        $$('[data-gallery-thumb]', gallery).forEach((t) => t.classList.remove('is-active'));
        thumb.classList.add('is-active');
      });
    });
    if (mainLink) {
      mainLink.addEventListener('click', (event) => {
        event.preventDefault();
        openLightbox(mainImage.src, mainImage.alt);
      });
    }
  }

  function openLightbox(src, alt) {
    const backdrop = document.createElement('div');
    backdrop.className = 'estora-dialog-backdrop';
    backdrop.innerHTML = `<img src="${escapeHtml(src)}" alt="${escapeHtml(alt)}"><button type="button" aria-label="Close">×</button>`;
    const close = () => backdrop.remove();
    backdrop.addEventListener('click', (event) => {
      if (event.target === backdrop || event.target.tagName === 'BUTTON') close();
    });
    document.addEventListener('keydown', function onKey(event) {
      if (event.key === 'Escape') {
        close();
        document.removeEventListener('keydown', onKey);
      }
    });
    document.body.appendChild(backdrop);
  }

  /* ---------- copy link ---------- */
  $$('[data-copy-link]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(btn.dataset.copyLink);
        toast(strings.copied || 'Link copied');
      } catch (error) {
        toast('Copy failed', 'error');
      }
    });
  });

  /* ---------- search: auto-apply filters ---------- */
  const filterForm = $('[data-filter-form]');
  if (filterForm) {
    $$('select, input[type="number"]', filterForm).forEach((input) => {
      input.addEventListener('change', debounce(() => filterForm.requestSubmit(), 400));
    });
  }

  /* ---------- subtle reveal ---------- */
  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });
    $$('.estora-card, .estora-feature, .estora-quote').forEach((el) => observer.observe(el));
  }
})();
