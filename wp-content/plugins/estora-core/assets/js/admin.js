/* ESTORA back office application — vanilla JS, REST-first. */
(() => {
  'use strict';

  const cfg = window.estoraAdmin;
  if (!cfg) return;
  const apiBase = (cfg.api || '').replace(/\/$/, '');
  const screens = document.querySelectorAll('[data-screen]');
  if (!screens.length) return;

  const escape = (value) => String(value == null ? '' : value).replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
  const money = (value) => '฿' + Number(value || 0).toLocaleString('en-US', { maximumFractionDigits: 0 });
  const dateFmt = (value, withTime = false) => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleString('en-GB', withTime
      ? { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }
      : { day: '2-digit', month: 'short', year: 'numeric' });
  };
  const todayISO = () => new Date().toISOString().slice(0, 10);
  const badge = (label, cls) => `<span class="estora-badge ${cls || 'badge-neutral'}">${escape(label)}</span>`;

  let toastTimer;
  function toast(message, tone = 'ok') {
    const el = document.querySelector('[data-toast]');
    if (!el) return;
    el.textContent = message;
    el.dataset.tone = tone;
    el.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { el.hidden = true; }, 4200);
  }
  window.estoraToast = toast;

  async function api(method, path, body) {
    const options = {
      method,
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
      credentials: 'same-origin'
    };
    if (body !== undefined) options.body = JSON.stringify(body);
    const response = await fetch(apiBase + path, options);
    let payload = null;
    try { payload = await response.json(); } catch (error) { payload = null; }
    if (!response.ok || !payload || payload.success === false) {
      const message = (payload && payload.message) || `Request failed (${response.status})`;
      const err = new Error(message);
      err.status = response.status;
      err.payload = payload;
      throw err;
    }
    return payload;
  }

  function skeleton(rows = 4) {
    return `<div aria-hidden="true">${Array.from({ length: rows }, () => '<div class="estora-skeleton estora-skeleton--row"></div>').join('')}</div>`;
  }

  function emptyBox(title, copy, action = '') {
    return `<div class="estora-emptyadmin"><strong>${escape(title)}</strong>${escape(copy)}${action ? `<div style="margin-top:14px">${action}</div>` : ''}</div>`;
  }

  function errorBox(message) {
    return `<div class="estora-errorbox">${escape(message)}</div>`;
  }

  function debounce(fn, wait = 320) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), wait); };
  }

  function formDataObject(form) {
    const data = {};
    new FormData(form).forEach((value, key) => {
      if (key.endsWith('[]')) {
        const clean = key.slice(0, -2);
        data[clean] = data[clean] || [];
        data[clean].push(value);
      } else if (Object.prototype.hasOwnProperty.call(data, key)) {
        if (!Array.isArray(data[key])) data[key] = [data[key]];
        data[key].push(value);
      } else {
        data[key] = value;
      }
    });
    return data;
  }

  function pagination(container, meta, onPage) {
    if (!container) return;
    const p = meta && meta.pagination ? meta.pagination : meta;
    if (!p || !p.total_pages || p.total_pages <= 1) {
      container.innerHTML = p ? `<span>${Number(p.total).toLocaleString()} records</span>` : '';
      return;
    }
    const current = Number(p.page);
    const total = Number(p.total_pages);
    const buttons = [];
    buttons.push(`<button type="button" data-page="${current - 1}" ${current <= 1 ? 'disabled' : ''}>←</button>`);
    const start = Math.max(1, current - 2);
    const end = Math.min(total, start + 4);
    for (let i = start; i <= end; i++) {
      buttons.push(`<button type="button" data-page="${i}" class="${i === current ? 'is-active' : ''}">${i}</button>`);
    }
    if (end < total) buttons.push('<span>…</span>');
    buttons.push(`<button type="button" data-page="${current + 1}" ${current >= total ? 'disabled' : ''}>→</button>`);
    buttons.push(`<span>${Number(p.total).toLocaleString()} records</span>`);
    container.innerHTML = buttons.join('');
    container.querySelectorAll('button[data-page]').forEach((button) => {
      button.addEventListener('click', () => onPage(Number(button.dataset.page)));
    });
  }

  /* ---------------- notifications ---------------- */
  const notifToggle = document.querySelector('[data-notifications-toggle]');
  const notifPanel = document.querySelector('[data-notifications-panel]');
  const notifList = document.querySelector('[data-notifications-list]');
  const notifCount = document.querySelector('[data-notifications-count]');

  async function loadNotifications() {
    if (!notifList) return;
    try {
      const payload = await api('GET', '/notifications');
      const items = payload.data || [];
      const unread = payload.meta ? Number(payload.meta.unread) : 0;
      if (notifCount) {
        notifCount.textContent = String(unread);
        notifCount.hidden = unread === 0;
      }
      if (!items.length) {
        notifList.innerHTML = '<li class="estora-notifications__empty">No notifications yet.</li>';
        return;
      }
      notifList.innerHTML = items.map((item) => `
        <li><a class="estora-notification${item.is_read ? '' : ' is-unread'}" href="${escape(item.link || '#')}" data-notification-id="${item.id}">
          <strong>${escape(item.title)}</strong>${escape(item.body || '')}<time>${escape(dateFmt(item.created_at, true))}</time>
        </a></li>`).join('');
      notifList.querySelectorAll('[data-notification-id]').forEach((link) => {
        link.addEventListener('click', (event) => {
          event.preventDefault();
          const id = link.dataset.notificationId;
          api('POST', `/notifications/${id}/read`, {}).catch(() => {});
          window.location.href = link.getAttribute('href');
        });
      });
    } catch (error) {
      notifList.innerHTML = '<li class="estora-notifications__empty">Notifications unavailable.</li>';
    }
  }

  if (notifToggle && notifPanel) {
    notifToggle.addEventListener('click', (event) => {
      event.stopPropagation();
      const open = notifPanel.hidden;
      notifPanel.hidden = !open;
      notifToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('click', (event) => {
      if (!notifPanel.hidden && !notifPanel.contains(event.target) && !notifToggle.contains(event.target)) {
        notifPanel.hidden = true;
        notifToggle.setAttribute('aria-expanded', 'false');
      }
    });
    document.querySelector('[data-notifications-read-all]')?.addEventListener('click', async () => {
      try {
        await api('POST', '/notifications/read-all', {});
        loadNotifications();
        toast('All notifications marked as read.');
      } catch (error) { toast(error.message, 'error'); }
    });
    loadNotifications();
  }

  /* ---------------- shared: agents ---------------- */
  let agentsPromise = null;
  function loadAgents() {
    if (!agentsPromise) {
      agentsPromise = api('GET', '/agents').then((payload) => payload.data || []).catch(() => []);
    }
    return agentsPromise;
  }

  function fillAgentSelect(select, agents, { includeAll = false, selected = 0 } = {}) {
    if (!select) return;
    const current = String(selected || select.value || '');
    select.innerHTML = (includeAll ? '<option value="">All agents</option>' : '<option value="0">Unassigned</option>')
      + agents.map((agent) => `<option value="${agent.id}">${escape(agent.name)}</option>`).join('');
    if (current) select.value = current;
  }

  /* ---------------- dashboard ---------------- */
  async function initDashboard(root) {
    const kpis = root.querySelector('[data-kpis]');
    const trend = root.querySelector('[data-lead-trend]');
    const trendTotal = root.querySelector('[data-trend-total]');
    const activity = root.querySelector('[data-recent-activity]');
    const upcoming = root.querySelector('[data-upcoming-appointments]');
    const sources = root.querySelector('[data-source-bars]');
    const viewShell = root.closest('.estora-admin') || root;
    const rangeSelect = viewShell.querySelector('[data-dashboard-range]');
    const fromInput = viewShell.querySelector('[data-range-from]');
    const toInput = viewShell.querySelector('[data-range-to]');
    const applyBtn = viewShell.querySelector('[data-range-apply]');
    const rangeSummary = viewShell.querySelector('[data-range-summary]');

    const isoToday = () => new Date().toISOString().slice(0, 10);
    const range = () => {
      const value = rangeSelect ? rangeSelect.value : '30';
      let days = 30;
      let from = '';
      let to = '';
      if (value === 'month') {
        const now = new Date();
        from = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`;
        to = isoToday();
        days = Math.max(1, Math.round((new Date(`${to}T12:00:00`) - new Date(`${from}T12:00:00`)) / 86400000) + 1);
      } else if (value === 'custom') {
        from = fromInput?.value || '';
        to = toInput?.value || '';
        if (from && to) {
          const diff = Math.round((new Date(`${to}T12:00:00`) - new Date(`${from}T12:00:00`)) / 86400000) + 1;
          days = Math.max(1, Math.min(366, diff));
        } else {
          days = 30;
        }
      } else {
        days = Number(value) || 30;
      }
      return { days, from, to, value };
    };

    const paramsFor = () => {
      const current = range();
      const params = new URLSearchParams({ days: String(current.days) });
      if (current.from && current.to) {
        params.set('from', current.from);
        params.set('to', current.to);
      }
      return params;
    };

    const showCustom = () => {
      const custom = rangeSelect && rangeSelect.value === 'custom';
      if (fromInput) fromInput.hidden = !custom;
      if (toInput) toInput.hidden = !custom;
      if (applyBtn) applyBtn.hidden = !custom;
      if (custom && fromInput && !fromInput.value) {
        const d = new Date();
        d.setDate(d.getDate() - 29);
        fromInput.value = d.toISOString().slice(0, 10);
        if (toInput) toInput.value = isoToday();
      }
    };

    async function load() {
      if (!kpis) return;
      root.setAttribute('aria-busy', 'true');
      kpis.innerHTML = Array.from({ length: 8 }, () => '<div class="estora-kpi"><span class="estora-kpi__label">&nbsp;</span><span class="estora-kpi__value">—</span><span class="estora-kpi__delta">&nbsp;</span></div>').join('');
      const params = paramsFor();
      const isAgent = !cfg.caps.viewReports;
      try {
        let data;
        if (isAgent) {
          const payload = await api('GET', `/dashboard/me?${params.toString()}`);
          data = payload.data;
          const trendData = data.lead_trend || [];
          const cards = [
            { label: 'My leads', value: data.leads.total, detail: `${data.leads.new} new · ${data.leads.qualified} qualified`, href: cfg.urls.leads },
            { label: 'Won', value: data.leads.won, detail: `${data.leads.lost} lost · ${data.leads.conversion_rate}% conversion`, href: cfg.urls.leads },
            { label: 'Open leads', value: data.leads.total - data.leads.won - data.leads.lost, detail: 'New, contacted, qualified or negotiating', href: cfg.urls.leads },
            { label: 'Upcoming viewings', value: data.appointments.upcoming_count, detail: `${data.appointments.pending} awaiting confirmation`, href: cfg.urls.appointments, accent: true },
            { label: 'Assigned properties', value: data.properties.assigned, detail: 'Published listings you own', href: cfg.urls.properties }
          ];
          renderKpis(kpis, cards);
          renderTrend(trend, trendData);
          if (trendTotal) trendTotal.textContent = `${trendData.reduce((sum, point) => sum + Number(point.count), 0)} leads in range`;
          renderBars(root.querySelector('[data-lead-status-chart]'), statusEntries(data.leads.by_status || {}), { title: 'Your pipeline' });
          renderAgentUpcoming(upcoming, data.appointments.next || []);
          hidePanel(root, 'property-mix', true);
          hidePanel(root, 'types', true);
          hidePanel(root, 'agents', true);
          hidePanel(root, 'appointments', true);
          hidePanel(root, 'sources', true);
          hidePanel(root, 'pipeline-health', true);
          if (activity) activity.closest('.estora-panel')?.setAttribute('hidden', 'hidden');
          if (rangeSummary) {
            rangeSummary.hidden = false;
            rangeSummary.innerHTML = `<span><strong>${escape(data.range.total)}</strong> leads captured</span><span><strong>${escape(data.range.won)}</strong> won</span><span><strong>${escape(data.range.conversion_rate)}%</strong> range conversion</span><span class="estora-muted">${escape(data.range.from)} → ${escape(data.range.to)}</span>`;
          }
        } else {
          const [summaryRes, propertyRes] = await Promise.all([
            api('GET', `/dashboard/summary?${params.toString()}`),
            api('GET', '/dashboard/property-statistics')
          ]);
          data = summaryRes.data;
          const cards = [
            { label: 'Total properties', value: data.properties.total, detail: `${data.properties.published} published`, href: cfg.urls.properties },
            { label: 'Available', value: data.properties.available, detail: `${data.properties.reserved} reserved`, href: cfg.urls.properties },
            { label: 'Sold', value: data.properties.sold, detail: `${data.properties.rented} rented`, href: cfg.urls.properties },
            { label: 'Rental listings', value: data.properties.rentals, detail: 'Purpose: for rent', href: cfg.urls.properties }
          ];
          if (data.leads) {
            cards.push(
              { label: 'Total leads', value: data.leads.total, detail: `${data.leads.range_total} in range`, href: cfg.urls.leads },
              { label: 'New leads', value: data.leads.new, detail: 'Awaiting first contact', href: cfg.urls.leads },
              { label: 'Won', value: data.leads.won, detail: `${data.leads.conversion_rate}% all-time conversion`, href: cfg.urls.leads }
            );
            renderTrend(trend, data.lead_trend || []);
            if (trendTotal) trendTotal.textContent = `${(data.lead_trend || []).reduce((sum, point) => sum + Number(point.count), 0)} captured in range`;
            renderBars(root.querySelector('[data-lead-status-chart]'), statusEntries(data.leads), { title: 'Pipeline' });
            renderBars(root.querySelector('[data-agent-chart]'), (data.leads.by_agent || []).slice(0, 6).map((row) => ({ label: row.name, value: row.total, caption: `${row.won} won` })), { color: 'brass', title: 'Leads per agent' });
            renderSourceBars(sources, data.by_source || {});
            renderPipelineHealth(root.querySelector('[data-pipeline-health]'), data.leads);
            if (rangeSummary) {
              rangeSummary.hidden = false;
              rangeSummary.innerHTML = `<span><strong>${escape(data.range.total)}</strong> leads captured</span><span><strong>${escape(data.range.won)}</strong> won</span><span><strong>${escape(data.range.conversion_rate)}%</strong> range conversion</span><span class="estora-muted">${escape(data.range.from)} → ${escape(data.range.to)}</span>`;
            }
          } else {
            hidePanel(root, 'lead-status', true);
            hidePanel(root, 'agents', true);
            hidePanel(root, 'sources', true);
            hidePanel(root, 'pipeline-health', true);
            if (trend) trend.closest('.estora-panel')?.setAttribute('hidden', 'hidden');
          }
          if (data.appointments) {
            cards.push({ label: 'Upcoming viewings', value: data.appointments.upcoming, detail: `${data.appointments.pending} awaiting confirmation`, href: cfg.urls.appointments, accent: true });
          }
          renderKpis(kpis, cards.slice(0, 8));
          renderBars(root.querySelector('[data-property-mix]'), [
            { label: 'Available', value: data.properties.available },
            { label: 'Reserved', value: data.properties.reserved },
            { label: 'Sold', value: data.properties.sold },
            { label: 'Rented', value: data.properties.rented },
            { label: 'Unavailable', value: Math.max(0, data.properties.published - data.properties.available - data.properties.reserved - data.properties.sold - data.properties.rented) }
          ], { title: 'Current inventory' });
          renderBars(root.querySelector('[data-type-chart]'), (propertyRes.data.by_type || []).map((row) => ({ label: row.name, value: row.total })), { color: 'brass', title: 'Published by type' });
          renderBars(root.querySelector('[data-appointment-chart]'), (data.appointments?.by_weekday || []).map((row) => ({ label: row.name, value: row.total })), { color: 'muted', title: 'Viewings by weekday' });
          if (!data.appointments) hidePanel(root, 'appointments', true);
          if (!cfg.caps.manageAppointments) hidePanel(root, 'upcoming-panel', true);

          let nextAppointments = [];
          if (cfg.caps.manageAppointments) {
            const payload = await api('GET', `/appointments?from=${isoToday()}&order=ASC&per_page=6`).catch(() => ({ data: [] }));
            nextAppointments = payload.data || [];
          }
          upcoming.innerHTML = nextAppointments.length
            ? `<ul class="estora-simplelist">${nextAppointments.map((item) => `
                <li><span><strong>${escape(item.customer_name)}</strong><br><span class="estora-muted">${escape(item.property_title)} · ${escape(item.agent_name || 'Unassigned')}</span></span>
                <span style="text-align:right">${escape(item.date)} ${escape(item.time)}<br>${badge(item.status_label, statusBadge(item.status))}</span></li>`).join('')}</ul>`
            : emptyBox('No upcoming viewings', 'Confirmed and pending requests appear here.');

          if (activity) {
            const items = data.recent_activity || [];
            activity.innerHTML = items.length
              ? items.map((item) => `<li><strong>${escape(item.description || item.action)}</strong><time>${escape(dateFmt(item.created_at, true))} · ${escape(item.actor)}</time></li>`).join('')
              : '<li>No activity recorded yet.</li>';
          }
        }
        root.setAttribute('aria-busy', 'false');
      } catch (error) {
        if (trend) trend.innerHTML = errorBox(error.message);
        toast(error.message, 'error');
        root.setAttribute('aria-busy', 'false');
      }
    }

    if (rangeSelect) {
      showCustom();
      rangeSelect.addEventListener('change', () => {
        showCustom();
        if (rangeSelect.value !== 'custom') load();
      });
    }
    applyBtn?.addEventListener('click', load);
    viewShell.querySelector('[data-refresh-dashboard]')?.addEventListener('click', load);
    load();
  }

  function renderKpis(container, cards) {
    container.innerHTML = cards.map((card) => `
      <a class="estora-kpi${card.accent ? ' estora-kpi--accent' : ''}" href="${escape(card.href || '#')}">
        <span class="estora-kpi__label">${escape(card.label)}</span>
        <span class="estora-kpi__value">${Number(card.value || 0).toLocaleString()}</span>
        <span class="estora-kpi__delta">${escape(card.detail || '')}</span>
      </a>`).join('');
  }

  function statusEntries(leads) {
    const source = leads && leads.counts ? leads.counts : leads || {};
    return [
      ['New', 'new'], ['Contacted', 'contacted'], ['Qualified', 'qualified'], ['Negotiation', 'negotiation'], ['Won', 'won'], ['Lost', 'lost']
    ].map(([label, key]) => ({ label, value: Number(source[key] || 0) }));
  }

  function renderBars(container, entries, options = {}) {
    if (!container) return;
    const rows = (entries || []).filter((entry) => entry && entry.label);
    if (!rows.length) {
      container.innerHTML = '<p class="estora-muted">No data for this period.</p>';
      return;
    }
    const max = Math.max(1, ...rows.map((row) => Number(row.value || 0)));
    container.innerHTML = `<div class="estora-bars" role="img" aria-label="${escape(options.title || 'Chart')}">${rows.map((row) => `
      <div class="estora-bar">
        <span>${escape(row.label)}${row.caption ? ` <span class="estora-muted">· ${escape(row.caption)}</span>` : ''}</span>
        <span class="estora-bar__track"><span class="estora-bar__fill${options.color === 'brass' ? ' estora-bar__fill--brass' : options.color === 'muted' ? ' estora-bar__fill--muted' : ''}" style="width:${Math.max(2, Math.round((Number(row.value) / max) * 100))}%"></span></span>
        <span class="estora-bar__num">${Number(row.value).toLocaleString()}</span>
      </div>`).join('')}</div>`;
  }

  function renderSourceBars(container, source) {
    if (!container) return;
    const entries = Object.entries(source || {}).sort((a, b) => b[1] - a[1]);
    const max = entries.reduce((m, [, v]) => Math.max(m, Number(v)), 1);
    container.innerHTML = entries.length
      ? entries.map(([name, count]) => `<div class="estora-sourcebar"><span>${escape(name)}</span><span class="estora-sourcebar__track"><span class="estora-sourcebar__fill" style="width:${Math.round((Number(count) / max) * 100)}%"></span></span><span class="estora-sourcebar__num">${Number(count)}</span></div>`).join('')
      : '<p class="estora-muted">No leads recorded.</p>';
  }

  function renderPipelineHealth(container, leads) {
    if (!container) return;
    const total = Number(leads.total || 0);
    const won = Number(leads.won || 0);
    const lost = Number(leads.lost || 0);
    container.innerHTML = `<ul class="estora-simplelist">
      <li><span>Open leads</span><span class="num">${Math.max(0, total - won - lost).toLocaleString()}</span></li>
      <li><span>Won</span><span class="num">${won.toLocaleString()}</span></li>
      <li><span>Lost</span><span class="num">${lost.toLocaleString()}</span></li>
      <li><span>All-time conversion</span><span class="num">${Number(leads.conversion_rate || 0)}%</span></li>
    </ul>`;
  }

  function renderAgentUpcoming(container, items) {
    if (!container) return;
    container.innerHTML = items.length
      ? `<ul class="estora-simplelist">${items.map((item) => `<li><span><strong>${escape(item.customer_name)}</strong><br><span class="estora-muted">${escape(item.property_title)}</span></span><span style="text-align:right">${escape(item.date)} ${escape(item.time)}<br>${badge(item.status_label, statusBadge(item.status))}</span></li>`).join('')}</ul>`
      : emptyBox('No upcoming viewings', 'Requests assigned to you appear here.');
  }

  function statusBadge(status) {
    return ({ pending: 'warning', confirmed: 'new', rescheduled: 'info', completed: 'neutral', cancelled: 'danger', rejected: 'danger', no_show: 'danger' }[status] || 'neutral');
  }

  function hidePanel(root, name, hide) {
    const panel = root.querySelector(`[data-panel="${name}"]`);
    if (panel) panel.hidden = hide;
  }

  function renderTrend(container, points) {
    if (!points.length) {
      container.innerHTML = '<div class="estora-chart__empty">No lead data yet.</div>';
      return;
    }
    const width = 640;
    const height = 190;
    const pad = { top: 16, right: 10, bottom: 26, left: 30 };
    const max = Math.max(1, ...points.map((p) => Number(p.count)));
    const stepX = (width - pad.left - pad.right) / Math.max(1, points.length - 1);
    const y = (value) => height - pad.bottom - (Number(value) / max) * (height - pad.top - pad.bottom);
    const coords = points.map((point, index) => [pad.left + index * stepX, y(point.count)]);
    const line = coords.map(([x, yy], i) => `${i === 0 ? 'M' : 'L'}${x.toFixed(1)},${yy.toFixed(1)}`).join(' ');
    const area = `${line} L${coords[coords.length - 1][0].toFixed(1)},${height - pad.bottom} L${coords[0][0].toFixed(1)},${height - pad.bottom} Z`;
    const labels = [0, Math.floor(points.length / 2), points.length - 1];
    container.innerHTML = `<svg viewBox="0 0 ${width} ${height}" role="img" aria-label="Daily leads for the last ${points.length} days">
      <path d="${area}" class="estora-chart__area"></path>
      <path d="${line}" class="estora-chart__line"></path>
      ${coords.map(([x, yy], i) => `<circle cx="${x.toFixed(1)}" cy="${yy.toFixed(1)}" r="2.5" class="estora-chart__dot"><title>${escape(points[i].date)}: ${points[i].count} leads</title></circle>`).join('')}
      ${labels.map((i) => `<text x="${coords[i][0].toFixed(1)}" y="${height - 6}" text-anchor="${i === 0 ? 'start' : i === points.length - 1 ? 'end' : 'middle'}" class="estora-chart__axis">${escape(points[i].date.slice(5))}</text>`).join('')}
      <text x="${pad.left}" y="12" class="estora-chart__axis">max ${max}</text>
    </svg>`;
  }

  /* ---------------- leads ---------------- */
  const STATUS_LABELS = { new: 'New', contacted: 'Contacted', qualified: 'Qualified', negotiation: 'Negotiation', won: 'Won', lost: 'Lost' };
  const STATUS_BADGE = { new: 'badge-new', contacted: 'badge-neutral', qualified: 'badge-info', negotiation: 'badge-warning', won: 'badge-ok', lost: 'badge-danger' };
  const NEXT = { new: ['contacted', 'lost'], contacted: ['qualified', 'lost'], qualified: ['negotiation', 'lost'], negotiation: ['won', 'lost'], lost: ['contacted'], won: [] };

  async function initLeads() {
    const root = document.querySelector('[data-screen="leads"]');
    const table = root.querySelector('[data-leads-table]');
    const board = root.querySelector('[data-leads-board]');
    const pagEl = root.querySelector('[data-leads-pagination]');
    const stageEl = root.querySelector('[data-stage-counts]');
    const search = root.querySelector('[data-lead-search]');
    const statusFilter = root.querySelector('[data-lead-status-filter]');
    const agentFilter = root.querySelector('[data-lead-agent-filter]');
    const sortFilter = root.querySelector('[data-lead-sort]');
    const viewButtons = root.querySelectorAll('[data-leads-view]');
    const urlParams = new URLSearchParams(window.location.search);
    const state = { page: 1, q: urlParams.get('q') || '', status: '', agent: '', sort: 'created_at:DESC', view: cfg.initialView === 'board' ? 'board' : 'table' };
    if (search && state.q) search.value = state.q;

    const agents = await loadAgents();
    agentsCache = agents;
    fillAgentSelect(agentFilter, agents, { includeAll: true });
    fillAgentSelect(root.querySelector('[data-new-lead-agent]'), agents);

    const load = async () => {
      table.innerHTML = skeleton(5);
      const [orderby, order] = state.sort.split(':');
      const params = new URLSearchParams({ page: String(state.page), per_page: '20', orderby, order });
      if (state.q) params.set('q', state.q);
      if (state.status) params.set('status', state.status);
      if (state.agent) params.set('agent_id', state.agent);

      try {
        const payload = await api('GET', `/leads?${params.toString()}`);
        const items = payload.data || [];
        const counts = (payload.meta && payload.meta.counts) || {};
        const total = Object.values(counts).reduce((sum, n) => sum + Number(n), 0);
        stageEl.innerHTML = `<button type="button" data-stage="" class="${state.status === '' ? 'is-active' : ''}">All <strong>${total}</strong></button>`
          + Object.entries(STATUS_LABELS).map(([key, label]) => `<button type="button" data-stage="${key}" class="${state.status === key ? 'is-active' : ''}">${label} <strong>${Number(counts[key] || 0)}</strong></button>`).join('');
        stageEl.querySelectorAll('[data-stage]').forEach((button) => {
          button.addEventListener('click', () => { state.status = button.dataset.stage; state.page = 1; statusFilter.value = state.status; load(); });
        });

        renderLeadRows(table, items);
        renderBoard(board, items, { onOpen: openLead, onMove: moveLead });
        table.querySelectorAll('[data-lead-open]').forEach((row) => {
          row.addEventListener('click', (event) => {
            if (event.target.closest('button, a, select, input')) return;
            openLead(Number(row.dataset.leadOpen));
          });
        });
        pagination(pagEl, payload.meta, (page) => { state.page = page; load(); });
        root.setAttribute('aria-busy', 'false');
      } catch (error) {
        table.innerHTML = errorBox(error.message);
        board.innerHTML = '';
      }
    };

    async function moveLead(id, status) {
      try {
        await api('POST', `/leads/${id}/status`, { status });
        toast(`Moved to ${STATUS_LABELS[status] || status}.`);
        load();
      } catch (error) {
        toast(error.message, 'error');
        load();
      }
    }

    async function openLead(id) {
      try {
        const payload = await api('GET', `/leads/${id}`);
        renderLeadDrawer(payload.data, { onChanged: load });
      } catch (error) {
        toast(error.message, 'error');
      }
    }

    search.addEventListener('input', debounce(() => { state.q = search.value.trim(); state.page = 1; load(); }, 350));
    statusFilter.addEventListener('change', () => { state.status = statusFilter.value; state.page = 1; load(); });
    agentFilter.addEventListener('change', () => { state.agent = agentFilter.value; state.page = 1; load(); });
    sortFilter.addEventListener('change', () => { state.sort = sortFilter.value; state.page = 1; load(); });
    viewButtons.forEach((button) => {
      button.addEventListener('click', () => {
        state.view = button.dataset.leadsView;
        viewButtons.forEach((b) => b.classList.toggle('is-active', b === button));
        table.hidden = state.view !== 'table';
        board.hidden = state.view !== 'board';
      });
    });
    if (state.view === 'board') {
      table.hidden = true;
      board.hidden = false;
      viewButtons.forEach((b) => b.classList.toggle('is-active', b.dataset.leadsView === 'board'));
    }

    // New lead dialog
    const dialog = root.querySelector('[data-lead-dialog]');
    (root.closest('.estora-admin') || root).querySelector('[data-new-lead]')?.addEventListener('click', () => dialog.showModal());
    root.querySelector('[data-close-dialog]')?.addEventListener('click', () => dialog.close());
    root.querySelector('[data-new-lead-form]')?.addEventListener('submit', async (event) => {
      event.preventDefault();
      const form = event.currentTarget;
      const errorEl = form.querySelector('[data-form-error]');
      const button = form.querySelector('button[type="submit"]');
      const data = formDataObject(form);
      if (data.assigned_agent_id === '0') delete data.assigned_agent_id;
      errorEl.hidden = true;
      button.disabled = true;
      try {
        await api('POST', '/leads', data);
        dialog.close();
        form.reset();
        toast('Lead created.');
        load();
      } catch (error) {
        errorEl.hidden = false;
        errorEl.textContent = error.message;
      } finally {
        button.disabled = false;
      }
    });

    if (window.location.hash === '#new') {
      document.body.dataset.estoraOpenLead = '1';
    }
    document.body.dataset.estoraLeadsReady = '1';
    load();
  }

  function renderLeadRows(table, items) {
    if (!items.length) {
      table.innerHTML = emptyBox('No leads match these filters', 'Clear the filters or create a lead manually.', '<button type="button" class="estora-btn estora-btn--sm" onclick="window.location.reload()">Reset</button>');
      return;
    }
    table.innerHTML = `<div class="estora-tablewrap"><table class="estora-table">
      <thead><tr><th>Lead</th><th>Contact</th><th>Stage</th><th>Agent</th><th>Source</th><th>Follow-up</th><th>Created</th></tr></thead>
      <tbody>${items.map((lead) => `<tr class="is-clickable" data-lead-open="${lead.id}">
        <td><span class="strong">${escape(lead.full_name)}</span><br><span class="estora-muted">${escape(lead.public_ref)}${lead.property ? ' · ' + escape(lead.property.title) : ''}</span></td>
        <td>${escape(lead.phone || '—')}<br><span class="estora-muted">${escape(lead.email || '—')}</span></td>
        <td>${badge(lead.status_label, STATUS_BADGE[lead.status])}</td>
        <td>${escape(lead.agent_name || 'Unassigned')}</td>
        <td>${escape(lead.source)}</td>
        <td class="${lead.is_overdue ? 'overdue' : ''}">${lead.follow_up_local ? escape(dateFmt(lead.follow_up_local, true)) : '—'}</td>
        <td>${escape(dateFmt(lead.created_at))}</td>
      </tr>`).join('')}</tbody></table></div>`;
  }

  function renderBoard(board, items, handlers) {
    if (!board) return;
    const columns = Object.entries(STATUS_LABELS).map(([key, label]) => {
      const leads = items.filter((lead) => lead.status === key);
      return `<div class="estora-column" data-column="${key}">
        <div class="estora-column__head">${label} <span>${leads.length}</span></div>
        ${leads.map((lead) => `<article class="estora-kanban-card" draggable="true" data-lead-id="${lead.id}">
          <strong>${escape(lead.full_name)}</strong>
          <span>${escape(lead.public_ref)} · ${escape(lead.source)}</span>
          <span>${escape(lead.agent_name || 'Unassigned')}${lead.follow_up_local ? ' · ' + escape(dateFmt(lead.follow_up_local)) : ''}</span>
          <div class="estora-kanban-card__foot">
            <button type="button" class="estora-rowbtn" data-open-lead="${lead.id}">Open</button>
            <select class="estora-kanban-card__select" data-move-lead="${lead.id}" aria-label="Move lead stage">
              <option value="">Move…</option>
              ${(NEXT[lead.status] || []).map((next) => `<option value="${next}">${STATUS_LABELS[next]}</option>`).join('')}
            </select>
          </div>
        </article>`).join('') || '<p class="estora-muted" style="padding:8px 0;font-size:11px">Empty</p>'}
      </div>`;
    }).join('');
    board.innerHTML = columns;

    board.querySelectorAll('[data-open-lead]').forEach((button) => button.addEventListener('click', () => handlers.onOpen(Number(button.dataset.openLead))));
    board.querySelectorAll('[data-move-lead]').forEach((select) => {
      select.addEventListener('change', () => {
        if (!select.value) return;
        const id = Number(select.dataset.moveLead);
        const target = select.value;
        select.value = '';
        handlers.onMove(id, target);
      });
    });

    board.querySelectorAll('.estora-kanban-card').forEach((card) => {
      card.addEventListener('dragstart', (event) => {
        event.dataTransfer.setData('text/plain', card.dataset.leadId);
        event.dataTransfer.effectAllowed = 'move';
      });
    });
    board.querySelectorAll('[data-column]').forEach((column) => {
      column.addEventListener('dragover', (event) => { event.preventDefault(); column.classList.add('is-over'); });
      column.addEventListener('dragleave', () => column.classList.remove('is-over'));
      column.addEventListener('drop', (event) => {
        event.preventDefault();
        column.classList.remove('is-over');
        const id = Number(event.dataTransfer.getData('text/plain'));
        const target = column.dataset.column;
        if (id && target) handlers.onMove(id, target);
      });
    });
  }

  function leadStatusSelect(lead, scope) {
    if (!cfg.caps.editLeads) return badge(lead.status_label, STATUS_BADGE[lead.status]);
    const allowed = [lead.status, ...(lead.allowed_next || NEXT[lead.status] || [])];
    return `<select data-drawer-status aria-label="Lead stage">
      ${allowed.map((status) => `<option value="${status}" ${status === lead.status ? 'selected' : ''} ${status === lead.status ? 'disabled' : ''}>${STATUS_LABELS[status] || status}</option>`).join('')}
    </select>`;
  }

  function leadDetailHtml(lead, scope = 'drawer') {
    const agentOptions = scope === 'drawer' ? '<option value="0">Unassigned</option>' : '';
    return `
      <div class="estora-panel" data-lead-head>
        <div class="estora-panel__head">
          <div>
            <p class="estora-eyebrow">${escape(lead.public_ref)} · ${escape(lead.source)}</p>
            <p class="estora-muted" style="margin:0">${escape(lead.full_name)}</p>
          </div>
          <div>${badge(lead.status_label, STATUS_BADGE[lead.status])}</div>
        </div>
        <dl class="estora-dl">
          <dt>Phone</dt><dd><a href="tel:${escape(lead.phone)}">${escape(lead.phone || '—')}</a></dd>
          <dt>Email</dt><dd>${lead.email ? `<a href="mailto:${escape(lead.email)}">${escape(lead.email)}</a>` : '—'}</dd>
          <dt>Property</dt><dd>${lead.property ? `<a href="${escape(lead.property.url)}">${escape(lead.property.title)}</a>` : 'General inquiry'}</dd>
          <dt>Budget</dt><dd>${lead.budget_max ? money(lead.budget_max) : '—'}</dd>
          <dt>Preferred area</dt><dd>${escape(lead.preferred_location || '—')}</dd>
          <dt>Agent</dt><dd>${escape(lead.agent_name || 'Unassigned')}</dd>
          <dt>Created</dt><dd>${escape(dateFmt(lead.created_at, true))}</dd>
          <dt>Last contact</dt><dd>${lead.last_contact_at ? escape(dateFmt(lead.last_contact_at, true)) : '—'}</dd>
          <dt>Follow-up</dt><dd class="${lead.is_overdue ? 'overdue' : ''}">${lead.follow_up_local ? escape(dateFmt(lead.follow_up_local, true)) : 'Not scheduled'}</dd>
        </dl>
        ${lead.message ? `<p style="margin-top:12px;padding-top:12px;border-top:1px solid var(--line);font-size:13px">${escape(lead.message)}</p>` : ''}
      </div>
      <div class="estora-panel" data-lead-controls>
        <div class="estora-formgrid">
          <label>Stage${leadStatusSelect(lead, scope)}</label>
          ${cfg.caps.assignLeads ? `<label>Assign to<select data-drawer-agent>${agentOptions}${agentsCache.map((agent) => `<option value="${agent.id}" ${agent.id === lead.assigned_agent_id ? 'selected' : ''}>${escape(agent.name)}</option>`).join('')}</select></label>` : ''}
          <label>Next follow-up<input type="datetime-local" data-drawer-followup value="${escape(lead.follow_up_local || '')}"></label>
          <div style="display:flex;align-items:flex-end"><button type="button" class="estora-btn estora-btn--ghost" data-save-followup>Save follow-up</button></div>
        </div>
        ${cfg.caps.assignLeads ? '<div style="margin-top:10px"><button type="button" class="estora-btn estora-btn--sm" data-assign-lead>Assign agent</button></div>' : ''}
      </div>
      <div class="estora-panel">
        <div class="estora-panel__head"><h2>Notes</h2></div>
        <form data-drawer-note class="estora-stack">
          <label>Add a note<textarea name="body" rows="3" required></textarea></label>
          <div class="estora-inline">
            <label class="estora-check"><input type="checkbox" name="is_internal" value="1"> Internal only</label>
            <button class="estora-btn estora-btn--sm" type="submit">Save note</button>
          </div>
        </form>
        <div class="estora-notes">${(lead.notes || []).length ? lead.notes.map((note) => `<div class="estora-note estora-note--${escape(note.note_type)}"><div class="estora-note__meta">${escape(note.note_type)} · ${escape(note.author)} · ${escape(dateFmt(note.created_at, true))}</div><div class="estora-note__body">${escape(note.body)}</div></div>`).join('') : '<p class="estora-muted">No notes yet.</p>'}</div>
      </div>
      <div class="estora-panel">
        <div class="estora-panel__head"><h2>Assignment history</h2></div>
        <ol class="estora-timeline">${(lead.assignments || []).length ? lead.assignments.map((row) => `<li><strong>${escape(row.from_agent || 'Unassigned')} → ${escape(row.to_agent)}</strong>${row.reason ? '<br>' + escape(row.reason) : ''}<time>${escape(dateFmt(row.created_at, true))}</time></li>`).join('') : '<li>No assignments recorded.</li>'}</ol>
      </div>
      <div class="estora-panel">
        <div class="estora-panel__head"><h2>Activity</h2></div>
        <ol class="estora-timeline">${(lead.timeline || []).length ? lead.timeline.map((row) => `<li><strong>${escape(row.description || row.action)}</strong><time>${escape(dateFmt(row.created_at, true))} · ${escape(row.actor)}</time></li>`).join('') : '<li>No activity.</li>'}</ol>
      </div>`;
  }

  let agentsCache = [];

  function wireLeadControls(container, lead, onChanged) {
    const statusSelect = container.querySelector('[data-drawer-status]');
    statusSelect?.addEventListener('change', async () => {
      if (!statusSelect.value || statusSelect.value === lead.status) return;
      try {
        await api('POST', `/leads/${lead.id}/status`, { status: statusSelect.value });
        toast('Lead stage updated.');
        onChanged && onChanged();
        reloadDrawer(lead.id, onChanged);
      } catch (error) {
        toast(error.message, 'error');
        statusSelect.value = lead.status;
      }
    });

    container.querySelector('[data-assign-lead]')?.addEventListener('click', async () => {
      const select = container.querySelector('[data-drawer-agent]');
      const agentId = Number(select?.value || 0);
      if (!agentId) { toast('Choose an agent first.', 'error'); return; }
      try {
        await api('POST', `/leads/${lead.id}/assign`, { agent_id: agentId });
        toast('Lead assigned.');
        onChanged && onChanged();
        reloadDrawer(lead.id, onChanged);
      } catch (error) { toast(error.message, 'error'); }
    });

    container.querySelector('[data-save-followup]')?.addEventListener('click', async () => {
      const input = container.querySelector('[data-drawer-followup]');
      try {
        await api('PATCH', `/leads/${lead.id}`, { next_follow_up_at: input.value });
        toast('Follow-up saved.');
        onChanged && onChanged();
      } catch (error) { toast(error.message, 'error'); }
    });

    container.querySelector('[data-drawer-note]')?.addEventListener('submit', async (event) => {
      event.preventDefault();
      const form = event.currentTarget;
      const body = form.querySelector('[name="body"]').value.trim();
      if (!body) return;
      try {
        await api('POST', `/leads/${lead.id}/notes`, { body, is_internal: form.querySelector('[name="is_internal"]').checked });
        toast('Note saved.');
        onChanged && onChanged();
        reloadDrawer(lead.id, onChanged);
      } catch (error) { toast(error.message, 'error'); }
    });
  }

  function renderLeadDrawer(lead, options) {
    const drawer = document.querySelector('[data-lead-drawer]');
    if (!drawer) return;
    const title = drawer.querySelector('[data-drawer-title]');
    const body = drawer.querySelector('[data-drawer-body]');
    if (title) title.textContent = lead.full_name;
    body.innerHTML = leadDetailHtml(lead, 'drawer');
    drawer.hidden = false;
    drawer.dataset.leadId = String(lead.id);
    wireLeadControls(body, lead, options.onChanged);
    document.querySelector('[data-close-drawer]')?.addEventListener('click', () => { drawer.hidden = true; });
  }

  async function reloadDrawer(id, onChanged) {
    const drawer = document.querySelector('[data-lead-drawer]');
    if (!drawer) return;
    try {
      const payload = await api('GET', `/leads/${id}`);
      renderLeadDrawer(payload.data, { onChanged });
    } catch (error) { toast(error.message, 'error'); }
  }

  /* ---------------- lead detail page ---------------- */
  async function initLeadPage() {
    const root = document.querySelector('[data-screen="lead"]');
    const id = Number(root.dataset.leadId || 0);
    if (!id) {
      root.innerHTML = emptyBox('Lead not specified', 'Open a lead from the CRM list.');
      return;
    }
    const summary = root.querySelector('[data-lead-summary]');
    const pipeline = root.querySelector('[data-lead-pipeline]');
    const notes = root.querySelector('[data-lead-notes]');
    const assignments = root.querySelector('[data-lead-assignments]');
    const timeline = root.querySelector('[data-lead-timeline]');
    const noteForm = root.querySelector('[data-note-form]');
    summary.innerHTML = skeleton(5);

    const load = async () => {
      try {
        agentsCache = await loadAgents();
        const payload = await api('GET', `/leads/${id}`);
        const lead = payload.data;
        summary.innerHTML = `
          <div class="estora-panel__head">
            <div><p class="estora-eyebrow">${escape(lead.public_ref)} · ${escape(lead.source)}</p><h2 style="font-family:var(--font-display);font-size:1.7rem;text-transform:none;letter-spacing:-.01em;color:var(--ink)">${escape(lead.full_name)}</h2></div>
            <div>${badge(lead.status_label, STATUS_BADGE[lead.status])}</div>
          </div>
          <dl class="estora-dl">
            <dt>Phone</dt><dd><a href="tel:${escape(lead.phone)}">${escape(lead.phone || '—')}</a></dd>
            <dt>Email</dt><dd>${lead.email ? `<a href="mailto:${escape(lead.email)}">${escape(lead.email)}</a>` : '—'}</dd>
            <dt>Property</dt><dd>${lead.property ? `<a href="${escape(lead.property.url)}">${escape(lead.property.title)}</a>` : 'General inquiry'}</dd>
            <dt>Budget</dt><dd>${lead.budget_max ? money(lead.budget_max) : '—'}</dd>
            <dt>Preferred area</dt><dd>${escape(lead.preferred_location || '—')}</dd>
            <dt>Agent</dt><dd>${escape(lead.agent_name || 'Unassigned')}</dd>
            <dt>Created</dt><dd>${escape(dateFmt(lead.created_at, true))}</dd>
            <dt>Last contact</dt><dd>${lead.last_contact_at ? escape(dateFmt(lead.last_contact_at, true)) : '—'}</dd>
            <dt>Follow-up</dt><dd class="${lead.is_overdue ? 'overdue' : ''}">${lead.follow_up_local ? escape(dateFmt(lead.follow_up_local, true)) : 'Not scheduled'}</dd>
          </dl>
          ${lead.message ? `<p style="margin-top:12px;padding-top:12px;border-top:1px solid var(--line);font-size:13px">${escape(lead.message)}</p>` : ''}`;

        pipeline.innerHTML = `
          <div class="estora-panel__head"><h2>Pipeline</h2></div>
          <div class="estora-formgrid">
            <label>Stage${leadStatusSelect(lead, 'page')}</label>
            ${cfg.caps.assignLeads ? `<label>Assign to<select data-drawer-agent><option value="0">Unassigned</option>${agentsCache.map((agent) => `<option value="${agent.id}" ${agent.id === lead.assigned_agent_id ? 'selected' : ''}>${escape(agent.name)}</option>`).join('')}</select></label>` : ''}
            <label>Next follow-up<input type="datetime-local" data-drawer-followup value="${escape(lead.follow_up_local || '')}"></label>
            <div style="display:flex;align-items:flex-end"><button type="button" class="estora-btn estora-btn--ghost" data-save-followup>Save follow-up</button></div>
          </div>
          ${cfg.caps.assignLeads ? '<div style="margin-top:10px"><button type="button" class="estora-btn estora-btn--sm" data-assign-lead>Assign agent</button></div>' : ''}`;

        notes.innerHTML = (lead.notes || []).length ? lead.notes.map((note) => `<div class="estora-note estora-note--${escape(note.note_type)}"><div class="estora-note__meta">${escape(note.note_type)} · ${escape(note.author)} · ${escape(dateFmt(note.created_at, true))}</div><div class="estora-note__body">${escape(note.body)}</div></div>`).join('') : '<p class="estora-muted">No notes yet.</p>';
        assignments.innerHTML = (lead.assignments || []).length ? lead.assignments.map((row) => `<li><strong>${escape(row.from_agent || 'Unassigned')} → ${escape(row.to_agent)}</strong>${row.reason ? '<br>' + escape(row.reason) : ''}<time>${escape(dateFmt(row.created_at, true))}</time></li>`).join('') : '<li>No assignments recorded.</li>';
        timeline.innerHTML = (lead.timeline || []).map((row) => `<li><strong>${escape(row.description || row.action)}</strong><time>${escape(dateFmt(row.created_at, true))} · ${escape(row.actor)}</time></li>`).join('') || '<li>No activity.</li>';

        wireLeadControls(summary, lead, load);
        wireLeadControls(pipeline, lead, load);
        root.setAttribute('aria-busy', 'false');
      } catch (error) {
        summary.innerHTML = errorBox(error.message);
      }
    };

    noteForm.onsubmit = async (event) => {
      event.preventDefault();
      const data = formDataObject(noteForm);
      try {
        await api('POST', `/leads/${id}/notes`, data);
        noteForm.reset();
        toast('Note saved.');
        load();
      } catch (error) { toast(error.message, 'error'); }
    };

    load();
  }

  /* ---------------- appointments ---------------- */
  async function initAppointments() {
    const root = document.querySelector('[data-screen="appointments"]');
    const calendar = root.querySelector('[data-calendar]');
    const title = root.querySelector('[data-calendar-title]');
    const calendarCount = root.querySelector('[data-calendar-count]');
    const tableRoot = root.querySelector('[data-appointment-table]');
    const pagEl = root.querySelector('[data-appointment-pagination]');
    const statusFilter = root.querySelector('[data-appointment-status]');
    const agentFilter = root.querySelector('[data-appointment-agent]');
    const state = { view: 'month', cursor: new Date(), page: 1 };

    const agents = await loadAgents();
    fillAgentSelect(agentFilter, agents, { includeAll: true });

    const range = () => {
      const start = new Date(state.cursor);
      const end = new Date(state.cursor);
      if (state.view === 'month') {
        start.setDate(1);
        const offset = (start.getDay() + 6) % 7;
        start.setDate(start.getDate() - offset);
        end.setMonth(end.getMonth() + 1, 0);
        end.setDate(end.getDate() + (6 - ((end.getDay() + 6) % 7)));
      } else if (state.view === 'week') {
        const offset = (start.getDay() + 6) % 7;
        start.setDate(start.getDate() - offset);
        end.setTime(start.getTime());
        end.setDate(start.getDate() + 6);
      }
      return { start: start.toISOString().slice(0, 10), end: end.toISOString().slice(0, 10), startDate: start, endDate: end };
    };

    const load = async () => {
      const bounds = range();
      const params = new URLSearchParams({ from: bounds.start, to: bounds.end, per_page: '100', order: 'ASC' });
      if (statusFilter.value) params.set('status', statusFilter.value);
      if (agentFilter.value) params.set('agent_id', agentFilter.value);
      calendar.innerHTML = skeleton(3);
      tableRoot.innerHTML = skeleton(4);
      try {
        const payload = await api('GET', `/appointments?${params.toString()}`);
        const items = payload.data || [];
        title.textContent = state.view === 'month'
          ? state.cursor.toLocaleString('en-GB', { month: 'long', year: 'numeric' })
          : state.view === 'week'
            ? `Week of ${bounds.startDate.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}`
            : state.cursor.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
        if (calendarCount) calendarCount.textContent = `${items.length} viewings`;
        renderCalendar(calendar, state.view, state.cursor, items, bounds, (dateStr) => {
          state.view = 'day';
          state.cursor = new Date(`${dateStr}T12:00:00`);
          document.querySelectorAll('[data-calendar-view]').forEach((b) => b.classList.toggle('is-active', b.dataset.calendarView === 'day'));
          load();
        });
        renderAppointmentTable(tableRoot, items);
        pagination(pagEl, payload.meta, (page) => { state.page = page; load(); });
        root.setAttribute('aria-busy', 'false');
      } catch (error) {
        calendar.innerHTML = errorBox(error.message);
        tableRoot.innerHTML = '';
      }
    };

    root.querySelectorAll('[data-calendar-view]').forEach((button) => {
      button.addEventListener('click', () => {
        state.view = button.dataset.calendarView;
        root.querySelectorAll('[data-calendar-view]').forEach((b) => b.classList.toggle('is-active', b === button));
        load();
      });
    });
    root.querySelector('[data-calendar-prev]')?.addEventListener('click', () => { moveCursor(-1); });
    root.querySelector('[data-calendar-next]')?.addEventListener('click', () => { moveCursor(1); });
    root.querySelector('[data-calendar-today]')?.addEventListener('click', () => { state.cursor = new Date(); load(); });
    statusFilter.addEventListener('change', load);
    agentFilter.addEventListener('change', load);

    function moveCursor(direction) {
      const cursor = new Date(state.cursor);
      if (state.view === 'month') cursor.setMonth(cursor.getMonth() + direction);
      else if (state.view === 'week') cursor.setDate(cursor.getDate() + 7 * direction);
      else cursor.setDate(cursor.getDate() + direction);
      state.cursor = cursor;
      load();
    }

    async function updateStatus(id, status) {
      try {
        await api('POST', `/appointments/${id}/status`, { status });
        toast(`Viewing ${status.replace('_', ' ')}.`);
        load();
      } catch (error) { toast(error.message, 'error'); }
    }

    async function reschedule(id) {
      const dialog = document.createElement('dialog');
      dialog.className = 'estora-dialog';
      dialog.innerHTML = `<form method="dialog"><h2>Reschedule viewing</h2>
        <div class="estora-formgrid">
          <label>New date<input type="date" name="date" min="${todayISO()}" required></label>
          <label>New time<input type="time" name="time" step="900" required></label>
          <label class="estora-formgrid__wide">Reason<input type="text" name="reason" maxlength="200"></label>
        </div>
        <p class="estora-form-error" data-dialog-error hidden></p>
        <div class="estora-dialog__actions"><button type="button" class="estora-btn estora-btn--ghost" data-cancel>Cancel</button><button type="submit" class="estora-btn">Reschedule</button></div></form>`;
      document.body.appendChild(dialog);
      dialog.showModal();
      dialog.querySelector('[data-cancel]').addEventListener('click', () => dialog.close());
      dialog.addEventListener('close', () => dialog.remove());
      dialog.querySelector('form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const data = formDataObject(event.currentTarget);
        try {
          await api('POST', `/appointments/${id}/reschedule`, data);
          dialog.close();
          toast('Viewing rescheduled.');
          load();
        } catch (error) {
          const el = dialog.querySelector('[data-dialog-error]');
          el.hidden = false;
          el.textContent = error.message;
        }
      });
    }

    tableRoot.addEventListener('click', (event) => {
      const button = event.target.closest('[data-appt-action]');
      if (!button) return;
      const id = Number(button.dataset.id);
      if (button.dataset.apptAction === 'reschedule') reschedule(id);
      else updateStatus(id, button.dataset.apptAction);
    });

    // Staff booking dialog.
    const dialog = root.querySelector('[data-staff-appointment-dialog]');
    const form = dialog?.querySelector('[data-staff-appointment-form]');
    const propertySelect = dialog?.querySelector('[data-staff-property]');
    const agentSelect = dialog?.querySelector('[data-staff-agent]');
    const staffSlots = dialog?.querySelector('[data-staff-slots]');
    const timeInput = dialog?.querySelector('[data-staff-time]');
    let propertiesLoaded = false;

    fillAgentSelect(agentSelect, agents, { includeAll: false });
    if (agentSelect) {
      agentSelect.insertAdjacentHTML('afterbegin', '<option value="0">Auto-assign</option>');
    }

    async function openAppointmentDialog() {
      if (!dialog || !form) return;
      if (!propertiesLoaded && propertySelect) {
        try {
          const payload = await api('GET', '/properties?per_page=100&status=publish&sort=newest');
          propertySelect.innerHTML = '<option value="">Choose a property…</option>'
            + (payload.data || []).map((property) => `<option value="${property.id}">${escape(property.title)} · ${escape(property.reference)}</option>`).join('');
          propertiesLoaded = true;
        } catch (error) {
          propertySelect.innerHTML = `<option value="">${escape(error.message)}</option>`;
        }
      }
      form.reset();
      if (timeInput) timeInput.value = '';
      if (staffSlots) staffSlots.innerHTML = '<p class="estora-slots__empty">Choose a date and property to load slots.</p>';
      dialog.showModal();
    }

    (root.closest('.estora-admin') || root).querySelector('[data-new-appointment]')?.addEventListener('click', openAppointmentDialog);
    dialog?.querySelector('[data-close-appointment-dialog]')?.addEventListener('click', () => dialog.close());

    const loadStaffSlots = async () => {
      if (!form || !staffSlots) return;
      const property = Number(propertySelect?.value || 0);
      const date = form.querySelector('[name="date"]')?.value || '';
      if (!property || !date) return;
      staffSlots.innerHTML = '<p class="estora-muted">Loading available times…</p>';
      if (timeInput) timeInput.value = '';
      try {
        const params = new URLSearchParams({ date, property_id: String(property) });
        const agentId = Number(agentSelect?.value || 0);
        if (agentId) params.set('agent_id', String(agentId));
        const payload = await api('GET', `/appointments/availability?${params.toString()}`);
        const data = payload.data;
        if (data.closed || !data.slots.length) {
          staffSlots.innerHTML = `<p class="estora-slots__empty">${escape(data.message || 'No slots available.')}</p>`;
          return;
        }
        staffSlots.innerHTML = data.slots.map((slot) => `<button type="button" class="estora-slot" data-staff-slot="${slot.start}" ${slot.available ? '' : 'disabled'}>${slot.start}</button>`).join('');
        staffSlots.querySelectorAll('[data-staff-slot]').forEach((button) => {
          button.addEventListener('click', () => {
            if (timeInput) timeInput.value = button.dataset.staffSlot;
            staffSlots.querySelectorAll('[data-staff-slot]').forEach((b) => b.classList.remove('is-selected'));
            button.classList.add('is-selected');
          });
        });
      } catch (error) {
        staffSlots.innerHTML = `<p class="estora-slots__empty">${escape(error.message)}</p>`;
      }
    };

    form?.querySelector('[name="date"]')?.addEventListener('change', loadStaffSlots);
    propertySelect?.addEventListener('change', loadStaffSlots);
    agentSelect?.addEventListener('change', loadStaffSlots);

    form?.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (!form || !timeInput?.value) {
        const err = form?.querySelector('[data-form-error]');
        if (err) {
          err.hidden = false;
          err.textContent = 'Choose an available time.';
        }
        return;
      }
      const errorEl = form.querySelector('[data-form-error]');
      const button = form.querySelector('button[type="submit"]');
      const data = formDataObject(form);
      data.staff = 1;
      data.consent = true;
      if (!data.agent_id) delete data.agent_id;
      errorEl.hidden = true;
      button.disabled = true;
      try {
        const payload = await api('POST', '/appointments', data);
        dialog.close();
        toast(`${payload.message} ${payload.data.public_ref}`);
        load();
      } catch (error) {
        errorEl.hidden = false;
        errorEl.textContent = error.message;
        if (error.status === 409) loadStaffSlots();
      } finally {
        button.disabled = false;
      }
    });

    apptReschedule = reschedule;
    load();
    if (window.location.hash === '#new') {
      setTimeout(openAppointmentDialog, 450);
      history.replaceState(null, '', window.location.pathname);
    }
  }

  function renderCalendar(container, view, cursor, items, bounds, onDay) {
    const byDate = {};
    items.forEach((item) => { (byDate[item.date] = byDate[item.date] || []).push(item); });
    const eventHtml = (item) => `<button type="button" class="estora-event estora-event--${escape(item.status)}" data-appt-id="${item.id}" title="${escape(item.customer_name + ' · ' + item.time + ' · ' + item.status_label)}" style="border:0;text-align:left;cursor:pointer">${escape(item.time)} ${escape(item.customer_name)}</button>`;

    if (view === 'day') {
      const date = new Date(cursor);
      const key = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
      const list = byDate[key] || [];
      container.className = 'estora-calendar estora-calendar--day';
      container.innerHTML = `<div class="estora-day"><div class="estora-day__num">${escape(date.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' }))}<span>${list.length} viewings</span></div>
        <div class="estora-day__events" style="margin-top:12px">${list.length ? list.map((item) => `<div style="padding:8px 0;border-bottom:1px solid var(--line)"><strong>${escape(item.time)} · ${escape(item.customer_name)}</strong><br><span class="estora-muted">${escape(item.property_title)} · ${escape(item.agent_name || 'Unassigned')} · ${escape(item.status_label)}</span></div>`).join('') : '<p class="estora-muted">No viewings.</p>'}</div></div>`;
      return;
    }

    const start = new Date(bounds.startDate);
    const days = [];
    const total = view === 'week' ? 7 : 42;
    for (let i = 0; i < total; i++) {
      const date = new Date(start);
      date.setDate(start.getDate() + i);
      const key = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
      days.push({ date, key, items: byDate[key] || [] });
    }
    const todayKey = todayISO();
    container.className = `estora-calendar estora-calendar--${view}`;
    container.innerHTML = `<div class="estora-calendar__weekdays">${['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map((d) => `<span>${d}</span>`).join('')}</div>
      <div class="estora-calendar__grid">${days.map(({ date, key, items: dayItems }) => `<div class="estora-day ${date.getMonth() !== cursor.getMonth() && view === 'month' ? 'is-outside' : ''} ${key === todayKey ? 'is-today' : ''}" data-day="${key}" role="button" tabindex="0">
        <span class="estora-day__num">${date.getDate()}</span>
        <div class="estora-day__events">${dayItems.slice(0, 3).map(eventHtml).join('')}${dayItems.length > 3 ? `<span class="estora-muted" style="font-size:10px">+${dayItems.length - 3} more</span>` : ''}</div>
      </div>`).join('')}</div>`;
    container.querySelectorAll('[data-day]').forEach((day) => {
      day.addEventListener('click', (event) => {
        if (event.target.closest('[data-appt-id]')) return;
        onDay(day.dataset.day);
      });
      day.addEventListener('keydown', (event) => { if (event.key === 'Enter') onDay(day.dataset.day); });
    });
    container.querySelectorAll('[data-appt-id]').forEach((button) => {
      button.addEventListener('click', () => {
        const id = Number(button.dataset.apptId);
        const item = items.find((row) => row.id === id);
        if (item) openAppointmentDialog(item);
      });
    });
  }

  let apptReschedule = null;

  function openAppointmentDialog(item) {
    const dialog = document.createElement('dialog');
    dialog.className = 'estora-dialog';
    const actions = {
      pending: [['confirmed', 'Confirm'], ['rejected', 'Reject'], ['cancelled', 'Cancel']],
      confirmed: [['completed', 'Mark completed'], ['no_show', 'No show'], ['cancelled', 'Cancel']],
      rescheduled: [['confirmed', 'Confirm'], ['completed', 'Mark completed'], ['cancelled', 'Cancel']],
      completed: [], cancelled: [], rejected: [], no_show: []
    }[item.status] || [];
    dialog.innerHTML = `<div><h2>${escape(item.public_ref)}</h2>
      <dl class="estora-dl">
        <dt>Customer</dt><dd>${escape(item.customer_name)} · ${escape(item.customer_phone)}</dd>
        <dt>Property</dt><dd>${escape(item.property_title)}</dd>
        <dt>When</dt><dd>${escape(item.date)} ${escape(item.time)}–${escape(item.end_time)}</dd>
        <dt>Agent</dt><dd>${escape(item.agent_name || 'Unassigned')}</dd>
        <dt>Status</dt><dd>${badge(item.status_label)}</dd>
      </dl>
      ${item.notes ? `<p class="estora-muted" style="margin-top:10px">${escape(item.notes)}</p>` : ''}
      <div class="estora-dialog__actions">
        ${actions.map(([status, label]) => `<button type="button" class="estora-btn ${status === 'cancelled' || status === 'rejected' ? 'estora-btn--danger' : status === 'confirmed' ? '' : 'estora-btn--ghost'} estora-btn--sm" data-set-status="${status}">${label}</button>`).join('')}
        ${['pending', 'confirmed', 'rescheduled'].includes(item.status) ? '<button type="button" class="estora-btn estora-btn--ghost estora-btn--sm" data-reschedule-dialog>Reschedule</button>' : ''}
        <button type="button" class="estora-btn estora-btn--ghost estora-btn--sm" data-close-dialog>Close</button>
      </div></div>`;
    document.body.appendChild(dialog);
    dialog.showModal();
    dialog.addEventListener('close', () => dialog.remove());
    dialog.querySelector('[data-close-dialog]').addEventListener('click', () => dialog.close());
    dialog.querySelectorAll('[data-set-status]').forEach((button) => {
      button.addEventListener('click', async () => {
        const notes = window.prompt('Internal note (optional)') || '';
        try {
          await api('POST', `/appointments/${item.id}/status`, { status: button.dataset.setStatus, internal_notes: notes });
          dialog.close();
          toast('Appointment updated.');
          document.querySelector('[data-calendar-today]')?.click();
        } catch (error) { toast(error.message, 'error'); }
      });
    });
    dialog.querySelector('[data-reschedule-dialog]')?.addEventListener('click', () => {
      dialog.close();
      if (typeof apptReschedule === 'function') apptReschedule(item.id);
    });
  }

  function renderAppointmentTable(container, items) {
    if (!items.length) {
      container.innerHTML = emptyBox('No viewings in this range', 'Change the range or filters.');
      return;
    }
    container.innerHTML = `<div class="estora-tablewrap"><table class="estora-table">
      <thead><tr><th>When</th><th>Customer</th><th>Property</th><th>Agent</th><th>Status</th><th class="num">Actions</th></tr></thead>
      <tbody>${items.map((item) => `<tr>
        <td><span class="strong">${escape(item.date)}</span><br><span class="estora-muted">${escape(item.time)}–${escape(item.end_time)}</span></td>
        <td>${escape(item.customer_name)}<br><span class="estora-muted">${escape(item.customer_phone)}</span></td>
        <td>${escape(item.property_title)}</td>
        <td>${escape(item.agent_name || 'Unassigned')}</td>
        <td>${badge(item.status_label, item.status === 'pending' ? 'badge-warning' : item.status === 'confirmed' ? 'badge-new' : item.status === 'completed' ? 'badge-neutral' : 'badge-danger')}</td>
        <td><div class="estora-table__actions">
          ${item.status === 'pending' ? `<button class="estora-rowbtn" data-appt-action="confirmed" data-id="${item.id}">Confirm</button>` : ''}
          ${['pending', 'confirmed', 'rescheduled'].includes(item.status) ? `<button class="estora-rowbtn" data-appt-action="reschedule" data-id="${item.id}">Reschedule</button>` : ''}
          ${item.status === 'confirmed' ? `<button class="estora-rowbtn" data-appt-action="completed" data-id="${item.id}">Complete</button>` : ''}
          ${['pending', 'confirmed', 'rescheduled'].includes(item.status) ? `<button class="estora-rowbtn estora-rowbtn--danger" data-appt-action="cancelled" data-id="${item.id}">Cancel</button>` : ''}
        </div></td>
      </tr>`).join('')}</tbody></table></div>`;
  }

  /* ---------------- inventory ---------------- */
  async function initInventory() {
    const root = document.querySelector('[data-screen="inventory"]');
    const tableRoot = root.querySelector('[data-inventory-table]');
    const pagEl = root.querySelector('[data-inventory-pagination]');
    const summaryEl = root.querySelector('[data-inventory-summary]');
    const search = root.querySelector('[data-inventory-search]');
    const availabilityFilter = root.querySelector('[data-inventory-availability]');
    const statusFilter = root.querySelector('[data-inventory-status]');
    const state = { page: 1, q: new URLSearchParams(window.location.search).get('q') || '', availability: '', status: 'any' };
    if (search && state.q) search.value = state.q;

    const loadSummary = async () => {
      try {
        const payload = await api('GET', '/dashboard/property-statistics');
        const s = payload.data;
        summaryEl.innerHTML = [
          ['Total', s.total], ['Published', s.published], ['Available', s.available],
          ['Sold', s.sold], ['Rented', s.rented]
        ].map(([label, value]) => `<div class="estora-kpi"><span class="estora-kpi__label">${label}</span><span class="estora-kpi__value">${Number(value || 0).toLocaleString()}</span></div>`).join('');
      } catch (error) { summaryEl.innerHTML = ''; }
    };

    const load = async () => {
      tableRoot.innerHTML = skeleton(5);
      const params = new URLSearchParams({ page: String(state.page), per_page: '20', status: state.status });
      if (state.q) params.set('q', state.q);
      if (state.availability) params.set('availability', state.availability);
      try {
        const payload = await api('GET', `/properties?${params.toString()}`);
        const items = payload.data || [];
        tableRoot.innerHTML = items.length ? `<div class="estora-tablewrap"><table class="estora-table">
          <thead><tr><th></th><th>Property</th><th>Reference</th><th class="num">Price</th><th>Type</th><th>Availability</th><th>Agent</th><th class="num">Actions</th></tr></thead>
          <tbody>${items.map((item) => `<tr data-property-row="${item.id}">
            <td><img class="estora-table__thumb" src="${escape(item.image.url)}" alt="" width="56" height="40" loading="lazy"></td>
            <td><span class="strong">${escape(item.title)}</span><br><span class="estora-muted">${escape(item.location.district || '')}${item.location.province ? ', ' + escape(item.location.province) : ''} · ${escape(item.status_label)}</span></td>
            <td>${escape(item.reference)}</td>
            <td class="num">${escape(item.price_formatted)}</td>
            <td>${escape(item.type_label)}</td>
            <td><select data-availability="${item.id}" aria-label="Availability">${['available', 'reserved', 'sold', 'rented', 'unavailable'].map((value) => `<option value="${value}" ${item.availability === value ? 'selected' : ''}>${value[0].toUpperCase() + value.slice(1)}</option>`).join('')}</select></td>
            <td>${escape(item.agent ? item.agent.name : 'Unassigned')}</td>
            <td><div class="estora-table__actions"><a class="estora-rowbtn" href="${escape(cfg.urls.property)}${item.id}&action=edit">Edit</a><a class="estora-rowbtn" href="${escape(item.url)}" target="_blank" rel="noopener">View</a></div></td>
          </tr>`).join('')}</tbody></table></div>` : emptyBox('No properties match', 'Adjust the filters or add a property.', `<a class="estora-btn estora-btn--sm" href="${escape(cfg.urls.newProperty)}">Add property</a>`);
        pagination(pagEl, payload.meta, (page) => { state.page = page; load(); });
        tableRoot.querySelectorAll('[data-availability]').forEach((select) => {
          select.addEventListener('change', async () => {
            select.disabled = true;
            try {
              await api('PATCH', `/properties/${select.dataset.availability}`, { availability: select.value });
              toast('Availability updated.');
              loadSummary();
              load();
            } catch (error) {
              toast(error.message, 'error');
              load();
            } finally { select.disabled = false; }
          });
        });
        root.setAttribute('aria-busy', 'false');
      } catch (error) {
        tableRoot.innerHTML = errorBox(error.message);
      }
    };

    search.addEventListener('input', debounce(() => { state.q = search.value.trim(); state.page = 1; load(); }, 350));
    availabilityFilter.addEventListener('change', () => { state.availability = availabilityFilter.value; state.page = 1; load(); });
    statusFilter.addEventListener('change', () => { state.status = statusFilter.value; state.page = 1; load(); });
    loadSummary();
    load();
  }

  /* ---------------- reports ---------------- */
  async function initReports() {
    const root = document.querySelector('[data-screen="reports"]');
    const viewShell = root.closest('.estora-admin') || root;
    const rangeSelect = viewShell.querySelector('[data-report-range]');
    const requestedDays = Number(new URLSearchParams(window.location.search).get('days') || 0);
    const days = [30, 90, 365].includes(requestedDays) ? requestedDays : 30;
    if (rangeSelect) rangeSelect.value = String(days);
    const kpis = root.querySelector('[data-report-kpis]');
    const agentsEl = root.querySelector('[data-report-agents]');
    try {
      const [propertyRes, leadRes, appointmentRes] = await Promise.all([
        api('GET', '/dashboard/property-statistics'),
        api('GET', `/dashboard/lead-statistics?days=${days}`),
        api('GET', '/dashboard/appointment-statistics')
      ]);
      const p = propertyRes.data;
      const l = leadRes.data;
      const a = appointmentRes.data;
      kpis.innerHTML = [
        ['Published listings', p.published], ['Available', p.available], ['Average asking price', money(p.average_price)],
        ['Leads', l.total], ['Conversion', l.conversion_rate + '%'], ['Viewing completion', a.completion_rate + '%']
      ].map(([label, value]) => `<div class="estora-kpi"><span class="estora-kpi__label">${label}</span><span class="estora-kpi__value" style="font-size:1.5rem">${typeof value === 'number' ? value.toLocaleString() : escape(value)}</span></div>`).join('');

      const list = (entries, key = 'name', value = 'total') => entries.length
        ? `<ul class="estora-simplelist">${entries.map((entry) => `<li><span>${escape(entry[key])}</span><span class="num">${Number(entry[value]).toLocaleString()}</span></li>`).join('')}</ul>`
        : '<p class="estora-muted">No data.</p>';

      renderTrend(root.querySelector('[data-report-daily]'), l.daily || []);
      root.querySelector('[data-report-types]').innerHTML = list(p.by_type || []);
      root.querySelector('[data-report-provinces]').innerHTML = list(p.by_province || []);
      root.querySelector('[data-report-sources]').innerHTML = Object.keys(l.by_source || {}).length
        ? `<ul class="estora-simplelist">${Object.entries(l.by_source).map(([name, count]) => `<li><span>${escape(name)}</span><span class="num">${Number(count).toLocaleString()}</span></li>`).join('')}</ul>`
        : '<p class="estora-muted">No leads.</p>';
      root.querySelector('[data-report-weekdays]').innerHTML = list(a.by_weekday || []);

      const performance = Object.values((l.by_agent || []).reduce((acc, row) => {
        acc[row.agent_id] = row;
        return acc;
      }, {}));
      agentsEl.innerHTML = performance.length ? `<div class="estora-tablewrap"><table class="estora-table">
        <thead><tr><th>Agent</th><th class="num">Leads</th><th class="num">Won</th><th class="num">Conversion</th></tr></thead>
        <tbody>${performance.map((row) => `<tr><td class="strong">${escape(row.name)}</td><td class="num">${row.total}</td><td class="num">${row.won}</td><td class="num">${row.total ? ((row.won / row.total) * 100).toFixed(1) + '%' : '—'}</td></tr>`).join('')}</tbody></table></div>` : '<p class="estora-muted">No assigned leads yet.</p>';
      root.setAttribute('aria-busy', 'false');
    } catch (error) {
      kpis.innerHTML = errorBox(error.message);
    }

    rangeSelect?.addEventListener('change', () => {
      window.location.search = `?days=${rangeSelect.value}`;
    });
  }

  /* ---------------- campaigns ---------------- */
  async function initCampaigns() {
    const root = document.querySelector('[data-screen="campaigns"]');
    const tableRoot = root.querySelector('[data-campaign-table]');
    const form = root.querySelector('[data-campaign-form]');

    const load = async () => {
      tableRoot.innerHTML = skeleton(3);
      try {
        const payload = await api('GET', '/campaigns?per_page=50');
        const items = payload.data || [];
        tableRoot.innerHTML = items.length ? `<div class="estora-tablewrap"><table class="estora-table">
          <thead><tr><th>Campaign</th><th>UTM</th><th>Status</th><th class="num">Attributed</th><th class="num">Budget</th><th class="num">Actions</th></tr></thead>
          <tbody>${items.map((item) => `<tr>
            <td><span class="strong">${escape(item.name)}</span><br><span class="estora-muted">${escape(item.channel)}</span></td>
            <td><span class="estora-muted">${escape(item.utm_source)} / ${escape(item.utm_medium)} / ${escape(item.utm_campaign)}</span></td>
            <td>${badge(item.status, item.status === 'active' ? 'badge-ok' : item.status === 'paused' ? 'badge-warning' : 'badge-neutral')}</td>
            <td class="num">${item.attribution_count}</td>
            <td class="num">${item.budget ? money(item.budget) : '—'}</td>
            <td><div class="estora-table__actions">
              ${item.status !== 'active' ? `<button class="estora-rowbtn" data-campaign-status="active" data-id="${item.id}">Activate</button>` : `<button class="estora-rowbtn" data-campaign-status="paused" data-id="${item.id}">Pause</button>`}
              <button class="estora-rowbtn estora-rowbtn--danger" data-campaign-delete="${item.id}">Delete</button>
            </div></td></tr>`).join('')}</tbody></table></div>` : emptyBox('No campaigns yet', 'Create the first UTM campaign on the right.');
        tableRoot.querySelectorAll('[data-campaign-status]').forEach((button) => {
          button.addEventListener('click', async () => {
            try {
              await api('PATCH', `/campaigns/${button.dataset.id}`, { status: button.dataset.campaignStatus });
              toast('Campaign updated.');
              load();
            } catch (error) { toast(error.message, 'error'); }
          });
        });
        tableRoot.querySelectorAll('[data-campaign-delete]').forEach((button) => {
          button.addEventListener('click', async () => {
            if (!window.confirm('Delete this campaign? Attributed leads keep their UTM values.')) return;
            try {
              await api('DELETE', `/campaigns/${button.dataset.campaignDelete}`);
              toast('Campaign deleted.');
              load();
            } catch (error) { toast(error.message, 'error'); }
          });
        });
      } catch (error) { tableRoot.innerHTML = errorBox(error.message); }
    };

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const errorEl = form.querySelector('[data-form-error]');
      const button = form.querySelector('button[type="submit"]');
      errorEl.hidden = true;
      button.disabled = true;
      try {
        await api('POST', '/campaigns', formDataObject(form));
        form.reset();
        toast('Campaign created.');
        load();
      } catch (error) {
        errorEl.hidden = false;
        errorEl.textContent = error.message;
      } finally { button.disabled = false; }
    });

    load();
  }

  /* ---------------- settings ---------------- */
  async function initSettings() {
    const form = document.querySelector('[data-settings-form]');
    if (!form) return;
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const errorEl = form.querySelector('[data-form-error]');
      const button = form.querySelector('button[type="submit"]');
      const data = formDataObject(form);
      data.closed_days = data.closed_days || [];
      data.closed_days = data.closed_days.map((value) => Number(value));
      ['notify_new_lead', 'notify_new_appointment', 'notify_email_enabled'].forEach((flag) => {
        data[flag] = form.querySelector(`[name="${flag}"]`)?.checked ? 1 : 0;
      });
      errorEl.hidden = true;
      button.disabled = true;
      try {
        await api('POST', '/settings', data);
        toast('Settings saved.');
      } catch (error) {
        errorEl.hidden = false;
        errorEl.textContent = error.message;
      } finally { button.disabled = false; }
    });
  }

  /* ---------------- audit ---------------- */
  async function initAudit() {
    const root = document.querySelector('[data-screen="audit"]');
    const tableRoot = root.querySelector('[data-audit-table]');
    const pagEl = root.querySelector('[data-audit-pagination]');
    const actionFilter = root.querySelector('[data-audit-action]');
    const objectFilter = root.querySelector('[data-audit-object]');
    const state = { page: 1 };

    const load = async () => {
      tableRoot.innerHTML = skeleton(5);
      const params = new URLSearchParams({ page: String(state.page), per_page: '30' });
      if (actionFilter.value) params.set('action', actionFilter.value);
      if (objectFilter.value) params.set('object_type', objectFilter.value);
      try {
        const payload = await api('GET', `/audit?${params.toString()}`);
        const items = payload.data || [];
        tableRoot.innerHTML = items.length ? `<div class="estora-tablewrap"><table class="estora-table">
          <thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Object</th><th>Description</th></tr></thead>
          <tbody>${items.map((item) => `<tr>
            <td>${escape(dateFmt(item.created_at, true))}</td>
            <td>${escape(item.actor)}</td>
            <td><code style="font-size:11px">${escape(item.action)}</code></td>
            <td>${escape(item.object_type)}${item.object_id ? ' #' + item.object_id : ''}</td>
            <td>${escape(item.description)}</td>
          </tr>`).join('')}</tbody></table></div>` : emptyBox('No activity for these filters', 'Business events appear here as staff work.');
        pagination(pagEl, payload.meta, (page) => { state.page = page; load(); });
        root.setAttribute('aria-busy', 'false');
      } catch (error) { tableRoot.innerHTML = errorBox(error.message); }
    };

    actionFilter.addEventListener('change', () => { state.page = 1; load(); });
    objectFilter.addEventListener('change', () => { state.page = 1; load(); });
    load();
  }

  /* ---------------- boot ---------------- */
  screens.forEach((root) => {
    const screen = root.dataset.screen;
    if (screen === 'dashboard') initDashboard(root);
    if (screen === 'leads') initLeads();
    if (screen === 'lead') initLeadPage();
    if (screen === 'appointments') initAppointments();
    if (screen === 'inventory') initInventory();
    if (screen === 'reports') initReports();
    if (screen === 'campaigns') initCampaigns();
    if (screen === 'settings') initSettings();
    if (screen === 'audit') initAudit();
  });
})();
